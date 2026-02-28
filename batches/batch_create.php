<?php
// ==========================
// batch_create.php
// ==========================
$pageTitle = "Create New Batch";
require "../config/database.php";
require "../config/header.php";

// Fetch active products
$products = $pdo->query("SELECT id, product_code, description FROM products WHERE active = 1 ORDER BY product_code")->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)$_POST['product_id'];
    $batchCode = trim($_POST['batch_code']);
    $quantity  = (int)$_POST['quantity'];

    if (!$productId || !$batchCode || $quantity <= 0) {
        $errors[] = 'All fields are required.';
    }

    if (empty($errors)) {
        // Resolve active PRODUCTION flow for product
        $executionType = $_POST['execution_type'];

        $stmt = $pdo->prepare("
            SELECT pf.id
            FROM product_process_flows ppf
            JOIN process_flows pf ON pf.id = ppf.process_flow_id
            WHERE ppf.product_id = ?
            AND ppf.flow_type = ?
            AND ppf.active = 1
            ORDER BY pf.version DESC
            LIMIT 1
        ");
        $stmt->execute([$productId, $executionType]);
        $flow = $stmt->fetch();

        if (!$flow) {
            $errors[] = 'No active PRODUCTION flow found for this product.';
        } else {
            try {
                $pdo->beginTransaction();

                // Create batch
                $stmt = $pdo->prepare("
                    INSERT INTO batches
                    (batch_code, product_id, execution_type, process_flow_id, quantity)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $batchCode,
                    $productId,
                    $executionType,
                    $flow['id'],
                    $quantity
                ]);
                $batchId = $pdo->lastInsertId();

                // Create cells
                $cellStmt = $pdo->prepare("INSERT INTO cells (batch_id, cell_code) VALUES (?, ?)");
                for ($i = 1; $i <= $quantity; $i++) {
                    $cellStmt->execute([$batchId, $batchCode . '-C' . str_pad($i, 3, '0', STR_PAD_LEFT)]);
                }

                $pdo->commit();
                header('Location: batch_view.php?id=' . $batchId);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Failed to create batch: ' . $e->getMessage();
            }
        }
    }
}
?>

<h1>Create New Batch</h1>

<?php foreach ($errors as $e): ?>
    <p style="color:red;"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<form method="post">
    <center>
    <label>Product</label><br>
    <select name="product_id" class="form-select mb-2" required>
        <option value="">-- Select --</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product_code']) ?> — <?= htmlspecialchars($p['description']) ?></option>
        <?php endforeach; ?>
    </select><br><br>

    <label>Execution Type</label><br>
    <select name="execution_type" class="form-select mb-2" required>
        <option value="production">Production</option>
        <option value="rd">R&D</option>
    </select><br><br>

    <label>Batch Code</label><br>
    <input type="text" name="batch_code" required><br><br>

    <label>Quantity (Cells)</label><br>
    <input type="number" name="quantity" min="1" required><br><br>

    <button type="submit" class="btn btn-primary">Create Batch</button>
    </center>
</form>

<?php require "../config/footer.php"; ?>