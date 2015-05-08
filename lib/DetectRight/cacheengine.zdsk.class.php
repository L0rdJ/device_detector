<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage DRCache
 */
/******************************************************************************
Name:    cacheengine.zdsk.class.php
Version: 2.7.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License 
Agreement, the latest version of which can be found at 

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance, 
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRZendDiskCache");
}

/**
 * Zend Server Disk cache engine for DetectRight.
 * @internal
 */
Class DRZendDiskCache extends DRCache {	
	
	function __construct() {
		$this->engine = "ZDSK";
	}
	
	/**
	 * Increment a cache key
	 *
	 * @param string $key
	 * @access public
	 * @internal
	 * @return integer || false
	 */
	function cache_increment($key) {
		$value = zend_disk_cache_fetch($key);
		if ($value === null) {
			$this->throwError("Zend Increment attempted on $key but key not in Cache");
			return null;
		}
		if (!is_numeric($value)) {
			$this->throwError("Increment attempted on non-numeric_value");
			return null;
		}
		$value = $value + 1;
		$success = zend_disk_cache_store($key,$value);
		if ($success === false) {
			$this->throwError("Error incrementing $key in ZendDiskCache");
			$value = $value - 1;
		} 
		return $value;
	}

	/**
	 * Delete a cache key
	 *
	 * @param string $key
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_delete($key) {
		if (!$this->cache_ok()) return false;
		$success = zend_disk_cache_delete($key);
		if ($success === false) {
			$this->throwError("ZendDisk Delete error on $key");
		}
		return $success;
	}

	/**
	 * Set a variable into the cache
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param boolean $compression
	 * @param integer $timeout
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_set($key,$value,$timeout=0) {
		if (!$this->cache_ok()) return false;
		if (!$this->enabled) return false;
		return $this->set($key,$value,$timeout);
	}

	protected function set($key,$value,$timeout) {
		// TESTING
		$diag = DetectRight::$DIAG;
		if ($diag) $start= DRFunctionsCore::mt();		
		if ($value === false) $value = "bool{false}";
		$result = zend_disk_cache_store($key,$value,$timeout);
		if ($diag) $end = DRFunctionsCore::mt();
		if ($diag) $timeTaken = $end - $start;
		if ($diag) $this->set_time = $this->set_time + $timeTaken;		
		if ($result===false) {
			if ($diag) DetectRight::checkPoint("Zend Disk Set Fail $key");
			$this->throwError("Zend Disk Cache Set Fail $key");
		}
		return $result;		
	}
	
	/**
	 * Get a key from the cache
	 *
	 * @param string $key
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function cache_get($key) {

		if (!$this->cache_ok()) return null;
		if (DetectRight::$redetect) {
			$this->cache_delete($key);
			return null;
		}
		return $this->get($key);
	}

	protected function get($key) {
		$diag = DetectRight::$DIAG;
		if ($diag) DetectRight::checkPoint("Zend Disk Cache get $key start");
		if ($diag) $start = DRFunctionsCore::mt();
		$result = zend_disk_cache_fetch($key);

		if ($result === false) {
			DetectRight::checkPoint("Zend Disk Cache get fail");
			$this->throwError("Zend Disk Cache failed on $key");
			return null;
		}
				
		if ($diag) DetectRight::checkPoint("Zend Disk Cache get end ");
		if ($diag) $end = DRFunctionsCore::mt();
		
		if ($diag) $timeTaken = $end - $start;
		if ($diag) $this->get_time = $this->get_time + $timeTaken;
		// this makes sure that we can store "false" values in the cache without them
		// being confused with "key missing" values;
		if ($result === "bool{false}") $result = false;
		return $result;
	}
	
	function close() {
		
	}
	/**
	 * Initiate the cache with some HTTP variables which allow us to tweak the cache a bit.
	 *
	 * @param array $params
	 * @access public
	 * @internal
	 */
	function cache_init($params) {
		if (!function_exists("zend_disk_cache_clear")) {
			$this->throwError("Zend Disk Cache Not Installed");
			return;
		}
		
		parent::cache_init($params);
		return;
	}

	/**
	 * Flush the cache
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_flush() {
		if (!$this->cache_ok()) return false;
		try {
			zend_disk_cache_clear();
		} catch (Exception $e) {
			
		}
		//$success = $this->cache->query("truncate table {idd}$this->table{idd}");
		return true;
	}
}

if (!class_exists("ZendDiskCache")) {
	DetectRight::registerClass("ZendDiskCache");
	
	Class ZendDiskCache extends DRZendDiskCache {
		
	}
}
