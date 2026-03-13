<?php
$pageTitle = "EQE Viewer";
require "../config/database.php";
require "../config/header.php";

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT cell_id, jsc_val, bandgap_val FROM production.eqe_measurements WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cell_id = $result['cell_id'];
$measurement_id = $id ?? null; // Extract the ID from the array
$jsc = $result['jsc_val'] ?? null; // Extract the ID from the array
$bandgap = $result['bandgap_val'] ?? null; // Extract the ID from the array

if (!$measurement_id) {
    die("Error: Cell code not found in the eqe table.");
}

$stmt = $pdo->prepare("SELECT cell_code FROM production.cells WHERE id = ?");
$stmt->execute([$cell_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$cell_code = $result['cell_code'];

$query = "SELECT wavelength_nm, qe_value FROM production.eqe_data_points WHERE measurement_id = ? ORDER BY wavelength_nm ASC";
$stmt = $pdo->prepare($query);
$stmt->execute([$measurement_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$values = [];

foreach ($results as $row) {
    $labels[] = $row['wavelength_nm'];
    $values[] = $row['qe_value'];
}

// Convert PHP arrays to JSON for the frontend
$json_labels = json_encode($labels);
$json_values = json_encode($values);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card mt-4 shadow">
    <div class="card-header bg-dark text-white d-flex justify-content-between">
        <span>EQE Curve</span>
        <span>Cell: <?php echo htmlspecialchars($cell_code); ?></span>
        <a href="eqe_test_list.php" class="btn btn-sm btn-light">Back to List</a>
    </div>
    <div class="card-body">
        <div style="height: 400px;">
            <canvas id="eqeChart"></canvas>
        </div>
    </div>
</div>

<div class="row align-items-center bg-white p-3 border rounded shadow-sm mx-0" style="text-align: center;">
    <div class="col-md-3 border-end">
        <small class="text-muted d-block text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Integrated Current (J<sub>sc</sub>)</small>
        <span class="h5 mb-0 text-primary"><strong><?= number_format($jsc, 2) ?></strong></span> 
        <small class="text-muted">mA/cm²</small>
    </div>

    <div class="col-md-3 border-end">
        <small class="text-muted d-block text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">Estimated Bandgap (E<sub>g</sub>)</small>
        <span class="h5 mb-0 text-success"><strong><?= number_format($bandgap, 3) ?></strong></span> 
        <small class="text-muted">eV</small>
    </div>

    <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <center>
            <a href="export_eqe.php?id=<?= $measurement_id ?>" class="btn btn-outline-success btn-sm me-2">
                <i class="bi bi-download"></i> Export CSV
            </a>
            
            <button onclick="window.print();" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </center>
    </div>
</div>

<script>
const ctx = document.getElementById('eqeChart').getContext('2d');
const eqeChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo $json_labels; ?>, // Wavelengths
        datasets: [{
            label: 'EQE (%)',
            data: <?php echo $json_values; ?>, // QE Values
            borderColor: 'rgba(54, 162, 235, 1)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            borderWidth: 2,
            pointRadius: 0, // Set to 0 for a clean line, or 2 to see points
            fill: true,
            tension: 0.3 // Smooths the curve
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                title: { display: true, text: 'Wavelength (nm)' }
            },
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Quantum Efficiency (%)' }
            }
        },
        plugins: {
            tooltip: {
                mode: 'index',
                intersect: false
            }
        }
    }
});
</script>

<?php require "../config/footer.php"; ?>