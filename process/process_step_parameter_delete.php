<?php
require "../config/db.php";

$id = $_GET['id'] ?? null;
$step_id = $_GET['step_id'] ?? null;

if (!$id || !$step_id) {
    die("Invalid request.");
}

$stmt = $pdo->prepare("DELETE FROM process_step_parameters WHERE id = ?");
$stmt->execute([$id]);

header("Location: process_step_parameters.php?step_id=" . $step_id);
exit;

?>