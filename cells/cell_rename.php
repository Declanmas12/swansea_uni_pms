<?php
require "../config/database.php";

header('Content-Type: application/json');

$old = $_POST['old_code'] ?? '';
$new = $_POST['new_code'] ?? '';

if (!$old || !$new) {
    echo json_encode(['status'=>'error','message'=>'Missing cell codes']);
    exit;
}

// Check cell eligibility
$stmt = $pdo->prepare("
    SELECT status, inventory_status, module_id
    FROM cells
    WHERE cell_code = ?
");
$stmt->execute([$old]);
$cell = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cell) {
    echo json_encode(['status'=>'error','message'=>'Cell not found']);
    exit;
}

// BUSINESS RULES
if ($cell['status'] === 'scrapped') {
    echo json_encode(['status'=>'error','message'=>'Scrapped cells cannot be renamed']);
    exit;
}

if (!in_array($cell['inventory_status'], ['AVAILABLE', 'IN_PROCESS'])) {
    echo json_encode(['status'=>'error','message'=>'Only AVAILABLE cells can be renamed']);
    exit;
}

if (!empty($cell['module_id'])) {
    echo json_encode(['status'=>'error','message'=>'Cells already assigned to a module cannot be renamed']);
    exit;
}

// Check new code uniqueness
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cells WHERE cell_code = ?");
$stmt->execute([$new]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['status'=>'error','message'=>'New cell code already exists']);
    exit;
}

// Perform atomic rename
$pdo->beginTransaction();
try {

    // Update master cell table
    $pdo->prepare("
        UPDATE cells
        SET cell_code = ?
        WHERE cell_code = ?
    ")->execute([$new, $old]);

    $pdo->commit();

    echo json_encode(['status'=>'ok']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status'=>'error','message'=>'Rename failed: '.$e->getMessage()]);
}
