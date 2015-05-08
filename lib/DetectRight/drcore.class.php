<?php
/******************************************************************************
Name:    drcore.php (was detectright.php)
Version: 2.8.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License
Agreement, the latest version of which can be found at

http://www.detectright.com/legal-and-privacy.html
http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance,
		for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

The summary here is intended to highlight some parts of the agreement, but is only
an illustration and does not itself form part of the agreement itself.

The agreement does not contain any redistribution rights for the software (whether modified or unmodified),
but it does allow modification of the source code by the end-user, with the understanding that
modified builds of the software are not supported by DetectRight without a premium support license.

For license fees for commercial use, please check the current rates and offers at
http://www.detectright.com/device-detection-products.html

The database file accessed by this API should be downloaded solely from
http://www.detectright.com through the user control panel (free registration).

Your fair use and other rights are in no way affected by the above.

v2 change summary
2.0.0 - rewrite to make "schema" optional.
2.1.0 - support for flatter databases, bug fixes.
2.1.1 - added "booleansAsString" option setting for what you really need booleans as booleans
2.1.1 - various tidyups
2.1.2 - Remedial work on override code, another static variable, important changes
in Entity Sig Collection to bring profile generation up to Java. Changes to EntityAlias
and HTTP Headers.
2.2.0 - HTTP Header change to language processing, changes to isMobile and uaImportance,
minor changes to boolean handling in Quantum Data Tree.
2.2.0 - minor changes to fileTest function handling booleans.
2.2.1 - Additional changes to SQLite verification and handling of "should be array but isn't" situations here and there.
2.2.2 - Minor change to allow HTTPHeadersCore objects to be passed to the detection.
2.2.3 - Change to state object to change default importance
2.2.4 - Added patch to allow Tablets to be forced to portrait, and added content width mod.
2.3.0 - added DRProfile Object. Added code to stop nominative entity detection from proxy UAs.
2.3.0 - minor bug in datapoint merge found
2.3.1 - minor x/y bug in screensize code.
2.3.1.1 - add "enabledUserAgentEntityType" - turned off, makes system always spit out device type model names instead of the user agent.
2.3.1.1 - add "type" as a field into the end profile (its absence was an error). Add in extra postprocessing to read it.
2.3.1.1 - added "load database into memory" code.
2.3.2 - altered State object so that members are public, so that the crucial stateFromString function is sped up.
2.5.0 - harmonized getProfileFromUA and getPRofileFromHeaders and UACache stuff to follow same logic as Java and .NET
2.5.0 - added Heap Cache for batch processing, should improve speed on log processing
2.5.0 - many other changes.
2.7.0 - cache additions and changes. "Memcached" connect string should now be "DRMemcached" because of possible conflicts with one of the two PHP memcache extensions
2.7.0 - changed other classnames to minimise chance of conflict
2.7.1 - added $dpAsOS flag and code to use it. Had to add missing function to EntitySigCollection
2.8 - Refactored code in to divorce actual code from monolithic DetectRight object, to allow use with the extended core and enterprise code. That means that this object can be extended by the "servercore" master DetectRight.
2.8 - added "sqliteEnvironmentConfirmed" flag: set to true, this bypasses feature checks, such as availability of PDO drivers. Defaults to false (obviously).
2.8 - added a Rolling Cache object to the codebase, use it for stashing entity descriptors.
******************************************************************************/

