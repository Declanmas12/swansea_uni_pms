<?php
// equipment/index.php
$pageTitle = "Cell Dashboard";
require "../config/header.php";
?>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin: 50px auto;
            max-width: 800px;
            gap: 30px;
        }

        .card {
            background-color: white;
            width: 200px;
            height: 150px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .card i {
            font-size: 48px;
            color: #007acc;
            margin-bottom: 15px;
        }

        .card span {
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="dashboard">
    <a class="card" href="cell_inventory.php?search=&state=AVAILABLE">
        <i class="fas fa-boxes-stacked"></i>
        <span>Cell Inventory</span>
    </a>
    <a class="card" href="eqe_test_list.php">
        <i class="fas fa-chart-area"></i>
        <span>EQE Test Results</span>
    </a>
    <a class="card" href="iv_test_list.php">
        <i class="fas fa-chart-line"></i>
        <span>I-V Test Results</span>
    </a>
</div>

<?php require "../config/footer.php"; ?>