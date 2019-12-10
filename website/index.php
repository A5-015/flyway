<?PHP

$mysqli = new mysqli("localhost","bsimsekc_flyway","insert_password_here","bsimsekc_flyway");

// Check connection
if ($mysqli -> connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  exit();
}

/*
for ($i = 0; $i < 3; $i++) {
    // For inserting dummy data for a week
    for ($y = 0; $y < 7; $y++) {
        for ($x = 0; $x < 24; $x++) {
            $rand = rand(0, 100);
            $sql = "INSERT INTO crowdedness (location_id, day_of_week, hour_of_day, crowdedness) VALUES ('$i', '$y', '$x:00:00', '$rand')";
            $mysqli->query($sql);
        }
    }
}
*/

$page_selection = $_GET['loc'];

// Get current day of the week
$current_day_num = date("w", strtotime(date('l')));
$current_day_num = $current_day_num-1;

// Get hour of the day
date_default_timezone_set("Asia/Dubai");
$current_hour = new DateTime();
$current_hour = $current_hour->format('H:i:s');
//$current_hour[0] = 1;
//$current_hour[1] = 5;
$current_hour[3] = 0;
$current_hour[4] = 0;
$current_hour[6] = 0;
$current_hour[7] = 0;


if($page_selection == ""){
    $page_selection = "-1";

    $sql = "SELECT * FROM crowdedness INNER JOIN locations ON locations.location_id=crowdedness.location_id
            WHERE crowdedness.day_of_week = '$current_day_num' AND crowdedness.hour_of_day = '$current_hour' ";
    //echo $sql;
    $result = $mysqli->query($sql);

}else {
    $day_selection = $_GET['day'];

    if($day_selection == ""){
        $day_selection = "0";
    }

    $dowMap = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
    $selected_day = $dowMap[$day_selection];


    // Get information relatd to percentage bar
    $sql = "SELECT * FROM crowdedness INNER JOIN locations ON locations.location_id=crowdedness.location_id
            WHERE crowdedness.day_of_week = '$current_day_num' AND crowdedness.hour_of_day = '$current_hour' AND crowdedness.location_id = '$page_selection' ";
    //echo $sql;
    $result = $mysqli->query($sql);


    // Get information for the graphs
    if($day_selection > $current_day_num){
        $predictions_enabled = true;

        $sql = "SELECT hour_of_day, crowdedness FROM crowdedness WHERE crowdedness.location_id = '$page_selection' AND crowdedness.day_of_week = '$day_selection' ";
        //echo $sql;
        $result_for_plot = $mysqli->query($sql);

        $predicted_xarr = array();
        $predicted_yarr = array();

        while($row = $result_for_plot->fetch_assoc()) {
            array_push($predicted_xarr, date("g:i A", strtotime($row["hour_of_day"])));
            array_push($predicted_yarr, round($row["crowdedness"]*100));
        }

    }else if($day_selection == $current_day_num){
        $predictions_enabled = true;

        $sql = "SELECT hour_of_day, crowdedness FROM crowdedness WHERE crowdedness.location_id = '$page_selection' AND crowdedness.day_of_week = '$day_selection' AND crowdedness.hour_of_day < '$current_hour'";
        //echo $sql;
        $result_for_plot = $mysqli->query($sql);

        $actual_xarr = array();
        $actual_yarr = array();

        while($row = $result_for_plot->fetch_assoc()) {
            array_push($actual_xarr, date("g:i A", strtotime($row["hour_of_day"])));
            array_push($actual_yarr, round($row["crowdedness"]*100));
        }


        $sql = "SELECT hour_of_day, crowdedness FROM crowdedness WHERE crowdedness.location_id = '$page_selection' AND crowdedness.day_of_week = '$day_selection' AND crowdedness.hour_of_day >= '$current_hour'";
        //echo $sql;
        $result_for_plot = $mysqli->query($sql);

        $predicted_xarr = array();
        $predicted_yarr = array();

        while($row = $result_for_plot->fetch_assoc()) {
            array_push($predicted_xarr, date("g:i A", strtotime($row["hour_of_day"])));
            array_push($predicted_yarr, round($row["crowdedness"]*100));
        }

    }else{
        $predictions_enabled = false;

        $sql = "SELECT hour_of_day, crowdedness FROM crowdedness WHERE crowdedness.location_id = '$page_selection' AND crowdedness.day_of_week = '$day_selection'";
        //echo $sql;
        $result_for_plot = $mysqli->query($sql);

        $actual_xarr = array();
        $actual_yarr = array();

        while($row = $result_for_plot->fetch_assoc()) {
            array_push($actual_xarr, date("g:i A", strtotime($row["hour_of_day"])));
            array_push($actual_yarr, round($row["crowdedness"]*100));
        }

    }


    // Get information for the peak time
    $sql = "SELECT day_of_week, hour_of_day, crowdedness FROM crowdedness WHERE location_id = $page_selection ORDER BY crowdedness DESC LIMIT 1";
    //echo $sql;
    $result_for_peak_time = $mysqli->query($sql);

    while($row = $result_for_peak_time->fetch_assoc()) {
        $peak_time_message = "Typically busiest at ".date("g:i A", strtotime($row["hour_of_day"]))." on ".$dowMap[$row["day_of_week"]];
    }

}

