<?php
// ==========================
// cell_view.php
// ==========================
$pageTitle = "Cell Viewer";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$cellCode = $_GET['cell'] ?? '';
if (!$cellCode) die('Cell not specified');

/* -------------------------
   Cell + Batch Context
-------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        b.batch_code,
        b.status AS batch_status,
        b.process_flow_id,
        p.product_code,
        pf.flow_name,
        pf.version
    FROM cells c
    JOIN batches b ON b.id = c.batch_id
    JOIN products p ON p.id = b.product_id
    JOIN process_flows pf ON pf.id = b.process_flow_id
    WHERE c.cell_code = ?
");
$stmt->execute([$cellCode]);
$cell = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cell) die('Cell not found');

/* -------------------------
   Process Steps
-------------------------- */
$stepsStmt = $pdo->prepare("
    SELECT pfs.process_step_id, pfs.step_order, ps.step_name
    FROM process_flow_steps pfs
    JOIN process_steps ps ON ps.id = pfs.process_step_id
    WHERE pfs.process_flow_id = ?
    ORDER BY pfs.step_order
");
$stepsStmt->execute([$cell['process_flow_id']]);
$steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   Batch Parameters
-------------------------- */
$batchParamsStmt = $pdo->prepare("
    SELECT 
        bsd.process_step_id,
        ps.step_name,
        p.parameter_name,
        bsd.value,
        bsd.pass_fail,
        p.unit
    FROM batch_step_data bsd
    JOIN process_steps ps ON ps.id = bsd.process_step_id
    JOIN process_step_parameters p ON p.id = bsd.parameter_id
    WHERE bsd.batch_id = ?
");
$batchParamsStmt->execute([$cell['batch_id']]);
$batchParams = $batchParamsStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   Cell Parameters
-------------------------- */
$cellParamsStmt = $pdo->prepare("
    SELECT 
        csd.process_step_id,
        ps.step_name,
        p.parameter_name,
        csd.value,
        csd.pass_fail,
        p.unit
    FROM cell_step_data csd
    JOIN process_steps ps ON ps.id = csd.process_step_id
    JOIN process_step_parameters p ON p.id = csd.parameter_id
    WHERE csd.batch_id = ? AND csd.cell_serial = ?
");
$cellParamsStmt->execute([$cell['batch_id'], $cellCode]);
$cellParams = $cellParamsStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   Scrap History
-------------------------- */
$scrapStmt = $pdo->prepare("
    SELECT cs.*, ps.step_name
    FROM cell_scrap cs
    JOIN process_steps ps ON ps.id = cs.process_step_id
    WHERE cs.cell_serial = ?
");
$scrapStmt->execute([$cellCode]);
$scrapHistory = $scrapStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   Helper grouping
-------------------------- */
function groupByStep(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $out[$r['step_name']][] = $r;
    }
    return $out;
}

$batchParamsByStep = groupByStep($batchParams);
$cellParamsByStep  = groupByStep($cellParams);
?>

<style>
.pass { background:#d1e7dd; }
.fail { background:#f8d7da; }
.card { margin-bottom:1rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Cell Traveller – <?= htmlspecialchars($cell['cell_code']) ?></h3>
    <?php if($cell['batch_status'] != 'completed') :?>
        <a href="../batches/batch_view.php?id=<?= $cell['batch_id'] ?>" class="btn btn-outline-secondary btn-sm">
            View Batch Traveller
        </a>
    <?php else: ?>
        <a href="../batches/batch_summary.php?batch_id=<?= $cell['batch_id'] ?>" class="btn btn-outline-secondary btn-sm">
            View Batch Summary
        </a>
    <?php endif ?>
</div>

<!-- =======================
     Cell Summary
======================= -->
<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Product:</strong> <?= htmlspecialchars($cell['product_code']) ?><br>
                <strong>Batch:</strong> <?= htmlspecialchars($cell['batch_code']) ?><br>
                <strong>Flow:</strong> <?= htmlspecialchars($cell['flow_name']) ?> (v<?= $cell['version'] ?>)
            </div>
            <div class="col-md-4">
                <strong>Cell Status:</strong> <?= htmlspecialchars($cell['status']) ?><br>
                <strong>Inventory:</strong> <?= htmlspecialchars($cell['inventory_status'] ?? '-') ?><br>
                <strong>Location:</strong> <?= htmlspecialchars($cell['inventory_location'] ?? '-') ?>
            </div>
            <div class="col-md-4">
                <strong>Created:</strong> <?= $cell['created_at'] ?><br>
                <?php if ($cell['inventory_added_at']): ?>
                    <strong>Inventory Added:</strong> <?= $cell['inventory_added_at'] ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- =======================
     Process Flow
======================= -->
<div class="card">
    <div class="card-header"><strong>Process Flow</strong></div>
    <div class="card-body">
        <ol>
            <?php foreach ($steps as $s): ?>
                <li><?= htmlspecialchars($s['step_name']) ?></li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>

<!-- =======================
     Parameters
======================= -->
<div class="card">
    <div class="card-header"><strong>Measured Parameters</strong></div>
    <div class="card-body">

        <?php foreach ($steps as $s): ?>
            <h5><?= htmlspecialchars($s['step_name']) ?></h5>

            <?php if (!empty($batchParamsByStep[$s['step_name']])): ?>
                <h6>Batch-Level</h6>
                <table class="table table-sm">
                    <tr><th>Parameter</th><th>Value</th><th>Result</th></tr>
                    <?php foreach ($batchParamsByStep[$s['step_name']] as $p): ?>
                        <tr class="<?= strtolower($p['pass_fail']) ?>">
                            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
                            <td><?= $p['value'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= $p['pass_fail'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if (!empty($cellParamsByStep[$s['step_name']])): ?>
                <h6>Cell-Level</h6>
                <table class="table table-sm">
                    <tr><th>Parameter</th><th>Value</th><th>Result</th></tr>
                    <?php foreach ($cellParamsByStep[$s['step_name']] as $p): ?>
                        <tr class="<?= strtolower($p['pass_fail']) ?>">
                            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
                            <td><?= $p['value'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= $p['pass_fail'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

        <?php endforeach; ?>

    </div>
</div>

<!-- =======================
     Scrap History
======================= -->
<?php if ($scrapHistory): ?>
<div class="card border-danger">
    <div class="card-header bg-danger text-white">
        Scrap History
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <tr><th>Step</th><th>Reason</th><th>Date</th></tr>
            <?php foreach ($scrapHistory as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['step_name']) ?></td>
                    <td><?= htmlspecialchars($s['reason']) ?></td>
                    <td><?= $s['scrapped_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require "../config/footer.php"; ?>