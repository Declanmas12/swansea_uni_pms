<?php
require "../config/database.php";

$ids = explode(',', $_GET['ids'] ?? '');
if (empty($ids)) die("No tests selected.");

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=Bulk_EQE_Export.csv');

$output = fopen('php://output', 'w');

// 1. Fetch all data into a mapped array
$matrix = [];
$headers = ['Wavelength (nm)'];

foreach ($ids as $id) {
    $stmt = $pdo->prepare("SELECT c.cell_code FROM eqe_measurements e JOIN cells c ON c.id = e.cell_id WHERE e.id = ?");
    $stmt->execute([$id]);
    $headers[] = $stmt->fetch(PDO::FETCH_ASSOC)['cell_code'];

    $stmt = $pdo->prepare("SELECT wavelength_nm, qe_value FROM eqe_data_points WHERE measurement_id = ? ORDER BY wavelength_nm ASC");
    $stmt->execute([$id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $matrix[$row['wavelength_nm']][] = $row['qe_value'];
    }
}

// 2. Write CSV
fputcsv($output, $headers, ",", '"', "\\");
foreach ($matrix as $wl => $qes) {
    $row = array_merge([$wl], $qes);
    fputcsv($output, $row, ",", '"', "\\");
}
fclose($output);