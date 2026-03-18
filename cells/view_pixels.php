<?php
$pageTitle = "Cell Pixel Results";
require "../config/database.php";
require "../config/header.php";

$cell_id = $_GET['cell_id'];

$stmt = $pdo->prepare("SELECT cell_code FROM production.cells WHERE id=?");
$stmt->execute([$cell_id]);
$cell_code = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "
SELECT p.*, piv.test_date, piv.fwd_eff, piv.rev_eff, piv.hysteresis_index, piv.operator, 
avg(piv.rev_eff) as avg_rev, avg(piv.fwd_eff) as avg_fwd, avg(piv.hysteresis_index) as avg_hi
FROM cell_pixels p 
LEFT JOIN pixel_iv_measurements piv ON piv.pixel_id = p.id
WHERE p.cell_id = ?
GROUP BY p.id, piv.id"
;

$stmt = $pdo->prepare($query);
$stmt->execute([$cell_id]);
$pixels = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Cell <?= $cell_code['cell_code'] ?>- Pixel View</h4>
    </div>
    <div class="table-responsive">
        <table class="table table-hover border shadow-sm">
            <thead class="table-dark text-center">
                <tr>
                    <th>Pixel Code</th>
                    <th>Test Date</th>
                    <th class="table-primary text-dark">Fwd PCE (%)</th>
                    <th class="table-danger text-dark">Rev PCE (%)</th>
                    <th>Hysteresis Index</th>
                    <th>Operator</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody class="text-center align-middle">
                <?php foreach ($pixels as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['pixel_code']) ?></strong></td>
                    <?php if($p['test_date'] != null) : ?>
                    <td><?= date('Y-m-d', strtotime($p['test_date'])) ?></td>
                    <td class="fw-bold text-primary"><?= number_format($p['fwd_eff'], 2) ?>%</td>
                    <td class="fw-bold text-danger"><?= number_format($p['rev_eff'], 2) ?>%</td>
                    <td>
                        <?php
                        $hi = $p['hysteresis_index'];
                        $badgeClass = abs($hi) > 0.05 ? 'bg-warning text-dark' : 'bg-success';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= number_format($hi, 3) ?></span>
                    </td>
                    <td class="small"><?= htmlspecialchars($p['operator']) ?></td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-primary">View Curves</a>
                    </td>
                    <?php else : ?>
                    <td>
                        <td colspan="4" class="text-muted p-4">No IV tests found.</td>
                    </td>
                    <td>
                        <a href="#" class="btn btn-sm btn-outline-primary">Upload IV Test</a>
                    </td>
                    <?php endif ?>
                </tr>
                <?php endforeach; ?>
                <tr class="table-active text-dark">
                    <td colspan="2"><strong>Averages</strong></td>
                    <td><?= $pixels['avg_fwd'] ?? '--' ?></td>
                    <td><?= $pixels['avg_rev'] ?? '--' ?></td>
                    <td><?= $pixels['avg_hi'] ?? '--' ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require "../config/footer.php"; ?>