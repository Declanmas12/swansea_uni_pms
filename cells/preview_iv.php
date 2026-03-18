<?php
session_start();
$pageTitle = "Preview IV Sweep";
require "../config/header.php";

$data = $_SESSION['iv_preview'] ?? null;
if (!$data) die("No data found.");

$fwd_eff = 0; $rev_eff = 0;
foreach ($data['stats'] as $s) {
    if (stripos($s['direction'], 'Forward') !== false) $fwd_eff = $s['eff'];
    if (stripos($s['direction'], 'Reverse') !== false) $rev_eff = $s['eff'];
}
$hi = ($rev_eff != 0) ? ($rev_eff - $fwd_eff) / $rev_eff : 0;
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>IV Analysis: <?= htmlspecialchars($data['meta']['cell_code']) ?></h3>
        <a href="save_iv.php" class="btn btn-primary btn-lg shadow">Confirm & Save Data</a>
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
                        <?php foreach ($data['stats'] as $s): ?>
                        <tr>
                            <td><strong><?= $s['direction'] ?></strong></td>
                            <td><?= number_format($s['voc'], 3) ?></td>
                            <td><?= number_format($s['jsc'], 2) ?></td>
                            <td><?= number_format($s['ff'], 2) ?></td>
                            <td class="text-primary"><strong><?= number_format($s['eff'], 2) ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <canvas id="ivChart" style="height: 450px;"></canvas>
        </div>
    </div>
</div>
<br>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('ivChart').getContext('2d');
const fwd = <?= json_encode($data['points']['forward']) ?>.map(p => ({x: p.v, y: p.j}));
const rev = <?= json_encode($data['points']['reverse']) ?>.map(p => ({x: p.v, y: p.j}));

new Chart(ctx, {
    type: 'line',
    data: {
        datasets: [
            { label: 'Forward Sweep', data: fwd, borderColor: '#4e73df', tension: 0.1, pointRadius: 0 },
            { label: 'Reverse Sweep', data: rev, borderColor: '#e74a3b', tension: 0.1, pointRadius: 0 }
        ]
    },
    options: {
        scales: {
            x: { type: 'linear', title: { display: true, text: 'Voltage (V)' } },
            y: { title: { display: true, text: 'Current Density (mA/cm²)' } }
        }
    }
});
</script>