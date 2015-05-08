<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    httplocale.class.php
Version: 1.0.0
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
	DetectRight::registerClass("HTTPLocale");
}

Class HTTPLocale {
	private $array=array();

	public function add($locale) {
		if (DRFunctionsCore::isEmptyStr($locale)) return;
		$localeArray = explode(",",$locale);
		foreach ($localeArray as $loc) {
			$loc = str_replace(" ","",$loc);
			$tmp = explode(";q=",$loc);
			if (count($tmp) >1) {
				$confidence = (string)$tmp[1];
			} else {
				$confidence = (string)1;
			}
			$loc = $tmp[0];
			if (!isset($this->array[$confidence])) $this->array[$confidence] = array();
			if (!in_array($locale,$this->array[$confidence])) $this->array[$confidence][] = $loc;
		}
	}
	
	public function getPreferred() {
		ksort($this->array);
		$array = array_pop($this->array);
		return $array;
	}
	
	public function qlevel($locale) {
		$defaultQ = 0;
		foreach ($this->array as $q=>$localeArray) {
			if (in_array($locale,$localeArray)) return $q;
			if (in_array("*",$localeArray)) $defaultQ = 0;
		}
		return $defaultQ;
	}
}