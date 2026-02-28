<?php
// ==========================
// batch_view.php (Traveller)
// ==========================
$pageTitle = "Batch Viewer";
require "../config/database.php";
require "../config/header.php";

$batchId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT b.*, p.product_code, pf.flow_name AS flow_name, pf.version
    FROM batches b
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE b.id = ?
");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) die('Batch not found');

// Cells
$cells = $pdo->prepare("SELECT * FROM cells WHERE batch_id = ? ORDER BY id");
$cells->execute([$batchId]);
$cells = $cells->fetchAll(PDO::FETCH_ASSOC);

// Steps
$steps = $pdo->prepare("
    SELECT s.step_order, ps.id AS step_id, ps.step_name AS step_name
    FROM process_flow_steps s
    JOIN process_steps ps ON s.process_step_id = ps.id 
    WHERE s.process_flow_id = ?
    ORDER BY s.step_order
");
$steps->execute([$batch['process_flow_id']]);
$steps = $steps->fetchAll(PDO::FETCH_ASSOC);

// Batch-level parameters
$batchParamsStmt = $pdo->prepare("
    SELECT bsd.*, p.parameter_name, p.unit, ps.step_name AS step_name
    FROM batch_step_data bsd
    JOIN process_step_parameters p ON p.id = bsd.parameter_id
    JOIN process_steps ps ON ps.id = bsd.process_step_id
    WHERE bsd.batch_id = ?
    ORDER BY bsd.process_step_id, p.id
");
$batchParamsStmt->execute([$batchId]);
$batchParamsRaw = $batchParamsStmt->fetchAll(PDO::FETCH_ASSOC);
$batchParams = [];
foreach ($batchParamsRaw as $p) $batchParams[$p['process_step_id']][] = $p;

// Cell-level parameters
$cellParamsStmt = $pdo->prepare("
    SELECT csd.*, p.parameter_name, p.unit
    FROM cell_step_data csd
    JOIN process_step_parameters p ON p.id = csd.parameter_id
    WHERE csd.batch_id = ?
    ORDER BY csd.process_step_id, csd.cell_serial, p.id
");
$cellParamsStmt->execute([$batchId]);
$cellParamsRaw = $cellParamsStmt->fetchAll(PDO::FETCH_ASSOC);
$cellParams = [];
foreach ($cellParamsRaw as $cp) $cellParams[$cp['process_step_id']][$cp['cell_serial']][] = $cp;

// Scrapped cells
$scrapStmt = $pdo->prepare("
    SELECT cs.*, ps.step_name AS step_name
    FROM cell_scrap cs
    JOIN process_steps ps ON ps.id = cs.process_step_id
    WHERE cs.batch_id = ?
    ORDER BY cs.process_step_id, cs.cell_serial
");
$scrapStmt->execute([$batchId]);
$scrapsRaw = $scrapStmt->fetchAll(PDO::FETCH_ASSOC);
$scraps = [];
foreach ($scrapsRaw as $s) $scraps[$s['process_step_id']][] = $s;
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
.timeline-step { border-left: 4px solid #0d6efd; padding: 15px; margin-bottom: 20px; position: relative; }
.timeline-step::before { content: ''; width: 12px; height: 12px; background: #0d6efd; border-radius: 50%; position: absolute; left: -8px; top: 15px; }
.pass { background-color:#d1e7dd; }
.fail { background-color:#f8d7da; }
.collapse-header { cursor: pointer; color:#0d6efd; text-decoration: underline; }
</style>
<script>
function toggleCollapse(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'table' : 'none';
}
</script>

<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <h2>Batch Traveller – <?= htmlspecialchars($batch['batch_code']) ?></h2>
        <a href="batch_execute.php?batch_id=<?= $batch['id'] ?>" class="btn btn-primary">Move Batch</a>
    </div>

    <div class="mb-4">
        <ul>
            <li><strong>Product:</strong> <?= htmlspecialchars($batch['product_code']) ?></li>
            <li><strong>Flow:</strong> <?= htmlspecialchars($batch['flow_name']) ?> v<?= $batch['version'] ?></li>
            <li><strong>Status:</strong> <?= htmlspecialchars($batch['status']) ?></li>
            <li><strong>Created:</strong> <?= $batch['created_at'] ?></li>
            <li><strong>Started:</strong> <?= $batch['started_at'] ?></li>
            <li><strong>Completed:</strong> <?= $batch['completed_at'] ?></li>
        </ul>
    </div>

    <h3>Cells</h3>
    <table class="table table-bordered table-sm mb-4">
        <thead><tr><th>Serial</th><th>Status</th></tr></thead>
        <tbody>
            <?php foreach ($cells as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['cell_code']) ?></td>
                <td><?= htmlspecialchars($c['status']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Process Flow Timeline</h3>
    <?php foreach ($steps as $step): ?>
        <div class="timeline-step">
            <h5><?= htmlspecialchars($step['step_name']) ?></h5>

            <?php if(!empty($batchParams[$step['step_id']])): ?>
                <p class="collapse-header" onclick="toggleCollapse('batch_params_<?= $step['step_id'] ?>')">Batch-level Parameters</p>
                <table class="table table-bordered table-sm" id="batch_params_<?= $step['step_id'] ?>" style="display:none">
                    <thead><tr><th>Parameter</th><th>Value</th><th>Pass/Fail</th><th>Unit</th></tr></thead>
                    <tbody>
                        <?php foreach ($batchParams[$step['step_id']] as $p): ?>
                        <tr class="<?= strtolower($p['pass_fail']) ?>">
                            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
                            <td><?= $p['value'] ?></td>
                            <td><?= $p['pass_fail'] ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if(!empty($cellParams[$step['step_id']])): ?>
                <p class="collapse-header" onclick="toggleCollapse('cell_params_<?= $step['step_id'] ?>')">Cell-level Parameters</p>
                <table class="table table-bordered table-sm" id="cell_params_<?= $step['step_id'] ?>" style="display:none">
                    <thead><tr><th>Cell</th><th>Parameter</th><th>Value</th><th>Pass/Fail</th><th>Unit</th></tr></thead>
                    <tbody>
                        <?php foreach ($cellParams[$step['step_id']] as $cellSerial => $params): ?>
                            <?php foreach ($params as $p): ?>
                            <tr class="<?= strtolower($p['pass_fail']) ?>">
                                <td><?= htmlspecialchars($cellSerial) ?></td>
                                <td><?= htmlspecialchars($p['parameter_name']) ?></td>
                                <td><?= $p['value'] ?></td>
                                <td><?= $p['pass_fail'] ?></td>
                                <td><?= htmlspecialchars($p['unit']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if(!empty($scraps[$step['step_id']])): ?>
                <p class="collapse-header" onclick="toggleCollapse('scrap_<?= $step['step_id'] ?>')">Scrapped Cells</p>
                <table class="table table-bordered table-sm" id="scrap_<?= $step['step_id'] ?>" style="display:none">
                    <thead><tr><th>Cell</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($scraps[$step['step_id']] as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['cell_serial']) ?></td>
                            <td><?= htmlspecialchars($s['reason']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>
</div>

<?php require "../config/footer.php"; ?>