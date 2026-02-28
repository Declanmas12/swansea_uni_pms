<?php
// product_edit.php
$pageTitle = "Product Editor";
require "../config/database.php";
require "../config/header.php";

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: products_list.php');
    exit;
}

// Fetch product
$productStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$productStmt->execute([$id]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: products_list.php');
    exit;
}

// Fetch all flows
$allFlowsStmt = $pdo->query("SELECT id, flow_name, version, status FROM process_flows ORDER BY flow_name, version");
$allFlows = $allFlowsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch linked flows
$linkStmt = $pdo->prepare("SELECT * FROM product_process_flows WHERE product_id = ?");
$linkStmt->execute([$id]);
$links = $linkStmt->fetchAll(PDO::FETCH_ASSOC);

$linkedMap = [];
foreach ($links as $l) {
    $linkedMap[$l['process_flow_id']] = $l;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update metadata (locked if production)
        if ($product['lifecycle_status'] !== 'production') {
            $upd = $pdo->prepare("UPDATE products SET description=?, architecture=?, revision=? WHERE id=?");
            $upd->execute([
                $_POST['description'] ?? '',
                $_POST['architecture'] ?? '',
                $_POST['revision'] ?? '',
                $id
            ]);
        }

        // Lifecycle change
        if (!empty($_POST['lifecycle_status']) && $_POST['lifecycle_status'] !== $product['lifecycle_status']) {
            $allowed = ['rd','pilot','production','retired'];
            if (in_array($_POST['lifecycle_status'], $allowed, true)) {
                $lc = $pdo->prepare("UPDATE products SET lifecycle_status=? WHERE id=?");
                $lc->execute([$_POST['lifecycle_status'], $id]);
            }
        }

        // Flow linking actions
        if (!empty($_POST['assign_rd_flow'])) {
            $flowId = (int)$_POST['assign_rd_flow'];
            $pdo->prepare("INSERT IGNORE INTO product_process_flows (product_id, process_flow_id, flow_type, active) VALUES (?,?, 'rd', 1)")
                ->execute([$id, $flowId]);
        }

        if (!empty($_POST['set_production_flow'])) {
            $flowId = (int)$_POST['set_production_flow'];
            // Deactivate old
            $pdo->prepare("UPDATE product_process_flows SET active=0 WHERE product_id=? AND flow_type='production'")
                ->execute([$id]);
            // Insert new
            $pdo->prepare("INSERT INTO product_process_flows (product_id, process_flow_id, flow_type, active) VALUES (?,?, 'production', 1)")
                ->execute([$id, $flowId]);
        }

        $pdo->commit();
        header("Location: product_view.php?id=$id");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}
?>

<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
<?php endif; ?>

<form method="post">

<!-- Metadata -->
<div class="card mb-4">
  <div class="card-header">Product Metadata</div>
  <div class="card-body">
    <div class="mb-3">
      <label>Description</label>
      <textarea name="description" class="form-control" <?= $product['lifecycle_status']==='production'?'readonly':'' ?>><?= htmlspecialchars($product['description']) ?></textarea>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label>Architecture</label>
        <input name="architecture" class="form-control" value="<?= htmlspecialchars($product['architecture']) ?>" <?= $product['lifecycle_status']==='production'?'readonly':'' ?>>
      </div>
      <div class="col-md-6 mb-3">
        <label>Revision</label>
        <input name="revision" class="form-control" value="<?= htmlspecialchars($product['revision']) ?>" <?= $product['lifecycle_status']==='production'?'readonly':'' ?>>
      </div>
    </div>
  </div>
</div>

<!-- Lifecycle -->
<div class="card mb-4">
  <div class="card-header">Lifecycle</div>
  <div class="card-body">
    <select name="lifecycle_status" class="form-select">
      <?php foreach (['rd','pilot','production','retired'] as $s): ?>
        <option value="<?= $s ?>" <?= $product['lifecycle_status']===$s?'selected':'' ?>><?= strtoupper($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- R&D Flows -->
<div class="card mb-4">
  <div class="card-header">R&amp;D Flows</div>
  <div class="card-body">
    <select name="assign_rd_flow" class="form-select">
      <option value="">-- Add R&D Flow --</option>
      <?php foreach ($allFlows as $f): ?>
        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['flow_name']) ?> v<?= htmlspecialchars($f['version']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Production Flow -->
<div class="card mb-4">
  <div class="card-header">Production Flow</div>
  <div class="card-body">
    <select name="set_production_flow" class="form-select">
      <option value="">-- Select Production Flow --</option>
      <?php foreach ($allFlows as $f): ?>
        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['flow_name']) ?> v<?= htmlspecialchars($f['version']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="d-flex justify-content-end">
  <button class="btn btn-success">Save Changes</button>
</div>

</form>

<?php require "../config/footer.php"; ?>