<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage DRCache
 */
/******************************************************************************
Name:    drcache.class.php
Version: 2.2.1
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

� 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License
Agreement, the latest version of which can be found at

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance,
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com
2.2.1 - changed function definition of "set" to remove "compressed"
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRCache");
}

/**
 * DRCache Class
 *
 * Provides cacheing objects for the application.
 *
 */
Class DRCache {
	public $error;
	/**
	 * Array of connection strings keyed by Class
	 *
	 * @var array
	 */
	static $connections=array();

	/**
	 * Do we use the cache?
	 *
	 * @static boolean
	 * @access public
	 */
	static $useCache=false;

	/**
	 * Dummy variable
	 *
	 * @static array
	 */
	static $objectsToCache = array();

	/**
	 * Array of cache objects stored by connection hash
	 *
	 * @static Object[]
	 * @access public
	 */
	static $caches=array();

	public $hash;

	/**
	 * Which engine does this use?
	 *
	 * @var string
	 * @internal
	 * @access public
	 */
	public $engine="";

	/**
	 * Is the cache functional?
	 *
	 * @var boolean
	 * @access public
	 * @internal
	 */
	public $cacheOK=false;

	public $params;
	/**
	 * Amount of time spent getting cache data - diagnostic
	 *
	 * @var integer
	 * @access public
	 * @internal
	 */
	public $get_time=0.00;

	/**
	 * Amount of time spent setting cache data - diagnostic
	 *
	 * @var integer
	 * @access public
	 * @internal
	 */
	public $set_time=0.00;

	public $cache; // the object doing the actual cacheing.

	/**
	 * Are we using the cache?
	 *
	 * @var boolean
	 * @internal
	 * @access public
	 */
	public $enabled=false;

	public function __construct($hash) {
		$this->hash = $hash;
	}

	/**
	 * Spreads cache objects around the farm
	 *
	 */
	static function init() {
		$done = array();
		foreach (self::$connections as $className=>$connection) {
			$hash = $connection;
			if (isset(self::$caches[$hash])) {
				$cache = self::$caches[$hash];
			} else {
				$cache = self::getConnection($connection);
				$cache->enabled = self::$useCache;
				self::$caches[$hash] = $cache;
			}
			$class = new ReflectionClass($className);
			$class->setStaticPropertyValue("cacheLink",$cache);
			$done[] = $className;
		}

		if (is_null(DetectRight::$cacheLink)) DetectRight::$cacheLink = new DRCache("DUMMY");

		if (is_object(DetectRight::$cacheLink)) {
			$cacheLink = DetectRight::$cacheLink;
			foreach (DetectRight::$classes as $class) {
				if ($class === __CLASS__) continue;
				if (in_array($class,$done)) continue;
				if (property_exists($class,"cacheLink")) {
					try {
						$refClass = new ReflectionClass($class);
						$refClass->setStaticPropertyValue ( "cacheLink" , $cacheLink );
					} catch (Exception $e) {
						// this doesn't matter all that much
					}
				}
			}
		}
	}

	/**
	 * Get a Cache object
	 *
	 * @param string $connection
	 * @return Cache compatible object
	 */
	static function getConnection($connection) {
		// connection is formatted so:
		//Engine//Address//Port//Username//Password//Bucket
		$hash = $connection;
		$dummyCache = new DRCache($hash);
		$connection = explode("//",$connection);
		if (count($connection) < 1) {
			trigger_error("Failed cache connection, too few parts $connection",E_WARNING);
			return $dummyCache; // dummy cache
		}
		$params['engine'] = array_shift($connection);
		$engine = $params['engine'];
		$params['address'] = array_shift($connection);
		if (count($connection) > 0) {
			$params['port'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['username'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['password'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['bucket'] = array_shift($connection); // this is like a table in a database
		}

		if (!class_exists($params['engine'],true)) {
			trigger_error("Cache engine doesn't exist: ".$params['engine'],E_USER_WARNING);
			return $dummyCache;
		}
		
		$cache = new $engine($hash);
		$cache->cache_init($params);
		$cache->enabled = self::$useCache;
		$pointer = &$cache;
		return $pointer;
	}

	/**
	 * Check the cache exists
	 *
	 * @param string $name
	 * @return boolean
	 * @static
	 * @access public
	 * @internal
	 */
	function checkCache() {
		$this->cacheOK = true;
		return;
	}

	function testCache() {
		$random_number = time()."/".rand(0,1000000);
		$result = $this->set($random_number,"hellovalue",30);
		if (!$result || $this->get($random_number) !== "hellovalue") {
			echo "Cache $this->engine problem";
			$this->cacheOK=false;
		} else {
			echo "Cache $this->engine is OK";
			$this->cacheOK=true;
		}
	}
	/**
	 * Check if the cache object is OK
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_ok() {
		if (!self::$useCache) return false;
		if (!$this->cacheOK) return false;
		return true;
	}


	/**
	 * Set Cacheing on/off in selected cache
	 *
	 * @param boolean $status
	 * @param string $name
	 * @return boolean
	 * @internal
	 * @access public
	 * @static
	 */
	function setCacheing($status) {
		$this->enabled=$status;
	}

	function throwError($error) {
		$this->error = $error;
		$this->checkError();
	}

	function checkError() {
		if (!is_object($this->cache)) return;
		if ($this->error) {
			trigger_error("Cache error in $this->engine",E_USER_ERROR);
			$this->cacheOK = false;
		}
	}

	// functions to be overridden
	function cache_init($params) {
		$this->params = $params;
		$this->get_time=0.00;
		$this->set_time=0.00;
		$this->file_get_time=0;
		$this->file_set_time=0;
		$this->cacheOK=false;
		$this->checkCache();
	}

	/**
	 * Close the cache
	 *
	 * @param string $name
	 * @return boolean
	 * @internal
	 * @access public
	 * @static
	 */
	function cache_close() {
		unset(self::$caches[$this->hash]);
	}

	/**
	 * Increment a cache value
	 *
	 * @param string $key
	 * @param string $name
	 * @return integer
	 * @static
	 * @internal
	 * @access public
	 */
	function cache_increment($key) {

	}

	/**
	 * Delete a value from cache
	 *
	 * @param string $key
	 * @param string $name
	 * @return boolean
	 * @static
	 * @access public
	 * @internal
	 */
	function cache_delete($key) {

	}

	protected function get($key) {

	}

	protected function set($key,$value,$timeout=0) {

	}

	/**
	 * Put in the cache
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param boolean $compression
	 * @param integer $timeout
	 * @param string $name
	 * @return boolean
	 * @internal
	 * @access public
	 */
	function cache_set($key,$value,$timeout=0) {

	}

	function cache_multiset($array) {
		// stub for caches which allow lists
	}
	/**
	 * Get something from cache
	 *
	 * @param string $key
	 * @param string $name
	 * @return mixed
	 * @internal
	 * @static
	 * @access public
	 */
	function cache_get($key) {

	}

	function cache_flush() {

	}

	/**
	 * Stats for all caches
	 *
	 * @return array
	 * @static
	 * @access public
	 * @internal
	 */
	function stats() {
		$output = self::cacheStats();
		return $output;
	}

	/**
	 * Cache stats for a particular name
	 *
	 * @param string $name
	 * @return array
	 * @static
	 * @access public
	 * @internal
	 */
	function cacheStats() {
		$output = array();
		$output['engine']=$this->engine;
		$output['get_time']=$this->get_time;
		$output['set_time']=$this->set_time;
		return $output;
	}
}