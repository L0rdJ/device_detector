<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    datapoint.class.php
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
2.3.0 - big hidden in "merge" with datapoint->min
2.5.1 - removed inactive mergeWith command (functionality already in "merge"). 
2.5.1 - tweaked the getDataStatus to remove some redundant string handling
2.8.0 - changed offset message in brand stuff (damn)
2.8.0 - another change to check for empty brands before adding
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("Datapoint");
}

// essentially this is the meat of a schema property padded with context information
Class Datapoint {
	/**
	 * How is "trust" related to importance?
	 *
	 * @static integer 
	 * @access public
	 */
	static $TRUST_IMPORTANCE_MULTIPLIER=10;

	public $negate=false;
	public $brand;
	public $context=""; // within a PC. Not used much.
	public $property="";
	public $label="";
	public $access_count = 0;
	public $source="";
	public $importance=0;
	public $importanceOffset=0;
	public $trust="";
	public $command = "";
	public $locked=false;
	static $objFields = array("source","label","trust","value","units","flag","version","max","min","arg","partofset","type","importance","importanceOffset");
	
	// datapoint level fields, the result of parsing
	public $value="";
	public $units="none";
	public $flag;
	public $version;
	public $max;
	public $min;
	public $arg;
	public $partofset=0;
	public $type="none";
	public $hash="";
	
	public function __sleep() {
		$ov = get_object_vars($this);
		unset($ov['parsed']);
		unset($ov['access_count']);
		unset($ov['locked']);
		return array_keys($ov);
	}
	
/*	static function newDatapoint($map,$parse=false) {
		$map = str_replace("://","{cdbs}",$map);
		$tmp = explode("//",$map);
		$command = array_pop($tmp);
		$path = implode("//",$tmp);
		$command = str_replace("{cdbs}","://",$command);
		$path = str_replace("{cdbs}","://",$path);
		$datapoint = new Datapoint($path,$command);
		if ($parse) {
			$datapoint->parse();
			$datapoint->parseValue();
		}
		return $datapoint;
	}*/
	
	public function toArray() {
		return get_object_vars($this);
	}

	public function recordAccess() {
		$this->access_count = $this->access_count + 1;
	}
	
	public function getAccessCount() {
		return $this->access_count;
	}
		
	public function addBrand($brand) {
		if (!is_array($this->brand)) {
			$this->brand = array();
			return;
		}
		if (!in_array($brand,$this->brand)) {
			$this->brand[] = $brand;
		}
	}

	// returns "1" for positive data, and "0" for negative data;
	public function getDataStatus() {
		if ($this->flag === 0) return 0;
		if ($this->flag === "0") return 0;
		$commandStripped = strtolower(str_replace(" ","",$this->command));
		if ($commandStripped === "status=0") return 0;
		if ($commandStripped == "status" && !DRFunctionsCore::isEmpty($this->wildcard)) {
			if ($this->wildcard === "0" || $this->wildcard ===0) return 0;
		}
		if (strpos($this->command,"->0") !== false || strpos($this->command("0:")) !== false) {
			
			if (strpos($commandStripped,"status->0") !== false) {
				return 0;
			}
			if (strpos($commandStripped,"sc->0:") !== false) {
				return 0;
			}
			if (strpos($commandStripped,"|0:") !== false) {
				return 0;
			}
		}
		return 1;
	}
	/*
		if (this.command.equals("status") && this.wildcard != null) {
			if (this.wildcard instanceof String && this.wildcard.toString().equals("0")) return 0;
			if (String.valueOf(this.wildcard).equals("0")) return 0;
		}
		if (Functions.str_replace(" ","",this.command).contains("status->0")) {
			return 0;
		}
		return 1;
*/
	public function compliesWithDP(Datapoint $datapoint) {
		// does this datapoint comply with the parameters of the incoming one: kind of a query by example
		$fieldsToDo = array("units","flag","version","max","min","value");
		$comparitors = array("<>","!=",">=","<=","=","!","<",">");
		foreach ($fieldsToDo as $field) {
			$comparitor = "=";
			$queryValue = $datapoint->$field;
			if ($queryValue === null) continue; // null in a datapoint, doesn't matter.
			$compareValue = $this->$field;
			if (strpos($queryValue,"*") !== false) continue;
			foreach ($comparitors as $testComparitor) {
				$len = strlen($testComparitor);
				if (substr($queryValue,0,$len) == $testComparitor) {
					$comparitor = $testComparitor;
					$queryValue = substr($queryValue,$len);
					break;
				}
			}
			
			if ((DRFunctionsCore::isEmptyStr($queryValue) || DRFunctionsCore::isEmptyStr($compareValue)) && $comparitor === "=") continue;
			switch ($comparitor) {
				case '<>':
				case '!=':
				case '!':
					$return = ($queryValue !== $compareValue);
					break;
				case '>':
					$return = ($compareValue > $queryValue);
					break;
				case '<':
					$return = ($compareValue < $queryValue);
					break;
				case '>=':
					$return = ($compareValue >= $queryValue);
					break;
				case '<=':
					$return = ($compareValue <= $queryValue);
					break;
				default:
					$return = ($compareValue == $queryValue);
			}
			if ($return === false) return false;
		}
		return true;
	}
	
	public function setSource($source) {
		$this->source = $source;
	}
	
	public function getWildcardField() {
		$fieldsToDo = array("value","max","flag","version","min");
		foreach ($fieldsToDo as $field) {
			if ($this->$field === null) continue;
			if ($this->$field === "*") return $field;
			if (strpos($this->$field,"*") !== false) return $field;
		}
		return "";
	}

	public function setWildcard($value) {
		if ($this->negate && (array) $value !== $value) {
			$testBool = Validator::validate("boolean",$value);
			if ($testBool !== null) {
				$value = !$testBool;
			}
		}
		$this->wildcard = $value;
	}
	
	public function toString() {
		//return http_build_query($this);
		$vars = get_object_vars($this);
		unset($vars['wildcard']);
		unset($vars['path']);
		$context = $vars['context'];
		$label = $vars['label'];
		$property = $vars['property'];
		unset($vars['context']);
		unset($vars['property']);
		unset($vars['label']);
		unset($vars['map']);
		unset($vars['validated']);
		unset($vars['command']);
		unset($vars['negate']);
		unset($vars['parsed']);
		unset($vars['access_count']);
		unset($vars['locked']);
		unset($vars['importance']);
		unset($vars['importanceOffset']);
		// prioritise the most important features of this datapoint.
		$args = array("importance:".$this->getImportance());
		foreach ($vars as $key=>$value) {
			if (!is_scalar($value)) continue;
			if ($value !== null && $value !== '') {
				if ($value === true || $value === false) {
					$value = $value ? "1" : "0";
				} else {
					$value = str_replace(";","\\;",$value);
				}
				$args[] = "$key:$value";
			}
		}
		$output = $property."=".$label."{".$args = implode("; ",$args)."}";
		if ($context !== null && $context !== '') {
			$output = $context.".".$output;
		}
		return $output;		
	}
	
	public function fromString($string) {
		$args = array();
		parse_str($string,$args);
		foreach ($args as $key=>$value) {
			if (property_exists($this,$key)) {
				$this->$key = $value;
			}
		}
	}
	
	public function __construct($command="",$wildcard="") {
		if ($command === "") return;
		if (substr($command,0,1) == "!") {
			$this->negate = true;
			$command = substr($command,1);
			if (strpos($command,"status=1") !== false) {
				$command = str_replace("status=1","status=0",$command);
			} elseif (strpos($command,"status=0") !== false) {
				$command = str_replace("status=0","status=1",$command);
			}
		}
		if (strpos($command,"|") !== false) {
			$tmp = explode("|",$command);
			$command = $tmp[0];
			$this->type = $tmp[1];
		}
		$this->command = $command;
		$this->wildcard = $wildcard;
		$this->parse();
		$this->parseValue();
	}

	public function hash() {
		if (!$this->hash) {
			$this->hash = md5($this->toString());
		}
		return $this->hash;
	}
	
	public function addImportance($importance) {
		$this->importanceOffset = $this->importanceOffset + $importance;
	}
	
	public function setImportance($importance) {
		$this->importance = $importance;
	}
	
	public function getImportance() {
		if ($this->importance === 0 && DRFunctionsCore::in("importance",$this->command)) {
			$impString = substr($this->command,stripos($this->command,"importance->") + strlen("importance->"));
			$pos = strpos($impString,"&/&");
			if ($pos !== false) {
				$impString = substr($impString,0,$pos);
			}
			if (is_numeric($impString)) $this->importance = (int) $impString;
		}
		return $this->importance + $this->importanceOffset;
	}

	public function initValues() {
		$this->value = "";	
		$this->min = "";
		$this->max = "";
		$this->flag = 0;
		//$this->source="";
		//$this->brand = array();
		$this->type="none";
		$this->context=""; // within a PC. Not used much.
		$this->property="";
		$this->label="";
		$this->units = "none";
		$this->version = "";
		$this->arg = 0;
		$this->partofset=0;
	}
	
	public function parse() {
		$this->initValues();
		$parts = explode("=",$this->command,2);
		if (count($parts) < 2) return false;
		$propertyPart = $parts[0];
		$valuePart = $parts[1];

		$this->value = $valuePart;
		// property parts: the last one is the property name, everything before that
		// is a context. Things in the map override the context in the inputPoint.
		if (strpos($propertyPart,".") !== false) {
			$tmp = explode(".",$propertyPart);
			$this->property = array_pop($tmp);
			$this->context = implode(".",$tmp);
		} elseif (strpos($propertyPart,"}") !== false) {
			$tmp = explode("}",$propertyPart);
			$this->property = array_pop($tmp);
			$this->context = str_replace("{","",implode(".",$tmp));
		} else {
			$this->property = $propertyPart;
			$this->context = "";
		}
		return true;
	}
	
	public function parseValue() {
		if (DRFunctionsCore::isEmptyStr($this->command)) return; 
		$bv = new boolean_validator("boolean_validator");
		$valuePart = $this->value;
		$this->value = "";
		$tmp = explode("{",$valuePart,2);
		$this->label = array_shift($tmp);
		if (!isset($tmp[0])) return;
		$dataPart = array_shift($tmp);
		$dataPart = str_replace("}",";",$dataPart);
		// get keys to check in this object
		$fields = self::$objFields; // fields we've got		
		// for each field, look for it in the data part
		foreach ($fields as $field) {
			$pos = strpos($dataPart,$field.":");
			$fieldLen = strlen($field)+1;
			if ($pos === false) {
				continue;
			}
			$pos = $pos + $fieldLen;
			$endpos = false;
			$marker = $pos+1;
			while ($endpos === false) {
				$endpos = strpos($dataPart,";",$marker);
				if ($endpos === 0) {
					trigger_error("Zero position semicolon endpos in $dataPart for field $field",E_WARNING);
					continue; // this is very odd.
				}
				if ($endpos === false) {
					//syntax error, but we'll just take what we have
					trigger_error("Datapart fragment with missing semicolon/curly bracket $dataPart for field $field",E_USER_WARNING);
					$endpos = strlen($dataPart);
					break;
				}

				// check to see of this is escaped.
				$checkChar = substr($dataPart,$endpos-1,1);
				if ($checkChar ==="\\") {
					$marker = $endpos + 1;
					$endpos = false;
				}
			}
			$value = trim(substr($dataPart,$pos,$endpos - $pos));
			$value = str_replace("\\","",$value);
			if ($field === "flag" && $value !== "*" && $value !== "!*") {
				$value = $bv->process($value);
			}
			if ($value === null) {
				continue;
			}
			if ($value === ";") $value="";

			$this->$field = $value;
		}
		$this->parsed=true;
		$this->command = "";
	}

	/**
 * Stuff that helps out the PropertyCollection object.
 * Although I don't like putting it here, I'm not sure where else would be appropriate.
 *
 * Anyway, this takes a look with a "*" in it which is a value, and inserts it appropriately
 *
 * @param array $loop
 * @param string $value
 * @param string $validation_type
 * @return array
*/
	function processValue() {
		if (!property_exists($this,"wildcard") || DRFunctionsCore::isEmpty($this->wildcard)) return;
		// purpose here: to insert the value into the map variable.
		$keysToUse=array("value","max","min","flag","type","version");
		$value = trim($this->wildcard);
		
		foreach ($keysToUse as $field) {
			$valueToUse = $this->$field;
			$pos = strpos($valueToUse,"*");
			if ($pos !== false) {
				$value = trim($value);
				if ($pos > 0) {
					$prefix = substr($valueToUse,0,$pos);
					switch ($prefix) {
						case '!':
							$valueToUse = substr($valueToUse,1);
							$value = !($value);
							break;
					}
				}
				switch ($valueToUse) {
					case 'pow(2,*)':
						$value = pow(2,$value);
						$valueToUse = "*";
						break;
					case 'root(2,*)':
						$str = base_convert($value, 10, 2);
						$value = strlen($str) -1;
						$valueToUse = "*";
						break;
				}
				if ($field === "flag") {
					$value = Validator::validate("boolean",$value);
				}
				$value = str_replace("\\","",$value);
				$this->$field = str_replace("*",$value,$valueToUse);
				$this->wildcard="";
				break;
			}
		}		
	}
	
	/**
	 * Datapoint
	 *
	 * @param mixed $value
	 * @param boolean $flag
	 * @param mixed $min
	 * @param mixed $max
	 * @param string $units
	 * @param string $version
	 * @param integer $importance
	 * @param boolean $partOfSet
	 * @param string $brand
	 * @return Datapoint
	 * @access public
	 * @questionable
	 */
	static function dataPointFromParts($value,$flag,$min,$max,$units,$version,$importance=1,$partOfSet=0,$brand="") {
		$datapoint = new Datapoint("","");
		$datapoint->value=$value;
		$datapoint->min=$min;
		$datapoint->max=$max;
		if (DRFunctionsCore::is_expression($flag)) $flag=eval($flag);
/*		if (DRFunctionsCore::is_expression($max)) $flag=eval($max);
		if (DRFunctionsCore::is_expression($min)) $flag=eval($min);
		if (DRFunctionsCore::is_expression($value)) $flag=eval($min);*/
		$datapoint->flag=$flag;
		$datapoint->units=$units;
		$datapoint->version=$version;
		$datapoint->importance=$importance; // used for sorting.
		if ($partOfSet===true) $partOfSet=1;
		if ($partOfSet===false) $partOfSet=0;
		$datapoint->partofset=$partOfSet;
		if ($brand) $datapoint->brand=array($brand);
		return $datapoint;
	}
	
	/**
	 * Match this datapoint: does it fulfil the criteria, and does it have the $need field?
	 *
	 * @param array $criteria
	 * @param string $need
	 * @return boolean

	 * @access public
	 * ??? - is this still needed? I'm not entirely sure it is.
	 */
	function match($criteria,$need) {
		$fields=get_object_vars($this);
		$match=true;
		unset($criteria['partofset']); // we don't need to compare on this

		if (!isset($fields[$need]) || $fields[$need] === null || $fields[$need]==="") {
			$match=false;
		}
		
		if ($match) {
			foreach ($criteria as $method=>$value) {
				if (isset($fields[$method])) {
					if (!DRFunctionsCore::isEqual($fields[$method],$value)) {
						$match=false;
						break;
					}
				}
			}
		}		
		// special case: in some cases, when asking if a datapoint is valid, we might exclude a datapoint
		// without a version but which invalidates with a flag=0 the other datapoints. We should leave this in.
		if ($this->flag === 0 || $this->flag ===false) {
			if (DRFunctionsCore::isEmptyStr($this->version) && $this->units == DRFunctionsCore::gv($criteria,'units') && $this->value === DRFunctionsCore::gv($criteria,'value')) {
				$match=true;
			}
		}
		return $match;
	}

	/**
	 * Are the two datapoints identical??
	 *
	 * @param Datapoint $valueObject
	 * @return boolean
	 * @access public
	 */
	function isIdentical($newDatapoint) {
		// later on
		$log = DetectRight::$LOG;
		if ($log) echo "Checking values\n";
		if (!DRFunctionsCore::isEqual($this->value,$newDatapoint->value)) return false;
		if ($log) echo "Checking max\n";
		if (!DRFunctionsCore::isEqual($this->max,$newDatapoint->max)) return false;
		if ($log) echo "Checking min\n";
		if (!DRFunctionsCore::isEqual($this->min,$newDatapoint->min)) return false;
		if ($log) echo "Checking units\n";
		if (!DRFunctionsCore::isEqual($this->units,$newDatapoint->units)) return false;
		if ($log) echo "Checking flag\n";
		if (!DRFunctionsCore::isEqual($this->flag,$newDatapoint->flag)) return false;
		if ($log) echo "Checking version\n";
		if (!DRFunctionsCore::isEqual($this->version,$newDatapoint->version)) return false;
		return true;
	}
	
	/**
	 * Merge this datapoint with another datapoint
	 *
	 * @param Datapoint $datapoint
	 * @access public
	 */
	function merge($datapoint) {
		// first, merge conflicting values
		if (!DRFunctionsCore::isEmptyStr($this->version)) {
			$version=explode(",",$this->version);
		} else {
			$version=array();
		}
		$better=($datapoint->getImportance() > $this->getImportance());
		
		if (DRFunctionsCore::isEmptyStr($this->max) && !DRFunctionsCore::isEmptyStr($datapoint->max)) $this->max=$datapoint->max;
		if (DRFunctionsCore::isEmptyStr($this->min) && !DRFunctionsCore::isEmptyStr($datapoint->min)) $this->min=$datapoint->min;
		if (DRFunctionsCore::isEmptyStr($this->version) && !DRFunctionsCore::isEmptyStr($datapoint->version) && !in_array($datapoint->version,$version)) $version[] = $datapoint->version;
		if (DRFunctionsCore::isEmptyStr($this->value) && !DRFunctionsCore::isEmptyStr($datapoint->value)) $this->value=$datapoint->value;

		if (!DRFunctionsCore::isEmptyStr($datapoint->version)) {
			if (array_search($datapoint->version,$version) === false) $version[]=$datapoint->version;
		}
		if ((array) $this->brand !== $this->brand) $this->brand=array();
		if ((array) $datapoint->brand === $datapoint->brand) $this->brand = array_merge($this->brand,$datapoint->brand);
		if ($better) {
			if (!DRFunctionsCore::isEmptyStr($datapoint->value)) {
				$this->value=$datapoint->value;
				$this->importance=$datapoint->importance;
				$this->importanceOffset = $datapoint->importanceOffset;
				if (!DRFunctionsCore::isEmptyStr($datapoint->flag)) $this->flag=$datapoint->flag;
			}
			if (!DRFunctionsCore::isEmptyStr($datapoint->max)) {
				$this->max=$datapoint->max;
			}
			
			if (!DRFunctionsCore::isEmptyStr($datapoint->min)) {
				$this->min=$datapoint->min;
			}
		}
		$this->version=implode(",",$version);
	}
	
	function brand($brand) {
		if ((array) $this->brand !== $this->brand) $this->brand=array();
		if (!in_array($brand,$this->brand)) {
			$this->brand[] = $brand;
		}
	}
	
	function isFlagOnly() {
		if (!DRFunctionsCore::isEmptyStr($this->value)) return false;
		if (!DRFunctionsCore::isEmptyStr($this->max)) return false;
		if (!DRFunctionsCore::isEmptyStr($this->min)) return false;
		if (!DRFunctionsCore::isEmptyStr($this->units)) return false;
		///if ($this->partofset === 1) return false;
		if (!DRFunctionsCore::isEmptyStr($this->version)) return false;
		return true;
	}
	
	static function dealWithArgs($datapoints,$key) {
		// check for datapoints that have the same brand and source but which are args,
		// then stitch them together into the arg 1 datapoint.
		$args = array();
		foreach ($datapoints as $hash=>$datapoint) {
			if (!DRFunctionsCore::isEmptyStr($datapoint->arg) && $datapoint->arg > 0) {
				$id = $datapoint->source."/".$datapoint->trust."/".$datapoint->getImportance();
				$brand = null;
				if (isset($datapoint->brand[0])) {
					$id = $datapoint->brand[0]."/".$id;
				} 
				if (!isset($args[$id])) $args[$id] = array();
				$args[$id][] = $hash;
			}
		}
		
		foreach ($args as $hashArray) {
			$arg = array();
			$firstArg = "";
			foreach ($hashArray as $hash) {
				$dp = $datapoints[$hash];
				$argInt = (int)$dp->arg;
				if ($argInt === 1) {
					$firstArg = $hash;
				} else {
					$arg[$argInt] = $dp->$key;
					unset($datapoints[$hash]);
				}
			}
			
			if (!DRFunctionsCore::isEmptyStr($firstArg)) {
				$dp = $datapoints[$firstArg];
				$argArray = array($dp->$key);
				ksort($arg);
				foreach ($arg as $argInt=>$value) {
					$argArray[] = $value;
				}
				$argValue = implode("x",$argArray);

				$dp->$key = $argValue;
				$dp->arg = 0;

				// now the hash of this datapoint has just changed!
				$newHash = $dp->hash();
				unset($datapoints[$firstArg]);
				$datapoints[$newHash] = $dp;
				
			}
		}
		
		return $datapoints;
	}
	
	/**
	 * This is the function which sorts out the datapoints passed in. It gives back an array of the datapoints passed in
	 * scored appropriately.
	 *
	 * @param Datapoint[] array of datapoints
	 * @param string $key field in datapoint needed for comparison
	 * @param string $sortKey Additional sort key for datapoints with the same value
	 * @return Datapoint[]
	 * @access public
	 */
	static function sortDatapoints($datapoints,$key,$sortKey = "version",$sortKeyDescending = false) {
		//$datapoints=$this->datapoints;
		if (!property_exists(__CLASS__,$key)) return array();
		if (count($datapoints) === 0) return array();
		if (count($datapoints) === 1) {
			$datapoint = array_shift($datapoints);
			return array($datapoint);
		}

		$datapoints = self::dealWithArgs($datapoints,$key);	
		// this creates final importance levels for each datapoint
		// originally it just picked a value/version to use.
		// the datapoints here should already be heterogenous in terms of 
		// units, so this function isn't going to do anything about that.
		// for sanity's sake, it will also have to avoid doing anything with
		// other fields in the datapoint other than the one asked for: though
		// we used to use "version", which complicated things a fair bit.
				
		$output = array();
		$return=array();
		$done=array();
		$frequency=array();
		$importances = array();
		$seenValues=array();
		$dps = array();
		// first, arrange datapoints generally in order of importance: this ensures that strong datapoints
		// come first: useful for weeding out the crap from fallback data points.
		foreach ($datapoints as $hash=>$datapoint) {
			$importances[$hash] = $datapoint->getImportance();
		}
		
		arsort($importances);
		$dpKeys = array_keys($importances);
		$importances = array();
		// we build a frequency table of seen values
		foreach ($dpKeys as $dpKey) {
			$datapoint = $datapoints[$dpKey];
			$source = $datapoint->source;
			$value = $datapoint->$key;
			if (DRFunctionsCore::isEmptyStr($value)) {
				continue;
			}
			$doneKey = md5($value);
			$seenValue = md5($value."/".$source);
			
			if (array_search($doneKey,$done) === false) {				
				// we haven't seen this value before
				// $results is a list of value importances.
				// misusing a datapoint here
				$newDP = new Datapoint("","");
				$importance = $datapoint->getImportance();
				$newDP->importance = $importance;
				$newDP->brand = $datapoint->brand;
				$newDP->$key = $value; // FIX
				// return is the actual values.
				$return[$doneKey]=$newDP;
				$done[]=$doneKey;
				$frequency[$doneKey]=1;
				$importances[$doneKey] = $importance;
			} else {
				// here, we're trying to stop a particular datasource agreeing with itself numerous times: for instance, loads of UAProfiles
				// that contain a wrong value. A particular value from a datasource should only be counted once.
				if (!isset($seenValues[$seenValue])) {
					$frequency[$doneKey]++;
				}
			}
			$seenValues[$seenValue]=true;
			if (!isset($dps[$doneKey])) $dps[$doneKey] = array();
			$dps[$doneKey][] = $datapoint;
		}

		if (count($return) === 1) {
			return $datapoints;
			//return array_values($return);
		}

		// now frequency contains the most frequent values, and $done contains the 
		// hashlist of values. $importance contains the most "important" values.
		//arsort($frequency);
		//arsort($importances);
	
		//$frequencyRanks = array_keys($frequency);
		//$importanceRanks = array_keys($importances);
		$scores=array();
		foreach ($done as $doneKey) {
			$datapoint = $return[$doneKey];
			
			$score = self::getScore($datapoint->getImportance(),$frequency[$doneKey]);
			$scoreInt = floor($score + 0.5);
			$scores[$doneKey] = $scoreInt;
		}

		// check to see if there's a tie: if that happens then sort-order variations
		// between platforms might lead to inconsistent results
		$max = 0;
		foreach ($scores as $score) {
			if ($score > $max) $max = $score;
		}
			
		$ties = array();
		foreach ($scores as $scoreHash=>$score) {
			if ($score === $max) $ties[] = $scoreHash;
		}
		
		if (count($ties) > 1) {
			// tiebreak needed. We probably just need to go with the biggest number and inflate its score
			$maxHash = "";
			$maxValue = "";
			foreach ($ties as $tieHash) {
				$tieValue = DRFunctionsCore::nn($return[$tieHash]->$key);
				if ($tieValue > $maxValue) {
					$maxValue = $tieValue;
					$maxHash = $tieHash;
				}
			}
			// the next thing should always be true.
			if ($maxHash !== "") {
				$scores[$maxHash]++;
			} 
		}
		// how many things have max?
		arsort($scores);
		$scoreKeys = array_keys($scores);
		foreach ($scoreKeys as $dpKey) {
			$dpsToPush = $dps[$dpKey];
			if ($sortKey !== "" && count($dpsToPush) > 1) {
				$dpsToPush = self::sortByField($dpsToPush,$sortKey,$sortKeyDescending);
			}
			foreach ($dpsToPush as $datapoint) {
				array_push($output,$datapoint);
			}
		}
		
		return $output;
	}
	
	/**
	 * Sorts a collection of datapoints by a field within it.
	 *
	 * @param Datapoint[] $datapoints
	 * @param string $field class member to sort by
	 * @param boolean $descending whether to do descending sort
	 * @return Datapoint[]
	 */
	static function sortByField($datapoints,$field,$descending = false) {
		if (!property_exists(__CLASS__,$field)) return $datapoints;
		$output = array();
		$array = array();
		foreach ($datapoints as $key=>$datapoint) {
			$array[$key] = $datapoint->$field;
		}
		
		if ($descending) {
			arsort($array);
		} else {
			asort($array);
		}
		
		$keys = array_keys($array);
		foreach ($keys as $key) {
			array_push($output,$datapoints[$key]);
		}
		return $output;
	}
	
	// PHP SPECIFIC, like the fill in Java
	static public function __set_state($array) {
		$obj = new Datapoint("","");
		foreach ($array as $key=>$value) {
			$obj->$key = $value;
		}
		return $obj;
	}

	static public function getScore($importance,$frequency,$importanceCeiling = 100) {

		// frequencyWeighting: large means "give frequency great weight", small means "give frequency small weight"
		// 0 = disregard frequency.
		
		if ($frequency <= 0) return 0;
		if ($importance <= 0) return 0;
		// maximum importance of value
		
		$expNum1 = 1;
		$expNum2 = 1;
		$expNum3 = 1;
		$offset1 = 1;
		$offset2 = 1;
		$offset3 = 1;
		if ($importance >= $importanceCeiling) {
			// above the ceiling, frequency is irrelevant
			$frequencyWeightingPct = 0;
		} else {
			$frequencyWeightingPct = (($importanceCeiling - $importance)/$importanceCeiling);
			// the further from the ceiling, the higher the weighting should be
			//$frequencyWeightingPct = self::transformPCT2($frequencyWeightingPct,$expNum1,$offset1);
			//$frequencyWeightingPct = 0.25;
			$dec = floor($importance/10);
			$frequencyWeightingPct = ((($dec*10)/80)+20)/100;
			
		}
		
		// importance percantage
		$importanceWeight = (float)$importance/150;
		if ($importanceWeight > 1) $importanceWeight = 1;
		$importanceWeight = self::transformPCT($importanceWeight,$expNum2,$offset2);
		//$importanceWeight = exp($importance / $expCoef); // marks out of 150

		// frequency percentage, assuming max 25 things
		$dblFrequency = (float) $frequency/10;
		if ($dblFrequency > 1) $dblFrequency = 1;
		$dblFrequency = self::transformPCT($dblFrequency,$expNum3,$offset3);
		//$dblFrequency = exp($dblFrequency / $expCoef); // 50 people saying the same thing as a max?

		// base score on importance first
		$score = ($importanceWeight*(1-$frequencyWeightingPct) + ($dblFrequency*$frequencyWeightingPct ))*100;
		return round($score,2);
	}
	
	static function transformPCT($pct,$exp = 1,$offset = 1) {
		$newPCT = pow($pct,(1/$exp));
		$newPCT = ((exp($newPCT)-$offset)/(exp(1)-$offset));
		return $newPCT;
	}
	
	static function transformPCT2($pct,$exp = 1,$offset = 1) {
		$newPCT = pow($pct,(1/$exp));
		$newPCT = ((log($newPCT+$offset)/log(1+$offset)));
		return $newPCT;		
	}
}
/*
0.75
1,80,0,999
1,80,1,81
1,80,2,79
1,80,3,78
1,80,4,76
1,80,5,74
1,80,6,73
1,80,7,71
1,80,8,71
1,80,9,71
*/