<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    drprofileresult.class.php
Version: 2.3.1
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
	DetectRight::registerClass("DRProfileResult");
}

// this abstracts away all of the messy stuff with Literals and whatnot.
Class DRProfileResult {

	/* @var SchemaPropertyCore */
	private $_sp;
	private $_key;
	private $_valueLiteral; // will always be array
	private $_valueBoolean;
	private $_valueInteger;
	private $_valueFloat;
	private $_valueLiteralArray = array();
	private $_isBoolean = false;
	private $_isMissing = true;
	public $_boolFormat = "truefalse"; // This is an enum, really. Values are "Boolean","ZeroOne","TrueFalse" or "YesNo"
	private $_importanceArray = array();
	private $_importance = 0;
	private $source = "";
	private $type = "Literal";
		
	static public function newResult($key,$values,$importances,$sp) {
		$result = new DRProfileResult($key,$sp);
		if (DRFunctionsCore::isEmpty($values)) return $result;
		
		if ($result->isArray()) {
			if (!is_array($values)) {
				$values = array($values);
			}
			foreach ($values as $value) {
				$importance = DRFunctionsCore::gv($importances,$value,0);
				$result->setValue($value,$importance);
			}
		} else {
			if (is_array($values)) {
				$value = array_shift($values);
			} else {
				$value = $values;
			}
			$importance = array_shift($importances);
			$result->setValue($value,$importance);
		}
		return $result;
	}

	public function getType() {
		return $this->type;
	}
	
	public function setType($type) {
		$allowedTypes = array("Float","Literal","LiteralArray","Bytesize","Integer","Boolean","Dimension");
		if (!in_array($type,$allowedTypes)) return;
		$this->type = $type;
	}
	
	public function setSource($source) {
		$this->source = $source;
	}
	
	public function getSource() {
		return $this->source;
	}
	
	public function isArray() {
		if ($this->_sp === null) return false;
		return ($this->_sp->type === "LiteralArray");
	}
	
	public function setImportance($importance,$value="") {
		if (!is_numeric($importance)) return;
		if (!$this->isArray()) {
			if (DRFunctionsCore::isEmptyStr($value) || $value === $this->getValue()) {
				$this->_importance = $importance;
			} 
		} else {
			$this->_importanceArray[$value] = $importance;
		}
	}
	
	public function getImportance($value = "") {
		$isArray = $this->isArray();
		if (!$isArray) {
			if (DRFunctionsCore::isEmptyStr($value) || $value === $this->getValue()) {
				return $this->_importance;
			} else {
				return 0; // invalid ask.
			}
		}
		if ($value === null) return 0;
		if (!isset($this->_importanceArray[$value])) return 0;
		return $this->_importanceArray[$value];
	}
	
	public function __construct($key = "",$sp = "") {
		if (DRFunctionsCore::isEmpty($key)) return;
		$this->_key = $key;
		if (is_object($sp)) {
			$this->_sp = $sp;
			$this->type = $sp->type;
			$this->_isBoolean = ($sp->type === "Boolean");
		}
		//$this->setValue($value,$importance);
	}
	
	public function isMissing() {
		return $this->_isMissing;
	}
	
	public function getDefaultValue() {
		if (!$this->_sp) return null;
		return $this->_sp->default_value;
	}
	
	public function getKey() {
		return $this->_key;
	}
	
	public function setKey($key) {
		$this->_key = $key;	
	}
	
	public function setSP($sp) {
		$this->_sp = $sp;
	}
	
	public function getSP() {
		return $this->_sp;
	}
	
	public function getValue() {
		if ($this->_isMissing) return null;
		switch ($this->getType()) {
			case 'Boolean':
				return $this->getBoolString();
			case 'LiteralArray':
				return $this->_valueLiteralArray;
			case 'Float':
				return (string) $this->_valueFloat;
			case 'Integer':
				return (string) $this->_valueInteger;
			case 'Dimension':
			case 'Literal':
			default:
				return $this->_valueLiteral;
		}
		$return = null;
		return $return;
	}

	public function getDimensionX() {
		if (!$this->isDimension()) return null;
		$tmp = explode("x",$this->_valueLiteral);
		if (!isset($tmp[1])) return null; // not a dimension;
		return $tmp[0];
	}

	public function getDimensionY() {
		if (!$this->isDimension()) return null;
		$tmp = explode("x",$this->_valueLiteral);
		if (!isset($tmp[1])) return null; // not a dimension;
		return $tmp[1];
	}

	public function getDimensionZ() {
		if (!$this->isDimension()) return null;
		$tmp = explode("x",$this->_valueLiteral);
		if (!isset($tmp[2])) return null; // not a dimension;
		return $tmp[2];
	}

	public function isDimension() {
		if (!$this->_sp) return false;
		return ($this->_sp->type === "Dimension");
	}
	
	public function getBoolString() {
		if (!$this->isBoolean()) return null;
		switch ($this->_boolFormat) {
			case 'YesNo':
				$return = $this->_valueBoolean ? "Yes" : "No";
				break;
			case 'yesno':
				$return = $this->_valueBoolean ? "yes" : "no";
				break;
			case 'ZeroOne':
				$return = $this->_valueBoolean ? "1": "0";
				break;
			case 'TrueFalse':
				$return = $this->_valueBoolean ? "True" : "False";
				break;
			case 'truefalse':
				$return = $this->_valueBoolean ? "true" : "false";
				break;
			case 'Boolean':
				$return = $this->_valueBoolean ? true : false;
				break;
		}
		return $return;
	}
	
	public function setValue($value,$importance) {
		$this->_isMissing = (DRFunctionsCore::isEmpty($value));
		switch ($this->getType()) {
			case 'LiteralArray':
				$this->_valueLiteralArray[] = $value;
				$this->_importanceArray[$value] = $importance;
				break;
			case 'Float':
				$this->_valueFloat = $value;
				$this->_importance = $importance;
				break;
			case 'Integer':
			case 'ByteSize':
				$this->_valueInteger = $value;
				$this->_importance = $importance;
				break;
			case 'Boolean':
				$this->_valueBoolean = (Validator::validate("boolean",$value) == "1") ? true : false;
				$this->_importance = $importance;
				break;
			case 'Dimension':
			case 'Literal':
			default:
				$this->_valueLiteral = $value;
				$this->_importance = $importance;
		}
	}
		
	public  function addSP($sp) {
		if (!is_object($sp)) return;
		$this->_sp = $sp;
		$this->_isBoolean = ($sp->type === "Boolean");
	}
	
	public function isBoolean() {
		return $this->_isBoolean;
	}
	
	public function matches($value) {
		// TODO
		return true;
	}
}