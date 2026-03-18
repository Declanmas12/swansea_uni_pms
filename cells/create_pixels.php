<?php
require "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cell_id = $_POST['cellid'];
    $pixels = $_POST['pixels'];
    $cell_code = $_POST['cell_code'];

    $index = 0;

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("INSERT INTO production.cell_pixels (cell_id, pixel_code) VALUES (?,?)");
        for ($index = 0; $index < $pixels; $index++) {
            $pixel_code = $cell_code ."-P0". $index;
            $stmt->execute([$cell_id, $pixel_code]);
        }

        $pdo->commit();

        // Clean up session and redirect
        header("Location: view_pixels.php?cellid=$cell_id&status=success");
        exit();

    } catch (Exception $e) {
        $pdo->rollback();
        die("Database Error: " . $e->getMessage());
    }
} else {
    die("Session expired or invalid request.");
}