<?php
require "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flow_id = $_POST['process_flow_id'];
    $step_id = $_POST['process_step_id'];

    // Get the current max step_order for this flow
    $stmt = $pdo->prepare("SELECT MAX(step_order) as max_order FROM process_flow_steps WHERE process_flow_id = ?");
    $stmt->execute([$flow_id]);
    $maxOrder = $stmt->fetchColumn();
    $newOrder = $maxOrder ? $maxOrder + 1 : 1;

    // Insert new flow step
    $stmt = $pdo->prepare("
        INSERT INTO process_flow_steps (process_flow_id, process_step_id, step_order)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$flow_id, $step_id, $newOrder]);

    header("Location: process_flow_builder.php?id=" . $flow_id);
    exit;
}
