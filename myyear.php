<?php
require_once('conf.php');
require_once('common.php');

$imagePath = 'rollerviewer.png'; // Set the path to the template PNG file
$width = 1024; // Set the width and height of the image
$height = 1024;

$image = imagecreatetruecolor($width, $height); // creat new image from template
$existingImage = imagecreatefrompng($imagePath);
imagecopy($image, $existingImage, 0, 0, 0, 0, $width, $height);

$font = './arial.ttf'; // Set the font and text color for the text to be added to the image
$textColor = imagecolorallocate($image, 255, 255, 255);

$id = (int) ($_GET['id'] ?? 6198501) ?: 6198501;
$year = (int) ($_GET['year'] ?? 2022) ?: 2022;

// Add the year to the image
$fontSize = 55;
$x = 32;
$y = 32;
imagettftext($image, $fontSize, 0, $x, $y+$fontSize, $textColor, $font, $year);

$conn = mysqli_connect(DBHOST, DBUSER, DBPASS, DBNAME);
if (!$conn) {
    // If the connection was not successful, print an error message and exit
    echo 'Error: Could not connect to DB server';
    exit;
}

$query = "SELECT * FROM user WHERE id = $id";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    
    $user = mysqli_fetch_assoc($result);
    $prenom = $user['prenom'];
    $nom = $user['nom'];

    $photo = $user['photo'];
    $type = exif_imagetype($photo);
    switch($type) {
      case IMAGETYPE_JPEG:
        $img = imagecreatefromjpeg($photo);
        break;
      case IMAGETYPE_PNG:
        $img = imagecreatefrompng($photo);
        break;
      case IMAGETYPE_WEBP:
        $img = imagecreatefromwebp($photo);
        break;
      case IMAGETYPE_GIF:
        $img = imagecreatefromgif($photo);
        break;
      default:
    }
    imagecopyresampled($image, $img, 32, 112, 0, 0, 64, 64, ImageSX($img), ImageSY($img));
    $fontSize = 55;
    $x = 96;
    $y = 112;
    imagettftext($image, $fontSize, 0, $x, $y+$fontSize, $textColor, $font, $prenom.' '.$nom);
}

$query = "SELECT * FROM yearSummary user WHERE id = $id AND annee = $year";
$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) > 0) {
    $summary = mysqli_fetch_assoc($result);
    
    $allDistance = $summary['allDistance'];
    imagettftext($image, 24, 0, 80, 320, $textColor, $font, m2km($allDistance).' km');
    imagettftext($image, 24, 0, 30, 520, $textColor, $font, 'Max Distance');
    imagettftext($image, 24, 0, 80, 550, $textColor, $font, $summary['distanceMax'].' km');

    $allDuree = $summary['allDuree'];
    imagettftext($image, 24, 0, 80, 650, $textColor, $font, sec2h($allDuree).' hrs');
    imagettftext($image, 24, 0, 30, 860, $textColor, $font, 'Max Duration');
    imagettftext($image, 24, 0, 20, 890, $textColor, $font, sec2hms($summary['dureeMax']));

    $allElevation = $summary['allElevation'];
    imagettftext($image, 24, 0, 450, 650, $textColor, $font, round($allElevation).' m');
    imagettftext($image, 24, 0, 390, 860, $textColor, $font, round($allElevation/8849,1).'×');

    $activeDays = $summary['joursActifs'];
    $maxSerie = $summary['serieMax'];
    imagettftext($image, 24, 0, 400, 290, $textColor, $font, $activeDays.' active days');
    imagettftext($image, 24, 0, 320, 320, $textColor, $font, 'max : '.$maxSerie.' consecutive days');
    showDetail($summary['detail'], $year, 300, 364, $image);

    // roller
    imagettftext($image, 24, 0, 780, 290, $textColor, $font, 'roller');
    imagettftext($image, 20, 0, 780, 320, $textColor, $font, m2km($summary['inlineDistance']).' km');
    imagettftext($image, 20, 0, 780, 350, $textColor, $font, sec2hms($summary['inlineDuree']));
    imagettftext($image, 20, 0, 780, 380, $textColor, $font, $summary['inlineElevation'].' m');

    // velo
    imagettftext($image, 24, 0, 780, 440, $textColor, $font, 'cycling');
    imagettftext($image, 20, 0, 780, 470, $textColor, $font, m2km($summary['veloDistance']).' km');
    imagettftext($image, 20, 0, 780, 500, $textColor, $font, sec2hms($summary['veloDuree']));
    imagettftext($image, 20, 0, 780, 530, $textColor, $font, $summary['veloElevation'].' m');

    // course
    imagettftext($image, 24, 0, 780, 590, $textColor, $font, 'running');
    imagettftext($image, 20, 0, 780, 620, $textColor, $font, m2km($summary['courseDistance']).' km');
    imagettftext($image, 20, 0, 780, 650, $textColor, $font, sec2hms($summary['courseDuree']));
    imagettftext($image, 20, 0, 780, 680, $textColor, $font, $summary['courseElevation'].' m');

    // autre
    imagettftext($image, 24, 0, 780, 740, $textColor, $font, 'other');
    imagettftext($image, 20, 0, 780, 770, $textColor, $font, m2km($summary['autreDistance']).' km');
    imagettftext($image, 20, 0, 780, 800, $textColor, $font, sec2hms($summary['autreDuree']));
    imagettftext($image, 20, 0, 780, 830, $textColor, $font, $summary['autreElevation'].' m');

}

// Check if the download parameter is set
if (isset($_GET['download'])) {
  // The download parameter is set, so force the browser to download the image
  header('Content-Type: image/png');
  header('Content-Disposition: attachment; filename="myyear'.$year.'.png"');
  imagepng($image);
} else {
  // The download parameter is not set, so display the image in the browser
  header('Content-Type: image/png');
  imagepng($image);
}

imagedestroy($image);
imagedestroy($existingImage);
