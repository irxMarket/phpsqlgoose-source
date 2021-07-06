<?php

/**
 * Procedure:
 * - First, establish a connection to the database by declaring a class 'Connection'
 * - Create a schema with data by declaring a class 'Schema'
 * - Create a model by declaring the class 'Model'
 * - Access the model to interact with a table in the database
 *
 * @version 1.0.0
 * @author masloff (irtex)
 * @copyright 2021 iRTEX
 */

namespace phpsqlgoose {

    use ErrorException;
    use Exception;
    use mysqli;
    use mysqli_result;
    use phpsqlgoose\Engine\Operator\Operator;
    use phpsqlgoose\Engine\Parser\ParserOperators;
    use phpsqlgoose\Engine\Parser\ParserSelect;
    use phpsqlgoose\Engine\Parser\ParserUpdate;
    use phpsqlgoose\Engine\Parser\ParserWhile;
    use phpsqlgoose\Wrapper\Point;
    use function phpsqlgoose\Engine\bind;
    use function phpsqlgoose\Engine\sql_escape_string;
    use function phpsqlgoose\Helpers\sanitize_name;

    /**
     * Version of phpsqlgoose
     */
    define("VERSION", '1.0.0');

    /**
     * A data type that will attempt to cast any incoming values into a string
     */
    define("TYPE_STRING", 'string');

    /**
     * String data of variable size.
     * Use n to specify the string size in bytes (values from 1 to 8000 are allowed) or use max to specify a column size
     * limit up to the maximum storage size, which is 2^31-1 bytes (2GB). For single-byte encodings such as Latin,
     * the size at storage is n bytes + 2 bytes and the number of characters stored is n.
     * For multibyte encodings, the size when stored is also n bytes + 2 bytes,
     * but the number of stored characters may be less than n. ISO synonyms for varchar are charvarying or charactarying.
     */
    define("TYPE_VARCHAR", 'varchar');

    /**
     * String data of fixed size. n specifies the size of the string in bytes and should have a value between 1 and 8000.
     * For single-byte encodings such as Latin, the size in storage is n bytes and the number of characters stored is also n.
     * For multibyte encodings, the storage size is also n bytes, but the number of stored characters may be less than n.
     */
    define("TYPE_CHAR", 'char');

    /**
     * Variable length data not in Unicode in the server charset and with a maximum string length of 2^31-1 (2,147,483,647).
     * If two-byte characters are used in the server codepage, the amount of space occupied by the type still does not
     * exceed 2,147,483,647 bytes. It may be less than 2,147,483,647 bytes - depending on the character string.
     */
    define("TYPE_TEXT", 'text');

    /**
     * A TEXT column with a maximum length of 255 (28 - 1) characters.
     * The effective maximum length is less if the value contains multi-byte characters.
     * Each TINYTEXT value is stored using a one-byte length prefix that indicates the number of bytes in the value.
     */
    define("TYPE_TINYTEXT", 'tinytext');

    /**
     * A TEXT column with a maximum length of 16,777,215 (224 - 1) characters.
     * The effective maximum length is less if the value contains multi-byte characters
     * Each MEDIUMTEXT value is stored using a three-byte length prefix that indicates the number of bytes in the value.
     */
    define("TYPE_MEDIUMTEXT", 'mediumtext');

    /**
     * A TEXT column with a maximum length of 4,294,967,295 or 4GB (232 - 1) characters.
     * The effective maximum length is less if the value contains multi-byte characters.
     * The effective maximum length of LONGTEXT columns also depends on the configured maximum packet size in the
     * client/server protocol and available memory. Each LONGTEXT value is stored using a four-byte length prefix
     * that indicates the number of bytes in the value.
     */
    define("TYPE_LONGTEXT", 'longtext');

    /**
     * A normal-size integer. When marked UNSIGNED, it ranges from 0 to 4294967295,
     * otherwise its range is -2147483648 to 2147483647 (SIGNED is the default). If a column has been set to ZEROFILL,
     * all values will be prepended by zeros so that the INT value contains a number of M digits. INTEGER is a synonym for INT.
     */
    define("TYPE_INT", 'int');

    /**
     * A small integer. The signed range is -32768 to 32767. The unsigned range is 0 to 65535.
     * If a column has been set to ZEROFILL, all values will be prepended by zeros so that the SMALLINT value contains a number of M digits.
     */
    define("TYPE_SMALLINT", 'smallint');

    /**
     * A large integer. The signed range is -9223372036854775808 to 9223372036854775807.
     * The unsigned range is 0 to 18446744073709551615.
     * If a column has been set to ZEROFILL, all values will be prepended by zeros so that the BIGINT value contains
     * a number of M digits.
     */
    define("TYPE_BIGINT", 'bigint');

    /**
     * A normal-size (double-precision) floating-point number (see FLOAT for a single-precision floating-point number).
     *
     * Allowable values are:
     * 1. -1.7976931348623157E+308 to -2.2250738585072014E-308
     * 2. 0
     * 3. 2.2250738585072014E-308 to 1.7976931348623157E+308
     * These are the theoretical limits, based on the IEEE standard.
     * The actual range might be slightly smaller depending on your hardware or operating system.
     *
     * M is the total number of digits and D is the number of digits following the decimal point. If M and D are omitted, values are stored to the limits allowed by the hardware. A double-precision floating-point number is accurate to approximately 15 decimal places.
     */
    define("TYPE_DOUBLE", 'double');

    /**
     * These types are synonyms for TINYINT(1). A value of zero is considered false. Non-zero values are considered true.
     */
    define("TYPE_BOOLEAN", 'boolean');

    /**
     * A time. The range is '-838:59:59.999999' to '838:59:59.999999'.
     * Microsecond precision can be from 0-6; if not specified 0 is used.
     * Microseconds have been available since MariaDB 5.3.
     */
    define("TYPE_TIME", 'time');
    
    /**
     * A date. The supported range is '1000-01-01' to '9999-12-31'.
     * MariaDB displays DATE values in 'YYYY-MM-DD' format,
     * but can be assigned dates in looser formats, including strings or numbers,
     * as long as they make sense. These include a short year, YY-MM-DD, no delimiters,
     * YYMMDD, or any other acceptable delimiter, for example YYYY/MM/DD. For details, see date and time literals.
     */
    define("TYPE_DATE", 'date');

    /**
     * A date and time combination.
     * MariaDB displays DATETIME values in 'YYYY-MM-DD HH:MM:SS.ffffff' format,
     * but allows assignment of values to DATETIME columns using either strings or numbers.
     * For details, see date and time literals.
     */
    define("TYPE_DATETIME", 'datetime');

    /**
     * A timestamp in the format YYYY-MM-DD HH:MM:SS.ffffff.
     * The timestamp field is generally used to define at which moment in time a row was added
     * or updated and by default will automatically be assigned the current datetime when a record
     * is inserted or updated. The automatic properties only apply to the first TIMESTAMP in the record;
     * subsequent TIMESTAMP columns will not be changed.
     */
    define("TYPE_TIMESTAMP", 'timestamp');

    /**
     * Special data type. Hash the incoming value and write the hash of the transmitted password to the database.
     * In the find functions, the comparison is done with the unencrypted password.
     */
    define("TYPE_PASSWORD", 'password');

    /**
     * Special data type. Checks if the string is a URL and writes it to the database,
     * if the string is not a URL, the method to which the value is passed will throw an exception
     */
    define("TYPE_URL", 'url');

    /**
     * JSON is an alias for LONGTEXT introduced for compatibility reasons with MySQL's JSON data type.
     * MariaDB implements this as a LONGTEXT rather, as the JSON data type contradicts the SQL standard,
     * and MariaDB's benchmarks indicate that performance is at least equivalent.
     */
    define("TYPE_OBJECT", 'json');

    /**
     * The BINARY type is similar to the CHAR type, but stores binary byte strings rather than non-binary character strings.
     * M represents the column length in bytes.
     * It contains no character set, and comparison and sorting are based on the numeric value of the bytes.
     * If the maximum length is exceeded, and SQL strict mode is not enabled , the extra characters will be dropped with a warning.
     * If strict mode is enabled, an error will occur.
     *
     * BINARY values are right-padded with 0x00 (the zero byte) to the specified length when inserted.
     * The padding is not removed on select, so this needs to be taken into account when sorting and comparing,
     * where all bytes are significant. The zero byte, 0x00 is less than a space for comparison purposes.
     */
    define("TYPE_BINARY", 'binary');

    /**
     * A BLOB column with a maximum length of 65,535 (216 - 1) bytes.
     * Each BLOB value is stored using a two-byte length prefix that indicates the number of bytes in the value.
     * An optional length M can be given for this type.
     * If this is done, MariaDB creates the column as the smallest BLOB type large enough to hold values M bytes long.
     */
    define("TYPE_BLOB", 'blob');

    /**
     * The VARBINARY type is similar to the VARCHAR type,
     * but stores binary byte strings rather than non-binary character strings. M represents the maximum column length in bytes.
     * It contains no character set, and comparison and sorting are based on the numeric value of the bytes.
     * If the maximum length is exceeded, and SQL strict mode is not enabled,
     * the extra characters will be dropped with a warning. If strict mode is enabled, an error will occur.
     */
    define("TYPE_VARBINARY", 'varbinary');

    /**
     * A BLOB column with a maximum length of 16,777,215 (224 - 1) bytes.
     * Each MEDIUMBLOB value is stored using a three-byte length prefix that indicates the number of bytes in the value.
     */
    define("TYPE_MEDIUMBLOB", 'mediumblob');

    /**
     * A BLOB column with a maximum length of 4,294,967,295 bytes or 4GB (232 - 1).
     * The effective maximum length of LONGBLOB columns depends on the configured maximum packet
     * size in the client/server protocol and available memory. Each LONGBLOB value is stored using a
     * four-byte length prefix that indicates the number of bytes in the value.
     */
    define("TYPE_LONGBLOB", 'longblob');

