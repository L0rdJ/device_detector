<?php
/**
 * @author Chris Abbott, DetectRight Ltd.
 * @package DetectRight
 */
/******************************************************************************
Name:    dbengine.sqlite2.class.php
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
2.2.1 - further error checks on source database file.
2.5.0 - altered to inherit from SQLLink
2.7.0 - removed SQLite2 code.
2.8.0 - moved to purely PDO based code.
**********************************************************************************/

if (class_exists("DetectRight")) {
	DetectRight::registerClass("DRSQLite");
}

/**
 * SQLite engine
 *
 */
Class DRSQLite extends SQLLink {

	const IDD = '"';
	static $exportTypeMap = array(
	"BIGINT"=>"INTEGER",
	"BIT"=>"NUMERIC",
	"BIT1"=>"NUMERIC",
	"BLOB"=>"NONE",
	"CHAR"=>"TEXT",
	"CLOB"=>"TEXT",
	"DATE"=>"NUMERIC",
	"DECIMAL"=>"NUMERIC",
	"DOUBLE"=>"REAL",
	"FLOAT"=>"REAL",
	"INTEGER"=>"INTEGER",
	"LONGVARBINARY"=>"NONE",
	"LONGVARCHAR"=>"TEXT",
	"NUMERIC"=>"NUMERIC",
	"REAL"=>"REAL",
	"SMALLINT"=>"INTEGER",
	"SQLXML3"=>"TEXT",
	"TIME"=>"NUMERIC",
	"TIMESTAMP"=>"STRING",
	"VARBINARY"=>"NONE",
	"VARCHAR"=>"TEXT"
	);

	/**
	 * This type map is for when we've got SQLite columns and we want to map them to internal JDBC compatible datatypes.
	 *
	 * @var associative_array
	 */
	static $importTypeMap = array(
	"INTEGER"=>"BIGINT",
	"NUMERIC"=>"BIGINT",
	"REAL"=>"DOUBLE",
	"NUMERIC"=>"NUMERIC",
	"TEXT"=>"VARCHAR",
	"NONE"=>"NONE"
	);

	/**
	 * Special for handling SQLite erors.
	 *
	 * @var string
	 * @access private
	 * @internal
	 */
	private $SQLiteError;
	private $affectedRows;
	private $all = null;
	public function __construct($hash) {
		parent::__construct($hash);
		$this->engine = "SQLite";
	}

	/**
	 * Connecting here is just checking whether we can
	 * (it's assumed the hard disk is working)
	 * Selecting a database does the dirty work here.
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	public function connect($params) {
		// these checks are only done if the sqlite environment isn't "confirmed".
		if (!DetectRight::$sqliteEnvironmentConfirmed) {
			if (!class_exists("PDO")) {
				throw new ConnectionLostException("PDO extension not installed");
			}
			$drivers = PDO::getAvailableDrivers();
			if (!is_array($drivers)) throw new ConnectionLostException("No PDO drivers installed, or PHP PDO failure");
			if (!in_array("sqlite",$drivers)) throw new ConnectionLostException("PDO SQLite is not installed on your machine. (Linux might need package php5-sqlite)");
		}
		$this->params = $params;
		$fn = DRFunctionsCore::gv($params,'address');
		if ($fn !== ":memory:" && !DetectRight::$expressMode) {
			if (!file_exists($fn)) {
				$this->throwError("Database file doesn't exist",true);
				return false;
			}
			if (filesize($fn) === 0) {
				$this->throwError("Database exists, but is empty",true);
				return false;
			}
			if (filesize($fn) < 10000) {
				$this->throwError("Database exists, but is unfeasibly small",true);
				return false;
			}
		}
		try {
			// postpone connecting until needed.
			$this->db = $fn;
			if ($fn === ":memory:" || !DetectRight::$expressMode) {
				$this->connectToSQLite();
			}
			$this->dbOK = true;
		} catch (Exception $e) {
			$this->throwError($e->getMessage(),true);
			return false;
		}
		return true;
	}

	private function connectToSQLite() {
		$db = new PDO('sqlite:'.$this->db);
		$this->db = $db;
	}
	/**
	 * Connecting here is just checking whether we can
	 * (it's assumed the hard disk is working)
	 *
	 * @return boolean
	 * @access public
	 * @internal
	 */
	public function newDatabase($params) {
		if (!class_exists("PDO")) {
			$this->throwError("PDO extension not installed",true);
			return false;
		}
		$drivers = PDO::getAvailableDrivers();
		if (!is_array($drivers)) return false;
		if (!in_array("sqlite",$drivers)) return false;
		$this->params = $params;
		$fn = DRFunctionsCore::gv($params,'address');
		if (file_exists($fn)) {
			$this->throwError("File exists",true);
			return false;
		}

		try {
			$db = new PDO('sqlite:'.$fn);
			$this->db = $db;
			$this->dbOK = true;
		} catch (Exception $e) {
			$this->throwError($e->getMessage(),true);
			return false;
		}
		return true;
	}

	function _close() {
		$this->db = null;
	}

	/**
	 * Selecting a database
	 *
	 * @param string $database
	 * @return boolean
	 * @access public
	 * @internal
	 */
	function _selectDatabase($database) {
		// technically, this does nothing within this file. We could alter SQLite's behaviour to qualify all table names,
		// but that's complicated.
		return true;
	}
	/**
	 * Fetch an array which has both associative and non-associative
	 * elements
	 *
	 * @param resource $result
	 * @return array || false
	 * @access public
	 * @internal
	 */
	function _fetch_array($result) {
		return $result->fetch(PDO::FETCH_BOTH);
	}

	/**
	 * Escape this string for the engine
	 *
	 * @param string $string
	 * @return string
	 * @access public
	 * @internal
	 */
	function escape_string($string) {
		$string = str_replace("'","''",$string);
		return $string;
	}
/*
		if (!is_object($this->db)) {
			$this->connectToSQLite();
		}

		$return = $this->db->quote($string);
		//if (strpos($return,"'") === false) return $return;
		// escape string doesn't expect single quotes.
		if ($return{0} === "'") {
			$return = substr($return,1,-1);
		}
		/*if (substr($return,-1,1) === "'") {
			$return = substr($return,0,-1);
		}
		return $return;
	}*/

	function _escape_string($string) {
		return $this->escape_string($string);
	}

	/**
	 * Number of rows in a result set
	 *
	 * @param resource $result
	 * @return integer
	 * @access public
	 * @internal
	 */
	function _num_rows($result) {
		// this is UGLY, but PDO can't do number of rows properly without issuing a count statement.
		$this->all = $result->fetchAll(PDO::FETCH_ASSOC);
		$cnt = count($this->all);
		return $cnt;
	}

	/**
	 * Return insert ID
	 *
	 * @return integer
	 * @access public
	 * @internal
	 */
	function _insert_id() {
		if (!is_object($this->db)) {
			$this->connectToSQLite();
		}
		return $this->db->lastInsertId();
	}

	/**
	 * Number of rows affected by last query
	 *
	 * @return integer
	 * @access public
	 * @internal
	 */
	function _affected_rows() {
		return $this->affectedRows;
	}

	/**
	 * Return last SQL error
	 *
	 * @param resource $dbLink
	 * @return string
	 * @access public
	 * @internal
	 */
	function _sql_error() {
		if ($this->SQLiteError) {
			$return = $this->SQLiteError;
		} else {
			if (!is_object($this->db)) {
				$this->connectToSQLite();
			}
			$return = $this->db->errorInfo();
			if (isset($return[2])) {
				$return = $this->SQLiteError = $return[2];
			}
		}
		$this->SQLiteError="";
		return $return;
	}

	/**
	 * Do a query! REALLY!!!
	 *
	 * @param sql $sql
	 * @return resource || boolean
	 * @access public
	 * @internal
	 */
	function _query($sql) {
		if (!is_object($this->db)) {
			$this->connectToSQLite();
			if (!is_object($this->db)) return false;
		}
		$this->SQLiteError="";
		if (substr(strtolower($sql),0,6)=="select" || substr(strtolower($sql),0,6) == "pragma") {
			$query=@$this->db->query($sql);
			return $query;
		} else {
			try {
				$rows = $this->db->exec($sql);
				$this->affectedRows = $rows;
			} catch (Exception $e) {
				$this->SQLiteError = $this->_sql_error();
				return false;
			}
			return true;
		}
	}

	/**
	 * Fetch as associative array
	 *
	 * @param resource $result
	 * @return associative_array
	 * @access public
	 * @internal
	 */
	function _fetch_assoc($result) {
		return $result->fetch(PDO::FETCH_ASSOC);
	}

	/**
	 * Return all the rows from a recordset
	 *
	 * @param resource $result
	 * @param string $keyField
	 * @return array || false
	 * @access public
	 * @internal
	 */
	function _fetch_all($result,$keyField="") {
		if ($this->all) {
			$rows = $this->all;
			$this->all = null;
			return $rows;
		}
		$rows = $result->fetchAll(PDO::FETCH_ASSOC);
		if ($rows === false) return false;
		if ($keyField && $rows) {
			$output = array();
			$testRow = $rows[0];
			if (isset($testRow[$keyField])) {
				foreach ($rows as $row) {
					$output[$row[$keyField]] = $row;
				}
			}
			$rows = $output;
		}
		return $rows;
	}

	function getTableMap($tableName) {
		$table = array();
		if (!is_object($this->db)) {
			$this->connectToSQLite();
		}
		$result = $this->db->query("PRAGMA table_info(" . $tableName . ")");
		$result->setFetchMode(PDO::FETCH_ASSOC);
		$columns = array();
		foreach ($result as $row) {
			$columns[$row['name']] = $row['type'];
		}
		foreach ($columns as $colName=>$dataType) {
			$dataType = DRFunctionsCore::gv(self::$importTypeMap,$dataType,$dataType);
			$field["colName"] = $colName;
			$field["dataType"] = $dataType;
			$table[$colName] = $field;
		}
		return $table;
	}

	function _getFields($table) {
		$table = $this->idd($table);
		$query = "pragma table_info($table)";
		$result = $this->fillArrayFromSQL($query,"name");
		$fields = array_keys($result);
		return $fields;
	}

	function getMemDB() {
		/**
	 * Copies an SQLite database to memory and makes it the global database source
	 * @param source String
	 * @return boolean
	 * @throws DetectRightException
	 * @throws ConnectionLostException
	 */
		// copies database from dbLink into this database connection
		// this only works for engines which support a "memory" option: i.e. not MySQL
		// special case: coming from an SQLite database

		$mem = new SQLite(md5("SQLite//:memory:"));
		$params = array();
		$params["engine"] = "SQLite";
		$params["address"] = ":memory:";
		$params["timestamp"] = time();
		//$mem->params = $params;
		$mem->connect($params);

		if (!$mem->dbOK) return false;
		$globalTmp = $mem->db;

		$engine = DRFunctionsCore::gv($this->params,"engine");
		$host = DRFunctionsCore::gv($this->params,"address");

		if ($engine === "SQLite") {
			try {
				/* we don't need the source link we passed in: we just used it to check for validity.
				* we also needed it for parameter verification. The real action is in the "attach" statement below.
				*/
				$this->_close();

				// attach local database to in-memory database
				$attachStmt = "ATTACH '" . $host . "' AS src";
				$result = $globalTmp->exec($attachStmt);

				$countResult="";
				$cnt = "select count(*) as cnt from src.sqlite_master where type='table'";
				$result = $globalTmp->query($cnt);
				$rows = $result->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$countResult = $row['cnt'];
				}

				$sql = array();
				// copy table definition
				$tableQuery = "SELECT sql FROM src.sqlite_master WHERE type='table'";
				$result = $globalTmp->query($tableQuery);
				$rows = $result->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$sql[] = $row['sql'];
				}

				foreach ($sql as $sqlStr) {
					$result = $globalTmp->exec($sqlStr);
				}

				$sql = array();
				// copy data
				$tableNameQuery = "SELECT name FROM sqlite_master WHERE type='table'";
				$result = $globalTmp->query($tableNameQuery);
				$rows = $result->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$copyDataQuery =
					"INSERT INTO " . $row['name'] . " SELECT * FROM src." . $row['name'];
					$sql[] = $copyDataQuery;
				}

				foreach ($sql as $sqlStr) {
					$result = $globalTmp->exec($sqlStr);
				}

				$sql = array();
				// copy other definitions (i.e. indices)
				$nonTableQuery = "SELECT sql FROM src.sqlite_master WHERE type!='table'";
				$result = $globalTmp->query($nonTableQuery);
				$rows = $result->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$sql[] = $row['sql'];
				}

				foreach ($sql as $sqlStr) {
					$result = $globalTmp->exec($sqlStr);
				}

				$sql = array();

				// detach local db
				$detachStmt = "DETACH src";
				$globalTmp->exec($detachStmt);

				return $mem;
			} catch (Exception $e) {
				return null;
			}
		} else {
			// we should really just copy everything... but that's for another day.
		}
		return null;
	}

	/**
	 * Which wildcard character is used to represent
	 * multiple characters?
	 *
	 * @return string
	 * @access public
	 */
	function _multichar_wildcard() {
		return "%";
	}

	/**
	 * Which wildcard character is used to represent
	 * a single character?
	 *
	 * @return string
	 * @access public
	 */
	function _singlechar_wildcard() {
		return "_";
	}

	/**
	 * Which delimiter is put round fields and tables?
	 *
	 * @return string
	 * @access public
	 */
	function _identifier_delimiter() {
		return self::IDD;
	}

	/**
	 * Which delimiter is put round values?
	 *
	 * @return string
	 * @access public
	 */
	function _quoted_delimiter() {
		return "'";
	}

	/**
	 * Ping!
	 *
	 * @param resource $link
	 * @return boolean
	 * @access public
	 */
	function _ping() {
		return true;
	}

		/**
	 * Free a result. Since SQLite doesn't have the ability to
	 * free "resources" as such,
	 * we just free up the object.
	 *
	 * @param resource $result
	 * @access public
	 */
	function _free_result(&$result) {
		unset($result);
	}
		/**
	 * Return a timestamp, in all its timestamply glory.
	 *
	 * @param timestamp $ts
	 * @return string
	 * @access public
	 */
	function _ts2Date($ts) {
		return date("Y-m-d H:i:s",$ts);
	}

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
	 * @access public
	 */
	function _now() {
		return "DATETIME('NOW')";
	}

	/**
	 * Does this engine support "delayed"?
	 *
	 * @return string
	 * @access public
	 */
	function _delayed_string() {
		return "";
	}


		/**
	 * Get list of tables
	 *
	 * @return array
	 * @access public
	 */
	function _getTableList() {
		// extend this
		$result = $this->_query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
		$rows = $this->_fetch_all($result);
		foreach ($rows as $row) {
			$output[]=array_shift($row);
		}
		return $output;
	}

		/**
	 * Bulk insert string
	 *
	 * @param string $table
	 * @param string $fields
	 * @param string $values
	 * @return boolean
	 * @access public
	 */
	function _bulk_insert($table,$fields,$values) {
		$success=true;
		foreach ($values as $value) {
			$sql = "insert into $table ($fields) VALUES $value";
			$success = $success && $this->_query($sql);
		}
	}

		/**
	 * Returns literal string representing null values
	 *
	 * @return string
	 */
	function _null() {
		return "null";
	}

	function getDDL($tablename,$pk, $index, $fields) {
		if (!is_array($pk)) return array();
		if (!is_array($index)) return array();
		if (!is_array($fields)) return array();
		$boolValidator = new boolean_validator("boolean");
		$output = array();

		$keys = array_keys($fields);

		foreach ($keys as $key) {
			$lhm = $fields[$key];
			if (!is_array($lhm)) continue;
			$colName = DRFunctionsCore::gv($lhm,'colName',"");
			$dataType = DRFunctionsCore::gv($lhm,'dataType',"");
			if ($dataType === "") continue;
			if (!isset(self::$exportTypeMap[$dataType])) continue;
			$dataName = self::$exportTypeMap[$dataType];
			if (DRFunctionsCore::in("TEXT",$dataName)) $dataName = $dataName." COLLATE NOCASE";
			$colSize = DRFunctionsCore::gv($lhm,"colSize",0);
			if (!is_numeric($colSize)) continue;
			if ($colSize > 0 && DRFunctionsCore::in("(M)",$dataName)) {
				$dataName = str_replace("(M)","(".$colSize.")",$dataName);
			} elseif ($colSize < 1 && DRFunctionsCore::in("(M)",$dataName)) {
				$dataName = substr($dataName,0,strpos(dataName,"(M)"));
			}
			$takesNullsObject = DRFunctionsCore::gv($lhm,"takesNulls");
			$takesNulls = true;
			if (!is_null($takesNullsObject)) {
				$takesNulls = $boolValidator->process($takesNullsObject);
			}
			$command = array();
			$command[] = "{idd}" . $colName . "{idd}";
			$command[] = $dataName;

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
				//PRIMARY KEY (\"ID1\",\"ID2\")
				if (!is_array($lhm)) continue;
				$colName = DRFunctionsCore::gv($lhm,"colName","");
				if (DRFunctionsCore::isEmptyStr($pkName)) $pkName = DRFunctionsCore::gv($lhm,"pkName","");
				// NOTE: SQLite requires indices that are unique across the database. This means we have to
				// append the tablename to each index to ensure its uniqueness.
				if (!DRFunctionsCore::in($tablename,$pkName)) $pkName = $pkName.$tablename;
				$keyList[] = "{idd}" . $colName . "{idd}";
			}
			$output[] = $pkString . implode(",",$keyList) . ")";
		}

		$indexStrings = array();
		if (count($index) > 0) {
			$indexNames = array_keys($index);
			foreach ($indexNames as $indexName) {
				$iName = $indexName;
				if (!DRFunctionsCore::in($tablename,$indexName)) {
					$iName = $indexName.$tablename;
				}
				$establishedUniqueness = false;
				// comparison with primary key name to ensure that we don't include it twice.
				// however, the name has already been appended.
				if ($iName === $pkName) continue;
				//String indexString = "";
				$indexBits = array();
				$lhm = DRFunctionsCore::gv($index,$indexName);
				if (!is_array($lhm)) continue;
				if (count($lhm) == 0) continue;
				ksort($lhm);

				foreach ($lhm as $ihm) {
					if (!is_array($ihm)) continue;
					/*indexColumn.put("colName",colName);
					indexColumn.put("nonUnique",nonUnique);
					indexColumn.put("ascOrDesc",ascOrDesc);*/
					$colName = DRFunctionsCore::gv($ihm,"colName","");
					if ($colName === "") continue;
					if (!$establishedUniqueness) {
						$nonUnique = $boolValidator->process(DRFunctionsCore::gv($ihm,"nonUnique","0"));
						if (!$nonUnique) {
							$establishedUniqueness=true;
						}
					}
					$indexBits[] = "{idd}" . $colName . "{idd}";
				}

				if (!$establishedUniqueness) {
					// this is a normal key
					$indexStrings[] = "CREATE INDEX {idd}" . $iName . "{idd} ON {idd}" . $tablename . "{idd} (" . implode(",",$indexBits) . ")";
				} else {
					$indexStrings[] = "CREATE UNIQUE INDEX {idd}" . $iName . "{idd} ON {idd}" . $tablename . "{idd} (" . implode(",",$indexBits) . ")";
				}

			}
		}
		$innerBrackets = implode(", ",$output);
		$returnStr = "CREATE TABLE {idd}" . $tablename . "{idd} (" . $innerBrackets . ")";
		$resultArr= array();
		$resultArr[] = $returnStr;
		if (count($indexStrings) > 0) {
			foreach ($indexStrings as $indexString) {
				$resultArr[] = $indexString;
			}
		}
		return $resultArr;
	}

		function getTableIndices($tableName,$getIndex="") {
		$indexCollection = array();

		$result = $this->_query("select * from sqlite_master where \"type\"='index' and \"tbl_name\" = '$tableName'",$this->db);
		if ($result === false) return array();
		if ($this->_num_rows($result) === 0) return array();
		while ($index = $this->_fetch_assoc($result)) {
			$indexColumn = array();
			$indexName = $index['name'];
			$tnl = strlen($tableName);
			if (substr($indexName,-$tnl,$tnl) == $tableName) {
				$indexName = substr($indexName,0,-$tnl);
			}
			if ($getIndex && $getIndex !== $indexName) continue;
			$sql = $index['sql'];
			//CREATE UNIQUE INDEX "hashentity" ON "entity" ("hash")
			if (!DRFunctionsCore::in($tableName,$sql)) continue; // problemo!
			$nonUnique=!(DRFunctionsCore::in("unique",$sql));
			$tmp = explode("(\"",$sql);
			$tmp2 = explode(",",str_replace(array("\"",")"),"",$tmp[1]));
			$indexCollection[$indexName] = array();

			foreach ($tmp2 as $ordinal=>$colName) {
				if (substr($colName,-2,2) == "\")") $colName = substr($colName,0,-2);
				$indexColumn = array();
				$indexColumn['nonUnique'] = $nonUnique;
				$indexColumn['colName'] = $colName;
				$indexCollection[$indexName][$ordinal+1] = $indexColumn;
			}
		}
		return $indexCollection;
	}

	function getPrimaryKey($tableName) {
		$pkName = "PRIMARY";
		$primaryKey = array();

		$result = $this->_query("select * from sqlite_master where \"type\"='table' and \"tbl_name\" = '$tableName'",$this->db);
		if ($result === false) return array();
		if ($this->_num_rows($result) === 0) return array();
		$table = $this->_fetch_assoc($result);
		$sql = $table['sql'];
		//PRIMARY KEY ("
		$tmp = explode("PRIMARY KEY (",$sql);
		$pk = $tmp[1];
		$pk = str_replace(array("\"",")"),"",$pk);
		$fields = explode(",",$pk);
		foreach ($fields as $ordinal=>$colName) {
			$pkColumn = array();
			$pkColumn["pkName"] = $pkName;
			$pkColumn["colName"] = $colName;
			$primaryKey[$ordinal+1] = $pkColumn;
		}
		return $primaryKey;
	}

}

if (!class_exists("SQLite")) {
	DetectRight::registerClass("SQLite");
	Class SQLite extends DRSQLite {

	}
}