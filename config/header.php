<?php
if (!isset($pageTitle)) {
    $pageTitle = "Production Management System";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>

<nav class="navbar navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <a href="javascript:history.back()" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <a href="/homepage.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house"></i>
            </a>
        </div>

        <span class="navbar-brand mx-auto fw-semibold">
            <?= htmlspecialchars($pageTitle) ?>
        </span>

        <div></div>
    </div>
</nav>

<div class="container mt-4">