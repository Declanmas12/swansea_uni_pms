<?php
session_start();
require "../config/database.php";

// 1. Validation check
if (!isset($_SESSION['iv_preview'])) {
    header("Location: iv_upload.php?error=session_expired");
    exit;
}

$pixel_code = $_GET['id'];
$data = $_SESSION['iv_preview'];
$meta = $data['meta'];

try {
    // Start transaction to ensure data integrity
    $pdo->beginTransaction();

    // 2. Resolve Cell ID
    $stmt = $pdo->prepare("SELECT id, cell_id FROM cell_pixels WHERE pixel_code = ?");
    $stmt->execute([$pixel_code]);
    $pixel = $stmt->fetch();

    if (!$pixel) {
        throw new Exception("Cell code " . htmlspecialchars($pixel_code) . " not found in database.");
    }

    // 3. Organize Sweep Statistics 
    $fwd = ['voc' => 0, 'jsc' => 0, 'ff' => 0, 'eff' => 0, 'date' => date('Y-m-d')];
    $rev = ['voc' => 0, 'jsc' => 0, 'ff' => 0, 'eff' => 0, 'date' => date('Y-m-d')];

    foreach ($data['stats'] as $s) {
        if (stripos($s['direction'], 'Forward') !== false) {
            $fwd = $s;
        } elseif (stripos($s['direction'], 'Reverse') !== false) {
            $rev = $s;
        }
    }

    // Convert date from DD/MM/YYYY (or similar) to YYYY-MM-DD
    $rawDate = $fwd['date']; 

    // Use try-catch to ensure the date format is valid before passing to SQL
    try {
        $dateObj = DateTime::createFromFormat('d/m/Y', $rawDate);
        
        // If that fails, try YYYY-MM-DD just in case it's already formatted
        if (!$dateObj) {
            $dateObj = new DateTime($rawDate);
        }
        
        $formattedDate = $dateObj->format('Y-m-d');
    } catch (Exception $e) {
        $formattedDate = date('Y-m-d'); // Fallback to today if date is unreadable
    }

    // 4. Calculate Hysteresis Index (HI)
    // Formula: (PCE_rev - PCE_fwd) / PCE_rev 
    $hi = ($rev['eff'] != 0) ? ($rev['eff'] - $fwd['eff']) / $rev['eff'] : 0;

    // 5. Insert Main Measurement Record
    $sql = "INSERT INTO pixel_iv_measurements (
                pixel_id, test_date, operator, 
                fwd_voc, fwd_jsc, fwd_ff, fwd_eff, 
                rev_voc, rev_jsc, rev_ff, rev_eff, 
                hysteresis_index
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt_m = $pdo->prepare($sql);
    $stmt_m->execute([
        $pixel['id'], 
        $formattedDate, 
        $meta['operator'] ?? 'Unknown',
        $fwd['voc'], $fwd['jsc'], $fwd['ff'], $fwd['eff'],
        $rev['voc'], $rev['jsc'], $rev['ff'], $rev['eff'], 
        $hi
    ]);
    
    $measurement_id = $pdo->lastInsertId();

    // 6. Bulk Insert Raw Data Points 
    $stmt_pt = $pdo->prepare("INSERT INTO pixel_iv_data_points (measurement_id, voltage_v, current_density_ma_cm2, direction) VALUES (?, ?, ?, ?)");

    // Insert Forward Points
    foreach ($data['points']['forward'] as $p) {
        $stmt_pt->execute([$measurement_id, $p['v'], $p['j'], 'forward']);
    }

    // Insert Reverse Points
    foreach ($data['points']['reverse'] as $p) {
        $stmt_pt->execute([$measurement_id, $p['v'], $p['j'], 'reverse']);
    }

    // Commit changes
    $pdo->commit();

    // Clean up and redirect
    unset($_SESSION['iv_preview']);
    header("Location: view_pixels.php?id=".$pixel['cell_id']."&success=1");
    exit;

} catch (Exception $e) {
    // Rollback database if anything fails
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
}