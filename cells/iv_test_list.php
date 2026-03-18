<?php
$pageTitle = "IV Sweep Test List";
require "../config/database.php";
require "../config/header.php";

$search = trim($_GET['search'] ?? '');
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$params = [];
$where = [];

if ($search !== '') {
    $where[] = "(b.batch_code LIKE ? OR c.cell_code LIKE ? OR p.product_code LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam];
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT iv.*, c.cell_code, b.batch_code, p.product_code 
    FROM iv_measurements iv
    JOIN cells c ON c.id = iv.cell_id
    JOIN batches b ON b.id = c.batch_id
    JOIN products p ON b.product_id = p.id
    $whereSql
    ORDER BY iv.test_date DESC, iv.created_at DESC
    LIMIT $limit
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>IV Sweep Characterization</h4>
        <a href="iv_upload.php" class="btn btn-primary"><i class="bi bi-upload"></i> New Upload</a>
    </div>
    <br>

    <form class="row g-3 mb-4 align-items-end bg-light p-3 border rounded mx-0">
        <div class="col-md-4">
            <label class="form-label small fw-bold">Search Cell/Batch</label>
            <input class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="">
        </div>
        <div class="col-md-2 d-flex align-items-center">
            <label for="limit" class="me-2 text-nowrap small fw-bold">Limit:</label>
            <input type="number" class="form-control" name="limit" value="<?= $limit ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-secondary w-100">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover border shadow-sm">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cell Code</th>
                    <th>Batch</th>
                    <th>Test Date</th>
                    <th class="table-primary text-dark">Fwd PCE (%)</th>
                    <th class="table-danger text-dark">Rev PCE (%)</th>
                    <th>Hysteresis Index</th>
                    <th>Operator</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody class="text-center align-middle">
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td><strong><a href="cell_view.php?cell=<?= $t['cell_code'] ?>" class="text-decoration-none"><?= htmlspecialchars($t['cell_code']) ?></a></strong></td>
                    <td><?= htmlspecialchars($t['batch_code']) ?></td>
                    <td><?= date('Y-m-d', strtotime($t['test_date'])) ?></td>
                    <td class="fw-bold text-primary"><?= number_format($t['fwd_eff'], 2) ?>%</td>
                    <td class="fw-bold text-danger"><?= number_format($t['rev_eff'], 2) ?>%</td>
                    <td>
                        <?php 
                        $hi = $t['hysteresis_index'];
                        $badgeClass = abs($hi) > 0.05 ? 'bg-warning text-dark' : 'bg-success';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= number_format($hi, 3) ?></span>
                    </td>
                    <td class="small"><?= htmlspecialchars($t['operator']) ?></td>
                    <td>
                        <a href="view_iv.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">View Curves</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tests)): ?>
                    <tr><td colspan="8" class="text-muted p-4">No IV tests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require "../config/footer.php"; ?>