<?php

class D {
	public static $db;
	private static $f;

	public static function setup($dsn, $mysql = FALSE, $frozen = FALSE) {
		self::$f = $frozen;

		if ($mysql) {
			$dsn = "mysql:host=" . $dsn["host"] . ";dbname=" . $dsn["name"] . ";charset=" . $dsn["char"] . ";port=" . $dsn["port"];
			try {
				$pdo = new PDO($dsn, $dsn["user"], $dsn["pass"], [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => FALSE,
				]);
			}
			catch (PDOException $e) {
				throw new PDOException($e->getMessage(), (int)$e->getCode());
			}
		}
		else {
			self::$db = new PDO("sqlite:" . $dsn["name"]);
		}
	}

	public static function dispense($table) {
		$sql = "create table if not exists {$table} (id int primary key not null);";
		$ret = self::$db->exec($sql);

		if (!$ret) {
			return NULL;
		}
		else {
			$obj = new stdClass();
			$obj->_table = $table;
			return $obj;
		}
	}

	public static function store($obj) {
		$table = $obj->_table;

		if (!$table) {
			return FALSE;
		}

		if (!self::$f) {
			$sql = "pragma table_info({$table});";
			$ret = self::$db->exec($sql);
			$cols = $ret->fetchArray(SQLITE3_ASSOC);
			$new = [];

			foreach ($obj as $col => $val) {
				if ($field != "_table") {
					if (!in_array($col, $cols)) {
						$new[] = [$col => $val];
					}
				}
			}

			if (count($new) > 0) {
				$sql = "alter table {$table} ";

				foreach ($new as $col => $val) {
					$type = NULL;
					switch (gettype($val)) {
						case "integer":
							$type = "integer";
							break;
						case "double":
							$type = "real";
							break;
						case "string":
							$type = "text";
							break;
					}

					if ($type) {
						$sql .= "add {$col} {$type},";
					}
				}

				$sql = substr_replace($sql, ";", -1);
				$ret = self::$db->exec($sql);
			}
		}
		if (isset($obj->id)) {
			$id = $obj->id;
			$sql = "update {$table} set ";

			foreach ($obj as $col => $val) {
				if ($field != "_table") {
					$sql .= "{$col} = {$val},";
				}
			}

			$sql = rtrim($sql, ",") . " where id = {$id};";
			$ret = self::$db->exec($sql);
		}
		else {
			$sql = "insert into {$table} (";

			foreach ($obj as $col => $val) {
				if ($field != "_table") {
					$sql .= "{$col},";
				}
			}

			$sql = rtrim($sql, ",") . ") values (";

			switch (gettype($val)) {
				case "integer":
					$sql .= "{$val},";
					break;
				case "double":
					$sql .= "{$val},";
					break;
				case "string":
					$sql .= "'{$val}',";
					break;
			}

			$sql = rtrim($sql, ",") . ");";
			$ret = self::$db->exec($sql);
		}

		if (!$ret) {
			return self::$db->lastErrorMsg();
		}
		else {
			return TRUE;
		}
	}

	public static function find($table, $query, $params = [], $num = 0) {
		$query = strtr($query, $params);
		$sql = "select * from {$table} where {$query}";
		$ret = self::$db->query($sql . ($num > 0) ? " limit {$num};" : ";");

		if (!$ret) {
			return self::$db->lastErrorMsg();
		}
		else {
			$arr = [];
			$rows = $ret->fetchArray(SQLITE3_ASSOC);

			foreach ($rows as $row) {
				$obj = new stdClass();
				$obj->_table = $table;

				foreach ($row as $col => $val) {
					$obj->$col = $val;
				}
				$arr[] = $obj;
			}

			return $arr;
		}
	}

	public static function trash($obj) {
		$table = $obj->_table;

		if (!$table) {
			return FALSE;
		}

		$id = $obj->id;
		$sql = "delete from {$table} where id = {$id};";
		$ret = self::$db->exec($sql);
		
		if (!$ret) {
			return self::$db->lastErrorMsg();
		}
		else {
			return TRUE;
		}
	}
}