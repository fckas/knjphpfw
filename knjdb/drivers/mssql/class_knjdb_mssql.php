<?php
	class knjdb_mssql{
		private $args;
		public $sep_col = "";
		public $sep_val = "'";
		public $sep_table = "";
		public $sep_index = "";
		
		function __construct($knjdb, $args){
			$this->args = $args;
			$this->knjdb = $knjdb;
			
			require_once("knjphpframework/functions_knj_extensions.php");
			knj_dl("mssql");
		}
		
		function connect(){
			$this->conn = mssql_connect($this->args["host"], $this->args["user"], $this->args["pass"]);
			if (!$this->conn){
				throw new Exception("Could not connect to the database.");
			}
			
			if ($this->args["db"]){
				if (!mssql_select_db($this->args["db"], $this->conn)){
					throw new Exception("Could not select database.");
				}
			}
		}
		
		function close(){
			mssql_close($this->conn);
			unset($this->conn);
		}
		
		function query($sql){
			$res = mssql_query($sql, $this->conn);
			if (!$res){
				throw new Exception("Query error: " . mssql_get_last_message());
			}
			
			return new knjdb_result($this->knjdb, $this, $res);
		}
		
		function fetch($res){
			$data = mssql_fetch_assoc($res);
			
			if (is_array($data)){
				/** NOTE: This prevents the weird empty columns from MS-SQL. */
				foreach($data AS $key => $value){
					if (strlen($value) == 1 && ord($value) == 2){
						$data[$key] = "";
					}
				}
			}
			
			return $data;
		}
		
		function error(){
			throw new Exception("Not supported.");
		}
		
		function sql($sql){
			return strtr($sql, array(
					"'" => "''"
				)
			);
		}
		
		function getLastID(){
			throw new Exception("Not supported.");
		}
		
		/** A quick way to do a simple select. */
		function select($table, $where = null, $args = null){
			$sql = "SELECT";
			
			if ($args["limit"]){
				$sql .= " TOP " . $args["limit"];
			}
			
			$sql .= " * FROM [" . $table . "]";
			 
			if ($where){
				$sql .= " WHERE " . $this->makeWhere($where);
			}
			
			if ($args["orderby"]){
				$sql .= " ORDER BY " . $args["orderby"];
			}
			
			return $this->query($sql);
		}
		
		/** A quick way to insert a new row into the database. */
		function insert($table, $arr){
			$sql = "INSERT INTO [" . $table . "] (";
			
			$first = true;
			foreach($arr AS $key => $value){
				if ($first == true){
					$first = false;
				}else{
					$sql .= ", ";
				}
				
				$sql .= "[" . $key . "]";
			}
			
			$sql .= ") VALUES (";
			$first = true;
			foreach($arr AS $key => $value){
				if ($first == true){
					$first = false;
				}else{
					$sql .= ", ";
				}
				
				$sql .= $this->sep_val . $this->sql($value) . $this->sep_val;
			}
			$sql .= ")";
			
			$this->query($sql);
			
			if ($this->knjdb->insert_autocommit){ //check wherever autocommit is on.
				$this->knjdb->insert_countcommit++;
				
				if ($this->knjdb->insert_countcommit >= $this->knjdb->insert_autocommit){
					$this->knjdb->trans_commit();
					$this->knjdb->trans_begin();
					$this->knjdb->insert_countcommit = 0;
				}
			}
			
			return true;
		}
		
		/** A quick way to do a simple update. */
		function update($table, $data, $where = null){
			if (!is_array($data)){
				throw new Exception("Second argument must be an array with data.");
			}
			
			$sql .= "UPDATE [" . $table . "] SET ";
			
			$first = true;
			foreach($data AS $key => $value){
				if ($first == true){
					$first = false;
				}else{
					$sql .= ", ";
				}
				
				$sql .= "[" . $key . "] = " . $this->sep_val . $this->sql($value) . $this->sep_val;
			}
			
			if ($where){
				$sql .= " WHERE " . $this->makeWhere($where);
			}
			
			return $this->query($sql);
		}
		
		/** A quick way to do a simple delete. */
		function delete($table, $where = null){
			$sql = "DELETE FROM [" . $table . "]";
			 
			if ($where){
				$sql .= " WHERE " . $this->makeWhere($where);
			}
			
			return $this->query($sql);
		}
		
		/** Returns the SQL for the query based on an array. */
		function makeWhere($where){
			$first = true;
			foreach($where AS $key => $value){
				if ($first == true){
					$first = false;
				}else{
					$sql .= " AND ";
				}
				
				$sql .= "[" . $key . "] = " . $this->sep_val . $this->sql($value) . $this->sep_val;
			}
			
			return $sql;
		}
		
		function date_format($unixt){
			return date("m/d/Y H:i:s", $unixt);
		}
	}
?>