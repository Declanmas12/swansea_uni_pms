<?php
$pageTitle = "Process Flows";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$flows = $pdo->query("SELECT * FROM process_flows ORDER BY created_at DESC")->fetchAll();
?>

<div class="d-flex justify-content-between mb-3">
    <h4>Process Flows</h4>
    <a href="process_flow_edit.php" class="btn btn-primary">New Flow</a>
</div>

<table class="table" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Name</th>
            <th>Version</th>
            <th>Status</th>
            <th width="200"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($flows as $f): ?>
        <tr>
            <td><?= htmlspecialchars($f['flow_name']) ?></td>
            <td><?= htmlspecialchars($f['version']) ?></td>
            <td><?= htmlspecialchars($f['status']) ?></td>
            <td class="text-end">
                <a href="process_flow_edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                <a href="process_flow_manager.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary">Build</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>
