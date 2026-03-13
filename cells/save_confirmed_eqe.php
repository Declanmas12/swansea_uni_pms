<?php
session_start();

require "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['temp_data'])) {
    $cell_code = $_POST['cell_code'];
    $jsc = (float)$_POST['jsc'];
    $bandgap = (float)$_POST['bandgap'];
    $data_points = $_SESSION['temp_data'];

    $stmt = $pdo->prepare("
        SELECT id
        FROM cells
        WHERE cell_code = ?
    ");
    $stmt->execute([$cell_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cell_id = $result['id'] ?? null; // Extract the ID from the array

    if (!$cell_id) {
        die("Error: Cell code not found in the cells table.");
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("INSERT INTO production.eqe_measurements (cell_id, jsc_val, bandgap_val) VALUES (?, ?, ?)");
        $stmt->execute([$cell_id, $jsc, $bandgap]);
        
        $measurement_id = $pdo->lastInsertId();

        // Bulk insert spectral data
        $stmt_data = $pdo->prepare("INSERT INTO production.eqe_data_points (measurement_id, wavelength_nm, qe_value) VALUES (?, ?, ?)");
        foreach ($data_points as $point) {
            $stmt_data->execute([$measurement_id, $point['wl'], $point['qe']]);
        }

        $pdo->commit();
        
        // Clean up session and redirect
        unset($_SESSION['temp_data']);
        header("Location: view_eqe.php?id=" . urlencode($measurement_id) . "&status=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollback();
        die("Database Error: " . $e->getMessage());
    }
} else {
    die("Session expired or invalid request.");
}
?>