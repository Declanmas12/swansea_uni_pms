<?php
$pageTitle = "View Module Test";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$testId = $_GET['id'] ?? null;
if (!$testId) {
    die("Missing test ID.");
}

// Core test record + product context
$stmt = $pdo->prepare("
SELECT 
    mt.*,
    p.product_code,
    p.description AS product_desc
FROM module_tests mt
JOIN products p ON mt.product_id = p.id
WHERE mt.id = ?");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Module test not found.");
}

// How often this test has been used (basic telemetry)
$usageStmt = $pdo->prepare("
SELECT COUNT(*) AS uses, 
       SUM(CASE WHEN pass_fail = 'PASS' THEN 1 ELSE 0 END) AS passes,
       SUM(CASE WHEN pass_fail = 'FAIL' THEN 1 ELSE 0 END) AS fails
FROM module_test_results
WHERE test_id = ?");
$usageStmt->execute([$testId]);
$usage = $usageStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Module Test — Read Only</h3>
        <div>
            <a href="module_test_list.php" class="btn btn-outline-secondary">← Back to List</a>
            <a href="module_test_edit.php?id=<?= $testId ?>" class="btn btn-primary">Edit Test</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3">Definition</h5>
                <table class="table table-sm">
                    <tr><th style="width: 40%">Test Name</th><td><?= htmlspecialchars($test['test_name']) ?></td></tr>
                    <tr><th>Product</th><td><?= htmlspecialchars($test['product_code']) ?><?= $test['product_desc'] ? " — " . htmlspecialchars($test['product_desc']) : '' ?></td></tr>
                    <tr><th>Execution Order</th><td><?= (int)$test['test_order'] ?></td></tr>
                    <tr><th>Required</th><td><?= $test['required'] ? 'Yes (completion gate)' : 'No' ?></td></tr>
                    <tr><th>Unit</th><td><?= htmlspecialchars($test['unit'] ?? '—') ?></td></tr>
                    <tr><th>Min Value</th><td><?= $test['min_value'] !== null ? htmlspecialchars($test['min_value']) : '—' ?></td></tr>
                    <tr><th>Max Value</th><td><?= $test['max_value'] !== null ? htmlspecialchars($test['max_value']) : '—' ?></td></tr>
                    <tr><th>Created At</th><td><?= $test['created_at'] ?></td></tr>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3 h-100">
                <h5 class="mb-3">Usage & Outcomes</h5>
                <table class="table table-sm">
                    <tr><th style="width: 40%">Times Executed</th><td><?= (int)($usage['uses'] ?? 0) ?></td></tr>
                    <tr><th>Passes</th><td><?= (int)($usage['passes'] ?? 0) ?></td></tr>
                    <tr><th>Fails</th><td><?= (int)($usage['fails'] ?? 0) ?></td></tr>
                    <tr><th>Pass Rate</th>
                        <td>
                        <?php
                        $uses = (int)($usage['uses'] ?? 0);
                        if ($uses > 0) {
                            $rate = round((($usage['passes'] ?? 0) / $uses) * 100, 1);
                            echo $rate . "%";
                        } else {
                            echo "—";
                        }
                        ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <h5>Recent Results (last 20)</h5>
        <?php
        $recentStmt = $pdo->prepare("
        SELECT mtr.*, m.module_code
        FROM module_test_results mtr
        JOIN modules m ON m.id = mtr.module_id
        WHERE mtr.test_id = ?
        ORDER BY mtr.tested_at DESC
        LIMIT 20");
        $recentStmt->execute([$testId]);
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Value</th>
                    <th>Pass/Fail</th>
                    <th>Tested By</th>
                    <th>Tested At</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recent)): ?>
                <tr><td colspan="5" class="text-muted">No results recorded yet.</td></tr>
            <?php else: ?>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['module_code']) ?></td>
                    <td><?= htmlspecialchars($r['value'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['pass_fail']) ?></td>
                    <td><?= htmlspecialchars($r['tested_by'] ?? '—') ?></td>
                    <td><?= $r['tested_at'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require "../config/footer.php"; ?>