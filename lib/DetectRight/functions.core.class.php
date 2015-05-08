<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    functions.core.class.php
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
2.3.3 - added "Download file"
2.7.0 - added "nthValue" function
2.8.0 - added "isInteger" function, added splitRegex functions for SigPart.
**********************************************************************************/

Class DRFunctionsCore {
	
	/**
 * Microtime
 *
 * @return float
 */
	static function mt() {
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}

	static function nn($var) {
		if ($var === null) return "";
		return $var;
	}
	
	// a different isNumeric which merely looks for something which is all digits, 
	// this allows major revisions which have two or even three sets of dots in them:
	// something that is_numeric would reject.
	static function isNumeric($var) {
		if ($var === '') return false;
		if ($var === null) return false;
		$array = array();
		$containsNonDigits = preg_match_all("/[^\\d\\.\\-]/",$var,$array) > 0;
		return !$containsNonDigits;
	}

	static function isMostlyNumeric($var) {
		if ($var === '') return false;
		if ($var === null) return false;
		$array = array();
		// if first or last are digits, keep.
		if (ctype_digit($var[0])) return true;
		if (ctype_digit(substr($var,-1,1))) return true;
		$len = strlen($var);
		if ($len > 2) {
			$var = substr($var,0,3);
		}
		$containsNonDigits = preg_match_all("/[^\\d\\.\\-]/",$var,$array) > 0;
		return !$containsNonDigits;
	}

	// function breaks a string into its decimal points and as far as it can, aligns them.
	static function dpAlign(&$str1,&$str2) {
		$tmp1 = explode(".",$str1);
		$tmp2 = explode(".",$str2);

		$r = -1;
		while (true) {
			$r++;
			if (isset($tmp1[$r]) && isset($tmp2[$r])) {
				$part1 = $tmp1[$r];
				$part2 = $tmp2[$r];
				$len1 = strlen($part1);
				$len2 = strlen($part2);
				$diff = $len1 - $len2;
				if ($diff === 0) continue;
				if ($diff > 0) {
					$tmp2[$r] = str_pad($part2,$len1,"0",STR_PAD_LEFT);
				} else {
					$tmp1[$r] = str_pad($part1,$len2,"0",STR_PAD_LEFT);
				}
			} else {
				break;
			}
		}
		$str1 = implode(".",$tmp1);
		$str2 = implode(".",$tmp2);
	}
	/**
 * A better echo command, which can log
 *
 * @param string $string
 * @param boolean $flush
 */
	static function dr_echo($string,$flush=true) {
		static $output = array();
		if (!isset($output)) $output=array();
		$method = DetectRight::$LOG_METHOD;

		switch ($method) {
			case 'echo':
				echo $string;
				if ($flush) {
					flush();
				}
				break;
			case 'log':
				DRFunctionsCore::writeLogEntry($string);
				break;
			case 'buffer':
				$output[]=$string;
				if (count($output) > 10 || $flush) {
					echo implode("",$output);
					$output="";
				}
				break;
		}
	}


	static public function writeLogEntry($string) {
		// @TODO
	}
	/**
 * Boz's "in" function
 *
 * @param string $needle
 * @param string $uAgent
 * @param boolean $toLower
 * @return boolean
 */
	static function in( $needle, $uAgent, $ci = true ) {
		if ((array) $uAgent === $uAgent) $uAgent = implode(" ",$uAgent);
		if ( $ci) {
			return (stripos($uAgent,$needle) !== false);
		} else {
			return (strpos($uAgent,$needle) !== false);
		}
	}

	/**
 * Like function
 *
 * @param string $str1
 * @param string $str2
 * @return boolean
 */
	static function like($str1,$str2) {
		// I hate remembering regular expressions, so I'll just write this ickle translator
		if (substr($str2,-1,1)=='*') {
			$str2=substr($str2,0,-1);
		}
		$str1=strtolower(substr($str1,0,strlen($str2)));
		$str2=strtolower($str2);
		if ($str1==$str2) {
			return true;
		} else {
			return false;
		}
	}

	/**
 * GV - a cleaner way of getting a value out of an associative array
 *
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @param boolean $escape
 * @return mixed
 */
	static function gv($array,$key,$default=null) {
		// this stops having to keep checking on array key sets
		/*if (is_scalar($array)) {
			return $default;
		}*/

		if (isset($array[$key])) {
			$result=$array[$key];
		} else {
			$result=$default;
		}
		return $result;
	}
	
	/**
 * GZEncode and serialize an object, then base64 it.
 *
 * @param Object $object
 * @return string
 */
	static function gz($object,$compact=false) {
		$serObject=serialize($object);
		/*if (DRFunctionsCore::isEmptyStr($serObject)) {
			
		}*/
		//$object = str_replace("\0","\\%00",$object);
		//if ($compact) $object = DRFunctionsCore::compactSerializedPHP($object);
		$string=gzencode($serObject);
		$string=base64_encode($string);
		return $string;
	}

	static function root($a,$k){
		return (($a<0 && $k%2 > 0) ? -1 : 1) * pow(abs($a),1/$k);
	}
	
/*	static function compactSerializedPHP($string) {
		$replace = array(
			"%pct%"=>"O:15:\"QuantumDataTree\"",
			"%pctt%"=>"s:13:\"\0QuantumDataTree\0top\"",
			"%pctp%"=>"s:23:\"\0PropertyCollectionTree\0parent\"",
			"%pctr%"=>"s:28:\"\0PropertyCollectionTree\0tree\"",
			"%pcte%" => "s:30:\"\0PropertyCollectionTree\0entity\"",
			"%pcto%" => "s:30:\"\0PropertyCollectionTree\0object\"",
			"%pctpk%" => "s:31:\"\0PropertyCollectionTree\0package\"",
			"%pcts%" => "s:30:\"\0PropertyCollectionTree\0status\"",
			"%pctdh%" => "s:33:\"\0PropertyCollectionTree\0directHit\"",
			"%pc%" => "s:26:\"\0PropertyCollectionTree\0pc\"",
			"%v%" => "s:5:\"value\"",
			"%auth%" => "s:13:\"Authorisation\"",
			"%es%" => "s:0:\"\"",
			"%ds%" => "s:10:\"descriptor\"",
			"%et%" => "s:10:\"entitytype\"",
			"%c%" => "s:8:\"category\"",
			"%d%" => "s:11:\"description\"",
			"%sc%" => "s:8:\"subclass\"",
			"%mar%" => "s:13:\"majorrevision\"",
			"%mir%" => "s:13:\"minorrevision\"",
			"%im%" => "s:10:\"importance\"",
			"%b%" => "s:5:\"build\"",
			"%md%" => "s:8:\"metadata\"",
			"%ea%" => "a:0:{}",
			"%us%" => "s:10:\"useSubtree\"",
			"%ac%" => "s:12:\"access_count\"",
			"%eid%" => "s:8:\"entityid\"",
			"%ff%" => "s:11:\"File Format\"",
			"%spl%" => "s:16:\"Streaming Player\"",
			"%pl%" => "s:6:\"Player\"",
			"%br%" => "s:7:\"Browser\"",
			"%a%" => "s:5:\"Audio\"",
			"%v%" => "s:5:\"Video\""
		);
		
		$string = str_replace(array_values($replace),array_keys($replace),$string);
		return $string;
	}
	
	static function uncompactSerializedPHP($string) {
		$replace = array(
			"%pct%"=>"O:22:\"PropertyCollectionTree\"",
			"%pctt%"=>"s:27:\"\0PropertyCollectionTree\0top\"",
			"%pctp%"=>"s:30:\"\0PropertyCollectionTree\0parent\"",
			"%pctr%"=>"s:28:\"\0PropertyCollectionTree\0tree\"",
			"%pcte%" => "s:30:\"\0PropertyCollectionTree\0entity\"",
			"%pcto%" => "s:30:\"\0PropertyCollectionTree\0object\"",
			"%pctpk%" => "s:31:\"\0PropertyCollectionTree\0package\"",
			"%pcts%" => "s:30:\"\0PropertyCollectionTree\0status\"",
			"%pctdh%" => "s:33:\"\0PropertyCollectionTree\0directHit\"",
			"%pc%" => "s:26:\"\0PropertyCollectionTree\0pc\"",
			"%v%" => "s:5:\"value\"",
			"%auth%" => "s:13:\"Authorisation\"",
			"%es%" => "s:0:\"\"",
			"%ds%" => "s:10:\"descriptor\"",
			"%et%" => "s:10:\"entitytype\"",
			"%c%" => "s:8:\"category\"",
			"%d%" => "s:11:\"description\"",
			"%sc%" => "s:8:\"subclass\"",
			"%mar%" => "s:13:\"majorrevision\"",
			"%mir%" => "s:13:\"minorrevision\"",
			"%im%" => "s:10:\"importance\"",
			"%b%" => "s:5:\"build\"",
			"%md%" => "s:8:\"metadata\"",
			"%ea%" => "a:0:{}",
			"%us%" => "s:10:\"useSubtree\"",
			"%ac%" => "s:12:\"access_count\"",
			"%eid%" => "s:8:\"entityid\"",
			"%ff%" => "s:11:\"File Format\"",
			"%spl%" => "s:16:\"Streaming Player\"",
			"%pl%" => "s:6:\"Player\"",
			"%br%" => "s:7:\"Browser\"",
			"%a%" => "s:5:\"Audio\"",
			"%v%" => "s:5:\"Video\""
		);
		
		$string = str_replace(array_keys($replace),array_values($replace),$string);
		return $string;
	}*/
	/**
 * GZEncode and json encode an object, then base64 it.
 *
 * @param Object $object
 * @return string
 */
	static function gz_json($object) {
		$object=json_encode($object);
		$string=gzencode($object);
		$string=base64_encode($string);
		return $string;
	}

	/**
 * Ungzencode and deserialize
 *
 * @param string $string
 * @return mixed
 */
	static function ungz($string) {
		//DetectRight::checkPoint("base64");
		if (DRFunctionsCore::in(":",$string)) {
			$return = unserialize($string);
			if ($return) {
				return $return;
			} else {
				return null;
			}
		}
		$string=base64_decode($string);
		//DetectRight::checkPoint("GZDecode");
		$object=gzdecode($string);
		//$object = DRFunctionsCore::uncompactSerializedPHP($object);
		//DetectRight::checkPoint("Unserialize");
		if (is_string($object) && (preg_match('/^[saibdO][:][0-9]+.*[;}]/',$object) || $object == "N;")) {
			$object = str_replace("\\%00","\0",$object);
			$object=unserialize($object);
			if (is_string($object) && (preg_match('/^[saibdO][:][0-9]+.*[;}]/',$object) || $object== "N;")) {
				@$object=unserialize($object);
			}
		} 
		//DetectRight::checkPoint("Dungz");
		return $object;
	}

	/**
 * Ungzencode and deserialize
 *
 * @param string $string
 * @return mixed
 */
	static function ungz_json($string) {
		//DetectRight::checkPoint("base64");
		if (DRFunctionsCore::in(":",$string)) return json_decode($string);
		$string=base64_decode($string);
		//DetectRight::checkPoint("GZDecode");
		$object=gzdecode($string);
		//DetectRight::checkPoint("Unserialize");
		@$object=json_decode($object);
		//DetectRight::checkPoint("Dungz");
		return $object;
	}
	
	/**
 * Make an XML keysafe
 *
 * @param unknown_type $str
 * @return unknown
 */
	static function strToXMLKeySafe($str) {
		if (is_numeric($str)) {
			$str = 'a' . $str;
		} elseif (is_numeric(substr($str,0,1))) { // End of if (is_numeric($str))
			$str = preg_replace("/^(\\d*)/","",$str);
		} // End of elseif (is_numeric(substr($str,0,1)))

		return $str;
	}

	/**
* String to XML Safe
*
* @param string $str
* @return string
* @access Private
* 
*/
	static function strToXMLSafe($str,$utf8=true) {
		$searchArray    = array('&','<', '>', "'", '"');
		$replaceArray   = array('&amp;','&lt;', '&gt;', '&apos;', '&quot;');
		$str = str_replace($searchArray,$replaceArray,$str);
		$str=str_replace("&amp;&quot;","&quot;",$str);
		if ($utf8) $str = utf8_encode($str);
		return $str;
	} // End of strToXMLSafe()

	/**
 * Case-insensitive array search
 *
 * @param string $str
 * @param array $array
 * @return string || false
 */
	static function array_isearch($str, $array) {
		foreach($array as $k => $v) {
			if(strcasecmp($str, $v) == 0) return $k;
		}
		return false;
	}
	
	/**
 * URL Cleaning
 *
 * @param string $url
 * @return string
 */
	static function cleanURL($url) {
		$tmp=explode(", ",$url);
		$url=$tmp[0];
		if (count($tmp)==2 && strlen($tmp[1]) < 4) $url .= ",".$tmp[1];
		$url=str_replace(array('\n','\r','\\','"'),'',$url);
		$url=trim($url);
		$prefix=substr($url,0,4);
		if ($prefix=="wap." || $prefix=="uapr" || $prefix == "www.") $url = "http://".$url;
		if (substr($url,0,4) !== "http") $url="";
		return $url;
	}

	/**
 * Punctuation cleaning...
 *
 * @param string $string
 * @return string
 */
	static function punctClean($string) {
		$string=str_replace(array(" ","-","_","/","*"),"",$string);
		$string=str_replace(array("\n","\r"),"",$string);
		$string=preg_replace('#[^\+\;\-\)\(\d\w\s\/:.]#','',$string);
		return $string;
	}

	/**
 * Checks to see whether a string is serialized or not.
 *
 * @param string $string
 * @return boolean
 */
	static function isSerialized($string)
	{
		if (is_array($string)) return false;
		if (substr($string,0,2)=="a:" || substr($string,0,2)=="O:") return true;
		$matches = array();
		$str = 's';
		$array = 'a';
		$integer = 'i';
		$any = '[^}]*?';
		$count = '\d+';
		$content = '"(?:\\\";|.)*?";';
		$open_tag = '\{';
		$close_tag = '\}';
		$parameter = "($str|$array|$integer|$any):($count)" . "(?:[:]($open_tag|$content)|[;])";
		$preg = "/$parameter|($close_tag)/";
		if( !preg_match_all( $preg, $string, $matches ) ) return false;
		return true;
	}
	
	static function today() {
		return strftime('%Y-%m-%d', time());
	}

	/**
 * Very complicated equality function :)
 *
 * @param mixed $val1
 * @param mixed_type $val2
 * @return boolean
 */
	static function isEqual($val1,$val2) {
		$a1 = ((array) $val1 === $val1);
		$a2 = ((array) $val2 === $val2);
		if ($val1 === null && $val2 === null) return true; // the same
		if ($val1 === null || $val2 === null) return false; // different
		if ($a1 && !$a2) return false;
		if (!$a1 && $a2) return false;
		if ($a1 && $a2) {
			if (count($val1) != count($val2)) return false;
			$diffs=array_diff($val1,$val2);
			if (count($diffs)>0) {
				return false;
			}
			return true;
		}

		// make this case insensitive.
		if (strcasecmp($val1,$val2) === 0) return true;
		return false;
	}

	/**
 * Is it empty? This will tell...
 *
 * @param mixed $var
 * @return boolean
 */
	static function isEmpty($var) {
		if ($var === null) return true;
		if ($var === "") return true;
		if ((array) $var === $var) {
			if (count($var) == 0) return true;
		}
		return false;
	}

	static function isEmptyStr($var) {
		if ($var === null) return true;
		if ($var === "") return true;
		return false;
	}

	/**
 * Converts an object to an array, leaving out the fields in "suppressed".
 * Doesn't activate if there's already a "toArray" function.
 *
 * @param Object $object
 * @return array
 */
	static function objectToArray($object) {
		$data=array();
		$suppress=explode(",",$object->suppressed);
		foreach ($object as $key=>$value) {
			if (!in_array($key,$suppress)) {
				if (is_array($value)) {
					$data[$key]=self::processArray($value);
				} elseif (is_object($value)) {
					if (!method_exists($value,"toArray")) {
						$data[$key]=DRFunctionsCore::objectToArray($value);
					} else {
						$data[$key]=$value->toArray();
					}
				} else {
					$data[$key]=$value;
				}
			}
		}
		return $data;
	}
	
	/**
 * Helper function for objectToArray: assist in the conversion of objects to arrays by scanning
 * arrays for other nested objects.
 *
 * @param array $array
 * @return array
 */
	static function processArray($array) {
		$return=array();
		foreach ($array as $key=>$value) {
			if (is_object($value)) {
				if (!method_exists($value,"toArray")) {
					$data[$key]=DRFunctionsCore::objectToArray($value);
				} else {
					$data[$key]=$value->toArray();
				}
			} elseif (is_array($value)) {
				$return[$key]=self::processArray($value);
			} else {
				$return[$key]=$value;
			}
		}
		return $return;
	}


	/**
 * Searches an array for something: unlike array_search, this searches in the strings.
 *
 * @param unknown_type $needle
 * @param unknown_type $haystack
 * @return unknown
 */
	static function array_in_search($needle,$haystack) {
		foreach ($haystack as $value) {
			if (strpos($needle,$value) !== false) return true;
		}
		return false;
	}

	static function readpath($dir){
		$output=array();
		if($handle = opendir($dir))
		{
			while($file = readdir($handle))
			{
				clearstatcache();
				if(is_file($dir."/".$file)) {
					$output[]=$dir."/".$file;
				}
			}
			closedir($handle);
		}
		return $output;
	}

	static function arrayToTextArray($value,$baseKey="") {
		if (!is_array($value)) $value = array($value);
		$description = array();
		foreach ($value as $key=>$value) {
			//$description = "<![CDATA[".array2tabbedText($value,0,"<br />","&nbsp;&nbsp;&nbsp;&nbsp;")."]]>";
			//$description = "<![CDATA[".array2table($value,true)."]]>";
			if ($baseKey) {
				$key = $baseKey."_".$key;
			}
			if (is_array($value)) {
				$description = array_push($description,self::arrayToTextArray($value,$key."_"));
			} else {
				$description[] = "$key = $value";
			}
		}
		return $description;
	}
	
	/*
6 High-Definition
*/
	static function replaceSize($dp) {
		$sizeMap = array(
			"whuxga"=>"7680x4800",
			"whsxga"=>"6400x4096",
			"wquxga"=>"3840x2400",
			"wqsxga"=>"3200x2048",
			"wsxga+"=>"1680x1050",
			"sqcif"=>"128x96",
			"wqvga"=>"240x400",
			"qqvga"=>"120x160",
			"hqvga"=>"240x160",
			"fwvga"=>"480x854",
			"wsvga"=>"1024x576",
			"wuxga"=>"1920x1200",
			"1020i"=>"1920x1020",
			"1080p"=>"1920x1020",
			"qwxga"=>"2048x1152",
			"wqxga"=>"2560x1600",
			"qsxga"=>"2560x2048",
			"quxga"=>"3200x2400",
			"whxga"=>"5120x3200",
			"hsxga"=>"5120x4096",
			"huxga"=>"6400x4800",
			"wxga+"=>"1440x900",
			"sxga+"=>"1400x1050",
			"qcif"=>"176x220",
			"svga"=>"800x600",
			"wvga"=>"480x800",
			"qvga"=>"240x320",
			"hvga"=>"480x320",
			"dvga"=>"640x960",
			"wxga"=>"1280x768",
			"xga+"=>"1152x864",
			"sxga"=>"1280x1024",
			"uxga"=>"1600x1200",
			"720p"=>"1280x720",
			"wqhd"=>"2560x1440",
			"qfhd"=>"3840x2160",
			"qxga"=>"2048x1536",
			"hxga"=>"4096x3072",
			"nhd"=>"640x360",
			"qhd"=>"960x540",
			"fhd"=>"1920x1080",
			"uhd"=>"7680x4320",
			"vga"=>"640x480",
			"xga"=>"1024x768",
			"hd"=>"1280x720"
		);
		foreach ($sizeMap as $nickname=>$dimension) {
			if (strtolower($dp) === $nickname) {
				$dp = str_ireplace($nickname,$dimension,$dp);
			}
		}
		return $dp;
	}

	static function is_expression($expr) {
		/**
	 * @todo: rewrite using preg expressions
	 */
		return preg_match("/[=><]/",$expr);
		/*if (strpos($expr,"=") !== false || strpos($expr,">") !== false || strpos($expr,"<") !== false) {
			// flag being evaluated
			return true;
		}
		return false;*/
	}

	static function splitFirstDelimiterRegexDelimArray($strFragment, $delimiters, $exact = false) {
		if (!$delimiters) return array($strFragment, "");
		$delims = "";
		$needsEscaping = ".$,^{}[]()|*+?\\/";
		$delLength = count($delimiters);
		for ($r = 0; $r < $delLength; $r++) {
			$str = $delimiters[$r];
			if (strlen($str) == 1)
			{
				if (strpos($needsEscaping,$str) !== false)
				{
					$str = "\\".$str;
				}
			}
			else
			{
				$str = str_replace(".", "\\.",$str);
				$str = str_replace("$", "\\$",$str);
				$str = str_replace(",", "\\,",$str);
				$str = str_replace("^", "\\^",$str);
				$str = str_replace("{", "\\{",$str);
				$str = str_replace("}", "\\}",$str);
				$str = str_replace("[", "\\[",$str);
				$str = str_replace("]", "\\]",$str);
				$str = str_replace("|", "\\|",$str);
				$str = str_replace("(", "\\(",$str);
				$str = str_replace(")", "\\)",$str);
				$str = str_replace("*", "\\*",$str);
				$str = str_replace("+", "\\+",$str);
				$str = str_replace("?", "\\?",$str);
				$str = str_replace("\\", "\\\\",$str);
			}
			$delims = $delims . (($r > 0) ? "|" : "").$str;
		}			
		return self::splitFirstDelimiterRegex($strFragment,$delims,$exact);
	}
	
	static function splitFirstDelimiterRegex($strFragment,$delimiters,$exact = false) {
		if (!is_string($delimiters)) {
			$delimiters = implode("|\\",$delimiters);
		}
		if (!$exact) {
			$extra = "|\\+http:\\/\\/|compatible[ ;\\)_\\/$]|Profile[ \\)_\\/$]|Build[ \\)_\\/$]|Configuration[ \\)_\\/$]";
			$delimiters = $delimiters.$extra;
		}
		$delimiters = "/(.*?)(".$delimiters.")(.*)/";
		$matches = array();
		$secondTest = preg_match($delimiters,$strFragment,$matches);
		if ($secondTest === 0) return array($strFragment,""); 
		/*while ($secondTest > 0 && $matches[1] === "") {
			$strFragment = substr($strFragment,strlen($matches[2]));
			$secondTest = preg_match($delimiters,$strFragment,$matches);
			//$matches[1] = substr($ua,0,1).$matches[1];
		}
		if ($secondTest === 0) return array($strFragment,"");*/
		return array($matches[1],$matches[3]);
	}
	
	static function splitFirstDelimiter($strFragment,$delimiters,$exact=false) {
		// now find first next delimiter
		if (!is_array($delimiters)) return array($strFragment,"");
		if (empty($delimiters)) return array($strFragment,"");
		if (!$exact) {
			$delimiters[] = "Profile";
			$delimiters[] = "Build";
			$delimiters[] = "Configuration";
		}

		$posArray = array();
		$posDelim = array();
		foreach ($delimiters as $delimiter) {
			$pos = stripos($strFragment,$delimiter);
			if ($pos !== false) {
				$posArray[] = $pos;
				$posDelim[$delimiter] = $pos;
			}
		}
		sort($posArray);
		ksort($posDelim);

		// get position of first delimiter
		$pos = array_shift($posArray);
		$delimiterUsed = "";
		if ($pos === null) {
			// end of line
			$description = $strFragment;
			$strFragment = "";
		} else {
			$delimiters = array_keys($posDelim);
			$delimiterUsed = array_shift($delimiters);
			$description = substr($strFragment,0,$pos);
			$strFragment = substr($strFragment,$pos+strlen($delimiterUsed));
		}
		return array($description,$strFragment);
	}

	static function splitDelimiters($strFragment,$delimiters) {
		// returns a string split by the delimiters in the array
		$delimiterString = implode("",$delimiters);
		$tokens = array();
		$tok = trim(strtok($strFragment,$delimiterString));
		while ($tok !== false) {
			if (!self::isEmptyStr($tok)) $tokens[] = $tok;
			$tok = trim(strtok($delimiters));
		}
		return $tokens;
	}

	/**
     * Returns true if $string is valid UTF-8 and false otherwise. 
     * 
     * @since        1.14 
     * @param [mixed] $string     string to be tested 
     * @subpackage 
     */ 
	static function is_utf8($string) {

		// From http://w3.org/International/questions/qa-forms-utf-8.html
		return preg_match('%^(?:
              [\x09\x0A\x0D\x20-\x7E]            # ASCII 
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte 
            |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs 
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte 
            |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates 
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3 
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15 
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16 
        )*$%xs', $string);        
	}

	static function isAssociativeArray($array) {
		$keys = array_keys($array);
		for ($r = 0; $r < count($keys); $r++) {
			if ($keys[$r] !== $r) return true;
		}
		return false;
	}
	
	static function make_portrait($dimension) {
		if (stripos($dimension,"x") === false) return "";
		$tmp = explode("x",$dimension);
		try {
			$x = (int) $tmp[0];
			$y = (int) $tmp[1];
		} catch (Exception $e) {
			return $dimension;
		}
		if ($x > $y) return $y."x".$x;
		return $x."x".$y;
	}
	
	static function make_landscape($dimension) {
		if (stripos($dimension,"x") === false) return "";
		$tmp = explode("x",$dimension);
		try {
			$x = (int) $tmp[0];
			$y = (int) $tmp[1];
		} catch (Exception $e) {
			return $dimension;
		}
		if ($x < $y) return $y."x".$x;
		return $x."x".$y;
	}
	
	static function ssort(&$array) {
		sort($array, SORT_STRING);
	}
	
	static function nsort(&$array) {
		sort($array,SORT_NUMERIC);
	}
	
	static function rssort(&$array) {
		rsort($array,SORT_STRING);
	}
	
	static function rnsort(&$array) {
		rsort($array,SORT_NUMERIC);
	}
	
	static function parseDoubleLoose($number) {
		// purpose is to be loose with doubles
		if (is_double($number)) return $number;
		if (self::isNumeric($number)) {
			$tmp = explode(".",$number);
			$test = $tmp[0];
			if ($test === "") $test = "0";
			if (isset($tmp[1])) $test .= ".".$tmp[1];
			return $test;
		}
		throw new DetectRightException("Number Format Exception",null);
	}
	
	static public function downloadFile ($url, $path) {	
		$newfname = $path;
		$newf = "";
		$file = fopen ($url, "rb");
		stream_set_timeout($file, 240);
		$success = false;
		if ($file) {
			$newf = fopen ($newfname, "wb");

			if ($newf)
			while(!feof($file)) {
				fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
			}
			$success = true;
		}

		if ($file) {
			fclose($file);
		}

		if ($newf) {
			fclose($newf);
		}
		
   		/*$zip = new ZipArchive;
     	$res = $zip->open($newf);
     	if ($res === TRUE) {
        $zip->extractTo(".");
        $zip->close();
     } else {
         echo ‘failed’;
     }*/
		chmod($newfname, 0777); // make sure everyone can read/write it.
		return $success;
	}
	
	static public function nthValue($map, $n) {
		if ($map === null) return null;
		
		$c=0;
		foreach ($map as $key=>$value) {
			if ($c === $n) return $value;
			$c++;
		}
		return null;
	}

	static function isInteger($input){
		if (substr($input,0,1) === "-") $input = substr($input,1);
		return(ctype_digit(strval($input)));
	}
}

