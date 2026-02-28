<?php
$pageTitle = "Batch List";
require "../config/database.php";
require "../config/header.php";


$sql = "
SELECT b.*, p.product_code, p.revision,
pf.flow_name AS flow_name, pf.version AS flow_version,
COUNT(c.id) AS cell_count
FROM batches b
JOIN products p ON p.id = b.product_id
JOIN process_flows pf ON pf.id = b.process_flow_id
LEFT JOIN cells c ON c.batch_id = b.id
WHERE b.status IN ('planned','active', 'completed')
GROUP BY b.id
ORDER BY b.created_at DESC
";


$batches = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between mb-3">
    <h4>Production Batches</h4>
    <a href="batch_create.php" class="btn btn-primary">New Batch</a>
</div>

<table class="table table-hover" style="text-align:center;">
    <thead class="table-dark">
        <tr>
            <th>Batch</th>
            <th>Product</th>
            <th>Revision</th>
            <th>Flow</th>
            <th>Status</th>
            <th>Cells</th>
            <th>Started</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($batches as $b): ?>
            <tr VALIGN="MIDDLE">
                <td><?= htmlspecialchars($b['batch_code']) ?></td>
                <td><?= htmlspecialchars($b['product_code']) ?></td>
                <td><?= htmlspecialchars($b['revision']) ?></td>
                <td><?= htmlspecialchars($b['flow_name']) ?> v<?= htmlspecialchars($b['flow_version']) ?></td>
                <td>
                <span class="badge bg-<?= match($b['status']){
                'planned'=>'secondary','active'=>'success','completed'=>'dark','aborted'=>'danger'
                } ?>">
                <?= strtoupper($b['status']) ?>
                </span>
                </td>
                <td><?= $b['cell_count'] ?></td>
                <td><?= $b['started_at'] ?: '-' ?></td>
                <?php if($b['status'] != 'completed') :?>
                    <td><a href="batch_view.php?id=<?=$b['id']?>"><button class="btn btn-outline-primary w-100">View Batch</button></a></td>
                <?php else: ?>
                    <td><a href="batch_summary.php?batch_id=<?=$b['id']?>"><button class="btn btn-outline-primary w-100">Batch Summary</button></a></td>
                <?php endif ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require "../config/footer.php"; ?>