    /**
     * Type of coordinate in space
     */
    define("TYPE_POINT", 'point');

    /**
     * Set of coordinates in space
     */
    define("TYPE_LINESTRING", 'linestring');

    /**
     * Regular expression checking nickname
     */
    define('REGEX_NICKNAME', '/^[a-zA-Z0-9_.]{1,30}$/i');

    /**
     * Regular expression checking email
     */
    define('REGEX_EMAIL', '/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD');

    /**
     * A global variable that contains the class of the database connection 'Connection'
     */
    global $phpsqlgoose_connection;

    $phpsqlgoose_connection = false;

    /**
     * Check the version of PHP against the minimum required
     */
    if (version_compare(PHP_VERSION, '7.0.0') < 0) {
        $PHP_VERSION = PHP_VERSION;
        throw new ErrorException("You are using too old version of PHP ($PHP_VERSION). Version 7.0.0 is required.");
    }


    /**
     * Error in SQL query processing
     * @package phpgoose
     */
    class SQLException extends Exception
    {
    }


    /**
     * Exception when a string does not match a regular expression
     * @package phpsqlgoose
     */
    class RegexException extends Exception
    {
    }


    /**
     * Exception where string does not match filter requirements. Usually occurs in insert, update functions
     * @package phpsqlgoose
     */
    class FilterException extends Exception
    {
    }


    /**
     * Creates a global connection to MySQL for further work with phpgoose models.
     * @package phpgoose
     */
    class Connection
    {

        private $connection = false;
        private $db;
        private $db_selected = false;
        private $charset;
        private $host;
        private $username;
        private $password;
        private $error = false;

        /**
         * Creates a global connection to MySQL for further work with phpgoose models.
         *
         * @param string $hostname Server name string (example: localhost or 127.0.0.1)
         * @param string $username MYSQL username to connect to the server
         * @param string $password MYSQL password to connect to the server
         * @param string $db_name Database name for connecting to MYSQL
         * @param string $charset MYSQL database encoding (default: utf8)
         * @throws ErrorException
         * @author masloff (irtex)
         * @copyright 2021 iRTEX
         */
        public function __construct(string $hostname = 'localhost', string $username = 'root', string $password = 'root', string $db_name = 'My App', string $charset = 'utf8')
        {
            global $phpsqlgoose_connection;

            $this->charset = strval($charset);
            $this->db = sanitize_name($db_name);
            $this->host = strval($hostname);
            $this->username = strval($username);
            $this->password = strval($password);

            try {
                
                $this->connection = mysqli_connect($this->host, $username, $password);

                if (mysqli_connect_errno()) {
                
                    /**
                     * Connection not completed
                     */
                    $this->error = mysqli_connect_error();
                
                } else {
                    
                    /**
                     * Connection completed
                     */
                    
                    $query = bind('CREATE DATABASE $bind', [
                        '$bind' => $this->db
                    ]);

                    $phpsqlgoose_connection = $this;
                    mysqli_set_charset($this->connection, $this->charset);
                    $this->db_selected = $this->connection->select_db($this->db);

                    if ($this->db_selected == false) {
                        if (!$this->connection->query($query, MYSQLI_USE_RESULT)) {
                            $this->error = (mysqli_error($this->connection));
                        } else {
                            $this->db_selected = $this->connection->select_db($this->db);
                            $phpsqlgoose_connection = $this;
                        }
                    }

                }
            } catch (Exception $e) {
                $this->error = $e;
            }

        }

        /**
         * @return array|false
         */
        public function __debugInfo()
        {
            if ($this->connection instanceof mysqli) {
                return $this->connection->get_connection_stats();
            }

            return false;
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
                'charset' => $this->charset
            );
        }

        /**
         * @param array $data
         * @throws ErrorException
         */
        public function __unserialize(array $data)
        {
            $this->charset = strval($data['charset']);
            $this->db = sanitize_name($data['db']);
            $this->host = strval($data['host']);
            $this->username = strval($data['username']);
            $this->password = strval($data['password']);

            $this->__construct($this->host, $this->username, $this->password, $this->db, $this->charset);
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
         * Checks for connection to the SQL database
         *
         * @noinspection PhpUnused
         * @return bool
         */
        public function is_connected()
        {
            if ($this->get_connection() == false) {
                return false;
            }

            return $this->get_connection()->ping();
        }

    }


    /**
     * Data schema for creating the phpgoose model. It is used to declare fields in a table and their configurations: field type, default value, uniqueness, etc.
     * @package phpgoose
     */
    class Schema
    {

        /**
         *
         */
        const NOT_DEFINED = 'not-defined';

