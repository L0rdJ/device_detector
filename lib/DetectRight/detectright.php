<?php
/******************************************************************************
Name:    detectright.php
Version: 2.8
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2014 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License 
Agreement, the latest version of which can be found at 

http://www.detectright.com/legal-and-privacy.html

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance, 
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

The summary here is intended to highlight some parts of the agreement, but is only 
an illustration and does not itself form part of the agreement itself.

The agreement does not contain any redistribution rights for the software (whether modified or unmodified), 
but it does allow modification of the source code by the end-user, with the understanding that
modified builds of the software are not supported by DetectRight without a premium support license.

For license fees for commercial use, please check the current rates and offers at 
http://www.detectright.com/device-detection-products.html

The database file accessed by this API should be downloaded solely from 
http://www.detectright.com through the user control panel (free registration). 

Your fair use and other rights are in no way affected by the above.

2.8 - this is now a stub loader aliasing the core. This DetectRight object handles loading for the core.
******************************************************************************/
/**
 * @author Chris Abbott <chris@detectright.com>
 * @package DetectRight
 */
/********************************* ROOT OPTIONS **********************************/
/* IMPORTANT: This has changed since previous versions to make the variable name */
/* much less ambiguous (used to be $ROOT).										 */
/*********************************************************************************/
if (!isset($DRROOT) || !$DRROOT) {
// recovery
	$DRROOT = dirname(__FILE__);
	//echo $DRROOT."\n";
	/*$fn = $_SERVER['SCRIPT_FILENAME'];
	$tmp = pathinfo($fn);
	$ROOT = $tmp['dirname'];*/
}

// helper functions and classes.
include_once($DRROOT."/classloader.class.php");
// Check for use in HipHop and load all classes if so.
if(isset($_ENV['HPHP'])) {
	DR_ClassLoader::loadAllClasses();
} else {
	spl_autoload_register(array('DR_ClassLoader', 'loadClass'));
}
include_once($DRROOT."/functions.core.class.php");
include_once($DRROOT."/drcore.class.php");
Class DetectRight extends DRCore {
	// Alias of DRCore
}
DetectRight::$HOME=$DRROOT;
include_once($DRROOT."/detectright.conf.php");
