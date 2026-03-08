<?php
// ==========================
// module_test_execute.php
// ==========================
$pageTitle = "Module Test Execute";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$moduleId = (int)($_GET['module_id'] ?? 0);
if ($moduleId <= 0) {
    die('Invalid module');
}

// Fetch module
$moduleStmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
$moduleStmt->execute([$moduleId]);
$module = $moduleStmt->fetch(PDO::FETCH_ASSOC);
if (!$module) {
    die('Module not found');
}

// Fetch test definitions for this product
$testsStmt = $pdo->prepare("SELECT * FROM module_tests WHERE product_id = ? ORDER BY test_order");
$testsStmt->execute([$module['product_id']]);
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tests'])) {
    $pdo->beginTransaction();
    try {
        foreach ($tests as $test) {
            $value = $_POST['test'][$test['id']]['value'] ?? null;
            $result = $_POST['test'][$test['id']]['result'] ?? 'PASS';

            if ($value === null && $test['required']) {
                throw new Exception("Missing required test: {$test['test_name']}");
            }

            $insert = $pdo->prepare("INSERT INTO module_test_results
                (module_id, test_id, value, pass_fail, tested_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value), pass_fail = VALUES(pass_fail), tested_by = VALUES(tested_by)");
            $insert->execute([
                $moduleId,
                $test['id'],
                $value,
                $result,
                $_SESSION['username'] ?? null
            ]);
        }

        // Re-evaluate module status
        $failCheck = $pdo->prepare("SELECT COUNT(*) FROM module_test_results WHERE module_id = ? AND pass_fail = 'FAIL'");
        $failCheck->execute([$moduleId]);
        $hasFail = $failCheck->fetchColumn() > 0;

        $update = $pdo->prepare("UPDATE modules SET build_status = ?, built_at = IF(? = 'COMPLETED', NOW(), built_at) WHERE id = ?");
        $update->execute([
            $hasFail ? 'ABORTED' : 'COMPLETED',
            $hasFail ? 'ABORTED' : 'COMPLETED',
            $moduleId
        ]);

        $pdo->commit();
        header("Location: module_view.php?id={$moduleId}");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>



<style>
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 6px; }
    .PASS { background: #c8f7c5; }
    .FAIL { background: #f7c5c5; }
</style>


<h2>Module Test Execution</h2>
<p><strong>Module:</strong> <?= htmlspecialchars($module['module_code']) ?></p>

<?php if (!empty($error)): ?>
    <p style="color:red;">Error: <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">
<table class="table table-sm mb-0" style="text-align:center;">
    <tr>
        <th>Test</th>
        <th>Spec</th>
        <th>Value</th>
        <th>Result</th>
    </tr>
    <?php foreach ($tests as $test):
        $existing = $pdo->prepare("SELECT * FROM module_test_results WHERE module_id = ? AND test_id = ?");
        $existing->execute([$moduleId, $test['id']]);
        $ex = $existing->fetch(PDO::FETCH_ASSOC);
    ?>
    <tr class="<?= $ex['pass_fail'] ?? '' ?>">
        <td><?= htmlspecialchars($test['test_name']) ?></td>
        <td><?= htmlspecialchars($test['min_value'] ?? '') ?> – <?= htmlspecialchars($test['max_value'] ?? '') ?> <?= htmlspecialchars($test['unit'] ?? '') ?></td>
        <td>
            <input type="number" step="any" name="test[<?= $test['id'] ?>][value]" value="<?= htmlspecialchars($ex['value'] ?? '') ?>">
        </td>
        <td>
            <select name="test[<?= $test['id'] ?>][result]">
                <option value="PASS" <?= ($ex['pass_fail'] ?? '') === 'PASS' ? 'selected' : '' ?>>PASS</option>
                <option value="FAIL" <?= ($ex['pass_fail'] ?? '') === 'FAIL' ? 'selected' : '' ?>>FAIL</option>
            </select>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<br>
<center>
    <button class="btn btn-primary" name="save_tests">Save Test Results</button>
</center>
</form>

<?php require "../config/footer.php"; ?>