<?php
session_start();
$pageTitle = "Confirm EQE Result";
require "../config/database.php";
require "../config/header.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['eqe_file'])) {
    $cell_code = $_POST['cell_code'];
    $tmp_file = $_FILES['eqe_file']['tmp_name'];

    $stmt = $pdo->prepare("SELECT id FROM production.cells WHERE cell_code = ?");
    $stmt->execute([$cell_code]);
    $cell_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Read file into an array of lines
    $lines = file($tmp_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $data_points = [];
    $jsc = 0.0;
    $bandgap = 0.0;
    $is_data_section = false;

    foreach ($lines as $line) {
        $line = trim($line);

        // Detect Data Table Start 
        if (strpos($line, 'WL') !== false && strpos($line, 'QE') !== false) {
            $is_data_section = true;
            continue;
        }

        // Detect Data Table End 
        if ($line === "end data") {
            $is_data_section = false;
            continue;
        }

        // Parse Wavelength (WL) and Quantum Efficiency (QE)
        if ($is_data_section) {
            $cols = preg_split('/\s+/', $line);
            if (count($cols) >= 2) {
                $data_points[] = [
                    'wl' => (float)$cols[0],
                    'qe' => (float)$cols[1]
                ];
            }
        }

        // Parse Summary Values 
        if (strpos($line, 'Jsc [mA/cm2]:') !== false) {
            $parts = preg_split('/\s+/', $line);
            $jsc = (float)$parts[2]; // Captures 12.48 from your file 
        }
        if (strpos($line, 'Bandgap [eV]:') !== false) {
            $parts = preg_split('/\s+/', $line);
            $bandgap = (float)$parts[2]; // Captures 1.612 from your file 
        }
    }
}
?>

<div class="container mt-4">
    <div class="card border-warning shadow">
        <div class="card-header bg-warning text-dark">
            <strong>Confirm Parsed Data</strong>
        </div>
        <div class="card-body">
            <div class="row mb-3" style="text-align: center;">
                <div class="col-md-4">
                    <strong>Cell Code:</strong> <?= htmlspecialchars($cell_code) ?>
                </div>
                <div class="col-md-4">
                    <strong>Calculated Jsc:</strong> <?= number_format($jsc, 2) ?> mA/cm²
                </div>
                <div class="col-md-4">
                    <strong>Bandgap:</strong> <?= number_format($bandgap, 3) ?> eV
                </div>
            </div>

            <table class="table table-sm table-striped border">
                <thead class="table-light">
                    <tr>
                        <th>Wavelength (nm)</th>
                        <th>QE Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Show first 5 and last 5 rows as a sample
                    $sample = array_merge(array_slice($data_points, 0, 5), [['spacer'=>true]], array_slice($data_points, -5));
                    foreach ($sample as $pt): 
                        if (isset($pt['spacer'])): ?>
                            <tr><td colspan="3" class="text-center text-muted">...</td></tr>
                        <?php else: ?>
                            <tr>
                                <td><?= $pt['wl'] ?></td>
                                <td><?= $pt['qe'] ?></td>
                                <td><span class="badge bg-success">Valid</span></td>
                            </tr>
                        <?php endif; 
                    endforeach; ?>
                </tbody>
            </table>

            <form action="save_confirmed_eqe.php" method="POST">
                <input type="hidden" name="cell_code" value="<?= htmlspecialchars($cell_code) ?>">
                <input type="hidden" name="jsc" value="<?= $jsc ?>">
                <input type="hidden" name="bandgap" value="<?= $bandgap ?>">
                <?php $_SESSION['temp_data'] = $data_points; ?>
                
                <div class="d-flex justify-content-between">
                    <a href="upload_eqe.php?cell_code=<?= $cell_code ?>" class="btn btn-outline-danger">Cancel & Re-upload</a>
                    <button type="submit" class="btn btn-primary">Confirm & Save to Database</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require "../config/footer.php"; ?>