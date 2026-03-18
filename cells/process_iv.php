<?php
session_start();
require "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['iv_file'])) {
    $fileContent = file_get_contents($_FILES['iv_file']['tmp_name']);
    $lines = explode("\n", $fileContent);

    $metadata = [];
    $results_table = [];
    $raw_data = ['forward' => [], 'reverse' => []];
    $section = '';
    $headerMap = [];

    foreach ($lines as $line) {
        $line = trim($line, "\r\n");
        if (empty($line)) continue;

        // 1. Meta Extraction
        if (strpos($line, 'Cell ID :') !== false) {
            $metadata['cell_code'] = trim(explode(':', $line)[1]);
        }
        
        // Capture Operator (User)
        if (strpos($line, "User\t") !== false) {
            $section = 'capture_operator';
            continue;
        }
        if ($section == 'capture_operator') {
            $metadata['operator'] = trim(explode("\t", $line)[0]);
            $section = '';
        }

        // 2. Map Columns via Partial Header Matching
        if (strpos($line, 'Measurment') !== false) {
            $headers = explode("\t", $line);
            foreach ($headers as $index => $name) {
                $clean = trim($name);
                if (strpos($clean, 'Direction') !== false) $headerMap['Direction'] = $index;
                if (strpos($clean, 'Voc (V)/Stabilised') !== false)       $headerMap['Voc'] = $index;
                if (strpos($clean, 'Jsc') !== false)       $headerMap['Jsc'] = $index;
                if (strpos($clean, 'Fill factor') !== false) $headerMap['FF'] = $index;
                if (strpos($clean, 'Efficiency') !== false)  $headerMap['Eff'] = $index;
                if (strpos($clean, 'Start Date') !== false)  $headerMap['Date'] = $index;
            }
            $section = 'results';
            continue;
        }

        // 3. Parse Results
        if ($section == 'results' && is_numeric(explode("\t", $line)[0])) {
            $p = explode("\t", $line);
            $results_table[] = [
                'direction' => trim($p[$headerMap['Direction']]),
                'voc'       => (float)($p[$headerMap['Voc']] ?? 0),
                'jsc'       => (float)($p[$headerMap['Jsc']] ?? 0),
                'ff'        => (float)($p[$headerMap['FF']] ?? 0),
                'eff'       => (float)($p[$headerMap['Eff']] ?? 0),
                'date'      => trim($p[$headerMap['Date']])
            ];
        }

        // 4. Raw Data
        if (strpos($line, 'VSource Forward') !== false) { $section = 'raw'; continue; }
        if ($section == 'raw') {
            $p = explode("\t", $line);
            if (isset($p[0], $p[1]) && is_numeric($p[0])) {
                $raw_data['forward'][] = ['v' => (float)$p[0], 'j' => (float)$p[1]];
            }
            if (isset($p[4], $p[5]) && is_numeric($p[4])) {
                $raw_data['reverse'][] = ['v' => (float)$p[4], 'j' => (float)$p[5]];
            }
        }
    }

    $_SESSION['iv_preview'] = ['meta' => $metadata, 'stats' => $results_table, 'points' => $raw_data];
    header("Location: preview_iv.php");
    exit;
}