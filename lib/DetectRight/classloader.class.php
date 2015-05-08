<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    classloader.class.php
Version: 2.8.0
Config:  default
Author:  Chris Abbott, chris@detectright.com
Support: http://www.detectright.com

© 2012 DetectRight Limited, All Rights Reserved

THIS IS NOT OPEN SOURCE SOFTWARE.

This library's default licencing is under the DetectRight Evaluation License 
Agreement, the latest version of which can be found at 

http://www.detectright.com/legal-and-privacy.html.

Use of this library will be deemed to be an acceptance of those terms and conditions,
and must be adhered to unless you have signed a difference license with us (for instance, 
for development, non-profit, social community, OEM, Enterprise or Commercial).

Further details can be found at www.DetectRight.com

2.2.2 - added Analysis and Result classes
2.3.0 - added DRPRofile and DRProfileResult classes
2.8.0 - changed load all classes to actually work. D'oh. Added cat manager.
**********************************************************************************/

Class DR_ClassLoader {
	
	static $classes = array(
			"Datapoint"=>"datapoint.class.php",
			"DataQuery"=>"dataquery.class.php",
			"DetectorCore"=>"detector.core.class.php",
			"DRCatManager"=>"enterprise/drcatmanager.class.php",
			"DRProfile"=>"drprofile.class.php",
			"DRProfileResult"=>"drprofileresult.class.php",
			"EntityCore"=>"entity.core.class.php",
			"EntityAliasCore"=>"entityalias.core.class.php",
			"EntityPackage"=>"entitypackage.class.php",
			"EntityProfileCore"=>"entityprofile.core.class.php",
			"EntitySig"=>"entitysig.class.php",
			"EntitySigCollection"=>"entitysigcollection.class.php",
			"HTTPAccept"=>"httpaccept.class.php",
			"HTTPConnection"=>"httpconnection.class.php",
			"HTTPHeadersCore"=>"httpheaders.core.class.php",
			"HTTPLocale"=>"httplocale.class.php",
			"IP"=>"ip.class.php",
			"PointerCore"=>"pointer.core.class.php",
			"Property"=>"property.class.php",
			"QuantumDataCollection"=>"quantumdatacollection.class.php",
			"QuantumDataTree"=>"quantumdatatree.class.php",			
			"SchemaPropertyCore"=>"schemaproperty.core.class.php",
			"Sig"=>"sig.class.php",
			"SigGroup"=>"siggroup.class.php",
			"SigPart"=>"sigpart.class.php",
	
			// DB Handlers
			"DBLink"=>"dblink.class.php",
			"SQLLink"=>"sqllink.class.php",
			"NoSQLLink"=>"nosqllink.class.php",
			"MySQL"=>"dbengine.mysql.class.php",
			"DRMySQL"=>"dbengine.mysql.class.php",
			"SQLite"=>"dbengine.sqlite.class.php",			
			"DRSQLite"=>"dbengine.sqlite.class.php",
			"RecordSet"=>"recordset.class.php",
			
			// Cache Handlers
			"DRCache"=>"drcache.class.php",
			"Memcached"=>"cacheengine.memcache.class.php",
			"DRMemcached"=>"cacheengine.memcache.class.php",
			"APCCache"=>"cacheengine.apc.class.php",
			"DRAPCCache"=>"cacheengine.apc.class.php",
			"HeapCache"=>"cacheengine.heap.class.php",
			"DRHeapCache"=>"cacheengine.heap.class.php",
			"WinCache"=>"cacheengine.wincache.class.php",
			"DRWinCache"=>"cacheengine.wincache.class.php",
			"MySQLCache"=>"cacheengine.mysql.class.php",
			"DRMySQLCache"=>"cacheengine.mysql.class.php",
			"MySQLPDOCache"=>"cacheengine.mysqlpdo.class.php",
			"DRMySQLPDOCache"=>"cacheengine.mysqlpdo.class.php",
			"ZendSHMCache"=>"cacheengine.zshm.class.php",
			"DRZendSHMCache"=>"cacheengine.zshm.class.php",
			"ZendDiskCache"=>"cacheengine.zdsk.class.php",
			"DRZendDiskCache"=>"cacheengine.zdsk.class.php",
			"DRRollingCache"=>"rollingcache.class.php",
			
			// validators
			"Validator"=>"validator.class.php",
			"positive_integer_validator"=>"standard.classes.php",
			"abs_integer_validator"=>"standard.classes.php",
			"positive_number_validator"=>"standard.classes.php",
			"url_validator"=>"standard.classes.php",
			"boolean_supported_validator"=>"standard.classes.php",
			"boolean_validator"=>"standard.classes.php",
			"underscore_version_validator"=>"standard.classes.php",
			"dot_version_validator"=>"standard.classes.php",
			"dimension_validator"=>"standard.classes.php",
			"integer_validator"=>"standard.classes.php",
			"positive_float_validator"=>"standard.classes.php",
			"bytesize_validator"=>"standard.classes.php",
			"datetime_validator"=>"standard.classes.php",
			"model_validator"=>"standard.classes.php",
			"none_validator"=>"standard.classes.php",
			"manufacturer_validator"=>"standard.classes.php",
			"color_validator"=>"standard.classes.php",
			"content_validator"=>"standard.classes.php",
			"numver_validator"=>"standard.classes.php",
			"alphaver_validator"=>"standard.classes.php",
			"mimetype_validator"=>"standard.classes.php",
			"screencolors_validator"=>"standard.classes.php",
			"wurfl_charset_validator"=>"wurfl.classes.php",
			"wurfl_boolean_validator"=>"wurfl.classes.php",
			"wurfl_h264_codec_validator"=>"wurfl.classes.php",
			"wurfl_nokia_series_validator"=>"wurfl.classes.php",
			"wurfl_markup_validator"=>"wurfl.classes.php",
			"ConnectionLostException"=>"connectionlost.exception.class.php",
			"DeviceNotFoundException"=>"devicenotfound.exception.class.php",
			"DetectRightException"=>"detectright.exception.class.php",
			
			// quantum
			"StateCollection"=>"statecollection.class.php",
			"State"=>"state.class.php",
			"QDTMessage"=>"qdtmessage.class.php",
			
			// analytics
			"DRAnalytics"=>"dranalytics.class.php",
			"DRAnalysis"=>"dranalysis.class.php",
			"DRAnalysisResult"=>"dranalysisresult.class.php"
			);
			

	/**	 
	 * Loads a Class given the class name
	 *
	 * @param string $className
	 */
	public static function loadClass($className) {
		if (!isset(self::$classes[$className])) return false;
		if (!class_exists($className, false)) {
			$ROOT = DetectRight::$HOME;
			$url = self::$classes[$className];
			//echo $ROOT."/".$url."\n";
			require_once ($ROOT . "/". $url);
		}
		return true;
	}
	
	public static function loadAllClasses() {
		$array = self::$classes;
		foreach ($array as $class=>$file) {
			if (!class_exists($class)) {
				$ROOT = DetectRight::$HOME;
				$fn = $ROOT . "/". $file;
				if (file_exists($fn)) {
					require_once($fn);
				}
			}
		}
	}
}