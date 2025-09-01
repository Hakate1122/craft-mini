<?php
namespace Craft\Database\Mapper;

use Exception;
use mysqli;

/**
 * CraftMysqli class
 * 
 * This class is used to handle the database connection and queries.
 * 
 * It is used to handle the database connection and queries.
 */
#[\AllowDynamicProperties]
class CraftMysqli extends BaseMapper
{
    public function __construct()
    {
        $host = env('DB_HOST');
        $dbname = env('DB_NAME');
        $username = env('DB_USER');
        $password = env('DB_PASS');
        $this->connection = new mysqli($host, $username, $password, $dbname, 3306);
        if ($this->connection->connect_error) {
            throw new Exception("Database connection failed: " . $this->connection->connect_error);
        }
        $this->connection->set_charset("utf8mb4");
    }
    protected function getConnection()
    {
        return $this->connection;
    }
    protected function fetchAllRows($result)
    {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
    protected function fetchRow($result)
    {
        return $result->fetch_assoc();
    }
    protected function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }
    protected function lastError()
    {
        return $this->connection->error;
    }
    protected function lastInsertId()
    {
        return $this->connection->insert_id;
    }
    protected function affectedRows($stmt)
    {
        return $stmt->affected_rows;
    }
    protected function getTableColumns()
    {
        $columns = [];
        $sqlDescribe = $this->getDescribeTableSql();
        $resultDescribe = $this->connection->query($sqlDescribe);
        while ($row = $resultDescribe->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        return $columns;
    }
    protected function bindParams($stmt, $types, $values)
    {
        $stmt->bind_param($types, ...$values);
    }
    protected function getSoftDeleteTraitName()
    {
        return 'Craft\\Database\\Helper\\SoftDelete';
    }
    protected function getTimestampTraitName()
    {
        return 'Craft\\Database\\Helper\\Timestamp';
    }
    protected function getDescribeTableSql()
    {
        return "DESCRIBE {$this->table}";
    }
    protected function getDeletedAtFieldName()
    {
        return 'deleted_at';
    }
    protected function getIdFieldName()
    {
        return 'id';
    }
}