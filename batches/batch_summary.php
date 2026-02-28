<?php
$pageTitle = "Batch Summary";
require "../config/database.php";
require "../config/header.php";

$batchId = (int)($_GET['batch_id'] ?? 0);
if (!$batchId) die('Batch ID required.');

// Fetch batch
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

// Fetch steps
$stepsStmt = $pdo->prepare("
    SELECT bse.*, ps.step_name
    FROM batch_step_execution bse
    JOIN process_steps ps ON ps.id = bse.process_step_id
    WHERE bse.batch_id = ?
    ORDER BY bse.id
");
$stepsStmt->execute([$batchId]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch batch-level parameters
$batchParamsStmt = $pdo->prepare("
    SELECT p.parameter_name, bsd.value, bsd.pass_fail, p.unit
    FROM batch_step_data bsd
    JOIN process_step_parameters p ON p.id = bsd.parameter_id
    WHERE bsd.batch_id = ?
    ORDER BY p.id
");
$batchParamsStmt->execute([$batchId]);
$batchParams = $batchParamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch cell-level parameters
$cellParamsStmt = $pdo->prepare("
    SELECT cs.cell_serial, p.parameter_name, cs.value, cs.pass_fail, p.unit
    FROM cell_step_data cs
    JOIN process_step_parameters p ON p.id = cs.parameter_id
    WHERE cs.batch_id = ?
    ORDER BY cs.cell_serial, p.id
");
$cellParamsStmt->execute([$batchId]);
$cellParams = $cellParamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group cell params by cell for easy display
$cells = [];
foreach ($cellParams as $cp) {
    $cells[$cp['cell_serial']][] = $cp;
}

// Fetch scrapped cells
$scrapStmt = $pdo->prepare("
    SELECT *, ps.step_name
    FROM cell_scrap
    JOIN process_steps ps
    ON cell_scrap.process_step_id = ps.id
    WHERE batch_id = ?
    ORDER BY process_step_id, cell_serial
");
$scrapStmt->execute([$batchId]);
$scraps = $scrapStmt->fetchAll(PDO::FETCH_ASSOC);

// Compute yield
$totalCellsStmt = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE batch_id=?");
$totalCellsStmt->execute([$batchId]);
$totalCells = (int)$totalCellsStmt->fetchColumn();

$scrappedCellsStmt = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE batch_id=? AND status='SCRAPPED'");
$scrappedCellsStmt->execute([$batchId]);
$scrappedCells = (int)$scrappedCellsStmt->fetchColumn();
$activeCells = $totalCells - $scrappedCells;

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
    .fail { background-color:#f8d7da; }
    .pass { background-color:#d1e7dd; }
</style>

<div class="container">

<h1>Batch Summary – <?= htmlspecialchars($batch['batch_code']) ?></h1>
<p><strong>Product:</strong> <?= htmlspecialchars($batch['product_code'] . ' – ' . $batch['product_desc']) ?><br>
<strong>Flow:</strong> <?= htmlspecialchars($batch['flow_name']) ?><br>
<strong>Status:</strong> <?= htmlspecialchars($batch['status']) ?><br>
<strong>Started:</strong> <?= $batch['started_at'] ?><br>
<strong>Completed:</strong> <?= $batch['completed_at'] ?></p>

<hr>

<h3>Steps</h3>
<table class="table table-bordered table-sm" style="text-align:center;">
    <thead>
        <tr>
            <th>#</th>
            <th>Step Name</th>
            <th>Status</th>
            <th>Started At</th>
            <th>Completed At</th>
            <th>Comment</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($steps as $step): ?>
        <tr>
            <td><?= $step['id'] ?></td>
            <td><?= htmlspecialchars($step['step_name']) ?></td>
            <td><?= htmlspecialchars($step['status']) ?></td>
            <td><?= $step['started_at'] ?></td>
            <td><?= $step['completed_at'] ?></td>
            <td><?= $step['comment'] ?? '' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<hr>

<h3>Batch-level Parameters</h3>
<?php if ($batchParams): ?>
<table class="table table-bordered table-sm" style="text-align:center;">
    <thead>
        <tr>
            <th>Parameter</th>
            <th>Value</th>
            <th>Pass/Fail</th>
            <th>Unit</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($batchParams as $p): ?>
        <tr class="<?= strtolower($p['pass_fail']) ?>">
            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
            <td><?= $p['value'] ?></td>
            <td><?= $p['pass_fail'] ?></td>
            <td><?= htmlspecialchars($p['unit']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>No batch-level parameters recorded.</p>
<?php endif; ?>

<hr>

<h3>Cell-level Parameters</h3>
<?php if ($cells): ?>
<table class="table table-bordered table-sm" style="text-align:center;">
    <thead>
        <tr>
            <th>Cell</th>
            <th>Parameter</th>
            <th>Value</th>
            <th>Pass/Fail</th>
            <th>Unit</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($cells as $cellSerial => $params): ?>
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
<?php else: ?>
<p>No cell-level parameters recorded.</p>
<?php endif; ?>

<hr>

<h3>Scrapped Cells</h3>
<?php if ($scraps): ?>
<table class="table table-bordered table-sm" style="text-align:center;">
    <thead>
        <tr>
            <th>Step ID</th>
            <th>Step Name</th>
            <th>Cell</th>
            <th>Reason</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($scraps as $s): ?>
        <tr>
            <td><?= htmlspecialchars($s['process_step_id']) ?></td>
            <td><?= htmlspecialchars($s['step_name']) ?></td>
            <td><?= htmlspecialchars($s['cell_serial']) ?></td>
            <td><?= htmlspecialchars($s['reason']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p>No cells were scrapped in this batch.</p>
<?php endif; ?>

<hr>

<h3>Yield Summary</h3>
<ul>
    <li>Total cells: <?= $totalCells ?></li>
    <li>Active cells: <?= $activeCells ?></li>
    <li>Scrapped cells: <?= $scrappedCells ?></li>
    <li>Yield: <?= $totalCells > 0 ? round($activeCells / $totalCells * 100, 2) : 0 ?>%</li>
</ul>

</div>

<?php require "../config/footer.php"; ?>