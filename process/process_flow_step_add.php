<?php
require "../config/database.php";

$flowId = $_POST['process_flow_id'];
$stepId = $_POST['process_step_id'];

/* Find next order */
$stmt = $pdo->prepare("
    SELECT COALESCE(MAX(step_order),0)+1
    FROM process_flow_steps
    WHERE process_flow_id=?
");
$stmt->execute([$flowId]);
$order = $stmt->fetchColumn();

/* Insert */
$stmt = $pdo->prepare("
    INSERT INTO process_flow_steps
    (process_flow_id, process_step_id, step_order)
    VALUES (?, ?, ?)
");

$stmt->execute([$flowId, $stepId, $order]);

echo "OK";
