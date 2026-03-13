<?php
$pageTitle = "EQE Comparison";
require "../config/database.php";
require "../config/header.php";

$test_ids = $_POST['test_ids'] ?? [];

if (empty($test_ids)) {
    echo "<div class='alert alert-warning'>Please select at least one test to compare.</div>";
    require "../config/footer.php";
    exit;
}

// Logic for Bulk Export if that button was clicked
if (($_POST['action'] ?? '') === 'export') {
    // Redirect to a bulk export script or handle here
    header("Location: bulk_export_eqe.php?ids=" . implode(',', $test_ids));
    exit;
}

$datasets = [];
$all_labels = [];

foreach ($test_ids as $id) {
    // 1. Get Cell Meta
    $stmt = $pdo->prepare("SELECT e.id, c.cell_code FROM eqe_measurements e JOIN cells c ON c.id = e.cell_id WHERE e.id = ?");
    $stmt->execute([$id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get Data Points
    $stmt = $pdo->prepare("SELECT wavelength_nm, qe_value FROM eqe_data_points WHERE measurement_id = ? ORDER BY wavelength_nm ASC");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        $data[] = (float)$row['qe_value'];
        if (count($all_labels) < count($rows)) { 
            $all_labels[] = (float)$row['wavelength_nm']; 
        }
    }

    $datasets[] = [
        'label' => $meta['cell_code'],
        'data' => $data,
        'fill' => false,
        'borderWidth' => 2,
        'tension' => 0.1
    ];
}

$json_labels = json_encode($all_labels);
$json_datasets = json_encode($datasets);
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">EQE Comparison Analysis</h5>
            <a href="eqe_test_list.php" class="btn btn-sm btn-light">Back to List</a>
        </div>
        <div class="card-body">
            <div style="height: 500px;">
                <canvas id="compareChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1'];
const ctx = document.getElementById('compareChart').getContext('2d');
const rawData = <?= $json_datasets ?>;

// Dynamically assign colors
const finalDatasets = rawData.map((ds, i) => ({
    ...ds,
    borderColor: colors[i % colors.length],
    backgroundColor: colors[i % colors.length]
}));

new Chart(ctx, {
    type: 'line',
    data: { labels: <?= $json_labels ?>, datasets: finalDatasets },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { title: { display: true, text: 'Wavelength (nm)' } },
            y: { title: { display: true, text: 'QE (%)' }, beginAtZero: true }
        },
        plugins: {
            tooltip: { mode: 'index', intersect: false }
        }
    }
});
</script>

<?php require "../config/footer.php"; ?>