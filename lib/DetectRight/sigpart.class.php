<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    sigpart.class.php
Version: 2.8
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

2.2.0 - if validation comes back null, turn to empty string. This happens implicitly in PHP anyway,
but it makes the functinality explicit.
2.7.0 - added SigPartResult thing
2.8 - changed splitting to regex based for speed.
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("SigPart");
}

Class SigPart {
	static $dbLink;
	static $cacheLink;
	
	public $originalString;
	public $strings = array();
	public $startPart = "";
	public $endPart = "";
	public $expression;
	public $parts = array();
	//public $found = true;
	public $workingString = "";
	
	static $delimiters = " /*{})(*:;[]";
	static $delimitersString = "[ \\/\\*\\{\\}\\)\\(:;\\[\\]]";
	
	public function __construct($string) {
		//$this->cacheDB(); this class doesn't (yet) use cache or DB

		// check for improperly nested strings
		$this->originalString = $string;

		$tmp = explode("(",$string,2);
		if (isset($tmp[2])) return;
		if (!isset($tmp[1])) {
			$this->startPart = $string;
			return;
		}
		// count = 2
		$this->startPart = $tmp[0];
		$expression = $tmp[1];
		$strpos = false;
		$len = strlen($expression);
		for ($r = $len-1; $r > -1; $r--) {
			//if (substr($expression,$r,1) == ")") {
			if ($expression{$r} == ")") {
				$strpos = $r;
				break;
			}
		}
		if ($strpos === false) return; // check for closing bracket
		$this->expression = substr($expression,0,$strpos);
		$remainder = substr($expression,$strpos+1);
		if ($remainder !== false) {
			$this->endPart = $remainder; // note, we're only allowing one wildcard entry
		} 
		$this->processExpression();		
	}

	/*public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
	}
	
	public function __wakeup() {
		$this->cacheDB();
	}*/
	
/*	public function __sleep() {
		$ov = get_object_vars($this);
		return array_keys($ov);
	}*/

	public function processExpression() {
		$array = preg_split("/([{}])/i",$this->expression,null,PREG_SPLIT_DELIM_CAPTURE);
		
		$special = false;
		foreach ($array as $value) {
			if ($value === "{") {
				$special=true;
				continue;
			} elseif ($value === false) {
				$special = false;
				continue;
			} elseif ($value === "}") {
				$special = false;
				continue;
			}
			
			if ($value !== "") {
				$this->parts[] = array("special"=>$special,"value"=>$value);
			}
		}
	}
	
	// (version{d}*{3}):(version{d}*)::(Mobile Safari{d}*)
	public function process($string) {
		// here's where the major thing comes...
		if ($string === null) $string = "";
		$result = new SigPartResult();
		$output = "";
		//$this->found=true;
		$result->found = true;
		$notFoundEmpty = false;
		$workingString = $string;
		$diag = DetectRight::$DIAG;
		foreach ($this->parts as $part) {
			if ($workingString === null) return $result; // if the string goes to null while we're going through the parts, then it's bad news.
			$special = $part['special'];
			$partValue = $part['value'];
			if ($special) {
				$op = $partValue{0};
				if (isset($partValue{1})) {
					$command = substr($partValue,1);
				} else {
					$command = "";
				}
				switch ($op) {
					case '+':
						$output .= $command;
						break;
					case 'd':
						// forwarding to delimiter
						if ($command === null || $command === "") {
							$delims = self::$delimitersString;
							$exact=false;
							$tmp = DRFunctionsCore::splitFirstDelimiterRegex($workingString,$delims,$exact);
						} else {
							$delims = str_split($command,1);
							$exact=true;
							$tmp = DRFunctionsCore::splitFirstDelimiterRegexDelimArray($workingString,$delims,$exact);
						}
						array_shift($tmp);
						if (!empty($tmp)) {
							$workingString = array_shift($tmp);
						} else {
							$workingString = "";
						}
						break;
					case '*':
					case '^':
						// taking chars
							// this is an implied delimiter ending
							//$workingString = trim($workingString);
							while ($workingString !== "" && strpos(self::$delimiters,$workingString{0}) !== false) {
								$workingString = substr($workingString,1);
							}
							
							$delimiters = self::$delimitersString;
							$chars = 0;
							$tmp = null;
							if ($command !== null && $command !== "") {
								if (is_numeric($command)) {
									$chars = (int)$command;
								} else {
									$delimiters = str_split($command,1);
									$tmp = DRFunctionsCore::splitFirstDelimiterRegexDelimArray($workingString,$delimiters);
								}
							}
					
							if (!$tmp) {
								$tmp = DRFunctionsCore::splitFirstDelimiterRegEx($workingString,$delimiters);
							}
							
							if (isset($tmp[0])) {
								$value = $tmp[0];
								if ($chars > 0 && isset($value[$chars-1])) /*(strlen($value) > $chars))*/ {
									$value = substr($value,0,$chars);
								}
								if (isset($tmp[1])) {
									$workingString = $tmp[1];
								} else {
									$workingString = "";
								}
							} else {
								// should never happen
							}
							
							$output .= $value;
							break;
					case '|':
						if ($diag) DetectRight::checkPoint("Validating $command");
						$lookup = DetectorCore::$dbLink->getArray($command);
						if (!empty($lookup)) {
							foreach ($lookup as $lKey=>$lValue) {
								if ($lKey && substr($lKey,0,1) === "%") {
									$lKey = substr($lKey,1);
									$output = trim(str_ireplace($lKey,$lValue,$output));
								} else {
									$output = trim(DRFunctionsCore::gv($lookup,$output,$output));
								}
							}
						//} elseif ((array) $lookup !== $lookup || !isset($lookup[0])) {
						} else {
							$output = Validator::validate($command,$output);
							if ($output === null) $output = "";
							$profileChanges = Validator::$profileChanges;
							if (!empty($profileChanges)) $result->properties = array_merge($result->properties,$profileChanges);
						}
						if ($diag) DetectRight::checkPoint("Validated $command");
						break;
						// skipping!	
					case '.':
						if (!DRFunctionsCore::isEmptyStr($command) && is_numeric($command))	 {
							$chars = (int)$command;
						} else {
							$chars = 1;
						}
						$workingString = substr($workingString,$chars);
						break;
					case '=':
						// working string must equal this or fail immediately
						if ($value !== $command) {
							$result->found = false;
							$result->result = "";
							return $result;
						}
						break;
					case '>':
						if (!($output > $command)) {
							$result->result = "";
							$result->found = false;
							return $result;
						}
						break;
					case '<':
						if (!($output < $command)) {
							$result->result = "";
							$result->found = false;
							return $result;
						}
						break;	
					case '!':
						if ($output === $command) {
							$result->result = "";
							$result->found = false;
							return $result;
						}
					default:
						
				}
				continue;
			}
			
			$negate=false;
			// we're using ! as a negate, so we can reject the match in the presence of a certain string.
			// this is important for BlackBerries, for instance, in the sense that Opera Mini can contain the "BlackBerry"
			// string which would normally match the 
			$mustMatch = true;
			$atStart = null;
			switch($partValue{0}) {
				case '@':
					$notFoundEmpty=true;
					$partValue = substr($partValue,1);
					break;
				case '#':
					$mustMatch = true;
					$negate = false;
					$atStart = false;
					$partValue = substr($partValue,1);
					break;
				case '$':
					$mustMatch = true;
					$negate = false;
					$atStart = true;
					$partValue = substr($partValue,1);
					break;
				case '!':
					$mustMatch = true;
					$negate = true;
					$partValue = substr($partValue,1);
					break;
				case '&':
					$mustMatch = false;
					$negate = false;
					$partValue = substr($partValue,1);
					break;
				default:
					$mustMatch = true;
					$negate = false;
			}
			
			if ($atStart !== null) {
				while ($workingString !== "" && strpos(self::$delimiters,$workingString{0}) !== false) {
					$workingString = substr($workingString,1);
				}
			}

			$pos = stripos($workingString,$partValue);
			if ($pos !== false && $pos > 0) {
				// note for later development: we should really only run this if the first character of the string isn't itself a delimiter
				$prevChar = $workingString{$pos-1};
				if (preg_match("/[A-Za-z]/",$prevChar)) {
					$pos = false;
				}
			}
			$matched = ($pos !== false);
			if ($atStart === true) {
				if ($pos !== 0) $matched = false;
			} elseif ($atStart === false) {
				if ($pos === 0) $matched = false;
			}
			
			if ($matched) {
				if ($mustMatch && $negate) {
					// matching is bad
					$result->result = "";
					$result->found = false;
					return $result;
				}
				$workingString = substr($workingString,$pos+strlen($partValue));
			} else {
				if ($mustMatch && !$negate) {
					// not matching is a fail
					$result->result = "";
					$result->found = false;
					return $result;
				}
				if (!$mustMatch) {
					// we don't want to cancel found status, but we also return nothing
					// since the optional string isn't here
					$result->result = "";
					return $result;
				}
			}
		}
		// some trick here to make it look for another instance in the string?
		// or maybe duplicating this function but pass it workingstring?
		$this->workingString = $workingString;
		$retStr = $this->startPart.$output.$this->endPart;
		if ($retStr === "" && $notFoundEmpty) {
			$result->found = false;
		}
		$result->result = $retStr;
		return $result;
	}
}

class SigPartResult {
	public $found = true;
	public $properties = array();	
	public $result = "";
}