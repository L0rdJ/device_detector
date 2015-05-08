<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    quantumdatacollection.class.php
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
	DetectRight::registerClass("QuantumDataCollection");
}

/**
 * QuantumDataCollection holds data in the Universal schema and allows it to be merged, retrieved, etc.
 */
Class QuantumDataCollection {
	/**
	 * Array of metadata associated with this object. Heavily used.
	 *
	 * @var array
	 * @access public
	 */
	public $metadata = array();

	/**
	 * Locked? Or not?
	 *
	 * @var boolean
	 */
	public $locked = false;
	
	/**
	 * Keeps track of what data has been added into this QDC.
	 *
	 * @var array
	 * @access public
	 */
	public $addedData = array();

	/**
	 * Array of Property Objects, keyed by property name.
	 *
	 * @var array
	 * @access public
	 */
	public $properties;

	/**
	 * Who created this? DetectRight uses this field to hold the datasource that did.
	 * It can also occasionally be a DetectRight module.
	 *
	 * @var string
	 * @access public
	 */
	public $creator;

	/**
	 * Another space for the originator to be put. This is a default "brand" (as in cow-steering brand, 
	 * not manufacturer brand), which is applied to other stuff coming in to this QDC.
	 *
	 * @var string
	 * @access public
	 */
	public $brand;

	/**
	 * Has this been "branded" already? If so, then don't do it again.
	 *
	 * @var boolean
	 * @access public
	 */
	public $branded;
	
	/**
	 * Constructor. Create a new QDT and GO.
	 *
	 * @param string $creator
	 * @return QuantumDataCollection
	 * @access public
	 * @internal
	 */
	function __construct($creator="") {
		$this->properties=array();
		$this->metadata=array();
		$this->creator=$creator;
		if (!$this->creator) {
			$this->creator="Unknown";
		} 
		
		$this->brand=array($creator);
		$this->branded=true; // things are naturally branded!
	}
	
	public function _clone() {
		$qdc = new QuantumDataCollection();
		$qdc->metadata = $this->metadata;
		$qdc->addedData = $this->addedData;		
		$qdc->brand = $this->brand;
		$qdc->creator = $this->creator;
		foreach ($qdc->properties as $key=>$property) {
			$qdc->properties[$key] = $property->_clone();
		}
		return $qdc;
	}
	
	public function __wakeup() {
		
	}
	
	public function __sleep() {
		$ov = get_object_vars($this);
		unset($ov['locked']);
		return array_keys($ov);
	}

	/******************* Adding Code *******************/
	/**
	 * Add another QDC to this. A Much-used function.
	 *
	 * @param QuantumDataCollection $newQDC
	 * @return boolean
	 * @access public
	 * @internal
	 */
	// Now this decimates the QDC
	function addPropertyCollection(&$newQDC) {
		/* @var $newPropertyCollection QuantumDataCollection */
		$log = DetectRight::$LOG;

		if (!is_object($newQDC)) {
			DetectRight::$stopFlag=true;
			return false;
		}

		foreach ($newQDC->properties as $propertyName=>$propertyObject) {
			$propertyName=strtolower($propertyName);
			if (!array_key_exists($propertyName,$this->properties)) {
				$this->properties[$propertyName]=$propertyObject;
			} else {
				$this->properties[$propertyName]->merge($propertyObject);
				if ($log) echo "Done $propertyName.\n";
			}
			if (!$this->locked) unset($newQDC->properties[$propertyName]);
		}

		// preserve a list of the properties added to this data after the first one.
		if ($newQDC->metadata) {
			$this->metadata = array_merge($this->metadata,$newQDC->metadata);
		}

		return true;
	}

	function prune() {
		$propKeys = array_keys($this->properties);
		foreach ($propKeys as $propKey) {
			$this->properties[$propKey]->prune();
			if (count($this->properties[$propKey]) == 0)  {
				$this->properties[$propKey] = null;
				unset($this->properties[$propKey]);
			}
		}
	}	

	function relink() {
		
	}
	
	function resetCount($cnt = 0) {
		$propKeys = array_keys($this->properties);
		foreach ($propKeys as $key) {
			if (is_object($this->properties[$key])) $this->properties[$key]->resetCount($cnt);
		}
	}
	
	function addDatapoint($datapoint) {
		$property = $datapoint->property;
		$this->addProperty($property);
		$this->properties[$property]->addDatapoint($datapoint);
	}

	function addProperty($property) {
		if (!isset($this->properties[$property])) {
			$propertyObject = new Property($property);
			$this->properties[$property] = $propertyObject;
		}
	}

	/**
	 * Add a brand across all elements
	 *
	 * @param string $brand
	 * @param boolean $force
	 * @param boolean $clear
	 * @internal 
	 * @access public
	 */
	function addBrand($brand="",$force=false,$clear=false) {
		if (!$brand) {
			$brand = $this->creator;
		}

		if (!$this->branded || array_search($brand,$this->brand) === false || $force) {
			if (!$this->properties) return;
			$keys = array_keys($this->properties);
			foreach ($keys as $key) {
				/* @var $this->properties[$key] Property */
				$this->properties[$key]->rebrand($brand,$clear);
			}
			$this->brand[]=$brand;
		}
		$this->branded=true;
	}

	/**
	 * This adds an importance offset to everything in it: this is to allow us to increase or decrease the 
	 * importance of this.
	 *
	 * @param integer $importance
	 * @internal 
	 * @access public
	 */
	function addImportance($importance) {
		if (!$this->properties) return;
		if ($importance === 0) return;
		$keys = array_keys($this->properties);
		foreach ($keys as $key) {
			/* @var $propertyObject Property */
			$this->properties[$key]->addImportance($importance);
		}
	}

	/************************ Setting code *******************/

	/**
	 * This adds an importance offset to everything in it: this is to allow us to increase or decrease the 
	 * importance of this.
	 *
	 * @param integer $importance
	 * @internal 
	 * @access public
	 */
	function setImportance($importance) {
		if (!$this->properties) return;
		$keys = array_keys($this->properties);
		foreach ($keys as $key) {
			/* @var $propertyObject Property */
			$this->properties[$key]->setImportance($importance);
		}
	}

	/**
	 * Rebrand all aspects of this with the default brand.
	 *
	 * @param string $brand
	 * @access public
	 * @internal 
	 * 
	 */
	function rebrand($brand="") {
		$this->addBrand($brand,true,true);
	}

	function getValueArrayFromExampleDP(Datapoint $datapoint) {
		// collect DPs to analyse from properties
		$property = $datapoint->property;
				
		if (!isset($this->properties[$property])) {
			return array();
		}
		$propertyObject = $this->properties[$property];
		$datapoints = $propertyObject->getValueFromExampleDP($datapoint);
		return $datapoints;
	}

	function setSource($source) {
		$propKeys = array_keys($this->properties);
		foreach ($propKeys as $prop) {
			if (is_object($this->properties[$prop])) {
				$this->properties[$prop]->setSource($source);
			}
		}
	}
	
	/************************ Formatting code ****************/
	/**
	 * Array representation of object
	 *
	 * @return array
	 * @access public
	 * @internal
	 * @deprecated
	 */
	function toArray() {
		$output=array();
		foreach ($this->properties as $propertyObject) {
			$tmp = $propertyObject->toArray();
			foreach ($tmp as $tmpString) {
				array_push($output,$tmpString);
			}
		}
		return $output;
	}


	/**
	 * XML Representation of this object.
	 *
	 * @return string
	 * @access public
	 * @internal
	 * @deprecated
	 */
	function toXML() {
		$output=array();
		$properties=$this->properties;
		foreach ($properties as $propertyObject) {
			/* @var $propertyObject Property */
			$output=array_merge($output,$propertyObject->toXMLArray());
		}
		return $output;

	}

	/**
	 * More complex XML representation of this.
	 *
	 * @param string $labels	List of labels to restrict to.
	 * @return string
	 * @deprecated
	 * @internal
	 * @access public
	 */
	function toXMLSummary($labels="") {
		if (!$labels) $labels=array();
		$properties=$this->properties;
		$output=array();
		foreach ($properties as $propertyObject) {
			/* @var $propertyObject Property */
			$result=$propertyObject->toXMLSummary($labels);
			if (is_array($result)) {
				array_push($output,$result);
			}
		}
		$metadata=$this->metadata;
		foreach ($metadata as $key=>$value) {
			if (is_array($value)) {
				foreach ($value as $value2) {
					$value2=str_replace("&","&amp;",$value2);
					$value2=str_replace("<","&lt;",$value2);
					$value2=str_replace("<","&gt;",$value2);
					$output[]="<metadata key=\"$key\" value=\"$value2\" />";
				}
			} else {
				$value=str_replace("&","&amp;",$value);
				$value=str_replace("<","&lt;",$value);
				$value=str_replace("<","&gt;",$value);

				$output[]="<metadata key=\"$key\" value=\"$value\" />";
			}
		}
		return $output;
	}

	/**
	 * Full summary in a \n delimited string
	 * This is slightly different behaviour to the other objects which output short descriptions.
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
	function toStringArray($path="//",$optimize=false) {
		// convert to a nice database friendly \n delimited string
		$output=array();
		$properties=$this->properties;
		foreach ($properties as $propertyObject) {
			/* @var $propertyObject Property */
			$dps = $propertyObject->toStringArray($optimize);
			foreach ($dps as $dp) {
				array_push($output,$path.$dp);
			}
		}
		return $output;
	}
	
	function toString() {
		$array = $this->toStringArray();
		return implode("\n",$array);
	}
	
	function propSize() {
		if (!is_array($this->properties)) return 0;
		return count($this->properties);
	}
	
	function close() {
		$keys = array_keys($this->properties);
		foreach ($keys as $key) {
			if (is_object($this->properties[$key])) {
				$this->properties[$key]->close();
			}
			unset($this->properties[$key]);
		}
		unset($this->properties);
	}
	
	function __destruct() {
		if (!isset($this->properties) || !is_array($this->properties)) return;
		$keys = array_keys($this->properties);
		foreach ($keys as $key) {
			$this->properties[$key] = null;
			unset($this->properties[$key]);
		}
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$obj = new QuantumDataCollection();
		foreach ($array as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
	}
}