<?php

namespace Craft\Database\QueryBuilder;

use SQLite3;
use Exception;

class CraftSqlite extends BaseBuilder
{
    private $connection;
    public function __construct()
    {
        if (!extension_loaded('sqlite3')) {
            throw new Exception('sqlite3 extension is not enabled or does not exist!');
        }
        $database = env('DB_SQLITE_FILE');
        $this->connection = new SQLite3($database . '.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        if ($this->connection->lastErrorCode() !== 0) {
            throw new Exception("Failed to connect to the database: " . $this->connection->lastErrorMsg());
        }
    }
    // Override where để dùng SQLite3::escapeString và logic SQLite3
    public function where($columns, $values = null, $operator = '=')
    {
        if (is_array($columns)) {
            $conditions = [];
            foreach ($columns as $column => $value) {
                $conditions[] = "{$column} {$operator} '" . SQLite3::escapeString($value) . "'";
            }
            $this->whereClause = " WHERE " . implode(" AND ", $conditions);
        } else {
            if (is_string($columns)) {
                $columns = explode(',', $columns);
            }
            if (is_string($values)) {
                $values = explode(',', $values);
            }
            if (count($columns) === count($values)) {
                $conditions = [];
                for ($i = 0; $i < count($columns); $i++) {
                    if ($operator === 'LIKE') {
                        $conditions[] = "{$columns[$i]} {$operator} '" . SQLite3::escapeString($values[$i]) . "%'";
                    } else {
                        $conditions[] = "{$columns[$i]} {$operator} '" . SQLite3::escapeString($values[$i]) . "'";
                    }
                }
                if ($this->whereClause === '') {
                    $this->whereClause = " WHERE " . implode(" AND ", $conditions);
                } else {
                    $this->whereClause .= " AND " . implode(" AND ", $conditions);
                }
            }
        }
        return $this;
    }
    // Override set để dùng SQLite3::escapeString
    public function set(...$data)
    {
        if (count($data) == 1 && is_array($data[0])) {
            $data = $data[0];
            $setClause = [];
            foreach ($data as $column => $value) {
                $setClause[] = "{$column} = '" . SQLite3::escapeString($value) . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        } else {
            $setClause = [];
            foreach ($data as $index => $value) {
                $setClause[] = "{$this->columns[$index]} = '" . SQLite3::escapeString($value) . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        }
        return $this;
    }
    // Override values để dùng SQLite3::escapeString
    public function values($data)
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $columns = implode(", ", array_keys($data[0]));
            $values = implode(", ", array_map(function ($value) {
                return "'" . SQLite3::escapeString($value) . "'";
            }, $data[0]));
            $this->query .= " ({$columns}) VALUES ({$values})";
        } else {
            if (array_keys($data) !== range(0, count($data) - 1)) {
                $columns = implode(", ", array_keys($data));
                $values = implode(", ", array_map(function ($value) {
                    return "'" . SQLite3::escapeString($value) . "'";
                }, $data));
                $this->query .= " ({$columns}) VALUES ({$values})";
            } else {
                $values = implode(", ", array_map(function ($value) {
                    return "'" . SQLite3::escapeString($value) . "'";
                }, $data));
                $this->query .= " VALUES ({$values})";
            }
        }
        return $this;
    }
    /**
     * Execute the query and return the result.
     * @param bool $debug If true, will print the query and exit.
     * @throws \Exception If there is an error executing the query.
     * @return array<array|bool|null>|bool
     */
    public function execute(bool $debug = false)
    {
        if ($this->joinClause) {
            $this->query .= $this->joinClause;
        }
        if ($this->whereClause) {
            $this->query .= $this->whereClause;
        }
        if ($debug) {
            echo "<pre>";
            print_r($this->query);
            echo "</pre>";
            die();
        }
        $result = $this->connection->query($this->query);
        if ($result) {
            if (stripos(trim($this->query), "SELECT") === 0) {
                $data = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $data[] = $row;
                }
                return $data;
            } else {
                return true;
            }
        } else {
            throw new Exception("Error executing query: " . $this->connection->lastErrorMsg());
        }
    }
}