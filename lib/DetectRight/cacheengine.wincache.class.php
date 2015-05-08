<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage DRCache
 */
/******************************************************************************
Name:    cacheengine.wincache.class.php
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
	DetectRight::registerClass("WinCache");
}

/**
 * WinCache cache engine for DetectRight.
 * @internal
 */
Class DRWinCache extends DRCache {	
	
	function __construct() {
		$this->engine = "WinCache";
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
		$success = false;
		$newValue = wincache_ucache_inc ( $key , 1 , $success );
		if (!$success) {
			$this->throwError("WinCache Increment failed");
		}
		return $newValue;
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
		$success = wincache_ucache_delete ($key);
		if ($success === false) {
			$this->throwError("WinCache Delete error on $key");
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
		$start= DRFunctionsCore::mt();		
		$result = wincache_ucache_set($key, $value, $timeout);
		$end = DRFunctionsCore::mt();
		$timeTaken = $end - $start;
		$this->set_time = $this->set_time + $timeTaken;		
		if ($result===false) {
			DetectRight::checkPoint("WinCache Cache Set Fail $key");
			$this->throwError("WinCache Cache Set Fail $key");
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
		DetectRight::checkPoint("WinCache get $key start");
		$start = DRFunctionsCore::mt();
		$success = true;
		
		$result = wincache_ucache_get($key, $success);
		
		if ($success === false) {
			DetectRight::checkPoint("WinCache get fail");
			$this->throwError("WinCache Get failed on $key");
			return null;
		}
		DetectRight::checkPoint("WinCache get end ");
		$end = DRFunctionsCore::mt();
		
		$timeTaken = $end - $start;
		$this->get_time = $this->get_time + $timeTaken;
			
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
		if (!extension_loaded("wincache")) {
			$this->throwError("WinCache Not Installed");
			return;
		}
		$enabled = ini_get("wincache.ucenabled");
		if (PHP_SAPI == 'cli') {
			$enabled = $enabled && (bool) ini_get("wincache.enablecli");
		}
		if (!$enabled) {
			$this->throwError("WinCache is not enabled");
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
			wincache_ucache_clear();
		} catch (Exception $e) {
			
		}
		//$success = $this->cache->query("truncate table {idd}$this->table{idd}");
		return true;
	}
}

if (!class_exists("WinCache")) {
	DetectRight::registerClass("WinCache");
	
	Class WinCache extends DRWinCache {
		
	}
}