Class DRCore {

	// is this using flat trees? The DB would tell us this usually.
	static $flat = false;
	static $sqliteEnvironmentConfirmed = false;
	// does DR return booleans in a profile as "true" or "false", or true/false booleans?
	static $booleansAsString = true;
	static $dpAsOS = true; // if this is true, postprocessing will move anything in DP to OS.
	static $forcePortraitTablets = true; // for Express use, yes.
	static $disableOverrides = false;
	static $enableUserAgentEntityType = true;
	static $expressMode = true;
	
	const VERSION = "2.8.0";
	
	/**
	 * Set this to true to reload DB stuff if dbCache is on.
	 *
	 * @var boolean
	 */
	static $dbFlush=false;
	
	/**
	 * This is unused in this build, but you could use it yourself.
	 *
	 * @var integer
	 */
	static $access_level=1;
	
	/**
	 * If set to true, suffices values in a returned profile
	 * with a notifier of their status in relation to overrides.
	 *
	 * @var boolean
	 */
	static $overrideHighlight = false;
	
	/**
	 * If set to true, non-detections will feature as as browser object
	 *
	 * @var boolean
	 */	
	static $userAgentsAsBrowsers = false;
	
	/**
	 * This can be either "Adaptation" or "Exception". 
	 * If a device isn't found in the database, DR can either produce a nice 
	 * fresh steaming hot pile of data anyway, or throw a nasty little exception,
	 * full of spiky bits.
	 *
	 * @var string
	 */
	static $deviceNotFoundBehavior = "Adaptation";
	
	/**
	 * Do we treat readers as Tablets?
	 *
	 * @var boolean
	 */
	static $readersAsTablets = false;
	
	/**
	 * IF DIAG is no, Checkpoints fill.
	 *
	 * @var boolean
	 */
	static $DIAG = false;
	
	/**
	 * Path to home dir on server. Filled in detectright.boot.php.
	 *
	 * @static string
	 * @access public
	 */
	static $HOME;

	/**
	 * Create internal memory log? Normally only for testing
	 *
	 * @static boolean
	 * @access public
	 */
	static $LOG = 0;

	/**
	 * This will hold the default system link.
	 * This is copied into objects if they don't have any other link.
	 *
	 * @static string
	 * @access public
	 */
	static $dbLink;

	/**
	 * This will hold the default system cache
	 *
	 * @static Cache
	 */
	static $cacheLink;
	
	/**
	 * Do we use the UserAgent cache?
	 *
	 * @var boolean
	 */
	static $uaCache=true;
	
	/**
	 * Do we keep a memory log of queries? This is REALLY expensive 
	 * in terms memory, and is only a development option. This setting
	 * only applies to the default link, and is set separately 
	 * for other DB links.
	 *
	 * @static boolean
	 * @access public
	 */
	static $logQueries=0;

	/**
	 * Stop flag: set in one part of the code for conditional breakpointing in another
	 *
	 * @static boolean
	 * @access public
	 */
	static $stopFlag=false;
				
	/**
	 * DetectRight Classes currently loaded
	 *
	 * @static string
	 * @access public
	 */
	static $classes;
		
	/**
	 * User registered by the system
	 *
	 * @static string
	 * @access public
	 */
	private static $user;
	
	/**
	 * List of checkpoints
	 *
	 * @static string[]
	 * @internal 
	 * @access public
	 */
	static $checkPoints = array();
	
	/**
	 * Unix Timestamp of start time
	 *
	 * @static timestamp
	 * @internal 
	 * @access public
	 */
	static $startTime;
	
	/**
	 * Unix Timestamp of previous time
	 *
	 * @static timestamp
	 * @internal 
	 * @access public
	 * @todo What?
	 */
	static $prevTime;
	
	/**
	 * Sets the level of logging
	 *
	 * @static integer
	 * @internal 
	 * @access public
	 */
	static $maxCheckPointLevel=999;
	
	/**
	 * If we ever decide to encrypt something, this will be the key!
	 *
	 * @static string
	 * @internal 
	 * @access public
	 */
	static $cryptKey;
			
	/**
	 * What schema is the output going to be?
	 *
	 * @static string
	 * @internal 
	 * @access public
	 */
	static $schema="";
	
	/**
	 * Do we bypass cached data for this access? A combination of "redetect=1" and "usecache=0" would
	 * mean trustMin was fully effective.
	 *
	 * @static boolean
	 * @internal 
	 * @access public
	 */
	static $redetect=false;
	
	/**
	 * Flush reloads the lookup tables: particularly the validation, translation and schema tables.
	 *
	 * @static boolean
	 * @access public
	 * @internal
	 */
	static $flush=false;
		
	/**
	 * Data owner
	 *
	 * @static string
	 */
	static $data_owner="SYSTEM";
	
	/**
	 * When set to true, ignores "owner" in tables. This mode is needed for
	 * shifting data about when both system data and global data have to be moved
	 * (for instance, when cleaning or merging entities, or syncing between
	 * nodes when you want the user data to go too.
	 *
	 * values are:
	 * "SystemUser" - read data for the user and system data together, writes are for the user
	 * "SystemOnly" - system data only, ignore user data. All data writes are owned by "System".
	 * "Global" - read all data
	 * "Local" - only deal with user data
	 * 
	 * @static boolean
	 */
	static $data_mode="SystemUser";
		
	/**
	 * Current username
	 *
	 * @static string
	 * @internal 
	 * @access public
	 */
	static $username="";
			
	/**
	 * Holds any error messages
	 *
	 * @static string
	 * @internal 
	 * @access public
	 */
	static $error="";
		
	/**
	 * What method do we use to log stuff?
	 *
	 * @static string
	 * @access public
	 * @internal
	 */
	static $LOG_METHOD="echo";

	/**
	 * Holds the object containing the last detection, so we can do more to it.
	 *
	 * @var EntitySigCollection
	 */
	static public $lastDetection; // last ESC.
	
	/**
	 * Holds the last intelligent header object, so we can do more to it.
	 *
	 * @var HTTPHeadersCore
	 */
	static public $lastHeaders; // lastHeaderObject
	
	public static function version() {
		return VERSION;
	}
	/**
	 * Tests the default DetectRight database.
	 *
	 * @return String[]
	 */
	public static function testDB() {
		$output = array();
		$dbLink = self::$dbLink;
		if (is_null($dbLink)) throw new ConnectionLostException("Database link is null");
		$tables = $dbLink->getTables();

		foreach ($tables as $table) {
			$testStr = "Table ".$table;
			$query = "select count(*) as {idd}cnt{idd} from {idd}$table{idd}";
			$rs = $dbLink->query($query);
			if ($rs == false || is_null($rs)) {
				$testStr = $testStr." couldn't read table";
			} else {
				$row = $dbLink->fetch_assoc($rs);
				if (is_null($row) || count($row) == 0) {
					$testStr = $testStr." - No data could be read";
				} else {
					$cnt = $row['cnt'];
					$testStr = $testStr." has ".$cnt." rows";
				}
			}
			$output[] = $testStr;
			if (is_resource($rs)) $dbLink->free_result($rs);
		}

		$entity = EntityCore::getEntityFromCatDesc("Apple","iPhone");
		if ($entity === null || $entity->ID < 1) {
			$output[] = "ERROR GETTING IPHONE";
		} else {
			$output[] = "Got iPhone successfully";
		}
		return $output;
	}

	/**
	 * Gets the Entity Sig Collection of the last detection run.
	 * This allows us to detect once, and query many times.
	 *
	 * @return EntitySigCollection
	 */
	public static function getLastDetection() {
		$obj = self::$lastDetection;
		if (is_object($obj)) return $obj;
		return new EntitySigCollection();
	}

	/**
	 * Gets the headers contributing to the last detection run, in case we want to do further clever stuff with
	 * connections, languages, etc.
	 * @return HTTPHeaderCore
	 */
	public static function getLastHeaders() {
		$headers = self::$lastHeaders;
		return $headers;
	}
	
	/**
	 * Removes the last detection we did, clearing up.
	 *
	 */
	static function removeLastDetection() {
		if (is_object(self::$lastDetection)) {
			self::$lastDetection->close();
		}
		if (is_object(self::$lastHeaders)) {
			if (is_object(self::$lastHeaders->esc)) {
				self::$lastHeaders->esc->close();
				unset(self::$lastHeaders->esc);
			}
			if (is_object(self::$lastHeaders->qdt)) {
				self::$lastHeaders->qdt->close();
				unset(self::$lastHeaders->qdt);
			}
		}
		self::$lastHeaders = null;
		self::$lastDetection = null;
	}

	/**
	 * Allows the user to determine whether to set the isTablet field for readers
	 *
	 * @param boolean $status
	 */
	static function setReadersAsTablets($status) {
		self::$readersAsTablets = $status ? true : false;
	}
	
	/**
	 * Returns the current Database link object.
	 *
	 * @return DBLink (or compatible)
	 */
	static function getDBLink() {
		return self::$dbLink;
	}
	
	/**
	 * Slightly more controlled way of setting the DBLink.
	 *
	 * @param DBLink $db
	 */
	static function setDBLink(DBLink $db) {
		if (is_null($db)) return;
		if (is_null($db->db)) return;
		if (!$db->dbOK) return;
		self::$dbLink = $db;
	}
	
	/**
	 * Getting the cache link.
	 *
	 * @return Cache (or compatible);
	 */
	static function getCacheLink() {
		return self::$cacheLink;
	}
	
	/**
	 * Sets the cache link for this baby...
	 *
	 * @param Cache $cache
	 */
	static function setCacheLink(Cache $cache) {
		if (is_null($cache)) return;
		self::$cacheLink = $cache;
	}
	
	/**
	 * Removes and override for adevice
	 *
	 * @param string $deviceId
	 * @param string $key
	 * @return boolean
	 * @see addTemporaryOverride
	 */
	static function removeTemporaryOverride($deviceId,$key) {
		return self::addTemporaryOverride($deviceId,$key,"");
	}
	
	/**
	 * Handles adding a temporary override to the current main DBLink.
	 *
	 * It's inevitable that in production, there will be a case when a device
	 * has a wrong item of data. What's more, it will be an important device, and
	 * there will be a lot of money riding on being able to fix the problem. Or,
	 * you might want to be adding a special field dynamically, for a promotion or something.
	 * At some point, this might well save your bacon, or other meaty repast, as is your preference.
	 *
	 * This function simply allows you to append any arbitrary data value to any deviceId in the database.
	 *
	 * This function will fail if it doesn't have write access to the database table
	 *
	 * @param string $deviceId String of device ID/entity hash to put data against
	 * @param string $key String name of data to add
	 * @param string $value String value of data to add. If blank, removes the database entry.
	 * @return boolean success or failure
	 * @see removeTemporaryOverride
	 * @throws DeviceNotFoundException
	 */
	static function addTemporaryOverride($deviceId,$key,$value) {
		if (DRFunctionsCore::isEmptyStr($deviceId) || DRFunctionsCore::isEmptyStr($key)) return false;
		$entity = EntityCore::get(0,$deviceId);
		if (!is_object($entity) || $entity->id() < 1) throw new DeviceNotFoundException("Device $deviceId doesn't exist");
		
		$mode = "add";
		if (DRFunctionsCore::isEmpty($value)) {
			$mode = "remove";
		}
		
		$result = self::$dbLink->getIDs("entity_overrides","ID",array("entityid"=>$entity->id(),"key"=>$key));
		
		$ID = -1;
		if (!DRFunctionsCore::isEmpty($result)) {
			$ID = array_shift($result);
			if ($ID > 0) {
				if ($mode !== "remove") {
					$success = self::$dbLink->updateData("entity_overrides",array("value"=>$value),array("ID"=>$ID));
				} else {
					$success = self::$dbLink->deleteData("entity_overrides",array("ID"=>$ID));
				}
			}
			return $success;
		}
		
		// adding.  The SQLite table doesn't have an increment on it so we have to do a non-thread-safe thing
		$ID = 1;
		
		$sql = "select max({idd}ID{idd}) as {idd}maxid{idd} from {idd}entity_overrides{idd}";
		$result = self::$dbLink->query($sql,false);
		if (!DRFunctionsCore::isEmpty($result)) {
			$row = array_shift($result);
			$maxid = $row['maxid'];
			if (DRFunctionsCore::isEmpty($maxid))  {
				throw new DetectRightException("Recordset doesn't include specified column");
			}
			$ID = $maxid + 1;
		} else {
			// we've got nothing. Make ID 1 and let the insert pick up any error.
		}
		
		$data = array();
		$data['ID'] = $ID;
		$data['entityid'] = $entity->id();
		$data['entityhash'] = $entity->hash();
		$data['key'] = $key;
		$data['value'] = $value;
		$data['ts'] = self::$dbLink->ts2Date(time());
		$success = self::$dbLink->insertData("entity_overrides",$data,$ID);
		return ($success !== false);
	}
	
	/**
	 * Get the DetectRight's behaviour on this thread if a detection doesn't find a database entity.
	 *
	 * There are two possible behaviours. The default is "Adaptation", the other is "Exception".
	 *
	 *
	 * <b>Adaptation:</b>
	 * DetectRight's normal mode of operation is to create adaptive profiles based on components it detects
	 * from a useragent or header set (Adaptation). So it's possible to build a profile about a device or
	 * browser even if it's not in the database.
	 *
	 * Consider this fictional useragent:
	 * "Mozilla/5.0 (compatible; U; NokiaN678; Series60/3.1; SymbianOS/9.2; Profile/MIDP-2.0) Version/4.0.1 MobileSafari/6233
	 * then we know many of the component parts of this device, as well as knowing that it's a "Nokia N678".
	 * DetectRight in this case would build a profile from all these bits (with no data being available for the
	 * N678, since it doesn't exist).
	 *
	 * It would combine this data with any other dynamic data in the header (accept strings, languages, encodings, etc),
	 * and throw back a plausible profile. The correctness of the screensize would be dependent on how much the device
	 * differed from the defaults in the Series 60 data, but overall the user would get the right version of the website,
	 * along with appropriate media support.
	 * This behaviour is enabled by default or by {@link #adaptiveProfileOnDeviceNotFound}
	 *
	 * <b>Exception:</b>
	 * DetectRight would generate a {@link #DeviceNotFoundException} as soon as it realised that the Nokia N678 was outside the scope fo the databas
	 * This behaviour is enabled by {@link #generateExceptionOnDeviceNotFound}
	 *
	 * @return String representing the desired behaviour.
	 *
	 *
	 */
	static function deviceNotFoundBehavior() {
		if (DRFunctionsCore::isEmpty(self::$deviceNotFoundBehavior)) self::$deviceNotFoundBehavior = "Adaptation";
		return self::$deviceNotFoundBehavior;
	}

	/**
	 * Generate a DeviceNotFoundException if the detected device is not in the database.
	 *
	 * @see deviceNotFoundBehavior
	 */
	public static function generateExceptionOnDeviceNotFound() {
		self::$deviceNotFoundBehavior = "Exception";
	}

	/**
	 * Generate an adaptive profile if the detected device is not in the database.
	 *
	 * @see deviceNotFoundBehavior
	 */
	public static function adaptiveProfileOnDeviceNotFound() {
		self::$deviceNotFoundBehavior = "Adaptation";
	}

	/**
	 * Connects a data-aware object to a database link at a static level. This allows different
	 * DetectRight objects to target completely different data sources.
	 *
	 * @param string $classname
	 * @param string $connectString
	 * @param string $queryCacheing
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function addDataEngine($classname = "",$connectString = "",$queryCacheing = false) {
		if (!class_exists($classname,true)) throw new DetectRightException("Class $classname doesn't exist");
		$dbLink = DBLink::getConnection($connectString);
		if (!$dbLink->dbOK) throw new ConnectionLostException("Connect String $connectString is invalid");
		$refClass = new ReflectionClass($classname);
		try {
			$refClass->setStaticPropertyValue ( "dbLink" , $dbLink );
			$refClass->setStaticPropertyValue( "useCache", $queryCacheing);
		} catch (Exception $e) {
			throw new DetectRightException("$classname can't accept database link $connectString",$e);
		}
	}
	
	
	/**
	 * Initialize the system, with caches and DBLinks. Do this once per thread entry.
	 *
	 * @param string $username	Optional, and mostly unused.
	 * @access public
	 * @throws ConnectionLostException
	 */
	public static function initialize($dbString = "", $copyToMemory = false, $username = "Guest") {
		self::$startTime = mt();
		self::$username = $username;
		
		EntityCore::$descriptors = new DRRollingCache(500,true); // this is supposed to speed things up a fair bit. I'm not sure it does though!
		
		if ($dbString) {
			DBLink::$connections['DetectRight'] = $dbString;
		}
		DRCache::init();
		DBLink::init($copyToMemory);

		if (DetectRight::$dbLink->dbOK) {
			if (DetectRight::$dbLink->getOption("eptype") === "flat") {
				DetectRight::$flat = true;
			} else {
				DetectRight::$flat = false;
			}
		}

		if (!PointerCore::$dbLink) PointerCore::$dbLink = self::$dbLink;
		if (!SigGroup::$dbLink) SigGroup::$dbLink = self::$dbLink;
		if (!DetectorCore::$dbLink) DetectorCore::$dbLink = self::$dbLink;
		if (!PointerCore::$cacheLink) PointerCore::$cacheLink = self::$cacheLink;
		if (!EntityCore::$dbLink) EntityCore::$dbLink = self::$dbLink;
		if (!EntityCore::$cacheLink) EntityCore::$cacheLink = self::$cacheLink;
		if (!EntityAliasCore::$dbLink) EntityAliasCore::$dbLink = self::$dbLink;
		if (!EntityAliasCore::$cacheLink) EntityAliasCore::$cacheLink = self::$cacheLink;
		if (!EntityProfileCore::$cacheLink) EntityProfileCore::$cacheLink = self::$cacheLink;
		if (!EntityProfileCore::$dbLink) EntityProfileCore::$dbLink = self::$dbLink;
		if (!Validator::$dbLink) Validator::$dbLink = self::$dbLink;
		if (!Validator::$cacheLink) Validator::$cacheLink = self::$dbLink;
		if (!SchemaPropertyCore::$dbLink) SchemaPropertyCore::$dbLink = self::$dbLink;
		if (!SchemaPropertyCore::$cacheLink) SchemaPropertyCore::$cacheLink = self::$dbLink;

		$lhm = DetectRight::$dbLink->getArray("NominativeEntityTypes");
		if (is_array($lhm) && in_array("Device",$lhm)) EntityCore::$nominativeEntityTypes = $lhm;

		DetectorCore::loadSigs_all();

		if (!is_object(self::$dbLink) || !self::$dbLink->dbOK || get_class(self::$dbLink) === "DBLink") {
			throw new ConnectionLostException(self::$dbLink->error);
		}
	}
	
	/**
	 * Does a class exist in the currently loaded classes?
	 *
	 * @param string $class
	 * @return boolean
	 * @static
	 * @internal 
	 * @access public
	 */
	static function classExists($class) {
		if (!isset(self::$classes[$class])) return false;
		return true;
	}

	/**
	 * Register a class when loaded
	 *
	 * @param string $class
	 * @static 
	 * @access public
	 * @internal
	 */
	static function registerClass($class) {
		//echo "$class\n";
		//ob_flush();
		self::$classes[]=$class;
	}

	/**
	 * Logging
	 *
	 * @param string $str
	 * @param integer $level
	 * @static 
	 * @access public
	 * @internal
	 */
	static function checkPoint($str="",$level=0) {
		if (!self::$DIAG) return;
		if ($level >0 && self::$maxCheckPointLevel > 0 && $level > self::$maxCheckPointLevel ) return;
		$thisTime=mt();
		if (is_null(self::$prevTime)) self::$prevTime=self::$startTime;
		$delta = $thisTime - self::$prevTime;
		if ($delta > 0.00) {
			$checkPoint['delta']=$thisTime-self::$prevTime;
			$checkPoint['elapsed']=$thisTime-self::$startTime;
			$checkPoint['comment']=$str;
			self::$checkPoints[]=$checkPoint;
			self::$prevTime=mt();
		} else {
			self::$checkPoints[]=$str;
		}
	}

	/**
	 * Print checkpoints
	 *
	 * @static 
	 * @access public
	 * @internal
	 */
	static function printCheckpoints() {
		$cp = DetectRight::$checkPoints;
		if (!is_array($cp)) return;
		foreach ($cp as $array) {
			extract($array);
			echo $elapsed.":".$comment." (".$delta.")\n";
		}
	}
	
	static function addOwner($array=array()) {
		$owner = self::$data_owner;
		if (!$owner) $owner = "SYSTEM";
		if (!is_array($array)) $array = array();

		if ($owner === "SYSTEM") {
			$array['owner'] = "SYSTEM";
		} else {
			$owners = array("SYSTEM",$owner);
			$array['owner'] = array("op"=>"in","value"=>$owners);
		}
		return $array;
	}
	
	static function owner() {
		return self::$data_owner;
	}
			
	/**
	 * Housekeeping prior to thread exit.
	 */
	static function clear() {
		self::removeLastDetection();		
		self::$checkPoints = array();
	}
	
	/**
	 * Closing out.
	 *
	 */
	static function close() {
		self::$dbLink->close();
		self::$cacheLink->cache_close();
		self::$username="Anonymous";
		self::$access_level = 1;
		self::clear();
	}
	
	static function canLearn() {
		return 0;
	}
	
	/**
	 * Generate a cache key to prevent cache key overwriting in the same memcache
	 *
	 * @param string $key
	 * @return string
	 */
	static function cacheKey($key) {
		$key = "DRPHP:"."/".$key;
		return $key;
	}
	
	/**
	 * Descriptors on DR are separated by colons. It's important therefore to weed out the colons
	 * in the constituent parts, and also backslashes, which are used in data paths.
	 * 
	 * @param String $string
	 * @return String
	 * @see unescapeDescriptor
	 */
	static function escapeDescriptor($string) {
		if (!$string) return $string;
		$string = str_replace("//","{df}",$string);
		$string = str_replace(":","{colon}",$string);
		$string = str_replace("\\","{backslash)",$string);
		return $string;
	}
	
	/**
	 * Descriptors on DR are separated by colons. It's important therefore to weed out the colons
	 * in the constituent parts, and also backslashes, which are used in data paths.
	 * 
	 * @param String $string
	 * @return String
	 * @see escapeDescriptor
	 */	
	static function unescapeDescriptor($string) {
		if (!$string) return $string;
		if (strpos($string,"{") === false) return $string;
		$string = str_replace("{df}","//",$string);
		$string = str_replace("{colon}",":",$string);
		$string = str_replace("{backslash}","\\",$string);
		return $string;
	}
	
	static function recordAccess($string) {
		// do nothing. Seriously. Nothing to do here. Move along. Nothing to do here.
	}

	static function stop() {
		// dummy function. Put a breakpoint here, and you might be set!
		$a = 0;
		return $a;
	}
	

	/**
	 * Get a profile for a schema from a full header set
	 *
	 * @param associative_array $lhm
	 * @param string $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
    public static function getProfileFromHeaders($lhm=array(),$schema="") {
    	if (DRFunctionsCore::isEmpty($lhm)) return array();
    	if (!is_array($lhm) && !is_object($lhm)) return array();
    	if (!is_object($lhm)) {
    		$headers = new HTTPHeadersCore($lhm);
    	} else {
    		$headers = $lhm;
    	}
    	try {
   			$profile = self::checkUACache($headers->uid);
    			if ($profile) {
   				self::$lastHeaders = $headers;
    				return $profile;
    			}
			if (!self::detect($lhm)) return array();
			$result = self::getProfile($schema);
			self::addToUACache($headers->uid,$result);
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to detect ",$e);
		}
		return $result;	
	}

	/**
	 * Get a profile for a schema from a useragent. Also works with TAC codes, oddly.
	 *
	 * @param String $useragent
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	// DetectRight Object in Server fix
	public static function getProfileFromUA($useragent) {
		if (DRFunctionsCore::isEmpty($useragent)) return array();
		$cacheResult = null;
		if (DetectRight::$expressMode)
		{
			$cacheResult = self::checkUACache($useragent);
			if ($cacheResult !== null) {
				return $cacheResult;
			}
		}

		$httpHeaders = new HTTPHeadersCore(array("HTTP_USER_AGENT"=>$useragent));

		// Take a look in the UA cache and see if we're already in there
		if (!DetectRight::$expressMode)
		{
			$profile = self::checkUACache($httpHeaders->uid);
					if ($profile !== false && !DRFunctionsCore::isEmpty($profile)) {
						return $profile;
					}
				}

		try {
			// Since we're not in the UA cache, go ahead and detect
			if (!self::detect($httpHeaders)) {
				$result = array();
			} else {
				$result = self::getProfile();
			}

			// Add to the UA cache if neccessary
			if (!DetectRight::$expressMode) {
				self::addToUACache($httpHeaders->uid, $result);
			} else {
				self::addToUACache($useragent, $result);
			}
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to detect $useragent",$e);
		}
		return $result;
	}

	private static function addToUACache($thingToAdd, $result) {
		if (!$thingToAdd) return;
		if (is_object($thingToAdd)) {
    		$key = $thingToAdd->uid;
		} else {
			$key = $thingToAdd;
		}
		self::addResultToUACache($key,$result);
	}
	
	private static function addResultToUACache($key, $result) {
		if (!$result) return;
		if (self::$uaCache) {
			self::$cacheLink->cache_set($key,$result,3600);
		}		
	}

	private static function checkUACache($thingToCheck) {
		if (!$thingToCheck) return null;
		if (is_object($thingToCheck)) {
			$key = $thingToCheck->uid;
		} else {
			$key = $thingToCheck;
		}
		return self::getResultFromUACache($key);
	}
	
	private static function getResultFromUACache($key) {
		if (!self::$uaCache) return null;
		if (DetectRight::$redetect) return null;
		if (!$key) return null;
		return self::$cacheLink->cache_get($key);
	}

	/**
	 * Retrieve a profile from a Device ID: that's a 32 char hash based on the
	 * entity type, manufacturer and model. This needn't be cached.
	 *
	 * @param String $hash
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromDeviceID($hash,$schema="") {
		try {
			if (!self::detectDevice($hash)) return array();
			$return = self::getProfile($schema);
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to get Device Id $hash",$e);
		}
		return $return;
	}

	/**
	 * Retrieve a profile from the
	 * entity type, manufacturer and model of a device.
	 * Use "Device" as a catch-all.
	 *
	 * @param String $hash
	 * @param String $schema
	 * @return associative_array
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromDevice($entitytype,$category,$description,$schema="") {
		try {
			$entity = EntityCore::getEntityFromCatDesc($category,$description,$entitytype,false);
			if (!is_object($entity)) throw new DeviceNotFoundException("Didn't find $entitytype $category $description");
			$hash = $entity->hash;
			$return = self::getProfileFromDeviceID($hash,$schema);
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DetectRightException $dre) {
			throw $dre;
		} catch (Exception $e) {
			throw new DetectRightException("Failed to detect $entitytype $category $description",$e);
		}
		return $return;
	}

	/**
	 * Gets a profile from an IMEI/TAC number
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromTAC($tac,$schema) {
		if (DRFunctionsCore::isEmpty($tac)) throw new DeviceNotFoundException("Empty TAC!");
		if (!is_numeric($tac)) return $tac;
		if (strlen($tac) > 8) $tac = substr($tac,0,8);
		$tacESC = PointerCore::getESC("TAC",md5($tac));
		if (count($tacESC->entities) == 0) {
			throw new DeviceNotFoundException("TAC $tac Not Found");
		}
		self::processDetection($tacESC);
		return self::getProfile($schema);
	}

	/**
	 * Gets a profile from a Phone ID: that is, an arbitrary text string that might be 
	 * used in other systems to identify a device.
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromPhoneID($id,$schema) {
		if (DRFunctionsCore::isEmpty($id)) throw new DeviceNotFoundException("Empty ID!");
		$phoneIDESC = PointerCore::getESC("PhoneID",md5($id));
		if (count($phoneIDESC->entities) == 0) {
			throw new DeviceNotFoundException("Phone ID $id Not Found");
		}
		self::processDetection($phoneIDESC);
		return self::getProfile($schema);
	}

	/**
	 * Gets a profile from a UAProfile URL. This kind of gets done implicitly during a 
	 * detection, but this is a quicker way if all you've got is the UAProfile URL.
	 *
	 * @param mixed $tac
	 * @param String $schema
	 * @return associative_array
	 * @throws DeviceNotFoundException
	 */
	public static function getProfileFromUAProfile($uap,$schema) {
		if (DRFunctionsCore::isEmpty($uap)) throw new DeviceNotFoundException("Empty URL!");
		$url = DRFunctionsCore::cleanURL($uap);
		$uapESC = PointerCore::getESC("UAP",md5($url));
		if (count($uapESC->entities) == 0) {
			throw new DeviceNotFoundException("UAProfile link $uap Not Found");
		}
		self::processDetection($uapESC);
		return self::getProfile($schema);
	}

	/**
	 * Just a quickie to give you the last manufacturer/category we detected.
	 *
	 * @return String
	 */
	public static function getDetectedManufacturer() {
		$lastDetection = self::getLastDetection();
		if (!is_object($lastDetection)) return "";
		return $lastDetection->getManufacturer();
	}

	/**
	 * Just a quickie to give you the last model we detected.
	 *
	 * @return String
	 */
	public static function getDetectedModel() {
		$lastDetection = self::getLastDetection();
		if (!is_object($lastDetection)) return "";
		$nom = $lastDetection->getNominativeEntity();
		if (is_null($nom)) return "";
		return $nom->description;
	}
		
	/**
	 * Gets a detection for a device in the database based on the deviceId/entity hash.
	 *
	 * @param mixed $id String or integer corresopnding to entity.hash/id column in DetectRight database
	 * @return boolean success or failure
	 * @see EntitySigCollection
	 * @throws DeviceNotFoundException
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function detectDevice($id) {
		if (!is_object($id)) {
			$entity = new EntityCore($id);
			if (!is_object($entity)) {
				if (is_numeric($id) && strlen($id < 32)) {
					$entity = EntityCore::get($id);
				} else {
					$entity = EntityCore::get(0,$id);
				}
			
				if ($entity === null || !is_object($entity) || $entity->id() == 0)	{
					// absolutely no point in doing adaptation for this, there's no device!
					throw new DeviceNotFoundException("Device $id not found");
				}
			} else {
				if ($entity->id() < 0) throw new DeviceNotFoundException("Invalid entity object passed in");
			}
		} else {
			$entity = $id;
			$id = $entity->ID;
		}
		
		$esc = new EntitySigCollection();
		$esc->addEntity($entity);
		//esc.filter();
		// resolve the manifest from the contain chains of each of its entities by going through them
		// and adding them to the ETS. Components which have been superceded in some way will be rejected.
		$esc->addEntityContains();
		DetectRight::checkPoint("Getting QDT");
		$esc->getQDT();
		//System.out.println(esc.qdt.getPackages());
		$esc->qdt->processPackages();
		self::$lastDetection = $esc;
		return true;
	}

	/**
	 * In the Java, this is another set of arguments to the main detectDevice function
	 *
	 * @param String $manufacturer
	 * @param String $description
	 */
	public static function detectDeviceByManModel($manufacturer,$description) {
		$entity = EntityCore::getEntityFromCatDesc($manufacturer,$description);
		if (!is_object($entity) || $entity->id() > 0) throw new DeviceNotFoundException("Cannot find $manufacturer $description");
		return self::detectDevice($entity);
	}
	
	/**
	 * Runs a detection on either a pre-prepared HTTPHeaders object, or a raw $_SERVER
	 * type array. Produces an EntitySigCollection, which is a special object containing
	 * the list of all detected components and their versions.
	 *
	 * @param mixed $headers Associative array or HTTPHeadersCore/HTTPHeaders object
	 * @return EntitySigCollection
	 */
	public static function detect($headers)  {
		if (DRFunctionsCore::isEmpty($headers)) return false;
		if (is_array($headers)) {
			$httpHeaders = new HTTPHeadersCore($headers);
		} elseif (is_object($headers) && substr(get_class($headers),0,11) === "HTTPHeaders") {
			$httpHeaders = $headers;
		} else {
			return array();
		}
		
		
		// the next bit is an adjustment where we delete any of the added EntitySigs if they're already
		// in the appropriate entity or its contain chain. This ought to filter out much of the crap.
		// if the entities are already in the chain, leave them for the version number.
		try {
			$httpHeaders->process();
			$result = self::processDetection($httpHeaders->getESC());
			self::$lastHeaders = $httpHeaders;
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		}
		return $result;
	}

	/**
	 * The second bit of the detection: we've got something, and now we process it to add
	 * contained entities, and other stuff.
	 *
	 * @param EntitySigCollection $esc
	 * @return boolean
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function processDetection(EntitySigCollection $esc) {
		//$esc->filter();
		// resolve the manifest from the contain chains of each of its entities by going through them
		// and adding them to the ETS. Components which have been superceded in some way will be rejected.
		self::clear();
		try {
			$esc->addEntityContains();
			DetectRight::checkPoint("Getting QDT");
			$esc->getQDT();
			DetectRight::checkPoint("Got QDT");
			//System.out.println(esc.qdt.getPackages());
			$esc->qdt->processPackages();
			$esc->resetRootEntity();
			self::$lastDetection = $esc;
		} catch (DeviceNotFoundException $dnfe) {
			throw $dnfe;
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		}
		return true;
	}
	

	/**
	 * Gets a single datapoint from a detection. One use case of DetectRight
	 * is doing the detection and then only getting the data you're interested in. Much
	 * quicker!
	 *
	 * @param String $schema
	 * @param String $fieldname
	 * @return mixed
	 */
	public static function getDatapoint($fieldname,$schema) {
		$lastDetection = self::getLastDetection();
		if (DRFunctionsCore::isEmpty($lastDetection)) return "";
		$sp = SchemaPropertyCore::getSchemaProperty($fieldname,$schema);
		$importances = array();
		$valueObj = SchemaPropertyCore::getObjectValue($lastDetection->qdt,$sp,$importances);
		return $valueObj;
	}

	/**
	 * Get a profile from the last detection done. This avoids doing the whole 
	 * detection process again, including building the trees (which is the expensive bit).
	 * If you have a combined database (for instance, WURFL/W3C such as the ones you get with
	 * the data upgrade), then you can run profiles for each of them without redetecting.
	 *
	 * @param String $schema
	 * @return associative_array of key/value pairs.
	 */
	public static function getProfile($schema="") {
		$lastDetection = self::getLastDetection();
		$profile = $lastDetection->getExportProfile($schema);
		$profile['schema'] = $schema;
		self::postProcess($profile);
		return $profile;
	}
	
	/**
	 * Makes last minute changes to the profile, after the overrides have been added, so use with caution.
	 *
	 * @param associative_array $profile
	 */
	public static function postProcess(&$profile) {
		if (!$profile) return;
		$headers = self::getLastHeaders();
		$esc = self::getLastDetection();
		$nom = null;
		if (!is_null($esc)) {
			$nom = $esc->getNominativeEntity();
		}
		$ua = "";
		if (!is_null($headers)) {
			$ua = $headers->ua;
		}
		
		
		// this next bit solves an odd problem. Virtually every device supports "HTML 4.0", and has claimed to since the dawn of time.
		// however, historically, this has been a bit of an odd boast, since many browsers can't do proper websites.
		// This corrects for that by pegging something back down to XHTML MP if the device doesn't claim web support.
		// note that non-WURFL profiles are not affected by this. To be honest, the idea of basing your entire
		// serving strategy on "preferred_markup" alone is a bit crappy, since redirection is a function of much more than
		// whether HTML 4.0 is technically supported. "device_claims_web_support" is thus much more useful.	
		if (isset($profile["preferred_markup"])) {
			$prefMarkup = $profile["preferred_markup"];
			if ($prefMarkup === "html_web_4_0") {
				$dcws = DRFunctionsCore::gv($profile,"device_claims_web_support","");
				if ($dcws === "false" || $dcws === "0" || $dcws === 0) {
					$profile["preferred_markup"] = "html_wi_oma_xhtmlmp_1_0";
				}
			}  else if ($prefMarkup === "html_web_5_0") {
				$browserName = DRFunctionsCore::gv($profile, "mobile_browser");
				if ($browserName === "NetFront") {
					$bv = DRFunctionsCore::gv($profile, "mobile_browser_version");
					if ($bv && substr($bv,0,1) === "3") {
						$profile["preferred_markup"] = "html_wi_oma_xhtmlmp_1_1";
					}
				}
			} else if (stripos($prefMarkup,"xhtml") !== false && $profile['type'] === "Desktop") {
				// Generic desktop devices will always support street HTML. It's just what they do.
				$profile['preferred_markup'] = "html_web_4_0";
			}
		}

		if (isset($profile["preferredmarkup"])) {
			$prefMarkup = $profile["preferredmarkup"];
			if ($prefMarkup === "HTML" || $prefMarkup === "HTML 4.0") {
				if (DRFunctionsCore::gv($profile,"isweb","") != "1") {
					$profile["preferredmarkup"] = "XHTML Mobile Profile 1.0";
				}
			} else if ($prefMarkup === "HTML 5.0") {
				$browserName = DRFunctionsCore::gv($profile, "browsername");
				if ($browserName === "NetFront") {
					$bv = DRFunctionsCore::gv($profile, "browserversion");
					if ($bv && substr($bv,0,1) === "3") {
						$profile["preferred_markup"] = "XHTML Mobile Profile 1.1";
					}
				}
			} else if (stripos($prefMarkup,"xhtml") !== false && $profile['type'] === "Desktop") {
				// Generic desktop devices will always support street HTML. It's just what they do.
				$profile['preferredmarkup'] = "HTML 4.0";
			}
		}

		if (isset($profile["markup.preferred"])) {
			$prefMarkup = $profile["markup.preferred"];
			if ($prefMarkup === "HTML" || $prefMarkup === "HTML 4.0") {
				if (DRFunctionsCore::gv($profile,"markup.webcapable","") != "1") {
					$profile["markup.preferred"] = "XHTML Mobile Profile 1.0";
				}
			} else if ($prefMarkup === "HTML 5.0") {
				$browserName = DRFunctionsCore::gv($profile, "browsername");
				if ($browserName === "NetFront") {
					$bv = DRFunctionsCore::gv($profile, "browserversion");
					if ($bv && substr($bv,0,1) === "3") {
						$profile["markup.preferred"] = "XHTML Mobile Profile 1.1";
					}
				}
			} else if (stripos($prefMarkup,"xhtml") !== false && $profile['type'] === "Desktop") {
				// Generic desktop devices will always support street HTML. It's just what they do.
				$profile['markup.preferred'] = "HTML 4.0";
			}
		}

		if ($ua) {
			$tabletOverride = array(
				"Opera/9.02 (Linux armv7l; U; ARCHOS; GOGI; mobile; G6H; Version 1.0.87 (WMDRMPD: 10.1) ; en)",
				"Mozilla/5.0 (Linux; U; Android 1.5; fr-fr; Archos5 Build/CUPCAKE) AppleWebKit/525.10 (KHTML, like Gecko) Version/3.0.4 Mobile Safari/523.12.2"
			);

			if (in_array($ua,$tabletOverride)) {
				$profile['istablet'] = 1;
			}
			
			if (stripos($ua,"ipad") !== false) {
				$profile['istablet'] = 1;
			}

			if (stripos($ua,"tablet") !== false) {
				$profile['istablet'] = 1;
			}

		}
		
		$entitytype = "";
		if ($nom !== null) {
			$entitytype = $nom->entitytype;
			$tabletModels = array(
				"MZ604"
			);
			
			if (in_array($nom->description ,$tabletModels)) {
				$profile['istablet'] = 1;
			}
		}
		
		self::checkScreensizes($profile,$entitytype);
		
		if (function_exists("DRPostProcess")) {
			DRPostProcess($profile);
		}


		// Java fix
		/*if (isset($profile['screendiagonalin'])) {
			$sd = (int) DRFunctionsCore::gv($profile,"screendiagonalin",0);
			if ($sd >= 7) {
				$profile['istablet'] = 1;
			}
		}*/
		
		// if we need to put OS in DP, let's do that:
		if (DetectRight::$dpAsOS && isset($profile['device_dp']) && $profile['device_dp'] !== "Google Android") {
			$dp = $esc->getEntityForEntityType("Developer Platform");
			if ($dp !== null) {
				if (isset($profile["device_os"])) $profile["device_kernel"] = $profile["device_os"];
				if (isset($profile["device_os_version"])) $profile["device_kernel_version"] = $profile["device_os_version"];
				$profile["device_os"] = $dp->description;
				unset($profile['device_os_version']);
				if (!DRFunctionsCore::isEmpty($dp->majorrevision)) {
					$profile["device_os_version"] = $dp->majorrevision;
				}
		}
	}
	}
	
	static function checkScreensizes(&$profile,$entitytype) {
		$modes = array(
		"resolution_width"=>array("resolution_width","resolution_height","max_image_width","max_image_height"),
		"screenx"=>array("screenx","screeny","contentx","contenty"),
		"displayWidth"=>array("displayWidth","displayHeight","usableDisplayWidth","usableDisplayHeight"),
		"displaywidth"=>array("displaywidth","displayheight","usabledisplaywidth","usabledisplayheight"));

		$activeModes = array();

		foreach ($modes as $mode=>$array) {
			if (isset($profile[$mode])) $activeModes[$mode] = $array;
		}

		// now deal with content widths

		foreach ($activeModes as $mode=>$fields) {
			$xField = $fields[0];
			$yField = $fields[1];
			$xcField = $fields[2];
			$ycField = $fields[3];
			$x = DRFunctionsCore::gv($profile,$xField,"-1");
			if (!$x) $x = "-1";
			$y = DRFunctionsCore::gv($profile,$yField,"-1");
			if (!$y) $y = "-1";
			$xc = DRFunctionsCore::gv($profile,$xcField,"-1");
			if (!$xc) $xc = "-1";
			$yc = DRFunctionsCore::gv($profile,$ycField,-1);
			if (!$yc) $yc = "-1";
			try {
				$x = (int) $x;
				$y = (int) $y;
				$xc = (int) $xc;
				$yc = (int) $yc;
			} catch (Exception $e) {
				continue;
			}

			$hasx = ($x > 0);
			$hasy = ($y > 0);
			$hasxc = ($xc > 0);
			$hasyc = ($yc > 0);
			$tmp = 0;
			
			if (DetectRight::$forcePortraitTablets) {
				// forcing
				if ($entitytype === "Tablet") {
					if ($hasx && $hasy && $x > $y) {
						$tmp = $y;
						$y = $x;
						$x = $tmp;
						$profile[$xField] = (string) $x;
						$profile[$yField] = (string) $y;
					}
				}
			}

			// possible conditions (we know we have an entry for the width, but it may not contain anything.
			// should max_image_widths be swapped round?
			if (!$hasxc || !$hasyc) continue;
			if (($x > $y && $xc < $yc) || ($y > $x && $xc > $yc)) {
				$tmp = $yc;
				$yc = $xc;
				$xc = $tmp;
				$profile[$xcField] = (string) $xc;
				$profile[$ycField] = (string) $yc;	
			} 
		}
	}

	/**
	 * Gets a list of descriptions for a manufacturer
	 *
	 * @param String $manufacturer
	 * @return String[]
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function getCatalog($manufacturer) {
		if (!class_exists("DRCatManager",true)) throw new DetectRightException("You do not have this Enterprise module");
		return DRCatManager::getCatalog($manufacturer);
	}
	
	/**
	 * Get a HashMap containing records for every known deviceId/entity hash in the Detectright data.
	 *
	 * The fields in the entity table are remapped for this function.
	 * The sample useragent is merely the first alphabetical useragent on file in our main HQ database
	 * for the device. It's more of an illustration, really. It also indicates where we haven't
	 * got a useragent for a device.
	 * 
	 * This may get big. You have been warned!
	 *
	 * @return associative_array containing an associative array for each entity, keyed by deviceId
	 */
	public static function getAllDevices($devices = array()) {
		if (!class_exists("DRCatManager",true)) throw new DetectRightException("You do not have this Enterprise module");
		return DRCatManager::getAllDevices($devices);
		}

	/**
	 * Takes a list of deviceIDs that you have in your system, and returns the ones you don't have. Puts
	 * missing ones in the exceptions parameter (this should never happen). Fills the "remap"
	 * Map with a list of remapped IDs for the ones you passed in.
	 * 
	 * NOTE: This function requires a build with catalogue management enabled!
	 * 
	 * @param oldDevices List<String> List of devices you have
	 * @param exceptions List<String> IDs which don't exist any more. This list is filled if non-existent IDs are found.
	 * @param remaps Map<String,String> Maps IDs passed in which now map to a different primary ID.
	 * @return List<String>
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function getDeltaDeviceIDs($oldDevices,&$exceptions,&$remaps) {
		if (!class_exists("DRCatManager",true)) throw new DetectRightException("You do not have this Enterprise module");
		return DRCatManager::getDeltaDeviceIDs($oldDevices,$exceptions,$remaps);
			}
			
	
	/**
	 * Pass in a Device ID (primary or secondary) and get its primary back. A list of secondary IDs is also passed back inserted into 
	 * aliases. 
	 * @param String $deviceID String with 32 Char Device ID
	 * @param array $aliases List<String> a List that will be filled with any aliases.
	 * @return String 32-char Device ID/hash
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	static public function getDeviceIDMap($deviceID, $aliases) {
		if (!class_exists("DRCatManager",true)) throw new DetectRightException("You do not have this Enterprise module");
		return DRCatManager::getDeviceIDMap($deviceID,$aliases);
	}

	/**
	 * For any given device ID, get the actual details it corresponds to (not what it ultimately maps to).
	 * This can be used to generate lists of aliases using any ID lists generated by getDeviceIDMap.
	 * @param String $deviceID Detectright Device ID.
	 * @return associative_array Map<String,String>
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	static public function getDeviceIDDetails($deviceID)  {
		if (!class_exists("DRCatManager",true)) throw new DetectRightException("You do not have this Enterprise module");
		return DRCatManager::getDeviceIDDetails($deviceID);
	}
	
	/**
	 * Takes a stream of things to detect, detects them, and outputs a result for each field in the database in
	 * tab delimited form.
	 * @param scanner Input stream of either useragents or device IDs
	 * @param out Output for detection results
	 * @param detectionMethod
	 * @throws java.io.FileNotFoundException
	 * @throws java.io.IOException
	 * @throws ConnectionLostException
	 * @throws DetectRightException
	 */
	public static function testFile($in,$out, $detectionMethod="UA",$externalURL="") {
		if (self::getDBLink() === null) throw new DetectRightException("DetectRight not initialized",null); 		
		if (DRFunctionsCore::isEmptyStr($detectionMethod)) $detectionMethod = "UA";

		if (empty($in)) throw new DetectRightException("Input not specified",null);
		if (!is_resource($out)) {
			try {
				$out = fopen($out,"w");
			} catch (Exception $e) {
				throw new DetectRightException("Output Error",$e);
			}
		}
		
		if (!is_resource($in)) {
			if (is_string($in)) {
				try {
					$in = fopen($in,"r");
				} catch (Exception $e) {
					throw new DetectRightException("Input Error",$e);
				}
			}
		} 
		$schemas = SchemaPropertyCore::getSchema();
		$fields = array();
		// add fieldnames here.
		$schemaKeys = array_keys($schemas);
		$booleans = array();
		foreach ($schemaKeys as $sKey) {
			$sp = $schemas[$sKey];
			$isBool = ($sp->type === "Boolean");
			
			if (SchemaPropertyCore::$strictExportNames) {
				if ($isBool) $booleans[] = $sp->display_name;
				$fields[] = $sp->display_name;
			} else {
				if ($isBool) $booleans[] = $sp->property;
				$fields[] = $sp->property;
			}
		}

		sort($fields);
		array_unshift($fields,"devicedescriptor");
		array_unshift($fields,"deviceid");
	
    	$outStr = "Item\t" . implode("\t",$fields) . "\ttimetaken\tmemoryUsed\tfreeMem\ttotalMem\tdatasource";
    	echo $out;
    	if (is_resource($out)) {
    		try {
    			fwrite($out,$outStr."\n");
    		} catch (Exception $e) {
    			throw new DetectRightException("Output Error",$e);
    		}
    	}  else {
    		echo $outStr."\n";
    	}
    	
    	$r=0;
    	$n = 0;
    	$totalTime = 0.00;
    	try {
      		while ($useragent = fgets($in)){
      			$useragent = str_replace("\n","",$useragent);
      			$useragent = trim($useragent);
      			if (strpos($useragent," - - ") !== false && strpos($useragent,"/2012") !== false && strpos($useragent,"]") !== false) {
      				$pos = strpos($useragent,"]");
					$useragent = trim(substr($useragent,$pos+1));
      				$pos = strpos($useragent,'"');
      				if ($pos !== false && $pos < strlen($useragent)-1) {
      					$useragent = substr($useragent,$pos+1,-1);	
      				}
      			}
  				$propString = array();
        		$propString[] = $useragent;
        		//echo $useragent."\n";
        		$start = DRFunctionsCore::mt();
        		$tt = 0;
        		$profile = null;
        		$miss = false;
        		try {
        			if ($externalURL) {
        				$profile = DRFunctionsCore::ungz(file_get_contents($externalURL."?ua=".urlencode($useragent)));
        				$tt = $profile['timetaken'];
        			}
        			if (!$profile) {
        			if ($detectionMethod === "UA") {
        				$profile = self::getProfileFromUA($useragent);
      				} else if ($detectionMethod === "ID") {
      					$profile = self::getProfileFromDeviceID($useragent);
      				} else {
      					$profile = array();
      				}
        			}
      			} catch (DeviceNotFoundException $dnfe) {
      				if (!is_resource($out)) {
      					//echo ($useragent . " missed");
      				}
      				$miss = true;
      			} catch (DetectRightException $de) {
      				echo $de->getMessage();
      				if ($de->ex !== null) {
      					var_export($de->ex->getTrace());
      				} else {
      					var_export($de->getTrace());
      				}
      				$miss=true;
      			}
        		$end = DRFunctionsCore::mt();
        		foreach ($fields as $field) {
        			/*if (field.equals("tiff")) {
        				boolean dummy=true;
        			}*/
        			$isBool = in_array($field,$booleans);
        			if (!$miss && $profile !== null && isset($profile[$field])) {
        				$thing =  $profile[$field];
        				if (is_array($thing)) $thing = "[".implode(",",$thing)."]";
        				if ($isBool) {
        					$thing = Validator::validate("wurfl_boolean",$thing,true);
        				}
       					$propString[] = $thing;
        			} else {
        				$propString[] = "---";
        			}
        		}
        		
        		if ($tt == 0) {
        			$tt = ($end - $start);
        		}
        		$propString[] = $tt;
        		$totalTime = $totalTime + $tt;
        		$n++;

        		if (!$externalURL) {
        			$db = DetectRight::getDBLink();
        			$datasource = $db->params["address"];
        			$propString[] = $datasource;
        		} else {
        			$propString[] = $externalURL;
        		}
        		$property = implode("\t",$propString);
        		if (is_resource($out)) {
        			fwrite($out,$property . "\n");
        		} else {
        			//echo $property;
        		}

        		$r++;
        		//if (r == 1) break;
        		self::clear();
        		$average = $totalTime/$n;
        		//echo "Current average: $average\n";
      		}
      		$average = $totalTime/$n;
      		echo "Total time: " . $totalTime;
      		echo "Average time: " . ($average * 1000) . "ms";
    	} catch (Exception $e) {
    		//boolean dummy=true;
    	}  
      	fclose($in);
      	if (is_resource($in)) {
      		fclose($out);
    	}
    	self::close();
	}
	
	/**
	 * Diagnostic function which detects a full header set in diagnostic mode and 
	 * generates voluminous output to the passed-in writer (or System.out if none)
	 * which diagnoses the detection process in great detail.
	 * @param headers
	 * @param field
	 * @throws IOException
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function testDR($headers, $field="")  {
		$out = array();
		if (is_string($headers)) {
			$headers = array("HTTP_USER_AGENT"=>$headers);
		}
		try {
			if (DRFunctionsCore::isEmpty($headers)) throw new DetectRightException("No Input headers",null);
			$lhm = self::getProfileFromHeaders($headers);
			$esc = self::getLastDetection();
			$qdt = $esc->qdt;
			$pkg = $qdt->packageMe();
			
			$out[] = "Entity sigs";
			$ess = $esc->es;
			$esigs = array();
			foreach ($ess as $es) {
				$esigs[] = $es->descriptor . " --- " .  $es->sig;
			}
			$out[] = implode("\n",$esigs);
			$out[] = "Last Detection QDT";
			$out[] = implode("\n", $pkg);
			$qdt->resetCount();
			
			if (!DRFunctionsCore::isEmpty($field)) {
				$nsp = new SchemaPropertyCore(0,"",$field);
				$importances = array();
				$value = SchemaPropertyCore::getObjectValue($qdt,$nsp,$importances);
				$pkg = $qdt->packageMe(true);
				$out[] = $value;
				$out[] = implode("\n", $pkg);
			}
			
			$keys = array_keys($lhm);
			foreach ($keys as $key) {
				$property = $lhm[$key];
				$outStr = $key . " = " . $property;
				$out[] = $outStr;
			}
			//System.out.println(lhm);
		} catch (DeviceNotFoundException $e) {
			echo $e->getTraceAsString();
		} catch (DetectRightException $e) {
			if (!is_null($e->ex)) {
				echo $e->ex->getTraceAsString();
			} else {
				echo $e->getTraceAsString();
			}
		} catch (ConnectionLostException $e) {
			echo $e->getTraceAsString();
		}
		return $out;
	}
	
	/**
	 * Get a list of (and details of) all fields in the current database that would appear in a detection. 
	 * Includes global defaults but not generics. 
	 * @return Map<String,Object> Map representing fields in the build from the database.
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function getAllFieldNames()  {
		return SchemaPropertyCore::getSchema();
	}
	
	/**
	 * An alternative way of updating: copying data atomically 
	 * from a source database to the live one. Since the data is done entity by entity
	 * and each entity is quite small, it avoids locking the data for any long periods
	 * of time. This can also work as a live link to a MySQL database into another MySQL
	 * database, oddly enough (which means technically if you've got a direct access MySQL 
	 * database permission at Detectright.com, and MySQL at your end, you can get live updates 
	 * that way.
	 *
	 * @param String $hash
	 * @param String $dbString
	 * @see updateEntities
	 */
	static function importEntity($hash,$dbString) {
		try {
			$src = DBLink::getConnection($dbString);
			$dest = &self::$dbLink;
		
			$srcEP = EntityPackage::getPkg($hash,$src);
			$destEP = EntityPackage::getPkg($hash,$dest);
			$destEP->addFrom($srcEP);
			$src->close();
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		} 
	}
	
	/**
	 * Hot update function across the database.
	 *
	 * @param String $dbString valid connection string, DR-stylee
	 */
	static function updateEntities($dbString) {
		try {
			$src = DBLink::getConnection($dbString);
			$dest = DetectRight::$dbLink;
			
			$srcEntities = $src->getIDs(EntityCore::$table,"hash");
			$destEntities = $src->getIDs(EntityCore::$table,"hash");
			$sameEntities = array_intersect($srcEntities,$destEntities);
			$srcEntities = array_diff($srcEntities,$sameEntities);
			$destEntities = array_diff($destEntities,$sameEntities);
			// now srcEntities contains new entries, and destEntities contains orphaned entries
			// entries would be orphaned due to being merged with other devices or being invalidated from the main
			// dataset at HQ. It would be very unusual for a status zero (invalid) entry to have got into the original
			// data. It's much more likely that an entity has disappeared because it's now only an alias.
			// First, let's process the entities which are the same: they might have extra data.They also have to clear out
			// old entities that have been merged into them.
			foreach ($sameEntities as $hash) {
				$srcEP = EntityPackage::getPkg($hash,$src);
				$destEP = EntityPackage::getPkg($hash,$dest);
				$destEP->addFrom($srcEP);
			}
			
			// now New Entities
			foreach ($srcEntities as $hash) {
				$srcEP = EntityPackage::getPkg($hash,$src);
				$destEP = EntityPackage::getPkg($hash,$dest);
				$destEP->addFrom($srcEP);
			}

			// now Orphaned entities
			foreach ($destEntities as $hash) {
				$destEP = EntityPackage::getPkg($hash,$dest);
				$destEP->delete();
			}
		} catch (DetectRightException $de) {
			throw $de;
		} catch (ConnectionLostException $cle) {
			throw $cle;
		} catch (Exception $e) {
			throw new DetectRightException("Error detecting",$e);
		}
	}
	
	
	/**
	 * Updates lookup lists in the current database hotly!
	 *
	 * An update for the various lookup lists in the database, which relies on a list of tables
	 * in detectright.properties. For each table we open a recordset at each end and synchronize.
	 * This ensures the least disruption possible to operation.
	 *
	 * @todo This has not been substantially tested yet
	 *
	 * @param dbString String containing DetectRight-style database connection string
	 * @return success or failure
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
	public static function updateLookups($dbString,$tables=array())  {
		// create new DBLink for source database
		$src = DBLink::getConnection($dbString);
		$dest = self::$dbLink;

		$success = true;
		if (!$tables) {
			$tables = $src->getIDs("lookups","table");
		}

		foreach ($tables as $table) {
			$srcRS = $src->fetchRecordset($table,array("*"));
			$destRS = $dest->fetchRecordset($table,array("*"));
			$success = $success & RecordSet::syncRS($srcRS,$destRS);
		}
		return $success;
	}
	
	public static function status() {
		$status = self::testDB();
		$status[] = var_export(self::$dbLink,true);
		$status[] = var_export(self::$cacheLink,true);
		return $status;
	}
	
	/**
	 * Gets a variable from an object, hopefully by force
	 *
	 * @param Object $object
	 * @param string $property
	 * @return mixed
	 * @internal
	 * @access public
	 */
	static function get_object_var($object,$property) {
		$reflectionObject = new ReflectionObject($object);
		/* @var $reflectionProperty ReflectionProperty */
		try {
			$reflectionProperty = $reflectionObject->getProperty($property);
		} catch (ReflectionException $e) {
			return null;
		}
		try {
		$value = $reflectionProperty->getValue($object);
		} catch (ReflectionException $e) {
			return null;
		}
		return $value;
	}
}