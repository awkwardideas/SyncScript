<?php
namespace AwkwardIdeas\SyncScript;

use AwkwardIdeas\MyPDO\MyPDO as DB;
use AwkwardIdeas\MyPDO\SQLParameter;

class SyncScript{
    private $connection = [
        "host"=>"",
        "database"=>"",
        "username"=>"",
        "password"=>""
    ];
    private $backup;
    private $process=false;
    private $db;

    public function __construct()
    {
        self::GetConnectionData();
        $this->db = new DB();
    }

    private function GetConnectionData(){
        $filePath = getcwd().'/.env';
        if (file_exists($filePath)) {
            $handle = @fopen($filePath, "r");
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $value = self::GetEnvVariable("DB_HOST", $buffer);
                    if ($value !== false){
                        $this->connection["host"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_DATABASE", $buffer);
                    if ($value !== false){
                        $this->connection["database"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_USERNAME", $buffer);
                    if ($value !== false){
                        $this->connection["username"] = $value;
                        continue;
                    }
                    $value = self::GetEnvVariable("DB_PASSWORD", $buffer);
                    if ($value !== false){
                        $this->connection["password"] = $value;
                        continue;
                    }
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            }
        }

        if($this->connection["host"]!="" && $this->connection["database"]!="" && $this->connection["username"]!="" && $this->connection["password"]!="") $this->process=true;
    }

    private function GetEnvVariable($variableName, $buffer){
        if (strpos(strtoupper($buffer), $variableName."=") > -1) {
            $removeFromFileValue = "/[\n\r]/";
            return preg_replace($removeFromFileValue, '', after("=", $buffer));
        }else{
            return false;
        }
    }

    private function EstablishConnection(){
        if(!$this->process)
            return "<p>Required connection data not found in .env</p>";

        if($this->db->EstablishConnections($this->GetHost(), $this->GetDatabase(), $this->GetUsername(), $this->GetPassword(), $this->GetUsername(), $this->GetPassword()))
            return "<p>Connected to <b>".$this->GetDatabase()."</b> on <b>".$this->GetHost()."</b>.</p>";
        else
            return "<p>Unable to connect. Please verify permissions.</p>";
    }

    private function CloseConnection(){
        $this->db->CloseConnections();
        $this->process = false;
    }

    public function GetHost(){
        return $this->connection["host"];
    }

    public function GetDatabase(){
        return $this->connection["database"];
    }

    public function SetDatabase($database){
        $this->CloseConnection();
        $this->connection["database"] =$database;
        $this->process = true;
        $this->EstablishConnection();
    }

    public function GetUsername(){
        return $this->connection["username"];
    }

    public function GetPassword(){
        return $this->connection["password"];
    }

    public function GetBackup(){
        return $this->backup;
    }
    public function SetBackup($backup){
        $this->backup = $backup;
    }

    private static function GetSyncDirectory(){
        return getcwd().'/database/sync/';
    }

    public function GetTables(){
        $query = "show tables;";
        $tables = $this->db->Query($query);
        return $tables;
    }

    public function DescribeTable($tablename){
        $query = "describe `" . $tablename . "`;";
        $columns = $this->db->Query($query);
        return $columns;
    }

    private function GetSelectAllStatement($tablename, $columns, $indentation){
        $select = $indentation . "SELECT "
                  . self::ListColumnsWithTable($tablename, $columns, $indentation.indent(), false) . PHP_EOL
                  . $indentation . "FROM `" . $this->GetDatabase() . "`.`" . $tablename ."`;";
        return $select;
    }

    private function GetInsertStatement($tablename, $columns, $indentation){

        $insert = $indentation . "INSERT IGNORE INTO `" . $this->GetBackup() . "`.`" . $tablename ."`" . PHP_EOL
                  .self::ListColumns($columns, $indentation.indent(), true);
        return $insert;
    }

    private function ListColumnsWithTable($tablename="", $columns, $indentation, $wrap){
        $return = "";
        if($wrap) $return.="(";
        foreach($columns as $columndata){
            if($tablename!="") $return .= "`".$tablename."`.";
            $return.="`".$columndata["Field"]."`,".PHP_EOL;
        }
        $return=rtrim($return,",".PHP_EOL);
        if($wrap) $return.=")";
        return $return;
    }

    private function ListColumns($columns, $indentation, $wrap){
        return self::ListColumnsWithTable("",$columns, $indentation, $wrap);
    }

    public static function Generate($database, $backup)
    {
        $syncScript = new SyncScript();
        if ($database != "") {
            $syncScript->SetDatabase($database);
        }
        if ($backup != "") {
            $syncScript->SetBackup($backup);
        }
        $tables = $syncScript->GetTables();
        $fullSync = "";
        foreach ($tables as $table) {
            $tablename = $table[0];
            $fullSync .= $syncScript->CreateSyncScript($tablename) . PHP_EOL . PHP_EOL . PHP_EOL;
        }
        $syncScript->CreateFullSyncScript($fullSync);
        return "New SyncScript Files Created in " . self::GetSyncDirectory();
    }
    public function CreateFullSyncScript($fullSync){
        $dir = self::GetSyncDirectory();
        $fileName = $this->GetFileName("all");

        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);

        return file_put_contents("$dir/$fileName", $fullSync);
    }

    public function CreateSyncScript($tablename){
        $fileData = $this->GetFileOutput($tablename);
        $fileName = $this->GetFileName($tablename);
        $dir = $this->GetSyncDirectory();

        $parts = explode('/', $dir);
        $file = array_pop($parts);
        $dir = '';
        foreach($parts as $part)
            if(!is_dir($dir .= "/$part")) mkdir($dir);

        file_put_contents("$dir/$fileName", $fileData);

        return $fileData;
    }

    private function ConnectToDatabase(){
        $this->db = new DB();
        $output="";
        if($this->db->EstablishConnections($this->GetHost(), $this->GetDatabase(), $this->GetUsername(), $this->GetPassword(), $this->GetUsername(), $this->GetPassword()))
            $output.= "<p>Connected to <b>".$this->GetDatabase()."</b> on <b>".$this->GetHost()."</b>.</p>";
        else
            $output.= "<p>Unable to connect. Please verify permissions.</p>";
        return $output;
    }

    private function GetFileName($tablename){
        $d = date('Y_m_d_His');
        return $d . "_sync_" . $tablename . "_table.php";
    }

    private function GetFileOutput($tablename)
    {
        $columns = $this->DescribeTable($tablename);
        $insertStatement = $this->GetInsertStatement($tablename, $columns, indent());
        $selectStatement = $this->GetSelectAllStatement($tablename, $columns, indent());
        $output = "-- MySQL Sync Script for " . $tablename . PHP_EOL
            . "-- Host: " . $this->GetHost() . "   Database: " . $this->GetDatabase() . "   Backup: " . $this->GetBackup() . PHP_EOL
            . PHP_EOL
            . $insertStatement . PHP_EOL
            . $selectStatement;
        return $output;
    }

    private function CommentTableStructure($columns, $indentation){
        $output = $indentation . "/**" . PHP_EOL
            . $indentation . " *" . PHP_EOL;
        foreach ($columns as $columndata) {
            $output .= $indentation . " * " . $columndata["Field"] . "	" . $columndata["Type"] . "	" . $columndata["Null"] . "	" . $columndata["Key"] . "	" . $columndata["Default"] . "	" . $columndata["Extra"] . "	" . PHP_EOL;
        }
        $output .= $indentation . " *" . PHP_EOL
            . $indentation . " */" . PHP_EOL;

        return $output;
    }

    private function GetSchemaNotExists($tablename, $columns, $indentation)
    {
        $schemaCreateWrapInject = "";
        $schemaTableWrapInject = "";
        $output="";
        $tabledata = [
            "foreignKeys" => [],
            "primaryKeys" => [],
            "indexes" => [],
            "uniques" => [],
            "autoIncrement" => []
        ];

        //Loop through the columns and collect information about the columns
        foreach ($columns as $columndata) {
            $schemaCreateWrapInject .= $indentation . indent() . self::AddColumnByDataType($columndata) . ';' . PHP_EOL;
            if (strpos(strtoupper($columndata["Extra"]), "AUTO_INCREMENT") > -1) {
                $tabledata["autoIncrement"][] = $columndata["Field"];
            }
            if (strpos(strtoupper($columndata["Key"]), "PRI") > -1 && ($columndata["Extra"] == "" || strpos(strtoupper($columndata["Extra"]), "AUTO_INCREMENT") == -1)) {
                $tabledata["primaryKeys"][] = $columndata["Field"];
            }
            if (strpos(strtoupper($columndata["Key"]), "MUL") > -1) {
                $tabledata["foreignKeys"][] = self::GetForeignKeys($tablename, $columndata["Field"], $indentation . indent());
            }
        }
        $inheritUnique = array_merge($tabledata["autoIncrement"], $tabledata["primaryKeys"]);

        $tabledata["indexes"][] = self::GetIndexes($tablename, $inheritUnique, $indentation . indent());
        $tabledata["uniques"][] = self::GetUniques($tablename, $inheritUnique, $indentation . indent());
        if (count($tabledata["primaryKeys"]) > 0 && count($tabledata["autoIncrement"]) == 0) {
            $identifierName = self::GetIdentifier($tablename, implode("_", $tabledata["primaryKeys"]), "primary");
            if (count($tabledata["primaryKeys"]) == 1) {
                $schemaTableWrapInject .= $indentation . indent() . '$table->primary(\'' . implode($tabledata["primaryKeys"]) . '\',\'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $schemaTableWrapInject .= $indentation . indent() . '$table->primary([\'' . implode('\',\'', $tabledata["primaryKeys"]) . '\'],\'' . $identifierName . '\');' . PHP_EOL;
            }
        }
        $tabledata["foreignKeys"] = array_filter($tabledata["foreignKeys"]);
        if (count($tabledata["foreignKeys"]) > 0) {
            foreach ($tabledata["foreignKeys"] as $foreignKey) {
                $schemaTableWrapInject .= $foreignKey;
            }
        }
        $tabledata["indexes"] = array_filter($tabledata["indexes"]);
        if (count($tabledata["indexes"]) > 0) {
            foreach ($tabledata["indexes"] as $index) {
                $schemaTableWrapInject .= $index;
            }
        }
        $tabledata["uniques"] = array_filter($tabledata["uniques"]);
        if (count($tabledata["uniques"]) > 0) {
            foreach ($tabledata["uniques"] as $unique) {
                $schemaTableWrapInject .= $unique;
            }
        }
        $output .= self::SchemaCreateWrap($tablename, $schemaCreateWrapInject, $indentation);
        unset($schemaCreateWrapInject);
        $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, $indentation);
        unset($schemaTableWrapInject);

        return $output;
    }

    private function GetSchemaExists($tablename, $columns, $indentation)
    {
        $tabledata = [
            "foreignKeys" => [],
            "primaryKeys" => [],
            "indexes" => [],
            "uniques" => [],
            "autoIncrement" => []
        ];
        $schemaTableWrapInject = "";
        $output = "";

        foreach ($columns as $columndata) {
            $output .=  self::GetSchemaNotHasColumn($tablename, $columndata, $tabledata, $indentation.indent(3));
        }
        $inheritUnique = array_merge($tabledata["autoIncrement"], $tabledata["primaryKeys"]);

        $tabledata["indexes"][] = self::GetIndexes($tablename, $inheritUnique, indent(5));
        $tabledata["uniques"][] = self::GetUniques($tablename, $inheritUnique, indent(5));

        if (count($tabledata["primaryKeys"]) > 0 && count($tabledata["autoIncrement"]) == 0) {
            $identifierName = self::GetIdentifier($tablename, implode("_", $tabledata["primaryKeys"]), "primary");
            if (count($tabledata["primaryKeys"]) == 1) {
                $schemaTableWrapInject .= indent(4) . '$table->primary(\'' . implode($tabledata["primaryKeys"]) . '\',\'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $schemaTableWrapInject .= indent(4) . '$table->primary([\'' . implode('\',\'', $tabledata["primaryKeys"]) . '\'],\'' . $identifierName . '\');' . PHP_EOL;
            }
            $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, indent(3));
            $schemaTableWrapInject = "";
        }

        $tabledata["foreignKeys"] = array_filter($tabledata["foreignKeys"]);
        if (count($tabledata["foreignKeys"]) > 0) {
            foreach ($tabledata["foreignKeys"] as $foreignKey) {
                $schemaTableWrapInject .= $foreignKey;
            }
            $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, indent(3));
            $schemaTableWrapInject = "";
        }

        $tabledata["indexes"] = array_filter($tabledata["indexes"]);
        if (count($tabledata["indexes"]) > 0) {
            foreach ($tabledata["indexes"] as $index) {
                $schemaTableWrapInject .= $index;
            }
            $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, indent(3));
            $schemaTableWrapInject = "";
        }

        $tabledata["uniques"] = array_filter($tabledata["uniques"]);
        if (count($tabledata["uniques"]) > 0) {
            foreach ($tabledata["uniques"] as $unique) {
                $schemaTableWrapInject .= $unique;
            }
            $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, indent(3));
            $schemaTableWrapInject = "";
        }

        return $output;
    }

    private function GetSchemaNotHasColumn($tablename, $columndata, &$tabledata, $indentation){
        $output = $indentation . 'if (!Schema::hasColumn(\'' . $tablename . '\', \'' . $columndata["Field"] . '\')) {' . PHP_EOL;
        $schemaTableWrapInject = $indentation . indent(2) . self::AddColumnByDataType($columndata) . ';' . PHP_EOL;
        $output .= self::SchemaTableWrap($tablename, $schemaTableWrapInject, $indentation . indent());

        if(strpos(strtoupper($columndata["Extra"]),"AUTO_INCREMENT") > -1){
            $tabledata["autoIncrement"][]=$columndata["Field"];
        }
        if(strpos(strtoupper($columndata["Key"]), "PRI") > -1 && strpos(strtoupper($columndata["Extra"]),"AUTO_INCREMENT") == -1){
            $tabledata["primaryKeys"][]=$columndata["Field"];
        }
        if (strpos(strtoupper($columndata["Key"]),"MUL") > -1) {
            $tabledata["foreignKeys"][]= self::GetForeignKeys($tablename, $columndata["Field"], $indentation .indent(2));
        }
        $output .= $indentation . '}' . PHP_EOL
            . PHP_EOL;

        return $output;
    }


    private function AddColumnByDataType($coldata)
    {
        $name = $coldata["Field"];
        $typedata = $coldata["Type"];
        $null = $coldata["Null"];
        $key = $coldata["Key"];
        $default = $coldata["Default"];
        $extra = $coldata["Extra"];

        $type = before('(', $typedata);
        $data = between('(', ')', $typedata);
        $info = after(')', $typedata);

        $migrationCall = '$table->';

        switch (strtoupper($type)) {
            //      $table->bigIncrements('id');	Incrementing ID (primary key) using a "UNSIGNED BIG INTEGER" equivalent.
            //      $table->bigInteger('votes');	BIGINT equivalent for the database.
            case 'BIGINT':
                if (strpos(strtoupper($extra),"AUTO_INCREMENT") > -1) {
                    $migrationCall .= 'bigIncrements(\'' . $name . '\')';
                } else {
                    $migrationCall .= 'bigInteger(\'' . $name . '\')';
                }
                break;
            //      $table->binary('data');	BLOB equivalent for the database.
            case 'BINARY':
                $migrationCall .= 'binary(\'' . $name . '\')';
                break;
            case 'BIT':
                $migrationCall .= 'boolean(\'' . $name . '\')';
                if($default!=""){
                    $default = (strpos($default,'0')>-1) ? "0" : "1";
                }
                break;
            //      $table->boolean('confirmed');	BOOLEAN equivalent for the database.
            case 'BOOLEAN':
                $migrationCall .= 'boolean(\'' . $name . '\')';
                break;
            //      $table->char('name', 4);	CHAR equivalent with a length.
            case 'CHAR':
                $migrationCall .= 'char(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->date('created_at');	DATE equivalent for the database.
            case 'DATE':
                $migrationCall .= 'date(\'' . $name . '\')';
                break;
            //      $table->dateTime('created_at');	DATETIME equivalent for the database.
            case 'DATETIME':
                $migrationCall .= 'dateTime(\'' . $name . '\')';
                break;
            //      $table->decimal('amount', 5, 2);	DECIMAL equivalent with a precision and scale.
            case 'DECIMAL':
                $migrationCall .= 'decimal(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->double('column', 15, 8);	DOUBLE equivalent with precision, 15 digits in total and 8 after the decimal point.
            case 'DOUBLE':
                $migrationCall .= 'double(\'' . $name . '\', ' . $data . ')';
                break;
            //      $table->enum('choices', ['foo', 'bar']);	ENUM equivalent for the database.
            case 'ENUM':
                $migrationCall .= 'enum(\'' . $name . '\', [' . $data . '])';
                break;
            //      $table->float('amount');	FLOAT equivalent for the database.
            case 'FLOAT':
                $migrationCall .= 'float(\'' . $name . '\')';
                break;
            //      $table->increments('id');	Incrementing ID (primary key) using a "UNSIGNED INTEGER" equivalent.
            //      $table->integer('votes');	INTEGER equivalent for the database.
            case 'INT':
                if (strpos(strtoupper($extra),"AUTO_INCREMENT") > -1) {
                    $migrationCall .= 'increments(\'' . $name . '\')';
                } else {
                    $migrationCall .= 'integer(\'' . $name . '\')';
                }
                break;
            //      $table->json('options');	JSON equivalent for the database.
            case 'JSON':
                $migrationCall .= 'json(\'' . $name . '\')';
                break;
            //      $table->jsonb('options');	JSONB equivalent for the database.
            case 'JSONB':
                $migrationCall .= 'jsonb(\'' . $name . '\')';
                break;
            //      $table->longText('description');	LONGTEXT equivalent for the database.
            case 'LONGTEXT':
                $migrationCall .= 'longText(\'' . $name . '\')';
                break;
            //      $table->mediumInteger('numbers');	MEDIUMINT equivalent for the database.
            case 'MEDIUMINT':
                $migrationCall .= 'mediumInteger(\'' . $name . '\')';
                break;
            //      $table->mediumText('description');	MEDIUMTEXT equivalent for the database.
            case 'MEDIUMTEXT':
                $migrationCall .= 'mediumText(\'' . $name . '\')';
                break;
            //      $table->morphs('taggable');	Adds INTEGER taggable_id and STRING taggable_type.
            case 'MORPHS':
                $migrationCall .= 'morphs(\'' . $name . '\')';
                break;
            //      $table->nullableTimestamps();	Same as timestamps(), except allows NULLs.
            case 'NULL_TIMESTAMPS':
                $migrationCall .= 'nullableTimestamps()';
                break;
            //      $table->rememberToken();	Adds remember_token as VARCHAR(100) NULL.
            case 'REMEMBER':
                $migrationCall .= 'rememberToken()';
                break;
            //      $table->smallInteger('votes');	SMALLINT equivalent for the database.
            case 'SMALLINT':
                $migrationCall .= 'smallInteger(\'' . $name . '\')';
                break;
            //      $table->softDeletes();	Adds deleted_at column for soft deletes.
            case 'SOFTDELETES':
                $migrationCall .= 'softDeletes()';
                break;
            //      $table->string('email');	VARCHAR equivalent column.
            //      $table->string('name', 100);	VARCHAR equivalent with a length.
            case 'VARCHAR':
                if ($data != "") {
                    $migrationCall .= 'string(\'' . $name . '\', ' . $data . ')';
                } else {
                    $migrationCall .= 'string(\'' . $name . '\')';
                }
                break;
            //      $table->text('description');	TEXT equivalent for the database.
            case 'TEXT':
                $migrationCall .= 'text(\'' . $name . '\')';
                break;
            //      $table->time('sunrise');	TIME equivalent for the database.
            case 'TIME':
                $migrationCall .= 'time(\'' . $name . '\')';
                break;
            //      $table->tinyInteger('numbers');	TINYINT equivalent for the database.
            case 'TINYINT':
                if($data==1){
                    $migrationCall .= 'boolean(\'' . $name . '\')';
                }else{
                    $migrationCall .= 'tinyInteger(\'' . $name . '\')';
                }
                break;
            //      $table->timestamp('added_on');	TIMESTAMP equivalent for the database.
            case 'TIMESTAMP':
                $migrationCall .= 'timestamp(\'' . $name . '\')';
                break;
            //      $table->timestamps();	Adds created_at and updated_at columns.
            case 'TIMESTAMPS':
                $migrationCall .= 'timestamps()';
                break;
            //      $table->uuid('id');
            case 'YEAR':
                $migrationCall .= 'tinyInteger(\'' . $name . '\')';
                break;
            case 'UUID':
                $migrationCall .= 'uuid(\'' . $name . '\')';
                break;
            default:
                return false;
        }

        if(strpos(strtoupper($info), " UNSIGNED") > -1){
            $migrationCall .= "->unsigned()";
        }

        if(strtoupper($null) == "YES"){
            $migrationCall .= "->nullable()";
        }

        if($default != ""){
            if($default=="CURRENT_TIMESTAMP"){
                $migrationCall .= "->useCurrent()";
                //Needs on update use current_timestamp feature if in extra
            }else{
                $migrationCall .= "->default('".addslashes($default)."')";
            }

        }

        return $migrationCall;
    }

    private function GetIndexes($tablename, $primaryKeys, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT DISTINCT GROUP_CONCAT(COLUMN_NAME) as COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND Non_unique=1 AND INDEX_NAME <> 'PRIMARY' GROUP BY INDEX_NAME;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $indexCall="";
        foreach($relations as $relation) {
            $columns = $relation['COLUMN_NAME'];
            if(in_array($columns, $primaryKeys)){
                continue;
            }
            $columns = array_filter(explode(",",$columns));
            $identifierName = self::GetIdentifier($tablename, implode("_", $columns), "index");
            if (count($columns) > 1) {

                $indexCall .= $indentation . '$table->index([\'' . implode("','", $columns) . '\'], \'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $indexCall .= $indentation . '$table->index(\'' . implode($columns) . '\', \'' . $identifierName . '\');' . PHP_EOL;
            }
        }
        return $indexCall;
    }

    private function GetUniques($tablename, $primaryKeys, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT DISTINCT GROUP_CONCAT(COLUMN_NAME) as COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND Non_unique=0 AND INDEX_NAME <> 'PRIMARY' GROUP BY INDEX_NAME;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $uniqueCall="";

        foreach($relations as $relation) {
            $columns = $relation['COLUMN_NAME'];
            if(in_array($columns, $primaryKeys)){
                continue;
            }
            $columns = array_filter(explode(",",$columns));
            $identifierName = self::GetIdentifier($tablename, implode("_", $columns), "unique");
            if (count($columns) > 1) {
                $uniqueCall .= $indentation . '$table->unique([\'' . implode("','", $columns) . '\'], \'' . $identifierName . '\');' . PHP_EOL;
            } else {
                $uniqueCall .= $indentation . '$table->unique(\'' . implode($columns) . '\', \'' . $identifierName . '\');' . PHP_EOL;
            }
        }
        return $uniqueCall;
    }

    private function GetForeignKeys($tablename, $columnname, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND COLUMN_NAME=:columnname AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME IS NOT NULL;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename), new SQLParameter(":columnname",$columnname)]);
        $foreignCall="";
        foreach($relations as $relation) {
            $foreignCall.= $indentation . '$table->foreign(\'' . $relation['COLUMN_NAME'] . '\')->references(\'' . $relation['REFERENCED_COLUMN_NAME'] . '\')->on(\'' . $relation['REFERENCED_TABLE_NAME'] . '\');' . PHP_EOL;
        }
        return $foreignCall;
    }

    private function DropForeignKeys($tablename, $indentation){
        $schemaname = $this->GetDatabase();
        $sqlQuery = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=:schemaname AND TABLE_NAME=:tablename AND REFERENCED_TABLE_NAME IS NOT NULL AND REFERENCED_COLUMN_NAME IS NOT NULL;";
        $relations = $this->db->Query($sqlQuery, [new SQLParameter(":schemaname",$schemaname), new SQLParameter(":tablename",$tablename)]);
        $foreignCall="";
        foreach($relations as $relation) {
            $foreignCall.= $indentation . indent() . '$table->dropForeign([\'' . $relation['COLUMN_NAME'] . '\']);' . PHP_EOL;
        }

        if($foreignCall!=""){
            $foreignCall = self::SchemaTableWrap($tablename,$foreignCall,$indentation);
        }

        return $foreignCall;
    }

    private function SchemaCreateWrap($tablename, $content, $indentation){
        $wrap = $indentation . 'Schema::create(\'' . $tablename . '\', function (Blueprint $table){' . PHP_EOL
            . $content
            . $indentation . '});' . PHP_EOL;

        return $wrap;
    }

    private function SchemaTableWrap($tablename, $content, $indentation){
        $wrap = $indentation . 'Schema::table(\'' . $tablename . '\', function ($table) {' . PHP_EOL
            . $content
            . $indentation . '});' . PHP_EOL;

        return $wrap;
    }

    private function GetIdentifier($tablename, $columns, $type){
        $maxCharacters = 60; //64, but reducing to avoid issues
        $identifier = $tablename."_".$columns."_".$type;
        if(strlen($identifier) > $maxCharacters){
            $constraint = strlen($tablename."_".$type);
            $columns = explode("_",$columns);
            $remainder = $maxCharacters - $constraint - count($columns);
            $permit = ($remainder - ($remainder % count($columns))) / count($columns);
            $identifier =$tablename."_";
            foreach($columns as $column){
                $identifier .= substr($column,0,$permit)."_";
            }
            $identifier .= $type;
        }
        return $identifier;
    }
}