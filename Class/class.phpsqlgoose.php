<?php
/**
 * Procedure:
 * - First, establish a connection to the database by declaring a class 'Connection'
 * - Create a schema with data by declaring a class 'Schema'
 * - Create a model by declaring the class 'Model'
 * - Access the model to interact with a table in the database
 *
 * @name Model
 * @version 1.0.0
 * @author masloff (irtex)
 * @copyright 2021 iRTEX
 */

namespace phpgoose;

use ErrorException;
use Exception;
use mysqli;
use Wrapper\Time;
use function Engine\bind;
use function Engine\sql_escape_string;
use function Helpers\sanitize_name;
use function mysqli_connect;
use function mysqli_error;

define("TYPE_ANY", 'any');
define("TYPE_STRING", 'string');
define("TYPE_VARCHAR", 'varchar');
define("TYPE_CHAR", 'char');
define("TYPE_TEXT", 'text');
define("TYPE_MEDIUMTEXT", 'mediumtext');
define("TYPE_LONGTEXT", 'longtext');
define("TYPE_INT", 'int');
define("TYPE_SMALLINT", 'smallint');
define("TYPE_BIGINT", 'bigint');
define("TYPE_DOUBLE", 'double');
define("TYPE_BOOLEAN", 'boolean');
define("TYPE_TIME", 'time');
define("TYPE_DATE", 'date');
define("TYPE_DATETIME", 'datetime');
define("TYPE_TIMESTAMP", 'timestamp');
define("TYPE_PASSWORD", 'password');
define("TYPE_URL", 'url');
define("TYPE_OBJECT", 'json');
define("TYPE_BINARY", 'binary');
define("TYPE_BLOB", 'blob');
define("TYPE_VARBINARY", 'varbinary');
define("TYPE_MEDIUMBLOB", 'mediumblob');
define("TYPE_LONGBLOB", 'longblob');
define("TYPE_BLOB", 'blob');
define("TYPE_POINT", 'point');
define("TYPE_LINESTRING", 'linestring');
define("TYPE_POLYGON", 'polygon');
define("TYPE_GEOMETRY", 'geometry');
define("IS_NOT_TYPE", 'This data type is not supported within an array. Please request data from this cell using the special method');

/**
 * A global variable that contains the class of the database connection 'Connection'
 */
global $phpgoose_connection;

$phpgoose_connection = false;

/**
 * Error in SQL query processing
 * @package phpgoose
 */
class SQLException extends Exception
{
}

/**
 * Creates a global connection to MySQL for further work with phpgoose models.
 * @package phpgoose
 */
class Connection
{

    const PROVIDER_MYSQL = "mysql";
    const PROVIDER_MYSQLI = "mysqli";
    const LOCALHOST = '127.0.0.1';

    private $connection = false;
    private $provider = false;
    private $db = false;
    private $db_selected = false;
    private $charset = 'utf8';
    private $host = "localhost";
    private $username = "";
    private $password = "";
    private $port = "";

    /**
     * Creates a global connection to MySQL for further work with phpgoose models.
     *
     * @param string $hostname Server name string (example: localhost or 127.0.0.1)
     * @param string $username MYSQL username to connect to the server
     * @param string $password MYSQL password to connect to the server
     * @param string $db_name Database name for connecting to MYSQL
     * @param number|boolean $port MYSQL port to connect to the server (default: standard mysql port)
     * @param string $charset MYSQL database encoding (default: utf8)
     * @param string $new_link Database connection name (default: main)
     * @param array|boolean $client_flags Optional flags to connect to the database (default: false)
     * @throws SQLException
     * @throws ErrorException
     * @author masloff (irtex)
     * @copyright 2021 iRTEX
     */
    public function __construct(string $hostname, string $username, string $password, string $db_name, $port = false, string $charset = 'utf8', string $new_link = "main", $client_flags = false)
    {
        global $phpgoose_connection;

        if (version_compare(PHP_VERSION, '4.3.0') < 0) {
            $PHP_VERSION = PHP_VERSION;
            throw new ErrorException("You are using too old version of PHP ($PHP_VERSION). Version 4.3.0 is required.");
        }

        $this->charset = strval($charset);
        $this->db = sanitize_name($db_name);
        $this->host = strval($hostname);
        $this->username = strval($username);
        $this->password = strval($password);

        if (is_int($port)) {
            $host = strval("$hostname:$port");
            $this->password = intval($port);
        } else {
            $host = strval($hostname);
            $this->password = false;
        }

        if (function_exists("mysql_connect")) {
            $this->connection = mysql_connect($host, $username, $password, $new_link, $client_flags);
            $this->provider = Connection::PROVIDER_MYSQL;

            if (!$this->connection) {
                throw new SQLException(mysql_error());
            }
        } elseif (function_exists("mysqli_connect")) {
            $this->connection = mysqli_connect($host, $username, $password);
            $this->provider = Connection::PROVIDER_MYSQLI;

            if (mysqli_connect_errno()) {
                throw new SQLException(mysqli_connect_error());
            }
        } else {
            throw new ErrorException("Unable to install an ISP because none of the functions to connect to SQL are found");
        }

        if ($this->connection) {
            $query = bind('CREATE DATABASE $bind', [
                '$bind' => $this->db
            ]);

            if ($this->provider == Connection::PROVIDER_MYSQL) {
                $phpgoose_connection = $this;
                mysql_set_charset($this->charset);
                $this->db_selected = mysql_select_db($this->db, $this->connection);
            } elseif ($this->provider == Connection::PROVIDER_MYSQLI) {
                $phpgoose_connection = $this;
                mysqli_set_charset($this->connection, $this->charset);
                $this->db_selected = $this->connection->select_db($this->db);
            } else {
                throw new ErrorException("Provider not specified ");
            }

            if ($this->db_selected == false) {
                if ($this->provider == Connection::PROVIDER_MYSQL) {
                    mysql_query($query, $phpgoose_connection->get_connection());

                } elseif ($this->provider == Connection::PROVIDER_MYSQLI) {
                    if (!$this->connection->query($query, MYSQLI_USE_RESULT)) {
                        throw new SQLException(mysqli_error($this->connection));
                    }
                }
            }
        }
    }


