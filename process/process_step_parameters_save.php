<?php
require "../config/database.php";

// Get POST values
$process_step_id = $_POST['process_step_id'] ?? null;
$parameter_name  = trim($_POST['parameter_name'] ?? '');
$unit            = trim($_POST['unit'] ?? '');
$min_value       = $_POST['min_value'] ?? null;
$max_value       = $_POST['max_value'] ?? null;
$scope       = $_POST['scope'];
$required = $_POST['required'] ?? 0;

// Basic validation
if (!$process_step_id || !$parameter_name) {
    die("Error: Step ID and parameter name are required.");
}

// Ensure numeric min/max if provided
$min_value = $min_value === '' ? null : $min_value;
$max_value = $max_value === '' ? null : $max_value;

// Insert into database
$stmt = $pdo->prepare("
    INSERT INTO process_step_parameters 
    (process_step_id, parameter_name, unit, min_value, max_value, scope, required) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

try {
    $stmt->execute([
        $process_step_id,
        $parameter_name,
        $unit,
        $min_value,
        $max_value,
        $scope,
        $required
    ]);
} catch (PDOException $e) {
    // Handle duplicate parameter name gracefully
    if ($e->getCode() == 23000) {
        die("Error: Parameter with this name already exists for this step.");
    } else {
        die("Database error: " . $e->getMessage());
    }
}

// Redirect back to the parameters page
header("Location: process_step_parameters.php?step_id=" . $process_step_id);
exit;

?>