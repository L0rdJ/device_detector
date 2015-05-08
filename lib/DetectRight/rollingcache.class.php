<?php
class DRRollingCache {
		private $mainCache = array();
		private $buffer = array();
	
		private $maxCacheSize = -1; // default is "unlimited cache"
		private $maxBufferSize = 0;
		private $useBuffer = false; // temporary
		private $enabled = true;
		private $bufferDivisor = 10;
		public $serializeInOut = true;
	
		public function __construct($maxCacheSize = -1, $serialization = true)
		{
			$this->maxCacheSize = $maxCacheSize;
			$this->serializeInOut = $serialization;
		}
	
		public function isEnabled()
		{
			if (!$this->enabled) return false;
			if ($this->maxCacheSize == 0) return false;
			return true;
		}

		public function enable()
		{
			$this->enabled = true;
		}
	
		public function disable()
		{
			$this->enabled = false;
		}
	
		public function get($key) {
			if (!$this->enabled) return null;
			$value = DRFunctionsCore::gv($this->mainCache,$key,null);
			if ($value === null && $this->useBuffer) {
				$value = DRFunctionsCore::gv($this->buffer,$key);
			}	
			// Java fix for serialization?
			if ($this->serializeInOut && $value !== null) {
				$value = unserialize($value);
			}
			return $value; // could be null
		}
	
		public function Add($key, $value)
		{
			return $this->set($key, $value);
		}
	
		public function set($key, $value)
		{
			// Java fix for serialization?
			if ($this->serializeInOut) $value = serialize($value);
			if (!$this->enabled || $this->maxCacheSize == 0) return false;
			if (!$this->useBuffer)
			{
				$this->mainCache[$key] = $value;
				$reachedMax = ($this->maxCacheSize > 0) && (count($this->mainCache) >= $this->maxCacheSize);
				if ($reachedMax)
				{
					$this->useBuffer = true;
				}
			} else {
				$this->buffer[$key] = $value;
				if (count($this->buffer) >= $this->maxBufferSize) {
					$this->switchOver();
				}
			}
			return true;
		}
	
		public function hardReset()
		{
			$this->enabled = false;
			$this->mainCache = null;
			$this->mainCache = array();
			$this->enabled = true;
		}
		
		public function reset()
		{
			$this->useBuffer = true; // triggers a flush, but gently.
		}
	
		private function switchOver()
		{
			if (!$this->enabled) return;
			$this->enabled = false;
			$this->mainCache = $this->buffer;
			$this->buffer = array();
			$this->useBuffer = false;
			$this->enabled = true;
		}
	
		public function setMaxCacheSize($maxSize) {
			$this->maxCacheSize = $maxSize;
			if ($maxSize === 0)
			{
				$this->enabled = false;
			}
			else
			{
				$this->enabled = true;
			}
			if ($maxSize > 0)
			{
				$this->maxBufferSize = $maxSize / $this->bufferDivisor;
			}
			else
			{
				$this->maxBufferSize = 0; // buffer not needed in this new state of affairs
			}
		}
	
		private function setMaxBufferSize($bufferSize)
		{
			$this->maxBufferSize = $bufferSize;
		}
	
		public function getCurrentCacheSize()
		{
			return count($this->mainCache);
		}
	
		public function getCurrentBufferSize()
		{
			return count($this->buffer);
		}
	
	
		public function getMaxBufferSize()
		{
			return $this->maxBufferSize;
		}
	
		public function getMaxCacheSize()
		{
			return $this->maxCacheSize;
		}
	
		public function stats() {
			$statusMap = array();
			$statusMap["size"] = count($this->mainCache);
			$statusMap["maxSize"] = $this->maxCacheSize;
			$statusMap["buffersize"] = count($this->buffer);
			$statusMap["maxBufferSize"] = $this->maxBufferSize;
			return statusMap;
		}
	
		public function containsKey($key)
		{
			if (!$this->enabled) return false;
			if (array_key_exists($key,$this->mainCache)) return true;
			if ($this->useBuffer && array_key_exists($key,$this->buffer)) return true;
			return false;
		}
	
		public function Clear()
		{
			$this->reset();
		}
}