    /**
     * Get a direct link to the SQL connection
     *
     * @return mysqli|false Direct connection to mysql
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function get_connection()
    {
        return $this->connection;
    }


    /**
     * Get the name of the connection provider
     *
     * @return string Provider name in lowercase
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function get_provider()
    {
        return $this->provider;
    }

    /**
     * @return array|false
     */
    public function __debugInfo()
    {
        if ($this->connection instanceof mysqli) {
            return $this->connection->get_connection_stats();
        }
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return array(
            'db' => $this->db,
            'host' => $this->host,
            'username' => $this->username,
            'password' => $this->password,
            'port' => $this->port,
            'charset' => $this->charset
        );
    }

    /**
     * @param array $data
     * @throws ErrorException
     * @throws SQLException
     */
    public function __unserialize(array $data)
    {
        $this->charset = strval($data['charset']);
        $this->db = sanitize_name($data['db']);
        $this->host = strval($data['host']);
        $this->username = strval($data['username']);
        $this->password = strval($data['password']);

        $this->__construct($this->host, $this->username, $this->password, $this->db, $this->port, $this->charset);
    }

}

/**
 * Data schema for creating the phpgoose model. It is used to declare fields in a table and their configurations: field type, default value, uniqueness, etc.
 * @package phpgoose
 */
class Schema
{

    const NOT_DEFINED = 'not-defined';

    private $fluids = array();
    private $data_types = [
        TYPE_VARBINARY,
        TYPE_BLOB,
        TYPE_MEDIUMBLOB,
        TYPE_POINT,
        TYPE_LINESTRING,
        TYPE_POLYGON,
        TYPE_GEOMETRY,
        TYPE_BINARY,
        TYPE_LONGTEXT,
        TYPE_MEDIUMTEXT,
        TYPE_STRING,
        TYPE_CHAR,
        TYPE_INT,
        TYPE_BIGINT,
        TYPE_SMALLINT,
        TYPE_BOOLEAN,
        TYPE_TIME,
        TYPE_DATE,
        TYPE_ANY,
        TYPE_DOUBLE,
        TYPE_TEXT,
        TYPE_VARCHAR,
        TYPE_PASSWORD,
        TYPE_URL,
        TYPE_OBJECT,
        TYPE_DATETIME,
        TYPE_TIMESTAMP
    ];

    /**
     * Data schema for creating the phpgoose model. It is used to declare fields in a table and their configurations: field type, default value, uniqueness, etc.
     *
     * @param array $fluids An array of database table fields
     * @throws ErrorException
     * @copyright 2021 iRTEX
     * @author masloff (irtex)
     */
    public function __construct(array $fluids)
    {
        if (version_compare(PHP_VERSION, '4.3.0') < 0) {
            $PHP_VERSION = PHP_VERSION;
            throw new ErrorException("You are using too old version of PHP ($PHP_VERSION). Version 4.3.0 is required.");
        }

        foreach ($fluids as $key => $value) {
            $no_size_types = [
                TYPE_BOOLEAN,
                TYPE_TIME,
                TYPE_DATE,
                TYPE_OBJECT,
                TYPE_DATETIME,
                TYPE_TIMESTAMP
            ];

            if (!is_array($value) and !in_array($value, $this->data_types)) {
                throw new ErrorException("Invalid data type: $value");
            } elseif (is_array($value) and !key_exists("type", $value)) {
                throw new ErrorException("The obligatory parameter type was not passed in $key");
            } elseif (is_array($value) and key_exists("type", $value) and !in_array($value["type"], $this->data_types)) {
                throw new ErrorException("Invalid data type: $value");
            }

            if (!is_array($value)) {
                $value = [
                    'type' => $value,
                    'default' => Schema::NOT_DEFINED,
                    'size' => !in_array($value, $no_size_types) ? 255 : false,
                    'not_null' => false,
                    'primary' => false,
                    'optional' => []
                ];
            }

            if (in_array($value["type"], $no_size_types) and key_exists("LEN", $value) and $value['LEN'] != false) {
                throw new ErrorException("Data type {$value["type"]} does not support LEN");
            }

            $this->fluids[$key] = (object)array(
                'type' => $value["type"],
                'default' => key_exists("default", $value) ? $value["default"] : false,
                'size' => !in_array($value["type"], $no_size_types) ? (key_exists("LEN", $value) ? (int)$value["LEN"] : 255) : false,
                'not_null' => key_exists("not_null", $value) ? (boolean)boolval($value["not_null"]) : false,
                'primary' => key_exists("primary", $value) ? (boolean)boolval($value["primary"]) : false,
                'optional' => []
            );
        }
    }


    /**
     * Get a list of all fields in the database
     *
     * @return array List of all database fields
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function get_keys()
    {
        return array_keys($this->fluids);
    }


    /**
     * Get all the fields in the schematic
     *
     * @return array Scheme
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function get_fluids()
    {
        return $this->fluids;
    }


    /**
     * Get the default value for a field in the schema by its key
     *
     * @param string $key Field in the scheme
     * @return mixin Get the default value
     * @author masloff (irtex)
     * @copyright 2021 iRTEX
     *
     */
    public function get_default(string $key)
    {
        if (is_array($this->fluids) and key_exists($key, $this->fluids) and key_exists("default", $this->fluids[$key])) {
            return $this->fluids[$key]->default;
        }

        return Schema::NOT_DEFINED;
    }


    /**
     * Get fluid type by its key
     *
     * @param string $key Field in the scheme
     * @return string Get the type
     * @author masloff (irtex)
     * @copyright 2021 iRTEX
     *
     */
    public function get_type(string $key)
    {
        if (is_array($this->fluids) and key_exists($key, $this->fluids) and key_exists("type", $this->fluids[$key])) {
            return $this->fluids[$key]->type;
        }

        return false;
    }


