<?php

namespace Craft\Database\QueryBuilder;

use Exception;
use mysqli;
use mysqli_sql_exception;

class CraftMysqli extends BaseBuilder
{
    private $connection;
    public function __construct()
    {
        if (!extension_loaded('mysqli')) {
            throw new Exception('mysqli extension is not enabled or does not exist!');
        }
        $host = env('DB_HOST');
        $database = env('DB_NAME');
        $username = env('DB_USER');
        $password = env('DB_PASS');
        $this->connection = new mysqli($host, $username, $password, $database);
        $this->connection->set_charset("utf8mb4");
        if ($this->connection->connect_error) {
            throw new mysqli_sql_exception("Connection failed: " . $this->connection->connect_error);
        }
    }
    // Override where, set, values, execute để dùng real_escape_string và logic MySQLi
    public function where($columns, $values = null, $operator = '=') {
        if (is_array($columns)) {
            $conditions = [];
            foreach ($columns as $column => $value) {
                $conditions[] = "{$column} {$operator} '" . $this->connection->real_escape_string($value) . "'";
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
                        $conditions[] = "{$columns[$i]} {$operator} '" . $this->connection->real_escape_string($values[$i]) . "%'";
                    } else {
                        $conditions[] = "{$columns[$i]} {$operator} '" . $this->connection->real_escape_string($values[$i]) . "'";
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
    public function set(...$data) {
        if (count($data) == 1 && is_array($data[0])) {
            $data = $data[0];
            $setClause = [];
            foreach ($data as $column => $value) {
                $setClause[] = "{$column} = '" . $this->connection->real_escape_string($value) . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        } else {
            $setClause = [];
            foreach ($data as $index => $value) {
                $setClause[] = "{$this->columns[$index]} = '" . $this->connection->real_escape_string($value) . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        }
        return $this;
    }
    public function values($data)
    {
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $columns = implode(", ", array_keys($data[0]));
            $values = implode(", ", array_map(function ($value) {
                return "'" . $this->connection->real_escape_string($value) . "'";
            }, $data[0]));
            $this->query .= " ({$columns}) VALUES ({$values})";
        } else {
            if (array_keys($data) !== range(0, count($data) - 1)) {
                $columns = implode(", ", array_keys($data));
                $values = implode(", ", array_map(function ($value) {
                    return "'" . $this->connection->real_escape_string($value) . "'";
                }, $data));
                $this->query .= " ({$columns}) VALUES ({$values})";
            } else {
                $values = implode(", ", array_map(function ($value) {
                    return "'" . $this->connection->real_escape_string($value) . "'";
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
                if ($result->num_rows === 1) {
                    return [$result->fetch_assoc()];
                } else {
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    return $data;
                }
            } else {
                return true;
            }
        } else {
            throw new Exception("Error executing query: " . $this->connection->error);
        }
    }
}