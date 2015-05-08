<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    dbengine.mysql.class.php
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
2.8.0 - many changes in switch to Mysqli
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRMySQL");
}

Class DRMySQL extends SQLLink {
	static $mysql_data_type_hash = array(
			MYSQLI_TYPE_BIT=>'bit',
			MYSQLI_TYPE_BLOB=>'blob',
			MYSQLI_TYPE_CHAR=>'char',
			MYSQLI_TYPE_DATE=>'date',
			MYSQLI_TYPE_DATETIME=>'datetime',
			MYSQLI_TYPE_DECIMAL=>'decimal',
			MYSQLI_TYPE_DOUBLE=>'double',
			MYSQLI_TYPE_ENUM=>'enum',
			MYSQLI_TYPE_FLOAT=>'float',
			MYSQLI_TYPE_GEOMETRY=>'geometry',
			MYSQLI_TYPE_INT24=>"int24",
			MYSQLI_TYPE_INTERVAL=>"interval",
			MYSQLI_TYPE_LONG=>"bigint",
			MYSQLI_TYPE_LONG_BLOB=>"blob",
			MYSQLI_TYPE_MEDIUM_BLOB=>"blob",
			MYSQLI_TYPE_NEWDATE=>"newdate",
			MYSQLI_TYPE_NEWDECIMAL=>"newdecimal",
			MYSQLI_TYPE_NULL=>"null",
			MYSQLI_TYPE_SET=>"set",
			MYSQLI_TYPE_SHORT=>"int",
			MYSQLI_TYPE_STRING=>"string",
			MYSQLI_TYPE_TIME=>'time',
			MYSQLI_TYPE_TIMESTAMP=>"timestamp",
			MYSQLI_TYPE_TINY=>"int",
			MYSQLI_TYPE_TINY_BLOB=>"blob",
			MYSQLI_TYPE_VAR_STRING=>"string",
			MYSQLI_TYPE_YEAR=>"year"			
	);
	
	const IDD = '`';
	static $exportTypeMap = array(
			"BIGINT"=>"BIGINT(M)",
			"BIT"=>"BIT(1)",
			"BIT1"=>"BIT(1)",
			"BLOB"=>"BLOB",
			"CHAR"=>"CHAR(M)",
			"CLOB"=>"LONGTEXT",
			"DATE"=>"DATE",
			"DECIMAL"=>"DECIMAL(M)",
			"DOUBLE"=>"DOUBLE(M)",
			"FLOAT"=>"FLOAT(M)",
			"INTEGER"=>"INT(M)",
			"LONGVARBINARY"=>"VARBINARY(M)",
			"LONGVARCHAR"=>"MEDIUMTEXT",
			"NUMERIC"=>"DOUBLE",
			"REAL"=>"DOUBLE",
			"SMALLINT"=>"SMALLINT(M)",
			"SQLXML3"=>"LONGTEXT",
			"TIME"=>"TIME",
			"TIMESTAMP"=>"TIMESTAMP(M)",
			"VARBINARY"=>"VARBINARY",
			"VARCHAR"=> "VARCHAR(M)"
	);
			
	static $importTypeMap = array(
		"BIGINT"=>"BIGINT",
		"INT"=>"INTEGER",
		"LONG"=>"LONG",
		"STRING"=>"VARCHAR",
		"TEXT"=>"MEDIUMTEXT",
		"TIMESTAMP"=>"TIMESTAMP",
		"BLOB"=>"BLOB"
	);
	
	function __construct($hash) {
		$this->engine = "MySQL";
		parent::__construct($hash);
	}
	
	/**
	 * Ping, and return
	 *
	 * @return boolean
	 * @internal 
	 * @access public
	 */
		function _ping() {
			return mysqli_ping($this->db);
		}

		/**
	 * Which wildcard character is used to represent 
	 * multiple characters?
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _multichar_wildcard() {
			return "%";
		}

	/**
	 * Which wildcard character is used to represent 
	 * a single character?
	 * 
	 * @internal 
	 * @access public
	 * @return string
	 * 
	 */	
		function _singlechar_wildcard() {
			return "_";
		}

		/**
	 * Which delimiter is put round fields and tables?
	 *
	 * @internal 
	 * @access public
	 * @return string
	 */
		function _identifier_delimiter() {
			return MySQL::IDD;
		}

		/**
	 * Which delimiter is put round values?
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _quoted_delimiter() {
			return "'";
		}

		/**
	 * Connect the damn out of an engine!
	 *
	 * @return resource || false
	 * @internal 
	 * @access public
	 */
		function connect($params) {
			$this->params = $params;
			$host = DRFunctionsCore::gv($params,'address');
			$port = DRFunctionsCore::gv($params,'port',"3306");
			$database = DRFunctionsCore::gv($params,'bucket');
			$user = DRFunctionsCore::gv($params,'username');
			$password = DRFunctionsCore::gv($params,'password');
			
			$retval = @mysqli_connect( $host.":$port", $user, $password, $database);
			if (!is_object($retval)) {
				$this->throwError("Cannot connect to MySQL: ".mysqli_error($retval));
				return;
			} else {
				$this->db = $retval;
				$this->currentDB = $database;
			}
			$this->dbOK = true;
		}

		function _close() {
			if (is_object($this->db)) @mysqli_close($this->db);
		}
		/**
	 * Selecting a database
	 *
	 * @param string $database
	 * @return boolean
	 * @internal 
	 * @access public
	 */
		function _selectDatabase($database) {
			$retval = @mysqli_select_db( $this->db,$database);
			return $retval;
		}

	/**
	 * Fetch Array
	 *
	 * @param resource $result
	 * @return array || false
	 * @internal 
	 * @access public
	 */
		function _fetch_array($result) {
			return mysqli_fetch_array($result);
		}

		/**
	 * Free up a database result
	 *
	 * @param resource $result
	 * @return boolean
	 * @internal 
	 * @access public
	 */
		function _free_result(&$result) {
			$success=mysqli_free_result($result);
			unset($result);
			return $success;
		}

	/**
	 * Escape a string for transmission to the database
	 *
	 * @param string $string
	 * @return string
	 * @internal 
	 * @access public
	 */
		function escape_string($string) {
			return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $string); 
			/*if (!is_resource($this->db)) {
				if (is_resource(DetectRight::$dbLink)) {
					return mysql_real_escape_string($string,DetectRight::$dbLink->db);
				} else {
					return $string; // unescaped, but it's not going anywhere.
				}
			}
			return mysql_real_escape_string($string,$this->db);*/
		}

		function _escape_string($string) {
			return $this->escape_string($string);
		}
		/**
	 * Return the number of rows in a given result
	 *
	 * @param resource $result
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
		function _num_rows($result) {
			return mysqli_num_rows($result);
		}

		/**
	 * Return the last insert id on this connection.
	 *
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
		function _insert_id() {
			return mysqli_insert_id($this->db);
		}

		/**
	 * how many records were affected by the last action?
	 *
	 * @return integer || false
	 * @internal 
	 * @access public
	 */
		function _affected_rows() {
			return mysqli_affected_rows($this->db);
		}

		/**
	 * Return the last SQL Error
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _sql_error() {
			$error=mysqli_error($this->db);
			return $error;
		}

		/**
	 * Run a query. D'oh!
	 *
	 * @param string $sql
	 * @return resource
	 * @internal 
	 * @access public
	 */
		function _query($sql) {
			// make sure we get rid of any delimiters left in
			if (DRFunctionsCore::in("ignore delayed",$sql)) {
				$sql = str_replace("ignore delayed","delayed ignore",$sql);
			}
			$query=mysqli_query($this->db,$sql);
			return $query;
		}

		/**
	 * Fetch an associative array
	 *
	 * @param resource $result
	 * @return associative_array
	 * @internal 
	 * @access public
	 */
		function _fetch_assoc($result) {
			return mysqli_fetch_assoc($result);
		}

		/**
	 * Return a timestamp suitable for this engine
	 *
	 * @param timestamp $ts
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _ts2Date($ts) {
			return date("Y-m-d H:i:s",$ts);
		}

		/**
	 * Return all of the rows in a resultset in an associative
	 * array keyed by $keyField
	 *
	 * @param resource $result
	 * @param string $keyField
	 * @return array
	 * @internal 
	 * @access public
	 */
		function _fetch_all($result,$keyField="") {
			$return=array();
			while ($row = mysqli_fetch_assoc($result)) {
				if ($keyField && isset($row[$keyField])) {
					$return[$row[$keyField]]=$row;
				} else {
					$return[]=$row;
				}
			}
			return $return;
		}

		/**
	 * Create a limit string to limit the resultset
	 *
	 * @param array $array
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _limit_string($array) {
			$limit = DRFunctionsCore::gv($array,'limit');
			$offset = DRFunctionsCore::gv($array,'offset',"");
			if (!is_numeric($limit)) {
				$this->error = "Non-numeric Limit";
				return "";
			}
			if ($offset && !is_numeric($offset)) {
				$this->error="Non-numeric offset";
				return "";
			}
			$return="limit $limit";
			if ($offset) $return .= " OFFSET $offset";
			return $return;
		}

		/**
	 * Returns the string of the moment!
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _now() {
			return "NOW()";
		}

		/**
	 * Does this engine support "delayed"?
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _delayed_string() {
			return "delayed";
		}

		/**
	 * Get list of tables
	 *
	 * @return array
	 * @internal 
	 * @access public
	 */
		function _getTableList() {
			// extend this
			$result = $this->_query("show tables from `$this->currentDB`");
			if (!$result) return false;
			while ($row = $this->_fetch_array($result)) {
				$output[]=array_shift($row);
			}
			$this->free_result($result);
			return $output;
		}

		/**
	 * Bulk insert string
	 *
	 * @param string $table
	 * @param string $fields
	 * @param string $values
	 * @return boolean
	 * @internal 
	 * @access public
	 */
		function _bulk_insert($table,$fields,$values) {
			$success=true;
			$chunks = array_chunk($values,10000,true);
			foreach ($chunks as $values) {
				$valueStrings = implode(",",$values);
				$sql = "insert ignore into $table ($fields) VALUES $valueStrings";
				$success = $this->_query($sql) && $success;
			}
			return $success;
		}

		/**
	 * Literal string representing null values
	 *
	 * @return string
	 * @internal 
	 * @access public
	 */
		function _null() {
			return "null";
		}
		
		function _getFields($table) {
			$table = $this->idd($table);
			$query = "show fields from $table";
			$result = $this->fillArrayFromSQL($query,"Field");
			$fields = array_keys($result);
			return $fields;
		}
		
		// @return ArrayList<String>
		function getDDL($tablename,$pk, $index, $fields) {
			if (!is_array($pk)) return array();
			if (!is_array($index)) return array();
			if (!is_array($fields)) return array();
			$boolValidator = new boolean_validator();
			$output = array();

			$keys = array_keys($fields);

			foreach ($keys as $key) {
				$lhm = $fields[$key];
				if (!is_array($lhm)) continue;
				$colName = $lhm["colName"];
				$dataType = $lhm["dataType"];
				if (DRFunctionsCore::isEmptyStr($dataType)) continue;
				if (!isset(DBLink::$typeMap[$dataType])) continue;
				$dataName = DBLink::$typeMap[$dataType];
				if (!isset(self::$exportTypeMap[$dataName])) continue;
				$dataName = self::$exportTypeMap[$dataName];
				$colSize = DRFunctionsCore::gv($lhm,'colSize',0);
				if (is_numeric($colSize) && $colSize > 0 && DRFunctionsCore::in("(M",$dataName)) {
					$dataName = str_replace("(M)","(".$colSize.")",$dataName);
				} else if ($colSize < 1 && DRFunctionsCore::in("(M)",$dataName)) {
					$dataName = substr($dataName,0,strpos($dataName,"(M)"));
				}
				
				$takesNulls = true;
				$takesNullsObject = DRFunctionsCore::gv($lhm,"takesNulls");
				if ($takesNullsObject != null) {
					$takesNulls = $boolValidator->process($takesNullsObject);
				}
				$command = array();
				$command[] = "`" . $colName . "`";
				$command[] = $dataName;

				if ($dataName === "INT(10)" || $dataName === "INT(12)") {
					$command[] = "UNSIGNED";
				}

				if ($takesNulls) {
					$command[] = "NULL";
				} else {
					$command[] = "NOT NULL";
				}

				$output[] = implode(" ",$command);
			}

			// done fields, now do PK
			ksort($pk);

			$pkString = "";
			$pkName = "";
			if (count($pk) > 0) {
				$pkString = "PRIMARY KEY (";
				

				$keyList = array();
				foreach ($pk as $lhm) {
					if (!is_array($lhm)) continue;
					$colName = $lhm['colName'];
					if (DRFunctionsCore::isEmptyStr($pkName)) $pkName = $lhm['pkName'];
					$keyList[] = "`" . $colName . "`";
				}
				$output[] = $pkString . implode(",",$keyList) . ")";
			}

			if (count($index) > 0) {
				$indexNames = array_keys($index);

				foreach ($indexNames as $indexName) {
					$establishedUniqueness = false;
					if ($indexName == $pkName) continue;
					$indexString = "KEY ";
					$indexBits = array();
					$lhm = $index[$indexName];
					if (!is_array($lhm)) continue;
					if (count($lhm) == 0) continue;
					ksort($lhm);

					foreach ($lhm as $ihm) {
						if (!is_array($ihm)) continue;
						/*indexColumn.put("colName",colName);
						indexColumn.put("nonUnique",nonUnique);
						indexColumn.put("ascOrDesc",ascOrDesc);*/
						$colName = DRFunctionsCore::gv($ihm,"colName","");
						if (DRFunctionsCore::isEmptyStr($colName)) continue;
						if (!$establishedUniqueness) {
							$nonUnique = $boolValidator->process(DRFunctionsCore::gv(ihm,"nonUnique","0"));
							if (!$nonUnique) $indexString = "UNIQUE ".$indexString;
						}
						$indexBits[] = "`" . $colName . "`";
					}
					$output[] = $indexString . " `" . $indexName . "` (" . implode(",",$indexBits) . ")";
				}
			}
			$innerBrackets = implode(", ",$output);
			$resultStr = "CREATE TABLE `" . $tablename . "` (" . $innerBrackets . ") ENGINE MYISAM DEFAULT CHARSET=LATIN1";
			$resultArr= array();
			$resultArr[] = $resultStr;
			return $resultArr;
		}
		
		function getTableMap($tableName) {
			$table = array();
	
			$fields = $this->_fetch_fields($tableName);
			foreach ($fields as $meta) {
				if (!$meta) {
					continue;
				}
				/*blob:         $meta->blob
				max_length:   $meta->max_length
				multiple_key: $meta->multiple_key
				name:         $meta->name
				not_null:     $meta->not_null
				numeric:      $meta->numeric
				primary_key:  $meta->primary_key
				table:        $meta->table
				type:         $meta->type
				unique_key:   $meta->unique_key
				unsigned:     $meta->unsigned
				zerofill:     $meta->zerofill */
				$colName = $meta->name;
				$dataType = DRFunctionsCore::gv(self::$mysql_data_type_hash,$meta->type);
				if (stripos($dataType,"blob")) {
					$meta->blob = 1;
				} else {
					$meta->blob = 0;
				}
				$colSize = $meta->length;
				if ($dataType === "int" && $colSize > 2) $dataType="bigint";
				if ($dataType === "string" && $meta->blob === 1) $dataType = "text";
				//if ($meta->unsigned === 1) $dataType = "unsigned $dataType";
				// transformation here into general type? This would have been a JDBC type, but here it's just a regular MySQL Type
				if ($dataType !== false) {
					$dataType = strtoupper($dataType);
					$dataType = DRFunctionsCore::gv(self::$importTypeMap,$dataType);
					if (!$dataType) {
						continue;
					}
					$noNulls = $meta->not_null;
					if (!is_null($noNulls)) {
						$takesNulls = !$noNulls;
					} else {
						$takesNulls = true;
					}


					$field["colName"] = $colName;
					$field["dataType"] = strtoupper($dataType);
					$field["takesNulls"] = $takesNulls;
					$field["colSize"] = $colSize;
					$field["auto"] = $meta->auto_increment;
					$field['unsigned'] = $meta->unsigned;
					$table[$colName] = $field;
				}

			}
			return $table;
		}
		
		function getTableIndices($tableName,$getIndex="") {
			$indexCollection = array();

			$result = mysqli_query($this->db,"SHOW INDEX FROM $tableName");
			if ($result === false) return array();
			if (mysqli_num_rows($result) === 0) return array();
			while ($index = mysqli_fetch_assoc($result)) {
				$indexColumn = array();
				$indexName = $index['Key_name'];
				if (!DRFunctionsCore::isEmpty($indexName)) {
					if (!$getIndex || $indexName == $getIndex) {
						if (!isset($indexCollection[$indexName])) {
							$indexCollection[$indexName] = array();
						}
						$nonUnique = $index['Non_unique'];
						$ordinalPosition = $index['Seq_in_index'];
						$colName = $index['Column_name'];
						$ascOrDesc = $index['Collation'];
						$indexColumn = array();
						$indexColumn['colName'] = $colName;
						$indexColumn['nonUnique'] = $nonUnique;
						$indexColumn['ascOrDesc'] = $ascOrDesc;
						$indexCollection[$indexName][$ordinalPosition] = $indexColumn;
					}
				}
			}
			return $indexCollection;
		}

		function getPrimaryKey($tableName) {
			$pkName = "PRIMARY";
			$primaryKey = array();

			$result = mysqli_query($this->db,"SHOW INDEX FROM $tableName");
			if ($result === false) return array();
			if (mysqli_num_rows($result) === 0) return array();
			while ($index = mysqli_fetch_assoc($result)) {
				$indexName = $index['Key_name'];
				if ($indexName !== "PRIMARY") continue;

				$pkColumn = array();
				$colName = $index['Column_name'];
				$ordinal = $index['Seq_in_index'];

				$pkColumn["pkName"] = $pkName;
				$pkColumn["colName"] = $colName;
				$primaryKey[$ordinal] = $pkColumn;
			}
			return $primaryKey;
		}

		function _result($rs, $i, $field) {
			$result = mysqli_data_seek($rs,$i);	
			$row = mysqli_fetch_assoc($rs);
			$return = DRFunctionsCore::gv($row,$field,"");
			return $return;
		}
		
		function _defAutoIncrement($field) {
			return (($field->flags & MYSQLI_AUTO_INCREMENT_FLAG) == $field->flags) ? 1 : 0;
		}
		
		function _defNotNull($field) {
			return (($field->flags & MYSQLI_NOT_NULL_FLAG) == $field->flags) ? 1 : 0;
		}

		function _defPrimaryKey($field) {
			return (($field->flags & MYSQLI_PRI_KEY_FLAG) == $field->flags) ? 1 : 0;
		}
		
		function _defUniqueKey($field) {
			return (($field->flags & MYSQLI_UNIQUE_KEY_FLAG) == $field->flags) ? 1 : 0;
		}
		
		// Note: DR detection doesn't use this, but updates do.
		function _fetch_fields($table) {
			// LIMIT 1 means to only read rows before row 1 (0-indexed)
			$result = mysqli_query($this->db,"SELECT * FROM $table LIMIT 1");
			$describe = mysqli_query($this->db,"SHOW COLUMNS FROM $table");
			$num = mysqli_num_fields($result);
			$output = array();
			for ($i = 0; $i < $num; ++$i) {
				$field = mysqli_fetch_field_direct($result, $i);
				// new extra stuff, no longer "extra"
				$flags = $field->flags;
				// Create the column_definition
				$infoRS = mysqli_data_seek($describe,$i);	
				$info = mysqli_fetch_assoc($describe);
				
				$field->extra = DRFunctionsCore::gv($info,'Extra');
				$field->definition = $info['Type'];
				$field->unsigned = (strpos($field->definition,"unsigned") !== false) ? 1 : 0;
				$field->def = $info['Default'];
				$field->not_null = ($info['Null'] === "NO") ? 1 : 0;
				$field->auto_increment = (strpos($field->extra,"auto_increment") !== false) ? 1 : 0;
				$key = $info['Key'];
				if ($key === "PRI") {
					$field->primary_key = 1;
				} else {
					$field->primary_key = 0;
				}
				
				if ($key === "UNI") {
					$field->unique_key = 1;
				} else {
					$field->unique_key = 0;
				}
				
				if ($field->not_null && !$field->primary_key) $field->definition .= ' NOT NULL';
				if ($field->def) $field->definition .= " DEFAULT '" . mysqli_real_escape_string($this->db,$field->def) . "'";
				if ($field->auto_increment) $field->definition .= ' AUTO_INCREMENT';
				if ($field->primary_key) $field->definition .= ' PRIMARY KEY';
				if ($field->unique_key) $field->definition .= ' UNIQUE KEY';

				// Create the field length
				//$field->len = mysqli_field_len($result, $i);
				// Store the field into the output
				$output[$field->name] = $field;
			}
			return $output;
		}
	}
	
	if (!class_exists("MySQL",false)) {
		DetectRight::registerClass("MySQL");
		Class MySQL extends DRMySQL {
	
		}
	}
	