    /**
     * Verify the data with the field data type
     *
     * @param string $key Field in the scheme
     * @param string $value Value
     * @return boolean Is the data type correct?
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function validate_type(string $key, $value)
    {
        if (is_array($this->fluids) and key_exists($key, $this->fluids) and key_exists("default", $this->fluids[$key])) {
            if (
                $this->fluids[$key]->type == TYPE_STRING or
                $this->fluids[$key]->type == TYPE_TEXT or
                $this->fluids[$key]->type == TYPE_PASSWORD or
                $this->fluids[$key]->type == TYPE_VARCHAR
            ) {
                if (!is_string($value)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type string, but receives a $type");
                }
            } elseif (
                $this->fluids[$key]->type == TYPE_INT or
                $this->fluids[$key]->type == TYPE_SMALLINT or
                $this->fluids[$key]->type == TYPE_BIGINT
            ) {
                if (!is_numeric($value)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type int, but receives a $type");
                }
            } elseif (
                $this->fluids[$key]->type == TYPE_DOUBLE
            ) {
                if (!is_double($value)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type double, but receives a $type");
                }
            } elseif (
                $this->fluids[$key]->type == TYPE_TIME or
                $this->fluids[$key]->type == TYPE_DATE or
                $this->fluids[$key]->type == TYPE_DATETIME or
                $this->fluids[$key]->type == TYPE_TIMESTAMP
            ) {
                if (!($value instanceof Time)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type Time, but receives a $type");
                }
            } elseif (
                $this->fluids[$key]->type == TYPE_POINT
            ) {
                if (!($value instanceof Point)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type Point, but receives a $type");
                }
            } elseif (
                $this->fluids[$key]->type == TYPE_OBJECT
            ) {
                if (!is_array($value) and !is_object($value)) {
                    $type = gettype($value);
                    throw new \TypeError("The $key key expects data of type object or array, but receives a $type");
                }
            } else {
                return false;
            }
        }

        return Schema::NOT_DEFINED;
    }


    /**
     * Process a specific data type and return the result
     *
     * @param string $key Field in the scheme
     * @param string $value Value
     * @return boolean Is the data type correct?
     * @copyright 2021 iRTEX
     *
     * @author masloff (irtex)
     */
    public function parse_specific_types(string $key, $value)
    {
        if (is_array($this->fluids) and key_exists($key, $this->fluids) and key_exists("default", $this->fluids[$key])) {
            switch ($this->fluids[$key]->type) {
                case TYPE_PASSWORD:
                    return md5($value);
                case TYPE_OBJECT:
                    return json_encode($value);
                default:
                    return $value;
            }
        }

        return $value;
    }


    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->fluids[$name];
    }

    /**
     * @param string $name
     * @param $value
     */
    public function __set(string $name, $value)
    {
        $this->fluids[$name] = $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name)
    {
        return isset($this->fluids[$name]);
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return $this->fluids;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return array(
            'fluids' => $this->fluids,
            'data_types' => $this->data_types
        );
    }

    /**
     * @param string $name
     */
    public function __unset(string $name)
    {
        unset($this->fluids[$name]);
    }

    /**
     * @return false|string
     */
    public function __toString()
    {
        return json_encode($this->fluids);
    }

    /**
     * @return $this
     */
    public function __clone()
    {
        return $this;
    }

    /**
     * @param array $data
     */
    public function __unserialize(array $data)
    {
        $this->fluids = $data['fluids'];
        $this->data_types = $data['data_types'];
    }


}

# TODO: Add migrate model
# TODO: Add validate, regex type in fluid scheme
# TODO: find by id


/**
 * Creates a model by implementing a schema for a specific table
 * @package phpgoose
 */
class Model
{

    private $excerpts = array();
    private $schema = false;
    private $name = false;
    private $limit = false;
    private $order = false;
    private $sort = false;
    private $select = false;
    private $offset = false;
    private $if_exists = false;
    private $mod = false;
    private $sql_query = false;
    private $actions = array();
    private $data = array();

    /**
     * Creates a model by implementing a schema for a specific table
     *
     * @param string $name Name of the table in which the scheme will be implemented
     * @param Schema $schema Table schema
     * @throws ErrorException
     * @throws SQLException
     * @author masloff (irtex)
     * @copyright 2021 iRTEX
     */
    public function __construct(string $name, Schema $schema)
    {
        if (version_compare(PHP_VERSION, '4.3.0') < 0) {
            $PHP_VERSION = PHP_VERSION;
            throw new ErrorException("You are using too old version of PHP ($PHP_VERSION). Version 4.3.0 is required.");
        }

        global $phpgoose_connection;

        if ($phpgoose_connection instanceof Connection) {

            if ($schema instanceof Schema) {
                $this->schema = $schema;
                $this->name = sanitize_name($name);

                $query = "CREATE TABLE IF NOT EXISTS `{$this->name}` (";
                $query_list = array();

                foreach ($this->schema->get_fluids() as $key => $value) {
                    $size = $value->size != false ? "({$value->size})" : '';

                    switch ($value->type) {
                        case 'int':
                            $type = 'SMALLINT';
                            break;
                        case 'url':
                        case 'password':
                        case 'string':
                            $type = 'VARCHAR';
                            break;
                        case 'boolean':
                            $type = 'BIT';
                            break;
                        default:
                            $type = strtoupper($value->type);
                    }
                    $not_null = boolval($value->not_null) ? " NOT NULL " : " ";
                    $primary = boolval($value->primary) ? "PRIMARY KEY" : " ";
                    $optional = empty($value->optional) ? "" : implode(" ", $value->optional);

                    array_push($query_list, "`{$key}` {$type}{$size} {$optional}{$not_null}{$primary}");
                }

                $query .= implode(",", $query_list);
                $query .= ")";

                if ($phpgoose_connection->get_provider() == Connection::PROVIDER_MYSQL) {
                    mysql_query($query, $phpgoose_connection->get_connection());

                    if (mysql_error($phpgoose_connection->get_connection())) {
                        throw new SQLException(mysql_error($phpgoose_connection->get_connection()));
                    }
                } elseif ($phpgoose_connection->get_provider() == Connection::PROVIDER_MYSQLI) {
                    $phpgoose_connection->get_connection()->query($query);

                    if (mysqli_error($phpgoose_connection->get_connection())) {
                        throw new SQLException(mysqli_error($phpgoose_connection->get_connection()));
                    } else {
                        return true;
                    }
                }
            } else {
                throw new ErrorException("The data for the model is of the wrong type");
            }

        } else {
            throw new ErrorException("Before you create a model, establish a connection to the database with the Connection class");
        }

    }


