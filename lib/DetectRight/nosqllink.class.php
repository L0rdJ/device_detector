<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    nosqllink.class.php
Version: 2.5
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

2.5.0 - added.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("NoSQLLink");
}

/**
 * Class which exists to get and set access to use a NoSQL source as a data source
 * in the world. It's assumed that this doesn't need cacheing because it already
 * essentially is a cache.
 * 
 * The default NoSQLLink is actually going to be a heap version (that starts off with no data, natch)
 * 
 * Note that we could potentially pass a DBLink to a NoSQLLink to populate it.
 *
 */
Class NoSQLLink extends DBLink {

}
