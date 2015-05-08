<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @subpackage Cache
 */
/******************************************************************************
Name:    cacheengine.memcache.class.php
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
2.7.0 - detects both kinds of memcached extension, doesn't do big binaries any more.
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRMemcached");
}

/**
 * Memcache cache engine for DetectRight.
 * @internal
 */
// This code is for backwards compatibility, but can be removed if you use "DRMemcached" as
// the cache signifier.

Class DRMemcached extends DRCache {

	function __construct($hash) {
		// there are two different memcache extensions.
		if (extension_loaded("Memcache")) {
			$this->engine = "Memcache";
		} elseif (extension_loaded("Memcached")) {
			$this->engine = "Memcached";
		} else {
			$this->engine = "Error";
		}
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
		if (!$this->cache_ok()) return false;
		if (!self::$useCache) return false;
		if (strlen($key) > 200) $key = md5($key);
		$success = $this->cache->increment($key);
		if ($success === false) {
			$this->throwError("Memcache increment failure $key");
		}
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
		if (!$this->cache_ok()) return;
		$key = md5($key);
		$success = $this->cache->delete($key);
		if ($success === false) {
			$this->throwError("Memcache Delete error");
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
		$key = md5($key);
		return $this->set($key,$value,$timeout);
	}

	protected function set($key,$value,$timeout) {
		if (DRFunctionsCore::isEmptyStr($key)) return false;
		$maxSize = (1024*1024)-256;
		//$maxSize = 1024*512;
		//$maxSize=0;
		if (!is_scalar($value)) {
			$value = "gz:".DRFunctionsCore::gz($value);
			if (strlen($value) > $maxSize) {
				return true; // too big to store, but let's pretend we did.
			}
		}
		$start=DRFunctionsCore::mt();
		if ($this->engine === "Memcache") {
			$result = $this->cache->set($key,$value,0,$timeout);
		} else {
			$result = $this->cache->set($key,$value,$timeout);
		}

		/*if (!$do) {
			DetectRight::checkPoint("Big cache set for $key");

			// chunk
			$chunks=array();
			$chunkKeys = array();

			while (isset($value[0])) {
				$chunks[]="";
				$chunkKey = "$key"."_chunk_".count($chunks);
				DetectRight::checkPoint("Doing $chunkKey\n");
				$chunkKeys[]=$chunkKey;
				if (strlen($value) > $maxSize) {
					$tmp = substr($value,0,$maxSize);
					$result=$this->cache->set($chunkKey,$tmp,0,$timeout);
					if (!$result) DetectRight::checkPoint("Failed setting for $chunkKey to string of length ".strlen($tmp)."\n");
					$value = substr($value,$maxSize);
				} else {
					$result=$this->cache->set($chunkKey,$value,0,$timeout);
					//echo $chunkKey."\n";
					if (!$result) DetectRight::checkPoint("Failed setting for $chunkKey to string of length ".strlen($value)."\n");
					$value="";
				}
			}
			$chunkKeys['chunked']=true;
			$result=$this->cache->set($key,$chunkKeys,0,$timeout);
		}*/

		$end = DRFunctionsCore::mt();
		$timeTaken = $end - $start;
		$this->set_time = $this->set_time + $timeTaken;

		if ($result===false) {
			DetectRight::checkPoint("Memcache Set Fail $key");
			$this->throwError("Memcache Set Fail $key");
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
		if (!self::$useCache) return null;
		$key = md5($key);
		if (DetectRight::$flush) {
			$this->cache->delete($key);
			return null;
		}
		$ret = $this->get($key);
		if ($ret === false) return null;
		return $ret;
	}

	protected function get($key) {
		$diag = DetectRight::$DIAG;
		if ($diag) DetectRight::checkPoint("Memcache get start");
		if ($diag) $start = DRFunctionsCore::mt();
		$result = $this->cache->get($key);
		if (is_string($result) && substr($result,0,3) === "gz:") {
			$test = DRFunctionsCore::ungz(substr($result,3));
			if (is_object($test) || is_array($test)) $result = $test;
		}

		/*if (is_array($result) && isset($result['chunked'])) {
			// this is chunked.
			$tmpResult="";
			$fail=false;
			unset($result['chunked']);
			foreach ($result as $chunkKey) {
				$tmp = $this->cache->get($chunkKey);
				if ($tmp) {
					$tmpResult .= $tmp;
				} else {
					$fail=true;
				}
			}
			if (!$fail) $result=$tmpResult;
		}*/

		if ($diag) DetectRight::checkPoint("Memcache get end");
		if ($diag) $end = DRFunctionsCore::mt();
		if ($diag) $timeTaken = $end - $start;
		if ($diag) $this->get_time = $this->get_time + $timeTaken;
		if (is_string($result) && (substr($result,0,2)=="O:" || substr($result,0,2)=="a:")) {
			if ($diag) DetectRight::checkPoint("Unserialize start");
			@$result=unserialize($result);
			if ($diag) DetectRight::checkPoint("Unserialize end");
		}
		if (is_object($result)) {
			if ($diag) DetectRight::checkPoint("Memcache returned object from $key");
		}
		if ($result===false) {
			DetectRight::checkPoint("Memcache returned nothing for $key");
		}

		/*if (is_string($result) && substr($result,0,5) == "file:")  {
			$start = DRFunctionsCore::mt();
			DetectRight::checkPoint("Memcache file read $result");
			$attribs = explode(":",substr($result,5));
			$compression = array_pop($attribs);
			if ($compression !== "compressed" && $compression !== "uncompressed") return null;
			$crc = array_pop($attribs);
			if (!$crc) return null;
			$fileKey = implode(":",$attribs);
			if (file_exists($fileKey)) {
				$input = file_get_contents($fileKey);
				if ($compression == "compressed") {
					$input = gzdecode($input);
				}
				$crcCheck = md5($input);
				if ($crcCheck !== $crc) return null;
				if (DRFunctionsCore::isSerialized($input)) {
					$result = unserialize($input);
				} else {
					$result=$input;
				}
			}
			$end = DRFunctionsCore::mt();
			$timeTaken = $end - $start;
			$this->file_get_time = $this->file_get_time + $timeTaken;
		}*/
		if (DRFunctionsCore::isEmptyStr($result) && !DRFunctionsCore::isEmptyStr($key)) {
			if ($diag) DetectRight::checkPoint("Memcache deleting $key");
			$this->cache_delete($key);
		}
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
		if ($this->engine === "Error") {
			$this->throwError("Memcache not installed");
			return;
		} 
		
		if ($this->engine === "Memcache") {
			$this->cache = new Memcache;
		} else {
			$this->cache = new Memcached;
		}
		$server = DRFunctionsCore::gv($params,'address');
		$port = DRFunctionsCore::gv($params,'port');
		if (DRFunctionsCore::isEmptyStr($server) || DRFunctionsCore::isEmptyStr($port)) {
			$this->throwError("Memcache init parameters missing");
		}
		$success = $this->cache->addServer($server, $port,false);
		if ($success === false) {
			$this->throwError("Memcache server adding failed");
		}
		parent::cache_init($params);
		return;
	}
	
	function cache_flush() {
		if (!$this->cache_ok()) return false;
		try {
			$this->cache->flush();
		} catch (Exception $e) {
			
		}
		//$success = $this->cache->query("truncate table {idd}$this->table{idd}");
		return true;
	}
}

if (!extension_loaded("Memcached")) {
	if (class_exists("DetectRight")) DetectRight::registerClass("Memcached");
	Class Memcached extends DRMemcached {
		
	}
}