    /**
     * When you create an SQL record via the invoke method,
     * the function will handle errors and return False instead of the standard PHP errors.
     * If you need to handle errors manually use the insert method
     *
     * @param $data
     * @return $this|bool
     */
    public function __invoke($data)
    {
        try {
            return $this->insert_one($data);
        } catch (ErrorException $e) {
            return false;
        } catch (SQLException $e) {
            return false;
        }
    }


    /**
     * Insert SQL record into the table
     *
     * @param $data SQL query as an array
     * @return true
     * @throws ErrorException
     * @throws SQLException
     */
    public function insert_one(array $data)
    {
        foreach (array_diff($this->schema->get_keys(), array_keys($data)) as $key) {
            if ($this->schema->get_default($key) === Schema::NOT_DEFINED) {
                throw new ErrorException("The key $key does not contain a default value and is not passed to the function when the record is created");
            } else {
                $value = $this->schema->get_default($key);
                $value = $this->schema->parse_specific_types($key, $value);
                $value = $this->value($value);
                $data[$key] = $value;
            }
        }

        foreach ($data as $key => $value) {
            $result = $this->action('pre_save', [
                $key,
                $value
            ]);

            if (!empty($result)) {
                $data[$key] = $result;
            }

            $data[$key] = $this->schema->parse_specific_types($key, $data[$key]);

            if (!in_array($key, Operator::OPERATORS) and !isset($this->schema->{$key})) {
                throw new ErrorException("Key $key not found in schema");
            }

            if (!$this->schema->validate_type($key, $data[$key])) {
                return new ErrorException('Transmitted data type does not match the data type specified in the schema');
            }

        }

        $keys = implode(", ", array_map(function ($e) { return sql_escape_string($e); }, array_keys($data)));

        $values = implode(", ", array_map(function ($e) {
            if (is_int($e) or is_numeric($e) or is_float($e) or is_double($e)) {
                return bind("%s", [
                    '%s' => sql_escape_string($e)
                ]);
            } elseif (is_string($e)) {
                return bind("'%s'", [
                    '%s' => sql_escape_string($e)
                ]);
            } elseif ($e instanceof Time) {
                return bind("'%s'", [
                    '%s' => sql_escape_string($e->get_timestamp())
                ]);
            } elseif (is_callable($e)) {
                return bind("%s", [
                    '%s' => sql_escape_string($e())
                ]);
            } elseif (is_array($e) or is_object($e)) {
                return bind("'%s'", [
                    '%s' => sql_escape_string(json_encode($e))
                ]);
            }
        }, array_values($data)));

        $this->sql_query = bind('INSERT INTO $name ($keys) VALUES ($values);', array(
            '$name' => $this->name,
            '$keys' => $keys,
            '$values' => [
                'value' => $values,
                'escape' => false
            ]
        ));

        return $this->execute_query($this->sql_query);
    }


    /**
     * Data for inserting a plural object into the database must be of the array type
     * 
     * @param array $data SQL records
     * @return ErrorException
     * @throws ErrorException
     * @throws SQLException
     */
    public function insert_many(array $data) {
        $records = [];
        
        foreach ($data as $record) {
            if (is_array($record)) {
                array_push($records, $this->insert_one($record));
            } else {
                return new ErrorException('');
            }
        }

        return $records;
    }


    /**
     * Selects records in a collection or view and returns a array selected records.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @return array SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function find(array $query = [])
    {
        $this->sql_query = bind('SELECT $select $mod FROM $name $where $limit $offset $order $sort;', array(
            '$name' => $this->name,
            '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
            '$mod' => $this->mod ? ", MOD({$this->mod[0]}, {$this->mod[1]})" : '',
            '$select' => $this->select ? $this->select : '*',
            '$where' => empty($query) ? "" : "WHERE " . $this->prepare_while($query),
            '$order' => [
                'value' => $this->order ? "ORDER BY {$this->order}" : "",
                'escape' => false
            ],
            '$sort' => [
                'value' => $this->sort ? strtoupper($this->sort) : "",
                'escape' => false
            ],
            '$limit' => [
                'value' => is_numeric($this->limit) ? "LIMIT {$this->limit}" : "",
                'escape' => false
            ]
        ));

        return $this->execute_query($this->sql_query);
    }


    /**
     * Returns a single record that satisfies the given query criteria on the collection. If the query satisfies multiple documents, this method returns the last document according to the natural order. If no documents satisfy the query, the method returns null.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @return null|object SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function find_one(array $query = [])
    {
        $results = $this->limit(1)->find($query);

        if ($results) {
            return (object) end($results);
        } else {
            return null;
        }
    }


    /**
     * Returns the number of records that match the find() query for the collection.
     * The count() method does not perform the find() operation,
     * but instead counts and returns the number of results that match the query.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @return int|null Count orders
     * @throws ErrorException
     * @throws SQLException
     */
    public function count(array $query = [])
    {
        $this->sql_query = bind('SELECT COUNT($select) FROM $name $where $limit $offset $order $sort;', array(
            '$name' => $this->name,
            '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
            '$select' => $this->select ? $this->select : '*',
            '$where' => empty($query) ? "" : "WHERE " . $this->prepare_while($query),
            '$order' => [
                'value' => $this->order ? "ORDER BY {$this->order}" : "",
                'escape' => false
            ],
            '$sort' => [
                'value' => $this->sort ? strtoupper($this->sort) : "",
                'escape' => false
            ],
            '$limit' => [
                'value' => is_numeric($this->limit) ? "LIMIT {$this->limit}" : "",
                'escape' => false
            ]
        ));

        $data = $this->execute_query($this->sql_query);

        if (is_array($data)) {
            return array_values(end($data))[0];
        } else {
            return null;
        }
    }


