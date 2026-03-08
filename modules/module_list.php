<?php
// ==========================
// module_list.php
// ==========================
$pageTitle = "Modules";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$product = $_GET['product'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.module_serial LIKE ? OR p.product_code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status !== '') {
    $where[] = 'm.status = ?';
    $params[] = $status;
}
if ($product !== '') {
    $where[] = 'p.product_code = ?';
    $params[] = $product;
}

$sql = "
    SELECT m.*, p.product_code
    FROM modules m
    JOIN products p ON p.id = m.product_id
";

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' GROUP BY m.id ORDER BY m.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For filter dropdowns
$products = $pdo->query("SELECT DISTINCT product_code FROM products ORDER BY product_code")->fetchAll(PDO::FETCH_COLUMN);
$statuses = ['PLANNED','BUILDING','COMPLETED','SCRAPPED'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Modules</h4>
    <a href="module_create.php" class="btn btn-success">Create Module</a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search serial or product" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
        <select name="product" class="form-select">
            <option value="">All Products</option>
            <?php foreach ($products as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $product===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100">Filter</button>
    </div>
</form>

<table class="table table-sm table-bordered table-hover" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Module Serial</th>
            <th>Product</th>
            <th>Status</th>
            <th>Cells</th>
            <th>Started</th>
            <th>Completed</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($modules as $m): ?>
        <tr VALIGN="MIDDLE">
            <td><?= htmlspecialchars($m['module_code']) ?></td>
            <td><?= htmlspecialchars($m['product_code']) ?></td>
            <td>
                <span class="badge bg-<?= $m['build_status']==='COMPLETED'?'success':($m['build_status']==='OPEN'?'warning':'secondary') ?>">
                    <?= htmlspecialchars($m['build_status']) ?>
                </span>
            </td>
            <td><?= (int)$m['cells_required'] ?></td>
            <td><?= htmlspecialchars($m['created_at']) ?></td>
            <?php if ($m['built_at'] != ''): ?>
                <td><?= htmlspecialchars($m['built_at']) ?></td>
            <?php else: ?>
                <td>-</td>
            <?php endif ?>
            <?php if($m['built_at'] != '') :?>
            <td>
                <a href="module_view.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
            </td>
            <?php else: ?>
            <td>
                <a href="module_build.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">Build</a>
            </td>
            <?php endif ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$modules): ?>
        <tr><td colspan="7" class="text-muted text-center">No modules found</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>