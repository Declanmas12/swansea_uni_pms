<?php
$pageTitle = "Edit Step Parameter";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Parameter ID is required.");
}

/* ---------- Save ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE process_step_parameters
        SET parameter_name = ?, unit = ?, min_value = ?, max_value = ?, scope=?, required=?
        WHERE id = ?
    ");

    $min_value = $_POST['min_value'] === '' ? null : $_POST['min_value'];
    $max_value = $_POST['max_value'] === '' ? null : $_POST['max_value'];
    $scope = $_POST['scope'];
    $required = $_POST['required'] ?? 0;

    try {
        $stmt->execute([
            $_POST['parameter_name'],
            $_POST['unit'],
            $min_value,
            $max_value,
            $scope,
            $required,
            $id
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            die("Error: Parameter with this name already exists for this step.");
        } else {
            die("Database error: " . $e->getMessage());
        }
    }

    header("Location: process_step_parameters.php?step_id=" . $_POST['process_step_id']);
    exit;
}

/* ---------- Load ---------- */
$stmt = $pdo->prepare("SELECT * FROM process_step_parameters WHERE id = ?");
$stmt->execute([$id]);
$param = $stmt->fetch();

if (!$param) {
    die("Parameter not found.");
}

$step_id = $param['process_step_id'];
if ($param['required'] == 1) {
        $checked = 'checked';
    } else {
        $checked = '';
    };

?>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title mb-3">Edit Parameter</h5>

        <form method="post">
            <input type="hidden" name="process_step_id" value="<?= $step_id ?>">

            <div class="mb-3">
                <label class="form-label">Parameter Name</label>
                <input name="parameter_name" class="form-control" required value="<?= htmlspecialchars($param['parameter_name']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Parameter Scope</label>
                <select name="scope" class="form-select" required>
                    <option value="BATCH" <?= ($param['scope'] ?? '') === 'BATCH' ? 'selected' : '' ?>>
                        Batch-level (applies to entire batch)
                    </option>
                    <option value="CELL" <?= ($param['scope'] ?? 'CELL') === 'CELL' ? 'selected' : '' ?>>
                        Cell-level (measured per cell)
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Unit</label>
                <input name="unit" class="form-control" value="<?= htmlspecialchars($param['unit']) ?>">
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Min Value</label>
                    <input name="min_value" class="form-control" value="<?= $param['min_value'] ?>">
                </div>
                <div class="col">
                    <label class="form-label">Max Value</label>
                    <input name="max_value" class="form-control" value="<?= $param['max_value'] ?>">
                </div><br><br>
                <div class="col">
                    <label class="form-label" for="required">Required?</label><br>
                    <input class="form-check-input" type="checkbox" id="required" name="required" <?=$checked?> value="1">
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="process_step_parameters.php?step_id=<?= $step_id ?>" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require "../config/footer.php"; ?>