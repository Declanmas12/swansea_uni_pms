<?php
$pageTitle = "Liquid Nitrogen Dewar Monitoring";
require "../config/database.php";
require "../config/header.php";

$dewar_id = $_GET['dewar_id'];

/* -------- HANDLE INPUT -------- */

if(isset($_POST['action'])){

$action=$_POST['action'];
$volume=floatval($_POST['volume']);
$operator=$_POST['operator'];

if($action=="Withdraw"){

$stmt = $pdo->prepare("
        UPDATE dewars
        SET current_volume_litres =
        GREATEST(0,current_volume_litres-?)
        WHERE id=?
    ");
$stmt->execute([$volume, $dewar_id]);
}

if($action=="Refill"){

$stmt = $pdo->prepare("
        UPDATE dewars
        SET current_volume_litres =
        LEAST(max_volume_litres,current_volume_litres+?)
        WHERE id=?
    ");
$stmt->execute([$volume, $dewar_id]);

}

$stmt = $pdo->prepare("
    INSERT INTO ln2_log (dewar_id,action,volume_litres,operator_name)
    VALUES (?,?,?,?)
    ");
$stmt->execute([$dewar_id, $action, $volume, $operator]);

}

/* -------- GET DATA -------- */

$stmt = $pdo->prepare("SELECT * FROM dewars WHERE id = ?");
$stmt->execute([$dewar_id]);
$dewar = $stmt->fetch(PDO::FETCH_ASSOC);

$current=$dewar['current_volume_litres'];
$max=$dewar['max_volume_litres'];

$percent=($current/$max)*100;

/* -------- DAILY USAGE -------- */

$usage = $pdo->query("
    SELECT DATE(log_time) d,
    SUM(volume_litres) v
    FROM ln2_log
    WHERE action='withdraw' AND
    id=$dewar_id
    GROUP BY DATE(log_time)
    ORDER BY d
");

$dates=[];
$values=[];

while($row=$usage->fetch()){

$dates[]=$row['d'];
$values[]=$row['v'];

}

?>

<title>LN₂ Dewar Monitoring</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    h1{
        margin-bottom:30px;
    }

    .container{
        display:flex;
        gap:60px;
        align-items:flex-start;
        text-align: center;
    }

    /* -------- Dewar -------- */

    .dewar{
        width:180px;
        height:420px;
        border:6px solid #2c3e50;
        border-radius:90px 90px 40px 40px;
        background:#dfe6ee;
        position:relative;
        overflow:hidden;
        box-shadow:0 4px 10px rgba(0,0,0,0.2);
    }

    .ln2{
        position:absolute;
        bottom:0;
        width:100%;
        background: linear-gradient(
        to top,
        #2a9df4,
        #5cc4ff,
        #9be2ff
        );
        transition:height 1s ease;
    }

    /* liquid animation */

    .ln2::before{
        content:'';
        position:absolute;
        top:-10px;
        left:0;
        width:200%;
        height:20px;

        background: radial-gradient(circle at 10px -5px,
        rgba(255, 255, 255, 0.6) 10px,
        transparent 11px) repeat-x;

        background-size:40px 20px;

        animation:wave 3s linear infinite;
    }

    @keyframes wave{
        0%{
            transform:translateX(0);
        }
        100%{
            transform:translateX(-40px);
        }
        }

    .readout{
        margin-top:20px;
        font-size:28px;
        font-weight:bold;
    }

    .percent{
        font-size:20px;
        color:#444;
    }

    .card{
        background:white;
        padding:25px;
        border-radius:8px;
        box-shadow:0 3px 8px rgba(0,0,0,0.1);
    }

    .warning{
        background:#ffdddd;
        color:#a40000;
        padding:10px;
        margin-top:15px;
        border-radius:5px;
        font-weight:bold;
    }

    form input{
        padding:8px;
        margin:5px 0;
        width:160px;
    }

    button{
        padding:10px 16px;
        margin-top:10px;
        cursor:pointer;
    }

    table{
        border-collapse:collapse;
        margin-top:30px;
        width:100%;
    }

    th,td{
        border:1px solid #ccc;
        padding:8px;
    }

    th{
        background:#f0f0f0;
    }
</style>

<div class="container">
    <div class="card">
        <h4><?php echo $dewar['name'] . ' - ' . $dewar['location']; ?></h4>
        <center>
            <div class="dewar">
                <div class="ln2" style="height:<?php echo $percent;?>%"></div>
            </div>
        </center>
        <div class="readout">
            <?php echo round($current,1);?> L
        </div>
        <div class="percent">
            <?php echo round($percent,1);?> %
        </div>
        <?php
        if($percent<20){
            echo "<div class='warning'>LOW LN₂ LEVEL</div>";
        }
        ?>
    </div>
    <div class="card">
        <h4>Log Dewar Activity</h4>
        <form method="POST">
            Operator<br>
            <input type="text" name="operator" required>
            <br>
            Volume (L)<br>
            <input type="number" step="0.1" name="volume" required>
            <br>
            <button name="action" class= "btn btn-primary" value="withdraw">Withdraw LN₂</button>
            <br>
            <button name="action" class= "btn btn-primary" value="refill">Refill Dewar</button>
        </form>
    </div>
</div>
<div class="card" style="width:100%">
    <h4>Daily LN₂ Usage</h4>
    <canvas id="usageChart"></canvas>
    <hr>
    <h4>Last 24 Hr Usage Log</h4>
    <table>
        <tr>
            <th>Time</th>
            <th>Action</th>
            <th>Volume</th>
            <th>Operator</th>
        </tr>
        <?php
            $log=$pdo->query("SELECT * FROM ln2_log WHERE log_time >= NOW() - INTERVAL 1 DAY ORDER BY log_time DESC");
            foreach ($log as $row) {
                echo "<tr>
                    <td>{$row['log_time']}</td>
                    <td>{$row['action']}</td>
                    <td>{$row['volume_litres']} L</td>
                    <td>{$row['operator_name']}</td>
                </tr>";
            }
        ?>
    </table>
</div>

<script>
    const ctx=document.getElementById("usageChart");
    new Chart(ctx,{
        type:'bar',
        data:{
            labels:<?php echo json_encode($dates);?>,
            datasets:[{
                label:"LN2 Used (L)",
                data:<?php echo json_encode($values);?>
            }]
        }
    });
</script>
<?php require "../config/footer.php"; ?>