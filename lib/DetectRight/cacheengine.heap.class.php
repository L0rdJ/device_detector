<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage DRCache
 */
/******************************************************************************
Name:    cacheengine.heap.class.php
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
2.8.0 - fixed scope bug
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRHeapCache");
}

/**
 * Heap cache engine for DetectRight.
 * Note that this cache is almost entirely useless except in batch mode!
 * @internal
 */
Class DRHeapCache extends DRCache {	
	
	function __construct() {
		$this->engine = "Heap";
	}
	
	public $cache = array();
	
	/**
	 * Increment a cache key
	 *
	 * @param string $key
	 * @access public
	 * @internal
	 * @return integer || false
	 */
	function cache_increment($key) {
		/* @var $value HeapCacheItem */
		$value = DRFunctionsCore::gv($this->cache,$key);
		if ($value === null) {
			$this->throwError("APC Increment attempted on $key but key not in Cache");
			return null;
		}
		$value->increment();
		$this->cache[$key] = $value;
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
		unset($this->cache[$key]);
		return true;
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
		if (!$this->enabled) return false;
		return $this->set($key,$value,$timeout);
	}

	protected function set($key,$value,$timeout) {
		if (is_object($value)) $value = clone $value;
		$item = new HeapCacheItem($value,$timeout);
		$this->cache[$key] = $item;
		return true;
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
		if (DetectRight::$redetect) {
			$this->cache_delete($key);
			return null;
		}
		return $this->get($key);
	}

	protected function get($key) {
		/* @var $result HeapCacheItem */
		$result = DRFunctionsCore::gv($this->cache,$key);
		if ($result === null) return null;
		if ($result->isExpired()) {
			unset($cache[$key]);
			return null;
		}
		$value = $result->getValue();
		if (is_object($value)) {
			return clone $value;
		} else {
			return $value;
		}
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
		$this->cache = array();		
		parent::cache_init($params);
		return;
	}

	/**
	 * Flush the cache
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 * @todo Change this to work with APC
	 */
	function cache_flush() {
		$this->cache = array();
		return true;
	}

}

class HeapCacheItem {
	private $expiry;
	private $value;
	
	public function __construct($value, $expiry) {
		$this->value = $value;
		$this->expiry = time() + $expiry;
	}
	
	public function increment($increment = 1) {
		if (!is_numeric($this->value)) return false;
		$this->value = $this->value + $increment;
		return true;
	}
	
	public function isExpired() {
		return $this->expiry <= time();
	}
	
	public function getValue() {
		return $this->value;
	}
}

if (!class_exists("HeapCache")) {
	DetectRight::registerClass("HeapCache");
	
	Class HeapCache extends DRHeapCache {
		
	}
}
