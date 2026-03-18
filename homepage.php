<?php
// ==========================
// homepage.php (Dashboard / Homepage)
// ==========================
$pageTitle = "Production Dashboard";
require "config/database.php";
require "config/auth.php";
require "config/header.php";

// ----------------- Fetch Active Batches -----------------
$activeBatchesStmt = $pdo->prepare("
    SELECT b.id, b.batch_code, b.status, b.started_at, b.completed_at,
           p.product_code, pf.flow_name AS flow_name
    FROM batches b
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE b.status IN ('planned','active')
    ORDER BY b.started_at ASC
");
$activeBatchesStmt->execute();
$activeBatches = $activeBatchesStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Fetch Recently Completed -----------------
$recentCompletedStmt = $pdo->prepare("
    SELECT b.id, b.batch_code, b.completed_at,
           p.product_code, pf.flow_name AS flow_name
    FROM batches b
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE b.status='completed'
    ORDER BY b.completed_at DESC
    LIMIT 10
");
$recentCompletedStmt->execute();
$recentCompleted = $recentCompletedStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Fetch Alerts -----------------
// Scrapped cells
$scrapAlertStmt = $pdo->prepare("
    SELECT DISTINCT b.id, b.batch_code, p.product_code, pf.flow_name AS flow_name
    FROM cell_scrap cs
    JOIN batches b ON b.id = cs.batch_id
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE b.status IN ('planned','active')
");
$scrapAlertStmt->execute();
$scrapAlerts = $scrapAlertStmt->fetchAll(PDO::FETCH_ASSOC);

// Parameter failures
$paramFailStmt = $pdo->prepare("
    SELECT DISTINCT b.id, b.batch_code, p.product_code, pf.flow_name AS flow_name
    FROM cell_step_data csd
    JOIN batches b ON b.id = csd.batch_id
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE csd.pass_fail='FAIL' AND b.status IN ('planned','active')
");
$paramFailStmt->execute();
$paramFailAlerts = $paramFailStmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------- Summary Stats for Charts -----------------
$batchStatusStmt = $pdo->query("
    SELECT status, COUNT(*) AS count
    FROM batches
    GROUP BY status
");
$batchStatusData = $batchStatusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Yield calculation: % of passed cells
$yieldStmt = $pdo->query("
    SELECT 
        b.id AS batch_id,
        b.batch_code,
        100 * SUM(CASE WHEN csd.pass_fail='PASS' THEN 1 ELSE 0 END) / COUNT(*) AS yield
    FROM batches b
    JOIN cell_step_data csd ON csd.batch_id = b.id
    GROUP BY b.id
");
$yieldData = $yieldStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container py-4">
    <div class="row mb-3">
        <!-- Quick Actions -->
        <div class="col-md-12 d-flex justify-content-center gap-2 flex-wrap">
            <a href="../batches/batches_list.php" class="btn btn-success">Batches</a>
            <a href="../products/products_list.php" class="btn btn-primary">Products</a>
            <a href="../process/process_flows.php" class="btn btn-warning">Process Flows</a>
            <a href="../process/process_steps.php" class="btn btn-dark">Process Steps</a>
            <a href="/cells/" class="btn btn-info">Cells</a>
            <a href="../modules/module_list.php" class="btn btn-secondary">Arrays</a>
            <a href="/equipment/" class="btn btn-primary">Equipment</a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($scrapAlerts || $paramFailAlerts): ?>
    <div class="row mb-4">
        <?php if ($scrapAlerts): ?>
        <div class="col-md-6">
            <div class="alert alert-warning">
                <h5>Scrap Alerts</h5>
                <ul>
                <?php foreach ($scrapAlerts as $s): ?>
                    <li><a href="batch_view.php?id=<?= $s['id'] ?>">
                        <?= htmlspecialchars($s['batch_code']) ?> (<?= htmlspecialchars($s['product_code']) ?> - <?= htmlspecialchars($s['flow_name']) ?>)
                    </a></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($paramFailAlerts): ?>
        <div class="col-md-6">
            <div class="alert alert-danger">
                <h5>Parameter Failures</h5>
                <ul>
                <?php foreach ($paramFailAlerts as $f): ?>
                    <li><a href="batch_view.php?id=<?= $f['id'] ?>">
                        <?= htmlspecialchars($f['batch_code']) ?> (<?= htmlspecialchars($f['product_code']) ?> - <?= htmlspecialchars($f['flow_name']) ?>)
                    </a></li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <!-- Active Batches -->
        <div class="mb-4">
            <h4>Active Batches</h4>
            <table class="table table-striped table-bordered text-center">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Product</th>
                        <th>Flow</th>
                        <th>Status</th>
                        <th>Current Step</th>
                        <th>Progress</th>
                        <th>KPI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch active batches
                    $activeBatchesStmt = $pdo->prepare("
                        SELECT b.*, p.product_code, pf.flow_name
                        FROM batches b
                        JOIN products p ON p.id = b.product_id
                        JOIN process_flows pf ON pf.id = b.process_flow_id
                        WHERE b.status='active'
                        ORDER BY b.created_at DESC
                    ");
                    $activeBatchesStmt->execute();
                    $activeBatches = $activeBatchesStmt->fetchAll(PDO::FETCH_ASSOC);

                    // If there are active batches, compute KPIs
                    if ($activeBatches) {
                        $batchIds = array_column($activeBatches, 'id');
                        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));

                        // Current step per batch
                        $currentStepStmt = $pdo->prepare("
                            SELECT 
                                s.process_flow_id,
                                s.step_order,
                                ps.step_name,
                                bse.batch_id,
                                bse.status
                            FROM process_flow_steps s
                            JOIN process_steps ps ON ps.id = s.process_step_id
                            LEFT JOIN batch_step_execution bse
                                ON bse.process_step_id = s.process_step_id
                                AND bse.batch_id IN ($placeholders)
                            ORDER BY s.process_flow_id, s.step_order
                        ");
                        $currentStepStmt->execute($batchIds);
                        $rows = $currentStepStmt->fetchAll(PDO::FETCH_ASSOC);

                        $currentSteps = [];

                        foreach ($rows as $r) {
                            $batchId = $r['batch_id'];

                            // If no execution row OR not complete → this is the current step
                            if (!isset($currentSteps[$batchId]) &&
                                ($r['status'] === null || $r['status'] !== 'COMPLETE')) {
                                $currentSteps[$batchId] = $r['step_name'];
                            }
                        }

                        $progressStmt = $pdo->prepare("
                            SELECT 
                                b.id AS batch_id,
                                COUNT(DISTINCT pfs.process_step_id) AS total_steps,
                                SUM(CASE WHEN bse.status = 'COMPLETE' THEN 1 ELSE 0 END) AS completed_steps
                            FROM batches b
                            JOIN process_flow_steps pfs ON pfs.process_flow_id = b.process_flow_id
                            LEFT JOIN batch_step_execution bse
                                ON bse.batch_id = b.id
                                AND bse.process_step_id = pfs.process_step_id
                            WHERE b.id IN ($placeholders)
                            GROUP BY b.id
                        ");
                        $progressStmt->execute($batchIds);
                        $progressData = $progressStmt->fetchAll(PDO::FETCH_ASSOC);

                        $progressMap = [];
                        foreach ($progressData as $p) {
                            $progressMap[$p['batch_id']] = [
                                'completed' => (int)$p['completed_steps'],
                                'total'     => (int)$p['total_steps']
                            ];
                        }

                        // Failed parameters (cell + batch)
                        $failStmt = $pdo->prepare("
                            SELECT batch_id, COUNT(*) AS fails
                            FROM (
                                SELECT batch_id FROM cell_step_data WHERE pass_fail='FAIL' AND batch_id IN ($placeholders)
                                UNION ALL
                                SELECT batch_id FROM batch_step_data WHERE pass_fail='FAIL' AND batch_id IN ($placeholders)
                            ) f
                            GROUP BY batch_id
                        ");
                        $failStmt->execute(array_merge($batchIds, $batchIds)); // duplicate array to match 2 INs
                        $fails = $failStmt->fetchAll(PDO::FETCH_KEY_PAIR); // batch_id => fail_count

                        // Scrapped cells count
                        $scrapStmt = $pdo->prepare("
                            SELECT batch_id, COUNT(*) AS scrapped
                            FROM cell_scrap
                            WHERE batch_id IN ($placeholders)
                            GROUP BY batch_id
                        ");
                        $scrapStmt->execute($batchIds);
                        $scraps = $scrapStmt->fetchAll(PDO::FETCH_KEY_PAIR); // batch_id => scrapped_count

                        // Render rows
                        foreach ($activeBatches as $b) {
                            $currStep = $currentSteps[$b['id']] ?? 'Not Started';
                            $failCount = $fails[$b['id']] ?? 0;
                            $scrapCount = $scraps[$b['id']] ?? 0;

                            $statusClass = match($b['status']) {
                                'planned' => 'secondary',
                                'active' => 'primary',
                                'completed' => 'success',
                                'aborted' => 'danger',
                                default => 'light',
                            };

                            $completed = $progressMap[$b['id']]['completed'] ?? 0;
                            $total     = max(1, $progressMap[$b['id']]['total'] ?? 1);
                            $percent   = round(($completed / $total) * 100);

                            ?>
                            <tr>
                                <td><a href="batches/batch_view.php?id=<?= $b['id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></a></td>
                                <td><?= htmlspecialchars($b['product_code']) ?></td>
                                <td><?= htmlspecialchars($b['flow_name']) ?></td>
                                <td><span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($b['status']) ?></span></td>
                                <td><?= htmlspecialchars($currStep) ?></td>
                                <td style="min-width:160px;">
                                    <div class="progress" style="height: 18px;">
                                        <div
                                            class="progress-bar <?= $percent === 100 ? 'bg-success' : 'bg-info' ?>"
                                            role="progressbar"
                                            style="width: <?= $percent ?>%;"
                                            aria-valuenow="<?= $percent ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            <?= $completed ?> / <?= $total ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($failCount): ?>
                                        <span class="badge bg-danger">Failed Params: <?= $failCount ?></span>
                                    <?php endif; ?>
                                    <?php if ($scrapCount): ?>
                                        <span class="badge bg-warning text-dark">Scrap: <?= $scrapCount ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <!-- Recently Completed -->
        <div>
            <h4>Recently Completed Batches</h4>
            <table class="table table-bordered table-sm table-hover text-center">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Product</th>
                        <th>Flow</th>
                        <th>Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCompleted as $b): ?>
                    <tr VALIGN="MIDDLE">
                        <td><a href="batches/batch_summary.php?batch_id=<?= $b['id'] ?>"><?= htmlspecialchars($b['batch_code']) ?></a></td>
                        <td><?= htmlspecialchars($b['product_code']) ?></td>
                        <td><?= htmlspecialchars($b['flow_name']) ?></td>
                        <td><?= $b['completed_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require "config/footer.php"; ?>