<?php
// SAMPLE SCRIPT which acts as a test and a sample web service
/*  Note: it is against the licence conditions to publicly offer access to a DetectRight
	SaaS/webservice installation. This script is intended for the internal use of 
	a licenced customer, or for genuine evaluation purposes. */

$DRROOT = "/path/to/detectright/";

include("/path/to/detectright.php");

// Put options here that you need to set.
DetectRight::$dpAsOS = true; // this makes the single column "device_os" a lot more informative (if it exists)

if (isset($_GET['cache'])) {
	$cache = $_GET['cache'];
	switch ($cache) {
		case 'Heap':
			DRCache::$connections = array('DetectRight' =>"DRHeapCache");
			break;	
		case "APC":
			DRCache::$connections = array('DetectRight' =>"DRAPCCache");
			break;
		case 'ZSHM':
			DRCache::$connections = array('DetectRight' =>"DRZendSHMCache"); // memory	
			break;
		case 'ZDSK':
			DRCache::$connections = array('DetectRight' =>"DRZendDiskCache");
			break;
		case "Memcached":
			DRCache::$connections = array('DetectRight' => "DRMemcached//localhost//11211");
			break;
		case "MemcachedRemote":
			DRCache::$connections = array('DetectRight' => "DRMemcached//{remote_ip}//11211");
			break;
		case 'MySQL':
			DRCache::$connections = array('DetectRight' =>"DRMySQLPDOCache//localhost//3306//drserver//DrsCab1123//drcache");
			break;
		case 'MySQLRemote':
			DRCache::$connections = array('DetectRight' =>"DRMySQLPDOCache//{remote_ip}//3306//{username}//{password}//drcache"); // note: you need to create this database and appropriate table
			break;
	}
}

if (isset($_GET['uaCache'])) {
	$uaCache = $_GET['uaCache'];
	switch ($uaCache) {
		case 'true':
			DetectRight::$uaCache = true;
			break;
		case 'false':
			DetectRight::$uaCache = false;
			break;
	}
}

if (isset($_GET['dataCache'])) {
	$dataCache = $_GET['dataCache'];
	switch ($dataCache) {
		case 'true':
			DBLink::$useQueryCache = true;
			break;
		case 'false':
			DBLink::$useQueryCache = false;
			break;
	}
}

$start = DRFunctionsCore::mt(); // timing start
DetectRight::initialize("DRSQLite///path/to/detectright.data");
$end = DRFunctionsCore::mt(); // timing end
$initTime = $end - $start;

if (isset($_GET['flush'])) {
	// resets the cache
	$flush = $_GET['flush'];
	if ($flush === "true") {
		$success = true;
		if (DetectRight::$cacheLink) $success = DetectRight::$cacheLink->cache_flush();
		echo "Cache flush ".($success ? "OK" : "Error");
		die();
	}
}

if (isset($_GET['redetect'])) DetectRight::$redetect = true; // defeats cache

$start = DRFunctionsCore::mt(); // timing start
$profile = DetectRight::getProfileFromHeaders($_SERVER);
//$profile = DetectRight::getProfileFromUA($_SERVER['HTTP_USER_AGENT']);
$end = DRFunctionsCore::mt(); // timing start
$detectTime = $end - $start;

$profile['it'] = $initTime;
$profile['dt'] = $detectTime;

echo serialize($profile);
//echo json_encode($profile);