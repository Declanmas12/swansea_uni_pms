<?php
$pageTitle = "Process Flow Editor";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$id = $_GET['id'] ?? null;

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE process_flows
            SET flow_name = ?, version = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['flow_name'],
            $_POST['version'],
            $_POST['description'],
            $_POST['status'],
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO process_flows (flow_name, version, description, status, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['flow_name'],
            $_POST['version'],
            $_POST['description'],
            $_POST['status'],
            $_SESSION['user_id']
        ]);

        $id = $pdo->lastInsertId();
    }

    header("Location: process_flow_manager.php?id=" . $id);
    exit;
}

/* ---------- Load ---------- */
$flow = [
    'flow_name'   => '',
    'version'     => '1.0',
    'description' => '',
    'status'      => 'draft'
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM process_flows WHERE id = ?");
    $stmt->execute([$id]);
    $flow = $stmt->fetch();

    $pageTitle = "Edit Process Flow";
}
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title mb-3">Process Flow Definition</h5>

        <form method="post">

            <div class="mb-3">
                <label class="form-label">Flow Name</label>
                <input
                    name="flow_name"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars($flow['flow_name']) ?>">
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Version</label>
                    <input
                        name="version"
                        class="form-control"
                        required
                        value="<?= htmlspecialchars($flow['version']) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (['draft','active','obsolete'] as $s): ?>
                        <option value="<?= $s ?>" <?= $flow['status'] === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea
                    name="description"
                    rows="3"
                    class="form-control"><?= htmlspecialchars($flow['description']) ?></textarea>
            </div>

            <div class="d-flex justify-content-between">
                <a href="process_flows.php" class="btn btn-outline-secondary">
                    Cancel
                </a>

                <div>
                    <button class="btn btn-success">
                        Save & Build Flow
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<?php require "../config/footer.php"; ?>

