<?php
$pageTitle = "Pixel IV Curve View";
require "../config/database.php";
require "../config/header.php";

$id = $_GET['id'] ?? 0;
// Fetch the measurement and raw points from your database
$stmt = $pdo->prepare("
    SELECT iv.*, p.pixel_code
    FROM production.pixel_iv_measurements iv
    JOIN cell_pixels p
    ON iv.pixel_id = p.id
    WHERE iv.pixel_id = ?");
$stmt->execute([$id]);
$iv = $stmt->fetch();

// Fetch coordinates from your iv_points table
$pts = $pdo->prepare("SELECT voltage_v, current_density_ma_cm2, direction FROM production.pixel_iv_data_points WHERE measurement_id = ? ORDER BY voltage_v ASC");
$pts->execute([$iv['id']]);
$points = $pts->fetchAll(PDO::FETCH_ASSOC);

// Format points for Chart.js
$fwd = array_values(array_filter($points, fn($p) => $p['direction'] == 'forward'));
$rev = array_values(array_filter($points, fn($p) => $p['direction'] == 'reverse'));

$hi = ($iv['rev_eff'] != 0) ? ($iv['rev_eff'] - $iv['fwd_eff']) / $iv['rev_eff'] : 0;
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>IV Analysis: <?= htmlspecialchars($iv['pixel_code']) ?></h3>
    </div>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-primary shadow-sm h-100">
                <div class="card-body text-center">
                    <small class="text-muted d-block text-uppercase">Hysteresis Index</small>
                    <h2 class="display-6 <?= abs($hi) > 0.05 ? 'text-danger' : 'text-success' ?>">
                        <?= number_format($hi, 3) ?>
                    </h2>
                    <span class="badge bg-light text-dark border"><?= number_format($hi * 100, 1) ?>% Variance</span>
                </div>
            </div>
        </div>
        <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <table class="table mb-0 text-center align-middle h-100">
                        <thead class="table-light">
                            <tr><th>Sweep</th><th>V<sub>oc</sub> (V)</th><th>J<sub>sc</sub> (mA/cm²)</th><th>FF (%)</th><th>PCE (%)</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($iv != null): ?>
                            <tr>
                                <td><strong>Forward</strong></td>
                                <td><?= number_format($iv['fwd_voc'], 3) ?></td>
                                <td><?= number_format($iv['fwd_jsc'], 2) ?></td>
                                <td><?= number_format($iv['fwd_ff'], 2) ?></td>
                                <td class="text-primary"><strong><?= number_format($iv['fwd_eff'], 2) ?>%</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Reverse</strong></td>
                                <td><?= number_format($iv['rev_voc'], 3) ?></td>
                                <td><?= number_format($iv['rev_jsc'], 2) ?></td>
                                <td><?= number_format($iv['rev_ff'], 2) ?></td>
                                <td class="text-primary"><strong><?= number_format($iv['rev_eff'], 2) ?>%</strong></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <div class="card-body">
        <div style="height: 50%;">
            <canvas id="ivChart"></canvas>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end mb-3">
    <a href="export_iv.php?id=<?= $id ?>" class="btn btn-success">
        <i class="fa fa-download"></i> Export as CSV
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('ivChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        datasets: [
            { label: 'Forward', data: <?= json_encode($fwd) ?>, borderColor: 'blue', parsing: {xAxisKey: 'voltage_v', yAxisKey: 'current_density_ma_cm2'} },
            { label: 'Reverse', data: <?= json_encode($rev) ?>, borderColor: 'red', parsing: {xAxisKey: 'voltage_v', yAxisKey: 'current_density_ma_cm2'} }
        ]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true, // Let the chart define its own size
        aspectRatio: 2,            // 2:1 ratio
        scales: { 
            x: { 
            type: 'linear', // Force linear numerical scale
            title: { display: true, text: 'Voltage (V)' },
            ticks: { beginAtZero: false } 
        }, 
        y: { 
            type: 'linear',
            title: { display: true, text: 'Current (mA/cm²)' } 
        } 
        } 
    }
});
</script>