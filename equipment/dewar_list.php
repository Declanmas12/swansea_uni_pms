<?php
$pageTitle = "LN2 Dewar List";
require "../config/database.php";
require "../config/header.php";

$stmt = $pdo->query("SELECT * FROM production.dewars");
$dewars = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="d-flex justify-content-between mb-3">
    <h4>Liquid Nitrogen Dewars</h4>
    <a href="dewar_edit.php" class="btn btn-primary">New Dewar</a>
</div>

<table class="table" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Dewar Name</th>
            <th>Location</th>
            <th>Max Volume</th>
            <th>Fill Level</th>
            <th width="200"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dewars as $d): ?>
        <tr VALIGN="MIDDLE">
            <td><?= htmlspecialchars($d['name']) ?></td>
            <td><?= htmlspecialchars($d['location']) ?></td>
            <td><?= htmlspecialchars($d['max_volume_litres']) ?></td>
            <td>
                <progress id="volume" value=<?= $d['current_volume_litres']?> max=<?= $d['max_volume_litres']?>></progress>
            </td>
            <td class="text-end">
                <a href="ln2_dewar_monitoring.php?dewar_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                <a href="dewar_edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary">Update</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>