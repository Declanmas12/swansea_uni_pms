<?php
// ==========================
// module_view.php
// ==========================
$pageTitle = "Module View";
require "../config/database.php";
require "../config/header.php";

$moduleId = (int)($_GET['id'] ?? 0);

// --------------------------
// Load module
// --------------------------
$moduleStmt = $pdo->prepare("
    SELECT m.*, p.product_code
    FROM modules m
    JOIN products p ON p.id = m.product_id
    WHERE m.id = ?
");
$moduleStmt->execute([$moduleId]);
$module = $moduleStmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die('Module not found');
}

// --------------------------
// Load module cells
// --------------------------
$cellStmt = $pdo->prepare("
    SELECT mc.cell_code, c.status, c.batch_id
    FROM module_cells mc
    JOIN cells c ON c.cell_code = mc.cell_code
    WHERE mc.module_id = ?
    ORDER BY mc.cell_code
");
$cellStmt->execute([$moduleId]);
$cells = $cellStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------
// Load test definitions
// --------------------------
$testsStmt = $pdo->prepare("
    SELECT * FROM module_tests
    WHERE product_id = ?
    ORDER BY test_order
");
$testsStmt->execute([$module['product_id']]);
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------
// Load test results
// --------------------------
$resultsStmt = $pdo->prepare("
    SELECT r.*, t.test_name, t.unit
    FROM module_test_results r
    JOIN module_tests t ON t.id = r.test_id
    WHERE r.module_id = ?
");
$resultsStmt->execute([$moduleId]);
$resultsRaw = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($resultsRaw as $r) {
    $results[$r['test_id']] = $r;
}
?>

<style>
.card { margin-bottom: 1rem; }
.pass { color: #198754; font-weight: bold; }
.fail { color: #dc3545; font-weight: bold; }
.pending { color: #6c757d; font-style: italic; }
</style>

<div class="d-flex justify-content-between mb-3">
    <h4>Module Viewer</h4>
    <a href="module_test_execute.php?module_id=<?=$module['id']?>" class="btn btn-primary">Execute Tests</a>
</div>

<!-- ========================= -->
<!-- Module Summary -->
<!-- ========================= -->
<div class="card">
    <div class="card-header">Module Summary</div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            <li><strong>Module Code:</strong> <?=htmlspecialchars($module['module_code'])?></li>
            <li><strong>Product:</strong> <?=htmlspecialchars($module['product_code'])?></li>
            <li><strong>Status:</strong> <?=htmlspecialchars($module['build_status'])?></li>
            <li><strong>Cells Required:</strong> <?=$module['cells_required']?></li>
            <li><strong>Built By:</strong> <?=htmlspecialchars($module['built_by'] ?? '-')?></li>
            <li><strong>Built At:</strong> <?=$module['built_at'] ?? '-'?></li>
            <li><strong>Created:</strong> <?=$module['created_at']?></li>
        </ul>
    </div>
</div>

<!-- ========================= -->
<!-- Cell Genealogy -->
<!-- ========================= -->
<div class="card">
    <div class="card-header">Cell Genealogy</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="text-align:center;">
            <thead class="table-light">
                <tr>
                    <th>Cell Serial</th>
                    <th>Status</th>
                    <th>Batch ID</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cells as $c): ?>
                <tr>
                    <td><a style="text-decoration:none;" href="../cells/cell_view.php?cell=<?=$c['cell_code']?>"><?=htmlspecialchars($c['cell_code'])?></a></td>
                    <td><?=htmlspecialchars($c['status'])?></td>
                    <td><a style="text-decoration:none;" href="../batches/batch_summary.php?batch_id=<?=$c['batch_id']?>"><?=$c['batch_id']?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ========================= -->
<!-- Module Test Results -->
<!-- ========================= -->
<div class="card">
    <div class="card-header">Module Test Results</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="text-align:center;">
            <thead class="table-light">
                <tr>
                    <th>Order</th>
                    <th>Test</th>
                    <th>Value</th>
                    <th>Unit</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tests as $t):
                $r = $results[$t['id']] ?? null;
                $statusClass = 'pending';
                $statusText = 'PENDING';
                if ($r) {
                    if ($r['pass_fail'] === 'PASS') {
                        $statusClass = 'pass';
                        $statusText = 'PASS';
                    } else {
                        $statusClass = 'fail';
                        $statusText = 'FAIL';
                    }
                }
            ?>
                <tr>
                    <td><?=$t['test_order']?></td>
                    <td><?=htmlspecialchars($t['test_name'])?></td>
                    <td><?= $r['value'] ?? '-' ?></td>
                    <td><?=htmlspecialchars($t['unit'] ?? '-')?></td>
                    <td class="<?=$statusClass?>"><?=$statusText?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require "../config/footer.php"; ?>