<?php
// products_list.php
$pageTitle = "Product List";
require "../config/database.php";
require "../config/header.php";

$sql = "
SELECT p.*, 
       pf.id AS production_flow_id,
       pf.flow_name AS production_flow_name,
       pf.version AS production_flow_version
FROM products p
LEFT JOIN product_process_flows ppf
  ON p.id = ppf.product_id
 AND ppf.flow_type = 'production'
 AND ppf.active = 1
LEFT JOIN process_flows pf
  ON ppf.process_flow_id = pf.id
ORDER BY p.product_code
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

  <div class="d-flex justify-content-between mb-3">
    <h2>Products</h2>
    <a href="product_create.php" class="btn btn-success">New Product</a>
  </div>

  <table class="table table-striped table-hover" style="text-align:center;">
    <thead class="table-dark">
      <tr>
        <th>Product Code</th>
        <th>Architecture</th>
        <th>Revision</th>
        <th>Lifecycle</th>
        <th>Production Flow</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['product_code']) ?></td>
          <td><?= htmlspecialchars($p['architecture']) ?></td>
          <td><?= htmlspecialchars($p['revision']) ?></td>
          <td>
            <span class="badge bg-<?= match($p['lifecycle_status']){
              'rd' => 'secondary',
              'pilot' => 'info',
              'production' => 'success',
              'retired' => 'dark'
            } ?>">
              <?= strtoupper($p['lifecycle_status']) ?>
            </span>
          </td>
          <td>
            <?php if ($p['production_flow_id']): ?>
              <?= htmlspecialchars($p['production_flow_name']) ?> v<?= htmlspecialchars($p['production_flow_version']) ?>
            <?php else: ?>
              <span class="text-danger">Not Assigned</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="product_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">View</a>
            <a href="product_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

<?php require "../config/footer.php"; ?>
