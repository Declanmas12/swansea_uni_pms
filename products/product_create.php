<?php
// product_create.php
$pageTitle = "Product Creation";
require "../config/database.php";
require "../config/header.php";

// Fetch available R&D flows (draft or active)
$flowsStmt = $pdo->prepare("SELECT id, flow_name, version FROM process_flows WHERE status IN ('draft','active') ORDER BY flow_name, version");
$flowsStmt->execute();
$flows = $flowsStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code = trim($_POST['product_code'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $architecture = trim($_POST['architecture'] ?? '');
    $revision     = trim($_POST['revision'] ?? '');
    $initial_flow = $_POST['initial_flow'] ?? null;
    $type = $_POST['type'];

    if ($product_code === '') {
        $errors[] = 'Product code is required.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO products (product_code, description, architecture, revision, lifecycle_status, type) VALUES (?,?,?,?, 'rd', ?)");
            $stmt->execute([$product_code, $description, $architecture, $revision, $type]);
            $productId = $pdo->lastInsertId();

            if ($initial_flow) {
                $linkStmt = $pdo->prepare("INSERT INTO product_process_flows (product_id, process_flow_id, flow_type, active) VALUES (?,?, 'rd', 1)");
                $linkStmt->execute([$productId, $initial_flow]);
            }

            $pdo->commit();
            header("Location: product_view.php?id=" . $productId);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to create product. Product code must be unique.' . $e;
        }
    }
}
?>

<div class="container mt-4" style="max-width: 700px;">
  <h3>New Product</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?= implode('<br>', $errors) ?>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">Product Code *</label>
      <input type="text" name="product_code" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Description</label>
      <textarea name="description" class="form-control" rows="3"></textarea>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Architecture</label>
        <input type="text" name="architecture" class="form-control">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Initial Revision</label>
        <input type="text" name="revision" class="form-control" placeholder="e.g. A">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Product Type</label>
        <select name="type" class="form-select">
          <option value="cell">Cell</option>
          <option value="module">Module</option>
        </select>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Initial R&amp;D Flow (optional)</label>
      <select name="initial_flow" class="form-select">
        <option value="">-- None --</option>
        <?php foreach ($flows as $f): ?>
          <option value="<?= $f['id'] ?>">
            <?= htmlspecialchars($f['flow_name']) ?> v<?= htmlspecialchars($f['version']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">You can link additional R&amp;D flows later.</div>
    </div>

    <div class="d-flex justify-content-end">
      <button class="btn btn-success">Create Product</button>
    </div>
  </form>

<?php require "../config/footer.php"; ?>