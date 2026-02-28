<?php
$pageTitle = "Edit Module Test";
require "../config/database.php";
require "../config/header.php";

// ---- Load test ----
$testId = $_GET['id'] ?? null;
if (!$testId) {
    die("Missing test ID.");
}

// Fetch existing test
$stmt = $pdo->prepare("
SELECT 
    mt.*,
    p.product_code,
    p.description AS product_desc
FROM module_tests mt
JOIN products p ON mt.product_id = p.id
WHERE mt.id = ?
");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die("Test not found.");
}

// ---- Load products (in case product needs changing) ----
$productsStmt = $pdo->query("
SELECT id, product_code, description
FROM products
WHERE active = 1
AND type = 'module'
ORDER BY product_code
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = $_POST['product_id'] ?? null;
    $test_name = trim($_POST['test_name'] ?? '');
    $test_order = $_POST['test_order'] ?? null;
    $min_value = $_POST['min_value'] !== '' ? $_POST['min_value'] : null;
    $max_value = $_POST['max_value'] !== '' ? $_POST['max_value'] : null;
    $unit = trim($_POST['unit'] ?? '');
    $required = isset($_POST['required']) ? 1 : 0;

    $errors = [];

    if (!$product_id) {
        $errors[] = "Product is required.";
    }

    if ($test_name === '') {
        $errors[] = "Test name is required.";
    }

    if ($test_order === null || !is_numeric($test_order)) {
        $errors[] = "Test order must be a number.";
    }

    if ($min_value !== null && !is_numeric($min_value)) {
        $errors[] = "Min value must be numeric.";
    }

    if ($max_value !== null && !is_numeric($max_value)) {
        $errors[] = "Max value must be numeric.";
    }

    if ($min_value !== null && $max_value !== null && $min_value > $max_value) {
        $errors[] = "Min value cannot be greater than Max value.";
    }

    if (empty($errors)) {

        // Ensure unique ordering within product (simple normalization)
        $pdo->beginTransaction();

        // If order changed, rebalance other tests for same product
        if ($test_order != $test['test_order'] || $product_id != $test['product_id']) {

            // Shift down others at/after new position
            $shift = $pdo->prepare("
                UPDATE module_tests
                SET test_order = test_order + 1
                WHERE product_id = ?
                AND test_order >= ?
                AND id <> ?
            ");
            $shift->execute([$product_id, $test_order, $testId]);
        }

        // Update the test
        $update = $pdo->prepare("
            UPDATE module_tests
            SET 
                product_id = ?,
                test_name = ?,
                test_order = ?,
                min_value = ?,
                max_value = ?,
                unit = ?,
                required = ?
            WHERE id = ?
        ");

        $update->execute([
            $product_id,
            $test_name,
            $test_order,
            $min_value,
            $max_value,
            $unit !== '' ? $unit : null,
            $required,
            $testId
        ]);

        $pdo->commit();

        header("Location: module_test_list.php?updated=1");
        exit;
    }
}
?>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Edit Module Test</h3>
        <a href="module_test_list.php" class="btn btn-outline-secondary">← Back to List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="card p-3 mb-3">
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label">Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select product...</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                <?= ($p['id'] == $test['product_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['product_code']) ?>
                                <?= $p['description'] ? " — " . htmlspecialchars($p['description']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Test Name</label>
                    <input type="text"
                           name="test_name"
                           class="form-control"
                           value="<?= htmlspecialchars($test['test_name']) ?>"
                           required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Test Order</label>
                    <input type="number"
                           name="test_order"
                           class="form-control"
                           value="<?= (int)$test['test_order'] ?>"
                           min="1"
                           required>
                    <div class="form-text">
                        Execution sequence within this product.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Unit</label>
                    <input type="text"
                           name="unit"
                           class="form-control"
                           value="<?= htmlspecialchars($test['unit'] ?? '') ?>"
                           placeholder="e.g. V, A, Ω, W">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Min Value</label>
                    <input type="number"
                           step="any"
                           name="min_value"
                           class="form-control"
                           value="<?= htmlspecialchars($test['min_value'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Max Value</label>
                    <input type="number"
                           step="any"
                           name="max_value"
                           class="form-control"
                           value="<?= htmlspecialchars($test['max_value'] ?? '') ?>">
                </div>

                <div class="col-md-12">
                    <div class="form-check">
                        <input class="form-check-input"
                               type="checkbox"
                               name="required"
                               id="required"
                               <?= $test['required'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="required">
                            Required test (must PASS to complete module)
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="module_test_list.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>

    </form>

</div>

<?php require "../config/footer.php"; ?>