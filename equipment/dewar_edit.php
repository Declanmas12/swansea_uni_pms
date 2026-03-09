<?php
$pageTitle = "New Liquid Nitrogen Dewar";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id) {
        $stmt = $pdo->prepare("UPDATE dewars SET name=?, location=?, max_volume_litres=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['location'], $_POST['max_dewar_volume'], $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO dewars (name, location, max_volume_litres, current_volume_litres) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['location'], $_POST['max_dewar_volume'], $_POST['current_dewar_volume']]);
    }
    header("Location: dewar_list.php");
    exit;
}

$dewar = ['name'=>'','max_volume_litres'=>'','current_volume_litres'=>'', 'location'=>''];
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM dewars WHERE id=?");
    $stmt->execute([$id]);
    $dewar = $stmt->fetch();
}
?>

<form method="post" class="card p-4">
    <div class="mb-3">
        <label class="form-label">Dewar Name</label>
        <input name="name" class="form-control" required value="<?= htmlspecialchars($dewar['name']) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Location</label>
        <textarea name="location" class="form-control"><?= htmlspecialchars($dewar['location']) ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Max Dewar Volume</label>
        <input name="max_dewar_volume" class="form-control" required value="<?= htmlspecialchars($dewar['max_volume_litres']) ?>">
    </div>

    <?php if($id == null) : ?>
        <div class="mb-3">
            <label class="form-label">Current Dewar Volume</label>
            <input name="current_dewar_volume" class="form-control" required value="<?= htmlspecialchars($dewar['current_volume_litres']) ?>">
        </div>
    <?php endif ?>

    <button class="btn btn-success">Save</button>
</form>

<?php require "../config/footer.php"; ?>

