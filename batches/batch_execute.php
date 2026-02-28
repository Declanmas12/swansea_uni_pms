<?php
// =====================================================
// batch_execute.php (REDESIGNED – LINEAR EXECUTION)
// =====================================================
$pageTitle = "Batch Execute";
require "../config/database.php";
require "../config/header.php";

$batchId = (int)($_GET['batch_id'] ?? 0);

// Fetch batch
$stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) die('Batch not found');

// Fetch flow steps
$stepsStmt = $pdo->prepare("SELECT pfs.process_step_id, ps.step_name, pfs.step_order 
    FROM process_flow_steps pfs 
    JOIN process_steps ps 
    ON ps.id = pfs.process_step_id 
    WHERE pfs.process_flow_id = ? 
    ORDER BY pfs.step_order");
$stepsStmt->execute([$batch['process_flow_id']]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialise batch_step_execution rows if missing
foreach ($steps as $step) {
    $pdo->prepare("INSERT IGNORE INTO batch_step_execution (batch_id, process_step_id) 
    VALUES (?, ?)")->execute([$batchId, $step['process_step_id']]);
}

// Determine current step
$currentStepStmt = $pdo->prepare("SELECT bse.*, ps.step_name 
    FROM batch_step_execution bse 
    JOIN process_steps ps 
    ON ps.id = bse.process_step_id 
    WHERE bse.batch_id = ? 
    AND bse.status != 'COMPLETE' 
    ORDER BY bse.id LIMIT 1");
$currentStepStmt->execute([$batchId]);
$currentStep = $currentStepStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentStep) {
    // Prevent access to step-dependent logic
    $paramFailures = false;
} else {

// Fetch active cells
$cellsStmt = $pdo->prepare("SELECT * FROM cells WHERE batch_id = ? AND status != 'SCRAPPED'");
$cellsStmt->execute([$batchId]);
$cells = $cellsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch parameters for current step
$paramsStmt = $pdo->prepare("SELECT * FROM process_step_parameters WHERE process_step_id = ? ORDER BY scope, id");
$paramsStmt->execute([$currentStep['process_step_id']]);
$params = $paramsStmt->fetchAll(PDO::FETCH_ASSOC);

$batchParams = array_filter($params, fn($p) => $p['scope'] === 'BATCH');
$cellParams  = array_filter($params, fn($p) => $p['scope'] === 'CELL');

$paramFailures = $_SESSION['param_failures'] ?? false;
unset($_SESSION['param_failures']);

// AFTER save_params logic, re-check failures from DB
$failCheck = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT pass_fail FROM batch_step_data WHERE batch_id=? AND process_step_id=? AND pass_fail='FAIL'
        UNION ALL
        SELECT pass_fail FROM cell_step_data WHERE batch_id=? AND process_step_id=? AND pass_fail='FAIL'
    ) f
");
$failCheck->execute([$batchId, $currentStep['process_step_id'], $batchId, $currentStep['process_step_id']]);
$paramFailures = ((int)$failCheck->fetchColumn() > 0);

// Check required batch-level parameters
$missingBatchParamsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM process_step_parameters p
    LEFT JOIN batch_step_data bsd ON bsd.parameter_id = p.id AND bsd.batch_id = ? AND bsd.process_step_id = ?
    WHERE p.process_step_id = ? AND p.scope = 'BATCH' AND p.required = 1 AND bsd.value IS NULL
");
$missingBatchParamsStmt->execute([$batchId, $currentStep['process_step_id'], $currentStep['process_step_id']]);
$missingBatchParams = (int)$missingBatchParamsStmt->fetchColumn();

// Final failure flag
$paramFailures = $paramFailures || ($missingBatchParams > 0);
};

// ------------------ HANDLE POST ACTIONS ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pdo->beginTransaction();

    try {

        $bseId = $_POST['bse_id'] ?? null;
        $comment = $_POST['comment'] ?? null;
        if (!$bseId) {
            throw new Exception('Batch step ID missing.');
        }

        // --------------------------------------------------
        // START STEP
        // --------------------------------------------------
        if (isset($_POST['start_step'])) {

            $pdo->prepare("
                UPDATE batch_step_execution
                SET status = 'IN_PROGRESS',
                    started_at = NOW()
                WHERE id = ?
            ")->execute([$bseId]);

            // Activate batch if not already active
            $pdo->prepare("
                UPDATE batches
                SET status = 'active',
                    started_at = IFNULL(started_at, NOW())
                WHERE id = ?
            ")->execute([$batchId]);
        }

        // --------------------------------------------------
        // SAVE PARAMETERS (BATCH + CELL)
        // --------------------------------------------------
        if (isset($_POST['save_params'])) {

            $paramsStmt = $pdo->prepare("
                SELECT *
                FROM process_step_parameters
                WHERE process_step_id = ?
            ");
            $paramsStmt->execute([$currentStep['process_step_id']]);
            $params = $paramsStmt->fetchAll(PDO::FETCH_ASSOC);

            $batchParams = array_filter($params, fn($p) => $p['scope'] === 'BATCH');
            $cellParams  = array_filter($params, fn($p) => $p['scope'] === 'CELL');

            // ---------- Batch parameters ----------
            foreach ($batchParams as $param) {

                $raw = $_POST['batch_param'][$param['id']] ?? null;

                if ($raw === null || trim($raw) === '') {
                    continue; // missing handled later by gating
                }

                if (!is_numeric($raw)) {
                    continue;
                }

                $value = (float)$raw;
                $passFail = ($value >= $param['min_value'] && $value <= $param['max_value'])
                    ? 'PASS'
                    : 'FAIL';

                $pdo->prepare("
                    INSERT INTO batch_step_data
                        (batch_id, process_step_id, parameter_id, value, pass_fail)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        value = VALUES(value),
                        pass_fail = VALUES(pass_fail)
                ")->execute([
                    $batchId,
                    $currentStep['process_step_id'],
                    $param['id'],
                    $value,
                    $passFail
                ]);
            }

            // ---------- Cell parameters ----------
            foreach ($cells as $cell) {
                foreach ($cellParams as $param) {

                    $key = $cell['cell_serial'] . '_' . $param['id'];
                    $raw = $_POST['cell_param'][$key] ?? null;

                    if ($raw === null || trim($raw) === '') {
                        continue;
                    }

                    if (!is_numeric($raw)) {
                        continue;
                    }

                    $value = (float)$raw;
                    $passFail = ($value >= $param['min_value'] && $value <= $param['max_value'])
                        ? 'PASS'
                        : 'FAIL';

                    $pdo->prepare("
                        INSERT INTO cell_step_data
                            (batch_id, process_step_id, cell_serial, parameter_id, value, pass_fail)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            value = VALUES(value),
                            pass_fail = VALUES(pass_fail)
                    ")->execute([
                        $batchId,
                        $currentStep['process_step_id'],
                        $cell['cell_serial'],
                        $param['id'],
                        $value,
                        $passFail
                    ]);
                }
            }
        }

        // --------------------------------------------------
        // SCRAP CELLS
        // --------------------------------------------------
        if (isset($_POST['submit_scrap'])) {

            if ($currentStep['status'] !== 'IN_PROGRESS') {
                throw new Exception('Cells can only be scrapped while step is in progress.');
            }

            foreach ($_POST['scrap'] ?? [] as $cellSerial => $on) {

                $reason = trim($_POST['reason'][$cellSerial] ?? '');
                if ($reason === '') {
                    throw new Exception("Scrap reason required for cell $cellSerial");
                }

                // Prevent double scrap
                $exists = $pdo->prepare("
                    SELECT COUNT(*) FROM cell_scrap
                    WHERE batch_id=? AND cell_serial=?
                ");
                $exists->execute([$batchId, $cellSerial]);
                if ((int)$exists->fetchColumn() > 0) {
                    continue;
                }

                // Record scrap
                $pdo->prepare("
                    INSERT INTO cell_scrap
                        (batch_id, process_step_id, cell_serial, reason)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $batchId,
                    $currentStep['process_step_id'],
                    $cellSerial,
                    $reason
                ]);

                // Update cell status
                $pdo->prepare("
                    UPDATE cells
                    SET status='scrapped'
                    WHERE batch_id=? AND cell_code=?
                ")->execute([$batchId, $cellSerial]);
            }
        }

        // --------------------------------------------------
        // PARAMETER GATING (AUTHORITATIVE DB CHECK)
        // --------------------------------------------------

        // FAIL values
        $failStmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT pass_fail FROM batch_step_data
                WHERE batch_id=? AND process_step_id=? AND pass_fail='FAIL'
                UNION ALL
                SELECT pass_fail FROM cell_step_data
                WHERE batch_id=? AND process_step_id=? AND pass_fail='FAIL'
            ) f
        ");
        $failStmt->execute([
            $batchId,
            $currentStep['process_step_id'],
            $batchId,
            $currentStep['process_step_id']
        ]);
        $hasFails = ((int)$failStmt->fetchColumn() > 0);

        // Missing required batch params
        $missingStmt = $pdo->prepare("
            SELECT COUNT(*) FROM process_step_parameters p
            LEFT JOIN batch_step_data bsd
                ON bsd.parameter_id = p.id
               AND bsd.batch_id = ?
               AND bsd.process_step_id = ?
            WHERE p.process_step_id = ?
              AND p.scope = 'BATCH'
              AND p.required = 1
              AND bsd.value IS NULL
        ");
        $missingStmt->execute([
            $batchId,
            $currentStep['process_step_id'],
            $currentStep['process_step_id']
        ]);
        $missingRequired = ((int)$missingStmt->fetchColumn() > 0);

        $paramFailures = $hasFails || $missingRequired;

        // --------------------------------------------------
        // COMPLETE STEP (HARD GATED)
        // --------------------------------------------------
        if (isset($_POST['complete_step'])) {

            if ($paramFailures) {
                throw new Exception(
                    'Cannot complete step: missing or failing parameters detected.'
                );
            }

            $pdo->prepare("
                UPDATE batch_step_execution
                SET status='COMPLETE',
                    completed_at=NOW(),
                    comment=?,
                    signed_by=?
                WHERE id=?
            ")->execute([
                $comment,
                $_POST['signed_by'] ?? 'SYSTEM',
                $bseId
            ]);

            // Check if batch is fully complete
            $remaining = $pdo->prepare("
                SELECT COUNT(*)
                FROM batch_step_execution
                WHERE batch_id=? AND status!='COMPLETE'
            ");
            $remaining->execute([$batchId]);

            if ((int)$remaining->fetchColumn() === 0) {
                $pdo->prepare("
                    UPDATE batches
                    SET status='completed',
                        completed_at=NOW()
                    WHERE id=?
                ")->execute([$batchId]);

                $pdo->prepare("
                    UPDATE cells
                    SET status = 'complete',
                        inventory_status = 'AVAILABLE',
                        inventory_location = 'FG_CELL_STORES',
                        inventory_added_at = NOW()
                    WHERE batch_id = ?
                    AND status != 'SCRAPPED'
                ")->execute([$batchId]);

                $historyStmt = $pdo->prepare("
                    INSERT INTO cell_inventory_history
                    (cell_code, from_state, to_state, reference, changed_by)
                    SELECT cell_code, 'IN_PROCESS', 'AVAILABLE', ?, ?
                    FROM cells
                    WHERE batch_id = ? AND status != 'SCRAPPED'
                ");

                $historyStmt->execute([
                    'Batch ' . $batchCode . ' completed',
                    $_SESSION['username'] ?? 'SYSTEM',
                    $batchId
                ]);
            }
        }

        $pdo->commit();
        header("Location: batch_execute.php?batch_id=$batchId");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo '<p style="color:red;"><strong>' .
             htmlspecialchars($e->getMessage()) .
             '</strong></p>';
    }
}
?>

<style>
.step-box { border:1px solid #ccc; padding:15px; margin-bottom:20px; }
.scrap-table { margin-top:10px; width:100%; }
.param-table { width:100%; border-collapse:collapse; margin-top:10px; }
.param-table th, .param-table td { border:1px solid #ccc; padding:6px; }
.fail { background:#f8d7da; }
.pass { background:#d1e7dd; }
</style>
<script>
function toggleScrap() {
    const el = document.getElementById('scrapTable');
    el.style.display = el.style.display === 'none' ? 'table' : 'none';
}
</script>


<h1>Batch Execution – <?= htmlspecialchars($batch['batch_code']) ?></h1>

<?php if (!$currentStep): ?>
    <h2>Batch Complete</h2>
    <p>All steps have been completed.</p>
<?php else: ?>
    <div class="step-box">
        <h2>Current Step: <?= htmlspecialchars($currentStep['step_name']) ?></h2>
        <p>Status: <strong><?= $currentStep['status'] ?></strong></p>

        <form method="post">
            <input type="hidden" name="bse_id" value="<?= $currentStep['id'] ?>">

            <?php if ($currentStep['status'] === 'PENDING'): ?>
                <button class="btn btn-primary" name="start_step">Start Step</button>
            <?php endif; ?>

            <?php if ($currentStep['status'] === 'IN_PROGRESS'): ?>
                <h3>Parameters</h3>
                <?php if ($batchParams): ?>
                    <h5>Batch Parameters</h5>
                    <table class="param-table">
                        <tr>
                            <?php foreach ($batchParams as $p): ?>
                                <th><?= htmlspecialchars($p['parameter_name']) ?><br>(<?= $p['min_value'] ?>–<?= $p['max_value'] ?> <?= htmlspecialchars($p['unit']) ?>)</th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($batchParams as $p): ?>
                                <?php
                                $existing = $pdo->prepare("SELECT value, pass_fail FROM batch_step_data WHERE batch_id=? AND process_step_id=? AND parameter_id=?");
                                $existing->execute([$batchId, $currentStep['process_step_id'], $p['id']]);
                                $ex = $existing->fetch(PDO::FETCH_ASSOC);
                                $class = $ex ? strtolower($ex['pass_fail']) : '';
                                ?>
                                <td class="<?= $class ?>">
                                    <input type="number" step="any" name="batch_param[<?= $p['id'] ?>]" value="<?= $ex['value'] ?? '' ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                <?php endif ?>
                <br>
                <?php if ($cellParams): ?>
                    <h5>Cell Parameters</h5>
                    <table class="param-table">
                    <tr>
                        <th>Cell</th>
                        <?php foreach ($cellParams as $p): ?>
                            <th><?= htmlspecialchars($p['parameter_name']) ?><br>(<?= $p['min_value'] ?>–<?= $p['max_value'] ?> <?= htmlspecialchars($p['unit']) ?>)</th>
                        <?php endforeach; ?>
                    </tr>
                    <?php foreach ($cells as $cell): ?>
                        <tr>
                            <td><?= htmlspecialchars($cell['cell_code']) ?></td>
                            <?php foreach ($cellParams as $p): ?>
                                <?php
                                $existing = $pdo->prepare("SELECT value, pass_fail FROM cell_step_data WHERE batch_id=? AND process_step_id=? AND cell_serial=? AND parameter_id=?");
                                $existing->execute([$batchId, $currentStep['process_step_id'], $cell['cell_code'], $p['id']]);
                                $ex = $existing->fetch(PDO::FETCH_ASSOC);
                                $class = $ex ? strtolower($ex['pass_fail']) : '';
                                ?>
                                <td class="<?= $class ?>">
                                    <input type="number" step="any" name="cell_param[<?= $cell['cell_code'] . '_' . $p['id'] ?>]" value="<?= $ex['value'] ?? '' ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                <?php endif ?>
                <br>
                <button type="submit" name="save_params" class="btn btn-primary">
                    Save Parameters
                </button>
                <br><br>
                <a class="btn btn-primary" href="../cells/cell_inventory.php?search=<?=$batch['batch_code']?>">Rename Cells</a>
                <button class="btn btn-primary" type="button" onclick="toggleScrap()">Scrap Cells</button><br><br>
                <br>
                <div id="scrapTable" style="display:none;">
                    <table class="scrap-table table table-bordered" style="text-align:center;">
                        <tr><th>Scrap</th><th>Cell</th><th>Reason</th></tr>
                        <?php foreach ($cells as $cell): ?>
                        <tr VALIGN="MIDDLE">
                            <td><input type="checkbox" name="scrap[<?= $cell['cell_code'] ?>]"></td>
                            <td><?= htmlspecialchars($cell['cell_code']) ?></td>
                            <td><textarea style="width:100%" name="reason[<?= $cell['cell_code'] ?>]" rows="2" cols="30"></textarea></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <button type="submit" name="submit_scrap" class="btn btn-danger">
                        Confirm Scrap
                    </button>
                </div>
                <br>
                <p><strong>Comment</strong></p>
                <textarea name="comment" style="width:100%"></textarea>
                <br><br>
                <?php if ($paramFailures): ?>
                    <p style="color:red;"><strong>Step cannot be completed: parameter failures detected.</strong></p>
                <?php else: ?>
                    <input type="hidden" name="signed_by" value="<?= $_SESSION['user_name'] ?? 'SYSTEM' ?>">
                    <button class="btn btn-primary" name="complete_step">Complete Step (Sign Off)</button>
                <?php endif ?>
            <?php endif ?>
        </form>
    </div>
<?php endif ?>

<?php require "../config/footer.php"; ?>