<?php
$pageTitle = "Module Build";
require "../config/database.php";
require "../config/header.php";

$moduleId = (int)($_GET['id'] ?? 0);

// Fetch module
$stmt = $pdo->prepare("
    SELECT m.*, p.product_code
    FROM modules m
    JOIN products p ON p.id = m.product_id
    WHERE m.id = ?
");
$stmt->execute([$moduleId]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    die("Module not found");
}

// Count already consumed cells
$usedCount = $pdo->prepare("SELECT COUNT(*) FROM module_cells WHERE module_id = ?");
$usedCount->execute([$moduleId]);
$usedCount = (int)$usedCount->fetchColumn();

$remaining = $module['cells_required'] - $usedCount;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consume_cells'])) {
    $pdo->beginTransaction();
    try {
        foreach ($_POST['cell_code'] as $cellCode) {

            // Lock cell row
            $cellStmt = $pdo->prepare("
                SELECT * FROM cells
                WHERE cell_code = ?
                FOR UPDATE
            ");
            $cellStmt->execute([$cellCode]);
            $cell = $cellStmt->fetch(PDO::FETCH_ASSOC);

            if (!$cell || $cell['inventory_status'] !== 'AVAILABLE') {
                throw new Exception("Cell $cellCode is not available");
            }

            // Insert into module_cells
            $pdo->prepare("
                INSERT INTO module_cells (module_id, cell_code)
                VALUES (?, ?)
            ")->execute([$moduleId, $cellCode]);

            // Update cell
            $pdo->prepare("
                UPDATE cells
                SET inventory_status = 'CONSUMED',
                    module_id = ?
                WHERE cell_code = ?
            ")->execute([$moduleId, $cellCode]);
        }

        // Recount
        $count = $pdo->prepare("SELECT COUNT(*) FROM module_cells WHERE module_id = ?");
        $count->execute([$moduleId]);
        $count = (int)$count->fetchColumn();

        if ($count >= $module['cells_required']) {
            $pdo->prepare("
                UPDATE modules
                SET build_status = 'COMPLETED',
                    built_at = NOW()
                WHERE id = ?
            ")->execute([$moduleId]);
        }

        $pdo->commit();
        header("Location: module_view.php?id=$moduleId");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='alert alert-danger'>{$e->getMessage()}</div>";
    }
}

// Fetch available cells
$availableCells = $pdo->query("
    SELECT c.cell_code, b.batch_code
    FROM cells c
    JOIN batches b ON b.id = c.batch_id
    WHERE c.inventory_status = 'AVAILABLE'
    ORDER BY c.cell_code
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h4>Module Build</h4>

<div class="card mb-3">
  <div class="card-body">
    <strong>Module:</strong> <?= htmlspecialchars($module['module_code']) ?><br>
    <strong>Product:</strong> <?= htmlspecialchars($module['product_code']) ?><br>
    <strong>Status:</strong> <?= $module['build_status'] ?><br>
    <strong>Cells required:</strong> <?= $module['cells_required'] ?><br>
    <strong>Remaining:</strong> <?= max(0, $remaining) ?>
  </div>
</div>

<?php if ($remaining > 0): ?>
<div class="row mb-3">
  <div class="col-md-4">
    <input type="text" id="cellSearch" class="form-control"
           placeholder="Search Cell Code">
  </div>
  <div class="col-md-4">
    <input type="text" id="batchSearch" class="form-control"
           placeholder="Search Batch ID">
  </div>
</div>
<form method="post">
<table class="table table-sm" style="text-align:center;">
  <thead>
    <tr>
      <th>Select</th>
      <th>Cell</th>
      <th>Batch</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($availableCells as $c): ?>
    <tr
    data-cell="<?= strtolower($c['cell_code']) ?>"
    data-batch="<?= strtolower($c['batch_code']) ?>"
    >
    <td>
        <input type="checkbox"
            name="cell_code[]"
            value="<?= $c['cell_code'] ?>">
    </td>
    <td><?= htmlspecialchars($c['cell_code']) ?></td>
    <td><?= htmlspecialchars($c['batch_code']) ?></td>
    </tr>
    <?php endforeach; ?>
</tbody>
</table>
<div style="text-align:center;">
    <button class="btn btn-primary" name="consume_cells">
        Consume Selected Cells
    </button>
</div>
</form>
<br>
<?php else: ?>
    <div class="alert alert-success">
        Module build complete.
    </div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function () {

  function applyFilters() {
    const cellTerm  = $('#cellSearch').val().toLowerCase();
    const batchTerm = $('#batchSearch').val().toLowerCase();

    $('table tbody tr').each(function () {
      const cell  = $(this).data('cell');
      const batch = $(this).data('batch');

      const show =
        (!cellTerm  || cell.includes(cellTerm)) &&
        (!batchTerm || batch.includes(batchTerm));

      $(this).toggle(show);
    });
  }

  $('#cellSearch, #batchSearch').on('keyup change', applyFilters);

});
</script>

<?php require "../config/footer.php"; ?>