function isEmpty($variable) {
	return DRFunctionsCore::isEmpty($variable);
}

	#-- inflates a string enriched with gzip headers
	#   (this is the logical counterpart to gzencode(), but don't tell anyone!)
	if (!function_exists("gzdecode")) {
		function gzdecode($data, $maxlen=NULL) {

			#-- decode header
			$len = strlen($data);
			if ($len < 20) {
				return;
			}
			$head = substr($data, 0, 10);
			$head = unpack("n1id/C1cm/C1flg/V1mtime/C1xfl/C1os", $head);
			list($ID, $CM, $FLG, $MTIME, $XFL, $OS) = array_values($head);
			$FTEXT = 1<<0;
			$FHCRC = 1<<1;
			$FEXTRA = 1<<2;
			$FNAME = 1<<3;
			$FCOMMENT = 1<<4;
			$head = unpack("V1crc/V1isize", substr($data, $len-8, 8));
			list($CRC32, $ISIZE) = array_values($head);

			#-- check gzip stream identifier
			if ($ID != 0x1f8b) {
				trigger_error("gzdecode: not in gzip format", E_USER_WARNING);
				return;
			}
			#-- check for deflate algorithm
			if ($CM != 8) {
				trigger_error("gzdecode: cannot decode anything but deflated streams", E_USER_WARNING);
				return;
			}

			#-- start of data, skip bonus fields
			$s = 10;
			if ($FLG & $FEXTRA) {
				$s += $XFL;
			}
			if ($FLG & $FNAME) {
				$s = strpos($data, "\000", $s) + 1;
			}
			if ($FLG & $FCOMMENT) {
				$s = strpos($data, "\000", $s) + 1;
			}
			if ($FLG & $FHCRC) {
				$s += 2;  // cannot check
			}

			#-- get data, uncompress
			$data = substr($data, $s, $len-$s);
			if ($maxlen) {
				$data = gzinflate($data, $maxlen);
				return($data);  // no checks(?!)
			}
			else {
				$data = gzinflate($data);
			}

			#-- check+fin
			$chk = abs(crc32($data));
			if( $chk & 0x80000000){
				$chk ^= 0xffffffff;
				$chk += 1;
			}

			/*      if (abs($CRC32) != $chk) {
			trigger_error("gzdecode: checksum failed (real$chk != comp$CRC32)", E_USER_WARNING);
			}
			else*/
			if ($ISIZE != strlen($data)) {
				trigger_error("gzdecode: stream size mismatch", E_USER_WARNING);
			}
			else {
				return($data);
			}
		}
	}
	
	if (!function_exists("mt")) {
		function mt() {
			list($usec, $sec) = explode(" ", microtime());
			return ((float)$usec + (float)$sec);
		}
	}
	
	if (!function_exists("inet_pton")) {
		function inet_pton($ip)
		{
			# ipv4
			if (strpos($ip, '.') !== FALSE) {
				$ip = pack('N',ip2long($ip));
			}
			# ipv6
			elseif (strpos($ip, ':') !== FALSE) {
				$ip = explode(':', $ip);
				$res = str_pad('', (4*(8-count($ip))), '0000', STR_PAD_LEFT);
				foreach ($ip as $seg) {
					$res .= str_pad($seg, 4, '0', STR_PAD_LEFT);
				}
				$ip = pack('H'.strlen($res), $res);
			}
			return $ip;
		}
	}