<?php
abstract class HandyModel
{
    public $data; // where the data actually lives
    private $originalData; // a private copy of the data for comparison

    // Unique Integer ID Column name; defaults to "id"
    static $uidName = 'id';

    # ---------------------------------------------------------------------------------
    # __construct()
    #   This class is inteded to be extended but not required
    #   If it isn't extended, it needs to have a table name passed
    # ---------------------------------------------------------------------------------
    final public function __construct($dataObject)
    {
        // Store the data from the database in its own variable
        $this->data     = clone $dataObject;
        $this->originalData = clone $dataObject;

        // If an extensionConstruct method exists, call it
        if (method_exists($this, '__extensionConstruct')) {
            $this->__extensionConstruct();
        }
    }


    # ---------------------------------------------------------------------------------
    # save()
    #   automatically saves and column that has changed (other than ID)
    #   - uses $this->originalData for comparison (checking what fields to save)
    # ---------------------------------------------------------------------------------
    final public function save()
    {
        $modelClassName = get_called_class();

        // Localize unique id column name value
        $uidName = $modelClassName::$uidName;

        // Check that there is a table name set
        $thisClassName = get_class($this);
        $tableName = $thisClassName::TABLE_NAME;
        if (empty($tableName)) {
            return false;
        }

        // Make sure there is an ID with which to save
        if (empty($this->data->$uidName)) {
            return false;
        }

        // Calculate whether there have been changes made to the data
        $diff = array_diff_assoc(((array) $this->data), ((array) $this->originalData));

        // If there are changes to be saved, continue
        if (count($diff) > 0) {
            // Start SQL statement
            $sql = "UPDATE `{$tableName}` SET ";

            foreach ($this->data as $col => $value) {
                // If it isn't the ID field
                // (and it is different from what is originally loaded),
                if ($col != $uidName && $value != $this->originalData->$col) {
                    // add it to the query
                    $sql .= "`{$col}`='";
                    $sql .= self::dbInstance()->real_escape_string($value);
                    $sql .= "', ";
                }
            }


            // Trim off the extra ", "
            $sql = rtrim($sql, ', ');

            // Add the where clause for ID
            $sql .= " WHERE `{$uidName}`='{$this->data->$uidName}' LIMIT 1";

            if (self::dbInstance()->query($sql)) {
                return true;
            } else {
                return false;
            }

        // If there aren't changes, return true
        } else {
            return true;
        }
    }

    # ---------------------------------------------------------------------------------
    # delete()
    #   an easy way to delete a model with $item->delete();
    # ---------------------------------------------------------------------------------
    final public function delete()
    {
        $modelClassName = get_called_class();

        // Localize unique id column name value
        $uidName = $modelClassName::$uidName;

        $thisClassName = get_class($this);
        $tableName = $thisClassName::TABLE_NAME;
        if (empty($tableName)) {
            return false;
        }

        // Create the SQL
        $sql = "DELETE FROM `{$tableName}` WHERE `{$uidName}` = '{$this->data->$uidName}' LIMIT 1";

        // Return result
        if (self::dbInstance()->query($sql)) {
            return true;
        } else {
            return false;
        }

    }


    # ---------------------------------------------------------------------------------
    # get()
    #   alias for getting properties in $this->data->$x
    # ---------------------------------------------------------------------------------
    final public function get($propertyName)
    {
        return $this->data->$propertyName;
    }

    # ---------------------------------------------------------------------------------
    # set()
    # ---------------------------------------------------------------------------------
    final public function set($propertyNameOrArray, $value = '')
    {
        // If there is an array passed, set multiple
        if (is_array($propertyNameOrArray)) {
            foreach ($propertyNameOrArray as $name => $value) {
                $this->data->$name = $value;
            }
        // Otherwise, set the single property
        } else {
            $this->data->$propertyNameOrArray = $value;
        }

        return true;

    }

