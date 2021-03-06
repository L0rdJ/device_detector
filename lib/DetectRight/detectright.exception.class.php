<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    detectright.exception.class.php
Version: 2.2.1
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

� 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License 
Agreement, the latest version of which can be found at 

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance, 
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

2.2.1 - made e optional
**********************************************************************************/

Class DetectRightException extends Exception {
	public $ex;
	
	function __construct($message, $e = null) {
		parent::__construct($e);
		$this->ex = $e;
	}
}