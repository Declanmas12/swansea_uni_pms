<?php
$pageTitle = "Cell Pixel Results";
require "../config/database.php";
require "../config/header.php";

function calculateAverage($array) {
    // Remove null values specifically
    $filtered = array_filter($array, fn($value) => !is_null($value));

    // Prevent division by zero if the array is empty after filtering
    if (count($filtered) === 0) {
        return 0; 
    }

    return array_sum($filtered) / count($filtered);
}

$cell_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT cell_code FROM production.cells WHERE id=?");
$stmt->execute([$cell_id]);
$cell_code = $stmt->fetch(PDO::FETCH_ASSOC);

$query = "
SELECT p.*, piv.test_date, piv.fwd_eff, piv.rev_eff, piv.hysteresis_index, piv.operator
FROM cell_pixels p 
LEFT JOIN pixel_iv_measurements piv ON piv.pixel_id = p.id
WHERE p.cell_id = ?"
;

$stmt = $pdo->prepare($query);
$stmt->execute([$cell_id]);

$pixels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fwd_eff_values = [];
$rev_eff_values = [];
$hi_values = [];

foreach ($pixels as $p) {
    $fwd_eff_values[] = $p['fwd_eff'];
    $rev_eff_values[] = $p['rev_eff'];
    $hi_values[] = $p['hysteresis_index'];
};

$avg_fwd = round(calculateAverage($fwd_eff_values),2);
$avg_rev = round(calculateAverage($rev_eff_values),2);
$avg_hi = round(calculateAverage($hi_values),2);

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Cell: <?= $cell_code['cell_code'] ?> - Pixel View</h4>
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
                        <a href="view_pixel_iv.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">View Curves</a>
                    </td>
                    <?php else : ?>
                    <td>
                        <td colspan="4" class="text-muted p-4">No IV tests found.</td>
                    </td>
                    <td>
                        <a href="pixel_iv_upload.php?id=<?= $p['pixel_code'] ?>" class="btn btn-sm btn-outline-primary">Upload IV Test</a>
                    </td>
                    <?php endif ?>
                </tr>
                <?php endforeach; ?>
                <tr class="table-active text-dark">
                    <td colspan="2"><strong>Averages</strong></td>
                    <td><?= $avg_fwd ?? '--' ?></td>
                    <td><?= $avg_rev ?? '--' ?></td>
                    <td><?= $avg_hi ?? '--' ?></td>
                    <td colspan="2"></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require "../config/footer.php"; ?>