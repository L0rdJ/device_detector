<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    standard.classes.php
Version: 2.7.0
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
2.7.0 - changes to how content validator invokes load()
2.7.0 - content validator now uses translationImport and translationExport
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("positive_integer_validator");
	DetectRight::registerClass("abs_integer_validator");
	DetectRight::registerClass("positive_number_validator");
	DetectRight::registerClass("url_validator");
	DetectRight::registerClass("boolean_supported_validator");
	DetectRight::registerClass("boolean_validator");
	DetectRight::registerClass("underscore_version_validator");
	DetectRight::registerClass("dot_version_validator");
	DetectRight::registerClass("dimension_validator");
	DetectRight::registerClass("integer_validator");
	DetectRight::registerClass("positive_float_validator");
	DetectRight::registerClass("bytesize_validator");
	DetectRight::registerClass("datetime_validator");
	DetectRight::registerClass("model_validator");
	DetectRight::registerClass("none_validator");
	DetectRight::registerClass("manufacturer_validator");
	DetectRight::registerClass("color_validator");
	DetectRight::registerClass("content_validator");
	DetectRight::registerClass("jsr_validator");
	DetectRight::registerClass("numver_validator");
	DetectRight::registerClass("screencolors_validator");
	DetectRight::registerClass("mimetype_validator");
	DetectRight::registerClass("alphaver_validator");
	DetectRight::registerClass("pixels_to_megapixels_validator");
}

Class megapixels extends Validator {
	public $returnOriginalValueOnValidationFail=false;
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if ($value === "VGA" || $value === "vga" || $value === "webcam") return 0.3;
		if ($value > 1000) return $value / 1000000;
		$value = strtolower($value);
		$value = trim(str_replace(array("megapixels","megapixel","m","p","s"),"",$value));
		if (!is_numeric($value)) return null;
		$value = $value + 0.00;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}	
}

Class megapixels_to_pixels_validator extends Validator {
	public $returnOriginalValueOnValidationFail=false;
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if ($value === "VGA" || $value === "vga" || $value === "webcam") return 300000;
		if (!is_numeric($value)) return null;
		if ($value < 100) return $value;
		$value = $value * 1000000;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		if (is_float($value))
			return $value * 1000000;
		return $value;
	}	
}
// Java fix?
Class pixels_to_megapixels_validator extends Validator {
	public $returnOriginalValueOnValidationFail=false;
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if ($value === "VGA" || $value === "vga" || $value === "webcam") return 0.3;
		if (!is_numeric($value)) return null;
		if (!is_int($value)) return null;
		$value=$value+0;
		$value = $value / 1000000;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		if (is_float($value))
			return $value / 1000000;
		return $value;
	}	
}

/**
 * Class for validating positive integers
 *
 */
Class positive_integer_validator extends Validator {
	public $returnOriginalValueOnValidationFail=false;
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (!is_numeric($value)) return null;
		$value=$value+0;
		if ($value >= PHP_INT_MAX && strpos($value,".") === false) return $value;
		if (!is_int($value)) return null;
		if ($value<0) return null;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}
}

/**
 * Class for validating absolute integers
 * 
 *
 */
Class abs_integer_validator extends Validator {
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (!is_numeric($value)) return null;
		$value=$value+0;
		if (!is_int($value)) return null;
		$value = abs($value);
		return $value;
	}

	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		return abs($value);
	}	
}

/**
 * Class for validating positive numbers generally
 * @package detectright_metastore
 */
Class positive_number_validator extends Validator {
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (!is_numeric($value)) return null;
		$value=$value+0;
		if ($value<0) return null;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param number $value
	 * @return number
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}	
}

/**
 * Class for validating URLs
 */
Class url_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		$valid=preg_match('/^(http|https):\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(:(\d+))?\//i', $value);
		if ($valid) return $value;
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

/**
 * Class which validates a boolean value using common phrases such as
 * "true" (string), "yes", "No", etc.
 */
