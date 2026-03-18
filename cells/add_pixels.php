<?php
$pageTitle = "Add Pixels";
require "../config/database.php";
require "../config/header.php";

$cell_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT cell_code FROM production.cells WHERE id=?");
$stmt->execute([$cell_id]);
$result = $stmt->fetch();
$cell = $result['cell_code'];

?>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Create Cell Pixels</h5>
        </div>
        <div class="card-body">
            <form action="create_pixels.php" method="POST" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label for="cell_code" class="form-label">Cell ID</label>
                    <input class="form-control" id="cell_code" name="cell_code" value=<?= htmlspecialchars($cell) ?> readonly>
                    <input type="hidden" id="cellid" name="cellid" value="<?= $cell_id ?>">
                </div>

                <div class="mb-4">
                    <label for="pixels" class="form-label">Number of Pixels</label>
                    <input class="form-control" type="number" id="pixels" name="pixels" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success">Create Pixels</button>
                </div>

            </form>
        </div>
    </div>
</div>



<?php require "../config/footer.php"; ?>