        /**
         * @var array
         */
        private $fluids = array();
        /**
         * @var array
         */
        private $data_types = [
            TYPE_VARBINARY,
            TYPE_BLOB,
            TYPE_MEDIUMBLOB,
            TYPE_POINT,
            TYPE_LINESTRING,
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
         * @var array|array[]
         */
        private $filter = [];
        /**
         * List of field types that do not support specifying a particular size
         * @var array
         */
        private $fluids_without_size = [
            TYPE_DOUBLE,
            TYPE_BOOLEAN,
            TYPE_TIME,
            TYPE_DATE,
            TYPE_OBJECT,
            TYPE_DATETIME,
            TYPE_BLOB,
            TYPE_MEDIUMBLOB,
            TYPE_LONGBLOB,
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
            $this->filter = array(
                'type' => [
                    'wrapper' => function ($e) {
                        return strval($e);
                    },
                    'default' => 'TYPE_STRING'
                ],
                'size' => [
                    'wrapper' => function ($e) {
                        return strval($e);
                    },
                    'default' => false
                ],
                'constraint' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false
                ],
                'primary' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false
                ],
                'unique' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false
                ],
                'default' => [
                    'wrapper' => function ($e) {
                        if ($e instanceof Point) {
                            return $e;
                        }
                        return strval($e);
                    },
                    'default' => false
                ],
                'null' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false
                ],
                'not_null' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false
                ],
                'auto_increment' => [
                    'wrapper' => function ($e) {
                        return boolval($e);
                    },
                    'default' => false,
                    '$not' => ['default']
                ],
                'comment' => [
                    'wrapper' => function ($e) {
                        return strval($e);
                    },
                    'default' => false
                ],
                'regex' => [
                    'wrapper' => function ($e) {
                        return strval($e);
                    },
                    'default' => false
                ],
                'min' => [
                    'wrapper' => function ($e) {
                        return intval($e);
                    },
                    'default' => false
                ],
                'max' => [
                    'wrapper' => function ($e) {
                        return intval($e);
                    },
                    'default' => false
                ]
            );

            foreach ($fluids as $key => $value) {
                $this->fluids[$key] = (object)array();

                if (!is_array($value) and !in_array($value, $this->data_types)) {
                    throw new ErrorException("Invalid data type: $value");
                } elseif (is_array($value) and !key_exists("type", $value)) {
                    throw new ErrorException("The obligatory parameter type was not passed in $key");
                } elseif (is_array($value) and key_exists("type", $value) and !in_array($value["type"], $this->data_types)) {
                    throw new ErrorException("Invalid data type: $value");
                }

                if (is_array($value)) {
                    foreach ($value as $item => $v) {
                        if (isset($this->filter[$item])) {
                            $this->fluids[$key]->{$item} = $this->filter[$item]['wrapper']($v);
                            if (is_array($value) and isset($this->filter[$item]['$not']) and is_array($this->filter[$item]['$not'])) {
                                foreach (array_diff($this->filter[$item]['$not'], $value) as $e) {
                                    if (isset($value[$e])) {
                                        throw new ErrorException("Type {$e} cannot be used with type {$item}");
                                    }
                                }
                            }
                            if (isset($this->fluids[$key]->type) and !in_array($this->fluids[$key]->type, $this->fluids_without_size)) {
                                $this->fluids[$key]->size = empty($this->fluids[$key]->size) == false ? $this->filter['size']['wrapper']($this->fluids[$key]->size) : 255;
                            } else {
                                $this->fluids[$key]->size = false;
                            }
                        } else {
                            throw new ErrorException("Type {$item} does not support");
                        }

                    }
                }

                if (isset($value["type"])) {
                    if (in_array($value["type"], $this->fluids_without_size) and key_exists("size", $value) and $value['size'] != false) {
                        throw new ErrorException("Data type {$value["type"]} does not support size");
                    }
                }

            }
        }

        /**
         * Get a list of all fields in the database
         *
         * @return array List of all database fields
         */
        public function get_keys()
        {
            return array_keys($this->fluids);
        }

        /**
         * Get all the fields in the schematic
         *
         * @return array Scheme
         */
        public function get_fluids()
        {
            return $this->fluids;
        }

        /**
         * Get the default value for a field in the schema by its key
         *
         * @param string $fluid Field in the scheme
         * @return string=
         *
         */
        public function get_default(string $fluid)
        {
            if (is_array($this->fluids) and isset($schema->{$fluid}) and isset($schema->{$fluid}->default)) {
                return $schema->{$fluid}->default;
            }

            return \phpgoose\Schema::NOT_DEFINED;
        }

        /**
         * Get fluid type by its key
         *
         * @noinspection PhpUnused
         * @param string $fluid Field in the scheme
         * @return string Get the type
         */
        public function get_type(string $fluid)
        {
            if (is_array($this->fluids) and isset($schema->{$fluid}) and isset($schema->{$fluid}->type)) {
                return $schema->{$fluid}->type;
            }

            return false;
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


    /**
     * Creates a model by implementing a schema for a specific table
     * @package phpgoose
     */
    class Model extends Operator
    {

        /**
         * Template for formatting an select SQL query in the find function
         */
        CONST SQL_TEMPLATE_FIND = 'SELECT $select $pipe FROM $name $where $limit $offset $order $sort $group_by;';

        /**
         * Template for formatting an delete SQL query in the find function
         */
        CONST SQL_TEMPLATE_DELETE = 'DELETE FROM $name $where $limit $offset $order $sort;';

        /**
         * Template for formatting an update SQL query in the find function
         */
        CONST SQL_TEMPLATE_UPDATE = 'UPDATE $name $pipe SET $update $where $limit $offset $order $sort;';

        /**
         * Template for formatting an update SQL query in the find function
         */
        CONST SQL_TEMPLATE_DROP_DATABASE = 'DROP DATABASE $if_exists $if_not_exists $name;';

        /**
         * Template for formatting an drop SQL query in the find function
         */
        CONST SQL_TEMPLATE_DROP_TABLE = 'DROP TABLE $if_exists $if_not_exists $name;';

        /**
         * Template for formatting an count SQL query in the find function
         */
        CONST SQL_TEMPLATE_COUNT = 'SELECT COUNT($select) FROM $name $where $limit $offset $order $sort;';

        private $schema = false;
        private $name = false;
        private $limit = false;
        private $order = false;
        private $sort = false;
        private $select = false;
        private $filter = [];
        private $group_by = false;
        private $pipes = [];
        private $pipes_aliases = [];
        private $offset = false;
        private $reverse = false;
        private $if_exists = false;
        private $if_not_exists = false;
        private $sql_query = false;

        /**
         * WHILE object parser class variable in SQL query
         * @var bool|ParserWhile
         */
        private $parser_while = false;

        /**
         * Rich operator parser class variable in an SQL query
         * @var bool|ParserOperators
         */
        private $parser_operators = false;

        /**
         * Parser class variable of the SELECT parameter in an SQL query
         * @var bool|ParserSelect
         */
        private $parser_select = false;

        /**
         * Parser class variable of the UPDATE parameter in the SQL query
         * @var bool|ParserUpdate
         */
        private $parser_update = false;

        /**
         * Creates a model by implementing a schema for a specific table
         *
         * @param string $name Name of the table in which the scheme will be implemented
         * @param Schema $schema Table schema
         * @throws ErrorException
         * @throws \phpgoose\SQLException
         * @throws Exception
         * @throws Exception
         * @author masloff (irtex)
         * @copyright 2021 iRTEX
         */
        public function __construct(string $name, Schema $schema)
        {
            parent::__construct();

            global $phpsqlgoose_connection;

            if ($phpsqlgoose_connection instanceof \phpgoose\Connection) {

                if ($schema instanceof Schema) {
                    $this->schema = $schema;
                    $this->name = sanitize_name($name);
                    $this->parser_operators = new ParserOperators();
                    $this->parser_while = new ParserWhile();
                    $this->parser_select = new ParserSelect();
                    $this->parser_update = new ParserUpdate();

                    $this->sql_query = "CREATE TABLE IF NOT EXISTS `{$this->name}` (";
                    $query_list = array();

                    foreach ($schema->get_fluids() as $key => $value) {
                        switch ($value->type) {
                            case 'int':
                                $type = 'SMALLINT';
                                break;
                            case 'url':
                            case 'password':
                            case 'string':
                            case 'any':
                                $type = 'VARCHAR';
                                break;
                            case 'boolean':
                                $type = 'BIT';
                                break;
                            default:
                                $type = strtoupper($value->type);
                        }

                        array_push($query_list, bind('$constraint $key $type $size $null $primary $auto_increment $default', array(
                            '$key' => $key,
                            '$constraint' => [
                                'value' => isset($value->constraint) ? (boolval($value->constraint) == true ? "CONSTRAINT" : '') : '',
                                'escape' => false
                            ],
                            '$type' => [
                                'value' => $type,
                                'escape' => false
                            ],
                            '$size' => [
                                'value' => isset($value->size) ? (empty($value->size) == false ? "({$value->size})" : '') : '',
                                'escape' => false
                            ],
                            '$null' => [
                                'value' =>
                                    isset($value->not_null)
                                        ? (
                                    (boolval($value->not_null) == true
                                        ? "NOT NULL"
                                        : (isset($value->null)
                                            ? (boolval($value->null) == true ? "NULL" : '')
                                            : ''))
                                    )
                                        : (isset($value->null)
                                        ? (boolval($value->null) == true ? "NULL" : '')
                                        : ''),
                                'escape' => false
                            ],
                            '$primary' => [
                                'value' =>
                                    isset($value->primary)
                                        ? (boolval($value->primary) == true
                                        ? "PRIMARY KEY"
                                        : (isset($value->unique)
                                            ? (boolval($value->unique) == true
                                                ? "UNIQUE KEY"
                                                : '')
                                            : ''))
                                        : (isset($value->unique)
                                        ? (boolval($value->unique) == true
                                            ? "UNIQUE KEY"
                                            : '')
                                        : ''),

                                'escape' => false
                            ],
                            '$default' => [
                                'value' => isset($value->default) ? (empty($value->default) == false ? "DEFAULT " . $this->input_value_to_database($schema, $key, $value->default) : '') : '',
                                'escape' => false
                            ],
                            '$auto_increment' => [
                                'value' => isset($value->auto_increment) ? (boolval($value->auto_increment) == true ? "AUTO_INCREMENT" : '') : '',
                                'escape' => false
                            ],
                            '$comment' => [
                                'value' => isset($value->comment) ? (empty($value->comment) == false ? "COMMENT " . $this->input_value_to_database($schema, $key, $value->comment, false) : '') : '',
                                'escape' => false
                            ],

                        )));
                    }

                    $this->sql_query .= implode(",", $query_list);
                    $this->sql_query .= ")";

                    $this->execute_query($this->sql_query);

                } else {
                    throw new ErrorException("The data for the model is of the wrong type");
                }

            } else {
                throw new ErrorException("Before you create a model, establish a connection to the database with the Connection class");
            }

        }

        /**
         * Returns a single record that satisfies the given query criteria on the collection. If the query satisfies multiple documents, this method returns the last document according to the natural order. If no documents satisfy the query, the method returns null.
         *
         * @noinspection PhpUnused
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @return null|object SQL records
         * @throws ErrorException
         * @throws SQLException
         */
        public function find_one(array $query = [])
        {
            $results = $this->limit(1)->find_many($query);

            if ($results) {
                return (object)end($results);
            } else {
                return null;
            }
        }

        /**
         * Selects records in a collection or view and returns a array selected records.
         *
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @return array SQL records
         * @throws ErrorException
         * @throws SQLException
         */
        public function find_many(array $query = [])
        {
            $this->sql_query = bind(self::SQL_TEMPLATE_FIND, array(
                '$name' => $this->name,
                '$pipe' => [
                    'value' => empty($this->pipes) ? "" : ", " . implode(", ", $this->pipes),
                    'escape' => false
                ],
                '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
                '$select' => [
                    'value' => $this->parser_select->parse_select($this->schema, $this->select),
                    'escape' => false
                ],
                '$where' => [
                    'value' => empty($query) ? "" : "WHERE " . $this->parser_while->parse_while($this->schema, $query),
                    'escape' => false
                ],
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
                ],
                '$group_by' => [
                    'value' => $this->group_by ? "GROUP BY {$this->group_by}" : "",
                    'escape' => false
                ]
            ));

            return $this->execute_query($this->sql_query);
        }

        /**
         * Insert SQL record into the table
         *
         * @noinspection PhpUnused
         * @param array $data SQL query as an array
         * @return boolean Status
         * @throws ErrorException
         * @throws SQLException
         * @throws Exception
         */
        public function insert_one(array $data)
        {
            # Checks if a hook is present and if so, applies it to the parameters
            if ($this->exists_hook("insert")) {
                $data = $this->run_hook("insert", $data);
            }

            foreach (array_diff($this->schema->get_keys(), array_keys($data)) as $key) {
                if ($this->schema->get_default($key) === Schema::NOT_DEFINED) {
                    if (!(isset($this->schema->{$key}->auto_increment) and $this->schema->{$key}->auto_increment == true)) {
                        throw new ErrorException("The key $key does not contain a default value and is not passed to the function when the record is created");
                    }
                } else {
                    $data[$key] = $this->schema->get_default($key);
                }
            }

            foreach ($data as $key => $value) {
                if ($data[$key] == '') {
                    throw new ErrorException("Key $key is empty");
                }

                if ($this->exists_hook("insert:$key")) {
                    $value = $this->run_hook("pre_insert_one:$key", $value);
                }

                $data[$key] = $this->input_value_to_database($this->schema, $key, $value);
            }

            $keys = implode(", ", array_map(function ($e) {
                return sql_escape_string($e);
            }, array_keys($data)));

            $values = implode(", ", array_values($data));

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
         * @noinspection PhpUnused
         * @param array $data SQL records
         * @return array
         * @throws ErrorException
         * @throws SQLException
         */
        public function insert_many(array $data)
        {
            $records = [];

            foreach ($data as $record) {
                if (is_array($record)) {
                    array_push($records, $this->insert_one($record));
                } else {
                    throw new ErrorException('Insert parameter is not an assoc array');
                }
            }

            return $records;
        }

        /**
         * Removes a single record from a collection.
         *
         * @noinspection PhpUnused
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @return bool SQL records
         * @throws ErrorException
         * @throws SQLException
         */
        public function delete_one(array $query)
        {
            return $this->limit(1)->delete_many($query);
        }

        /**
         * Removes all records that match the filter from a collection.
         *
         * @noinspection PhpUnused
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @return boolean Status
         * @throws ErrorException
         * @throws SQLException
         */
        public function delete_many(array $query = [])
        {
            # Checks if a hook is present and if so, applies it to the parameters
            if ($this->exists_hook("delete")) {
                $query = $this->run_hook("delete", $query);
            }

            # Forms an SQL query
            $this->sql_query = bind(self::SQL_TEMPLATE_DELETE, array(
                '$name' => $this->name,
                '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
                '$where' => [
                    'value' => empty($query) ? "" : "WHERE " . $this->parser_while->parse_while($this->schema, $query),
                    'escape' => false
                ],
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

            $this->execute_query($this->sql_query);

            return $this->sql_has_no_error();
        }

        /**
         * Updates a single record within the collection based on the filter.
         *
         * @noinspection PhpUnused
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
         * Changes an existing record or records in the collection.
         * The method can change specific fields of an existing record or records or
         * completely replace an existing record, depending on the update parameter.
         *
         * @noinspection PhpUnused
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @param array $update_data The modifications to apply.
         * @return boolean Status
         * @throws ErrorException
         * @throws SQLException
         * @throws Exception
         */
        public function update_many(array $query = [], array $update_data = [])
        {

            # Checks if a hook is present and if so, applies it to the parameters
            if ($this->exists_hook("update")) {
                $update_data = $this->run_hook("update", $update_data);
            }

            # Forms an SQL query

            $this->sql_query = bind(self::SQL_TEMPLATE_UPDATE, array(
                '$name' => $this->name,
                '$update' => [
                    'value' => empty($update_data) ? "" : $this->parser_update->parse_update($this->schema, $update_data),
                    'escape' => false
                ],
                '$pipe' => [
                    'value' => empty($this->pipes) ? "" : ", " . implode(", ", $this->pipes),
                    'escape' => false
                ],
                '$select' => $this->select ? $this->select : '*',
                '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
                '$where' => [
                    'value' => empty($query) ? "" : "WHERE " . $this->parser_while->parse_while($this->schema, $query),
                    'escape' => false
                ],
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

            $this->execute_query($this->sql_query);

            return $this->sql_has_no_error();

        }

        /**
         * Removes database.
         *
         * @noinspection PhpUnused
         * @return boolean Status
         * @throws ErrorException
         * @throws SQLException
         */
        public function drop_database()
        {

            # Forms an SQL query
            $this->sql_query = bind(self::SQL_TEMPLATE_DROP_DATABASE, array(
                '$name' => $this->name,
                '$if_exists' => [
                    'value' => $this->if_exists ? "IF EXISTS" : '',
                    'escape' => false
                ],
                '$if_not_exists' => [
                    'value' => $this->if_not_exists ? "IF NOT EXISTS" : '',
                    'escape' => false
                ],
            ));

            $this->execute_query($this->sql_query);

            return $this->sql_has_no_error();

        }

        /**
         * Removes a table from the database.
         *
         * @noinspection PhpUnused
         * @return boolean Status
         * @throws ErrorException
         * @throws SQLException
         */
        public function drop_table()
        {

            # Forms an SQL query
            $this->sql_query = bind(self::SQL_TEMPLATE_DROP_TABLE, array(
                '$name' => $this->name,
                '$if_exists' => [
                    'value' => $this->if_exists ? "IF EXISTS" : '',
                    'escape' => false
                ],
                '$if_not_exists' => [
                    'value' => $this->if_not_exists ? "IF NOT EXISTS" : '',
                    'escape' => false
                ],
            ));

            $this->execute_query($this->sql_query);

            return $this->sql_has_no_error();

        }

        /**
         * Returns the number of records that match the find_many() query for the collection.
         * The count() method does not perform the find_many() operation,
         * but instead counts and returns the number of results that match the query.
         *
         * @noinspection PhpUnused
         * @param array $query Optional. Specifies selection filter using query operators. To return all documents in a collection, omit this parameter
         * @return int|null Count orders
         * @throws ErrorException
         * @throws SQLException
         */
        public function count(array $query = [])
        {

            # Checks if a hook is present and if so, applies it to the parameters
            if ($this->exists_hook("count")) {
                $query = $this->run_hook("count", $query);
            }

            # Forms an SQL query
            $this->sql_query = bind(self::SQL_TEMPLATE_COUNT, array(
                '$name' => $this->name,
                '$offset' => $this->offset ? "OFFSET {$this->offset}" : '',
                '$select' => $this->select ? $this->select : '*',
                '$where' => [
                    'value' => empty($query) ? "" : "WHERE " . $this->parser_while->parse_while($this->schema, $query),
                    'escape' => false
                ],
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
                return intval(end($data));
            } else {
                return null;
            }
        }

        /**
         * @param string $query
         * @return array|bool
         * @throws ErrorException
         * @throws SQLException
         */
        private function execute_query(string $query)
        {

            # Checks if a hook is present and if so, applies it to the parameters
            if ($this->exists_hook("__SQL__")) {
                $query = $this->run_hook("__SQL__", $query);
            }

            global $phpsqlgoose_connection;

            $data = array();

            if ($this->offset and !$this->limit) {
                throw new ErrorException("The offset parameter cannot be used without the limit parameter");
            }

            if (!($phpsqlgoose_connection instanceof Connection)) {
                throw new ErrorException("No connection to the database established");
            }

            $this->sql_query = $query;

            $results = mysqli_query($phpsqlgoose_connection->get_connection(), $query);

            if (mysqli_error($phpsqlgoose_connection->get_connection())) {
                throw new SQLException(mysqli_error($phpsqlgoose_connection->get_connection()));
            }

            if (
                $results === true or
                $results === false
            ) {
                return $results;
            }

            if ($results instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($results)) {
                    try {
                        array_push($data, $this->output_rows_from_database($this->schema, (array) $row, (array) $this->filter));
                    } catch (Exception $e) {
                        array_push($data, []);
                    }
                }
            }

            if ($this->reverse) {
                $data = array_reverse($data);
            }

            return $data;

        }


        /**
         * When you create an SQL record via the invoke method,
         * the function will handle errors and return False instead of the standard PHP errors.
         * If you need to handle errors manually use the insert method
         *
         * @noinspection PhpUnused
         * @param $data
         * @return \phpgoose\Model|bool
         * @throws Exception
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
         * Rename the column
         *
         * @noinspection PhpUnused
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
         * Get data type from MySQL
         *
         * @noinspection PhpUnused
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
         * Limits the number of records passed to the next stage in the pipeline.
         *
         * @noinspection PhpUnused
         * @param int $limit
         * @return $this
         */
        public function limit(int $limit)
        {
            $this->limit = intval($limit);
            return $this;
        }

        /**
         * Selects a subset of an array to return based on the specified condition.
         * Returns an array with only those elements that match the condition. The returned elements are in the original order.
         *
         * @noinspection PhpUnused
         * @param string|array $select
         * @return $this
         * @throws ErrorException
         */
        public function select($select)
        {
            if (is_array($select) or is_object($select)) {
                foreach ($select as $key) {
                    if (!isset($this->schema->{$key})) {
                        throw new ErrorException("Key $key not found in schema");
                    }
                }
                $this->select = implode(", ", $select);
            } else {
                if (!isset($this->schema->{$select}) and !$select == '*') {
                    throw new ErrorException("Key $select not found in schema");
                }

                $this->select = (string)$select;
            }

            return $this;
        }

        /**
         *
         * @noinspection PhpUnused
         * @param $filter
         * @return $this
         * @throws ErrorException
         */
        public function filter_by($filter)
        {
            if (is_array($filter) or is_object($filter)) {
                foreach ($filter as $key) {
                    if (!isset($this->schema->{$key}) and !in_array($key, $this->pipes_aliases)) {
                        throw new ErrorException("Key $key not found in schema");
                    }
                }
                $this->filter = (array) $filter;
            } else {
                if (!isset($this->schema->{$filter}) and !$filter == '*' and !in_array($filter, $this->pipes_aliases)) {
                    throw new ErrorException("Key $filter not found in schema");
                }

                $this->filter = (string)$filter;
            }

            return $this;
        }

        /**
         * The group by clause is used in a SELECT statement to collect data across multiple records and group the results by one or more columns.
         *
         * @noinspection PhpUnused
         * @param string $group
         * @return $this
         * @throws ErrorException
         */
        public function group_by(string $group)
        {
            if (is_array($group) or is_object($group)) {
                foreach ($group as $key) {
                    if (!isset($this->schema->{$key})) {
                        throw new ErrorException("Key $key not found in schema");
                    }
                }
                $this->group_by = implode(", ", $group);
            } else {
                if (!isset($this->schema->{$group})) {
                    throw new ErrorException("Key $group not found in schema");
                }

                $this->group_by = (string) $group;
            }

            return $this;
        }

        /**
         * @noinspection PhpUnused
         * @param string $by
         * @return $this
         */
        public function order(string $by)
        {
            $this->order = $by;
            return $this;
        }

        /**
         *
         * @noinspection PhpUnused
         * @param bool $reverse
         * @return $this
         */
        public function reverse(bool $reverse = true)
        {
            $this->reverse = $reverse;
            return $this;
        }

        /**
         * Sorts all input records and returns them to the pipeline in sorted order.
         *
         * @noinspection PhpUnused
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
         * Skips over the specified number of records pass into the stage and passes the remaining documents to the next stage in the pipeline.
         *
         * @noinspection PhpUnused
         * @param int $offset
         * @return $this
         */
        public function offset(int $offset)
        {
            $this->offset = intval($offset);
            return $this;
        }

        /**
         * EXISTS is a logical MySQL statement that accepts and processes a nested SQL query (SELECT) in order to check the existence of strings
         *
         * @noinspection PhpUnused
         * @param bool $boolean
         * @return $this
         * @throws Exception
         */
        public function if_exists(bool $boolean = true)
        {
            if ( $this->if_not_exists === true) {
                throw new Exception("The 'if_not_exists' parameter is already set.");
            }

            $this->if_exists = $boolean;
            return $this;
        }

        /**
         * EXISTS is a logical MySQL statement that accepts and processes a nested SQL query (SELECT) in order to check the existence of strings
         *
         * @noinspection PhpUnused
         * @param bool $boolean
         * @return $this
         * @throws Exception
         */
        public function if_not_exists(bool $boolean = true)
        {
            if ( $this->if_exists === true) {
                throw new Exception("The 'if_exists' parameter is already set.");
            }

            $this->if_not_exists = $boolean;
            return $this;
        }

        /**
         * Calls the action registered by the on function
         *
         * @noinspection PhpUnused
         * @return bool|string
         */
        public function get_sql_query()
        {
            return $this->sql_query;
        }

        /**
         * Checks if the last request has an error
         *
         * @noinspection PhpUnused
         * @return bool
         * @throws ErrorException
         */
        private function sql_has_no_error()
        {
            global $phpsqlgoose_connection;

            if ($this->offset and !$this->limit) {
                throw new ErrorException("The offset parameter cannot be used without the limit parameter");
            }

            if (!($phpsqlgoose_connection instanceof Connection)) {
                throw new ErrorException("No connection to the database established");
            }

            return empty(mysqli_error($phpsqlgoose_connection->get_connection()));
        }

        /**
         * FORMULAS FOR PIP FUNCTIONS ARE NOT SAFE, SCREEN QUERIES WITHIN FORMULAS YOURSELF
         *
         * @noinspection PhpUnused
         * @param $alias
         * @param $expression
         * @return $this
         * @throws Exception
         */
        public function pipe($alias, $expression)
        {
            array_push($this->pipes_aliases, $alias);
            $alias = $this->input_value_to_database($this->schema, '~hidden', $alias);
            array_push($this->pipes, "$expression AS $alias");
            return $this;
        }
    }

}

namespace phpsqlgoose\Builder {

    /**
     * Class Fluid
     * @noinspection PhpUnused
     * @package phpgoose
     * @method Fluid type(string $type) The AVG function returns the average value of an expression.
     * @method Fluid size(number $size) The AVG function returns the average value of an expression.
     * @method Fluid constraint(bool $constraint) The AVG function returns the average value of an expression.
     * @method Fluid primary(bool $primary) The AVG function returns the average value of an expression.
     * @method Fluid unique(bool $unique) The AVG function returns the average value of an expression.
     * @method Fluid default(bool $default) The AVG function returns the average value of an expression.
     * @method Fluid null(bool $null) The AVG function returns the average value of an expression.
     * @method Fluid not_null(bool $not_null) The AVG function returns the average value of an expression.
     * @method Fluid auto_increment(bool $auto_increment) The AVG function returns the average value of an expression.
     * @method Fluid comment(string $comment) The AVG function returns the average value of an expression.
     * @method Fluid regex(string $regex) The AVG function returns the average value of an expression.
     * @method Fluid min(string $min) The AVG function returns the average value of an expression.
     * @method Fluid max(string $max) The AVG function returns the average value of an expression.
     */
    class Fluid
    {
        private $fluid = array();

        /**
         * is triggered when invoking inaccessible methods in an object context.
         *
         * @param $name string
         * @param $arguments array
         * @return mixed
         * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.methods
         */
        public function __call($name, $arguments)
        {
            $this->fluid[$name] = $arguments[0];
            return $this;
        }

        /**
         * is utilized for reading data from inaccessible members.
         *
         * @param $name string
         * @return mixed
         * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
         */
        public function __get($name)
        {
            return $this->fluid[$name];
        }

        /**
         * run when writing data to inaccessible members.
         *
         * @param $name string
         * @param $value mixed
         * @return void
         * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
         */
        public function __set($name, $value)
        {
            $this->fluid[$name] = $value;
        }

        /**
         * is triggered by calling isset() or empty() on inaccessible members.
         *
         * @param $name string
         * @return bool
         * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
         */
        public function __isset($name)
        {
            return isset($this->fluid[$name]);
        }

        /**
         * is invoked when unset() is used on inaccessible members.
         *
         * @param $name string
         * @return void
         * @link https://php.net/manual/en/language.oop5.overloading.php#language.oop5.overloading.members
         */
        public function __unset($name)
        {
            unset($this->fluid[$name]);
        }

        /**
         * @return array
         */
        public function to_array(): array
        {
            return $this->fluid;
        }
    }

}

namespace phpsqlgoose\Engine\Hook {

    /**
     * Class Hook
     * @package Engine\Hook
     */
    class Hook {
        /**
         * @var array
         */
        protected $callbacks;

        /**
         * Hook constructor.
         * @param array $callbacks
         */
        public function __construct($callbacks = []) {
            $this->callbacks = [];

            if (!isset($callbacks) || !is_array($callbacks) || sizeof($callbacks)) {
                return;
            }

            foreach ($callbacks as $k => $v) {
                if (!is_string($k) || !isset($v) || !is_callable($v)) {
                    continue;
                }

                $this->callbacks[$k] = $v;
            }
        }

        /**
         * Hooks a function or method to a specific action.
         * @param $key
         * @param $callback
         */
        public function add_hook($key, $callback) {
            if (!isset($key) || !isset($callback) || !is_string($key) || !is_callable($callback)) {
                return;
            }

            $this->callbacks[$key] = $callback;
        }

        /**
         * Unhooks a function or method from a specific action.
         * @param $key
         * @access public
         * @noinspection PhpUnused
         */
        public function remove_hook($key) {
            if ($this->exists_hook($key)) {
                unset($this->callbacks[$key]);
            }
        }

        /**
         *  Determines whether an offset hook.
         * @param $key
         * @return bool
         */
        public function exists_hook($key) {
            return isset($key) && isset($this->callbacks[$key]);
        }

        /**
         * Calls the callback functions that have been added to an action hook.
         * @param $key
         * @param mixed ...$args
         * @return null
         */
        public function run_hook($key, ...$args) {
            if ($this->exists_hook($key)) {
                $func = $this->callbacks[$key];

                if (!isset($args) || !is_array($args)) {
                    $args = [];
                }

                if (isset($func)) {
                    return $func(...$args);
                }
            }

            return null;
        }
    }
}

namespace phpsqlgoose\Engine\Parser {

    use ErrorException;
    use Exception;
    use phpsqlgoose\Engine\Operator\Operator;
    use phpsqlgoose\Schema;
    use function phpsqlgoose\Engine\bind;
    
    /**
     * Class ParserOperators
     * @package Engine\Parser
     */
    class ParserOperators extends Operator {

        /**
         * @param Schema $schema
         * @param string $fluid
         * @param array $array
         * @return array
         * @throws Exception
         */
        public function parse_operators(Schema $schema, string $fluid, array $array) {
            $blocks = [];

            foreach ((array) $array as $key => $value) {
                if ($this->is_operator($key)) {
                    if ($this->is_negative_operator($key)) {
                        $results = $this->handle_operator($schema, $fluid, $key, $value);

                        if (is_string($results) and $results !== false) {
                            array_push($blocks, bind('(NOT $s)', [
                                '$s' => [
                                    'value' => $results,
                                    'escape' => false
                                ]
                            ]));
                        } else {
                            throw new Exception("Operator $key is not supported");
                        }

                    } else {
                        if (!is_array($value)) {
                            $value = [$value];
                        }

                        $results = $this->handle_operator($schema, $fluid, $key, $value);

                        if (is_string($results) and $results !== false) {
                            $results = $this->handle_operator($schema, $fluid, $key, $value);
                            array_push($blocks, $results);

                        } else {
                            throw new Exception("Operator $key is not supported");
                        }

                    }

                } else {
                    throw new Exception("Operator $key is not supported");
                }
            }

            return $blocks;
        }

    }

    /**
     * Class ParserWhile
     * @package Engine\Parser
     */
    class ParserWhile extends ParserOperators {

        /**
         * @param Schema $schema
         * @param array $query
         * @return bool|string
         * @throws ErrorException
         * @throws Exception
         * @throws Exception
         * @throws Exception
         */
        public function parse_while(Schema $schema, array $query) {
            $expression = array();

            foreach ($query as $fluid => $condition) {
                if ($this->is_operator($fluid)) {
                    throw new ErrorException("The top level field $fluid cannot be an operator");
                } else {
                    if (is_array($condition)) {
                        $result = $this->parse_operators($schema, $fluid, $condition);

                        if (!empty($result)) {
                            array_push($expression, implode(" AND ", $result));
                        }
                    } else {
                        $condition = $this->input_value_to_database($schema, $fluid, $condition);
                        array_push($expression, "$fluid = $condition");
                    }
                }
            }

            # Cleaning the array of empty elements
            $expression = array_filter($expression, function($element) {
                return !empty($element);
            });

            if (!empty($expression)) {
                return implode(" AND ", $expression);
            } else {
                return null;
            }
        }
    }

    /**
     * Class ParserSelect
     * @package Engine\Parser
     */
    class ParserSelect extends ParserWhile {

        /**
         * @param Schema $schema
         * @param string $select
         * @return string
         */
        public function parse_select(Schema $schema, string $select) {
            $select = [$select ? $select : '*'];

            foreach ($schema->get_keys() as $key) {
                if (isset($schema->{$key}->type)) {
                    if (in_array($schema->{$key}->type, [TYPE_POINT])) {
                        array_push($select, "X($key) as \"$key.X\"");
                        array_push($select, "Y($key) as \"$key.Y\"");
                    } elseif (in_array($schema->{$key}->type, [TYPE_LINESTRING])) {
                        array_push($select, "AsText($key) as \"$key::LineString\"");
                    }
                }
            }

            return implode(", ", $select);
        }
    }

    /**
     * Class ParserUpdate
     * @package Engine\Parser
     */
    class ParserUpdate extends ParserWhile {

        /**
         * @param Schema $schema
         * @param array $query
         * @return string
         * @throws Exception
         */
        public function parse_update(Schema $schema, array $query)
        {
            $update = array();

            foreach ($query as $key => $value) {
                if (is_array($value)) {
                    array_push($update, bind('$key = $value', [
                        '$key' => [
                            'value' => $key,
                            'escape' => false
                        ],
                        '$value' => [
                            'value' => implode(', ', (array) $this->parse_operators($schema, $key, $value)),
                            'escape' => false
                        ]
                    ]));

                } else {
                    $value = $this->input_value_to_database($schema, $key, $value);

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

    }
}

namespace phpsqlgoose\Engine\IO {

    use Exception;
    use phpsqlgoose\Engine\Hook\Hook;
    use phpsqlgoose\RegexException;
    use phpsqlgoose\Schema;
    use phpsqlgoose\Wrapper\File;
    use phpsqlgoose\Wrapper\LineString;
    use phpsqlgoose\Wrapper\Point;
    use phpsqlgoose\Wrapper\Time;
    use TypeError;
    use function phpsqlgoose\Engine\bind;
    use function phpsqlgoose\Engine\sql_escape_string;

    /**
     * Global handler class for all database data outputs and inputs
     * @package phpsqlgoose\Engine\IO
     */
    class IO extends Hook {

        /**
         * Filters the values before adding them to the database
         *
         * @param Schema $schema
         * @param string $fluid
         * @param $value
         * @param bool $validate
         * @return array|bool|false|float|int|string|string[]|Point|Time
         * @throws Exception
         */
        public function input_value_to_database(Schema $schema, string $fluid, $value, $validate = true) {
            if ($this->exists_hook('save_' . $fluid)) {
                $value = $this->run_hook('save_' . $fluid, $value);
            }

            // Check the field with the filter: does the type of data transmitted match the type declared in the schema?
            if (isset($schema->{$fluid}) and $validate) {

                if (isset($schema->{$fluid}) and isset($schema->{$fluid}->default)) {
                    $type = (string) gettype($value);

                    if (
                        $schema->{$fluid}->type == TYPE_STRING or
                        $schema->{$fluid}->type == TYPE_TEXT or
                        $schema->{$fluid}->type == TYPE_PASSWORD or
                        $schema->{$fluid}->type == TYPE_VARCHAR
                    ) {
                        if (!is_string($value)) {
                            throw new TypeError("The $fluid key expects data of type string, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_INT or
                        $schema->{$fluid}->type == TYPE_SMALLINT or
                        $schema->{$fluid}->type == TYPE_BIGINT
                    ) {
                        if (!is_numeric($value)) {
                            throw new TypeError("The $fluid expects data of type int, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_DOUBLE
                    ) {
                        if (!is_double($value)) {
                            throw new TypeError("The $fluid key expects data of type double, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_TIME or
                        $schema->{$fluid}->type == TYPE_DATE or
                        $schema->{$fluid}->type == TYPE_DATETIME or
                        $schema->{$fluid}->type == TYPE_TIMESTAMP
                    ) {
                        if (!($value instanceof Time)) {
                            throw new TypeError("The $fluid key expects data of type Time, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_POINT
                    ) {
                        if (!($value instanceof Point)) {
                            throw new TypeError("The $fluid key expects data of type Point, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_LINESTRING
                    ) {
                        if (!($value instanceof LineString)) {
                            throw new TypeError("The $fluid key expects data of type Linestring, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_BLOB or
                        $schema->{$fluid}->type == TYPE_MEDIUMBLOB or
                        $schema->{$fluid}->type == TYPE_LONGBLOB
                    ) {
                        if (!($value instanceof File)) {
                            throw new TypeError("The $fluid key expects data of type File, but receives a $type");
                        }
                    } elseif (
                        $schema->{$fluid}->type == TYPE_OBJECT
                    ) {
                        if (!is_array($value) and !is_object($value)) {
                            throw new TypeError("The $fluid key expects data of type object or array, but receives a $type");
                        }
                    } else {
                        return false;
                    }
                }

                if (isset($schema->{$fluid}->{"@validate"})) {
                    var_dump('Validate');
                }

                // Check the field with a filter: does it satisfy regex?
                if (isset($schema->{$fluid}->regex)) {
                    preg_match($schema->{$fluid}->regex, strval($value), $matches);

                    if (empty($matches)) {
                        throw new RegexException("The data ({$value}) in key {$fluid} do not match the regular expression {$schema->{$fluid}->regex}");
                    }
                }

                // Check the field with a filter: is it larger than the expected value?
                if (isset($schema->{$fluid}->min)) {
                    if (in_array($schema->{$fluid}->type, [TYPE_INT, TYPE_BIGINT, TYPE_DOUBLE, TYPE_SMALLINT]) and is_numeric($value) and intval($value) < intval($schema->{$fluid}->min)) {
                        throw new RegexException("Number {$value} less than the minimum value is " . intval($schema->{$fluid}->min));
                    } elseif (in_array($schema->{$fluid}->type, [TYPE_STRING, TYPE_TEXT, TYPE_VARCHAR, TYPE_LONGTEXT, TYPE_MEDIUMTEXT, TYPE_PASSWORD, TYPE_LINESTRING, TYPE_URL]) and is_string($value) and strlen($value) < intval($schema->{$fluid}->min)) {
                        throw new RegexException("String length {$value} less than the minimum value is " . intval($schema->{$fluid}->min));
                    } elseif (in_array($schema->{$fluid}->type, [TYPE_OBJECT]) and is_array($value) and count($value) < intval($schema->{$fluid}->min)) {
                        throw new RegexException("Array length {$value} less than the minimum value is " . intval($schema->{$fluid}->min));
                    }
                }

                // Check the field with a filter: is it less than the expected value?
                if (isset($schema->{$fluid}->max)) {
                    if (in_array($schema->{$fluid}->type, [TYPE_INT, TYPE_BIGINT, TYPE_DOUBLE, TYPE_SMALLINT]) and is_numeric($value) and intval($value) > intval($schema->{$fluid}->max)) {
                        throw new RegexException("Number {$value} more than the minimum value is " . intval($schema->{$fluid}->max));
                    } elseif (in_array($schema->{$fluid}->type, [TYPE_STRING, TYPE_TEXT, TYPE_VARCHAR, TYPE_LONGTEXT, TYPE_MEDIUMTEXT, TYPE_PASSWORD, TYPE_LINESTRING, TYPE_URL]) and is_string($value) and strlen($value) > intval($schema->{$fluid}->max)) {
                        throw new RegexException("String length {$value} more than the minimum value is " . intval($schema->{$fluid}->max));
                    } elseif (in_array($schema->{$fluid}->type, [TYPE_OBJECT]) and is_array($value) and count($value) > intval($schema->{$fluid}->max)) {
                        throw new RegexException("Array length {$value} more than the minimum value is " . intval($schema->{$fluid}->max));
                    }
                }

                // Checking and executing specific data handlers
                switch ($schema->{$fluid}->type) {
                    case TYPE_PASSWORD:
                        $value = bind("'\$s'", [
                            '$s' => [
                                'value' => md5($value),
                                'escape' => false
                            ]
                        ]);
                        break;
                    case TYPE_OBJECT:
                        $value = json_encode($value);
                        break;
                    case TYPE_POINT:
                        $value = "POINT({$value->get_latitude()}, {$value->get_longitude()})";
                        break;
                    case TYPE_LINESTRING:
                        $value = bind("LineStringFromText('LINESTRING(\$s)')", [
                            '$s' => [
                                'value' => implode(', ', array_map(function (Point $point) {
                                    return "{$point->get_latitude()} {$point->get_longitude()}";
                                }, $value->get_points())),
                                'escape' => false
                            ]
                        ]);
                        break;
                    case TYPE_BLOB:
                    case TYPE_MEDIUMBLOB:
                    case TYPE_LONGBLOB:
                        if ($value instanceof File) {
                            $value = bind("'\$s'", [
                                '$s' => [
                                    'value' => sql_escape_string($value
                                        ->read()
                                        ->get_binary()),
                                    'escape' => false
                                ]
                            ]);
                        }
                        break;
                    case TYPE_TEXT:
                    case TYPE_DATETIME:
                    case TYPE_DATE:
                        if ($value instanceof Time) {
                            $value = bind("'\$s'", [
                                '$s' => [
                                    'value' => $value->get_date(),
                                    'escape' => false
                                ]
                            ]);
                        }
                        break;

                    default:
                        if ($value === 0) {
                            $value = 0;
                        }

                        if (is_numeric($value)) {
                            $value = (int) $value;
                        }

                        if (is_int($value)) {
                            $value = (int) $value;
                        }

                        if (is_string($value)) {
                            $value = "'" . sql_escape_string($value) . "'";
                        }

                        if (is_bool($value)) {
                            $value = boolval($value);
                        }
                }
            }

            return $value;
        }

        /**
         * Filters values from the database before outputting them to the function
         *
         * @param Schema $schema
         * @param array $rows
         * @param array $filter
         * @return array|mixed
         * @throws Exception
         */
        public function output_rows_from_database(Schema $schema, array $rows, array $filter) {
            foreach ($rows as $key => $value) {
                if (!empty($filter)) {
                    if (!in_array($key, $filter)) {
                        unset($rows[$key]);
                        continue;
                    }
                }

                if (isset($schema->{$key}) and isset($schema->{$key}->type)) {
                    switch ($schema->{$key}->type) {
                        case TYPE_VARCHAR:
                        case TYPE_TEXT:
                        case TYPE_PASSWORD:
                        case TYPE_MEDIUMTEXT:
                        case TYPE_LONGTEXT:
                        case TYPE_STRING:
                            $rows[$key] = stripslashes($value);
                            break;
                        case TYPE_OBJECT:
                            $rows[$key] = json_decode($value);
                            break;
                        case TYPE_DATE:
                        case TYPE_TIMESTAMP:
                        case TYPE_DATETIME:
                        case TYPE_TIME:
                            $rows[$key] = new Time($value);
                            break;
                        case TYPE_LONGBLOB:
                        case TYPE_MEDIUMBLOB:
                        case TYPE_BLOB:
                            $rows[$key] = (new File())
                                ->set_binary($value);
                            break;
                        case TYPE_SMALLINT:
                        case TYPE_BIGINT:
                        case TYPE_INT:
                            $rows[$key] = intval($value);
                            break;
                        case TYPE_DOUBLE:
                            $rows[$key] = doubleval($value);
                            break;
                        case TYPE_BOOLEAN:
                            $rows[$key] = boolval($value);
                            break;
                        case TYPE_POINT:
                            $rows[$key] = new Point(intval($rows["$key.X"]), intval($rows["$key.Y"]));
                            unset($rows["$key.X"]);
                            unset($rows["$key.Y"]);
                            break;
                        case TYPE_LINESTRING:
                            preg_match_all('#LINESTRING\((.+?)\)#is', $rows["$key::LineString"], $out);

                            if (isset($out[1][0]) and !empty($out[1][0])) {
                                $rows[$key] = new LineString(...array_map(function ($point) {
                                    $point = explode(' ', $point);
                                    if (isset($point[0]) and isset($point[1])) {
                                        return new Point(intval($point[0]), intval($point[1]));
                                    } else {
                                        return null;
                                    }
                                }, explode(',', $out[1][0])));
                                unset($rows["$key::LineString"]);
                            }

                            break;
                        default:
                            throw new Exception('Unexpected type');
                    }
                }
            }

            if (count($rows) == 1) {
                $array = array_values($rows);
                $rows = end($array);
            }

            return $rows;
        }

    }

}


namespace phpsqlgoose\Engine\Operator {

    use Exception;
    use phpsqlgoose\Engine\IO\IO;
    use phpsqlgoose\Schema;
    use function phpsqlgoose\Engine\bind;

    /**
     * Based on the template, provide a ready-made SQL query section
     *
     * @param Engine $engine Class Engine
     * @param Schema $schema Class Schema
     * @param string $key Field to be specified in the SQL query
     * @param array $query Request for processing as an array
     * @param string $template The template itself, on which the output will be prepared
     * @return string|string[]
     * @throws Exception
     * @noinspection PhpUnused
     */
    function template(Engine $engine, Schema $schema, string $key, $query, string $template) {
        $sql_query_array = array();

        if (is_array($query)) {
            foreach ($query as $variant => $array) {
                array_push($sql_query_array, bind($template, [
                    '$key' => $key,
                    '$value' => [
                        'value' => $engine->input_value_to_database($schema, $key, $array),
                        'escape' => false
                    ],
                    '$implode' => implode(array_map(function ($e) use ($engine, $schema, $key) {
                        return $engine->input_value_to_database($schema, $key, $e);
                    }, $query))
                ]));
            }
        }

        return bind('($s)', [
            '$s' => [
                'value' => implode(' OR ', $sql_query_array),
                'escape' => false
            ]
        ]);
    }

    /**
     * Class Engine
     * @package Engine\Operator
     */
    class Engine extends IO {

        /**
         * Connects the two conditions and the records must satisfy one of these conditions
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function or(Schema $schema, string $key, array $query): string {
            $sql_query_array = array();

            if (is_array($query)) {
                foreach ($query as $variant) {
                    array_push($sql_query_array, bind('$key = $value', [
                        '$key' => $key,
                        '$value' => [
                            'value' => $this->input_value_to_database($schema, $key, $variant),
                            'escape' => false
                        ]
                    ]));
                }
            }

            return bind('($s)', [
                '$s' => [
                    'value' => implode(' OR ', $sql_query_array),
                    'escape' => false
                ]
            ]);
        }

        /**
         * Determines size of elements that should be
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function len(Schema $schema, string $key, array $query): string {
            $sql_query_array = array();

            if (is_array($query)) {
                foreach ($query as $variant => $array) {
                    $comparison = false;

                    if ($variant == '$gt') {
                        $comparison = '>';
                    } elseif ($variant == '$lt') {
                        $comparison = '<';
                    } elseif ($variant == '$eq') {
                        $comparison = '=';
                    } elseif ($variant == '$gte') {
                        $comparison = '>=';
                    } elseif ($variant == '$lte') {
                        $comparison = '<=';
                    }

                    array_push($sql_query_array, bind(template($this, $schema, $key, $query, 'LENGTH($key) $comparison $value'), [
                        '$comparison' => $comparison
                    ]));
                }
            }

            return bind('($s)', [
                '$s' => [
                    'value' => implode(' OR ', $sql_query_array),
                    'escape' => false
                ]
            ]);
        }

        /**
         * more or equal
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function gte(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key >= $value');
        }

        /**
         * more
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function gt(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key > $value');
        }

        /**
         * less or equal
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function lte(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key <= $value');
        }

        /**
         * less
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function lt(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key < $value');
        }

        /**
         * equal
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function eq(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key = $value');
        }

        /**
         * The LIKE operator is used in a WHERE clause to search for a specified pattern in a column.
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function like(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key LIKE $value');
        }

        /**
         * Defines an array of values, one of which must have a record field
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function in(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, '$key IN ($implode)');
        }

        /**
         * The BETWEEN statement selects values within a specified range. These values can be numbers, text or dates.
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @noinspection PhpUnused
         */
        protected function btw(Schema $schema, string $key, array $query): string {

            $engine = $this;
            return bind('$key BETWEEN $value', [
                '$key' => $key,
                '$value' => [
                    'value' => implode(" AND ", array_map(function ($e) use ($key, $schema, $engine) {
                        return $engine->input_value_to_database($schema, $key, $e);
                    }, $query)),
                    'escape' => false
                ]
            ]);

        }

        /**
         * does not equal
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @noinspection PhpUnused
         */
        protected function ne(Schema $schema, string $key, array $query): string {
            $engine = $this;
            return bind('$key IN ($value)', [
                '$key' => $key,
                '$value' => [
                    'value' => implode(", ", array_map(function ($e) use ($key, $schema, $engine) {
                        return $engine->input_value_to_database($schema, $key, $e);
                    }, $query)),
                    'escape' => false
                ]
            ]);
        }

        /**
         * This operator is used to increment the value of the field by the specified amount.
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @noinspection PhpUnused
         */
        protected function inc(Schema $schema, string $key, array $query): string {
            $engine = $this;
            return bind('$key + $value', [
                '$key' => $key,
                '$value' => [
                    'value' => implode(", ", array_map(function ($e) use ($key, $schema, $engine) {
                        return $engine->input_value_to_database($schema, $key, $e);
                    }, $query)),
                    'escape' => false
                ]
            ]);
        }

        /**
         * This operator is used to multiply the value of the field by the specified amount.
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string|string[]
         * @noinspection PhpUnused
         */
        protected function mul(Schema $schema, string $key, array $query): string {
            $engine = $this;
            return bind('$key * $value', [
                '$key' => $key,
                '$value' => [
                    'value' => implode(", ", array_map(function ($e) use ($key, $schema, $engine) {
                        return $engine->input_value_to_database($schema, $key, $e);
                    }, $query)),
                    'escape' => false
                ]
            ]);
        }

        /**
         * The records must NOT meet the condition
         *
         * @param Schema $schema
         * @param string $key
         * @param array $query
         * @return string
         * @throws Exception
         * @noinspection PhpUnused
         */
        protected function not(Schema $schema, string $key, array $query): string {
            return template($this, $schema, $key, $query, 'NOT $key = $value');
        }

    }

    /**
     * Class Operator
     * A class of operators for creating SQL queries
     * @package phpgoose
     */
    abstract class Operator extends Engine
    {

        /**
         * Operator marking by access level
         */
        const MARKUP = [
            'public' => [
                'and',
                'or',
                'not'
            ],
            'private' => [
                'not',
                'or',
                'len',
                'gte',
                'lte',
                'gt',
                'lt',
                'eq',
                'ne',
                'like',
                'in',
                'btw'
            ],
            'update' => [
                'inc',
                'mul',
            ]
        ];

        /**
         * Get all operators
         *
         * @param string $type Type of operators received: private or public or all
         * @return array
         * @noinspection PhpUnused
         */
        protected function get_all_operators ($type = 'all') {
            $return = array();
            if ($type == 'private') {
                $MARKUP = (array) self::MARKUP['private'];
                array_walk_recursive($MARKUP, function($a) use (&$return) { $return[] = $a; });

            } elseif ($type == 'public') {
                $MARKUP = (array) self::MARKUP['public'];
                array_walk_recursive($MARKUP, function($a) use (&$return) { $return[] = $a; });

            } elseif ($type == 'all') {
                $MARKUP = (array) self::MARKUP;
                array_walk_recursive($MARKUP, function($a) use (&$return) { $return[] = $a; });

            }

            return $return;
        }

        /**
         * Wrap the operator in "operator" markup
         *
         * @param string $operator
         * @return string
         * @noinspection PhpUnused
         */
        protected function wrap_operator(string $operator) {
            if (substr($operator, 0, 1 ) === "$") {
                return $operator;
            } else {
                return "$$operator";
            }
        }

        /**
         * Unfold the operator and strip it of its "operator" markup
         *
         * @param string $operator
         * @return string|string[]
         * @noinspection PhpUnused
         */
        protected function unwrap_operator(string $operator) {
            if (substr($operator, 0, 1 ) === "$") {
                return str_replace('$', '', $operator);
            } elseif (substr($operator, 0, 2 ) === "!$") {
                return str_replace('!$', '', $operator);
            } else {
                return $operator;
            }
        }

        /**
         * Checks whether the string is an operator
         *
         * @param string $operator
         * @return bool
         * @noinspection PhpUnused
         */
        protected function is_operator(string $operator) {
            return in_array((string) $this->unwrap_operator($operator), $this->get_all_operators('all'));
        }

        /**
         * Checks if the string is a negative operator
         *
         * @param string $operator
         * @return bool
         * @noinspection PhpUnused
         */
        protected function is_negative_operator(string $operator) {
            if (in_array((string) $this->unwrap_operator($operator), $this->get_all_operators('all'))) {
                if (substr($operator, 0, 2 ) === "!$") {
                    return true;
                }
            }

            return false;
        }

        /**
         * Process a specific operator
         *
         * @param Schema $schema Class Schema
         * @param string $key The field in relation to which the operator operates
         * @param string $operator Operator
         * @param array $query Query as an array
         * @return mixed
         * @noinspection PhpUnused
         */
        protected function handle_operator(Schema $schema, string $key, string $operator, array $query) {
            if ($this->is_operator($operator)){
                if (method_exists($this, $this->unwrap_operator($operator))) {
                    return $this->{$this->unwrap_operator($operator)}($schema, strval($key), (array) $query);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

    }

}


namespace phpsqlgoose\Helpers {

    /**
     * Class Pipe
     * @noinspection PhpUnused
     * @package phpgoose
     * @method static string avg(string|number $formula) The AVG function returns the average value of an expression.
     * @method static string count(string|number $fluid_or_int) The COUNT function returns the count of an expression.
     * @method static string min(string|number $fluid_or_int) The MIN function returns the minimum value of an expression.
     * @method static string max(string|number $fluid_or_int) The MAX function returns the maximum value of an expression.
     * @method static string sum(string|number $fluid_or_int) The SUM function returns the summed value of an expression.
     * @method static string mod(string|number $fluid_or_int_1, string|number $fluid_or_int_2) The MOD function finds the remainder of one number divided by another.
     * @method static string X(string $fluid) Get the X coordinate from a string of type Point
     * @method static string Y(string $fluid) Get the Y coordinate from a string of type Point
     */
    abstract class Pipe {

        /**
         * @param string $name
         * @param array $formulas
         * @return string
         */
        public static function __callStatic(string $name, array $formulas)
        {
            $name = (string) strtoupper($name);
            $formulas = implode(", ", $formulas);
            return "{$name}({$formulas})";
        }
    }

    /**
     * Value generator
     * @package phpgoose
     */
    abstract class Generator
    {

        /**
         * Generate standard record ID
         *
         * @noinspection PhpUnused
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
         * @noinspection PhpUnused
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
         * @noinspection PhpUnused
         */
        public static function UUID(): string
        {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),

                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),

                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,

                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,

                // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
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
         * @param string $alphabet A string of all possible characters
         *                         to select from
         * @return string
         * @throws ErrorException|Exception
         * @noinspection PhpUnused
         */
        public static function password(int $length, string $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
        {
            $str = '';
            $max = mb_strlen($alphabet, '8bit') - 1;

            if ($max < 1) {
                throw new ErrorException('$alphabet must be at least two characters long');
            }

            for ($i = 0; $i < $length; ++$i) {
                $str .= $alphabet[random_int(0, $max)];
            }
            return $str;
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

}

namespace phpsqlgoose\Wrapper {

    use \Exception;
    use TypeError;

    /**
     * Class Time
     *
     * @noinspection PhpUnused
     * @package phpgoose
     */
    class Time
    {
        const FORMAT_DATE = 'Y-m-d H:i:s';

        private $unix = 0;
        private $date = "";

        /**
         * Time constructor.
         * @param int $unix
         */
        public function __construct($unix = -1)
        {
            if ($unix == -1) {
                $unix = time();
            }

            if (is_numeric($unix)) {
                $this->unix = $unix;
                $this->date = gmdate(Time::FORMAT_DATE, $this->unix);

            } elseif (is_string($unix)) {
                $parsed = date_parse_from_format(Time::FORMAT_DATE, $unix);
                $this->unix = mktime($parsed['hour'], $parsed['minute'], $parsed['second'], $parsed['month'], $parsed['day'], $parsed['year']);
                $this->date = gmdate(Time::FORMAT_DATE, $this->unix);
            }
        }

        public function __toString()
        {
            return strval($this->unix);
        }

        /**
         * @return false|int|string
         */
        public function get_unix()
        {
            return $this->unix;
        }

        /**
         * @return false|string
         */
        public function get_date()
        {
            return $this->date;
        }


    }

    /**
     * SQL coordinate
     *
     * @noinspection PhpUnused
     * @package phpgoose
     */
    class Point
    {

        private $latitude;
        private $longitude;

        public function __construct(int $latitude = 0, int $longitude = 0)
        {
            $this->latitude = $latitude;
            $this->longitude = $longitude;
        }

        /**
         * @return int
         */
        public function get_latitude()
        {
            return $this->latitude;
        }

        public function get_longitude()
        {
            return $this->longitude;
        }
    }

    /**
     * Class LineString
     *
     * @noinspection PhpUnused
     * @package Wrapper
     */
    class LineString
    {

        private $points = [];

        public function __construct(...$points)
        {
            foreach ($points as $point) {
                if ($point instanceof Point) {
                    array_push($this->points, $point);
                } elseif (is_array($point)) {
                    array_push($this->points, new Point($point[0], $point[1]));
                } else {
                    throw new TypeError('The coordinate list must contain Point objects or be an array of two numbers of X and Y coordinates');
                }
            }
        }

        /**
         * @return array
         */
        public function get_points()
        {
            return $this->points;
        }
    }

    /**
     * Class File
     *
     * @package Wrapper
     */
    class File {

        private $binary;
        private $filename;

        /**
         * @link https://php.net/manual/en/language.oop5.decon.php
         * @param $file
         * @throws Exception
         */
        public function __construct(string $file = null)
        {
            if ($file !== null) {
                if (preg_match('~[^\x20-\x7E\t\r\n]~', $file) > 0) {
                    $this->binary = $file;
                } elseif (file_exists($file)) {
                    $this->filename = $file;
                } else {
                    throw new Exception("File $file not found");
                }
            }
        }

        /**
         * @return mixed
         */
        public function read()
        {
            $this->binary = file_get_contents($this->filename);
            return $this;
        }

        /**
         * @param string $filename
         * @return mixed
         */
        public function write(string $filename)
        {
            file_put_contents($filename, $this->binary);
            return $this;
        }


        /**
         * @return mixed
         */
        public function get_filename()
        {
            return $this->filename;
        }


        /**
         * @return mixed
         */
        public function get_binary()
        {
            return $this->binary;
        }

        /**
         * @param string $binary
         * @return File
         */
        public function set_binary(string $binary): File
        {
            $this->binary = $binary;
            return $this;
        }

        /**
         * @param string $filename
         * @return File
         */
        public function set_filename(string $filename): File
        {
            $this->filename = $filename;
            return $this;
        }
    }

}


namespace phpsqlgoose\Types {

    /**
     * Type to create a record ID
     *
     * @noinspection PhpUnused
     * @return array
     */
    function ID()
    {
        return array(
            'constraint' => false,
            'primary' => true,
            'type' => TYPE_BIGINT,
            'auto_increment' => true,
            'comment' => 'ID',
            'size' => false,
            'not_null' => true
        );
    }

    /**
     * Type for creating an encrypted password
     *
     * @noinspection PhpUnused
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
     *
     * @noinspection PhpUnused
     * @return array
     */
    function DateTime()
    {
        return array(
            'constraint' => false,
            'primary' => false,
            'type' => TYPE_DATETIME,
            'auto_increment' => false,
            'comment' => 'Time',
            'not_null' => true
        );
    }

    /**
     * Type to create email
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Email()
    {
        return array(
            'comment' => 'Email',
            'not_null' => true,
            'type' => TYPE_STRING,
            'regex' => REGEX_EMAIL,
            'unique' => true,
            'min' => 8,
            'max' => 32
        );
    }

    /**
     * Type to create nickname
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Nickname()
    {
        return array(
            'comment' => 'Nickname',
            'not_null' => true,
            'type' => TYPE_STRING,
            'regex' => REGEX_NICKNAME,
            'unique' => true,
            'min' => 4,
            'max' => 32
        );
    }

    /**
     * Type to create text
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Text()
    {
        return array(
            'comment' => 'Text',
            'not_null' => true,
            'type' => TYPE_TEXT,
        );
    }

    /**
     * Type to create number
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Number()
    {
        return array(
            'comment' => 'Number',
            'not_null' => true,
            'type' => TYPE_INT,
        );
    }

    /**
     * Type to create point
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Point()
    {
        return array(
            'comment' => 'Point',
            'not_null' => true,
            'type' => TYPE_POINT,
        );
    }

    /**
     * Type to create linestring
     *
     * @noinspection PhpUnused
     * @return array
     */
    function LineString()
    {
        return array(
            'comment' => 'Point',
            'not_null' => true,
            'type' => TYPE_LINESTRING,
        );
    }

    /**
     * Type to create blob
     *
     * @noinspection PhpUnused
     * @return array
     */
    function Blob()
    {
        return array(
            'comment' => 'Blob',
            'null' => true,
            'type' => TYPE_BLOB,
        );
    }

    /**
     * Type to create phone
     *
     * @noinspection PhpUnused
     * @return array
     */
    function PhoneNumber()
    {
        return array(
            'comment' => 'Phone Number',
            'not_null' => true,
            'type' => TYPE_VARCHAR,

            '@validate' => function($number) {
                $formats = [
                    '###-###-####', '####-###-###',
                    '(###) ###-###', '####-####-####',
                    '##-###-####-####', '####-####', '###-###-###',
                    '#####-###-###', '##########', '#########',
                    '# ### #####', '#-### #####'
                ];

                return in_array(
                    trim(preg_replace('/[0-9]/', '#', $number)),
                    $formats
                );
            }
        );
    }

}


namespace phpsqlgoose\Engine {

    use phpsqlgoose\Connection;

    /**
     * Shield the SQL string
     *
     * @param string $string
     * @return false|string
     */
    function sql_escape_string(string $string)
    {
        global $phpsqlgoose_connection;

        if ($phpsqlgoose_connection instanceof Connection) {
            $string = mysqli_real_escape_string($phpsqlgoose_connection->get_connection(), $string);
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
    function bind(string $string, array $data)
    {
        $sql = str_replace(array_keys($data), array_map(function ($e) {
            if (is_array($e) or is_object($e)) {
                $e = (array)$e;

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
    
}