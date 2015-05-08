<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    dataquery.class.php
Version: 2.3.0
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
2.3.0 Minor null check in processConcatQuery

**********************************************************************************/

Class DataQuery {
	public $map;
	public $path;
	public $command;
	public $type;
	public $rootQDT;
	public $qdt;
	public $queryType="qdc"; // "boolean","concatenation","qdc"
	public $datapoint;
	public $result=array();
	public $array=true;
	public $useSubtree = false; // is this query "greedy"?

	static $dbLink;
	static $cacheLink;
	
	public function __construct($map) {
		$this->cacheDB();		
		$tmp = explode("//",$map);
		$this->command = array_pop($tmp);
		$this->checkForExpression();
		$this->path = $tmp;
		$datapoint = new Datapoint($this->command);
		if (!DRFunctionsCore::in("flag",$this->command)) {
			$datapoint->flag = 1;
		}
		$datapoint->processValue();
		$this->datapoint = $datapoint;
	}
	
	public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
	}
	
	public function __wakeup() {
		$this->cacheDB();
	}
	
	public function __sleep() {
		$ov = get_object_vars($this);
		return array_keys($ov);
	}

	public function checkForExpression() {
		$command = $this->command;
		if (substr($command,0,1) === "(") {
			if (substr($command,-1,1) === ")") {
				if (strpos($command,";") !== false) {
					$this->queryType = "concatenation";
				} else {
					$this->queryType = "boolean";
				}
				$this->command = substr($command,1,-1);
			} else {
				trigger_error("Command $command is invalid",E_USER_WARNING);
			}
		}
		if ($this->queryType == "qdc" && strpos($command,"=") === false) {
			$this->queryType = "direct"; // applied against objects or entities
		}
	}
	
	// following two work out what kind of query to run
	public function queryQDTWithDescriptor(QuantumDataTree $qdt) {
		$this->rootQDT = $qdt;
		$this->qdt = new QuantumDataTree("",null);
		$qdt->getQDTWithDescriptor($this->qdt,$this->path);
		$this->process();
	}
	
	public function queryQDTWithPath(QuantumDataTree $qdt) {
		// hmm. This will extract results from a QDT according to this.
		$this->rootQDT = $qdt;
		$this->qdt = $qdt->getQDT($this->path);
		$this->process();		
	}

	function process() {
		// work out query type
		if (is_null($this->qdt)) {
			return;
		}
		$this->qdt->useSubtree = $this->useSubtree;
		switch ($this->queryType) {
			case 'boolean':
				$this->processBooleanQuery();
				break;
			case 'concatenation':
				$this->processConcatQuery();
				break;
			case 'qdc':
				$this->processQDCQuery();
				break;
			case 'direct':
				$this->processDirectQuery();
				break;
		}
	}

	//Browser//Language:Markup:WML//(majorrevision > 1.1)
	//Authorisation//DRM//(category; ;description; ;majorrevision)
	public function processBooleanQuery() {
		// All expression variables function on the tree beneath this one
		$command = $this->command;
		$firstChar = substr($command,0,1);
		$mode = "or";
		switch ($firstChar) {
			case '&':
				$mode = "and";
				$command = substr($command,1);
				break;
			case '!':
				$mode = "none";
				$command = substr($command,1);
				break;
			case '|':
				$mode = "or";
				$command = substr($command,1);
				break;
		}
		
		$result = $this->qdt->getBoolean($command,$mode);
		$this->result = array($result ? 1:0);
	}
	
	public function processConcatQuery() {
		// process a query which first returns a list of qdts which have a descriptor which has the required bits, then formats strings based on that
		// and returns the array;
		$concatArray = explode(";",$this->command);
		$compulsoryPresent = array();
		$compulsoryMissing = array();
		foreach ($concatArray as $key=>$value) {
			if (DRFunctionsCore::isEmptyStr($value)) continue;
			$char = $value{0};
			switch($char) {
				case '+':
					$value = substr($value,1);
					$concatArray[$key] = $value;
					$compulsoryPresent[] = $value;
					break;
				case '!':
					$value = substr($value,1);
					$concatArray[$key] = $value;
					$compulsoryMissing[] = $value;
					// must be empty, basically
					break;
			}
		}
		
		$descs = $this->qdt->getDescriptors(1,true);
		$descriptors = array();
		// go through descriptors picking maximum importance
		$importances = array();
		foreach ($descs as $key=>$descriptor) {
			if (($pos = strpos($descriptor,"%/%")) !== false) {
				$importance = substr($descriptor,$pos + 3);
				$descriptor = substr($descriptor,0,$pos);
				if (substr($descriptor,-1,1) === ":") {
					$descriptor = substr($descriptor,0,-1);
				}
				$md5Desc = md5($descriptor);
				if (!isset($importances[$md5Desc])) {
					$importances[$md5Desc] = $importance;
				} else {
					if ($importance > $importances[$md5Desc]) {
						$importances[$md5Desc] = $importance;
					}
				}
				if (!in_array($descriptor,$descriptors)) {
					$descriptors[] = $descriptor;
				}
			}
		}
		//$descriptors = array_unique($descriptors);
		rsort($descriptors);
		// now we check each descriptor to see if it matches our query.
		$output = array();
		foreach ($descriptors as $descriptor) {
			if (stripos($descriptor,$this->qdt->descriptor) !== 0) {
				continue;
			}
			$importance = $importances[md5($descriptor)];
			$expression = $concatArray;
			$do = true;
			$tmp = EntityCore::parseEntityDescriptor($descriptor);
			foreach ($tmp as $field=>$value) {
				$valueEmpty = DRFunctionsCore::isEmptyStr($value);
				if ($valueEmpty && in_array($field,$compulsoryPresent)) {
					$do=false;
					break;
				}
				if (!$valueEmpty && in_array($field,$compulsoryMissing)) {
					$do = false;
					break;
				}
				$keyPos = array_search($field,$concatArray);
				if ($keyPos !== null && $keyPos !== false) {
					$expression[$keyPos] = $value;
				}
			}
			if ($do) {
				$exp = trim(implode("",$expression));
				$exp = str_replace("   "," ",$exp);
				$exp = str_replace("  "," ",$exp);
				if (!DRFunctionsCore::isEmptyStr($exp)) $output[] = $exp."%/%".$importance;
			}
		}
		$this->result = $output;
	}
	
	public function processQDCQuery() {
		// this is complicated!
		/* @var $qdc PropertyCollection */
		// now datapoint has the parameters of this query.	
		// gather all datapoints together from QDCs in and 
		$wildcardField = $this->datapoint->getWildcardField();
		if (DRFunctionsCore::isEmptyStr($wildcardField)) {
			$this->result = array();
			return;
		}
		$datapoints = $this->qdt->getData($this->datapoint);
		$this->result = array();
		foreach ($datapoints as $datapoint) {
			$value = $datapoint->$wildcardField;

			if (!DRFunctionsCore::isEmptyStr($this->datapoint->arg) && $this->datapoint->arg > 0) {
				$arg = (integer) $this->datapoint->arg - 1;
				$tmp = explode("x",$value);
				if (isset($tmp[$arg])) {
					$value = $tmp[$arg];
				} else {
					$value = null;
				}
			}
			if (!DRFunctionsCore::isEmptyStr($value)) {
				$this->result[] = $value."%/%".$datapoint->getImportance();
			}
		}
	}
	
	public function processDirectQuery() {
		// assume value is array for this
		$output = $this->qdt->getDirectValueArray($this->command,!$this->useSubtree,true);
		$this->result = $output;
	}	
}