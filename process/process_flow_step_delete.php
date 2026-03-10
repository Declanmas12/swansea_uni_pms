<?php
require "../config/database.php";

$id = $_POST['id'] ?? null;

if (!$id) {
    http_response_code(400);
    exit;
}

$pdo->beginTransaction();

/* Get flow id before deleting */
$stmt = $pdo->prepare("SELECT process_flow_id FROM process_flow_steps WHERE id=?");
$stmt->execute([$id]);
$flowId = $stmt->fetchColumn();

/* Delete step */
$stmt = $pdo->prepare("DELETE FROM process_flow_steps WHERE id=?");
$stmt->execute([$id]);

/* Renumber steps */
$stmt = $pdo->prepare("
    SELECT id 
    FROM process_flow_steps
    WHERE process_flow_id = ?
    ORDER BY step_order
");
$stmt->execute([$flowId]);

$steps = $stmt->fetchAll(PDO::FETCH_COLUMN);

$update = $pdo->prepare("UPDATE process_flow_steps SET step_order=? WHERE id=?");

$order = 1;
foreach ($steps as $sid) {
    $update->execute([$order++, $sid]);
}

$pdo->commit();

echo "OK";