    /**
     * Removes all records that match the filter from a collection.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @return array SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function delete_many(array $query = [])
    {
        $this->sql_query = bind('DELETE FROM $name $where $limit $offset $order $sort;', array(
            '$name' => $this->name,
            '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
            '$where' => empty($query) ? "" : "WHERE " . $this->prepare_while($query),
            '$order' => [
                'value' => $this->order ? "ORDER BY {$this->order}" : "",
                'escape' => false
            ],
            '$sort' => [
                'value' => $this->sort ? strtoupper($this->sort) : "",
                'escape' => false
            ],
            '$limit' => [
                'value' => is_numeric($this->limit) ? "LIMIT {$this->limit}" : "",
                'escape' => false
            ]
        ));

       return $this->execute_query($this->sql_query);
    }


    /**
     * Removes a single record from a collection.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @return array SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function delete_one(array $query)
    {
        return $this->limit(1)->delete_many($query);
    }


    /**
     * Changes an existing record or records in the collection.
     * The method can change specific fields of an existing record or records or
     * completely replace an existing record, depending on the update parameter.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @param array $update_data The modifications to apply.
     * @return bool|ErrorException|array SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function update_many(array $query = [], array $update_data = [])
    {

        $this->sql_query = bind('UPDATE $name SET $update $where $limit $offset $order $sort;', array(
            '$name' => $this->name,
            '$update' => [
                'value' => empty($update_data) ? "" : $this->prepare_update($update_data),
                'escape' => false
            ],
            '$select' => $this->select ? $this->select : '*',
            '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
            '$where' => empty($query) ? "" : "WHERE " . $this->prepare_while($query),
            '$order' => [
                'value' => $this->order ? "ORDER BY {$this->order}" : "",
                'escape' => false
            ],
            '$sort' => [
                'value' => $this->sort ? strtoupper($this->sort) : "",
                'escape' => false
            ],
            '$limit' => [
                'value' => is_numeric($this->limit) ? "LIMIT {$this->limit}" : "",
                'escape' => false
            ]
        ));

        return $this->execute_query($this->sql_query);

    }


    /**
     * Updates a single record within the collection based on the filter.
     *
     * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
     * @param array $update_data The modifications to apply.
     * @return array|bool|ErrorException SQL records
     * @throws ErrorException
     * @throws SQLException
     */
    public function update_one(array $query = [], array $update_data = [])
    {
        return $this->limit(1)->update_many($query, $update_data);
    }


    /**
     * Removes database.
     *
     * @return array
     * @throws ErrorException
     * @throws SQLException
     */
    public function drop_database()
    {

        $this->sql_query = bind('DROP DATABASE $if_exists $name;', array(
            '$name' => $this->name,
            '$if_exists' => [
                'value' => $this->if_exists ? "IF EXISTS" : '',
                'escape' => false
            ],
        ));

        return $this->execute_query($this->sql_query);

    }


    /**
     * Removes a table from the database.
     *
     * @return array
     * @throws ErrorException
     * @throws SQLException
     */
    public function drop_table()
    {

        $this->sql_query = bind('DROP TABLE $if_exists $name;', array(
            '$name' => $this->name,
            '$if_exists' => [
                'value' => $this->if_exists ? "IF EXISTS" : '',
                'escape' => false
            ],
        ));

        return $this->execute_query($this->sql_query);

    }


    /**
     * Get data type from MySQL
     *
     * @param string $column_name
     * @return bool|mixed
     * @throws ErrorException
     * @throws SQLException
     */
    public function get_column_type(string $column_name)
    {

        $this->sql_query = bind('SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = \'$name\' AND COLUMN_NAME = \'$column_name\'', array(
            '$name' => $this->name,
            '$column_name' => $column_name,
        ));

        $data = $this->execute_query($this->sql_query);

        if (is_array($data)) {
            return array_values(end($data))[0];
        } else {
            return false;
        }

    }


    /**
     * Rename the column
     *
     * @param string $column_name
     * @param string $new_column_name
     * @return bool|mixed
     * @throws ErrorException
     * @throws SQLException
     */
    public function rename_column(string $column_name, string $new_column_name)
    {
        $data_type = $this->get_column_type($column_name);

        if ($data_type) {

            $this->sql_query = bind('ALTER TABLE $name CHANGE COLUMN $column_name $new_column_name $data_type;', array(
                '$name' => $this->name,
                '$column_name' => $column_name,
                '$new_column_name' => $new_column_name,
                '$data_type' => $data_type
            ));

            return $this->execute_query($this->sql_query);

        } else {
            return false;
        }

    }


    /**
     * Selects a subset of an array to return based on the specified condition.
     * Returns an array with only those elements that match the condition. The returned elements are in the original order.
     *
     * @param string|array $select
     * @return $this
     */
    public function select($select)
    {
        if (is_array($select) or is_object($select)) {
            foreach ($select as $key) {
                if (!in_array($key, Operator::OPERATORS) and !isset($this->schema->{$key})) {
                    throw new ErrorException("Key $key not found in schema");
                }
            }
            $this->select = implode(", ", $select);
        } else {
            $this->select = (string)$select;
        }

        return $this;
    }


    /**
     * @param string $by
     * @return $this
     */
    public function order(string $by)
    {
        $this->order = $by;
        return $this;
    }


    /**
     * Sorts all input records and returns them to the pipeline in sorted order.
     * @param string $sort
     * @return $this
     * @throws ErrorException
     */
    public function sort(string $sort)
    {
        switch ($sort) {
            case 'Z...A':
            case '9-0':
            case 'DESC':
                $this->sort = 'DESC';
                break;
            case 'A...Z':
            case '0-9':
            case 'ASC':
                $this->sort = 'ASC';
                break;
            default:
                throw new ErrorException("Wrong sorting type");
        }

        return $this;
    }


