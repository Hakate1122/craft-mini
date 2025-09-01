<?php
namespace Craft\Database\Mapper;

use Exception;
use ArrayObject;

abstract class BaseMapper
{
    /** Kiểu kết nối cơ sở dữ liệu sẽ sử dụng. */
    protected $connection;
    /** Tên bảng cơ sở dữ liệu sẽ sử dụng cho mapper. */
    protected $table;
    /** Khóa chính của bảng */
    protected $primaryKey;
    /** Các trường có thể gán giá trị hàng loạt */
    protected $fillable = [];
    /** The ID field name for the mapper. */
    protected $id;
    /** @var bool Whether to apply soft delete logic. */
    protected $softDelete = false;
    /** @var bool Whether to apply timestamps logic. */
    protected $timestamps = false;

    abstract protected function getConnection();
    abstract protected function fetchAllRows($result);
    abstract protected function fetchRow($result);
    abstract protected function prepare($sql);
    abstract protected function lastError();
    abstract protected function lastInsertId();
    abstract protected function affectedRows($stmt);
    abstract protected function getTableColumns();
    abstract protected function bindParams($stmt, $types, $values);
    abstract protected function getSoftDeleteTraitName();
    abstract protected function getTimestampTraitName();
    abstract protected function getDescribeTableSql();
    abstract protected function getDeletedAtFieldName();
    abstract protected function getIdFieldName();

    // Helper methods để tái sử dụng logic
    protected static function validateTableName($instance)
    {
        if (empty($instance->table)) {
            throw new Exception("Table name is not set.");
        }
    }

    protected static function executeQuery($instance, $sql)
    {
        $result = $instance->getConnection()->query($sql);
        if ($result === false) {
            throw new Exception("Error executing query: " . $instance->lastError());
        }
        return $result;
    }