    # ---------------------------------------------------------------------------------
    # setEscaped()
    # ---------------------------------------------------------------------------------
    final public function setEscaped($propertyNameOrArray, $value = '')
    {
        // If there is an array passed, set multiple
        if (is_array($propertyNameOrArray)) {
            foreach ($propertyNameOrArray as $name => $value) {
                $this->data->$name = self::dbInstance()->real_escape_string($value);
            }

        // Otherwise, set the single property
        } else {
            $this->data->$propertyNameOrArray = self::dbInstance()->real_escape_string($value);
        }

        return true;
    }

    # ---------------------------------------------------------------------------------
    # notEmpty()
    #
    # ---------------------------------------------------------------------------------
    public function notEmpty($propertyName)
    {
        return (!empty($this->data->$propertyName));
    }


    ###########################################################################################################
    # Static Methods
    ###########################################################################################################

    # ---------------------------------------------------------------------------------
    # ModelName::lookup()
    #   returns a single "hydrated" model
    # ---------------------------------------------------------------------------------
    public static function lookup($whereQuery = false)
    {
        $modelClassName = get_called_class();

        // Create SQL statement
        if ($whereQuery) {
            $sql = "SELECT * FROM `".$modelClassName::TABLE_NAME."` WHERE {$whereQuery} LIMIT 1";
        } else {
            $sql = "SELECT * FROM `".$modelClassName::TABLE_NAME."` LIMIT 1";
        }

        // Run the query
        $results = self::dbInstance()->query($sql);

        // If there is an object found
        if (isset($results->num_rows) && $results->num_rows > 0) {
            // fetch it
            $item = $results->fetch_object();

            // inject it in the provided model class and RETURN IT
            return new $modelClassName($item);

        // Otherwise, return false
        } else {
            return false;
        }
    }

    # ---------------------------------------------------------------------------------
    # ModelName::cleanValue()
    #   returns a "cleaned" string
    # ---------------------------------------------------------------------------------
    public static function cleanValue($value){
        return self::dbInstance()->real_escape_string($value);
    }


    # ---------------------------------------------------------------------------------
    # ModelName::lookupByID()
    #   returns a "hydrated" model by ID (an alias of sorts)
    # ---------------------------------------------------------------------------------
    public static function lookupByID($id)
    {
        $id = (int) $id;
        $modelClassName = get_called_class();
        $uidName = $modelClassName::$uidName;

        $query = "SELECT * FROM `".$modelClassName::TABLE_NAME."` WHERE `{$uidName}` = ? LIMIT 1";

        if ($stmt = self::dbInstance()->prepare($query)) {
            $stmt->bind_param("s", $id);
            $stmt->execute();

            $result = $stmt->get_result();

            $stmt->close();

            if (isset($result->num_rows) && $result->num_rows > 0) {
                $row = $result->fetch_object();
                return new $modelClassName($row);
            } else {
                return false;
            }
        } else {
            throw new Exception("Failed to prepare statement", 1);
        }
    }

    # ---------------------------------------------------------------------------------
    # ModelName::lookupRandom()
    #   returns a random "hydrated" model
    # ---------------------------------------------------------------------------------
    public static function lookupRandom($whereQuery = false)
    {
        $modelClassName = get_called_class();

        if (!$whereQuery) {
            $whereQuery = '1=1';
        }

        return $modelClassName::lookup("{$whereQuery} ORDER BY RAND()");
    }


    # ---------------------------------------------------------------------------------
    # ModelName::deleteByID()
    #   deletes an item by ID
    # ---------------------------------------------------------------------------------
    public static function deleteByID($id)
    {
        $id = self::dbInstance()->real_escape_string($id);
        $id = (int) $id;

        $modelClassName = get_called_class();
        $uidName = $modelClassName::$uidName;

        // Grab the database handler
        //$db =& self::returnDatabaseHandler();

        // Create SQL statement
        $sql = "SELECT * FROM `".$modelClassName::TABLE_NAME."` WHERE `{$uidName}`='{$id}' LIMIT 1";

        // Run the query
        return self::dbInstance()->query($sql);
    }