    /**
     * Limits the number of records passed to the next stage in the pipeline.
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit)
    {
        $this->limit = intval($limit);
        return $this;
    }


    /**
     * Skips over the specified number of records pass into the stage and passes the remaining documents to the next stage in the pipeline.
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset)
    {
        $this->offset = intval($offset);
        return $this;
    }


    /**
     * Divides one number by another and returns the remainder.
     *
     * @param $int_1 Number or field in the scheme
     * @param $int_2 Number or field in the scheme
     * @return $this
     */
    public function mod($int_1, $int_2)
    {
        foreach ([$int_1, $int_2] as $item) {
            if (is_string($item)) {
                if (!in_array($item, Operator::OPERATORS) and !isset($this->schema->{$item})) {
                    throw new ErrorException("Key $item not found in schema");
                }
            } elseif (!is_numeric($item) and !is_int($item) and !is_float($item) and !is_double($item)) {
                throw new ErrorException("The parameter of the function must be the field name of the schema or a number");
            }
        }

        $this->mod = [$int_1, $int_2];
        return $this;
    }


    public function if_exists(bool $boolean = true) {
        $this->if_exists = $boolean;
        return $this;
    }

    private function action($action, $data)
    {
        if (array_key_exists($action, $this->actions)) {
            return $this->actions[$action]($data);
        }
    }

    public function on($action, $callback)
    {
        $this->actions[$action] = $callback;
        return $this;
    }

    private function prepare_while(array $query)
    {
        $blocks = [];

        if ($query) {
            foreach ($query as $key => $value) {
                if (in_array($key, Operator::OPERATORS)) {
                    $expression = [];

                    foreach ($value as $name => $value) {
                        if (!in_array($name, Operator::OPERATORS) and !in_array($name, $this->schema->get_keys())) {
                            throw new ErrorException("Key $name not found in schema");
                        }

                        if (!is_array($value)) {
                            $validate = $this->schema->validate_type($name, $value);
                            $type = gettype($value);
                            $fluid_type = $this->schema->get_fluids()[$name]->type;

                            if (!$validate) {
                                throw new ErrorException("Key $name received data of type $type, but is waiting for type $fluid_type");
                            }

                            $value = $this->value($value);

                            array_push($expression, "$name = $value");
                        } else {
                            array_push($expression, implode(" AND ", $this->prepare_operators($name, $value)));
                        }
                    }

                    if ($key == Operator:: AND) {
                        array_push($blocks, implode(" AND ", $expression));
                    } elseif ($key == Operator:: OR) {
                        array_push($blocks, implode(" OR ", $expression));
                    } else {
                        throw new ErrorException("The $key operator cannot be used as a top-level operator");
                    }
                } else {
                    if (!in_array($key, $this->schema->get_keys())) {
                        throw new ErrorException("Key $key not found in schema");
                    } elseif (!is_array($value)) {
                        $validate = $this->schema->validate_type($key, $value);
                        $type = gettype($value);
                        $fluid_type = $this->schema->get_fluids()[$key]->type;

                        if (!$validate) {
                            throw new ErrorException("Key $key received data of type $type, but is waiting for type $fluid_type");
                        }
                    }

                    if (is_array($value)) {
                        $expression = $this->prepare_operators($key, $value);

                        array_push($blocks, implode(" AND ", $expression));
                    } else {
                        $value = $this->schema->parse_specific_types($key, $value);
                        $value = $this->value($value);

                        array_push($blocks, "($key = $value)");
                    }
                }
            }
        }

        if ($query) {
            return implode(" AND ", $blocks);
        }

        return false;
    }

    private function prepare_update(array $query) {
        $update = array();
        
        foreach ($query as $key => $value) {
            $result = $this->action('pre_save', [
                $key,
                $value
            ]);

            if (!empty($result)) {
                $value = $result;
            }

            if (!in_array($key, Operator::OPERATORS) and !isset($this->schema->{$key})) {
                throw new ErrorException("Key $key not found in schema");
            }

            if (is_array($value)) {
                array_push($update, bind('$key = $value', [
                    '$key' => [
                        'value' => $key,
                        'escape' => false
                    ],
                    '$value' => [
                        'value' => implode(', ', $this->prepare_operators($key, $value)),
                        'escape' => false
                    ]
                ]));

            } else {

                $value = $this->schema->parse_specific_types($key, $value);
                $value = $this->value($value);

                array_push($update, bind('$key = $value', [
                    '$key' => [
                        'value' => $key,
                        'escape' => false
                    ],
                    '$value' => [
                        'value' => $value,
                        'escape' => false
                    ]
                ]));
                
            }
        }
        
        return implode(", ", $update);
    }