Class boolean_validator extends Validator {
	
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (is_bool($value)) return $value;
		if ($value=="") return 0;
		$value = strtolower($value);
		if ($value === "yes") return 1;
		if ($value === "no") return 0;
		if ($value === "true") return 1;
		if ($value === "false") return 0;
		if ($value === "yep") return 1;
		if ($value === "nope") return 0;
		if ($value === "supported") return 1;
		if ($value === "not supported") return 0;
		if ($value === "1") return 1;
		if ($value === "0") return 0;
		if ($value === 0) return 0;
		if ($value === 1) return 1;
		return null;
	}
	
	/**
	 * Export
	 *
	 * @param boolean $value
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function export($value) {
		if (is_null($value)) return null;
		return ($value ? "true" : "false");
	}
}

/**
 * Class which returns whether something is supported or not based
 * on a boolean value
 */
Class boolean_supported_validator extends boolean_validator {	
	/**
	 * Export
	 *
	 * @param boolean $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function export($value) {
		if ($value) return "supported";
		return "not_supported";
	}
}


/**
 * Underscore version validator - for when we need versions
 * with underscores, not dots.
 */
Class underscore_version_validator extends Validator {

	/**
	 * Process
	 * we're receiving an underscored version which needs changing to a dot version
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		$value=str_replace("_",".",$value);
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
		$value=str_replace(".","_",$value);
		return $value;
	}
}

/**
 * Dot version validator, for when we need versions with dots,
 * not underscores.
 *
 */
Class dot_version_validator extends Validator {
	/**
	 * Process
	 * we're receiving an underscored version which needs changing to a dot version
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		// make sure that this is indeed a version
		$value=str_replace(array("_",","),".",$value);
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
		return $value;
	}
}

/**
 * 128x160 type dimension validator
 */
Class dimension_validator extends Validator {
	public $returnOriginalValueOnValidationFail=false;
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		$tmp=explode("x",strtolower($value));
		if (count($tmp)==1) {
			$tmp=explode("*",strtolower($value));
		}
		if (count($tmp) > 3) return null;
		if (count($tmp) < 2) return null;
		
		$thirdDim = "";
		switch (count($tmp)) {
			case 3:
				if (!is_numeric(trim($tmp[2]))) return null;
				$thirdDim = "x".$tmp[2];
			case 2:
				if (!is_numeric(trim($tmp[0]))) return null;
				if (!is_numeric(trim($tmp[1]))) return null;
				$result = trim($tmp[0])."x".trim($tmp[1]).$thirdDim;
				break;
		}
		
		return $result;
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

/**
 * Validator for integers generally
 *
 */
Class integer_validator extends Validator {
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (!is_numeric($value)) return null;
		$value=$value+0;
		if ($value >= PHP_INT_MAX && strpos($value,".") === false) return $value;
		if (!is_int($value)) return null;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param integer $value
	 * @return integer
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}
}

/**
 * Positive float values validated here
 *
 */
Class positive_float_validator extends Validator {
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return float
	 * @access public
	 * @internal
	 */
	function process($value) {
		$value = trim($value);
		if (!is_numeric($value)) return null;
		$value=$value+0.00;
		if (!is_float($value)) return null;
		if ($value<0.00) return null;
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param float $value
	 * @return float
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}
}

/**
 * Byte sizes are expressed in many different ways: this corrects
 * for the various permutations and returns a size in actual bytes.
 *
 */
Class bytesize_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		if (is_numeric($value)) return $value;
		if ($value=="dynamic") $value="1gb";
		$matcharray=
			array(
				"terabytes" => 1024*1024*1024*1024,
				"gigabytes"=>1024*1024*1024,
				"megabytes"=>1024*1024,
				"kilobytes"=>1024,
				"tbytes" =>1024*1024*1024*1024,
				"gbytes"=>1024*1024*1024,
				"mbytes"=>1024*1024,
				"kbytes"=>1024,
				"bytes"=>1,
				"mbps"=>1024*128,
				"kbps"=>128,
				"bps"=>-8,
				"tb"=> 1024*1024*1024*1024,
				"gb"=>1024*1024*1024,
				"mb"=>1024*1024,
				"kb"=>1024,
				"t"=> 1024*1024*1024*1024,
				"g"=>1024*1024*1024,
				"m"=>1024*1024,
				"k"=>1024,
				"b"=>1,
				);
				
		$multiplier=1;
		$value=strtolower($value);
		foreach ($matcharray as $memunit=>$multiplier) {
			if (strpos($value,$memunit) !== false) {
				$value=str_replace($memunit,"",$value);
				$value=trim($value);
				if (is_numeric($value)) {
					break;
				}
			}
		}
		try {
			if ($multiplier > 0) {
				$value=$value*$multiplier;
				if ($value < 0) return null;
			} elseif ($multiplier < 0) {
				$multiplier = abs($multiplier);
				$value = $value / $multiplier;
			}
		} catch (Exception $e) {
			return null;
		}
		return $value;
	}
	
	function export($value) {
		return $value;
	}
}

/**
 * Validates a datetime.
 *
 */
Class datetime_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		$valid = ($time = strtotime($value));
		if ($valid) return $value;
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

/**
 * Validates the heck out of Nuns.
 * Actually, it's just a dummy.
 *
 */
Class none_validator extends Validator {
	/**
	 * Process
	 *
	 * @param mixed $value
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function process($value) {
		return $value;
	}
	
	/**
	 * Export
	 *
	 * @param mixed $value
	 * @return mixed
	 * @access public
	 * @internal
	 */
	function export($value) {
		return $value;
	}
}

/**
 * Validator for model names, currently a passthrough.
 *
 */
Class model_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
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
		return $value;
	}
}

