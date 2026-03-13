<?php
$pageTitle = "Upload EQE Result";
require "../config/database.php";
require "../config/header.php";

$cell_code = $_GET['cell_code'] ?? '' ;
?>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Upload EQE Test Results</h5>
        </div>
        <div class="card-body">
            <form action="process_eqe.php" method="POST" enctype="multipart/form-data">
                
                <div class="mb-3">
                    <label for="cell_code" class="form-label">Cell ID</label>
                    <input class="form-control" id="cell_code" name="cell_code" value=<?= htmlspecialchars($cell_code) ?> readonly>
                </div>

                <div class="mb-4">
                    <label for="eqe_file" class="form-label">Select EQE Output File (.txt)</label>
                    <input class="form-control" type="file" id="eqe_file" name="eqe_file" accept=".txt" required>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success">Save to Database</button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require "../config/footer.php"; ?>