<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    dblink.class.php
Version: 2.8.0
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
2.7 - throw error changed to throw actual exceptions
2.8 - alterations to make some checking case-sensitive for speed reasons. They were only case-insensitive to compensate for the possibility of programmer error
but it was an expensive thing to do.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DBLink");
}

/**
 * Class which exists to hold a link to a database engine somewhere
 * in the world
 *
 * @internal
 */
Class DBLink {

	static $cacheLink; // this would be a database cache
	private $cache;
	
	static $dbs = array();
	static $connections = array();
	static $RECORD_INSERT=0;
	static $useQueryCache=false; // global
	public $useCache = false;
	
	/**
	 * What engine is this? MySQL? SQLite?
	 *
	 * @var string
	 * @access public
	 * @internal
	 */
	public $engine;
		
	/**
	 * The actual connection to whatever it is
	 *
	 * @var resource
	 * @access protected
	 * @internal
	 */
	public $db;

	public $queryLog=array(); // log of queries this time, for diagnostic reasons.
	
	public $dbOK=false;
	public $currentDB="";
	public $params;
	public $logQueries=false;
	/**
	 * Error from link
	 *
	 * @var string
	 * @access protected
	 * @internal
	 */
	public $error;
		
	/**
	 * Total query time over this link
	 *
	 * @var integer
	 * @access protected
	 * @internal
	 */
	protected $qt=0;
	
	public $lastSQL;
	
	public function __construct($hash) {
		$this->hash = $hash;
		if (is_null(self::$cacheLink)) self::$cacheLink = DetectRight::$cacheLink;
		$this->cache = self::$cacheLink;
		$this->useCache = self::$useQueryCache;
		$this->logQueries = DetectRight::$logQueries;
	}
	
	public function __destruct() {
		$this->close();	
	}
		
	public function throwError($error,$fatal=false) {
		if ($fatal) {
			$this->dbOK = false;
			trigger_error($error,E_USER_WARNING);
			throw new DetectRightException($error);
		} else {
			trigger_error($error,E_USER_NOTICE);
		}
	}
	
	/**
	 * Takes an existing incoming DBLink and spreads it around.
	 *
	 * @param DBLink $dbl
	 */
	static function initWith($dbl) {
		$done = array();
		if (!is_object($dbl) || !$dbl->dbOK) throw new ConnectionLostException("Invalid dbl passed to initWith");
		DetectRight::$dbLink = $dbl;
		foreach (DetectRight::$classes as $class) {
			if ($class === __CLASS__) continue;
			if (in_array($class,$done)) continue;
			if (property_exists($class,"dbLink")) {
				try {
					$refClass = new ReflectionClass($class);
					$refClass->setStaticPropertyValue ( "dbLink" , $dbl );
				} catch (Exception $e) {
					// this doesn't matter all that much
				}
			}
		}
	}
	
	/**
	 * Spreads dbLink objects around the farm
	 *
	 */
	static function init($copyToMemory = false) {
		$done = array();
		foreach (self::$connections as $class=>$connection) {
			$hash = $connection;
			if (isset(self::$dbs[$hash])) {
				$dbLink = self::$dbs[$connection];
			} else {
				$dbLink = self::getConnection($connection,$copyToMemory);
			}
			$refClass = new ReflectionClass($class);
			try {
				$refClass->setStaticPropertyValue ( "dbLink" , $dbLink );
			} catch (Exception $e) {
				throw new DetectRightException("Error during database init $connection");
			}
			$done[] = $class;
		}
		
		if (is_object(DetectRight::$dbLink)) {
			$dbLink = DetectRight::$dbLink;
			foreach (DetectRight::$classes as $class) {
				if ($class === __CLASS__) continue;
				if (in_array($class,$done)) continue;
				if (property_exists($class,"dbLink")) {
					try {
						$refClass = new ReflectionClass($class);
						$refClass->setStaticPropertyValue ( "dbLink" , $dbLink );
					} catch (Exception $e) {
						// this doesn't matter all that much
					}
				}
			}
		}
	}
	
	/**
	 * Get a DBLink object
	 *
	 * @param string $connection
	 * @return DBLink compatible object
	 */
	static function getConnection($connection,$copyToMemory = false) {
		// connection is formatted so:
		//Engine//Address//Port//Username//Password//Bucket
		$hash = $connection;
		$dummyDBLink = new DBLink($hash);
		$connection = explode("//",$connection);
		if (count($connection) < 2) {
			throw new ConnectionLostException("Database string isn't valid");
		}
		$params['engine'] = array_shift($connection);
		$params['address'] = array_shift($connection);
		if (count($connection) > 0) {
			$params['port'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['username'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['password'] = array_shift($connection);
		}
		if (count($connection) > 0) {
			$params['bucket'] = array_shift($connection); // this is like a table in a database
		}
		
		$useQueryCache = null;
		if (count($connection) > 0) {
			$useQueryCache = Validator::validate("boolean",array_shift($connection));
		}
		
		if (!class_exists($params['engine'],true)) {
			throw new ConnectionLostException("DB Engine doesn't exist");
		}
		
		$engine = $params['engine'];
		$dbLink = new $engine($hash);
		$dbLink->connect($params);
		if (!$dbLink->dbOK) {
			throw new ConnectionLostException("Database is not OK");
		}

		if ($useQueryCache !== null) {
			$dbLink->useCache = $useQueryCache;
		}
		
		if ($copyToMemory) {
			$oldUseCache = $dbLink->useCache;
			$dbLink->useCache = false;
			$newDBLink = $dbLink->getMemDB();
			$dbLink->close();
			$dbLink = $newDBLink;
			$dbLink->useCache = $oldUseCache;
		}

		$pointer = &$dbLink;
		return $pointer;
	}

	/**
	 * Reset the error status of this object
	 *
	 * @access public
	 * @internal 
	 */
	function reset() {
		$this->error = "";
	}
	
	/**
	 * Clear the logs table
	 *
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function clearLogs() {
		$success=$this->deleteAllData("logs");
		return $success;
	}
	
	/**
	 * Write an entry to the log table
	 *
	 * @param string $entry
	 * @return string
	 * @internal 
	 * @access public
	 */
	function writeLogEntry($entry) {
		$array = array("Comment"=>$entry,"Datetime"=>"{NOW}");
		$success=$this->insertData("logs",$array,0);
		if (!$success) echo $this->sql_error();
		return $success;
	}
		
	/**
	 * Get the current query time: the amount of time spent on queries in this object.
	 *
	 * @return integer
	 * @internal 
	 * @access public
	 */
	function queryTime() {
		return $this->qt;
	}
	
	/**
	 * Get Hash List
	 *
	 * @param string $table  Target Table
	 * @param string $hashname
	 * @return string
	 * @internal 
	 * @access public
	 */
	function getHashList($table,$hashname) {
		return $this->getIDs($table,$hashname);
	}

	function arrayFilter($name,$value) {
		$array = self::getArray($name);
		if (!is_array($array)) {
			return $value;
		}
		foreach ($array as $key=>$part) {
			if (substr($key,0,1) === "%") {
				// substring replacement
				$key = substr($key,1);
				if (stripos($value,$key) !== false) {
					$value = str_ireplace($key,$part,$value);
				}
			} else {
				if (strtolower($key) === strtolower($value)) {
					$value = $part;
					break;
				}
			}
		}
		return $value;
	}
	/**
	 * There are various lookup arrays in the "lookups" table. This gets said arrays.
	 *
	 * @param string $name
	 * @return associative array
	 */
	function getArray($name) {
		static $arrays = array();
		//if (empty($arrays)) $arrays = array();
		if (array_key_exists($name,$arrays)) return $arrays[$name];
		$cacheKey = DetectRight::cacheKey($name);
		if ($this->useCache) {
			$array = $this->cache->cache_get($cacheKey);
			if ($array !== null && is_array($array)) {
				$arrays[$name] = $array;
				return $array;
			}
		}
		$array = array();
		$maps = $this->simpleFetch("lookups",array("*"),array("mapname"=>$name));
		if (!is_array($maps) || count($maps) === 0) {
			$arrays[$name] = null;
			return null;
		}
		$mapRow = array_shift($maps);
		if ($mapRow === null) {
			$arrays[$name] = null;
			return null;
		}
		$map = $mapRow['map'];
		try {
			$exact = (int)$mapRow['exact'];
		} catch (Exception $e) {
			$exact = 1;
		}
		
		$mapArray = explode("\n",$map);
		
		foreach ($mapArray as $mapPart) {	
			if ($exact === 0) $mapPart = "%".$mapPart;
			$tmp = explode("=>",$mapPart);
			if (count($tmp) > 1) {
				$array[$tmp[0]] = trim($tmp[1]);
			} else {
				$array[] = trim($mapPart);
			}
		}
		if ($this->useCache) {
			self::$cacheLink->cache_set($cacheKey,$array,600);
		}
		$arrays[$name] = $array;
		return $array;
	}
	
	/**
	 * Check the status of the link
	 *
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function checkStatus() {
		if (!$this->db) {
			$this->status=self::UNKNOWN;
		} elseif (!$this->_ping($this->db)) {
			$this->status=self::DOWN;
		} else {
			$this->status=self::LIVE;
		}
		return ($this->status == self::LIVE);
	}

	/**
	 * Gets a whole damn table!
	 *
	 * @param string $table  Target Tablename
	 * @param array $orderBy Array of order clauses
	 * @return array
	 * @internal 
	 * @access public
	 */
	function getTable($tablename,$orderBy="") {
		$tablename = $this->idd($tablename);
		if ($orderBy) {
			$orderBy = $this->idd($orderBy);
			$orderBy=" order by $orderBy";
		}

		$sql="select * from $tablename$orderBy";
		return $this->getSQL($sql);
	}


	/**
	 * Get an array of IDs from the database
	 *
	 * @param string || array $table  Target Table
	 * @param string $field	 Field to return
	 * @param array $where  Array of where clauses
	 * @param array $orderBy Array of order clauses
	 * @param array $limit Array containing limit clause
	 * @param string $sqlOp   Keywords to use instead of "select"
	 * @return array
	 * @internal 
	 * @access public
	 */
	function getIDs($table,$field,$where="",$orderBy="",$limit="",$sqlOp="select") {
		$this->error = "";
		if (!$sqlOp) $sqlOp = "select";
		$orderString = "";
		$limitString = "";
		$table = $this->tableClauseFromArray($table);
		$fieldListString = $this->idd($field);
		$whereString = $this->whereClauseFromArray($where);
		if (is_array($orderBy)) $orderString = $this->orderByFromArray($orderBy);
		if (is_array($limit)) $limitString = $this->_limit_string($limit);
		if (!$this->error) {
			$sql = "$sqlOp $fieldListString from $table $whereString $orderString $limitString";
			return $this->getIDsFromSQL($sql,$field);
		}
		return false;
	}

	/**
	 * Gets a particular field from an SQL statement into an array.
	 * Particularly good for unique fields such as IDs and hashes.
	 *
	 * @param string $sql   SQL String to process
	 * @param string $keyname
	 * @return array
	 * @internal 
	 * @access public
	 */
	function getIDsFromSQL($sql,$keyname="ID") {
		$ids = array();
		$ckey = $sql."/".$keyname;
		$return = $this->getSQL($ckey);
		if ($return !== null) return $return;
		$result = $this->query($sql);
		if ($result === false || $result === null) return array();
		while ($row = $this->fetch_assoc($result)) {
			$ids[]=$row[$keyname];
		}
		
		$this->free_result($result);
		$this->saveSQL($ckey,$ids,14400);
		return $ids;
	}

	/**
	 * Set something in the options table in the DB
	 *
	 * @param string $optionKey
	 * @param string $optionValue
	 * @return string
	 * @internal 
	 * @access public
	 */
	function setOption($optionKey,$optionValue) {
		$success = $this->deleteData("options",array("optionKey"=>$optionKey));
		$success = $success && $this->insertData("options",array("optionKey"=>$optionKey,"optionValue"=>$optionValue));
		return $success;
	}

	/**
	 * Get something from the options table in the DB
	 *
	 * @param string $optionKey
	 * @return string
	 * @internal 
	 * @access public
	 */
	function getOption($optionKey) {
		$optionValue = $this->getIDs("options","optionValue",array("optionKey"=>$optionKey));
		if ($optionValue===false || !is_array($optionValue)) return false;
		if (count($optionValue) > 0) {
			return $optionValue[0];
		}
		return "";
	}

	/**
	 * Update an object
	 *
	 * @param Object $object
	 * @param string $flags Such as "ignore"
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function updateObject($object) {
		// this needs to go into dblink. All substantive code needs to go there.		
		if (!$object->fieldList) {
			DRFunctionsCore::dr_echo("No field list in ".serialize($object));
			exit;
		}
		$fieldArray=$object->fieldList;
		$tmp=array_flip($fieldArray);
		$pk=$object->pk;
		$pkid=$object->$pk;
		unset($tmp[$pk]);
		
		$fieldArray=array_flip($tmp);
		$vars=get_object_vars($object);
		$varKeys = array_keys($vars);
		foreach ($varKeys as $key) {
			if (array_search($key,$fieldArray) === false)  {
				unset($vars[$key]);
			}
		}

		if (isset($vars['ts'])) {
			$vars['ts']=$this->_ts2Date(time());
		}

		if (!$vars) {
			echo("Set Array failed for ".serialize($object));
			exit;
		}
		
		$where = array($pk=>$pkid);
		$table = $object->tablename;
		$result=$this->updateData($table,$vars,$where);
		return $result;
	}
	
	/**
	 * A bit similar to query except an array returned.
	 *
	 * @param string $sql   SQL String to process
	 * @return array
	 * @internal 
	 * @access public
	 */
	function saveSQL($sql,$result,$timeout = 600) {
		if (!$this->useCache) return;
		$key=$sql."/".$this->hash;
		self::$cacheLink->cache_set($key,$result,$timeout);
	}
	
	function getSQL($sql) {
		// quick and dirty SQL cacheing: yes, I know, MySql does this as well... this is unsuitable for fast moving queries: it's more for slow moving tables.		
		if (!$this->useCache) return null;
		$output = null;
		$diag = DetectRight::$DIAG;
		if ($diag) DetectRight::checkPoint("Asked for $sql",4);
		$key=$sql."/".$this->hash;
		
		if (self::$cacheLink->cache_ok() && !DetectRight::$flush) {
			$output=self::$cacheLink->cache_get($key);
		}
		if ($output === false) return null;
		return $output;
	}
	
	/**
	 * Do a bulk insert of data
	 *
	 * @param string $table  Target Table
	 * @param array $fields Array of Fields to return
	 * @param array $values
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function bulkInsert($table,$fields,$values) {
		$table = $this->idd($table);
		$valueStrings=array();
		if (!$values) return;
		foreach ($values as $array) {
			$array_keys = array_keys($array);
			$first_key = array_shift($array_keys);
			if ($first_key === 0) {
				$mode = "array";
			} else {
				$mode = "assoc";
			}
			break;
		}
		
		if ($mode == "assoc") {
			$fields = array_keys($values[0]);
			foreach ($values as $array) {
				$valueStrings[] = "(".$this->qd($array).")";
			}
		} else {
			foreach ($values as $array) {
				$valueStrings[] = "(".$this->qd($array).")";
			}
		}
		
		$fieldString = $this->idd($fields);
		$result = $this->_bulk_insert($table,$fieldString,$valueStrings);
		return $result;
	}
	
	/**
	 * Get an SQL table clause from a tablename or array describing a join
	 *
	 * @param string || array $table  Target Table
	 * @return string
	 * @internal 
	 * @access public
	 */
	function tableClauseFromArray($table) {
		if (is_string($table)) return $this->idd($table);		
		if (!is_array($table)) return "";
		$tables = DRFunctionsCore::gv($table,'tables',array());
		if (!$tables) $tables = DRFunctionsCore::gv($table,'table',array());
		$op = DRFunctionsCore::gv($table,'op',"=");
		$joinOp = DRFunctionsCore::gv($table,"joinop","inner join");
		$on = DRFunctionsCore::gv($table,'on',array());
		$onArray = array();
		$sql = "";
		$joinTables=array();
		foreach ($tables as $table) {
			if (is_string($table)) {
				$joinTables[]=$this->idd($table);
			} else {
				foreach ($table as $asTable=>$array) {
					$asTable = $this->idd($asTable);
					$joinTables[] = "( ".$this->tableClauseFromArray($array)." ) as $asTable";
				}
			}
		}
		$sql[] = implode(" $joinOp ",$joinTables);
		foreach ($on as $field1=>$field2) {
			$field1 = $this->idd($field1);
			$field2 = $this->idd($field2);
			$onArray[] = "$field1 $op $field2";
		}
		$sql[] = "on ".implode(" AND ",$onArray);
		return implode(" ",$sql);
	}
	
	/**
	 * Produce an SQL clause from a (possibly nested) array of where clauses
	 *
	 * @param array $where  Array of where clauses
	 * @param boolean $suppressWhere
	 * @return string
	 * @internal 
	 * @access public
	 */
	function whereClauseFromArray($where,$suppressWhere=false) {
		// danger, Will Robinson!!
		if (is_string($where)) return $this->fix_delimiters($where);
		// check for "in" clause.
		if (is_array($where) && count($where) > 0) {
			$whereClause=array();
			$joinOp = DRFunctionsCore::gv($where,"op","AND");
			unset($where['op']);
		
			foreach ($where as $key=>$value) {
				if (is_null($key)) {
					$this->error = "Error in where clause: null key";
					return false;
				}
				$op=" = ";
				if (is_numeric($key)) {
					if (is_array($value)) {
						$key = DRFunctionsCore::gv($value,'field');
					} else {
						continue; // discard this
					}
				}
				$key = $this->idd($key);
				if ($value === null) $value = "null";
				if ((array) $value === $value) {
					if (!isset($value["op"]) && !isset($value["value"])) {
						$this->error="Error in where clause $key";
						return false;
					}
					$op=$value['op'];
					$value = $value['value'];
				}
				
				switch ($op) {
					case 'in':
					case 'not in':
						if (!isset($value[1]) && isset($value[0])) {
							$value = array_shift($value);
						} elseif (!isset($value[0])) {
							if ($op == "in") {
								$key = "false";
							} else {
								$key = "true";
							}
							$op = "";
							$value = "";
						} else {
							$do = true;
							foreach ($value as $tmpValue) {
								if ((array) $tmpValue === $tmpValue) $do=false;
							}
							if (!$do) {
								$this->error="Malformed in clause ".serialize($where);
								return false;
							}
						}
						if (!DRFunctionsCore::isEmpty($value)) {
							$inItems = $this->qd($value);
							$value = "($inItems)";
						}
					break;
					case 'where':
						$key = "";
						$op = "";
						$value= "(".$this->whereClauseFromArray($value,true).")";
					break;
					case 'expression':
						$key = "";
						$op = "";
						$value = "(".$this->fix_delimiters($value).")";
					break;
					default:
						$valueUpper = strtoupper($value);
						if ($valueUpper !== "NOW()" && $valueUpper !== 'NULL') {
							$value = $this->qd($value);
						} 
				}
				$whereClause[]=trim("$key $op $value");
			}
			$whereString = implode(" $joinOp ",$whereClause);
			if (!$suppressWhere) $whereString = "where ".$whereString;
		} else {
			return "";
		}
		return $whereString;
	}

	/**
	 * Count of rows in a table with optional where
	 *
	 * @param string $table  Target Table
	 * @param string $field
	 * @param array $where  Array of where clauses
	 * @return string
	 * @internal 
	 * @access public
	 */
	function dcount($table,$field,$where) {	
		$field = $this->idd($field);
		$where = $this->whereClauseFromArray($where);
		$table = $this->tableClauseFromArray($table);
		$alias = $this->idd("cnt");
		$sqlString="select count($field) as $alias from $table $where";
		$result=$this->query($sqlString);
		if(!$result) return 0;
		$tmp=$this->fetch_assoc($result);
		$cnt=$tmp['cnt'];
		return (int)$cnt;
	}
	
	/**
	 * Access-style dlookup
	 *
	 * @param string $fieldToReturn
	 * @param string $table  Target Table
	 * @param array $where  Array of where clausesClause
	 * @return mixed
	 * @internal 
	 * @access public
	 */
	function dlookup($fieldToReturn,$table,$where) {
		$whereClause = $this->whereClauseFromArray($where);
		$table = $this->tableClauseFromArray($table);
		$field = $this->idd($fieldToReturn);
		$sqlStr="select $field from $table where $whereClause";
		$values=$this->query($sqlStr); // rows
		$numRows = $this->num_rows($values);
		if ($numRows==0) return false;
		if ($numRows==1) {
			$value=$this->fetch_assoc($values);
			return $value[$fieldToReturn];
		}
		// return an array. Rare, but happens.

		$result=array();
		while ($value=$this->fetch_assoc($values)) {
			array_push($result,$value[$fieldToReturn]);
		}
		return $result;
	}
	
	/**
	 * Parse a key/value array into an update "SET" clause.
	 *
	 * @param array $set
	 * @return string
	 * @internal 
	 * @access public
	 */
	function setClauseFromArray($set,$suppressSet=false) {
		$setArray=array();
		foreach ($set as $key=>$value) {
			if (is_array($value) || is_object($value)) {
				$value = serialize($value);
			}
			if ($key === "expression") {
				$setArray[] = $this->fix_delimiters($value);
			} else {
				$key = $this->idd($key);
				$value = $this->qd($value);
				$setArray[]="$key = $value";
			}
		}
		$return = "";
		if (!$suppressSet) $return = "set ";
		return $return.implode(",",$setArray);
	}

	/**
	 * Parse a key/value array into an order by clause
	 * It supports strings which pass through with delimiter checking, but this breaks
	 * the spirit of the abstraction layer a bit.
	 *
	 * @param array || string $orderBy
	 * @return string
	 * @internal 
	 * @access public
	 */
	function orderByFromArray($orderBy) {
		if (is_string($orderBy)) return $this->fix_delimiters($orderBy);
		if (!is_array($orderBy)) return "";
		$orderClause=array();
		foreach ($orderBy as $key=>$value) {
			if (is_numeric($key)) {
				$key = $value;
				$value = "ASC";
			}
			if ($key === "expression") {
				$orderClause[] = $value;	
			} else {
				$key = $this->idd($key);
				if ($value && strtoupper($value) !== "ASC" && strtoupper($value) !== "DESC") $value="";
				$orderClause[] = "$key $value";
			}
		}
		return "order by ".implode(",",$orderClause);
	}
	
	/**
	 * Commit an object to the ole' database
	 *
	 * @param Object $object
	 * @param boolean $delayed
	 * @return string
	 * @internal 
	 * @access public
	 */
	function commitObject($object,$delayed=false,$preserveIDs=true) {		
		$result=array();
		$data = array();
		if (!is_object($object)) {
			$this->error="Non-object Object passed to commitObject";
			return false;
		}
		$fieldArray=$object->fieldList;
		if (is_string($fieldArray)) {
			$fieldArray=explode(",",$object->fieldList);
		}
		if (!is_array($fieldArray)) {
			$this->error = "Fieldlist doesn't exist for object";
			return false;
		}
		foreach ($fieldArray as $property) {
			// change
			$use = DetectRight::get_object_var($object,$property);
			if (is_object($use) || is_array($use)) {
				$use=serialize($use);
			}
			// automagically timestamp this
			if ($property==="ts" || $use==="NOW()") $use = "{NOW}";
			$result[$property]=$use;
		}
		$data[]=$result;
		return $this->commitData($data,$object->tablename,$preserveIDs,false,$object->pk,$delayed);
	}
	
	/**
	 * Commit Data
	 *
	 * @param array $data
	 * @param string $table  Target Table
	 * @param boolean $preserveIDs
	 * @param boolean $ungz
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
	function commitData($data,$table,$preserveIDs=true,$ungz=false,$pkid="ID",$delayed=false) {
		if ($delayed) {
			$delayed = $this->_delayed_string();
		} else {
			$delayed = "";
		}
		$table = $this->idd($table);
		// first we have to look at the fields we're inserting. The easiest way is not to do a bulk insert.
		// I'd like to do a bulk insert, but I really can't guarantee that the data will be in the same order throughout
		// for each row of data, build an insert statement.
		if (!is_array($data)) return false;
		if (count($data)==0) return true;

		foreach ($data as $row) {
			// get the fields in this sql statement
			if (!is_array($row) && $ungz) $row=DRFunctionsCore::ungz($row);
			$fieldList=array_keys($row);
			if (!$preserveIDs) {
				$tmp=array_flip($fieldList);
				unset($tmp['ID']);
				$fieldList=array_flip($tmp);
			}
			$values=array();
			// insert the primary key at the beginning.
			//if (!array_key_exists(strtolower($field),array_change_key_case($fieldList,CASE_LOWER))) array_unshift($fieldList,$field);

			// get each value in the correct order // JAVA Fix/.NET Fix for zero floating point?
			foreach ($fieldList as $pkid=>$field) {
				$value=$row[$field];
				if ($value || $value===0 || $value === 0.00 || (is_string($value) && strlen($value)>0)) {
					$value=$this->qd($value);
					$values[]=$value;
				} else {
					unset($fieldList[$pkid]);
				}
			}

			$fieldList=$this->idd($fieldList);
			$sqlStr="insert $delayed into $table (".$fieldList.") VALUES (".implode(",",$values).")";
			$result=$this->query($sqlStr);
			if (!$result) {
				if (DetectRight::$allowEmail) DetectRight::cmail("chris@detectright.com","Insert",$sqlStr);
				return false;
			}
		}
		if ($delayed) {
			return true;
		} else {
			return $this->insert_id();
		}
	}

	public function tableExists($tablename) {
		$result = $this->query("SHOW CREATE TABLE `$tablename`",false);
		if ($result === false) return false;
		return true;
	}

	/**
	 * Updates data
	 *
	 * @param string $table  Target Tablename
	 * @param associative_array $data
	 * @param array $where  Array of where clauses
	 * @param string $sqlOp  Just in case of modifiers
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function updateData($table,$data,$where,$sqlOp="update") {
		$this->error = "";
		$table = $this->idd($table);
		$whereString = $this->whereClauseFromArray($where);
		$setString = $this->setClauseFromArray($data);
		
		if (!$setString) return false;
		if (!$whereString) return false;
		if ($this->error) return false;
		$sqlStr = "$sqlOp $table $setString $whereString";
		$success = $this->query($sqlStr);
		return $success;
	}
	
	function fieldListFromArray($fieldList) {
		if (is_string($fieldList)) return $fieldList;
		if (!is_array($fieldList)) die("Non-array fieldlist $fieldList");
		$noQuoteChars=array("*","(");
		foreach ($fieldList as $key=>$field) {
			$as="";
			if ($field."" === "Array") {
				$fieldName=$field['field'];
				$as = $field['as'];
			} else {
				$fieldName = $field;
			}
			$doField=true;
			$doAs=true;
			foreach ($noQuoteChars as $char) {
				if (strpos($fieldName,$char) !== false) $doField=false;
				if (strpos($as,$char) !== false) $doAs=false;
			}
			
			if ($doField) $field=$this->idd($field);
			if ($doAs && !DRFunctionsCore::isEmptyStr($as)) $as = $this->idd($as);
			if ($as) {
				$fieldList[$key] = "$field as $as";
			} else {
				$fieldList[$key] = $field;
			}
		}
		$fieldListString = implode($fieldList,",");
		return $fieldListString;
	}
	
	/**
	 * Simple fetch of select $fieldList from $tablename where implode($where)
	 *
	 * @param string $table
	 * @param array $fieldList
	 * @param associative_array $where
	 * @param associative_array $orderBy
	 * @param associative_array $limitClause
	 * @param string $keyField
	 * @param string $sqlOp   Modifier to SQL statement (e.g. "select distinct").
	 * @return array
	 * @internal 
	 * @access public
	 */
	function simpleFetch($table,$fieldList,$where="",$orderBy="",$limitClause="",$keyField="",$sqlOp="select") {
		if ($sqlOp == "") $sqlOp = "select";
		$this->error = "";
		$return=false;
		$whereString = "";
		$orderString = "";
		$limitString = "";
		$tableString = $this->tableClauseFromArray($table);
		$fieldListString = $this->fieldListFromArray($fieldList);
		if (!$fieldListString) $this->error = "Field List Error";
		$whereString = $this->whereClauseFromArray($where);
		if (is_array($orderBy)) $orderString = $this->orderByFromArray($orderBy);
		if (is_array($limitClause)) $limitString = $this->_limit_string($limitClause);
		if (!$this->error) {
			$sql = "$sqlOp $fieldListString from $tableString $whereString $orderString $limitString";
			//echo "$sql\n";
			$key = $sql."/".$keyField;
			$return = $this->getSQL($key);
			if ($return !== null) return $return;
			$return = $this->query($sql,false,$keyField);
			if ($return === false) return false;
			$this->saveSQL($key,$return,14400);
		} else {
			$return=false;
		}
		return $return;
	}

	function getFields($table) {
		return $this->_getFields($table);
	}
	
	function md5() {
		return md5(serialize($this->md5Summary()));
	}
	
	function summary() {
		$tables = $this->getTables();
		$data = array();
		foreach ($tables as $table) {
			$tableIDD = $this->idd($table);
			$fields = $this->getFields($table);
			$tsString = "";
			if (in_array("ts",$fields)) {
				$tsString = ", max({idd}ts{idd}) as {idd}maxts{idd}";
			}
			$query = "select count(*) as cnt, max(ID) as ID $tsString from $tableIDD";
			$result = $this->fillArrayFromSQL($query,'ID',false,false);
			$row = array_shift($result);
			$data[$table] = $row;
		}
		return $data;
	}
	
	function md5Summary() {
		$tables = $this->getTables();
		$data = array();
		foreach ($tables as $table) {
			$tableIDD = $this->idd($table);
			$fields = $this->getFields($table);
			if (in_array("ts",$fields)) {
				$query = "select count(*) as cnt, max(ID) as ID, max(ts) as maxts from $tableIDD";
				$result = $this->fillArrayFromSQL($query,'ID',false,false);
				$row = array_shift($result);
				$data[$table] = md5(serialize(array($row['cnt'],$row['maxts'],$row['ID'])));
			} else {
				$rs = new RecordSet($this,$table,array("*"));
				$hashes = array();
				while ($row = $rs->fetch()) {
					$hashes[] = md5(serialize($row));
				}
				$data[$table] = md5(serialize($hashes));

			}
		}
		return $data;
	}
	
	/**
	 * Insert some data
	 *
	 * @param string $table  Target Table
	 * @param array $array
	 * @param string $id
	 * @param string $delayed
	 * @param string $sqlOp   SQL String to process
	 * @return string
	 * @internal 
	 * @access public
	 */
	function insertData($table,$array,$id=0,$delayed=false,$sqlOp="insert") {	
		$this->error = "";
		if (!is_array($array)) return false;
		$table = $this->tableClauseFromArray($table);
		// all-purpose function that either updates a row (if ID is specified and exists)
		// or inserts one (if it doesn't). If $id is supplied and doesn't exist in the table, it will run an update query on no records. So there!
		// array is an assoc array with field->value combinations. Woo!

		$table=strtolower($table);
		//$result = $this->fillArrayFromSQL("show fields from $table",'Field');
		if ($delayed) $sqlOp = $sqlOp . " ".$this->_delayed_string();
		/*foreach ($array as $field=>$value) {
			if (!array_key_exists($field,$result)) unset($array[$field]);
		}*/
		//	validateValues(&$arrayToCommit,$table);
		$fields = $this->idd(array_keys($array));
		$values = array_values($array);
		foreach ($values as $key=>$value) {
			if (is_object($value) || is_array($value)) $values[$key] = serialize($value);
		}
		$values = $this->qd($values);

		if ($this->error) {
			return false;
		}
		if (empty($id)) {
			// do insert
			$sqlStr = "$sqlOp into $table ($fields) VALUES ($values)";
			$result=$this->query($sqlStr);
			if (!$result) {
				return false;
			}
			return $this->insert_id();
		} else {
			$result=$this->query("select ID from $table where ID=$id",false);
			if (!isset($result[0])) {
				$sqlStr = "$sqlOp into $table ($fields) VALUES ($values)";
				$result=$this->query($sqlStr);
				if (!$result) return false;
				return $this->insert_id();
			} else {
				//do update
				$clause=array();
				foreach ($array as $key => $value) {
					array_push($clause,"$key=$value");
				}
				$clauseStr = implode(",",$clause);
				$sqlStr = "update $table set $clauseStr where ID=$id";
				$result=$this->query($sqlStr);
				if (!$result) return false;
				return true;
			}
		}
	}

	/**
	 * The venerable array-filling function
	 *
	 * @param string $sql   SQL String to process
	 * @param string $keyColumn
	 * @param boolean $cleanKeys
	 * @param boolean $arrayOfArrays
	 * @return array || false
	 * @internal 
	 * @access public
	 */
	function fillArrayFromSQL($sql,$keyColumn='ID',$cleanKeys=false,$arrayOfArrays=false) {
		// if field is blank, just create an array of arrays
		$result=array();

		$keyColumn2="";
		if (strpos($keyColumn,",")) {
			$keyTmp=explode(",",$keyColumn);
			$keyColumn=$keyTmp[0];
			$keyColumn2=$keyTmp[1];
		}
		$rs = $this->query($sql,false);
		if ($rs === null || $rs === false) {
			return false;
		}

		foreach ($rs as $rownum=>$row) {
			if (isset($row[$keyColumn])) {
				if ($cleanKeys) {
					$key=strtolower(str_replace(" ","",$row[$keyColumn]));
				} else {
					$key=$row[$keyColumn];
				}
			} else {
				$key = $rownum;
			}
			//			$row=array_change_key_case($row,CASE_LOWER);
			if (isset($result[$key])) {
				// if we've specified a keyColumn that isn't unique, then we're going to end up with some rows being arrays.
				// this code turns the result into an array if this happens. It should be checked for in the calling code.
				if (!is_array($result[$key])) {
					$tmp=array();
					if ($keyColumn2) {
						$tmp[$row[$keyColumn2]]=$result[$key];
					} else {
						array_push($tmp,$result[$key]);
					}
					$result[$key]=$tmp;
				} else {
					if ($keyColumn2) {
						$result[$key][$row[$keyColumn2]]=$row;
					} else {
						array_push($result[$key],$row);
					}
				}

			} else {
				if (!$arrayOfArrays) {
					$result[$key] = $row; //put the whole array in there...
				} else {
					$tmp=array();
					if ($keyColumn2) {
						$tmp[$row[$keyColumn2]]=$row;
					} else {
						array_push($tmp,$row);
					}
					$result[$key]=$tmp;
				}
			}
		}
		//mysql_free_result($rs);
		return $result;
	}

	/**
	 * Puts an identifier delimiter round a string or array of strings.
	 * If array of strings, puts them together with commas.
	 * 
	 * @param string $identifier
	 * @return string
	 * @internal 
	 * @access public
	 */
	function idd($identifier) {
		// identifier_delimiter_getting
		if (!$identifier) return "";
		if ($identifier === "*") return $identifier;
		$identifier_delimiter = $this->_identifier_delimiter();
		if (!is_array($identifier)) {
			$identifier = $this->escape_string($identifier);
			if (strpos($identifier,".") !== false) {
				$identifier = str_replace(".",$identifier_delimiter.".".$identifier_delimiter,$identifier);
			}
			return $identifier_delimiter.$identifier.$identifier_delimiter;
		} 
		
		// JAVA FIX?
		if (isset($identifier[0]) && $identifier[0] === "*" && count($identifier)=== 1) return "*";

		foreach ($identifier as $key=>$value) {
			if (strpos($value,".") !== false) {
				$tmp1 = explode(".",$value);
				$tmp2 = array();
				$value = "";
				foreach ($tmp1 as $tmpValue) {
					$tmp2[] = $identifier_delimiter.$this->escape_string($tmpValue).$identifier_delimiter;
				}
				$value = implode(".",$tmp2);
			} else {
				$value = $identifier_delimiter.$this->escape_string($value).$identifier_delimiter;
			}
			$identifier[$key]=$value;
		}
		$identifier = implode(",",$identifier);
		return $identifier;
	}
	
	/**
	 * Puts an quoting delimiter round a string or array of strings.
	 * If array of strings, puts them together with commas.
	 *
	 * @param string || array $value
	 * @return string
	 * @internal 
	 * @access public
	 */
	function qd($value) {
		if (is_null($value)) return $this->_null();
		$quoted_delimiter = $this->_quoted_delimiter();
		
		// Getting the string thing out of the way first for speed reasons, since is_array is unaccountably slow.
		if (is_string($value)) {
			$do = $this->qd_doValue($value);
			if ($do) {
				$value = $quoted_delimiter.$this->escape_string($value).$quoted_delimiter;
			}
			return $value;
		}
		
		if (is_array($value)) {
			foreach ($value as $key=>$tmpValue) {
				$do = $this->qd_doValue($tmpValue);
				if ($do) {
					$tmpValue = $quoted_delimiter.$this->escape_string($tmpValue).$quoted_delimiter;
				}
				$value[$key]=$tmpValue;
			}
			$value = implode(",",$value);
		} elseif (is_numeric($value) || strlen(strval($value)) > 10) {
			$do = $this->qd_doValue($value);
			if ($do) {
				$value = $quoted_delimiter.$this->escape_string($value).$quoted_delimiter;
			}
		}
		return $value;
	}
	
	/**
	 * Search and replace on strings fed to qd, which also returns n string
	 * @internal 
	 * @access public
	 */
	function qd_doValue(&$value) {
		$mcw = $this->_multichar_wildcard();
		$scw = $this->_singlechar_wildcard();
		$now = $this->_now();

		$value = str_replace("{MCW}",$mcw,$value);
		$value = str_replace("{SCW}",$scw,$value);
		$do=true;
		if ($value==='{NOW}' || $value==="NOW()") {
			$value = str_replace("{NOW}",$now,$value);
			$value = str_replace("NOW()",$now,$value);
			$do=false;
		} elseif ($value === '\N') {
			$do=false;
			$value = "null";
		} elseif (strlen(strval($value)) > 31) {
			$do=true;
		} elseif ($value === "null") {
			$do=false;
		/*} elseif (is_numeric($value)) {
			$do=false;*/
		/*} elseif (strpos($value,"(") !== false) {
			$do=false;*/
		} else {
			$do=true;
		}
		return $do;
	}
	
	/**
	 * Delete series of data
	 *
	 * @param string $table  Target Table
	 * @param array $list
	 * @param string $field
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function deleteDataSeries($table,$list,$field) {
		// first check table exists
		$this->error = "";
		if (is_array($table)) return false;
		if (!is_array($list) || count($list)==0) return false;
		$where[$field] = array("op"=>"in","value"=>$list);
		if ($this->checkTable($table)) {
			$table = $this->idd($table);
			$sqlStr="delete from $table $where";
			$result=$this->query($sqlStr);
			return $result;
		} else {
			return false;
		}
	}

	/**
	 * Delete some data according to where clauses!
	 *
	 * @param string $table  Target Table
	 * @param array $where  Array of where clauses
	 * @return string
	 * @internal 
	 * @access public
	 */
	function deleteData($table,$where) {
		//	validateValues(&$arrayToCommit,$table);
		$this->error = "";
		$table = $this->idd($table);
		$whereString = $this->whereClauseFromArray($where);
		if ($whereString) {
			// I am NOT allowing full deletes. 
			$sqlStr = "delete from $table $whereString";
			return $this->query($sqlStr);
		} else {
				$this->error="No where clause supplied";
				return false;
		}
	}
	
	/**
	 * Kill all humans! Er, I mean "Delete All Data"!
	 *
	 * @param string $table  Target Table
	 * @return string
	 * @internal 
	 * @access public
	 */
	function deleteAllData($table) {
		$table = self::idd($table);
		$sqlStr = "truncate table $table";
		$success = $this->query($sqlStr);
		return $success;
	}
	
	/**
	 * Record an insert to a table
	 *
	 * @param string $table  Target Table
	 * @param string $hash
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function recordInsert($table,$id) {
		if (self::$RECORD_INSERT) {
			$array = array("table"=>$table,"added"=>$id);
			$this->insertData("new",$array);
		}
	}

	/**
	 * Replace data
	 *
	 * @param string $table  Target Tablename
	 * @param array $data
	 * @return boolean
	 * @internal 
	 * @access public
	 * @todo
	 */
	function replaceData($tablename,$data) {
		
	}
	
	/****************************************************************/
	/*                     Core functions                           */
	/****************************************************************/
	
	/**
	 * Connect to the datasource
	 *
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function connect($params) {
		return false;
	}
	
	/**
	 * Select Database to use
	 * Note: the flat thing doesn't happen in the Java, properties used instead.
	 * @param string $database
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function selectDatabase($database) {
		$retval = $this->_selectDatabase($database);
		if(!$retval) {
			if ($this->connect()) {
				$retval = $this->_selectDatabase($database);
			}
		}
		if ($retval) {
			$this->currentDB = $database;
			if ($this->getOption("eptype") === "flat") {
				DetectRight::$flat = true;
			} else {
				DetectRight::$flat = false;
			}
		}
		return $retval;
	}

	/**
	 * Fetch an array from a resource
	 *
	 * @param resource $result
	 * @return array || false
	 * @internal 
	 * @access public
	 */
	function fetch_array($result) {
		if (!is_resource($result) && !is_object($result)) {
			$this->error="Not a resource";
			return false;
		}
		return $this->_fetch_array($result);
	}

	/**
	 * Free Result Resource
	 *
	 * @param resource $result
	 * @return boolean
	 * @internal 
	 * @access public
	 */
	function free_result($result) {
		if (!is_resource($result) && !is_object($result)) {
			unset($result);
			return true;
		}
		return $this->_free_result($result);
	}

	/**
	 * Escape String
	 *
	 * @param string $string
	 * @return string
	 * @internal 
	 * @access public
	 */
	function escape_string($string) {
		// dummy function
		//return $this->_escape_string($string);
	}

	/**
	 * Number of rows in a resource
	 *
	 * @param resource $result
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
	function num_rows($result) {
		if (is_array($result)) return count($result);
		if (is_null($result) || $result === false) return false;
		return $this->_num_rows($result);
	}

	/**
	 * Get the last inserted ID
	 *
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
	function insert_id() {
		return $this->_insert_id();
	}

	/**
	 * Number of rows affected by last operation
	 *
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
	function affected_rows() {
		return $this->_affected_rows();
	}

	/**
	 * Get the last SQL Error from this link
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
	function sql_error() {
		$error=$this->_sql_error();
		if ($error) $this->error=$error;
		return $error;
	}

	function close() {
		$this->_close();
		unset(self::$dbs[$this->hash]);
	}

	function _close() {
		
	}
	
	/**
	 * Main query function
	 *
	 * @param string $sql   SQL String to process
	 * @param boolean $returnResource
	 * @return mixed
	 * @internal 
	 * @access public
	 */
	function query($sql,$returnResource=true,$keyField = "") {
		//global $DetectRight::checkPoints;
		$this->error = "";
		$this->lastSQL="";
		//if ($this->useCache) $result = $this->getSQL($sql);
		$logQueries = $this->logQueries;
		$dbLink = $this->db;
		if (!$dbLink) {
			if (!$this->connect()) return false;
		}

		if ($logQueries && DetectRight::$DIAG) {
			$this->queryLog[]=$sql;
			DetectRight::checkPoint($sql);
		}
		$start=mt();
		$sql = $this->fix_delimiters($sql);
		$query=$this->_query($sql);
		$this->lastSQL = $sql;
		if (!$query) {
			$this->error=$this->_sql_error($dbLink);
			if ($logQueries) $this->queryLog[]=$this->error;
		}
		if ($logQueries && DetectRight::$DIAG) {
			$timeTaken=mt()-$start;
			if (is_null($this->queryTime)) $this->queryTime=0;
			$this->queryTime=$this->queryTime+$timeTaken;
			$timeTaken=round($timeTaken,4);
			$log=$this->_affected_rows()." affected rows, ($timeTaken s)";
			DetectRight::checkPoint($log);
			$this->queryLog[]=$log;
		}
		if ($query===false) {
			$this->error=$this->_sql_error($dbLink);
			if ($logQueries) $this->queryLog[]=$this->error;
		}
		if (!$returnResource && (is_object($query) || is_resource($query))) {
			$query = $this->fetch_all($query,$keyField);
			$this->free_result($query);
		}
		return $query;
	}

	function fetchRecordset($table,$fieldList,$where="",$orderBy="",$limitClause="",$keyField="",$sqlOp="select",$idField="ID") {
		if (!$sqlOp) $sqlOp = "select";
		$this->error = "";
/*		$tableString = $this->tableClauseFromArray($table);
		$fieldListString = $this->fieldListFromArray($fieldList);
		$whereString = $this->whereClauseFromArray($where);
		if (is_array($orderBy)) $orderString = $this->orderByFromArray($orderBy);
		if (is_array($limitClause)) $limitString = $this->_limit_string($limitClause);*/
		if (!$this->error) {
			//$sql = "$sqlOp $fieldListString from $tableString $whereString $orderString $limitString";
			//echo "$sql\n";
			$result = new RecordSet($this,$table,$fieldList,$where,$orderBy,$limitClause,$keyField,$sqlOp,$idField);
			return $result;
		} else {
			return false;
		}

	}

	/**
	 * Return query log for this DB Link
	 *
	 * @return array
	 * @internal
	 * @access public
	 */
	function queryLog() {
		return $this->queryLog;
	}
	/**
	 * Fetch associative array
	 *
	 * @return associative_array
	 * @internal 
	 * @access public
	 */
	function fetch_assoc($result) {
		if (!is_resource($result) && !is_object($result)) {
			return false;
		}
		return $this->_fetch_assoc($result);
	}

	/**
	 * Search and replace for common internal delimiters
	 *
	 * @param string $string
	 * @return string
	 * @internal 
	 * @access public
	 */
	function fix_delimiters($string) {
		/**
		 * logic here: sometimes we need to fix sql strings
		 * directly passed from objects. 
		 * That's minimised here, but for particularly complex ones, 
		 * it might be necessary. 
		 * Here we just make sure that the correct delimiters are
		 * applied.
		 **/
		$string = str_replace("{idd}",$this->_identifier_delimiter(),$string);
		$string = str_replace("{dd}",$this->_quoted_delimiter(),$string);
		$string = str_replace("{qd}",$this->_quoted_delimiter(),$string);
		$string = str_replace("{MCW}",$this->_multichar_wildcard(),$string);
		$string = str_replace("{SCW}",$this->_singlechar_wildcard(),$string);
		$string = str_replace("{NOW}",$this->_now(),$string);
		return $string;
	}

	/**
	 * Fetch all of a recordset
	 *
	 * @param resource $result
	 * @param string $keyField
	 * @return array || associative_array
	 * @internal 
	 * @access public
	 */
	function fetch_all($result,$keyField="") {
		// this needs some serious testing :)
		if (!is_resource($result) && !is_object($result)) {
			return false;
		}
		if ((is_string($keyField) && strlen($keyField) > 0) || (is_array($keyField) && count($keyField)==1)) {
			if (is_array($keyField)) $keyField = array_shift($keyField);
			$rawRows = $this->_fetch_all($result,$keyField);
			return $rawRows;
		} else {
			$rawRows = $this->_fetch_all($result);
		}

		if (!$keyField) return $rawRows;
		if (!is_array($keyField)) {
			$this->error="Invalid Keyfield ".serialize($keyField);
			return false;
		}

		// multidimensional keyfield.
		$arrayOfArrays = DRFunctionsCore::gv($keyField,"arrayofarrays",false);
		if (isset($keyField["arrayofarrays"])) unset($keyField['arrayofarrays']);
		$output=array();

		foreach ($rawRows as $row) {
			$keys=array();
			foreach ($keyField as $field) {
				$keys[]=$row[$field];
			}
			$currentNode=&$output;
			foreach ($keys as $key) {
				if (!isset($currentNode[$key])) $currentNode[$key]=array();
				$currentNode = &$currentNode[$key];
			}
			if ($arrayOfArrays) {
				$currentNode[] = $row;
			} else {
				$currentNode = $row;
			}
			$currentNode=&$output;
		}
		return $output;
	}

	/**
	 * Check a table for existence
	 *
	 * @param string $table  Target Table
	 * @return string
	 * @internal 
	 * @access public
	 */
	function checkTable($tablename) {
		$tables = $this->_getTableList();
		if (in_array($tablename,$tables) !== false) return true;
		return false;
	}
	
	function getMaxPK($table,$pk) {
		$sql = "select max({idd}$pk{idd}) as maxid from {idd}$table{idd}";
		$result = $this->query($sql,false);
		if (!$result) {
			return 0;
		}
		$row = array_shift($result);
		if (!isset($row['maxid'])) return 0;
		return $row['maxid'];
	}
	
	function getTables() {
		return $this->_getTableList();
	}
	
	function getMemDB() {
		return null;
	}
	/**
	 * Timestamp to db engine specific date format.
	 *
	 * @param timestamp $ts
	 * @return string
	 * @internal 
	 * @access public
	 */
	function ts2Date($ts) {
		return $this->_ts2Date($ts);
	}
	
	function getDDL($tablename,$pk, $index, $fields) {
		return array();
	}
	
	function getTableMap($tableName) {
		return array();
	}
	
	function getTableIndices($tableName,$getIndex="") {
		return array();
	}
	
	function getPrimaryKey($tableName) {
		
	}
	
	function _getTableList() {}
	function _ts2Date($ts) {}
	function _ping() {}
	function _connect() {}
	function _selectDatabase($database) {}
	function _limit_string($array) {}
	function _fetch_array($result) {}
	function _free_result(&$result) {}
	function _escape_string($string) {}
	function _num_rows($result) {}
	function _insert_id() {}
	function _affected_rows() {}
	function _sql_error() {}
	function _query($sql) {}
	function _fetch_assoc($result) {}
	function _fetch_all($result) {}
	function _identifier_delimiter() {}
	function _quoted_delimiter() {}
	function _multichar_wildcard() {}
	function _singlechar_wildcard() {}
	function _bulk_insert($table,$fields,$values) {}
	function _delayed_string() {}
	function _now() {}
	function _null() {}
	function _getFields($table) {}
}
