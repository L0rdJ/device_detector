<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    wurfl.classes.php
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
	DetectRight::registerClass("wurfl_charset_validator");
	DetectRight::registerClass("wurfl_boolean_validator");
	DetectRight::registerClass("wurfl_h264_codec_validator");
	DetectRight::registerClass("wurfl_nokia_series_validator");
}

Class wurfl_charset_validator extends Validator {
	
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		$validator = new content_validator("charset");
		$validator->process($value);
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function export($value) {
		$value = strtolower($value);
		$value = str_replace("-","",$value);
		return $value;
	}		
}

Class wurfl_boolean_validator extends boolean_validator {
	/**
	 * Export
	 *
	 * @param boolean $value
	 * @return string || null
	 * @access public
	 * @internal
	 */
	function export($value) {
		if ($value === "supported" || $value === 'true' || $value === 1 || $value === true || $value === '1' || $value === "True") return "true";
		if ($value === "not_supported" || $value === "not supported" || $value === 'False' || $value === 'false' || $value === 0 || $value === false || $value === '0') return "false";
			return null;
	}
}

Class wurfl_h264_codec_validator extends Validator {
	public $map = array("10"=>"H264:Baseline:Level 1.0","11"=>"H264:Baseline:Level 1.1","12"=>"H264:Baseline:Level 1.2","1b"=>"H264:Baseline:Level 1.0b");
	
	/**
	 * Process
	 *
	 * @param string $value
	 * @return null
	 * @access public
	 * @internal
	 */
	function process($value) {
		$tmp = explode(" ",$value);
		foreach ($tmp as $codecKey) {
			$codec = DRFunctionsCore::gv($this->map,$codecKey);
			if ($codec) {
				$this->pcArray[]="Media//Player//Codec:Video:$codec";
			}
		}
		return null; // it's all done in the profile changes!
	}
	
	/**
	 * Export
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function export($value) {
		$codecKey=array_search($value,$this->map);
		if ($codecKey === false) return null;
		return $codecKey;
	}		
}

Class wurfl_nokia_series_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		return substr($value,0,2);
	}
	
	/**
	 * Export
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function export($value) {
		return substr($value,0,2);
	}	
}