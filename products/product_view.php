<?php
// product_view.php
$pageTitle = "Product Viewer";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

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

// Fetch linked flows
$flowsStmt = $pdo->prepare("
    SELECT ppf.flow_type, ppf.active,
           pf.id, pf.flow_name, pf.version, pf.status
    FROM product_process_flows ppf
    JOIN process_flows pf ON pf.id = ppf.process_flow_id
    WHERE ppf.product_id = ?
    ORDER BY ppf.flow_type, pf.flow_name, pf.version
");
$flowsStmt->execute([$id]);
$linkedFlows = $flowsStmt->fetchAll(PDO::FETCH_ASSOC);

$rdFlows = [];
$productionFlow = null;
foreach ($linkedFlows as $f) {
    if ($f['flow_type'] === 'production' && $f['active']) {
        $productionFlow = $f;
    } elseif ($f['flow_type'] === 'rd') {
        $rdFlows[] = $f;
    }
}

function badge(string $status): string {
    return match ($status) {
        'rd' => 'secondary',
        'pilot' => 'info',
        'production' => 'success',
        'retired' => 'dark',
        default => 'light'
    };
}
?>

  <!-- Product Summary -->
  <div class="card mb-4">
    <div class="card-header bg-light">
      <strong><?= htmlspecialchars($product['product_code']) ?></strong>
      <span class="badge bg-<?= badge($product['lifecycle_status']) ?> ms-2">
        <?= strtoupper($product['lifecycle_status']) ?>
      </span>
    </div>
    <div class="card-body">
      <p><?= nl2br(htmlspecialchars($product['description'])) ?: '<em>No description</em>' ?></p>
      <div class="row">
        <div class="col-md-4"><strong>Architecture:</strong> <?= htmlspecialchars($product['architecture'] ?: '-') ?></div>
        <div class="col-md-4"><strong>Revision:</strong> <?= htmlspecialchars($product['revision'] ?: '-') ?></div>
        <div class="col-md-4"><strong>Created:</strong> <?= htmlspecialchars($product['created_at'] ?? '-') ?></div>
      </div>
    </div>
  </div>

  <!-- Production Flow -->
  <div class="card mb-4">
    <div class="card-header bg-success text-white">Active Production Flow</div>
    <div class="card-body">
      <?php if ($productionFlow): ?>
        <h5><?= htmlspecialchars($productionFlow['flow_name']) ?> v<?= htmlspecialchars($productionFlow['version']) ?></h5>
        <p>Status: <strong><?= htmlspecialchars($productionFlow['status']) ?></strong></p>
        <a href="../process/process_flow_view.php?id=<?= $productionFlow['id'] ?>" class="btn btn-outline-success btn-sm">View Flow</a>
      <?php else: ?>
        <p class="text-danger">No production flow assigned.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- R&D Flows -->
  <div class="card mb-4">
    <div class="card-header bg-secondary text-white">R&amp;D Flows</div>
    <div class="card-body">
      <?php if ($rdFlows): ?>
        <ul class="list-group">
          <?php foreach ($rdFlows as $f): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= htmlspecialchars($f['flow_name']) ?> v<?= htmlspecialchars($f['version']) ?>
              <span class="badge bg-light text-dark"><?= htmlspecialchars($f['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p><em>No R&amp;D flows linked.</em></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Actions -->
  <div class="d-flex justify-content-end gap-2">
    <a href="product_edit.php?id=<?= $product['id'] ?>" class="btn btn-warning">Edit Product</a>
  </div>

<?php require "../config/footer.php"; ?>