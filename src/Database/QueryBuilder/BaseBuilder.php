<?php
namespace Craft\Database\QueryBuilder;

abstract class BaseBuilder {
    /** Table name for the query. */
    protected $table = '';
    /** @var bool Whether to apply soft delete logic. */
    protected $softDelete = false;
    /** @var bool Whether to apply timestamps logic. */
    protected $timestamps = false;
    /** @var bool Whether to include trashed records in the query. */
    protected $withTrash = false;
    /** @var bool Whether to only include trashed records in the query. */
    protected $onlyTrash = false;
    /** @var bool Whether to throw an exception if no results are found. */
    protected $orFail = false;
    /** @var mixed The ID to find in the query. */
    protected $findId = null;
    /** @var string|null The query mode (e.g., 'select', 'insert', 'update', 'delete'). */
    protected $queryMode = null;
    /** @var array Columns to be used in the query. */
    protected $columns = [];
    /** @var string The flag for if_exists in the query. */
    protected $ifExistsFlag = false;
    /** @var string The ON clause for joins. */
    protected $hasOnClause = false;
    /** @var string The WHERE clause for the query. */
    protected $whereClause = '';
    /** @var string The JOIN clause for the query. */
    protected $joinClause = '';
    /** @var string The query string being built. */
    protected $query;

    /**
     * Set the table name for the query.
     *
     * @param string $table The name of the table to query.
     * @return static A new instance of the query builder.
     */
    public static function table($table) {
        $instance = new static();
        $instance->table = $table;
        return $instance;
    }

    /**
     * Base method to select columns from the table.
     * 
     * This method initializes the SELECT query and sets the table name.
     * 
     * @param string $columns The columns to select, defaults to '*'.
     * @param string|null $where Optional WHERE clause to filter results.
     * @return static The current instance of the query builder.
     */
    public function select($columns = '*', $where = null) {
        $this->query = "SELECT {$columns} FROM {$this->table}";
        $this->hasOnClause = false;
        if ($where) {
            $this->where($where);
        }
        return $this;
    }

    /**
     * Base method to insert data into the table.
     * 
     * This method initializes the INSERT query and sets the table name.
     * 
     * @param string|array|null $columns The columns to insert, can be a string or an array.
     * @return static The current instance of the query builder.
     */
    public function insert($columns = null) {
        $this->query = "INSERT INTO {$this->table}";
        $this->hasOnClause = false;
        if (is_array($columns)) {
            if (array_keys($columns) !== range(0, count($columns) - 1)) {
                $columns = implode(',', array_keys($columns));
            } else {
                $columns = implode(',', $columns);
            }
        }
        if ($columns) {
            $this->query .= " ({$columns})";
        }
        return $this;
    }

    /**
     * Base method to update data in the table.
     * 
     * This method initializes the UPDATE query and sets the table name.
     * 
     * @param string|array|null $columns The columns to update, can be a string or an array.
     * @return static The current instance of the query builder.
     */
    public function update($columns = null) {
        $this->query = "UPDATE {$this->table}";
        $this->hasOnClause = false;
        if ($columns) {
            if (is_array($columns)) {
                $columns = implode(',', $columns);
            } else {
                $this->columns = explode(',', $columns);
            }
            $this->query .= " SET {$columns}";
        }
        return $this;
    }

    /**
     * Base method to delete data from the table.
     * 
     * This method initializes the DELETE query and sets the table name.
     * 
     * @param string|null $where Optional WHERE clause to filter results.
     * @param bool $softDelete Whether to apply soft delete logic, defaults to false.
     * @return static The current instance of the query builder.
     */
    public function delete($where = null, bool $softDelete = false) {
        $this->query = "DELETE FROM {$this->table}";
        $this->hasOnClause = false;
        if ($where) {
            $this->where($where);
        }
        return $this;
    }

    /**
     * Base method to add a WHERE clause to the query.
     * 
     * This method allows adding conditions to the query.
     * 
     * @param string|array $columns The columns to filter by, can be a string or an array.
     * @param mixed|null $values The values to filter by, can be a string or null.
     * @param string $operator The operator to use for comparison, defaults to '='.
     * @return static The current instance of the query builder.
     */
    public function where($columns, $values = null, $operator = '=') {
        // Logic escape và thực thi sẽ override ở class con
        if (is_array($columns)) {
            $conditions = [];
            foreach ($columns as $column => $value) {
                $conditions[] = "{$column} {$operator} '" . $value . "'";
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
                        $conditions[] = "{$columns[$i]} {$operator} '" . $values[$i] . "%'";
                    } else {
                        $conditions[] = "{$columns[$i]} {$operator} '" . $values[$i] . "'";
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

    /**
     * Base method to limit the number of results returned by the query.
     * 
     * This method adds a LIMIT clause to the query.
     * 
     * @param int $count The maximum number of results to return, defaults to 1.
     * @return static The current instance of the query builder.
     */
    public function limit(int $count = 1) {
        if ($this->whereClause) {
            $this->whereClause .= " LIMIT {$count}";
        } else {
            $this->query .= " LIMIT {$count}";
        }
        return $this;
    }

    /**
     * Base method to add a JOIN clause to the query.
     * 
     * This method allows joining another table to the query.
     * 
     * @param string $table2 The name of the table to join.
     * @param string $type The type of join (e.g., 'INNER', 'LEFT', 'RIGHT').
     * @param string $on The ON clause for the join, defaults to an empty string.
     * @return static The current instance of the query builder.
     */
    public function join(string $table2, string $type = '', string $on = '') {
        $this->joinClause .= " {$type} JOIN {$table2}";
        if ($on) {
            $this->joinClause .= " ON {$on}";
            $this->hasOnClause = true;
        }
        return $this;
    }

    /**
     * Base method to specify the ON clause for a JOIN.
     * 
     * This method allows specifying the ON condition for a JOIN clause.
     * 
     * @param string $table1Column The column from the first table.
     * @param string $table2Column The column from the second table.
     * @return static The current instance of the query builder.
     */
    public function on(string $table1Column, string $table2Column) {
        if ($this->hasOnClause) {
            return $this;
        }
        $this->joinClause .= " ON {$table1Column} = {$table2Column}";
        $this->hasOnClause = true;
        return $this;
    }

    /**
     * Base method to set data for an INSERT or UPDATE query.
     * 
     * This method allows setting the data to be inserted or updated in the query.
     * 
     * @param mixed ...$data The data to set, can be an array or individual values.
     * @return static The current instance of the query builder.
     */
    public function set(...$data) {
        if (count($data) == 1 && is_array($data[0])) {
            $data = $data[0];
            $setClause = [];
            foreach ($data as $column => $value) {
                $setClause[] = "{$column} = '" . $value . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        } else {
            $setClause = [];
            foreach ($data as $index => $value) {
                $setClause[] = "{$this->columns[$index]} = '" . $value . "'";
            }
            $this->query .= " SET " . implode(", ", $setClause);
        }
        return $this;
    }

    /**
     * Base method to set the query mode (e.g., 'select', 'insert', 'update', 'delete').
     * 
     * This method sets the query mode for the current instance.
     * 
     * @param string $mode The query mode to set.
     * @return static The current instance of the query builder.
     */
    public function if_exists() {
        $this->ifExistsFlag = true;
        return $this;
    }
    
    /** Base method to execute the query. */
    abstract public function execute(bool $debug = false);
}