<?php
$pageTitle = "Step Parameters";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$step_id = $_GET['step_id'];

$step = $pdo->prepare("SELECT * FROM process_steps WHERE id=?");
$step->execute([$step_id]);
$step = $step->fetch();

$params = $pdo->prepare("SELECT * FROM process_step_parameters WHERE process_step_id=?");
$params->execute([$step_id]);
$params = $params->fetchAll();
?>

<h5><?= htmlspecialchars($step['step_name']) ?></h5>

<form method="post" action="process_step_parameters_save.php" class="row g-2 mb-4">
    <input type="hidden" name="process_step_id" value="<?= $step_id ?>">

    <div class="col">
        <input name="parameter_name" class="form-control" placeholder="Parameter" required>
    </div>
    <div class="col">
        <select name="scope" class="form-select" required>
            <option value="BATCH" <?= ($param['scope'] ?? '') === 'BATCH' ? 'selected' : '' ?>>
                Batch-level
            </option>
            <option value="CELL" <?= ($param['scope'] ?? 'CELL') === 'CELL' ? 'selected' : '' ?>>
                Cell-level
            </option>
        </select>
    </div>
    <div class="col">
        <input name="unit" class="form-control" placeholder="Unit">
    </div>
    <div class="col">
        <input name="min_value" class="form-control" placeholder="Min">
    </div>
    <div class="col">
        <input name="max_value" class="form-control" placeholder="Max">
    </div>
    <br>
    <div class="col form-check">
        <label class="form-check-label" for="required">Required?</label>
        <input class="form-check-input" type="checkbox" id="required" name="required" value="1">
    </div>
    <div class="col-auto">
        <button class="btn btn-primary">Add</button>
    </div>
</form>

<table class="table table-sm" style="text-align:center;">
    <thead>
        <tr>
            <th>Parameter</th><th>Scope</th><th>Unit</th><th>Min</th><th>Max</th><th>Required?</th><th width="120"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($params as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
            <td><?= htmlspecialchars($p['scope']) ?></td>
            <td><?= htmlspecialchars($p['unit']) ?></td>
            <td><?= $p['min_value'] ?></td>
            <td><?= $p['max_value'] ?></td>
            <?php if($p['required'] == 1) : ?>
                <td>Yes</td>
            <?php else: ?>
                <td>No</td>
            <?php endif ?>
            <td class="text-end">
                <a href="process_step_parameter_edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                <a href="process_step_parameter_delete.php?id=<?= $p['id'] ?>&step_id=<?= $step_id ?>" 
                   class="btn btn-sm btn-outline-danger" 
                   onclick="return confirm('Are you sure you want to delete this parameter?');">
                    Delete
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>


<?php require "../config/footer.php"; ?>
