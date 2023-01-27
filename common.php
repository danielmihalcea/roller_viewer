<?php
function strava_oauth() {
    $scope = 'activity:read_all'; // Set the scope to request read_all access
    $clientId = CLIENTID;
    $redirectUri = REDIRECTURI;
    $oauthUrl = "https://www.strava.com/oauth/authorize?client_id=$clientId&redirect_uri=$redirectUri&response_type=code&scope=$scope";
    header("Location: $oauthUrl"); // Redirect the user to the OAuth URL
    exit;
}
function strava_init_user($code) {
    global $conn, $baseUrl;
    $clientId = CLIENTID;
    $clientSecret = CLIENTSECRET;
    $postData = "client_id=$clientId&client_secret=$clientSecret&code=$code&grant_type=authorization_code"; // Set up the POST data for the request to the OAuth token URL
    $options = array( // Set up the options for the request to the OAuth token URL
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $postData,
    ),
    );
    $context  = stream_context_create($options); // Create a context for the request to the OAuth token URL
    $tokenResponse = file_get_contents("https://www.strava.com/oauth/token", false, $context); // Use the authorization code to request an access token
    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'];
    $expiresAt = $tokenData['expires_at'];
    $refreshToken = $tokenData['refresh_token'];
    // Retrieve the user's information
    $response = file_get_contents("$baseUrl/athlete?access_token=$accessToken");
    $athlete = json_decode($response, true);
    $userId = $athlete['id'];
    $userPrenom = $athlete['firstname'];
    $userNom = $athlete['lastname'];
    $userEmail =  $athlete['email']; // doesn't exist ?
    $userPhoto =  $athlete['profile_medium'];
    $userVille =  $athlete['city'];
    $userPays =  $athlete['country'];
    setcookie('user_id', $userId, time()+2628000); // cookie expires in 1 month
    // l'utilisateur est-il déjà connu ? Si oui le maj sinon le créer
    $query = "SELECT * FROM user WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $query = "UPDATE user SET prenom = '$userPrenom', nom = '$userNom', email = '$userEmail', photo = '$userPhoto', ville = '$userVille', pays = '$userPays', refresh_token = '$refreshToken', access_token = '$accessToken', expires_at = '$expiresAt' WHERE id = $userId";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO user (id, prenom, nom, email, photo, ville, pays, refresh_token, access_token, expires_at) VALUES ('$userId', '$userPrenom', '$userNom', '$userEmail', '$userPhoto', '$userVille', '$userPays', '$refreshToken', '$accessToken', '$expiresAt')";
        mysqli_query($conn, $query);
    }
}

function get_user($id) {
    global $conn;
    $query = "SELECT * FROM user WHERE id = $id";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return array();
}

function strava_refresh_tokken($refreshToken) {
    global $conn, $userId;
    $clientId = CLIENTID;
    $clientSecret = CLIENTSECRET;
    $postData = "client_id=$clientId&client_secret=$clientSecret&refresh_token=$refreshToken&grant_type=refresh_token"; // Set up the POST data for the request to the OAuth token URL
    $options = array( // Set up the options for the request to the OAuth token URL
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $postData,
    ),
    );
    $context  = stream_context_create($options); // Create a context for the request to the OAuth token URL
    $tokenResponse = file_get_contents("https://www.strava.com/oauth/token", false, $context); // Use the authorization code to request an access token
    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'];
    $expiresAt = $tokenData['expires_at'];
    $refreshToken = $tokenData['refresh_token'];
    $query = "UPDATE user SET refresh_token = '$refreshToken', access_token = '$accessToken', expires_at = '$expiresAt' WHERE id = $userId";
    $result = mysqli_query($conn, $query);
    return $accessToken;
}

function strava_get_access_token($id) {
    $user = get_user($id);
    if (count($user) > 0) {
        $accessToken = $user['access_token'] ?? '';
        $expiresAt = $user['expires_at'] ?? 0;
        $refreshToken = $user['refresh_token'] ?? '';
    } else {
        strava_oauth();
    }
    if ($expiresAt < time()) {
        return strava_refresh_tokken($refreshToken);
    }
    return $accessToken;
}

function sec2h($n) { // seconds to hours
    return round($n/3600);
}
function sec2hms($s) { // seconds to hrs min sec
    $hours = floor($s / 3600);
    $minutes = floor(($s / 60) % 60);
    $seconds = $s % 60;
    return "$hours hrs $minutes min $seconds sec";
}
function m2km($n) { // meters to km
    return round($n/1000, 1);
}

function dateToDayNumber($date) {
    return intval(date('z', strtotime($date)));
}

function numberOfDaysInYear($year) {
    return date('L', strtotime("$year-01-01")) ? 366 : 365;
}

function firstDayOfWeek($year) {
    return date('N', strtotime("$year-01-01"));
}

function showDetail ($detail, $year, $x0, $y0, $image) {
    $font = './arial.ttf';
    $textColor = imagecolorallocate($image, 255, 255, 255);
    if ($detail === null || strlen($detail) < 1) return;
    $week = array ('M', 'T', 'W', 'T', 'F', 'S', 'S');
    $firstDay = firstDayOfWeek($year);
    $weeksPerLine = 4;
    $dayHeight = 14;
    $dayWidth = 14;

    $nbDays = numberOfDaysInYear($year);
    $numLines = ceil($nbDays / (7 * $weeksPerLine));
    $x = $x0;
    $y = $y0;
    $fontSize = .8*$dayHeight;
    for ($i=0; $i < 28; $i++) {
        $x = $x0 + ($i%($weeksPerLine*7))*$dayWidth + $dayWidth*.5*floor(($i%($weeksPerLine*7)/7));
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, '.');
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $week[$i%7]);
    }
    $x = $x0 + $dayWidth/2;
    $y += $dayHeight;
    $color0 = imagecolorallocate($image, 128, 128, 128);
    $color1 = imagecolorallocate($image, 255, 96, 96);
    for ($i=0; $i < $nbDays; $i++) {
        $day = $detail[$i];
        $j = ($i+$firstDay-1);
        $x = $x0 + $dayWidth/2 + ($j%($weeksPerLine*7))*$dayWidth + $dayWidth*.5*floor(($j%($weeksPerLine*7)/7));
        $y = $y0 + floor($j / ($weeksPerLine*7))*$dayHeight + $dayHeight;
        if ($day === '0') {
            imagefilledellipse($image, $x, $y, $dayWidth*.3, $dayWidth*.3, $color0);
        } else if ($day === '1') {
            imagefilledellipse($image,$x, $y, $dayWidth*.6, $dayWidth*.6, $color1);
        } else {
            imagefilledellipse($image,$x, $y, $dayWidth*.9, $dayWidth*.9, $color1);
        }
    }
}