    private function value($value)
    {
        if ($value === 0) {
            return 0;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        if (is_int($value)) {
            return (int)$value;
        }
        if (is_string($value)) {
            return "'" . sql_escape_string($value) . "'";
        }
        if (is_bool($value)) {
            return boolval($value);
        }
        return $value;
    }

    private function prepare_operators($sqlkey, $object)
    {
        $blocks = [];

        foreach ($object as $key => $value) {

            if (in_array($key, [
                Operator:: AND
            ])) {
                throw new ErrorException("The operator $key cannot be used as an element value. It must contain the value of the elements within itself");
            }

            if (!in_array($key, Operator::OPERATORS) and !in_array($key, $this->schema->get_keys())) {
                throw new ErrorException("Key $key not found in schema");
            }

            /**
             * OR operator
             */
            if ($key == Operator:: OR && is_array($value)) {
                $elements = [];

                foreach ($value as $element) {
                    if (!is_array($element)) {
                        if ($sqlkey and $element) {
                            $element = $this->schema->parse_specific_types($sqlkey, $element);
                            $element = $this->value($element);

                            array_push($elements, "$sqlkey = $element");
                        }
                    }
                }

                array_push($blocks, "(" . implode(" OR ", $elements) . ")");
            }

            /**
             * LEN operator
             */
            if ($key == Operator::LEN && is_array($value)) {
                $elements = [];

                foreach ($value as $key => $element) {
                    if (!is_array($element)) {
                        if ($key == Operator::GT) {
                            $comparison = '>';
                        } elseif ($key == Operator::LT) {
                            $comparison = '<';
                        } elseif ($key == Operator::EQ) {
                            $comparison = '=';
                        } elseif ($key == Operator::GTE) {
                            $comparison = '>=';
                        } elseif ($key == Operator::LTE) {
                            $comparison = '<=';
                        }

                        if (!$comparison) {
                            throw new ErrorException("The comparison operator is not set");
                        }

                        $element = $this->schema->parse_specific_types($sqlkey, $element);
                        $element = $this->value($element);

                        array_push($elements, "(LENGTH($sqlkey) {$comparison} $element)");
                    }
                }

                array_push($blocks, "(" . implode(" AND ", $elements) . ")");
            } /**
             * NOT operator
             */
            elseif ($key == Operator::NOT) {
                if (!is_array($value)) {
                    $value = $this->schema->parse_specific_types($sqlkey, $value);
                    $value = $this->value($value);

                    array_push($blocks, "($sqlkey NOT IN ( $value ))");
                } else {

                    if ($this->schema->get_type($sqlkey) == TYPE_PASSWORD) {
                        throw new ErrorException("The data type 'password' cannot be used as an array ");
                    }

                    $value = array_values($value);
                    $value = array_map(function ($e) {
                        return $this->value($e);
                    }, $value);
                    $value = implode(", ", $value);

                    array_push($blocks, "($sqlkey NOT IN ( $value ))");
                }
            } /**
             * GT, LT, EQ, GTE, LTE operator
             */
            elseif ($key == Operator::GT or $key == Operator::LT or $key == Operator::EQ or $key == Operator::GTE or $key == Operator::LTE) {
                if ($this->schema->get_type($sqlkey) == TYPE_PASSWORD) {
                    throw new ErrorException("The data type 'password' cannot be used in comparisons");
                }

                if (is_numeric($value) or is_int($value)) {
                    $value = (int)$value;

                    if ($key == Operator::GT) {
                        $comparison = '>';
                    } elseif ($key == Operator::LT) {
                        $comparison = '<';
                    } elseif ($key == Operator::EQ) {
                        $comparison = '=';
                    } elseif ($key == Operator::GTE) {
                        $comparison = '>=';
                    } elseif ($key == Operator::LTE) {
                        $comparison = '<=';
                    }

                    array_push($blocks, "($sqlkey $comparison $value)");
                } else {
                    $type = gettype($value);

                    throw new ErrorException("The {$key} expression must be of type number, passed type $type");
                }
            } /**
             * IN operator
             */
            elseif ($key == Operator::IN) {
                if ($this->schema->get_type($sqlkey) == TYPE_PASSWORD) {
                    throw new ErrorException("The data type 'password' cannot be checked by the operator \$in");
                }

                if (!is_array($value)) {
                    $value = $this->value($value);

                    array_push($blocks, "($sqlkey IN ( $value ))");
                } else {
                    $value = array_values($value);
                    $value = array_map(function ($e) {
                        return $this->value($e);
                    }, $value);
                    $value = implode(", ", $value);

                    array_push($blocks, "($sqlkey IN ( $value ))");
                }
            } /**
             * INC operator
             */
            elseif ($key == Operator::INC) {
                if ($this->schema->get_type($sqlkey) == TYPE_PASSWORD) {
                    throw new ErrorException("The data type 'password' cannot be checked by the operator \$inc");
                }

                if (!is_array($value)) {
                    $value = intval($value);

                    array_push($blocks, "($sqlkey + $value)");
                } else {
                    $value = array_values($value);
                    $value = array_map(function ($e) {
                        return intval($e);
                    }, $value);
                    $value = implode(", ", $value);

                    array_push($blocks, "($sqlkey + $value)");
                }
            }
        }

        return $blocks;
    }

    private function prepare_rows($results)
    {
        $data = array();

        if (function_exists('mysqli_fetch_object')) {
            if ($results instanceof \mysqli_result) {
                while ($row = mysqli_fetch_object($results)) {
                    array_push($data, (array) $this->prepare_row($row));
                }
            }
        } elseif (function_exists('mysql_fetch_object')) {
            if ($results instanceof \mysql_result) {
                while ($row = mysql_fetch_object($results)) {
                    array_push($data, (array) $this->prepare_row($row));
                }
            }
        }

        return $data;

    }

    private function prepare_row($row)
    {
        $row = (array) $row;

        foreach ($row as $key => $value) {
            switch ($this->schema->get_type($key)) {
                case TYPE_OBJECT:
                    $row[$key] = json_decode($value);
                    break;
                case TYPE_TIME:
                    $row[$key] = (new Time())->parse_time($value);
                    break;
                case TYPE_DATETIME:
                case TYPE_TIMESTAMP:
                case TYPE_DATE:
                    $row[$key] = (new Time())->parse_date($value);
                    break;
                case TYPE_SMALLINT:
                case TYPE_BIGINT:
                case TYPE_INT:
                    $row[$key] = intval($value);
                    break;
                case TYPE_DOUBLE:
                    $row[$key] = doubleval($value);
                    break;
                case TYPE_BOOLEAN:
                    $row[$key] = boolval($value);
                    break;
                case TYPE_POINT:
                    $row[$key] = IS_NOT_TYPE;
                    break;
            }
        }

        return $row;

    }

    private function execute_query(string $query) {
        global $phpgoose_connection;

        if ($this->offset and !$this->limit) {
            throw new ErrorException("The offset parameter cannot be used without the limit parameter");
        }

        if (!($phpgoose_connection instanceof Connection)) {
            throw new ErrorException("No connection to the database established");
        }

        if ($phpgoose_connection->get_provider() == Connection::PROVIDER_MYSQL) {
            $results = mysql_query($query, $phpgoose_connection->get_connection());

            if (mysql_error($phpgoose_connection->get_connection())) {
                throw new SQLException(mysql_error($phpgoose_connection->get_connection()));
            }

            return $this->prepare_rows($results);

        } elseif ($phpgoose_connection->get_provider() == Connection::PROVIDER_MYSQLI) {
            $results = mysqli_query($phpgoose_connection->get_connection(), $query);

            if (mysqli_error($phpgoose_connection->get_connection())) {
                throw new SQLException(mysqli_error($phpgoose_connection->get_connection()));
            }

            return $this->prepare_rows($results);

        }
    }


}

/**
 * A class of operators for creating SQL queries
 * @package phpgoose
 */
abstract class Operator
{

