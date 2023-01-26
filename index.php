<?php
require_once('conf.php');
require_once('common.php');

$scope = 'activity:read_all'; // Set the scope to request read_all access

$year = 2022; // Set the year you want to retrieve data for
$year2 = $year+1;

// Set the start and end dates for the year
$startDate = strtotime("$year-01-01");
// $endDate = strtotime("$year-12-31");
$endDate = strtotime("$year2-01-01");

// Set the base URL for the Strava API
$baseUrl = 'https://www.strava.com/api/v3';

// Check if the user has authorized the app
if (!isset($_GET['code'])) { // User has not yet authorized the app, so generate the OAuth URL
  $oauthUrl = "https://www.strava.com/oauth/authorize?client_id=$clientId&redirect_uri=$redirectUri&response_type=code&scope=$scope";
  header("Location: $oauthUrl"); // Redirect the user to the OAuth URL
  exit;
}
// User has authorized the app, so retrieve the authorization code
$code = $_GET['code'];

// Set up the POST data for the request to the OAuth token URL
$postData = "client_id=$clientId&client_secret=$clientSecret&code=$code&grant_type=authorization_code";

// Set up the options for the request to the OAuth token URL
$options = array(
  'http' => array(
    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    'method'  => 'POST',
    'content' => $postData,
  ),
);

// Create a context for the request to the OAuth token URL
$context  = stream_context_create($options);

// Use the authorization code to request an access token
$tokenResponse = file_get_contents("https://www.strava.com/oauth/token", false, $context);

// echo $tokenResponse;

$tokenData = json_decode($tokenResponse, true);
$accessToken = $tokenData['access_token'];

// Retrieve the user's information
$response = file_get_contents("$baseUrl/athlete?access_token=$accessToken");
$athlete = json_decode($response, true);

// Print the user's name and email address
//   echo 'Name: ' . $athlete['firstname'] . ' ' . $athlete['lastname'] . '<br>';
//   echo 'Email: ' . $athlete['email'] . '<br><br>';
// print_r($athlete);
$userId = $athlete['id'];
$userPrenom = $athlete['firstname'];
$userNom = $athlete['lastname'];
$userEmail =  $athlete['email']; // doesn't exist ?
$userPhoto =  $athlete['profile_medium'];
$userVille =  $athlete['city'];
$userPays =  $athlete['country'];
  
// Initialize an array to hold the activities
$activities = array();
  
// Set the initial page to 1
$page = 1;
$perPage = 200;
  
// Set a flag to indicate whether we have reached the last page
$lastPage = false;
  
// Set the maximum number of pages to retrieve
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
  // Make a request to the Strava API to retrieve the activities for the current page
  echo '.';
  // echo "<br>$baseUrl/athlete/activities?page=$page&per_page=100&access_token=$accessToken&after=$startDate&before=$endDate<br>\n";
  $response = file_get_contents("$baseUrl/athlete/activities?page=$page&per_page=$perPage&access_token=$accessToken&after=$startDate&before=$endDate");

  // Decode the response from JSON into a PHP array
  $data = json_decode($response, true);
      
  // Add the activities from the current page to the array
  $activities = array_merge($activities, $data);
      
  // Check if we have reached the last page
  $lastPage = $page == $data['pagination']['total_pages'];
      
  // Increment the page number
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
  // echo 'Type: ' . $activity['type'] . '<br>';
  // echo 'Duration: ' . $activity['duration'] . ' seconds<br>';
  // echo 'Distance: ' . $activity['distance'] . ' meters<br><br>';
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

  // Print the total duration, distance, and elevation for all activities
  echo 'Total duration for all activities: ' . sec2h($allDuration) . ' hours<br>';
  echo 'Total distance for all activities: ' . m2km($allDistance) . ' km<br>';
  echo 'Total elevation for all activities: ' . round($allElevation) . ' meters<br><br>';

  // Print the total duration, distance, and elevation for InlineSkate activities
  echo 'Total duration for InlineSkate activities: ' . sec2h($inlineSkateDuration) . ' hours<br>';
  echo 'Total distance for InlineSkate activities: ' . m2km($inlineSkateDistance) . ' km<br>';
  echo 'Total elevation for InlineSkate activities: ' . round($inlineSkateElevation) . ' meters<br><br>';

  // Print the total duration, distance, and elevation for Ride activities
  echo 'Total duration for Ride activities: ' . sec2h($rideDuration) . ' hours<br>';
  echo 'Total distance for Ride activities: ' . m2km($rideDistance) . ' km<br>';
  echo 'Total elevation for Ride activities: ' . round($rideElevation) . ' meters<br><br>';

  // Print the total duration, distance, and elevation for Run activities
  echo 'Total duration for Run activities: ' . sec2h($runDuration) . ' hours<br>';
  echo 'Total distance for Run activities: ' . m2km($runDistance) . ' km<br>';
  echo 'Total elevation for Run activities: ' . round($runElevation) . ' meters<br><br>';

  // Print the total duration, distance, and elevation for other activities
  echo 'Total duration for other activities: ' . sec2h($otherDuration) . ' hours<br>';
  echo 'Total distance for other activities: ' . m2km($otherDistance) . ' km<br>';
  echo 'Total elevation for other activities: ' . round($otherElevation) . ' meters<br><br>';

  echo 'Max distance : '.$distanceMax.' km<br>';
  echo 'Max durée : '.$dureeMax.' s / '.sec2h($dureeMax).' h<br>';


//   print_r($activities);
$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn) {
    // If the connection was not successful, print an error message and exit
    echo 'Error: Could not connect to DB server';
    exit;
}

// l'utilisateur est-il déjà connu ? Si oui le maj sinon le créer
$query = "SELECT * FROM user WHERE id = $userId";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    $query = "UPDATE user SET prenom = '$userPrenom', nom = '$userNom', email = '$userEmail', photo = '$userPhoto', ville = '$userVille', pays = '$userPays' WHERE id = $userId";
    mysqli_query($conn, $query);
    // echo '/' ;
} else {
    $query = "INSERT INTO user (id, prenom, nom, email, photo, ville, pays) VALUES ('$userId', '$userPrenom', '$userNom', '$userEmail', '$userPhoto', '$userVille', '$userPays')";
    mysqli_query($conn, $query);
    // echo '+';
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
