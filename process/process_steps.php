<?php
$pageTitle = "Process Steps";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$steps = $pdo->query("SELECT * FROM process_steps ORDER BY step_name")->fetchAll();
?>

<div class="d-flex justify-content-between mb-3">
    <h4>Process Steps</h4>
    <a href="process_step_edit.php" class="btn btn-primary">New Step</a>
</div>

<table class="table table-hover" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Step</th>
            <th>Description</th>
            <th width="150"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($steps as $step): ?>
        <tr>
            <td><?= htmlspecialchars($step['step_name']) ?></td>
            <td><?= htmlspecialchars($step['description']) ?></td>
            <td class="text-end">
                <a href="process_step_edit.php?id=<?= $step['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                <a href="process_step_parameters.php?step_id=<?= $step['id'] ?>" class="btn btn-sm btn-outline-primary">Parameters</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>
