<?php
require "../config/database.php";
$data = json_decode(file_get_contents("php://input"), true);

$stmt = $pdo->prepare("UPDATE process_flow_steps SET step_order=? WHERE id=?");

foreach ($data as $row) {
    $stmt->execute([$row['order'], $row['id']]);
}