    const OR = '$or';
    const AND = '$and';
    const NOT = '$not';
    const EQ = '$eq';
    const GT = '$gt';
    const LT = '$lt';
    const GTE = '$gte';
    const LTE = '$lte';
    const IN = '$in';
    const ADD = '$add';
    const LEN = '$len';

    /**
     * The $inc operator increments a field by a specified value
     */
    const INC = '$inc';

    const OPERATORS = [
        Operator::OR,
        Operator::AND,
        Operator::NOT,
        Operator::EQ,
        Operator::GT,
        Operator::LT,
        Operator::GTE,
        Operator::LTE,
        Operator::IN,
        Operator::LEN,
        Operator::ADD,
        Operator::INC
    ];
}


namespace Helpers;

use Wrapper\Time;

/**
 * Value generator
 * @package phpgoose
 */
abstract class Generator
{

    /**
     * Generate standard record ID
     *
     * @param int $length
     * @return int
     */
    public static function id(int $length = 8)
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;

        return mt_rand($min, $max);
    }

    /**
     * Generate a secret string
     *
     * @param string $salt Salt for secret-string generation
     * @return string
     */
    public static function secret(string $salt)
    {
        return strval(mt_rand(0, 0x7fffffff) ^ crc32($salt) ^ crc32(microtime()));
    }


    /**
     * Generate UUID
     *
     * @return string
     */
    public static function UUID() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }


    /**
     * Generate a random string, using a cryptographically secure
     * pseudorandom number generator (random_int)
     *
     * For PHP 7, random_int is a PHP core function
     * For PHP 5.x, depends on https://github.com/paragonie/random_compat
     *
     * @param int $length How many characters do we want?
     * @param string $keyspace A string of all possible characters
     *                         to select from
     * @return string
     * @throws \ErrorException
     */
    public static function password(int $length, string $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;

        if ($max < 1) {
            throw new \ErrorException('$keyspace must be at least two characters long');
        }

        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }


    /**
     * Return the current timestamp
     * @return Time
     */
    public static function current_time() {
        return new Time(time());
    }
}


/**
 * Put a string in a secure format for the name of a table, SQL database
 *
 * @param string $string
 * @return string|string[]|null
 */
function sanitize_name(string $string)
{
    return preg_replace('/^-+|-+$/', '', strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $string)));
}


namespace Wrapper;

/**
 * Class Time
 * @package phpgoose
 */
class Time
{

    const FORMAT_TIME = 'H:i:s';
    const FORMAT_DATE = 'Y-m-d H:i:s';

    private $unix = 0;
    private $timestamp = "";
    private $value = "";

    public function __construct($unix = false)
    {
        if ($unix) {
            $this->unix = $unix;
            $this->timestamp = gmdate(Time::FORMAT_DATE, $this->unix);
        }
    }

    public function time()
    {
        $this->unix = time();
        $this->timestamp = gmdate(Time::FORMAT_DATE, $this->unix);
        return $this;
    }

    public function get_unix()
    {
        return $this->unix;
    }

    public function get_timestamp()
    {
        return $this->timestamp;
    }

    public function __toString()
    {
        return strval($this->unix);
    }

    public function parse_date($date)
    {
        $parsed = date_parse_from_format(Time::FORMAT_DATE, $date);
        $this->unix = mktime($parsed['hour'], $parsed['minute'], $parsed['second'], $parsed['month'], $parsed['day'], $parsed['year']);
        $this->value = $date;
        $this->timestamp = gmdate(Time::FORMAT_DATE, $this->unix);
        return $this;
    }

    public function parse_time($date)
    {
        $parsed = date_parse_from_format(Time::FORMAT_TIME, $date);
        $this->unix = mktime($parsed['hour'], $parsed['minute'], $parsed['second'], date('m'), date('d'), date('y'));
        $this->value = $date;
        $this->timestamp = gmdate(Time::FORMAT_DATE, $this->unix);
        return $this;
    }

}

/**
 * SQL coordinate
 * @package phpgoose
 */
class Point
{

    private $latitude = 0;
    private $longitute = 0;

    public function __construct($latitude = 0, $longitute = 0)
    {
        $this->latitude = $latitude;
        $this->longitute = $longitute;
    }

    public function get_latitude()
    {
        return $this->latitude;
    }

    public function get_longitute()
    {
        return $this->longitute;
    }
}


namespace Types;

use Helpers\Generator;

/**
 * Type to create a record ID
 * @return array
 */
function ID()
{
    return array(
        'primary' => true,
        'type' => TYPE_BIGINT,
        'not_null' => true,
        'default' => Generator::id()
    );
}


/**
 * Type for creating an encrypted password
 * @return array
 */
function Password()
{
    return array(
        'primary' => false,
        'type' => TYPE_PASSWORD,
        'not_null' => true
    );
}


/**
 * Type to create time
 * @return array
 */
function DateTime()
{
    return array(
        'primary' => false,
        'type' => TYPE_DATETIME,
        'not_null' => true
    );
}


namespace Engine;

use phpgoose\Connection;

/**
 * Shield the SQL string
 *
 * @param string $string
 * @return false|string
 */
function sql_escape_string(string $string) {
    global $phpgoose_connection;

    if ($phpgoose_connection instanceof Connection) {
        if (function_exists('mysql_real_escape_string')) {
            $string = mysql_real_escape_string($string, $phpgoose_connection->get_connection());
        } elseif (function_exists('mysqli_real_escape_string')) {
            $string = mysqli_real_escape_string($phpgoose_connection->get_connection(), $string);
        }

        return $string;
    }

    return $string;
}


/**
 * Safely format an SQL string
 *
 * @param string $string
 * @param array $data
 * @return string|string[]
 */
function bind(string $string, array $data) {
    $sql = str_replace(array_keys($data), array_map(function ($e) {
        if (is_array($e) or is_object($e)) {
            $e = (array) $e;

            if ($e['escape']) {
                return sql_escape_string($e['value']);
            }

            return $e['value'];
        } else {
            return sql_escape_string($e);
        }
    }, array_values($data)), $string);

    $sql = preg_replace('/\s+/', ' ', $sql);

    return $sql;
}