$least_busy_place_count = PHP_INT_MAX;

if ($result->num_rows >= 0) {
    while($row = $result->fetch_assoc()) {
        //$crowdedness = ($row["people_count"]/$row["capacity"]) * 100;
        $crowdedness = $row["crowdedness"]*100;
        $crowdedness = round($crowdedness);

        if($crowdedness <= 30){
            $crowdedness_message = "Not Busy ".$crowdedness."%";
        }else if($crowdedness <= 50){
            $crowdedness_message = "Moderate Busy ".$crowdedness."%";
        }else if($crowdedness <= 75){
            $crowdedness_message = "Busy ".$crowdedness."%";
        }else if($crowdedness <= 100){
            $crowdedness_message = "Very Busy ".$crowdedness."%";
        }

        if($crowdedness <= 25){
            $bar_color = "bg-success";
        }else if($crowdedness <= 50){
            $bar_color = "bg-info";
        }else if($crowdedness <= 75){
            $bar_color = "bg-warning";
        }else {
            $bar_color = "bg-danger";
        }

        if($row["is_closed"]){
            $closed_message = "Closed";
            $crowdedness_message = "Closed";
            $crowdedness = 0;
        }else {
            $closed_message = "Open";
        }

        $temp_app = array(  //"people_count" => $row["people_count"],
                            "capacity" => $row["capacity"],
                            "name" => $row["name"],
                            "message" => $peak_time_message,
                            "hours" => $row["hours"],
                            "crowdedness" => $crowdedness,
                            "crowdedness_message" => $crowdedness_message,
                            "bar_color" => $bar_color,
                            "is_closed" => $closed_message
                        );

        $location_array[$row["location_id"]] = $temp_app;

        if($least_busy_place_count > $crowdedness){
            if($crowdedness != 0){
                $least_busy_place_count = $crowdedness;
                $least_busy_place_message = "Right now '".$row["name"]."' is the least busy place";
            }
        }
    }
}

/*
echo "<pre>";
var_dump($location_array);
echo "</pre>";
*/

?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>flyway</title>
  <link rel="icon" type="image/png" href="assests/img/favicon.ico">

  <!-- Bootstrap core CSS -->
  <link href="assests/css/bootstrap.min.css" rel="stylesheet">

  <!-- Plotly.js -->
  <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>

</head>

