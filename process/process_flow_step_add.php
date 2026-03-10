<?php
require "../config/database.php";

$flowId = $_POST['process_flow_id'];
$stepId = $_POST['process_step_id'];
$position = $_POST['position'] ?? 1;

$pdo->beginTransaction();

/* Shift steps down */
$shift = $pdo->prepare("
    UPDATE process_flow_steps
    SET step_order = step_order + 1
    WHERE process_flow_id = ?
    AND step_order >= ?
");

$shift->execute([$flowId, $position]);

/* Insert new step */
$insert = $pdo->prepare("
    INSERT INTO process_flow_steps
    (process_flow_id, process_step_id, step_order)
    VALUES (?, ?, ?)
");

$insert->execute([$flowId, $stepId, $position]);

$pdo->commit();

echo "OK";<?php
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

