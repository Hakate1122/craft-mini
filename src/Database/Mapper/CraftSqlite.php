<?php
namespace Craft\Database\Mapper;

use Exception;
use SQLite3;

#[\AllowDynamicProperties]
class CraftSqlite extends BaseMapper
{
    public function __construct()
    {
        $dbname = env('DB_NAME');
        $this->connection = new SQLite3($dbname . '.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        if ($this->connection->lastErrorCode() !== 0) {
            throw new Exception("Database connection failed: " . $this->connection->lastErrorMsg());
        }
    }
    protected function getConnection()
    {
        return $this->connection;
    }
    protected function fetchAllRows($result)
    {
        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }
    protected function fetchRow($result)
    {
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    protected function prepare($sql)
    {
        return $this->connection->prepare($sql);
    }
    protected function lastError()
    {
        return $this->connection->lastErrorMsg();
    }
    protected function lastInsertId()
    {
        return $this->connection->lastInsertRowID();
    }
    protected function affectedRows($stmt)
    {
        return $this->connection->changes();
    }
    protected function getTableColumns()
    {
        $columns = [];
        $sqlDescribe = $this->getDescribeTableSql();
        $resultDescribe = $this->connection->query($sqlDescribe);
        while ($row = $resultDescribe->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }
        return $columns;
    }
    protected function bindParams($stmt, $types, $values)
    {
        // SQLite3 không dùng types, chỉ bindValue theo thứ tự
        $i = 0;
        foreach ($values as $val) {
            $stmt->bindValue($i + 1, $val, SQLITE3_TEXT);
            $i++;
        }
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
        return "PRAGMA table_info({$this->table})";
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