<body width="100%">

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg static-top mb-3 bg-white shadow-sm" >
        <div class="container">
            <a class="navbar-brand" href="https://nyuad.app/flyway/"><img src="assests/img/logo_min_with_text.png" alt="" height="30px"></a>
        </div>
    </nav>

    <div class="mx-3">
        <!-- Page Content -->
        <div class="container">
            <?PHP
                if($page_selection == "-1"){
                    echo "<div class='row'>";
                    echo "<h5 style='font-size:1.15rem'>".$least_busy_place_message."</h5>";

                    foreach($location_array as $key => $value){

                        echo '
                            <div class="card border border-secondary rounded-0 mb-2" style="width: 100%;">
                                <div class="card-body">
                                    <div class="float-left">
                                        <h5>'.$value["name"].'</h5>
                                    </div>
                                    <div class="float-right">
                                        <h6 class="text-right">'.$value["is_closed"].'</h6>
                                    </div>
                                    <div class="clearfix"></div>
                                        <h6>'.$value["crowdedness_message"].'</h6>

                                    <div class="progress">
                                      <div class="progress-bar progress-bar-striped progress-bar-animated '.$value["bar_color"].'" role="progressbar" style="width: '.$value["crowdedness"].'%" aria-valuenow="'.$value["crowdedness"].'" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>

                                    <a href="https://nyuad.app/flyway/?loc='.$key.'&day='.$current_day_num.'" class="stretched-link"></a>
                                </div>
                            </div>
                        ';
                    }
                    echo "</div>";

                }else {
                    echo "<div class='row'>";
                    foreach($location_array as $key => $value){
                        echo '
                            <div class="card border border-0 rounded-0" style="width: 100%;">
                                <div class="card-body">
                                    <div class="float-left">
                                        <h5>'.$value["name"].'</h5>
                                    </div>
                                    <div class="float-right">
                                        <h6 class="text-right">'.$value["is_closed"].'</h6>
                                    </div>
                                    <div class="clearfix"></div>
                                        <h6>'.$value["crowdedness_message"].'</h6>

                                    <div class="progress">
                                      <div class="progress-bar progress-bar-striped progress-bar-animated '.$value["bar_color"].'" role="progressbar" style="width: '.$value["crowdedness"].'%" aria-valuenow="'.$value["crowdedness"].'" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            </div>
                        ';

                        echo "</div>";

                        echo "
                            <div class='dropdown show'>
                                <a class='btn btn-secondary dropdown-toggle' href='#' role='button' id='dropdownMenuLink' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                                    Day of the week
                                </a>

                                <div class='dropdown-menu' aria-labelledby='dropdownMenuLink'>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=0'>Monday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=1'>Tuesday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=2'>Wednesday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=3'>Thursday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=4'>Friday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=5'>Saturday</a>
                                    <a class='dropdown-item' href='https://nyuad.app/flyway/?loc=".$page_selection."&day=6'>Sunday</a>
                                </div>
                            </div>
                        ";

                        echo "<div class='row'>";
                        echo "
                            <!-- Plotly chart will be drawn inside this DIV -->
                            <div id='graphDiv' style='display: flex; flex-wrap: wrap; width: 100%;'></div>

                            <script>
                                var actual_xarr = ".json_encode($actual_xarr).";
                                var actual_yarr = ".json_encode($actual_yarr).";

                                var trace1 = {
                                    x: actual_xarr,
                                    y: actual_yarr,
                                    name: 'Actual',
                                    type: 'bar'
                                };

                                ";


                        if($predictions_enabled){
                            echo "var predicted_xarr = ".json_encode($predicted_xarr).";";
                            echo "var predicted_yarr = ".json_encode($predicted_yarr).";";

                            echo "
                                    var trace2 = {
                                        x: predicted_xarr,
                                        y: predicted_yarr,
                                        // opacity: 0.75,
                                        name: 'Predicted',
                                        type: 'bar'
                                    };
                                ";

                            echo "var data = [trace1, trace2];";

                        } else {
                            echo "var data = [trace1];";

                        }

                        echo "
                                var layout = {
                                    title: '',

                                    showlegend: true,

                                    legend: {
                                        xanchor: 'right',
                                        x: 1,
                                        y: 1.1,
                                        orientation: 'h'
                                    },

                                    xaxis: {
                                        title: 'Hour of the day',
                                        showgrid: false,
                                        zeroline: false,
                                        fixedrange: true
                                    },

                                    yaxis: {
                                        title: 'Percent Crowdedness on ".$selected_day."',
                                        showline: false,
                                        fixedrange: true,
                                        //range: [0, 100]
                                    },

                                    margin: {
                                        t: 30, //top margin
                                        l: 50, //left margin
                                        r: 30, //right margin
                                        b: 100 //bottom margin
                                    }
                                };

                                Plotly.newPlot(graphDiv, data, layout, {displayModeBar: false});

                            </script>
                        ";
                        echo "</div>";

                        echo "<div class='row mb-3 mt-2'>";
                        echo '<br><h6>'.$value["message"].'</h6><br>';
                        echo "</div>";

                        echo "<div class='row'>";
                        echo '<br><h5>'.$value["name"].' Hours</h5><br>';
                        echo "</div>";

                        echo "<div class='row mb-2'>";
                        echo $value["hours"];
                        echo "</div>";
                    }
                }
            ?>
      </div>

    </div>

    <!-- Bootstrap core JavaScript -->
    <script src="assests/jquery/jquery.slim.min.js"></script>
    <script src="assests/js/bootstrap.bundle.min.js"></script>

</body>

</html>
