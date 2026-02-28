<?php
$pageTitle = "Flow Visualization";
require "../config/database.php";
require "../config/header.php";

$flow_id = $_GET['id'] ?? null;
if (!$flow_id) {
    echo "<div class='alert alert-danger'>No flow selected.</div>";
    require "../config/footer.php";
    exit;
}

// Fetch flow info
$flow = $pdo->prepare("SELECT * FROM process_flows WHERE id=?");
$flow->execute([$flow_id]);
$flow = $flow->fetch();

// Fetch ordered steps with parameters
$stmt = $pdo->prepare("
    SELECT pfs.step_order, ps.step_name, ps.description, spp.parameter_name, spp.unit, spp.min_value, spp.max_value
    FROM process_flow_steps pfs
    JOIN process_steps ps ON pfs.process_step_id = ps.id
    LEFT JOIN process_step_parameters spp ON ps.id = spp.process_step_id
    WHERE pfs.process_flow_id = ?
    ORDER BY pfs.step_order, spp.id
");
$stmt->execute([$flow_id]);
$rows = $stmt->fetchAll();

// Group parameters by step
$steps = [];
foreach ($rows as $row) {
    $key = $row['step_order'] . '-' . $row['step_name'];
    if (!isset($steps[$key])) {
        $steps[$key] = [
            'step_name' => $row['step_name'],
            'description' => $row['description'],
            'parameters' => []
        ];
    }
    if ($row['parameter_name']) {
        $steps[$key]['parameters'][] = [
            'name' => $row['parameter_name'],
            'unit' => $row['unit'],
            'min' => $row['min_value'],
            'max' => $row['max_value']
        ];
    }
}
?>

<h4><?= htmlspecialchars($flow['flow_name']) ?> (<?= htmlspecialchars($flow['version']) ?>)</h4>

<div class="timeline mt-4">
    <?php foreach ($steps as $step): ?>
        <div class="timeline-step card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($step['step_name']) ?></h5>
                <?php if ($step['description']): ?>
                    <p class="card-text"><?= htmlspecialchars($step['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($step['parameters'])): ?>
                <table class="table table-sm mb-0" style="text-align:center;">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Unit</th>
                            <th>Min</th>
                            <th>Max</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($step['parameters'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= $p['min'] ?></td>
                            <td><?= $p['max'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.timeline {
    position: relative;
    margin-left: 20px;
}
.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 15px;
    width: 4px;
    background-color: #0d6efd;
    border-radius: 2px;
}
.timeline-step {
    position: relative;
    margin-left: 40px;
}
.timeline-step::before {
    content: '';
    position: absolute;
    left: -28px;
    top: 15px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #0d6efd;
    border: 3px solid white;
}
</style>

<?php require "../config/footer.php"; ?>
