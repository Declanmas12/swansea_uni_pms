<?php
$pageTitle = "Reserve Cells";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

$user = $_SESSION['username'] ?? 'SYSTEM';

/* ------------------------
   Available Cells
------------------------- */
$stmt = $pdo->query("
    SELECT cell_code, inventory_location
    FROM cells
    WHERE inventory_status = 'AVAILABLE'
    ORDER BY inventory_added_at
");
$cells = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ------------------------
   POST: Reserve
------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selected = $_POST['cells'] ?? [];
    $ref = trim($_POST['reference']);

    if (!$selected || $ref === '') {
        echo "<div class='alert alert-danger'>Select cells and enter reference</div>";
    } else {
        $pdo->beginTransaction();
        try {
            foreach ($selected as $code) {

                // State check
                $state = $pdo->prepare("SELECT inventory_status FROM cells WHERE cell_code=?");
                $state->execute([$code]);
                if ($state->fetchColumn() !== 'AVAILABLE') {
                    throw new Exception("Cell $code not available");
                }

                // Transition
                $pdo->prepare("
                    UPDATE cells SET inventory_status='RESERVED'
                    WHERE cell_code=?
                ")->execute([$code]);

                // Audit
                $pdo->prepare("
                    INSERT INTO cell_inventory_history
                    (cell_code, from_state, to_state, reference, changed_by)
                    VALUES (?, 'AVAILABLE', 'RESERVED', ?, ?)
                ")->execute([$code, $ref, $user]);
            }

            $pdo->commit();
            header("Location: cell_inventory.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
        }
    }
}
?>

<h3>Reserve Cells</h3>

<form method="post">
    <div class="mb-3">
        <label class="form-label">Reference / Purpose</label>
        <input class="form-control" name="reference" required>
    </div>

    <table class="table table-sm">
        <tr><th></th><th>Cell</th><th>Location</th></tr>
        <?php foreach ($cells as $c): ?>
        <tr>
            <td><input type="checkbox" name="cells[]" value="<?= $c['cell_code'] ?>"></td>
            <td><?= htmlspecialchars($c['cell_code']) ?></td>
            <td><?= htmlspecialchars($c['inventory_location']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <button class="btn btn-primary">Reserve Selected</button>
</form>

<?php require "../config/footer.php"; ?>
