<?php
require_once('conf.php');
require_once('common.php');

$conn = mysqli_connect(DBHOST, DBUSER, DBPASS, DBNAME);
if (!$conn) {
    // If the connection was not successful, print an error message and exit
    echo 'Error: Could not connect to DB server';
    exit;
}

$year = 2022; // Set the year you want to retrieve data for

$startDate = strtotime("$year-01-01");// Set the start and end dates for the year
$endDate = strtotime(($year+1).'-01-01');// $endDate = strtotime("$year-12-31");
$baseUrl = 'https://www.strava.com/api/v3';// Set the base URL for the Strava API

if (isset($_GET['code'])) { // User has authorized the app, retrieve user data and tokens
    strava_init_user($_GET['code']);
    header('Location: '.REDIRECTURI);
}
if(isset($_COOKIE['user_id'])) { // is th user logged to the app ?
    $userId = $_COOKIE['user_id'];
} else {
    strava_oauth();
}
$accessToken = strava_get_access_token($userId);
$user = get_user($userId);
echo 'Bonjour '.$user['prenom'].' '.$user['nom'].'<br>'.PHP_EOL;

$activities = array();
$page = 1;
$perPage = 200;
$lastPage = false;
$maxPages = 5;

$joursAnnee = numberOfDaysInYear($year);
$detail = array();
for ($i=0; $i<$joursAnnee; $i++) {
    $detail[$i]=0;
}
$detailStr = '';
$joursActifs = 0;
$serieTemp = 0;
$serieLast = 0;
$serieMax = 0;

$distanceMax = 0;
$dureeMax = 0;

while (!$lastPage && $page <= $maxPages) {
    echo '.';
    // Make a request to the Strava API to retrieve the activities for the current page
    $response = file_get_contents("$baseUrl/athlete/activities?page=$page&per_page=$perPage&access_token=$accessToken&after=$startDate&before=$endDate");
    $data = json_decode($response, true);
    $activities = array_merge($activities, $data);
    $lastPage = $page == $data['pagination']['total_pages']; // Check if we have reached the last page
    $page++;
}
echo "<div>$userPrenom $userNom<br>\n";
echo "<br>\n";
echo "<textarea>";print_r($activities);echo "</textarea><br>\n";

$allDuration = 0;
$allDistance = 0;
$allElevation = 0;
$inlineSkateDuration = 0;
$inlineSkateDistance = 0;
$inlineSkateElevation = 0;
$rideDuration = 0;
$rideDistance = 0;
$rideElevation = 0;
$runDuration = 0;
$runDistance = 0;
$runElevation = 0;
$otherDuration = 0;
$otherDistance = 0;
$otherElevation = 0;

// Loop through the activities and print the type, duration, and distance of each activity
foreach ($activities as $activity) {
    // Add the duration, distance, and elevation of the activity to the totals for all activities
    $allDuration += $activity['moving_time'];
    $allDistance += $activity['distance'];
    $allElevation += $activity['total_elevation_gain'];
    $detail[dateToDayNumber($activity['start_date_local'])]++;
    if (m2km($activity['distance']) > $distanceMax) $distanceMax = m2km($activity['distance']);
    if ($activity['moving_time'] > $dureeMax) $dureeMax = $activity['moving_time'];

    // Check the type of the activity and add the duration, distance, and elevation to the totals for the appropriate type
    switch ($activity['type']) {
      case 'InlineSkate':
        $inlineSkateDuration += $activity['moving_time'];
        $inlineSkateDistance += $activity['distance'];
        $inlineSkateElevation += $activity['total_elevation_gain'];
        break;
      case 'Ride':
        $rideDuration += $activity['moving_time'];
        $rideDistance += $activity['distance'];
        $rideElevation += $activity['total_elevation_gain'];
        break;
      case 'Run':
        $runDuration += $activity['moving_time'];
        $runDistance += $activity['distance'];
        $runElevation += $activity['total_elevation_gain'];
        break;
      default:
        $otherDuration += $activity['moving_time'];
        $otherDistance += $activity['distance'];
        $otherElevation += $activity['total_elevation_gain'];
        break;
    }
}
for ($i=0; $i<$joursAnnee; $i++) {
    $detailStr[$i] = (string) $detail[$i];
    if ($detail[$i]!=0) {
        $joursActifs++;
        if ($serieLast == 1) {
            $serieTemp++;
            if ($serieTemp > $serieMax) {
                $serieMax = $serieTemp;
            }
        } else {
            $serieTemp = 1;
        }
        $serieLast = 1;
    } else {
        $serieLast = 0;
    }
  }

// compte rendu de l'année
$query = "SELECT * FROM yearSummary WHERE id = $userId AND annee = $year";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    $query = "UPDATE yearSummary SET allDuree = '$allDuration', allDistance = '$allDistance', allElevation = '$allElevation', inlineDuree = '$inlineSkateDuration', inlineDistance = '$inlineSkateDistance', inlineElevation = '$inlineSkateElevation', veloDuree = '$rideDuration', veloDistance = '$rideDistance', veloElevation = '$rideElevation', courseDuree = '$runDuration', courseDistance = '$runDistance', courseElevation = '$runElevation', autreDuree = '$otherDuration', autreDistance = '$otherDistance', autreElevation = '$otherElevation', joursActifs='$joursActifs', serieMax='$serieMax', distanceMax='$distanceMax', dureeMax='$dureeMax', detail='$detailStr' WHERE id = $userId AND annee = $year";
    mysqli_query($conn, $query);
    // echo '/';
} else {
    $query = "INSERT INTO yearSummary (id, annee, allDuree, allDistance, allElevation, inlineDuree, inlineDistance, inlineElevation, veloDuree, veloDistance, veloElevation, courseDuree, courseDistance, courseElevation, autreDuree, autreDistance, autreElevation, joursActifs, serieMax, distanceMax, dureeMax, detail) VALUES ('$userId', '$year', '$allDuration', '$allDistance', '$allElevation', '$inlineSkateDuration', '$inlineSkateDistance', '$inlineSkateElevation', '$rideDuration', '$rideDistance', '$rideElevation', '$runDuration', '$runDistance', '$runElevation', '$otherDuration', '$otherDistance', '$otherElevation', '$joursActifs', '$serieMax', '$distanceMax', '$dureeMax', '$detailStr')";
    mysqli_query($conn, $query);
    // echo '+';
}
mysqli_close($conn);

$imgurl = "https://peripheria.fr/rollerviewer/myyear.php?id=$userId&year=$year";
echo '<img src="'.$imgurl.'" style="height:700px;width:700px;"><br><a href="'.$imgurl.'&download">download</a>';