    protected static function executePreparedQuery($instance, $sql, $types, $values)
    {
        $stmt = $instance->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $instance->lastError());
        }
        $instance->bindParams($stmt, $types, $values);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception("Error executing query: " . $instance->lastError());
        }
        return $result;
    }

    protected static function handleTimestamps($instance, &$data, $isUpdate = false): void
    {
        $uses = class_uses($instance);
        $timestamps = property_exists($instance, 'timestamps') ? $instance->timestamps : true;

        if ((in_array($instance->getTimestampTraitName(), $uses) || $timestamps)) {
            $check = $instance->checkTimestampColumns();
            if (!$check['created_at'] || !$check['updated_at']) {
                throw new Exception("Table '{$instance->table}' is missing 'created_at' or 'updated_at' columns but timestamps are enabled in model.");
            }
        }

        $now = date('Y-m-d H:i:s');
        if (in_array($instance->getTimestampTraitName(), $uses)) {
            $timestamps = property_exists($instance, 'timestamps') ? $instance->timestamps : true;
            if ($timestamps) {
                if (!$isUpdate) {
                    $data['created_at'] = $now;
                }
                $data['updated_at'] = $now;
            }
        } elseif (property_exists($instance, 'timestamps') && $instance->timestamps) {
            if (!$isUpdate) {
                $data['created_at'] = $now;
            }
            $data['updated_at'] = $now;
        }
    }

    protected static function convertToArrayObject($data)
    {
        return array_map(function ($item) {
            return new ArrayObject($item, ArrayObject::ARRAY_AS_PROPS);
        }, $data);
    }

    protected static function buildWhereClause($conditions, $instance)
    {
        $where = [];
        $types = '';
        $values = [];

        foreach ($conditions as $column => $value) {
            $where[] = "{$column} = ?";
            $types .= "s";
            $values[] = $value;
        }

        return [$where, $types, $values];
    }

    protected static function checkSoftDeleteSupport($instance)
    {
        $uses = class_uses($instance);
        $hasSoftDelete = in_array($instance->getSoftDeleteTraitName(), $uses);
        $columns = $instance->getTableColumns();
        $hasDeletedAt = in_array($instance->getDeletedAtFieldName(), $columns);

        return [$hasSoftDelete, $hasDeletedAt];
    }

    // Public methods sử dụng helper methods
    public static function all()
    {
        $instance = new static();
        self::validateTableName($instance);

        list($hasSoftDelete, $hasDeletedAt) = self::checkSoftDeleteSupport($instance);

        if ($hasSoftDelete && $hasDeletedAt) {
            $sql = "SELECT * FROM {$instance->table} WHERE {$instance->getDeletedAtFieldName()} IS NULL";
        } else {
            $sql = "SELECT * FROM {$instance->table}";
        }

        $result = self::executeQuery($instance, $sql);
        $data = $instance->fetchAllRows($result);
        return self::convertToArrayObject($data);
    }

    public static function allWithTrashed()
    {
        $instance = new static();
        self::validateTableName($instance);

        $sql = "SELECT * FROM {$instance->table}";
        $result = self::executeQuery($instance, $sql);
        $data = $instance->fetchAllRows($result);
        return self::convertToArrayObject($data);
    }

    public static function allOnlyTrashed()
    {
        $instance = new static();
        self::validateTableName($instance);

        $sql = "SELECT * FROM {$instance->table} WHERE {$instance->getDeletedAtFieldName()} IS NOT NULL";
        $result = self::executeQuery($instance, $sql);
        $data = $instance->fetchAllRows($result);
        return self::convertToArrayObject($data);
    }

    public static function findBy(array $columns, ?array $data = null)
    {
        $instance = new static();
        self::validateTableName($instance);

        if (empty($columns)) {
            throw new Exception("No columns specified for findBy.");
        }

        $where = [];
        $types = '';
        $values = [];

        foreach ($columns as $i => $column) {
            $where[] = "{$column} = ?";
            $types .= "s";
            $values[] = $data[$i] ?? null;
        }

        $where[] = "{$instance->getDeletedAtFieldName()} IS NULL";
        $sql = "SELECT * FROM {$instance->table} WHERE " . implode(" AND ", $where);

        $result = self::executePreparedQuery($instance, $sql, $types, $values);
        $dataArr = $instance->fetchAllRows($result);
        return self::convertToArrayObject($dataArr);
    }

    public static function find($id)
    {
        $instance = new static();
        self::validateTableName($instance);

        list($hasSoftDelete, $hasDeletedAt) = self::checkSoftDeleteSupport($instance);

        if ($hasSoftDelete && $hasDeletedAt) {
            $sql = "SELECT * FROM {$instance->table} WHERE {$instance->getIdFieldName()} = ? AND {$instance->getDeletedAtFieldName()} IS NULL";
        } else {
            $sql = "SELECT * FROM {$instance->table} WHERE {$instance->getIdFieldName()} = ?";
        }

        $result = self::executePreparedQuery($instance, $sql, "i", [$id]);
        $row = $instance->fetchRow($result);

        if (!$row) {
            return null;
        }
        return new ArrayObject($row, ArrayObject::ARRAY_AS_PROPS);
    }

    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new Exception("Record not found with id: $id");
        }
        return $result;
    }

    public static function findWithTrashed($id)
    {
        $instance = new static();
        self::validateTableName($instance);

        $sql = "SELECT * FROM {$instance->table} WHERE {$instance->getIdFieldName()} = ?";
        $result = self::executePreparedQuery($instance, $sql, "i", [$id]);
        $row = $instance->fetchRow($result);

        if (!$row) {
            return null;
        }

        $model = new static();
        foreach ($row as $key => $value) {
            $model->$key = $value;
        }
        return $model;
    }

    protected function checkTimestampColumns()
    {
        $columns = $this->getTableColumns();
        return [
            'created_at' => in_array('created_at', $columns),
            'updated_at' => in_array('updated_at', $columns),
        ];
    }

    public static function store($data)
    {
        $instance = new static();
        self::validateTableName($instance);

        self::handleTimestamps($instance, $data, false);

        $columns = array_keys($data);
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO {$instance->table} (" . implode(", ", $columns) . ") VALUES ({$placeholders})";

        $types = str_repeat("s", count($data));
        $values = array_values($data);

        $stmt = $instance->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $instance->lastError());
        }
        $instance->bindParams($stmt, $types, $values);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        return $instance->lastInsertId();
    }

    public static function update($id, $data)
    {
        $instance = new static();
        self::validateTableName($instance);

        self::handleTimestamps($instance, $data, true);

        $setClause = implode(", ", array_map(function ($key) {
            return "$key = ?";
        }, array_keys($data)));

        $sql = "UPDATE {$instance->table} SET {$setClause} WHERE {$instance->getIdFieldName()} = ?";

        $types = str_repeat("s", count($data)) . "i";
        $values = array_values($data);
        $values[] = $id;

        $stmt = $instance->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $instance->lastError());
        }
        $instance->bindParams($stmt, $types, $values);
        $stmt->execute();

        if ($stmt->error) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        return $instance->affectedRows($stmt);
    }

    public static function delete($id)
    {
        $instance = new static();
        self::validateTableName($instance);

        $uses = class_uses($instance);
        if (in_array($instance->getSoftDeleteTraitName(), $uses)) {
            $deletedAt = date('Y-m-d H:i:s');
            $sql = "UPDATE {$instance->table} SET {$instance->getDeletedAtFieldName()} = ? WHERE {$instance->getIdFieldName()} = ?";
            $stmt = $instance->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error preparing statement: " . $instance->lastError());
            }
            $instance->bindParams($stmt, "si", [$deletedAt, $id]);
            $stmt->execute();
            if ($stmt->error) {
                throw new Exception("Error executing query: " . $stmt->error);
            }
            return $instance->affectedRows($stmt);
        } else {
            $sql = "DELETE FROM {$instance->table} WHERE {$instance->getIdFieldName()} = ?";
            $stmt = $instance->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Error preparing statement: " . $instance->lastError());
            }
            $instance->bindParams($stmt, "i", [$id]);
            $stmt->execute();
            if ($stmt->error) {
                throw new Exception("Error executing query: " . $stmt->error);
            }
            return $instance->affectedRows($stmt);
        }
    }

    public static function join($table, $column)
    {
        $instance = new static();
        self::validateTableName($instance);

        $sql = "SELECT * FROM {$instance->table} INNER JOIN {$table} ON {$instance->table}.{$column} = {$table}.{$column}";
        $result = self::executeQuery($instance, $sql);
        return $instance->fetchAllRows($result);
    }

    public static function count($conditions = null)
    {
        $instance = new static();
        self::validateTableName($instance);

        $sql = "SELECT COUNT(*) as count FROM {$instance->table}";
        $params = [];
        $types = "";

        $uses = class_uses($instance);
        $hasSoftDelete = in_array($instance->getSoftDeleteTraitName(), $uses);

        if ($hasSoftDelete) {
            $sql .= " WHERE {$instance->getDeletedAtFieldName()} IS NULL";
        }

        if ($conditions && is_array($conditions)) {
            $whereClause = $hasSoftDelete ? " AND " : " WHERE ";
            list($conditionsArray, $conditionTypes, $conditionValues) = self::buildWhereClause($conditions, $instance);
            $sql .= $whereClause . implode(" AND ", $conditionsArray);
            $params = $conditionValues;
            $types = $conditionTypes;
        }

        if (!empty($params)) {
            $result = self::executePreparedQuery($instance, $sql, $types, $params);
        } else {
            $result = self::executeQuery($instance, $sql);
        }

        $row = $instance->fetchRow($result);
        return (int) $row['count'];
    }

    public function save()
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'table' && $key !== 'connection' && $key !== 'fillable') {
                $data[$key] = $value;
            }
        }
        return static::update($this->id, $data);
    }
}