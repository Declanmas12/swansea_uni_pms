<?php
  require "../config/database.php";

   if ($_SERVER['REQUEST_METHOD']=='POST') 
   {
    $date = date('Y-m-d H:i:s');
    $user=$_POST["user_name"];
    $intensity=$_POST["peak_intensity"];
    $peak_pos=$_POST["peak_pos"];
    $wavelength=$_POST["laser_wave"];
    $beam_steer=$_POST["beam_steer"];
   }
   $pdo->prepare("INSERT INTO raman_log(date_time, user, intensity, peak_pos, wavelength, beam_stear) VALUES(?,?,?,?,?,?)")
   ->execute([$date,$user,$intensity,$peak_pos, $wavelength, $beam_steer]);
 
   if ($pdo->query($insert_query) === TRUE) {
           echo 'New record created successfully';  
           }
   else {
         echo 'Error: ' . $insert_query . '<br>';  }

?>