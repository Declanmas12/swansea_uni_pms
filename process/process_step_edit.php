<?php
$pageTitle = "Edit Process Step";
require "../config/database.php";
require "../config/header.php";

$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE process_steps SET step_name=?, description=? WHERE id=?");
        $stmt->execute([$_POST['step_name'], $_POST['description'], $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO process_steps (step_name, description) VALUES (?, ?)");
        $stmt->execute([$_POST['step_name'], $_POST['description']]);
    }
    header("Location: process_steps.php");
    exit;
}

$step = ['step_name'=>'','description'=>''];
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM process_steps WHERE id=?");
    $stmt->execute([$id]);
    $step = $stmt->fetch();
}
?>

<form method="post" class="card p-4">
    <div class="mb-3">
        <label class="form-label">Step Name</label>
        <input name="step_name" class="form-control" required value="<?= htmlspecialchars($step['step_name']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($step['description']) ?></textarea>
    </div>

    <button class="btn btn-success">Save</button>
</form>

<?php require "../config/footer.php"; ?>
