<?php
// SAMPLE SCRIPT - edit to suit
$ROOT = "/your/path/to/detectright";

include("/your/path/to/detectright/detectright.php");

$start = DRFunctionsCore::mt(); // timing start
DetectRight::initialize("DRSQLite///your/path/to/detectright.data");
$end = DRFunctionsCore::mt(); // timing end

$profile = DetectRight::getProfileFromHeaders($_SERVER);
//$profile = DetectRight::getProfileFromUA($_SERVER['HTTP_USER_AGENT']);
var_dump($profile);