<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage DRCache
 */
/******************************************************************************
Name:    cacheengine.apc.class.php
Version: 2.8.0
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
2.8.0 - Refactored to change name
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRAPCCache");
}

/**
 * APC cache engine for DetectRight.
 * @internal
 */
Class DRAPCCache extends DRCache {	
	
	function __construct() {
		$this->engine = "APC";
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
		apc_inc($key,1,$success);
		return $success;
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
		$success = apc_delete($key);
		if ($success === false) {
			$this->throwError("APC Delete error on $key");
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

	protected function set($key,$value,$timeout = 0) {
		// TESTING
		if (!is_scalar($value)) {
			$value = "gz:".DRFunctionsCore::gz($value);
		}
		$start= DRFunctionsCore::mt();		
		$result = apc_store($key,$value,$timeout);
		$end = DRFunctionsCore::mt();
		$timeTaken = $end - $start;
		$this->set_time = $this->set_time + $timeTaken;		
		if ($result===false) {
			DetectRight::checkPoint("APC Cache Set Fail $key");
			$this->throwError("APC Cache Set Fail $key");
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
		DetectRight::checkPoint("APC Cache get $key start");
		$start = DRFunctionsCore::mt();
		$success = true;
		$result = apc_fetch($key,$success);
		
		if (!$success) {
			DetectRight::checkPoint("APC Cache get fail");
			$this->throwError("APC Get failed on $key");
			return null;
		}
		if ($result === null) {
			DetectRight::checkPoint("APC Cache get end (null)");
			return null;
		}
		if ($result === true) {
			DetectRight::checkPoint("APC Cache get end (true)");
			return true;
		}
		if ($result === false) {
			DetectRight::checkPoint("APC Cache get end (false)");
			return false;
		}
		
		if (substr($result,0,3) =="gz:") {
			$result = DRFunctionsCore::ungz(substr($result,3));
			if ($result === null) {
				DetectRight::checkPoint("APC deleting $key");
				$this->cache_delete($key);
			}
		} 
		
		DetectRight::checkPoint("APC Cache get end ");
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
		if (!function_exists("apc_fetch")) {
			$this->throwError("APC Not Installed");
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
			apc_clear_cache();
			if (!isset($_GET['debug_host'])) {
				apc_clear_cache('user');
				apc_clear_cache('opcode');
			}
		} catch (Exception $e) {
			
		}
		return true;
	}

}

if (!class_exists("APCCache") && class_exists("DetectRight")) {
	DetectRight::registerClass("APCCache");
	
	Class APCCache extends DRAPCCache {
		
	}	
}