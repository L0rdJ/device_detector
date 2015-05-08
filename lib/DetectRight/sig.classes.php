<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 * @deprecated ?
 */
/******************************************************************************
Name:    sig.classes.php
Version: 1.0.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2011 DetectRight Limited, All Rights Reserved

This library is licenced under the DetectRight Software License Agreement, the latest
version of which can be found at http://www.detectright.com/downloads.html
 
Please see the full licence for the full details under which you can use this software. 

**********************************************************************************/
DetectRight::registerClass("DoCoMo_validator");


Class DoCoMo_validator extends Validator {
	
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($uAgent) {
		// processes the string, and returns nothing.
		// the next two things should really be moved into the Developer Platform for i-mode
		//$this->profileChanges[]="has=chtml{flag:1}";
		//$this->profileChanges[]="preference=markup{value:chtml;flag:1}";
		
		// look for (c or /c to get cache
		$tmp = DRFunctionsCore::splitFirstDelimiter($uAgent,array("(c","/c"));
		$dump = array_shift($tmp);
		if (!isset($remains[0])) return;
		$remains = array_shift($tmp);
		$tmp = DRFunctionsCore::splitFirstDelimiter($remains,array("/",";",")")); // where are the split delimiters?
		$cache = array_shift($tmp);
		if (!DRFunctionsCore::isEmpty($cache)) {
			$decksize=$tmp[3]*1024;
			$this->profileChanges[]="capacity=wml{max:$decksize;units:bytes}";
			// assumes cache size is K, expresses wmldecksize in bytes
		}

		$next = array_shift($tmp);
		if (substr($next,0,1) !=="W") {
			$tmp = DRFunctionsCore::splitFirstDelimiter($next,array("W"));
			$size = array_shift($tmp);
		}
		if ($size) {
			$x=substr($size,0,2);
			$y=substr($size,3,2);

			$this->profileChanges[]="width=screen{max:$x;units:chars}";
			$this->profileChanges[]="height=screen{max$y;units:chars}";
		}
		return "";
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
		return $value;
	}		
}

