<?php

	/********************************* ROOT OPTIONS ***************/
	$ROOT = "/web/detectright";

	/**
	 * Home of the website this is living
	 *
	 * @DetectRight::string
	 * @internal
	 * @access public
	 */
	DetectRight::$HOME=$ROOT."/public_html";
	
	$classes = array(
			"Datapoint"=>"datapoint.class.php",
			"DataQuery"=>"dataquery.class.php",
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
			"SigPart"=>"sigpart.class.php",
	
			// DB Handlers
			"DBLink"=>"dblink.class.php",
			"DiskDB"=>"diskdb.class.php",
			"MySQL"=>"dbengine.mysql.class.php",
			"SQLite"=>"dbengine.sqlite.class.php",			
			"SQLite2"=>"dbengine.sqlite.class.php",			
			"RecordSet"=>"recordset.class.php",
			
			// Cache Handlers
			"DRCache"=>"drcache.class.php",
			"Memcached"=>"cacheengine.memcache.class.php",
			"FileCache"=>"cacheengine.file.class.php",
			"DBCache"=>"cacheengine.db.class.php",
			"APCCache"=>"cacheengine.apc.class.php",
			"RedisCache"=>"cacheengine.redis.class.php",
			
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
			"numver_validator"=>"standard.classes.php",
			"alphaver_validator"=>"standard.classes.php",
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
			"wurfl_charset_validator"=>"wurfl.classes.php",
			"wurfl_boolean_validator"=>"wurfl.classes.php",
			"wurfl_h264_codec_validator"=>"wurfl.classes.php",
			"wurfl_nokia_series_validator"=>"wurfl.classes.php",
			"ConnectionLostException"=>"connectionlost.exception.class.php",
			"DeviceNotFoundException"=>"devicenotfound.exception.class.php",
			"DetectRightException"=>"detectright.exception.class.php",
			
			// quantum
			"StateCollection"=>"statecollection.class.php",
			"State"=>"state.class.php",
			"QDTMessage"=>"qdtmessage.class.php"
			);
			

			function __autoload($class) {
 				global $classes;
				if (!isset($classes[$class])) return;
 				global $ROOT;
 				$fn = $ROOT."/public_html/drexpress/".$classes[$class];
 				//echo $fn."...";
    			require($fn);
    			//echo "Done.\n";
			}