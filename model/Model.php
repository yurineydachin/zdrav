<?php

abstract class Model {

    const MODEL_DELIMITER     = "|";
    public static $modelName = null;
    public $dir = "data/";

    protected $idField = null;
    protected $fields = array(); // always contains idField

    protected $items  = array();
    protected $errors  = array();

    public function __construct()
    {
        if (!static::$modelName) {
            throw new Exception("modelName is not defined");
        }
        if (!$this->fields) {
            throw new Exception("Fields are not defined");
        }
        if (!$this->idField) {
            throw new Exception("idField is not defined");
        }
        //$this->loadFromFile();
    }

    public function addRow(array $row)
    {
        $newRow = array();
        foreach ($this->fields as $fieldName) {
            if (isset($row[$fieldName])) {
                $newRow[$fieldName] = $row[$fieldName];
            } else {
                $newRow[$fieldName] = null;
            }
        }
        if (!$newRow[$this->idField]) {
            $this->items[] = $newRow;
            $newRow[$this->idField] = end(array_keys($this->items));
        }
        $this->items[$newRow[$this->idField]] = $newRow;
    }

    public function getAllItems()
    {
        return $this->items;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getById($id)
    {
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }
    }

    public function saveToFile()
    {
        $rows = array();
        if (count($this->items) > 0)
        {
            $item = reset($this->items);
            foreach ($this->items as $key => $item) {
                $rows[] = implode(self::MODEL_DELIMITER, $item);
            }
            file_put_contents($this->dir . static::$modelName, implode("\n", $rows));
            return true;
        }
        return false;
    }

    public function loadFromFile()
    {
        $this->items = array();
        if (file_exists($this->dir . static::$modelName)) {
            $rows = file($this->dir . static::$modelName);
            if (count($rows) > 0) {
                foreach ($rows as $row)
                {
                    $values = explode(self::MODEL_DELIMITER, trim($row));
                    $model = array();
                    foreach ($this->fields as $i => $fieldName)
                    {
                        $model[$fieldName] = $values[$i];
                    }
                    $this->items[$model[$this->idField]] = $model;
                }
            } else {
                $this->errors[] = "No rows: ".count($rows);
            }
        } else {
            $this->errors[] = "No file: ". $this->dir . static::$modelName;
        }
        return $this;
    }
}
