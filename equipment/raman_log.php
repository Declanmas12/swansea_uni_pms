<?php
// Webpage for saving and loading Raman calibration results
// Declan Hughes 10/02/2025

$pageTitle = "SPECIFIC Labs A005 - Raman Calibration Log";
require "../config/database.php";
require "../config/header.php";

// Query to fetch data
$stmt = $pdo->prepare("SELECT date_time, user, intensity, peak_pos, wavelength, beam_steer FROM production.raman_log ORDER BY date_time DESC limit 10");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Raman Log</title>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
            th { background-color: #f4f4f4; }
            h2 { text-align: center; }
            h3 { text-align: center; }

            /* Style the form - display items horizontally */
            .form-inline {
            display: flex;
            flex-flow: row wrap;
            flex-direction: column;
            align-items: center;
            }

            /* Add some margins for each label */
            .form-inline label {
            margin: 5px 5px 5px 0;
            }

            /* Style the input fields */
            .form-inline input {
            vertical-align: middle;
            margin: 5px 20px 5px 0;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            text-align: center;
            }

            /* Style the submit button */
            .form-inline button {
            padding: 10px 20px;
            background-color: dodgerblue;
            border: 1px solid #ddd;
            color: white;
            float: right;
            }

            .form-inline button:hover {
            background-color: royalblue;
            }

            /* Add responsiveness - display the form controls vertically instead of horizontally on screens that are less than 800px wide */
            @media (max-width: 800px) {
            .form-inline input {
                margin: 10px 0;
            }

            iframe {
            display: none;
            }
        }

        </style>
    </head>
    <body>

    <h2>SPECIFIC Labs A005 - Raman Calibration Log</h2>
    <hr>
    <h3>Input Raman Calibration Data</h3>
        <form class="form-inline" method="POST" action="insert_query.php" target="content">
            <div>
                <div>
                    <label for="user_name">User Name:</label>
                    <input type="text" id="user_name" placeholder="Enter Name of User" name="user_name">
                    &nbsp;
                    <label for="peak_intensity">Peak Intensity:</label>
                    <input type="text" id="peak_intensity" placeholder="Enter Intensity of The Peak" name="peak_intensity">
                    &nbsp;
                    <label for="peak_pos">Peak Position (cm-1):</label>
                    <input type="text" id="peak_pos" placeholder="Enter Peak Position" name="peak_pos">
                    &nbsp;
                    <label for="laser_wave">Laser Wavelength (nm):</label>
                    <input type="text" id="laser_wave" placeholder="Enter Laser Wavelength" name="laser_wave">
                </div>
                <div style="text-align: center;">
                    <p>Beam Steered? &nbsp;&nbsp;
                        <input type="radio" name="beam_stear"
                                    value = "1">Yes
                                    &nbsp;&nbsp;
                        <input type="radio" name="beam_stear"
                                    value="0">No
                    </p>
                </div>
                <button type="submit">Submit Calibration Results</button>
                <iframe name="content" style="display:none;"></iframe>
            </div>
        </form>

    <hr>
    <h3>Previous Raman Calibrations</h3>
    <table id="ramanlog">
        <thread>
            <tr>
                <th>DateTime</th>
                <th>User</th>
                <th>Intensity</th>
                <th>Peak Position (cm-1)</th>
                <th>Wavelength (nm)</th>
                <th>Beam Steered?</th>
            </tr>
        </thread>
        <tbody>
            <?php
            foreach ($result as $row) {
                    if ($row['beam_steer'] == '0') $row['beam_steer'] = "No";
                        elseif ($row['beam_steer'] == '1') $row['beam_steer'] = "Yes";
                    echo "<tr>
                    <td>{$row['date_time']}</td>
                    <td>{$row['user']}</td>
                    <td>{$row['intensity']}</td>
                    <td>{$row['peak_pos']}</td>
                    <td>{$row['wavelength']}</td>
                    <td>{$row['beam_steer']}</td>
                </tr>";
                }
            if (!$result) {
                echo "<tr><td colspan='6'> No Data Available </td></tr>";
            }
            ?>
        </tbody>
    </table>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#employeeTable').DataTable();
        });
    </script>
    <script>
        //Refresh page after 7 seconds of any form submission
        $(document).on('submit', 'form', function () {
            setTimeout(function () { location.reload(true); }, 100);
        });
    </script>
    <?  require "../config/footer.php"; ?>
</html>

<?php
//  Close Connection
$conn->close();
?>