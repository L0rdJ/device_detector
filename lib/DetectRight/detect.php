<?php
// sample script to run Detectright as web service detecting on a useragent and outputting
// an encoded array (or whatever you configure). It's also used by the test.php script.

include("detectright.php");

DetectRight::initialize("SQLite///path/to/detectright.data");

if (isset($_GET['flush'])) {
	apc_clear_cache();
  	apc_clear_cache('opcode');
  	apc_clear_cache('user');
  	echo "Done";
  	exit;
}
$ua = $_GET['ua'];
$start = DRFunctionsCore::mt();
$profile = DetectRight::getProfileFromUA($ua);
$end = DRFunctionsCore::mt();
$profile['timetaken'] = ($end - $start);
header("Content-type: text/plain");

// GZ is used to feed the "test.php" script, but you could output the array any way you wanted.
echo DRFunctionsCore::gz($profile);
//echo serialize($profile);
//echo json_encode($profile);
exit;