<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    property.class.php
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
2.8.0 - clear bug in "_clone" method
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("Property");
}

/**
 * Properties as stored in a property collection object
 *
 */
Class Property {
		
	/**
	 * What's the minimum trust level for any data done during this access?
	 * This has an effect on real-time prepared data, but cached stuff might not respond to it,
	 * since it would be unwieldy to cache all possible combinations of accesses and trust mins.
	 *
	 * @static integer
	 * @internal 
	 * @access public
	 */
	static $trustMin=1;

	/**
	 * If a profile asks for a flag to be fulfilled, and it can't find anything,
	 * what does it return?
	 *
	 * Sensible values are null, 0 and 1
	 * @static boolean
	 * @internal
	 * @access public
	 */
	static $nullFlagValue = null;

	/**
	 * array of possible contexts, there's always "". Key is the context, value is an array of value objects
	 *
	 * @var array
	 * @access public
	 */
	public $contexts = array();
	public $locked = false;
	public $done = array();
		
	/**
	 * Rearranged view of $contexts, this time by value label
	 *
	 * @var array
	 * @access public
	 */
	public $labels = array(); // value objects for this property
	
	public $datapoints = array();
	
	/**
	 * Name of this property
	 *
	 * @var string
	 * @access public
	 */
	public $property;
	
	/**
	 * Constructor
	 *
	 * @param string $Field
	 * @return Property
	 * @internal 
	 * @access public
	 */
	function __construct($property="") {
		$this->property = $property;
	}
				
	function _clone() {
		$property = new Property($this->property);
		foreach ($this->datapoints as $datapoint) {
			$property->addDatapoint($datapoint,false);
		}
		$property->reindex();
	}
	
	public function __wakeup() {
		$this->reindex();
	}
	
	function resetCount($cnt = 0) {
		$dpKeys = array_keys($this->datapoints);
		foreach ($dpKeys as $dp) {
			if (is_object($this->datapoints[$dp])) $this->datapoints[$dp]->access_count = $cnt;
		}
	}
	
	public function setSource($source) {
		foreach ($this->datapointHashes() as $hash) {
			if (is_object($this->datapoints[$hash])) {
				$this->datapoints[$hash]->setSource($source);
			}
		}
	}
	
	public function __sleep() {
		$ov = get_object_vars($this);
		unset($ov['contexts']);
		unset($ov['labels']);
		return array_keys($ov);
	}

	public function lock() {
		$this->applyLock(true);
	}
	
	public function unlock() {
		$this->applyLock(false);
	}
	
	public function applyLock($lockType) {
		$this->locked = $lockType;
	}
	
	/**
	 * Add a level of importance to everything
	 *
	 * @param integer $importance	Offset. Can be +ve or -ve
	 * @access public
	 * @internal
	 */
	function addImportance($importance) {
		if ($importance === 0) return;
		$dps = array_keys($this->datapoints);
		foreach ($dps as $hash) {
			$this->datapoints[$hash]->addImportance($importance);
		}
	}

	/**
	 * Add a level of importance to everything
	 *
	 * @param integer $importance	Offset. Can be +ve or -ve
	 * @access public
	 * @internal
	 */
	function setImportance($importance) {
		$dps = array_keys($this->datapoints);
		foreach ($dps as $hash) {
			$this->datapoints[$hash]->setImportance($importance);
		}
	}

	/**
	 * Rebrand (as in "cattle") all of this data
	 *
	 * @param string $brand
	 * @param boolean $clear
	 * @access public
	 * @internal
	 */
	function rebrand($brand,$clear=false) {
		$dps = array_keys($this->datapoints);
		foreach ($dps as $hash) {
			$this->datapoints[$hash]->addBrand($brand,$clear);
		}
	}
	
	/**
	 * kind of a "delete by example"
	 * Can't imagine why this would be useful.
	 *
	 * @param Property $propertyToDelete
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	//OK
	function delete(Property $propertyToDelete) {
		if ($propertyToDelete->property !== $this->property) return;
		$dpHashes = array_keys($propertyToDelete->datapoints);
		foreach ($dpHashes as $dpHash) {
			unset($this->datapoints[$dpHash]);
		}
		$this->reindex();
	}
	
	/**
	 * Merge two properties
	 *
	 * @param Property $newProperty
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	//This is now destructive to the property coming in!
	function merge(Property &$newProperty) {
		/* @var $newProperty */
		if (!is_array($newProperty->datapoints)) return;
		foreach ($newProperty->datapoints as $dpKey=>$datapoint) {
			$this->addDatapoint($datapoint,false);
			if (!$this->locked) unset($newProperty->datapoints[$dpKey]);
		}
		$this->reindex();
	}

	//OK
	function reindex() {
		$this->contexts = array();
		$this->labels = array();
		foreach ($this->datapoints as $hash=>$datapoint) {
			if (!is_object($datapoint)) {
				unset($this->datapoints[$hash]);
				continue;
			}
			$dpHash = $datapoint->hash();
			$this->addDatapointToContext($datapoint,$dpHash);
			$this->addDatapointToLabels($datapoint,$dpHash);
			if ($hash !== $dpHash) {
				unset($this->datapoints[$hash]);
				$this->datapoints[$dpHash] = $datapoint;
			}
		}
	}
	
	//OK
	function addDatapoint(Datapoint $originalDatapoint, $index = true) {
		$datapoint = clone $originalDatapoint;
		$datapoint->processValue();
		$ipHash = $datapoint->hash();
		// path in property tree is 
		//(this->contexts) context//hash
		//(this->labels)label//context//hash
		
		if (isset($this->datapoints[$ipHash])) {
			$this->datapoints[$ipHash]->merge($datapoint);
		} else {
			$this->datapoints[$ipHash] = $datapoint;
		}
		if (!$index) return;
		$this->addDatapointToContext($datapoint);
		$this->addDatapointToLabels($datapoint);
	}
	
	function addDatapointToContext(Datapoint $datapoint,$hash = "") {
		if ($this->contexts === null) $this->reindex();
		if (!$hash) $hash = $datapoint->hash();
		$context = $datapoint->context;
		$label = $datapoint->label;
		$units = $datapoint->units;
		if ((array) $this->contexts !== $this->contexts) $this->contexts = array();
		if (!isset($this->contexts[$context])) {
			$this->contexts[$context] = array();
		}
		if (!isset($this->contexts[$context][$label])) {
			$this->contexts[$context][$label] = array();
		}
		if (!isset($this->contexts[$context][$label][$units])) {
			$this->contexts[$context][$label][$units] = array();
		}
		
		$this->contexts[$context][$label][$units][] = $hash;
	}
	
	function addDatapointToLabels(Datapoint $datapoint, $hash = "") {
		if ($this->labels === null) $this->reindex();
		if (!$hash) $hash = $datapoint->hash();
		$context = $datapoint->context;
		$label = $datapoint->label;
		$units = $datapoint->units;
		if (!is_array($this->labels)) $this->labels = array();
		if (!isset($this->labels[$label]))  {
			$this->labels[$label] = array();
		}
		if (!isset($this->labels[$label][$context])) {
			$this->labels[$label][$context] = array();
		}
		
		if (!isset($this->labels[$label][$context][$units])) {
			$this->labels[$label][$context][$units] = array();
		}
		
		$this->labels[$label][$context][$units][] = $hash;
	}
	
	function datapointHashes() {
		return array_keys($this->datapoints);
	}
	
	function getValueFromExampleDP(Datapoint $datapoint) {
		$context = $datapoint->context;
		$label = $datapoint->label;
		$units = $datapoint->units;
		if (!isset($this->contexts[$context])) {
			return array();
		}
		if (!isset($this->contexts[$context][$label])) return array();
		if (!isset($this->contexts[$context][$label][$units])) return array();
		$dpHashArray = $this->contexts[$context][$label][$units];
		
		$datapoints = array();
		foreach ($dpHashArray as $hash) {
			$dp = null;
			if (isset($this->datapoints[$hash])) {
				$dp = &$this->datapoints[$hash];
			} else {
				// this shouldn't really happen. It's here for emergency reasons.
				foreach ($this->datapoints as $tmpDP2) {
					$tmpDP2Hash = $tmpDP2->hash();
					if ($tmpDP2Hash === $hash) {
						unset($this->datapoints[$hash]);
						$this->datapoints[$tmpDP2Hash] = $tmpDP2;
						$dp =&$this->datapoints[$tmpDP2Hash];
						$hash = $tmpDP2Hash;
						break;
					}
				}
			}
			
			if ($dp !== null) {
				if ($dp->compliesWithDP($datapoint)) {
					$this->datapoints[$hash]->recordAccess();
					$datapoints[$hash] = clone $this->datapoints[$hash];
				}
			}
			unset($dp);
		}
		
		$key = $datapoint->getWildcardField();
		if ($key === "") return array(); // empty query, basically.
		$datapoints = Datapoint::sortDatapoints($datapoints,$key,"version",true);
		return $datapoints;
	}
	/**
	 * String representation of this. At least, an array of strings!
	 *
	 * @return array		Array of strings
	 * @access public
	 * @internal
	 */
	function toStringArray($optimize = false) {
		$output=array();
		$this->reindex();
		$dp = $this->datapointHashes();
		foreach ($dp as $hash) {
			if ($optimize && $this->datapoints[$hash]->getAccessCount() === 0) continue;
			$output[] = $this->datapoints[$hash]->toString();
		}
		return $output;
	}

	function prune() {
		$dp = $this->datapointHashes();
		foreach ($dp as $hash) {
			if (!is_object($this->datapoints[$hash])) continue;
			if ($this->datapoints[$hash]->getAccessCount() === 0) {
				unset($this->datapoints[$hash]);
			}
		}
	}
	
	function toArray() {
		$output=array();
		$dp = $this->datapointHashes();
		foreach ($dp as $hash) {
			$output[] = $this->datapoints[$hash]->toArray();
		}
		return $output;		
	}
	
	function toString() {
		$output = $this->toStringArray();
		return implode("\n",$output);
	}
	
	/**
	 * XML Representation of this
	 *
	 * @return array 	Array of strings
	 * @deprecated 
	 * @internal 
	 * @access public
	 */
	function toXMLArray() {
		$output=array();
		$dp = $this->datapointHashes();
		foreach ($dp as $hash) {
			$output[] = $this->datapoints[$hash]->toXMLFragment();			
		}
		return $output;
	}
	
	/**
	 * Generate an XML summary
	 *
	 * @param string $labels
	 * @return array			Array of strings containing lines of XML output
	 * @access public
	 * @internal
	 */
	function toXMLSummaryArray() {
		$output=array();
		$output[] = "<property id=\"$this->property\">";
		array_push($output,$this->toXMLArray());
		$output[] = "</property>";
		return $output;		
	}

	function close() {
		unset($this->contexts);
		unset($this->labels);
		if (!$this->datapoints) return;
		$keys = array_keys($this->datapoints);
		foreach ($keys as $key) {
			unset($this->datapoints[$key]);
		}
		unset($this->datapoints);
	}
	
	function __destruct() {
		$this->contexts = null;
		$this->labels = null;
		if (!isset($this->datapoints)) return;
		$keys = array_keys($this->datapoints);
		foreach ($keys as $key) {
			$this->datapoints[$key] = null;
			unset($this->datapoints[$key]);
		}
		$this->datapoints = null;
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$obj = new Property();
		foreach ($array as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
	}
}