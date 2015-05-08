<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage Cache
 */
/******************************************************************************
Name:    cacheengine.redis.class.php
Version: 2.0.0
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
	DetectRight::registerClass("DRRedisCache");
}

/**
 * Redis cache engine for DetectRight.
 * @internal
 */
Class DRRedisCache extends DRCache {

	function __construct($hash) {
		$this->engine = "Redis";
		$this->hash = $hash;
	}
	
	/**
	 * Check if the cache object is OK
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function cache_ok() {
		if (!is_object($this->cache)) return false;
		if (!$this->cacheOK) return false;
		if (!$this->enabled) return false;
		return true;
	}

	/**
	 * Close the cache. Discard errors.
	 *
	 * @access public
	 * @internal
	 */
	function cache_close() {
		if (is_object($this->cache)) {
			@$this->cache->close();
		}
		parent::cache_close();
	}

	/**
	 * Increment a cache key
	 *
	 * @param string $key
	 * @internal
	 * @access public
	 * @return integer
	 */
	function cache_increment($key) {
		$success = $this->cache->incr($key,1);
		if ($success === false) {
			$this->throwError("Redis increment failure $key");
		}
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
		if (!$this->cache_ok()) return;
		$success = $this->cache->delete($key);
		if ($success === false) {
			$this->throwError("Redis Delete error");
		}
	}

	/**
	 * Set cache
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
		if (!self::$useCache) return false;
		return $this->set($key,$value,$timeout);
	}

	protected function set($key,$value,$timeout) {
		if (DRFunctionsCore::isEmptyStr($key)) return false;
		if (!is_scalar($value)) {
			$value = "gz:".DRFunctionsCore::gz($value);
		}
		$start=DRFunctionsCore::mt();
		$result = null;
		try {
			//$this->cache->watch($key);
			$result = $this->cache->set($key, $value, $timeout);
		} catch (Exception $e) {
			trigger_error($e->getMessage(),E_USER_WARNING);
			//throw new DetectRightException($e);
		}
		$end = DRFunctionsCore::mt();
		$timeTaken = $end - $start;
		$this->set_time = $this->set_time + $timeTaken;

		if ($result===false) {
			DetectRight::checkPoint("Redis Set Fail $key");
			$this->throwError("Redis Set Fail $key");
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
		if ($this->cache === null) return null;
		if (!$this->cache_ok()) return null;
		if (!self::$useCache) return null;
		if (DetectRight::$flush) {
			$this->cache->delete($key);
			return null;
		}
		return $this->get($key);
	}

	protected function get($key) {
		DetectRight::checkPoint("Redis get start");
		$start = DRFunctionsCore::mt();
		$result = $this->cache->get($key);
		if ($result !== false) {
		if (is_string($result) && substr($result,0,3) === "gz:") {
			$test = DRFunctionsCore::ungz(substr($result,3));
			if ($result !== null && $result !== false) {
				$result = $test;
			} else {
				DetectRight::checkPoint("Redis deleting invalid key $key");
				$this->cache_delete($key);
			}
		}
		} else {
			// no result
			$result = null;
		}

		DetectRight::checkPoint("Redis get end");
		$end = DRFunctionsCore::mt();
		$timeTaken = $end - $start;
		$this->get_time = $this->get_time + $timeTaken;
		return $result;
	}
	/**
	 * Initiate the cache with some HTTP variables which allow us to tweak the cache a bit.
	 *
	 * @param array $params
	 * @access public
	 * @internal
	 */
	function cache_init($params) {
		if (is_object($this->cache)) return;
		if (!class_exists("Redis",false)) {
			throw new DetectRightException("Redis not installed",null);
			return;
		} 
		
		$this->cache = new Redis();
		$server = DRFunctionsCore::gv($params,'address');
		$port = DRFunctionsCore::gv($params,'port');
		if (DRFunctionsCore::isEmptyStr($server) || DRFunctionsCore::isEmptyStr($port)) {
			throw new DetectRightException("Redis init parameters missing",null);
		}
		try {
			$success = $this->cache->connect($server, $port, 14400);
		} catch (Exception $e) {
			$success = false;
		}
		if ($success === false) {
			$this->throwError("Redis server adding failed");
		} else {
			$this->cache->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
		}
		parent::cache_init($params);
		return;
	}
	
	function cache_flush() {
		if ($this->cache === null) return true;
		if (!$this->cacheOK) return true;
		$success = $this->cache->flushAll();
		return $success;
		
	}
}

if (!class_exists("RedisCache")) {
	DetectRight::registerClass("RedisCache");
	
	Class RedisCache extends DRRedisCache {
		
	}
}