/**
 * Content validator, which goes into the translation and valid 
 * values tables to add profile fragments to incoming values.
 *
 */
Class content_validator extends Validator {
	function __construct($type) {
		parent::__construct($type);
		$this->onMissingAddToTranslation=true;
		$this->returnOriginalValueOnValidationFail=true;
	}

	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		
		if (!is_scalar($value)) return $value;
		$this->load(false);

		$pcChanges = null;		
		$translation=DRFunctionsCore::gv($this->translationImport,strtolower($value),array("replacement"=>"","data"=>""));
		$realString=$translation['replacement'];
		$data = DRFunctionsCore::gv($translation,'data');
		if ($data) {
			$pcChanges=explode("\n",$data);
		}
		
		if ($realString !== null && $realString !== "")  {
			$return=$realString;
		} else {
			if ($translation === null || count($translation) === 2) {
				if ($this->onMissingAddToTranslation) {
					$this->addTranslation($value,"",array(),2);
				}
			}
			if ($this->returnOriginalValueOnValidationFail) {
				$return=$value;
			} else {
				$return="";
			}
		}
		
		if (!empty($pcChanges)) $this->pcArray = array_merge($this->pcArray,$pcChanges);
		return $return;
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
		// type is a type such as "wurfl_markup"
		// if this type
		$this->load(true);
		if (!is_array($this->translationExport)) return $value; // probably a db error

		foreach ($this->translationExport as $key=>$array) {
			if ($array['replacement']===$value) {
				return $key;
			}
		}

		return $value;
	}
}

/**
 * Manufacturer validator: a special type of content validator.
 *
 */
Class manufacturer_validator extends content_validator {
	/**
	 * Constructor
	 *
	 * @param string $type
	 * @return manufacturer_validator
	 * @internal 
	 * @access public
	 */
	function manufacturer_validator($type) {
		parent::__construct($type);
		$this->onMissingAddToTranslation=true;
		$this->returnOriginalValueOnValidationFail=true;
	}
}

/**
 * Makes sure hex color strings are in upper case.
 *
 */
Class color_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		return strtoupper($value);
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
		return strtoupper($value);
	}	
}

/**
 * Makes sure JSRs are in upper case
 *
 */
Class jsr_validator extends Validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */
	function process($value) {
		return DRFunctionsCore::punctClean(strtoupper($value));
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
		return DRFunctionsCore::punctClean(strtoupper($value));
	}	
}

/**
 * "Validates" a mimetype: actually, it looks it up, and then acquires 
 * extra data for it.
 *
 */
