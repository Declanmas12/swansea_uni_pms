<?php
$pageTitle = "Flow Builder";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$flow_id = $_GET['id'];

$flow = $pdo->prepare("SELECT * FROM process_flows WHERE id=?");
$flow->execute([$flow_id]);
$flow = $flow->fetch();

$steps = $pdo->query("SELECT * FROM process_steps ORDER BY step_name")->fetchAll();

$flowSteps = $pdo->prepare("
    SELECT pfs.id, ps.step_name
    FROM process_flow_steps pfs
    JOIN process_steps ps ON pfs.process_step_id = ps.id
    WHERE pfs.process_flow_id = ?
    ORDER BY pfs.step_order
");
$flowSteps->execute([$flow_id]);
$flowSteps = $flowSteps->fetchAll();
?>

<h4><?= htmlspecialchars($flow['flow_name']) ?> (<?= $flow['version'] ?>)</h4>

<form method="post" action="process_flow_step_add.php" class="row g-2 mb-4">
    <input type="hidden" name="process_flow_id" value="<?= $flow_id ?>">
    <div class="col">
        <select name="process_step_id" class="form-select">
            <?php foreach ($steps as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['step_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-primary">Add Step</button>
    </div>
</form>

<ul class="list-group" id="stepList">
    <?php foreach ($flowSteps as $fs): ?>
    <li class="list-group-item d-flex align-items-center" data-id="<?= $fs['id'] ?>">
        <i class="bi bi-grip-vertical me-2"></i>
        <?= htmlspecialchars($fs['step_name']) ?>
    </li>
    <?php endforeach; ?>
</ul>

<script>
new Sortable(stepList, {
    animation: 150,
    onEnd: function () {
        let order = [];
        document.querySelectorAll("#stepList li").forEach((el, idx) => {
            order.push({id: el.dataset.id, order: idx + 1});
        });

        fetch("process_flow_reorder.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(order)
        });
    }
});
</script>

<?php require "../config/footer.php"; ?>
