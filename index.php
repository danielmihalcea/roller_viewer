<?php
require_once('conf.php');
require_once('common.php');
$conn = db_connect();

$year = 2022; // Set the year you want to retrieve data for

if (isset($_GET['code'])) { // User has authorized the app, retrieve user data and tokens
    strava_init_user($_GET['code']);
    header('Location: '.REDIRECTURI);
}
if(isset($_COOKIE['user_id'])) { // is th user logged to the app ?
    $userId = $_COOKIE['user_id'];
} else {
    strava_oauth();
}

html_top();
$user = get_user($userId);
$imgurl = REDIRECTURI."/myyear.php?id=$userId&year=$year";
mysqli_close($conn);
?>

<div id="image">
    <img src="<?=$imgurl?>" id="year_image">
</div>
<div id="text">
    Bonjour <?=$user['prenom']?> <?=$user['nom']?><br>
    <select id="year">
        <?php
        for($i=$year+1; $i>$year-10; $i--) {
            $selected = ($i===$year)?' SELECTED':'';
            echo '<option value="myyear.php?id='.$userId.'&year='.$i.'"'.$selected.'>'.$i.'</option>'.PHP_EOL;
        }
        ?>
    </select><input type="button" value="voir" onclick="document.getElementById('year_image').src=document.getElementById('year').value;">
    <br>
    <a href="<?=$imgurl?>&download">download</a>
</div>

<?php
html_bot();