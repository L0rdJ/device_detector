<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    validator.class.php
Version: 2.8.0
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
2.8.0 - added special "none" handling.
**********************************************************************************/
if (class_exists("DetectRight")) {
	DetectRight::registerClass("Validator");
}
/*

valid_values table
 `ID` int(10) unsigned NOT NULL auto_increment,                                              
                `type` varchar(40) collate latin1_general_ci NOT NULL COMMENT 'type of valid value',        
                `value` varchar(255) collate latin1_general_ci NOT NULL COMMENT 'the valid value',          
                `additional` text collate latin1_general_ci NOT NULL COMMENT 'additional profile changes',  
                
                This table's job is to hold valid values and also assign extra bits of fragment to them.
*/
/**
 * Validator class: makes organic unstructured data a little bit cleaner, mostly through the use of lookup
 * lists and attaching more detailed data to certain values for certain fields pairs.
 *
 * version 2.7.4 - suppress zero length strings in Validation export table.
 */
Class Validator {
	/* this class's job is to validate! */

	static $cacheLink;
	protected $cache;
	
	static $dbLink;
	protected $db;
	
	// to hold the last results
	static $profileChanges;
	
	/**
	 * If we get something to validate that doesn't appear in the translation table,
	 * do we add it?
	 *
	 * @static boolean
	 * @internal 
	 * @access public
	 */
	static $doNotAddToTranslationTable=true;

	/**
	 * Do we store validators in the cache for use later? This can save a lot of time
	 * for import/export, especially for the mime-type validators.
	 *
	 * @static boolean
	 * @internal 
	 * @access public
	 */
	static $storeValidatorsInCache=false;
	
	/**
	 * Type of validator
	 *
	 * @public string
	 * @access public
	 * @internal
	 */
	public $type;
	
	/**
	 * Array of translation rows from the translation table.
	 *
	 * @var array
	 * @internal 
	 * @access public
	 */
	protected $translationImport=null;
	protected $translationExport=null;

	/**
	 * Cache key for validity information
	 *
	 * @var string
	 * @access protected
	 * @internal
	 */
	protected $validKey;
	
	/**
	 * Cache key for translation information
	 *
	 * @var string
	 * @access protected
	 * @internal
	 */
	protected $translateKey;
			
	/**
	 * Do we return the original value passed in, or go strict and pass nothing back if the validation fails?
	 *
	 * @var boolean
	 * @access public
	 * @internal
	 */
	public $returnOriginalValueOnValidationFail=true;
	
	/**
	 * Holds extra data generated by the validation process.
	 *
	 * @var associative_array
	 * @access public
	 * @internal
	 */
	public $pcArray;
	
	/**
	 * Version picked up by validation process
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $version;
		
	/**
	 * Where did we pick this validator up from? Is it brand new, from cache, or from memory?
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $source="NEW";
	
	/**
	 * Array of validator objects. This is helpful for mime type validators.
	 *
	 * @staticvar array
	 * @access public
	 * @internal
	 */
	static $validators=array();
	
	/**
	 * Cache lifetime for validators. Set quite long.
	 *
	 * @staticvar integer
	 * @access public
	 * @internal
	 */
	static $cache_timeout=14400;
		
	/**
	 * Translation table name
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $table_translation="schema_property_values";
	
	/**
	 * Constructor
	 *
	 * @param string $type		Type of validator this is.
	 * @return Validator
	 * @access public
	 * @internal
	 */

	function __construct($type) {
		$this->cacheDB();
		
		$this->type=$type;	
		$this->translateKey=DetectRight::cacheKey("DR_TRANSLATE_$type");
		// we need to differentiate between zero arrays (validation type looked up, empty) and null (needs looking up)
		//$this->translation=array();
		$this->pcArray=array();
	}

	public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		$this->cache = self::$cacheLink;
		
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
		$this->db = self::$dbLink;		
	}
	
	public function __wakeup() {
		$this->cacheDB();
	}
	
	public function __sleep() {
		/*$this->cache = null;
		$this->db = null;*/
		$ov = get_object_vars($this);
		unset($ov['cache']);
		unset($ov['db']);
		return array_keys($ov);
	}

	/**
	 * Add a translation and mess with the cache to insert it.
	 *
	 * @param string $value
	 * @param string $version
	 * @return integer
	 * @access public
	 * @internal
	 */
	function addTranslation($value,$replacement="",$data="",$status=2) {
		if (DRFunctionsCore::isEmptyStr($value)) return false;
		//if (self::$doNotAddToTranslationTable === false) return false;
		if (is_object($data)) $data = $data->toString();
		if (is_array($data)) $data = implode("\n",$data);
		if (DRFunctionsCore::isEmptyStr($replacement)) $replacement = $value;
		$array = array("validation_type"=>$this->type,"value"=>$value,"replacement"=>$replacement,"data"=>$data,"status"=>$status);
		$this->db->insertData(self::$table_translation,$array,0,true);
		// reload the translation table
		$translationValue=array("ID"=>"TBA","validation_type"=>$this->type,"value"=>$value,"replacement"=>$replacement,"data"=>$data,"status"=>$status);
		$this->translationImport[$value]=$translationValue;
		$this->translationExport[$replacement]=$translationValue;
		/*if ($this->cache->cache_ok()) {
			$translation=$this->cache->cache_get($this->translateKey);
			if (!is_array($translation)) {
				$translation=array();
			}
			$translation[$value]=$translationValue;
			$this->cache->cache_set($this->translateKey,$translation,1,600);
		}*/
		return true;
	}

	/**
	 * Add a translation and mess with the cache to insert it.
	 *
	 * @param string $type
	 * @param string $value
	 * @param string $version
	 * @return integer
	 * @access public
	 * @static 
	 * @acl 9
	 */
	static function addTranslationForType($type,$value) {
		if (DRFunctionsCore::isEmptyStr($value)) return false;
		$validator = self::getValidator($type);
		return $validator->addTranslation($value);
	}

	/**
	 * Get a validator
	 *
	 * @param string $type
	 * @return Validator
	 * @access public
	 * @internal
	 */
	static function getValidator($type) {
		$validator="";
		$classname=$type."_validator";

		if ($type === "NONE") $type = "none";
		if (!class_exists($classname,true)) {
			$classname="content_validator";
			$key="content_validator_$type";
			$inbuilt = false;
		} else {
			$key="$classname";
			$inbuilt=true;
		}

		if (isset(self::$validators[$key])) {
			$validator=self::$validators[$key];
			$validator->source = "MEMORY";
		}

		// jury is out on the whole memcache thing, especially what it does to resource objects, which probably needs the "sleep" and "wakeup" methods
		// restablishing database and cache links.
		if (self::$storeValidatorsInCache && !is_object($validator) && self::$cacheLink->cache_ok() && $key && !DetectRight::$flush && !$inbuilt) {
			$validator = self::$cacheLink->cache_get(DetectRight::cacheKey($key));
			if (is_object($validator)) {
				$validator->source = "CACHE";
			}
		}
		
		if (!is_object($validator)) $validator = new $classname($type);
		return $validator;
	}
		
	/**
	 * Store a validator in memory/cache for later retrieval
	 *
	 * @param Validator $validator
	 * @return boolean
	 * @access public
	 * @static 
	 * @internal
	 */
	static function storeValidator($validator) {
		if (!is_object($validator)) return false;
		$success = false;
		$type = $validator->type;
		if ($validator->source == "MEMORY") return false;
		
		$classname=$type."_validator";

		if (!class_exists($classname)) {
			$classname="content_validator";
			$key="content_validator_$type";
		} else {
			$key="$classname";
		}

		self::$validators[$key]=$validator;
		if ($validator->source == "CACHE") return true;
		if (self::$storeValidatorsInCache && self::$cacheLink->cache_ok() && !class_exists($key)) {
			$success = self::$cacheLink->cache_set(DetectRight::cacheKey($key),$validator,self::$cache_timeout);
		}
		return $success;
	}
	
	function load($export = false) {
		if (!$export) {
			$this->loadImport();
		} else {
			$this->loadExport();
		}
	}
	/**
	 * Load the translation stuff for this. Not done from cache when flush is true.
	 *
	 * @param string $pk	Keying the resulting array by the string being translated
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function loadImport() {
		/* lolz  I'm in ur eyes, writing ur DDR */
		if (is_array($this->translationImport) && !DetectRight::$flush) return true;
		$key = "";
		$wc = array("validation_type"=>$this->type);
		$wc['status'] = "1";
		$translation=$this->db->simpleFetch(self::$table_translation,array("*"),$wc,"","","value");
		if (!is_array($translation)) return false;
		$translation=array_change_key_case($translation,CASE_LOWER);
		
		$this->translationImport=$translation;
		return true;
	}
	
	/**
	 * Load the translation stuff for this. Not done from cache when flush is true.
	 *
	 * @param string $pk	Keying the resulting array by the string being translated
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function loadExport() {
		/* lolz  I'm in ur eyes, writing ur DDR */
		/* lolz  I'm in ur eyes, writing ur DDR */
		if (is_array($this->translationExport) && !DetectRight::$flush) return true;
		$key = "";
		$translation = null;
		
		if ($translation !== null) {
			$wc = array("validation_type"=>$this->type);
			$wc['status'] = "1";
			$wc['replacement'] = array("op"=>"<>","value"=>""); // Java fix (non-urgent).
			$translation=$this->db->simpleFetch(self::$table_translation,array("*"),$wc,"","","replacement");
			if (!is_array($translation)) return false;
			$translation=array_change_key_case($translation,CASE_LOWER);
		}
		
		$this->translationExport=$translation;
		return true;
	}
		
	/**
	 * We VALIDATE! GRATE!
	 *
	 * @param string $type
	 * @param string $value
	 * @param associative_array $profileChanges
	 * @param string $version
	 * @param boolean $addToTranslationTableIfNotFound
	 * @return $string
	 * @access public
	 * @static
	 * @acl 9
	 */
	static function validate($type,$value,$export=false) {
		$pc = array();
		return self::validateWithPC($type,$value,$export,$pc);
	}
	
	static function validateWithPC($type,$value,$export=false,&$pc) {
		// this is where we create an object to validate.
		// we've got lots of objects.
		//$value=trim(str_replace(array("\n","\r","\t"),"",$value));
		if ($type === null) return $value;
		if ($type === "none") return $value;
		if ($type === "") return $value;
		
		$validator = self::getValidator($type);
		/* @var $validator Validator */
		$validator->pcArray = array();
		if (!$export) {
			$value=$validator->process($value);
		} else {
			$value=$validator->export($value);
		}
		
		$pc = array_merge($pc,$validator->pcArray);
		$validator->pcArray = array();
		self::storeValidator($validator);
		return $value;
	}

	/**
	 * Validating for export
	 *
	 * @param string $type
	 * @param string $value
	 * @param boolean $addToTranslationTableIfNotFound
	 * @return string
	 * @acl 9
	 * @access public
	 * @static
	 */
	static function validateExport($type,$value) {
		// this is where we create an object to validate.
		// we've got lots of objects.
		
		// some types are corrected on the way in, but not on the way out.
		// things like manufacturer and mimetype.
		return self::validate($type,$value,true);
	}
	
	/**
	 * Translation happens here from the translation table.
	 *
	 * @param string $thing
	 * @param string $type
	 * @param array $profileChanges
	 * @return string
	 * @internal
	 * @static
	 * @access public
	 */
	static function translation($thing,$type) {
		$pc = array();
		return self::translationWithPC($thing,$type,$pc);
	}
	
	static function translationWithPC($thing,$type,&$pc) {
		// we're going to use this for all sorts of things.
		// we also need to make this check in lookup tables first, especially for outputs!
		$where = array("value"=>$thing,"validation_type"=>$type);
		$result = self::$dbLink->simpleFetch(self::$table_translation,array("*"),$where,"",array("limit"=>1));
		if ($result==false) return $thing;

		$row = array_shift($result);
		if (!$row) return $thing;

		if ($row['replacement']) {
			$thing=$row['replacement'];
		}
		
		$newChanges=DRFunctionsCore::gv($row,'data');
		if ($newChanges) {
			$newChanges=explode("\n",$newChanges);
			foreach ($newChanges as $newChange) {
				$tmp=explode("=",$newChange,2);
				$pc[$tmp[0]]=str_replace(array("\r","\n"),"",$tmp[1]);
			}
		}
		return $thing;
	}
}