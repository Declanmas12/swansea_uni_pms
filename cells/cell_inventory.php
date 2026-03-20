<?php
$pageTitle = "Cell Inventory";
require "../config/database.php";
require "../config/header.php";
require "../config/auth.php";

/* ------------------------
   Filters
------------------------- */
$state = $_GET['state'] ?? '';
$search = trim($_GET['search'] ?? '');
$limit = trim($_GET['limit'] ?? '100');

$params = [];
$where = [];

if ($state !== '') {
    $where[] = "c.inventory_status = ?";
    $params[] = $state;
}

if ($search !== '') {
    $where[] = "(c.cell_code LIKE ? OR b.batch_code LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ------------------------
   Inventory Query
------------------------- */
$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.cell_code,
        c.inventory_status,
        c.inventory_location,
        c.inventory_added_at,
        b.batch_code,
        p.product_code,
        eqe.cell_id,
        eqe.id as eqe_id
    FROM cells c
    JOIN batches b ON b.id = c.batch_id
    JOIN products p ON p.id = b.product_id
    LEFT JOIN eqe_measurements eqe ON eqe.cell_id = c.id
    $whereSql
    ORDER BY c.inventory_added_at DESC, c.cell_code
    LIMIT $limit
");
$stmt->execute($params);
$cells = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.badge-IN_PROCESS { background:#6c757d; }
.badge-AVAILABLE { background:#198754; }
.badge-RESERVED { background:#ffc107; color:#000; }
.badge-ISSUED { background:#0d6efd; }
.badge-CONSUMED { background:#adb5bd; }
.badge-SCRAPPED { background:#dc3545; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Cell Inventory</h3>
    <a href="cell_checkout.php" class="btn btn-primary btn-sm">
        Reserve Cells
    </a>
</div>

<!-- =======================
     Filters
======================= -->
<form class="row g-2 mb-3 align-items-end bg-light p-3 border rounded mx-0 justify-content-between">
    <div class="col-md-4">
        <input class="form-control" name="search" placeholder="Search cell, batch, product"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <label for="limit" class="me-2 text-nowrap">No. of Results</label>
        <input class="form-control" id="limit" name="limit" placeholder="100"
               value="<?= htmlspecialchars($limit) ?>">
    </div>
    <div class="col-md-3">
        <select class="form-select" name="state">
            <option value="">All States</option>
            <?php foreach (['IN_PROCESS','AVAILABLE','RESERVED','ISSUED','CONSUMED','SCRAPPED'] as $s): ?>
                <option value="<?= $s ?>" <?= $state===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>

<!-- =======================
     Result Count
======================= -->
<div style="text-align: center;">
    <p>Showing: <?= count($cells) ?> Result(s)</p>
</div>

<!-- =======================
     Inventory Table
======================= -->
<table class="table table-sm table-hover align-middle" style="text-align:center;">
    <thead class="table-light">
        <tr>
            <th>Cell</th>
            <th>Product</th>
            <th>Batch</th>
            <th>I-V</th>
            <th>EQE</th>
            <th>No. of Pixels</th>
            <th>State</th>
            <th>Location</th>
            <th>Added</th>
            <th>Action(s)</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cells as $c):

            $stmt = $pdo->prepare("SELECT count(pixel_code) as pixels FROM production.cell_pixels WHERE cell_id = ?");
            $stmt->execute([$c['id']]);
            $pixels = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($pixels['pixels'] == 0) : {
                $pixels['pixels'] = '';
            } endif;

            $stmt = $pdo->prepare("SELECT id FROM production.iv_measurements WHERE cell_id = ? ORDER BY test_date DESC LIMIT 1");
            $stmt->execute([$c['id']]);
            $IV = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ?>
        <tr VALIGN="MIDDLE">
            <td>
                <a style="text-decoration: none;" href="cell_view.php?cell=<?= urlencode($c['cell_code']) ?>">
                    <?= htmlspecialchars($c['cell_code']) ?>
                </a>
            </td>
            <td><?= htmlspecialchars($c['product_code']) ?></td>
            <td><?= htmlspecialchars($c['batch_code']) ?></td>


            <?php if (!isset($IV['id'])) :?>
                <td><a class="text-danger" style="text-decoration:none;" href="iv_upload.php">✖</a></td>
            <?php else:  ?>
                <td><a class="text-success" style="text-decoration:none;" href="view_iv.php?id=<?= $IV['id'] ?> ">✔</a></td>
            <?php endif ?>
            

            <?php if($c['cell_id'] == null) :?>
                <td><a class="text-danger" style="text-decoration:none;" href="upload_eqe.php?cell_code=<?= $c['cell_code'] ?>">✖</a></td>
            <?php else:  ?>
                <td><a class="text-success" style="text-decoration:none;" href="view_eqe.php?id=<?= $c['eqe_id'] ?> ">✔</a></td>
            <?php endif ?>
            <?php if ($pixels['pixels'] != null) : ?>
                <td>
                    <a style="text-decoration: none;" href="view_pixels.php?id=<?= $c['id'] ?>"><?= $pixels['pixels'] ?></a>
                </td>
            <?php else: ?>
                <td>
                    <a style="text-decoration: none;" href="add_pixels.php?id=<?= $c['id'] ?>">--</a>
                </td>
            <?php endif ?>
            <td>
                <span class="badge badge-<?= $c['inventory_status'] ?>">
                    <?= $c['inventory_status'] ?>
                </span>
            </td>
            <td><?= htmlspecialchars($c['inventory_location'] ?? '-') ?></td>
            <td><?= $c['inventory_added_at'] ?></td>
            <?php if($c['inventory_status'] == 'AVAILABLE' || $c['inventory_status'] == 'IN_PROCESS'): ?>
            <td>
                <button type="button"
                    class="btn btn-sm btn-outline-primary rename-btn"
                    data-cell="<?= htmlspecialchars($c['cell_code']) ?>">
                    Rename
                </button>
            </td>
            <?php else: ?>
            <td>
            </td>
            <?php endif ?>
            <td>
                <?php if ($c['inventory_status'] === 'AVAILABLE'): ?>
                    <span class="text-success">✔ Ready</span>
                <?php elseif ($c['inventory_status'] === 'RESERVED'): ?>
                    <span class="text-warning">Allocated</span>
                <?php elseif ($c['inventory_status'] === 'SCRAPPED'): ?>
                    <span class="text-danger">✖ Scrapped</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="renameModal"
     style="display:none;
            position:fixed;
            top:30%;
            left:50%;
            transform:translate(-50%, -30%);
            background:white;
            padding:20px;
            border:1px solid #ccc;
            box-shadow:0px 4px 10px rgba(0,0,0,0.2);
            z-index:1000;">
    <h4>Rename Cell</h4>

    <p><strong>Current:</strong> <span id="currentCell"></span></p>

    <input type="text" id="newCellCode"
           class="form-control"
           placeholder="Enter new cell code">

    <div style="margin-top:10px; text-align:right;">
        <button id="cancelRename" class="btn btn-secondary btn-sm">Cancel</button>
        <button id="submitRename" class="btn btn-primary btn-sm">Save</button>
    </div>

    <p id="renameError" style="color:red; margin-top:10px; display:none;"></p>
</div>

<div id="modalBackdrop"
     style="display:none;
            position:fixed;
            top:0; left:0;
            width:100%; height:100%;
            background:rgba(0,0,0,0.3);
            z-index:999;">
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(function(){

  let selectedCell = null;

  $('.rename-btn').on('click', function(){
    selectedCell = $(this).data('cell');
    $('#currentCell').text(selectedCell);
    $('#newCellCode').val('');
    $('#renameError').hide();
    $('#renameModal, #modalBackdrop').show();
  });

  $('#cancelRename, #modalBackdrop').on('click', function(){
    $('#renameModal, #modalBackdrop').hide();
  });

  $('#submitRename').on('click', function(){

    const newCode = $('#newCellCode').val().trim();

    if (!newCode) {
      $('#renameError').text('New cell code is required.').show();
      return;
    }

    $.post('cell_rename.php', {
      old_code: selectedCell,
      new_code: newCode
    }, function(response){

      if (response.status === 'ok') {
        location.reload();
      } else {
        $('#renameError').text(response.message).show();
      }

    }, 'json');
  });

});
</script>

<?php require "../config/footer.php"; ?>