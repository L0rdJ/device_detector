<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    schemaproperty.core.class.php
Version: 2.2.1
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

/**
 * 2.0.7 - initialize where clause to prevent notice
 * 2.1.0 - allows flatter database reading
 * 2.1.1 - allows Booleans to be returned as booleans whatever.
 * 2.1.2 - make "property" an optional field in the constructor
 * 2.2.1 - handle non-arrays where arrays should be slightly better
 */
if (class_exists("DetectRight")) {
	DetectRight::registerClass("SchemaPropertyCore");
}

/**
 * Properties as stored in the schema_translate table.
 * 
 */
Class SchemaPropertyCore {

	/**
	 * Do we use string export names for output schemas: this corresponds to "display_name"
	 * in the schema_translate table
	 *
	 * @static boolean
	 * @internal 
	 * @access public
	 */
	static $strictExportNames=true;
	
	static $dbLink;
	static $cacheLink;
	static $useCache = false;
	/**
	 * Schemas stored here
	 *
	 * @var array
	 * @access public
	 * @internal
	 */
	static $schemaTranslate;
	
	static $qdt; // temporary storage
	static $qdc; // temporary storage
	static $profileChanges; // temporary array
	static $cacheArray=array();
	
	/**
	 * Autoincrementing ID
	 *
	 * @var integer
	 * @internal 
	 * @access public
	 */
	public $ID;
	
	/**
	 * Property name
	 *
	 * @var string
	 * @access public
	 * @acl 9
	 */
	public $property;
	
	/**
	 * property description devoid of spaces, dashes, underscores, etc.
	 *
	 * @var string
	 * @access public
	 * @acl 9
	 */
	public $property_desc;
	
	/**
	 * Variable Type
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $type; // variable type
		
	/**
	 * Human-friendly component context for top-level sorting.
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $component; // 
	
	
	/**
	 * Schema this belongs to
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $schema; // schema, of course.
		
	/**
	 * Validation type. Whatever value goes here will trigger a validator of the same name
	 * when exporting or importing subject to the flags
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $validation_type;

	/**
	 * Validation type. Whatever value goes here will trigger a validator of the same name
	 * when exporting or importing subject to the flags
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $validation_type_export;
	
	
	/**
	 * Do we validate this property on import?
	 *
	 * @var boolean
	 * @acl 9 
	 * @access public
	 * 
	 */
	public $validate_on_export=1;
		
	/**
	 * Is this entry an error trap? Sometimes there are misspellings in internal data sources. We pick them
	 * up here, but we don't want them to appear in output.
	 *
	 * @var boolean
	 * @access public
	 * @acl 9
	 */
	public $error_trap=0;
	
	/**
	 * Default Value
	 *
	 * @var mixed
	 * @access public
	 * @acl 9
	 */
	public $default_value="";
	
	/**
	 * Delimiter used when imploding for flat schema display
	 *
	 * @var string One character delimiter
	 * @access public
	 * @acl 9
	 */
	public $delimiter;
	
	/**
	 * Display name in the output
	 *
	 * @var string
	 * @access public
	 * @acl 9
	 */
	public $display_name;
	
	/**
	 * How trusted is this value for this schema? For instance, some UAProfile datapoints are highly untrustworthy.
	 *
	 * @var integer
	 * @access public
	 * @acl 9
	 */
	public $trust_offset=0;
	
	/**
	 * Mapping for export: contains the Universal schema mapping for this.
	 *
	 * @var string
	 * @acl 9 
	 * @access public
	 */
	public $output_map;
	
	
	/* CSV List of values to ignore */
	public $ignore_values;
	public $true_values;
	public $false_values;
	public $export_property;
	public $preferred_order;
	
	/**
	 * When interrogating, is this data filled (potentially) from child nodes
	 * of the one specified? Normally false.
	 *
	 * @var boolean
	 */
	public $use_subtree;
	
	/**
	 * Current primary key name
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $pk;
	
	/**
	 * LOL! Itz eror!
	 *
	 * @var unknown_type
	 */
	public $error;
	
	/**
	 * Current field list
	 *
	 * @var array
	 */
	public $fieldList;

	/**
	 * Default table to use
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $table="schema_properties";
	public $tablename="";
	
	/**
	 * Default PK to use
	 *
	 * @staticvar string
	 * @access public
	 * @internal
	 */
	static $PK="ID";
	static $fields=array("ID","property","type","component","schema","validate_on_export","validation_type","validation_type_export","delimiter","output_map","trust_offset","display_name","error_trap","default_value","property_desc","ignore_values","true_values","false_values","export_property","preferred_order","use_subtree");

	/**
	 * Constructor
	 *
	 * @param integer $id
	 * @param string $schema
	 * @param string $property
	 * @param array $data
	 * @return SchemaProperty
	 * @access public
	 * @internal
	 */
	function __construct($ID=0,$schema="",$property="") {
		$this->cacheDB();

		if ($ID === 0 && DRFunctionsCore::isEmptyStr($property)) return;
		$data = array();
		$this->fieldList = self::$fields;
		$this->pk = self::$PK;
		$this->tablename = self::$table;
		
		
		if ($ID) {
			$where = array($this->pk => $ID);
		} elseif ($schema && $property) {
			$where = array("schema" => $schema,"property"=>$property);
		} elseif ($property) {
			$where = array("property"=>$property);
		} else {
			return;
		}

		$property=self::$dbLink->simpleFetch($this->tablename,$this->fieldList,$where);
		if ($property===false) {
			trigger_error(self::$dbLink->sql_error(),E_WARNING);
			return;
		}
		$new=(count($property)==0);

		if ( $new ) {
			$this->error="Non-existent handset and no data...";
			return;
		}
		$data=array_shift($property);

		$data = self::process($data);
		
		foreach($data as $key=>$value) {
			$this->$key=$value;
		}		
	}

	public function cacheDB() {
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		if (is_null(self::$dbLink)) self::$dbLink = DetectRight::$dbLink;
	}
	
	public function __wakeup() {
		$this->cacheDB();
	}
	
	public function __sleep() {
		$ov = get_object_vars($this);
		return array_keys($ov);
	}

	static function process($data) {
		$tv = DRFunctionsCore::gv($data,'true_values','');
		$fv = DRFunctionsCore::gv($data,'false_values','');
		$iv = DRFunctionsCore::gv($data,'ignore_values','');
		if ($tv !== "") {
			$data['true_values'] = explode(',',$tv);
		} else {
			$data['true_values'] = array();
		}
		
		if ($fv !== "") {
			$data['false_values'] = explode(',',$fv);
		} else {
			$data['false_values'] = array();
		}
		
		if ($iv !== "") {
			$data['ignore_values'] = explode(',',$iv);
		} else {
			$data['ignore_values'] = array();
		}
		
		$fd = DRFunctionsCore::gv($data,'property_desc','');

		if ($fd === "") {
			$data['property_desc'] = strtolower(DRFunctionsCore::punctClean($data['property']));
		}
		return $data;
	}
		

	/**
	 * Gets all of a schema
	 *
	 * @param string $schema
	 * @param boolean $showall
	 * @param boolean $returnID			Do we return an ID or a property name as the key field?
	 * @return array
	 * @acl 0
	 * @access public
	 * @static
	 */
	static function getSchema($schema="",$returnID=false) {
		static $schemaCache = null;
		if (!$returnID) {
			$keyField = "property";
		} else {
			$keyField = self::$PK;
		}
		if ($schemaCache === null) $schemaCache = array();
		$key = $schema."/".$keyField;
		if (isset($schemaCache[$key])) return $schemaCache[$key];
		$output = array();

		if (self::$useCache && !DetectRight::$flush) {
			$cacheKey = DetectRight::cacheKey("DRSchema_".DetectRight::$username."_".$schema."_".$keyField);
			$output = self::$cacheLink->cache_get($cacheKey);
			if (is_array($output)) return $output;
		}
		
		$where = "";
		if ($schema) {
			$where = array("schema"=>$schema);
		}
		$result = self::$dbLink->simpleFetch(self::$table,array("*"),$where);
		if (!$result) $result = array();
		foreach ($result as $row) {
			$sp = self::tempSchemaProperty($row,false);
			$output[strtolower($sp->$keyField)] = $sp;
		}

		if (self::$useCache && is_array($output)) {
			self::$cacheLink->cache_set($cacheKey,$output,600);
		}
		$schemaCache[$key] = $output;
		return $output;
	}
				
	/**
	 * Initialize SP
	 *
	 * @param integer $trust
	 * @param integer $verification
	 * @param string $source
	 * @static 
	 * @internal
	 * @access public
	 */
	static function init($trust,$verification,$source) {
		if (is_null(self::$schemaTranslate)) self::$schemaTranslate=array();
		self::$trust = $trust;
		self::$verification = $verification;
		self::$source = $source;	
	}
		

	
	static function getSchemaProperty($property,$schema) {
		$cacheKey = DetectRight::cacheKey("DRSP_$schema"."_".$property);
		if (isset(self::$cacheArray[$cacheKey])) return self::$cacheArray[$cacheKey];
		$sp = new SchemaPropertyCore(0,$schema,$property);
		self::$cacheArray[$cacheKey] = $sp;
		return $sp;
	}

	static function getObjectValue(QuantumDataTree &$qdt,SchemaPropertyCore &$nsp,&$importances) {
		$flat = DetectRight::$flat;
		if (!is_object($nsp)) return null;
		if ($nsp->error_trap > 0) return null;
		if (!is_object($qdt)) return null;

        if ($qdt->treeCount() === 0 && $qdt->qdcCount() === 0 && DRFunctionsCore::isEmptyStr($nsp->default_value)) return null;
        
        if ($importances === null)
        {
        	$importances = array();
        }
		
		// if there"s no output mapping, then nothing can happen.
		// But is that actually true?
		//if (DRFunctionsCore::isEmptyStr($nsp->output_map) && !$flat) return null;

		// now we know we can do something :)
		//if (!array_key_exists($display_name,$output)) $output[$display_name]=array();
		/*if ($nsp->property==="resolution_width") {
			$dummy=true;
		}*/
		$tempAugment=false;
		$augmentQuery = false;
		$output_map = $nsp->output_map;
		if ($output_map) {
			if (DRFunctionsCore::in("\n",$output_map)) {
				$output_map_array = explode("\n",$output_map);
			} elseif (DRFunctionsCore::in("^+",$output_map)) {
				$output_map_array = explode("^+",$output_map);
				$augmentQuery = true;
			} elseif (DRFunctionsCore::in("^",$output_map)) {
				$output_map_array = explode("^",$output_map);
			} else {
				$output_map_array = array($output_map);
			}
		} else {
			$output_map_array = array();
		}
		
		if ($flat) {
			// the output path should be the schema/fieldname to look in.
			// this means we're working with flat trees. The rest of the program
			// doesn't have to know we're doing this :)
			$path = "$nsp->schema:$nsp->property";
			switch ($nsp->type) {
				// here's where we build a query depending on the data type of the nsp
				// sc->
				case 'Literal':
					array_unshift($output_map_array,$path."//description");
					break;
				case 'LiteralArray':
					array_unshift($output_map_array,$path."//description");
					break;
				case 'Dimension':
					array_unshift($output_map_array,$path."//has=dimension{value:*;units:none}");
					break;
				case 'Boolean':
					array_unshift($output_map_array,$path."//status");
					break;
				case 'Integer':
					array_unshift($output_map_array,$path."//has=integer{value:*;units:none;flag:1}");
					break;
				case 'Float':
					array_unshift($output_map_array,$path."//has=float{value:*;units:none;flag:1}");
					break;
			}
			$tempAugment = true; // just in case there is other stuff... (e.g. from Accept Strings)
		}
		
		$valueArray = array();
		foreach ($output_map_array as $output_map) {
			$qdts = array();
			if (strpos($output_map,"*//") !== false && strpos($output_map,":*//") === false) {
				// wildcard QDT query
				// here we need to build a list of the QDTs we're interrogating, then put
				// those QDTs into the QDT array.
				$tmp = explode("*//",$output_map);
				$rootPath = array_shift($tmp);
				$node = $qdt->getQDT($rootPath);
				if (is_null($node)) {

				}
				// now we've got where to start from.
				$remainder = array_shift($tmp); // What have we got left?
				$tmp = explode("//",$remainder); // we need to get the descriptor
				$descriptor = array_shift($tmp); // got the descriptor
				if (is_null($descriptor)) {
					// disaster?
				}
				if (is_object($node) && !DRFunctionsCore::isEmptyStr($descriptor)) {
					$paths = $node->getQDTsWithDescriptor($descriptor); // all nodes with this descriptor
					$remainder = implode("//",$tmp); // new output map

					foreach ($paths as $newQDTPath) {
						// In Java, getQDTsWithDescriptor returns QDTs, not paths to QDTs.
						// in PHP, the path returned will be relative to the node's root.
						$newQDTPath = str_replace($rootPath,"",$newQDTPath);
						$newQDT = $node->getQDT($newQDTPath);
						if (!is_null($newQDT)) {
							$dataQuery = new DataQuery($remainder);
							$dataQuery->array = ($nsp->type === "LiteralArray");
							$dataQuery->useSubtree = $nsp->use_subtree; // use subtree is now questionable in my mind
							$dataQuery->queryQDTWithPath($newQDT); // this is a mixed array, where objects are objectified scalars in Java
							$valueArray = array_merge($valueArray,$dataQuery->result);
						} else {

						}
					}
				}
			} else {
				$dataQuery = new DataQuery($output_map);
				$dataQuery->array = ($nsp->type === "LiteralArray");
				$dataQuery->useSubtree = $nsp->use_subtree;

				if (!is_null($qdt)) {
					$dataQuery->queryQDTWithPath($qdt); // this is a mixed array, where objects are objectified scalars in Java
					if (is_array($dataQuery->result)) {
						if (!is_null($dataQuery->result)) {
							$valueArray = array_merge($valueArray,$dataQuery->result);
						}
					}
				} else {

				}
			}
			
			// if tempAugment is true, then we've artificially created a priority map,
			// and should now check the real one!			
			if ($tempAugment) {
				$tempAugment = false;
				continue;
			}
			// if we've got a result and this isn't an augment query, then don't go any further.
			if (!DRFunctionsCore::isEmpty($valueArray) && !$augmentQuery) break;
		}

		if (count($valueArray) === 0) {
			if (DRFunctionsCore::isEmptyStr($nsp->default_value)) return null;
			$valueArray[] = $nsp->default_value;
		}
		$valueArray = array_unique($valueArray);
		$valueHash = array();
		$value = $valueArray[0]; // end value
		if (DRFunctionsCore::isEmptyStr($value)) {
			if (DRFunctionsCore::isEmptyStr($nsp->default_value)) return null;
			$value = $nsp->default_value;
			$valueArray[0] = $value;
		} else {
			$valueArray2 = array();
			while (count($valueArray) > 0) {
				$valueStr = array_shift($valueArray);
				// check for %/%, split, and assign final bit to importance array for use later.
				$tmpImportance = 50;
				$tmpSplit = explode("%/%",$valueStr);
				if (count($tmpSplit) > 1) {
					$tmpImportance = (int)$tmpSplit[1];
					$valueArray2[] = $tmpSplit[0];
				} else {
					array_unshift($valueArray,$tmpSplit[0]);
					break;
				}

				// this makes sure that we only choose the spiciest meatball
				$tmpSplitKey = $tmpSplit[0];
				if (!isset($valueHash[$tmpSplitKey])) {
					$valueHash[$tmpSplitKey] = $tmpImportance;
				} else {
					$oldImportance = $valueHash[$tmpSplitKey];
					if ($tmpImportance > $oldImportance) $valueHash[$tmpSplitKey] = $tmpImportance;
				}
			}
			if (count($valueArray2)) $valueArray = $valueArray2;
		}
		$validated = false;

		// choose the right validator from the record: the normal one, or the output one.
		$vt = "none";
		$vtExp = true;
		if ($nsp->validation_type_export) {
			$vt = $nsp->validation_type_export;
			$vtExp = false;
		} else {
			$vt = $nsp->validation_type;
		}

		$prefFound = false;
		switch ($nsp->type) {
			case 'LiteralArray':
				if ($nsp->validate_on_export) {
					for ($i = 0; $i < count($valueArray); $i++) {
						$tmpValue = trim(Validator::validate($vt,$valueArray[$i],$vtExp));
						if (!DRFunctionsCore::isEmptyStr($tmpValue)) $valueArray[$i] = $tmpValue;
					}
					$validated = true;
				}
				$value = array_unique($valueArray);
				$delimiter = $nsp->delimiter;
				if (!DRFunctionsCore::isEmptyStr($delimiter)) {
					$value = trim(implode($delimiter,$value));
				} 
				break;
			default:
				if (!empty($valueArray)) {
					// some way of collapsing this down.
					// Note: this is done differently in the Java and C#, in that the sort is always
					// done numerically if the data is up to it, otherwise string. Here, we choose the way it's sorted based on
					// variable type.
					// Careful!
					if ($nsp->preferred_order === "max") {
						if ($nsp->type === "Integer" || $nsp->type === "Float" || $nsp->type === "ByteSize") {
							DRFunctionsCore::rnsort($valueArray);	
						} else {
							DRFunctionsCore::rssort($valueArray);
						}
						$value = array_shift($valueArray);
						$valueHash = array();
						$prefFound = true;
					} elseif ($nsp->preferred_order === "min") {
						if ($nsp->type === "Integer" || $nsp->type === "Float" || $nsp->type === "ByteSize") {
							DRFunctionsCore::nsort($valueArray);	
						} else {
							DRFunctionsCore::ssort($valueArray);
						}
						$value = array_shift($valueArray);
						$valueHash = array();
						$prefFound = true;
					} elseif (!DRFunctionsCore::isEmptyStr($nsp->preferred_order)) {
						$preferred_order = explode(",",$nsp->preferred_order);
						if ($flat) {
							foreach ($preferred_order as $order) {
								$order = Validator::validate($vt,$order,$vtExp);
								if (in_array($order,$valueArray)) {
									$value = $order;
									$importances[$value] = DRFunctionsCore::gv($valueHash,$value,1);
									$valueHash = array();
									$prefFound = true;
									$validated = true;
									break;
								}
							}
						}
						
						if (!$prefFound) {
							foreach ($preferred_order as $order) {
								if (in_array($order,$valueArray)) {
									$value = $order;
									$importances[$value] = $valueHash[$value];
									$valueHash = array();
									$prefFound = true;
									break;
								}
							}
						}

						/*if (!$prefFound) {
							foreach ($preferred_order as $order) {
								foreach ($valueArray as $tmpValue) {
									if (stripos($order,$tmpValue) === 0) {
										$value = $order;
										$valueHash = array();
										break(2);
									}
								}
							}
						}*/
					}
					// QDC queries are pre-sorted in terms of importance,
					// so they don't need to be re-sorted here. The importances are in there
					// so as to return what we need up the chain.
					if (!$prefFound) {
						if (!empty($valueHash) && $dataQuery->queryType !== "qdc") {
							arsort($valueHash);
							$vhKeys = array_keys($valueHash);
							$topImportance = $valueHash[$vhKeys[0]];
							$topValues = array();
							foreach ($vhKeys as $vhKey) {
								if ($valueHash[$vhKey] === $topImportance) $topValues[] = $vhKey;
							}
							
							if (isset($topValues[1])) {
								if ($nsp->type === "Boolean" || $nsp->type === "Integer" || $nsp->type === "Float" || $nsp->type === "ByteSize") {
									DRFunctionsCore::rnsort($topValues); // this will prioritise big values over small ones, and 1 over zero
								} else {
									DRFunctionsCore::rssort($topValues);
								}
								$value = array_shift($topValues);
							} else {
								$value = $topValues[0];
							}
						} else {
							if (is_array($valueArray) && count($valueArray)) {
								$value = array_shift($valueArray);
							}
						}
					}
				}
		}

		switch ($nsp->type) {
			case 'String':
			case 'Version':
			case 'Dimension':
				$value = (string)$value;
				break;
			case 'Integer':
			case 'ByteSize':
				$value = Validator::validate("integer",$value);
				break;
			case 'Float':
				$value = (float)$value;
				break;
			case 'DateTime':
				break;
			case 'Boolean':
				$value = Validator::validate("boolean",$value);
				if (!DetectRight::$booleansAsString) {
					if ($value === 0) {
						$value = false;
					} elseif ($value === 1) {
						$value = true;
					}

				} else {
					if (!empty($nsp->true_values) && $value === 1) {
						$value = $nsp->true_values[0];
					} elseif (!empty($nsp->false_values) && $value === 0) {
						$value = $nsp->false_values[0];
					} 
				}
				$validated = true;
				break;
		}

		if (!DRFunctionsCore::isEmpty($value) && !$validated && $nsp->validate_on_export) {
			if (!is_array($value)) {
				$value = Validator::validate($vt,$value,$vtExp);
			}
		}
		
		if ($importances !== null) {
			foreach ($valueHash as $key=>$importance) {
				$importances[$key] = $importance;
			}
		}
		return $value;
	}
		
	/**
	 * Export
	 *
	 * @param PropertyCollection $pc
	 * @param string $toSchema
	 * @param boolean $includeNulls		If true, you get lots of potentially empty fields
	 * @param boolean $bestValues		Only activate if you ask for "Universal schema" export
	 * @param boolean $flatten			As above
	 * @return array
	 * @internal 
	 * @access public
	 * @static
	 */
	static function export(QuantumDataTree $qdt,$destSchema) {
		//DetectRight::$flush = true;
		$audit=array();
		$qdt->processPackages();
		/* @var $pc PropertyCollection */
		DetectRight::checkPoint("Importing schemas");
		$output=array();
		if (!is_array($destSchema)) {
			$schemas = explode(",",$destSchema);
		} else {
			$schemas = $destSchema;
		}
		
		//$flat = (DetectRight::$dbLink->getOption("eptype") === "flat");
		$rootQDT = $qdt;
		$array = array();
		foreach ($schemas as $toSchema) {
			$translate=self::getSchema($toSchema);
			if (!is_array($translate)) continue;
			DetectRight::checkPoint("Translating");

			foreach ($translate as $nsp) {
				// if errorTrap = 1, then this is a field that isn't "official" in the schema: i.e., it's misspelled or something.
				// we need the data in it, since that's likely to be valid, but we don't want the field appearing in output.
				if ($nsp->error_trap > 0) continue;
				if ($nsp->property === "urischemetel") {
					$dummy=true;
				}
				// if there's no output mapping, then nothing can happen.
				if (DRFunctionsCore::isEmptyStr($nsp->output_map)) continue;

				if (self::$strictExportNames) {
					$display_name = $nsp->display_name;
					if (DRFunctionsCore::isEmptyStr($display_name)) $display_name = $nsp->property;
				} else {
					$display_name = $nsp->property;
				}

				$value = self::getObjectValue($qdt,$nsp,$array);

				if (!DRFunctionsCore::isEmptyStr($value)) {
					$output[$display_name] = $value;
				}
			}

			unset($translate);
		}

		//$output = self::format($output,$toSchema);
		return $output;
	}
	
	static function format($output,$schema) {
		// no code here. That's for the non-core version
	}
	
	static function tempSchemaProperty($data,$commit=false) {
		$class = __CLASS__;
		$nsp = new $class(0,"","");

		$data = self::process($data);

		foreach($data as $key=>$value) {
			if (property_exists($nsp,$key)) {
				$nsp->$key=$value;
			}
		}
	
		if (!$nsp->ID && $commit) {
			// we need to commit this product. It's Noo!!
			if (DetectRight::canLearn()) {
				if (!$nsp->commit()) return null;
				self::$dbLink->recordInsert($nsp->tablename,$nsp->ID);
			} else {
				trigger_error('New schema item from unqualified person: '.DetectRight::$username." ".$data['property']." ".$data['schema'],E_WARNING);
				return null;
			}
		}
		return $nsp;
	}	
}