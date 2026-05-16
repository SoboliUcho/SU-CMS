<?php
namespace Core;
use mysqli;

/**
 * Třída pro práci s databází
 * 
 */
class Db
{
    private static $connection;
    private static $construct;
    private static $db_structure;
    private static $debug = false;

    /**
     * Připojí se k databázi
     * 
     * @param string $host Hostitel databáze
     * @param string $database Název databáze
     * @param string $user Uživatel databáze
     * @param string $password Heslo k databázi
     * @param string $db_construct Cesta k json souboru s konstrukcí databáze
     */

    public static function connect($host, $database, $user, $password, $db_construct = null)
    {
        self::$connection = new mysqli($host, $user, $password, $database);
        if (self::$connection->connect_error) {
            throw new \RuntimeException("Database connection failed: " . self::$connection->connect_error);
        }
        self::$connection->set_charset("utf8mb4");
        if ($db_construct != null) {
            self::$construct = $db_construct;
            self::constructDb();
        } else {
            // self::get_structure();
        }

        if (isset($GLOBALS['sql_debug'])) {
            self::$debug = (bool) $GLOBALS['sql_debug'];
        }
        if (isset($GLOBALS['config']['debuging_setting']['sql_debug']) && $GLOBALS['config']['debuging_setting']['sql_debug']) {
            self::$debug = (bool) $GLOBALS['config']['debuging_setting']['sql_debug'];
        }
    }

    /**
     * Vytvoří databázi a tabulky podle json struktury.
     *
     * @param array|string|null $structure Pole se strukturou nebo cesta k JSON souboru.
     */
    public static function constructDb($structure = null)
    {
        if ($structure !== null) {
            self::$construct = $structure;
        }
        if (self::$construct == null) {
            self::$construct = __DIR__ . '/db_structure_default.json';
        }

        $db_structure = self::resolveDbStructure(self::$construct);
        self::$db_structure = $db_structure;

        self::$connection->query("CREATE DATABASE IF NOT EXISTS `" . $db_structure["database"] . "`");
        self::$connection->select_db($db_structure["database"]);

        foreach ($db_structure["tables"] as $table) {
            // Kontrola, zda tabulka existuje
            $table_exists = self::$connection->query("SHOW TABLES LIKE '" . $table["name"] . "'");

            if ($table_exists->num_rows > 0) {
                // Tabulka existuje, použij ALTER TABLE pro přidání chybějících sloupců
                foreach ($table["columns"] as $column) {
                    $column_exists = self::$connection->query("SHOW COLUMNS FROM `" . $table["name"] . "` LIKE '" . $column["name"] . "'");

                    if ($column_exists->num_rows == 0) {
                        // Sloupec neexistuje, přidej ho
                        $query = "ALTER TABLE `" . $table["name"] . "` ADD COLUMN `" . $column["name"] . "` " . $column["type"];

                        if (isset($column["length"])) {
                            $query .= "(" . $column["length"] . ")";
                        }
                        if (isset($column["null"])) {
                            $query .= " " . $column["null"];
                        }
                        if (isset($column["default"])) {
                            $query .= " DEFAULT " . $column["default"];
                        }
                        if (isset($column["auto_increment"])) {
                            $query .= " AUTO_INCREMENT";
                        }
                        if (isset($column["after"])) {
                            $query .= " AFTER `" . $column["after"] . "`";
                        }
                        if (isset($column["coment"])) {
                            $query .= " COMMENT '" . $column["coment"] . "'";
                        }

                        self::$connection->query($query);
                    }
                }
            } else {
                // Tabulka neexistuje, vytvoř ji včetně všech sloupců
                $query = "CREATE TABLE `" . $table["name"] . "` (";

                foreach ($table["columns"] as $column) {
                    $query .= "`" . $column["name"] . "` " . $column["type"];

                    if (isset($column["length"])) {
                        $query .= "(" . $column["length"] . ")";
                    }
                    if (isset($column["null"])) {
                        $query .= " " . $column["null"];
                    }
                    if (isset($column["default"])) {
                        $query .= " DEFAULT " . $column["default"];
                    }
                    if (isset($column["auto_increment"])) {
                        $query .= " AUTO_INCREMENT";
                    }
                    if (isset($column["coment"])) {
                        $query .= " COMMENT '" . $column["coment"] . "'";
                    }

                    $query .= ",";
                }

                // Přidej klíče
                if (isset($table["keys"])) {
                    foreach ($table["keys"] as $key) {
                        if ($key["type"] == "primary") {
                            $query .= "PRIMARY KEY (";
                        } else {
                            $query .= "KEY `" . $key["name"] . "` (";
                        }

                        foreach ($key["columns"] as $column) {
                            $query .= "`" . $column . "`,";
                        }
                        $query = rtrim($query, ",");
                        $query .= "),";
                    }
                }

                $query = rtrim($query, ",");
                $query .= ")";

                self::$connection->query($query);
            }
        }
    }

