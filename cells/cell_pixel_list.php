<?php
$pageTitle = "Cell Pixel List";
require "../config/database.php";
require "../config/header.php";

/* ------------------------
   Filters
------------------------- */
$state = $_GET['state'] ?? 'AVAILABLE';
$search = trim($_GET['search'] ?? '');
$limit = trim($_GET['limit'] ?? '100');

$params = [];
$where = [];

if ($state !== '') {
    $where[] = "c.inventory_status = ?";
    $params[] = $state;
}

if ($search !== '') {
    $where[] = "(c.cell_code LIKE ? OR b.batch_code LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ------------------------
   Inventory Query
------------------------- */
$stmt = $pdo->prepare("
    SELECT 
        c.cell_code,
        c.inventory_status,
        c.inventory_location,
        c.inventory_added_at,
        b.batch_code,
        p.product_code,
        eqe.cell_id,
        eqe.id,
        iv.id as iv_id,
        c.id as cells_id
    FROM cells c
    JOIN batches b ON b.id = c.batch_id
    JOIN products p ON p.id = b.product_id
    LEFT JOIN eqe_measurements eqe ON eqe.cell_id = c.id
    LEFT JOIN iv_measurements iv ON iv.cell_id = c.id
    $whereSql
    ORDER BY c.inventory_added_at DESC, c.cell_code
    LIMIT $limit
");
$stmt->execute($params);
$cells = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- =======================
     Filters
======================= -->
<form class="row g-2 mb-3 align-items-end bg-light p-3 border rounded mx-0 justify-content-between">
    <div class="col-md-4">
        <input class="form-control" name="search" placeholder="Search cell, batch, product"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <label for="limit" class="me-2 text-nowrap">No. of Results</label>
        <input class="form-control" id="limit" name="limit" placeholder="100"
               value="<?= htmlspecialchars($limit) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="state">
            <option value="">All States</option>
            <?php foreach (['IN_PROCESS','AVAILABLE','RESERVED','ISSUED','CONSUMED','SCRAPPED'] as $s): ?>
                <option value="<?= $s ?>" <?= $state===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>

<!-- =======================
     Result Count
======================= -->
<div style="text-align: center;">
    <p>Showing: <?= count($cells) ?> Result(s)</p>
</div>

<!-- =======================
     Inventory Table
======================= -->
<table class="table table-sm table-hover align-middle" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Cell</th>
            <th>Product</th>
            <th>Batch</th>
            <th>No. of Pixels</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cells as $c):
            
            $stmt = $pdo->prepare("SELECT count(pixel_code) as pixels FROM production.cell_pixels WHERE cell_id = ?");
            $stmt->execute([$c['cells_id']]);
            $pixels = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pixels['pixels'] == 0) : {
                $pixels['pixels'] = '';
            } endif;

            ?>
        <tr VALIGN="MIDDLE">
            <td>
                <a style="text-decoration: none;" href="cell_view.php?cell=<?= urlencode($c['cell_code']) ?>">
                    <?= htmlspecialchars($c['cell_code']) ?>
                </a>
            </td>
            <td><?= htmlspecialchars($c['product_code']) ?></td>
            <td><?= htmlspecialchars($c['batch_code']) ?></td>
            <td>
                <?= htmlspecialchars($pixels['pixels']) ?>
            </td>
            <?php if ($pixels['pixels'] == '') : ?>
                <td><a href="add_pixels.php?id=<?= $c['cells_id'] ?>"><button class="btn btn-outline-primary">Add Pixels</button></a></td>
            <?php else: ?>
                <td><a href="view_pixels.php?id=<?= $c['cells_id'] ?>"><button class="btn btn-outline-primary">View Pixels</button></a></td>
            <?php endif ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>