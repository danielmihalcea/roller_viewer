<?php
$baseUrl = 'https://www.strava.com/api/v3';// Set the base URL for the Strava API

function db_connect() {
    $conn = mysqli_connect(DBHOST, DBUSER, DBPASS, DBNAME);
    if (!$conn) {
        echo 'Error: Could not connect to DB server';
        exit;
    }
    mysqli_query($conn, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $conn;
}

function file_get_contents_utf8($fn) {
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function strava_oauth() {
    $scope = 'activity:read_all'; // Set the scope to request read_all access
    $clientId = CLIENTID;
    $redirectUri = REDIRECTURI;
    $oauthUrl = "https://www.strava.com/oauth/authorize?client_id=$clientId&redirect_uri=$redirectUri&response_type=code&scope=$scope";
    header("Location: $oauthUrl"); // Redirect the user to the OAuth URL
    exit;
}

function strava_init_user($code) {
    global $conn;
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

    $userId = strava_set_user($userId, $accessToken);
    setcookie('user_id', $userId, time()+2628000); // cookie expires in 1 month

    $query = "SELECT * FROM user WHERE id = $userId"; // l'utilisateur est-il d??j?? connu ? Si oui le maj sinon le cr??er
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $query = "UPDATE user SET refresh_token = '$refreshToken', access_token = '$accessToken', expires_at = '$expiresAt' WHERE id = $userId";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO user (refresh_token, access_token, expires_at) VALUES ('$refreshToken', '$accessToken', '$expiresAt')";
        mysqli_query($conn, $query);
    }

}

function strava_set_user($userId, $accessToken = NULL) {
    global $conn, $baseUrl;
    if ($accessToken === NULL) {
        $accessToken = strava_get_access_token($userId);
    }
    // Retrieve the user's information
    $response = file_get_contents_utf8("$baseUrl/athlete?access_token=$accessToken");
    $athlete = json_decode($response, true);
    $userId = $athlete['id'];
    $userPrenom = $athlete['firstname'];
    $userNom = $athlete['lastname'];
    $userEmail =  $athlete['email']; // doesn't exist ?
    $userPhoto =  $athlete['profile_medium'];
    $userVille =  $athlete['city'];
    $userPays =  $athlete['country'];
    
    $query = "SELECT * FROM user WHERE id = $userId"; // l'utilisateur est-il d??j?? connu ? Si oui le maj sinon le cr??er
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $query = "UPDATE user SET prenom = '$userPrenom', nom = '$userNom', email = '$userEmail', photo = '$userPhoto', ville = '$userVille', pays = '$userPays' WHERE id = $userId";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO user (id, prenom, nom, email, photo, ville, pays) VALUES ('$userId', '$userPrenom', '$userNom', '$userEmail', '$userPhoto', '$userVille', '$userPays')";
        mysqli_query($conn, $query);
    }
    return $userId;
}

function get_user($userId) {
    global $conn;
    $query = "SELECT * FROM user WHERE id = $userId";
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

function strava_get_access_token($userId) {
    $user = get_user($userId);
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

function summmarize_year($userId, $year) {
    global $conn, $baseUrl;
    $accessToken = strava_get_access_token($userId);
    $startDate = strtotime("$year-01-01");// Set the start and end dates for the year
    $endDate = strtotime(($year+1).'-01-01')-1;// end timecode is 1st jan at 0:00:00 = 1 second after the end oft the year

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
        // Make a request to the Strava API to retrieve the activities for the current page
        $response = file_get_contents("$baseUrl/athlete/activities?page=$page&per_page=$perPage&access_token=$accessToken&after=$startDate&before=$endDate");
        $data = json_decode($response, true);
        $activities = array_merge($activities, $data);
        $lastPage = $page == $data['pagination']['total_pages']; // Check if we have reached the last page
        $page++;
    }

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

    // compte rendu de l'ann??e
    $query = "SELECT * FROM yearSummary WHERE id = $userId AND annee = $year";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) > 0) {
        $query = "UPDATE yearSummary SET allDuree = '$allDuration', allDistance = '$allDistance', allElevation = '$allElevation', inlineDuree = '$inlineSkateDuration', inlineDistance = '$inlineSkateDistance', inlineElevation = '$inlineSkateElevation', veloDuree = '$rideDuration', veloDistance = '$rideDistance', veloElevation = '$rideElevation', courseDuree = '$runDuration', courseDistance = '$runDistance', courseElevation = '$runElevation', autreDuree = '$otherDuration', autreDistance = '$otherDistance', autreElevation = '$otherElevation', joursActifs='$joursActifs', serieMax='$serieMax', distanceMax='$distanceMax', dureeMax='$dureeMax', detail='$detailStr' WHERE id = $userId AND annee = $year";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO yearSummary (id, annee, allDuree, allDistance, allElevation, inlineDuree, inlineDistance, inlineElevation, veloDuree, veloDistance, veloElevation, courseDuree, courseDistance, courseElevation, autreDuree, autreDistance, autreElevation, joursActifs, serieMax, distanceMax, dureeMax, detail) VALUES ('$userId', '$year', '$allDuration', '$allDistance', '$allElevation', '$inlineSkateDuration', '$inlineSkateDistance', '$inlineSkateElevation', '$rideDuration', '$rideDistance', '$rideElevation', '$runDuration', '$runDistance', '$runElevation', '$otherDuration', '$otherDistance', '$otherElevation', '$joursActifs', '$serieMax', '$distanceMax', '$dureeMax', '$detailStr')";
        mysqli_query($conn, $query);
    }

}

function imageUTF8text($image, $fontSize, $x, $y, $textColor, $font, $string) {
    $string = mb_convert_encoding($string, 'UTF-8');
	for ($i=0; $i<mb_strlen($string); $i++){
		$char = mb_substr($string, $i, 1, 'UTF-8');
		$ord = mb_ord($char);
		if ($ord > 126976) {
			$emojiCode = dechex($ord);
			$emojiFile = 'noto/'.$emojiCode.'.png';
			$emojiNoto = 'https://raw.githubusercontent.com/googlefonts/noto-emoji/main/png/72/emoji_u'.$emojiCode.'.png';
			$emojiTwemoji = 'https://cdnjs.cloudflare.com/ajax/libs/twemoji/14.0.2/72x72/'.$emojiCode.'.png';
            if (file_exists($emojiFile)) {
                $emojiImage = imagecreatefrompng($emojiFile);
            } elseif (substr(@get_headers($emojiNoto)[0], 9, 3) === '200') {
				$emojiImage = imagecreatefrompng($emojiNoto);
            } elseif (substr(@get_headers($emojiTwemoji)[0], 9, 3) === '200') {
                $emojiImage = imagecreatefrompng($emojiTwemoji);
			} else {
                $emojiImage = imagecreatetruecolor($fontSize,$fontSize);
                $trans_colour = imagecolorallocatealpha($emojiImage, 0, 0, 0, 127);
                imagefill($emojiImage, 0, 0, $trans_colour);
			}
            imagecopyresampled ($image, $emojiImage, $x, $y-$fontSize, 0, 0, $fontSize, $fontSize, imagesx($emojiImage), imagesy($emojiImage));
            $x += $fontSize;
		} else {
			$rect = imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $char);
			$x += $rect[2] - $rect[0];
		}
	}
}

function html_top() {
    echo '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport">
    <title>Roller Viewer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
';
}

function html_bot() {
    echo '
</body>
</html>
';
}