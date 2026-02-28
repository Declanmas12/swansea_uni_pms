<?php
$pageTitle = "Batch Timeline";
require "../config/database.php";
require "../config/header.php";

$batchId = (int)($_GET['batch_id'] ?? 0);
if (!$batchId) die('Batch ID required.');

// Fetch batch info
$stmt = $pdo->prepare("
    SELECT b.*, p.product_code, p.description AS product_desc, pf.flow_name AS flow_name
    FROM batches b
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE b.id = ?
");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) die('Batch not found');

// Fetch all steps for this batch
$stepsStmt = $pdo->prepare("
    SELECT bse.*, ps.step_name
    FROM batch_step_execution bse
    JOIN process_steps ps ON ps.id = bse.process_step_id
    WHERE bse.batch_id = ?
    ORDER BY bse.id
");
$stepsStmt->execute([$batchId]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch batch-level parameters (all steps)
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

// Organize batch params by step
$batchParams = [];
foreach ($batchParamsRaw as $p) {
    $batchParams[$p['process_step_id']][] = $p;
}

// Fetch cell-level parameters
$cellParamsStmt = $pdo->prepare("
    SELECT cs.*, p.parameter_name, p.unit
    FROM cell_step_data cs
    JOIN process_step_parameters p ON p.id = cs.parameter_id
    WHERE cs.batch_id = ?
    ORDER BY cs.process_step_id, cs.cell_serial, p.id
");
$cellParamsStmt->execute([$batchId]);
$cellParamsRaw = $cellParamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Organize cell params by step and cell
$cellParams = [];
foreach ($cellParamsRaw as $cp) {
    $cellParams[$cp['process_step_id']][$cp['cell_serial']][] = $cp;
}

// Fetch scrapped cells
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
foreach ($scrapsRaw as $s) {
    $scraps[$s['process_step_id']][] = $s;
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
    .timeline-step { border-left: 4px solid #007bff; padding: 15px; margin-bottom: 20px; position: relative; }
    .timeline-step::before {
        content: '';
        width: 12px; height: 12px;
        background: #007bff;
        border-radius: 50%;
        position: absolute;
        left: -8px; top: 15px;
    }
    .fail { background-color:#f8d7da; }
    .pass { background-color:#d1e7dd; }
    .collapse-header { cursor: pointer; color: #007bff; text-decoration: underline; }
</style>
<script>
    function toggleCollapse(id) {
        const el = document.getElementById(id);
        if(el.style.display === 'none') el.style.display='table'; else el.style.display='none';
    }
</script>

<div class="container">
<h1>Batch Timeline – <?= htmlspecialchars($batch['batch_code']) ?></h1>
<p><strong>Product:</strong> <?= htmlspecialchars($batch['product_code']) ?> |
<strong>Flow:</strong> <?= htmlspecialchars($batch['flow_name']) ?> |
<strong>Status:</strong> <?= htmlspecialchars($batch['status']) ?> |
<strong>Started:</strong> <?= $batch['started_at'] ?> |
<strong>Completed:</strong> <?= $batch['completed_at'] ?></p>

<?php foreach ($steps as $step): ?>
<div class="timeline-step">
    <h4><?= htmlspecialchars($step['step_name']) ?> 
        <small>(Status: <?= htmlspecialchars($step['status']) ?>, 
        Started: <?= $step['started_at'] ?>, Completed: <?= $step['completed_at'] ?>)</small>
    </h4>

    <?php if (!empty($batchParams[$step['process_step_id']])): ?>
    <p class="collapse-header" onclick="toggleCollapse('batch_params_<?= $step['id'] ?>')">Batch-level Parameters (click to expand)</p>
    <table class="table table-bordered table-sm" id="batch_params_<?= $step['id'] ?>" style="display:none">
        <thead><tr><th>Parameter</th><th>Value</th><th>Pass/Fail</th><th>Unit</th></tr></thead>
        <tbody>
        <?php foreach ($batchParams[$step['process_step_id']] as $p): ?>
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

    <?php if (!empty($cellParams[$step['process_step_id']])): ?>
    <p class="collapse-header" onclick="toggleCollapse('cell_params_<?= $step['id'] ?>')">Cell-level Parameters (click to expand)</p>
    <table class="table table-bordered table-sm" id="cell_params_<?= $step['id'] ?>" style="display:none">
        <thead><tr><th>Cell</th><th>Parameter</th><th>Value</th><th>Pass/Fail</th><th>Unit</th></tr></thead>
        <tbody>
        <?php foreach ($cellParams[$step['process_step_id']] as $cellSerial => $params): ?>
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

    <?php if (!empty($scraps[$step['process_step_id']])): ?>
    <p class="collapse-header" onclick="toggleCollapse('scrap_<?= $step['id'] ?>')">Scrapped Cells (click to expand)</p>
    <table class="table table-bordered table-sm" id="scrap_<?= $step['id'] ?>" style="display:none">
        <thead><tr><th>Cell</th><th>Reason</th></tr></thead>
        <tbody>
        <?php foreach ($scraps[$step['process_step_id']] as $s): ?>
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