Class mimetype_validator extends content_validator {
	/**
	 * Process
	 *
	 * @param string $value
	 * @return string
	 * @access public
	 * @internal
	 */	
	function process($value) {
		//DetectRight::checkPoint("Validating mimetype $value");
		// technically this is just a content
		$this->onMissingAddToTranslation=false;
		$this->returnOriginalValueOnValidationFail=true;

		return parent::process($value);
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
		// type is a type such as "wurfl_markup"
		// if this type
		return $value;
	}
}

Class measurement_validator extends Validator {
	function process($value) {
		// zero and return proper stuff to $this->profileChanges
	}
	
	function export($value) {
		
	}
}

Class battery_standby_validator extends Validator {
	function process($value) {
		// return nothing but also PC stuff with the proper connection with hours and stuff
	}
	
	function export($value) {
		
	}
}

Class video_profile_validator extends Validator {
	function process($value) {
		// would check for a proper dimension with an @ and a valid FPS
		// @todo
		return $value;
	}
	
	function export($value) {
		return $value;
	}
}

Class screencolors_validator extends Validator {
	function process($value) {
		// would check for a proper dimension with an @ and a valid FPS
		// @todo
		$value = trim(str_ireplace("colors","",$value));
		$value = trim(str_ireplace("colors","",$value));
		$value = str_replace(" ","",$value);
		$value = trim(str_ireplace("262K","262144",$value));
		$value = trim(str_ireplace("262000","262144",$value));
		$value = trim(str_ireplace("65K","65536",$value));
		$value = trim(str_ireplace("16M","16777216",$value));
		$value = trim(str_ireplace("4B","4294967296",$value));
		return $value;
	}
	
	function export($value) {
		return $value;
	}
}

Class volume_validator extends Validator {
	function process($value) {
		// TBD
		return $value;
	}
	
	function export($value) {
		return $value;
	}
}

// this enforces numeric version numbers by tossing out anything with alpha in it.
Class numver_validator extends Validator {
	function process($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$value = trim($value);
		if ($value === "") return null;
		if ($value[0] === "(" ) {
			$value = str_replace("(","",$value);
			$value = str_replace(")","",$value);
		}

		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (!DRFunctionsCore::isNumeric($checkString)) return null;
		return $value;		
	}
	
	function export($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (!DRFunctionsCore::isNumeric($checkString)) return null;
		return $value;
	}
}

// this enforces numeric version numbers by tossing out anything with alpha in it.
Class alphaver_validator extends Validator {
	function process($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$value = trim($value);
		if ($value === "") return null;
		if ($value[0] === "(" ) {
			$value = str_replace("(","",$value);
			$value = str_replace(")","",$value);
		}

		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (DRFunctionsCore::isNumeric($checkString)) return null;
		return $value;		
	}
	
	function export($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (DRFunctionsCore::isNumeric($checkString)) return null;
		return $value;
	}
}

// this enforces numeric version numbers by tossing out anything with alpha in it (kind of)
Class numver_loose_validator extends Validator {
	function process($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$value = trim($value);
		if ($value === "") return null;
		if ($value[0] === "(" ) {
			$value = str_replace("(","",$value);
			$value = str_replace(")","",$value);
		}

		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (!DRFunctionsCore::isMostlyNumeric($checkString)) return null;
		return $value;		
	}
	
	function export($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (!DRFunctionsCore::isMostlyNumeric($checkString)) return null;
		return $value;
	}
}

// this enforces numeric version numbers by tossing out anything with alpha in it.
Class alphaver_loose_validator extends Validator {
	function process($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$value = trim($value);
		if ($value === "") return null;
		if ($value[0] === "(" ) {
			$value = str_replace("(","",$value);
			$value = str_replace(")","",$value);
		}

		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (DRFunctionsCore::isMostlyNumeric($checkString)) return null;
		return $value;		
	}
	
	function export($value) {
		if (empty($value)) return null;
		if ($value === "p") return null;
		if ($value[0] === "R" || $value[0] === "V") $value = trim(substr($value,1));
		$checkString = $value;
		if (isset($value{1})) {
			$checkString = substr($value,0,-1);
		} 
		if (DRFunctionsCore::isMostlyNumeric($checkString)) return null;
		return $value;
	}
}