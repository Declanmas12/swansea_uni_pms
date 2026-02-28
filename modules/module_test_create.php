<?php
$pageTitle = "Create Module Test";
require "../config/database.php";
require "../config/header.php";

// Fetch products for dropdown
$productsStmt = $pdo->query("
    SELECT id, product_code, description
    FROM products
    WHERE active = 1
    AND type= 'module'
    ORDER BY product_code
");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_id = $_POST['product_id'] ?? null;
    $test_name  = trim($_POST['test_name'] ?? '');
    $test_order = $_POST['test_order'] ?? null;
    $min_value  = $_POST['min_value'] !== '' ? $_POST['min_value'] : null;
    $max_value  = $_POST['max_value'] !== '' ? $_POST['max_value'] : null;
    $unit       = trim($_POST['unit'] ?? '');
    $required   = isset($_POST['required']) ? 1 : 0;

    // ---- Validation ----
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

    // Optional sanity check: min < max
    if ($min_value !== null && $max_value !== null && $min_value > $max_value) {
        $errors[] = "Min value cannot be greater than Max value.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO module_tests
                (product_id, test_name, test_order, min_value, max_value, unit, required)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $product_id,
                $test_name,
                (int)$test_order,
                $min_value,
                $max_value,
                $unit !== '' ? $unit : null,
                $required
            ]);

            $success = true;

            // Redirect to test list after creation
            header("Location: module_test_list.php?created=1");
            exit;

        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>Create Module Test</h3>
        <a href="module_test_list.php" class="btn btn-secondary">← Back to Tests</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card p-3">
        <form method="post">

            <div class="mb-3">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-select" required>
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"
                            <?= isset($_POST['product_id']) && $_POST['product_id'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['product_code']) ?>
                            <?= $p['description'] ? " — " . htmlspecialchars($p['description']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Test Name</label>
                <input type="text"
                       name="test_name"
                       class="form-control"
                       value="<?= htmlspecialchars($_POST['test_name'] ?? '') ?>"
                       required>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Test Order</label>
                    <input type="number"
                           name="test_order"
                           class="form-control"
                           value="<?= htmlspecialchars($_POST['test_order'] ?? '') ?>"
                           required>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Unit (optional)</label>
                    <input type="text"
                           name="unit"
                           class="form-control"
                           placeholder="e.g. V, mA, Ω"
                           value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Required?</label><br>
                    <input type="checkbox"
                           name="required"
                           value="1"
                           <?= (!isset($_POST['required']) || $_POST['required']) ? 'checked' : '' ?>>
                    Required for pass
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Min Value (optional)</label>
                    <input type="number"
                           step="any"
                           name="min_value"
                           class="form-control"
                           value="<?= htmlspecialchars($_POST['min_value'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Max Value (optional)</label>
                    <input type="number"
                           step="any"
                           name="max_value"
                           class="form-control"
                           value="<?= htmlspecialchars($_POST['max_value'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Create Test</button>
        </form>
    </div>
</div>

<?php require "../config/footer.php"; ?>