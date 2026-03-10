<?php
$pageTitle = "Process Flow Manager";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$flow_id = $_GET['id'] ?? null;
if (!$flow_id) {
    echo "<div class='alert alert-danger'>No process flow selected.</div>";
    require "includes/footer.php";
    exit;
}

/* ---------------- Load Flow ---------------- */
$flowStmt = $pdo->prepare("SELECT * FROM process_flows WHERE id=?");
$flowStmt->execute([$flow_id]);
$flow = $flowStmt->fetch();

/* ---------------- Clone Flow ---------------- */
if (isset($_POST['clone_flow'])) {
    $newVersion = number_format((float)$flow['version'] + 0.1, 1);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO process_flows (flow_name, version, description, status)
                            VALUES (?, ?, ?, 'draft')");
    $stmt->execute([
        $flow['flow_name'],
        $newVersion,
        $flow['description']
    ]);

    $newFlowId = $pdo->lastInsertId();

    $steps = $pdo->prepare("SELECT process_step_id, step_order FROM process_flow_steps WHERE process_flow_id=? ORDER BY step_order");
    $steps->execute([$flow_id]);

    $insert = $pdo->prepare("INSERT INTO process_flow_steps (process_flow_id, process_step_id, step_order)
                              VALUES (?, ?, ?)");

    foreach ($steps as $s) {
        $insert->execute([$newFlowId, $s['process_step_id'], $s['step_order']]);
    }

    $pdo->commit();

    header("Location: process_flow_manager.php?id=" . $newFlowId);
    exit;
}

/* ---------------- Load Steps ---------------- */
$allSteps = $pdo->query("SELECT * FROM process_steps ORDER BY step_name")->fetchAll();

$stmt = $pdo->prepare("
    SELECT pfs.id AS flow_step_id, pfs.step_order,
           ps.step_name, ps.description,
           spp.parameter_name, spp.unit, spp.min_value, spp.max_value
    FROM process_flow_steps pfs
    JOIN process_steps ps ON pfs.process_step_id = ps.id
    LEFT JOIN process_step_parameters spp ON ps.id = spp.process_step_id
    WHERE pfs.process_flow_id = ?
    ORDER BY pfs.step_order, spp.id
");
$stmt->execute([$flow_id]);
$rows = $stmt->fetchAll();

/* ---------------- Group Steps + Parameters ---------------- */
$flowSteps = [];
foreach ($rows as $r) {
    if (!isset($flowSteps[$r['flow_step_id']])) {
        $flowSteps[$r['flow_step_id']] = [
            'name' => $r['step_name'],
            'description' => $r['description'],
            'params' => []
        ];
    }
    if ($r['parameter_name']) {
        $flowSteps[$r['flow_step_id']]['params'][] = $r;
    }
}
?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Flow Definition</h5>
                <p><strong>Name:</strong> <?= htmlspecialchars($flow['flow_name']) ?></p>
                <p><strong>Version:</strong> <?= htmlspecialchars($flow['version']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($flow['status']) ?></p>

                <form method="post" class="mt-3">
                    <button name="clone_flow" class="btn btn-outline-primary w-100">
                        Clone & Increment Version
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">

                <h5 class="card-title">Step Library</h5>

                <input type="text"
                    id="stepSearch"
                    class="form-control mb-2"
                    placeholder="Search steps...">

                <div class="step-library-container">
                    <ul class="list-group step-library" id="stepLibrary">
                    <?php foreach ($allSteps as $s): ?>
                        <li class="list-group-item library-step"
                            data-step-id="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['step_name']) ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <div class="col-md-8">
        <h5>Process Flow Timeline</h5>
        <ul class="list-group timeline" id="timeline">
            <?php foreach ($flowSteps as $id => $step): ?>
            <li class="list-group-item timeline-step" data-id="<?= $id ?>">
                <div class="fw-semibold"><?= htmlspecialchars($step['name']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($step['description']) ?></small>

                <?php if (!empty($step['params'])): ?>
                <table class="table table-sm mt-2 mb-0">
                    <thead>
                        <tr><th>Parameter</th><th>Unit</th><th>Min</th><th>Max</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($step['params'] as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['parameter_name']) ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td><?= $p['min_value'] ?></td>
                            <td><?= $p['max_value'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <?php if ($flow['status'] !== 'active'): ?>
                    <br><button class="btn btn-sm btn-outline-danger removeStepBtn"
                            data-id="<?= $id ?>">
                        Remove Step
                    </button>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 4px;
    background-color: #0d6efd;
}
.timeline-step {
    position: relative;
    margin-bottom: 12px;
}
.timeline-step::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 20px;
    width: 14px;
    height: 14px;
    background-color: #0d6efd;
    border-radius: 50%;
}

.step-library .library-step {
    cursor: grab;
}

.step-library .library-step:active {
    cursor: grabbing;
}

.timeline-step.dragging {
    opacity: 0.5;
}
</style>

<script>
const timeline = document.getElementById('timeline');
const library = document.getElementById('stepLibrary');

/* Timeline sortable */
new Sortable(timeline, {
    animation: 150,
    disabled: <?= $flow['status'] === 'active' ? 'true' : 'false' ?>,
    group: {
        name: 'steps',
        put: true
    },

    onAdd: function (evt) {

        const stepId = evt.item.dataset.stepId;
        const newPosition = evt.newIndex + 1;

        fetch('process_flow_step_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `process_flow_id=<?= $flow_id ?>&process_step_id=${stepId}&position=${newPosition}`
        }).then(() => location.reload());
    },

    onEnd: function () {

        let order = [];

        document.querySelectorAll('#timeline li').forEach((el, idx) => {
            if (el.dataset.id) {
                order.push({
                    id: el.dataset.id,
                    order: idx + 1
                });
            }
        });

        fetch('process_flow_reorder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(order)
        });

    }
});

/* Library draggable */
new Sortable(library, {
    group: {
        name: 'steps',
        pull: 'clone',
        put: false
    },
    sort: false,
    animation: 150
});

if (document.getElementById('addStepBtn')) {
    document.getElementById('addStepBtn').addEventListener('click', () => {
        const stepId = document.getElementById('stepSelect').value;

        fetch('process_flow_step_add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `process_flow_id=<?= $flow_id ?>&process_step_id=${stepId}`
        }).then(() => location.reload());
    });
}

document.querySelectorAll('.removeStepBtn').forEach(btn => {
    btn.addEventListener('click', function () {

        if (!confirm("Remove this step from the flow?")) return;

        const id = this.dataset.id;

        fetch('process_flow_step_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(() => location.reload());
    });
});

const searchBox = document.getElementById('stepSearch');

searchBox.addEventListener('input', function () {

    const filter = this.value.toLowerCase();

    document.querySelectorAll('#stepLibrary .library-step')
        .forEach(step => {

            const text = step.textContent;

            if (text.toLowerCase().includes(filter)) {
                step.style.display = "";
            } else {
                step.style.display = "none";
            }

        });
});
</script>

<?php require "../config/footer.php"; ?>

