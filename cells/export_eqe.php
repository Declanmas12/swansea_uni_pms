<?php
require "../config/database.php";

// Prevent any existing warnings or notices from being injected into the CSV file
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

if (isset($_GET['id'])) {
    $measurement_id = (int)$_GET['id'];

    // Fetch Metadata for the filename
    $meta_stmt = $pdo->prepare("SELECT cell_id FROM production.eqe_measurements WHERE id = ?");
    $meta_stmt->execute([$measurement_id]);
    $cell_id = $meta_stmt->fetch(PDO::FETCH_ASSOC)['cell_id'] ?? 'unknown_cell';

    $meta_stmt = $pdo->prepare("SELECT cell_code FROM production.cells WHERE id = ?");
    $meta_stmt->execute([$cell_id]);
    $cell_code = $meta_stmt->fetch(PDO::FETCH_ASSOC)['cell_code'] ?? 'unknown_cell';

    // Fetch Spectral Data
    $query = "SELECT wavelength_nm, qe_value FROM production.eqe_data_points WHERE measurement_id = ? ORDER BY wavelength_nm ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$measurement_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clean the output buffer to ensure no previous echoed text/warnings are in the file
    if (ob_get_length()) ob_end_clean();

    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=EQE_Export_' . $cell_code . '.csv');

    // Open the output stream
    $output = fopen('php://output', 'w');

    /**
     * CORRECTION: Added ",", '"', "\\" to fputcsv
     * This satisfies PHP 8.3+ requirements for the escape parameter.
     */
    
    // Output the CSV Header
    fputcsv($output, ['Wavelength (nm)', 'Quantum Efficiency (%)'], ",", '"', "\\");

    // Output the Data Rows
    foreach ($result as $row) {
        fputcsv($output, [$row['wavelength_nm'], $row['qe_value']], ",", '"', "\\");
    }

    fclose($output);
    exit;
}
?>