<?php
// ==========================
// module_create.php
// ==========================
$pageTitle = "Create Module";
require "../config/database.php";
require "../config/header.php";

// Fetch products for dropdown
$productsStmt = $pdo->query("SELECT id, product_code FROM products WHERE active = 1 AND type='module' ORDER BY product_code");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $cellsRequired = (int)($_POST['cells_required'] ?? 0);
    $builtBy = trim($_POST['built_by'] ?? '');

    if ($productId <= 0 || $cellsRequired <= 0) {
        $error = 'Product and required cell count are mandatory.';
    } else {
        try {
            // Generate module code (simple, deterministic)
            $prefixStmt = $pdo->prepare("SELECT product_code FROM products WHERE id = ?");
            $prefixStmt->execute([$productId]);
            $productCode = $prefixStmt->fetchColumn();

            if (!$productCode) throw new Exception('Invalid product selected.');

            $moduleCode = sprintf(
                'MOD-%s-%s',
                $productCode,
                date('Ymd-His')
            );

            $stmt = $pdo->prepare("
                INSERT INTO modules
                (module_code, product_id, cells_required, build_status, built_by)
                VALUES (?, ?, ?, 'OPEN', ?)
            ");
            $stmt->execute([$moduleCode, $productId, $cellsRequired, $builtBy ?: null]);

            $moduleId = $pdo->lastInsertId();

            header("Location: module_view.php?id=" . $moduleId);
            exit;

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between mb-3">
    <h4>Create Module</h4>
    <a href="module_list.php" class="btn btn-secondary">Back</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<center>
<form method="post" class="card card-body" style="max-width:600px;">
        <div class="mb-3">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
                <option value="">Select product</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= htmlspecialchars($p['product_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Cells Required</label>
            <input type="number" name="cells_required" class="form-control" min="1" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Built By (optional)</label>
            <input type="text" name="built_by" class="form-control">
        </div>

        <button class="btn btn-success">Create Module</button>
</form>
</center>

<?php require "../config/footer.php"; ?>
