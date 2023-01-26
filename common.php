<?php
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