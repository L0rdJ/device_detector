<?php

/** This is meant to be run in command line mode. It dumps everything that would appear in device_os/device_os_vendor, and also DP and browser fields.
 *	It generates CSV files into this directory. These can be matched against if you're matching against particular browsers or vendors. 
 */

/**
 * ************** Generate lookup lists *******************************
* Sample Script - feel free to alter/edit
* by Chris Abbott, DetectRight Ltd.
*/
include("../detectright.php");

DetectRight::initialize("DRSQLite///your/path/to/detectright.data");
DetectRight::$dpAsOS = false; // <-- this must be set to false for the developer platform to be filled. 

// OS list
$osFN = $DRROOT."/lookups/os.csv";
$dpFN = $DRROOT."/lookups/dp.csv";
$browserFN = $DRROOT."/lookups/browser.csv";

file_put_contents($osFN,"id\tdescription\tcategory\ttype\n");

$os = DetectRight::$dbLink->simpleFetch("entity",array("hash","description","category"),array("entitytype"=>array("op"=>"in","value"=>array("OS"))),array("description"=>"ASC"));

foreach ($os as $osRow) {
	$hash = $osRow['hash']; // unique ID in DetectRight
	$description = $osRow['description'];  // field "device_os"
	$category = $osRow['category'];  // field "device_os_vendor"
	file_put_contents($osFN,$hash."\t$description\t$category\n",FILE_APPEND);
}

file_put_contents($dpFN,"id\tdescription\tcategory\ttype\n");

$dp = DetectRight::$dbLink->simpleFetch("entity",array("hash","description","category"),array("entitytype"=>array("op"=>"in","value"=>array("Developer Platform"))),array("description"=>"ASC"));

foreach ($dp as $dpRow) {
	$hash = $dpRow['hash'];
	$description = $dpRow['description']; // field "device_dp"
	$category = $dpRow['category']; // field "device_dp_vendor"
	file_put_contents($dpFN,$hash."\t$description\t$category\n",FILE_APPEND);
}

file_put_contents($browserFN,"id\tdescription\tcategory\ttype\n");

$browsers = DetectRight::$dbLink->simpleFetch("entity",array("hash","entitytype","category","description"),array("entitytype"=>array("op"=>"in","value"=>array("Browser","Mobile Browser"))),array("description"=>"ASC"));

foreach ($browsers as $browserRow) {
	$hash = $browserRow['hash'];
	$description = $browserRow['description']; // field "mobile_browser"
	$category = $browserRow['category']; // field "mobile_browser_vendor"
	$type = $browserRow['entitytype']; // designates whether the browser is mobile or not.
	file_put_contents($browserFN,"$hash\t$description\t$category\t$type\n",FILE_APPEND);
}