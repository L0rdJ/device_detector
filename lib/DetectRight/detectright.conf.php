<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    detectright.conf.php
Version: 2.8.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2014 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License
Agreement, the latest version of which can be found at

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance,
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com
2.7.0 - rejigged options and available caches, more explanation.
2.7.0 - note that this file has a lot of explanation in it. You could usefully remove these comments
since there are obvious performance implications (though probably small ones).
**********************************************************************************/
/**** TEMPLATE ONLY: PLEASE COPY TO A CONF DIRECTORY! **********/

	/**************************** CACHE OPTIONS ***************************/
	/**
	 * Do we use the cache generally?
	 *
	 * @DetectRight::boolean
	 * @internal
	 * @access public
	 */
	DRCache::$useCache=true; // turns on cacheing generally. Usually a good idea.
	// Conservative defaults. CLI assumes batch mode, but if memory is an issue, use another cache.
	if (PHP_SAPI === 'cli') {
		DRCache::$connections = array('DetectRight' =>"DRHeapCache");
	} else {
		DRCache::$connections = array('DetectRight' =>"DRAPCCache");
	}
	// List of possible cache configurations. Anything uncommented here will override what just happened in the above lines.
	//DRCache::$connections = array('DetectRight' =>"DRMySQLCache//localhost//3306//drserver//DrsCab1123//drcache");
	//DRCache::$connections = array('DetectRight' =>"DRMySQLPDOCache//localhost//3306//drserver//DrsCab1123//drcache");/*

/* MySQL Table DDL for the cache table:

CREATE TABLE `cache` (
  `idcache` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(512) COLLATE latin1_general_cs NOT NULL,
  `object` mediumblob,
  `expiry` timestamp NULL DEFAULT NULL,
  `vartype` varchar(40) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `serialized` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`idcache`),
  UNIQUE KEY `key_UNIQUE` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_cs

*/
	//DRCache::$connections = array('DetectRight' =>"DRRedisCache//localhost//6379";
	//DRCache::$connections = array('DetectRight' =>"DRHeapCache");
	//DRCache::$connections = array('DetectRight' =>"DRZendSHMCache"); // memory
	//DRCache::$connections = array('DetectRight' =>"DRZendDiskCache");
	//DRCache::$connections = array('DetectRight' =>"DRAPCCache");
	//DRCache::$connections = array('DetectRight' =>"DRMemcached//localhost//11211");
	/*** UserAgent Cache ****/
	DetectRight::$uaCache = true;

	/* Explanation  of useragent cache:

	Caches profiles for headers. If we're in "Express Mode", it caches using the user-agent as a key.
	In real traffic, this is must.
	*/

	/*** Database Cache ***/
	DBLink::$useQueryCache = false; 

	/* 
	In an ideal situation, setting the above flag to true increases the speed of the system, since
	the SQLite file (not particularly concurrent) is not targeted as much.
	
	
	Explanation of query database cache:
	====================================
	DetectRight works off an SQLite file database. In the Java build, this database is loaded into a memory resident SQLite database
	connection. While SQLite can do this in PHP, it can't share the resulting link across multiple accesses, which means
	all you've done is add a load of overhead.

	The database query is very similar in concept to MySQL's: hash the query and retrieve the results from a cache. This relieves
	the load if you've stored the database file on a network, or in the event of lots of traffic accessing the SQLite file.

	It does mean that if you replace the database file, you have to flush the cache.
	
	Should I use the query cache?
	=============================
	If you're running Heap Cache (e.g. using PHP internal memory), 
	the query cache still speeds things up, but requires much more memory.
	
	If your cache is located somewhere other than localhost: set to "false"
	(the overhead of multiple additional calls to the cache outweighs the relief of stress put on the SQLite file.)

	If you're using Redis, MySQL or memcached @ localhost, then this option improves performance.
	Note that this is dependent upon the MySQL or memcache server being appropriately configured.
	
	If you're using APC under vanilla PHP, this setting should be left "false".
	If you're using APC under Zend Server, this setting should be left "true".
	If you're using APC under HipHop PHP, this setting can be set to "true".
	
	Zend Studio Server has two extra caches: Zend Studio Shared Memory and Zend Studio Disk Cache.
	We have found that the performance of Shared Memory cache is disappointing, 
	and APC should be used in preference (even though technically they are pointing at the same thing!).
	Zend Studio Disk cache is effective though performs worse than the other cache options.
	
	Note about IIS/PHP/Wincache
	
	(WinCache is an APC substitute for PHP under IIS (APC doesn't work there).
	
	Though we have written a Wincache object, we have found wincache to be unusable for batch mode, partly due to its
	small maximum size. There have also been reports of flakiness. Certainly the data cache should be turned off for this
	configuration.
	*/

	/*************************************************** Extra Internal Caches ******************************************************/
	/* The below caches should mostly be left alone. */
	EntityCore::$eCache = false; // this uses memory but speeds things up when a device has to be looked up multiple times.
	EntityCore::$epCache = false; // caches entity profiles. Serialization overhead is bigger than any performance benefit.
	EntityProfileCore::$useCache = false; // caches EPs. Serialization overhead is bigger than any performance benefit.
	EntityProfileCore::$qdtCache = false; // this caches EP trees rather than EPs. Serialization overhead is bigger than any performance benefit.
	EntitySigCollection::$useCache = false; // caches composite trees. Serialization overhead is bigger than any performance benefit.
	
	
	/*************************************************** Miscellaneous flags ******************************************************/
	
	/* streamSigGroups is a useful flag in regular web app usage. What it does is only bring in groups of detection
	   signatures when they're needed, instead of loading the whole load in at once as it used to. This means that iOS, MSIE and Android 
	   detections are performed without having to load all the signatures in.
	   
	   In general there's no practical reason for setting this to "false".	*/	
	DetectorCore::$streamSigGroups = true; // load sig groups as they're required rather than at initialization.
	

	DetectorCore::$getSigGroupsFromLookupTable = false; // if we're keeping whole cached object versions of the SigGroups in the lookup, get them from there.
	
	DetectRight::$sqliteEnvironmentConfirmed = false; // set this to true if you're using SQLite and you're absolutely sure that PDO is correctly installed.
	
	/**
	 * Uncomment the below line if you want DetectRight to be very strict about
	 * recognition/non-recognition: this may also reject web browsers.
	 */
	//DetectRight::$deviceNotFoundBehavior="Exception";
