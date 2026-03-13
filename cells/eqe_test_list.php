<?php
$pageTitle = "EQE Test List";
require "../config/database.php";
require "../config/header.php";

/* ------------------------
   Filters
------------------------- */
$search = trim($_GET['search'] ?? '');
$limit = trim($_GET['limit'] ?? '100');

$params = [];
$where = [];

if ($search !== '') {
    $where[] = "(b.batch_code LIKE ? OR c.cell_code LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
SELECT e.*, c.cell_code, b.batch_code, p.product_code, b.id as batch_id, p.id as product_id
FROM eqe_measurements e
JOIN cells c ON c.id = e.cell_id
JOIN batches b ON b.id = c.batch_id
JOIN products p ON b.product_id = p.id
$whereSql
GROUP BY e.id
ORDER BY e.test_date DESC
LIMIT $limit
");
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between mb-3">
    <h4>EQE Tests</h4>
</div>

<!-- =======================
     Filters
======================= -->
<form class="row g-2 mb-3">
    <div class="col-md-4">
        <input class="form-control" name="search" placeholder="Search batch or cell code"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <label for="limit" class="me-2 text-nowrap">No. of Results</label>
        <input class="form-control" id="limit" name="limit" placeholder="100"
               value="<?= htmlspecialchars($limit) ?>">
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>
<center>
    <div class="col-md-2" style="text-align: center;">
        <p>Showing: <?= count($tests) ?> Result(s)</p>
    </div>
</center>
<form action="compare_eqe.php" method="POST">
    <div class="d-flex mb-3" style="text-align: center;">
        <div>
            <button type="submit" name="action" value="compare" class="btn btn-primary">
                <i class="fa fa-chart-area"></i> Compare Selected
            </button>
            <button type="submit" name="action" value="export" class="btn btn-outline-success">
                <i class="fa fa-download"></i> Bulk Export
            </button>
        </div>
    </div>

    <table class="table table-hover" style="text-align:center;">
        <thead class="table-dark">
            <tr>
                <th>
                    <input type="checkbox" id="selectAll" class="form-check-input">
                </th>
                <th>Cell Code</th>
                <th>Product</th>
                <th>Batch</th>
                <th>Upload Date</th>
                <th>J<sub>sc</sub></th>
                <th>Bandgap</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tests as $b): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="test_ids[]" value="<?= $b['id'] ?>" class="form-check-input test-checkbox">
                    </td>
                    <td><a style="text-decoration:none;" href="cell_view.php?cell=<?= $b['cell_code']?>"><?= htmlspecialchars($b['cell_code']) ?></a></td>
                    <td><?= htmlspecialchars($b['product_code']) ?></td>
                    <td><?= htmlspecialchars($b['batch_code']) ?></td>
                    <td><?= date('Y-m-d', strtotime($b['test_date'])) ?></td>
                    <td><?= htmlspecialchars($b['jsc_val']) ?></td>
                    <td><?= htmlspecialchars($b['bandgap_val']) ?></td>
                    <td><a href="view_eqe.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    // Find all checkboxes in the table body
    const checkboxes = document.querySelectorAll('.test-checkbox');
    
    // Set their "checked" property to match the "Select All" checkbox
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
    });
});
</script>
<?php require "../config/footer.php"; ?>