    private static function resolveDbStructure($structure): array
    {
        if (is_array($structure)) {
            return $structure;
        }

        if (!is_string($structure) || $structure === '') {
            throw new \InvalidArgumentException('Chyba: Neplatná databázová struktura.');
        }

        $dbStructure = file_get_contents($structure);
        if ($dbStructure === false) {
            throw new \RuntimeException('Chyba: Nepodařilo se načíst databázovou strukturu.');
        }

        $decoded = json_decode($dbStructure, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Chyba: Databázová struktura není validní JSON.');
        }

        return $decoded;
    }

    /**
     * Vrátí strukturu databáze
     */
    public static function get_structure()
    {
        $db_structure = ["database" => self::$connection->query("SELECT DATABASE()")->fetch_row()[0]];
        $tables = self::$connection->query("SHOW TABLES");

        while ($table = $tables->fetch_row()) {
            $table_name = $table[0];
            $columns_result = self::$connection->query("SHOW FULL COLUMNS FROM `" . $table_name . "`");
            $table_structure = ["name" => $table_name, "columns" => []];

            $previous_column = null;
            while ($column = $columns_result->fetch_assoc()) {
                // Parsování typu a délky
                preg_match('/^([a-z]+)(\(([0-9,]+)\))?/i', $column["Type"], $matches);
                $type = strtoupper($matches[1]);
                $length = isset($matches[3]) ? $matches[3] : null;

                $column_data = [
                    "name" => $column["Field"],
                    "type" => $type
                ];

                if ($length !== null) {
                    $column_data["length"] = $length;
                }

                if ($column["Null"] == "NO") {
                    $column_data["null"] = "NOT NULL";
                }

                if ($column["Default"] !== null) {
                    $column_data["default"] = is_numeric($column["Default"]) ? $column["Default"] : "'" . $column["Default"] . "'";
                }

                if (strpos($column["Extra"], "auto_increment") !== false) {
                    $column_data["auto_increment"] = true;
                }

                if ($previous_column !== null) {
                    $column_data["after"] = $previous_column;
                }

                if (!empty($column["Comment"])) {
                    $column_data["coment"] = $column["Comment"];
                }

                $table_structure["columns"][] = $column_data;
                $previous_column = $column["Field"];
            }

            // Získání klíčů
            $keys_result = self::$connection->query("SHOW KEYS FROM `" . $table_name . "`");
            $keys_data = [];

            while ($key = $keys_result->fetch_assoc()) {
                $key_name = $key["Key_name"];

                if (!isset($keys_data[$key_name])) {
                    $keys_data[$key_name] = [
                        "name" => $key_name,
                        "type" => $key_name == "PRIMARY" ? "primary" : "index",
                        "columns" => []
                    ];
                }

                $keys_data[$key_name]["columns"][] = $key["Column_name"];
            }

            if (!empty($keys_data)) {
                $table_structure["keys"] = array_values($keys_data);
            }

            $db_structure["tables"][] = $table_structure;
        }

        self::$db_structure = $db_structure;

        if (self::$debug) {
            print_r($db_structure);
            echo json_encode($db_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $db_structure;
    }

    // /**
    //  * Vrátí strukturu databáze
    //  */
    // public static function get_structure()
    // {
    //     $db_structure = [];
    //     $tables = self::$connection->query("SHOW TABLES");
    //     while ($table = $tables->fetch_row()) {
    //         $table_name = $table[0];
    //         $columns = self::$connection->query("SHOW COLUMNS FROM " . $table_name);
    //         $table_structure = ["name" => $table_name];
    //         while ($column = $columns->fetch_assoc()) {
    //             $table_structure["columns"][$column["Field"]] = [
    //                 "name" => $column["Field"],
    //                 "type" => $column["Type"],
    //                 "null" => $column["Null"],
    //                 "default" => $column["Default"],
    //                 "key" => $column["Key"],
    //                 "extra" => $column["Extra"]
    //             ];
    //         }
    //         $db_structure["tables"][$table_name] = $table_structure;
    //     }
    //     self::$db_structure = $db_structure;
    //     if (self::$debug) {
    //         print_r($db_structure);
    //         echo json_encode($db_structure);
    //     }
    // }

    /**
     * Uloží strukturu databáze do json souboru
     * @param string $path Cesta k souboru výchocí je db_structure_pull.json
     * @return void
     */
    public static function save_structure($path = "db_structure_pull.json")
    {
        if (!self::$db_structure) {
            self::get_structure();
        }
        if (!self::$db_structure) {
            throw new \RuntimeException("Chyba: Struktura databáze není načtena.");
        }
        if (!is_string($path) || empty($path)) {
            throw new \InvalidArgumentException("Chyba: Neplatná cesta k souboru.");
            // $path = "db_structure_pull.json";
        }
        file_put_contents($path, json_encode(self::$db_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }


    /**
     * Normalizuje hodnoty parametrů pro bind_param.
     *
     * @param array $params
     * @return array
     */
    private static function normalizeParams(array $params): array
    {
        $normalized = [];
        foreach ($params as $param) {
            if (is_bool($param)) {
                $normalized[] = $param ? 1 : 0;
                continue;
            }

            $normalized[] = $param;
        }

        return $normalized;
    }

    private static function paramTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        return $types;
    }

    private static function executePrepared(string $sql, array $params = [])
    {
        if (!self::$connection instanceof mysqli) {
            throw new \RuntimeException('Database connection is not initialized.');
        }

        if ($params === []) {
            $result = self::$connection->query($sql);
            if ($result === false) {
                throw new \RuntimeException('SQL query failed: ' . self::$connection->error . '; SQL: ' . $sql);
            }
            return $result;
        }

        $statement = self::$connection->prepare($sql);
        if ($statement === false) {
            throw new \RuntimeException('SQL prepare failed: ' . self::$connection->error . '; SQL: ' . $sql);
        }

        $params = self::normalizeParams($params);
        $types = self::paramTypes($params);

        $bindParams = [$types];
        foreach ($params as $index => $value) {
            $bindParams[] = &$params[$index];
        }

        if (!call_user_func_array([$statement, 'bind_param'], $bindParams)) {
            $error = $statement->error;
            $statement->close();
            throw new \RuntimeException('SQL bind_param failed: ' . $error . '; SQL: ' . $sql);
        }

        if (!$statement->execute()) {
            $error = $statement->error;
            $statement->close();
            throw new \RuntimeException('SQL execute failed: ' . $error . '; SQL: ' . $sql);
        }

        $result = $statement->get_result();
        if ($result instanceof \mysqli_result) {
            $statement->close();
            return $result;
        }

        $statement->close();
        return true;
    }

    /**
     * Provede dotaz a vrátí výsledek
     * @param string $sql SQL dotaz 
    * @param mixed ...$prarams Parametry dotazu
     * @return object $result Výsledek dotazu
     */
    public static function query($sql, ...$prarams)
    {
        return self::executePrepared($sql, $prarams);
    }

    /**
     * Provede dotaz a vrátí první sloupec prvního řádku.
     *
     * @param string $sql SQL dotaz
     * @param mixed ...$prarams Parametry dotazu
     * @return mixed
     */
    public static function querySingle($sql, ...$prarams)
    {
        $result = self::executePrepared($sql, $prarams);
        if (!$result instanceof \mysqli_result) {
            return null;
        }
        $row = $result->fetch_row();
        return $row[0] ?? null;
    }

    /**
     * Provede dotaz a vrátí první řádek výsledku
     * @param string $sql SQL dotaz 
    * @param mixed ...$prarams Parametry dotazu
     * @return array $row První řádek výsledku
     */
    public static function queryOne($sql, ...$prarams)
    {
        $result = self::executePrepared($sql, $prarams);
        if (!$result instanceof \mysqli_result) {
            return [];
        }
        $row = $result->fetch_assoc();
        return $row ?: [];
    }

    /**
     * Provede dotaz a vrátí všechny řádky výsledku
     * @param string $sql SQL dotaz 
    * @param mixed ...$prarams Parametry dotazu
     * @return array $rows Všechny řádky výsledku
     */
    public static function queryAll($sql, ...$prarams)
    {
        $result = self::executePrepared($sql, $prarams);
        $rows = [];
        if (!$result instanceof \mysqli_result) {
            return $rows;
        }
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Provede vložení a vrátí id vloženého řádku
     * @param string $sql SQL dotaz 
    * @param mixed ...$prarams Parametry dotazu
     * @return int $id Id prvního vloženého řádku
     */
    public static function queryInsert($sql, ...$prarams)
    {
        if (stripos($sql, "INSERT") === false) {
            throw new \InvalidArgumentException("Chyba SQL dotazu: INSERT dotaz musí obsahovat klíčové slovo INSERT");
        }
        self::query($sql, ...$prarams);
        return self::$connection->insert_id;
    }

    /**
     * Provede aktualizaci a vrátí počet ovlivněných řádků
     * @param string $sql SQL dotaz 
    * @param mixed ...$params Parametry dotazu
     * @return int $affected_rows Počet ovlivněných řádků
     */
    public static function queryUpdate($sql, ...$params)
    {
        if (stripos($sql, "UPDATE") === false) {
            throw new \InvalidArgumentException("Chyba SQL dotazu: UPDATE dotaz musí obsahovat klíčové slovo UPDATE");
        }
        self::query($sql, ...$params);
        return self::$connection->affected_rows;
    }

    /**
     * Provede smazání a vrátí počet ovlivněných řádků
     * @param string $sql SQL dotaz 
    * @param mixed ...$params Parametry dotazu
     * @return int $affected_rows Počet ovlivněných řádků
     */
    public static function queryDelete($sql, ...$params)
    {
        if (stripos($sql, "DELETE") === false) {
            throw new \InvalidArgumentException("Chyba SQL dotazu: DELETE dotaz musí obsahovat klíčové slovo DELETE");
        }
        self::query($sql, ...$params);
        return self::$connection->affected_rows;
    }

    /**
     * Escapuje řetězec
     * @param string|null $string Řetězec k escapování nebo NULL
     * @return string $string Escapovaný řetězec
     */
    public static function escape($string)
    {
        if ($string === null) {
            return 'NULL';
        }
        return self::$connection->real_escape_string($string);
    }

    /**
     * Vrátí poslední id
     * @return int $id Poslední id
     */
    public static function getLastId()
    {
        return self::$connection->insert_id;
    }

    /**
     * Vrátí počet ovlivněných řádků
     * @return int $affected_rows Počet ovlivněných řádků
     */
    public static function getAffectedRows()
    {
        return self::$connection->affected_rows;
    }

    /**
     * Vrátí chybu
     * @return string $error Chyba
     */
    public static function getError()
    {
        return self::$connection->error;
    }

    /**
     * Vrátí chybové číslo
     * @return int $error Chybové číslo
     */
    public static function getErrorNo()
    {
        return self::$connection->errno;
    }

    /**
     * Uzavře spojení s databází
     */

    public static function close()
    {
        self::$connection->close();
    }

    /**
     * Vrátí aktuální časový razítko
     * @return int $timestamp Časové razítko
     */
    public static function debugTimestamp()
    {
        return (strtotime(date('his')));
    }

}