    # ---------------------------------------------------------------------------------
    # ModelName::lookupEach()
    #   returns multiple single "hydrated" model
    # ---------------------------------------------------------------------------------
    public static function lookupEach($whereQuery = false)
    {
        $modelClassName = get_called_class();

        // Create SQL statement
        $sql = "SELECT * FROM `".$modelClassName::TABLE_NAME."`";

        // If there is a where statement passed, use it
        if ($whereQuery) {
            // If it is only an order by, don't prepend "WHERE"
            if (strtoupper(substr($whereQuery, 0, 8)) == 'ORDER BY') {
                $sql .= " {$whereQuery}";
            // Otherwise, prepend WHERE
            } else {
                $sql .= " WHERE {$whereQuery}";
            }
        }

        // Run the query
        $results = self::dbInstance()->query($sql);

        // If there is an object found
        if (isset($results->num_rows) && $results->num_rows > 0) {
            $itemsToReturn = array();

            // fetch all
            while ($item = $results->fetch_object()) {
                $uidName = $modelClassName::$uidName;
                // add to array and inject it in the provided model class
                $itemsToReturn[$item->$uidName] = new $modelClassName($item);
            }

            return $itemsToReturn;

        // Otherwise, return false
        } else {
            return array();
        }
    }


    # ---------------------------------------------------------------------------------
    # ModelName::count()
    #
    # ---------------------------------------------------------------------------------
    public static function count($whereQuery = false)
    {
        $modelClassName = get_called_class();

        // Create SQL statement
        $sql = "SELECT COUNT(*) as `count` FROM `".$modelClassName::TABLE_NAME."`";

        // If there is a where statement, include it
        if ($whereQuery) {
            $sql .= " WHERE {$whereQuery}";
        }

        // Run the query
        $results = self::dbInstance()->query($sql);

        if (isset($results->num_rows) && $results->num_rows > 0) {
            $result = $results->fetch_object();

            if (isset($result->count)) {
                return $result->count;
            // Otherwise, return false
            } else {
                return false;
            }

        // Otherwise, return false
        } else {
            return false;
        }
    }

    /**
     * Determine value type for mysqli prepared statement
     * Types: s = string, i = integer, d = double,  b = blob
     *
     * @method getBindType
     * @see http://php.net/manual/en/mysqli-stmt.bind-param.php
     * @param  Mixed      $value
     * @return String
     */
    public static function getBindType($value) {
      if(is_int($value)) return 'i';
      if(is_double($value)) return 'd';
      if(strlen($value) > 255) return 'b';
      if(is_string($value)) return 's';
    }

    /**
     * Create a new item in the database and return a single model instance
     * @method create
     * @param  [type] $props [description]
     * @return [type]        [description]
     */
    public static function create($props)
    {
        $modelClassName = get_called_class();
        $values = [];
        $paramTypes = '';

        // Build dynamic query
        $sql = "INSERT INTO `".$modelClassName::TABLE_NAME."` SET ";
        foreach ($props as $col => $value) {
            if ($col != $modelClassName::$uidName) {
                $sql .= "`{$col}`= ?, ";
                $paramTypes .= self::getBindType($value);
                $values[] = $value;
            }
        }
        $sql = rtrim($sql, ', ');

        // Prepare statment
        if ($stmt = self::dbInstance()->prepare($sql)) {
            $stmt->bind_param($paramTypes, ...$values);
            $stmt->execute();

            $newItem = null;

            if (isset($stmt->insert_id)) {
                try {
                    $newItem = self::lookupByID($stmt->insert_id);
                    if (method_exists($newItem, '__postCreate')) {
                        $newItem->__postCreate();
                    }
                } catch (Exception $e) {
                    error_log($e);
                }
            }

            $stmt->close();

            if ($newItem != null) {
                return $newItem;
            } else {
                return false;
            }
        } else {
            throw new Exception("Failed to prepare statement", 1);
        }
    }

    # ---------------------------------------------------------------------------------
    # dbInstance
    #   returns the reference of the proper database (model-specific or default)
    # ---------------------------------------------------------------------------------
    protected static function &dbInstance()
    {
        $className = get_called_class();

        if (isset(Handy::$db[$className])) {
            return Handy::$db[$className];
        } else {
            return Handy::$defaultDB;
        }
    }
}
