<?php

namespace Essam\CompareDB\Services;

use Illuminate\Support\Facades\Storage;

class CompareDB extends Compare
{
    public array $sqlStatements = [];

    /**
     * Create tables that are compared.
     *
     * @return CompareDB
     */
    public function createTables(): CompareDB
    {
        $missingSourceTables = $this->getCompare()['source']['tables'] ?? [];
        $missingDestinationTables = $this->getCompare()['destination']['tables'] ?? [];

        if (count($missingSourceTables) > 0) {
            foreach ($missingSourceTables as $missingSourceTable => $missingSourceTableColumns) {
                $this->sqlStatements['source']['tables'][] = $this->createTableSql('source', $missingSourceTable);
            }
        }

        if (count($missingDestinationTables) > 0) {
            foreach ($missingDestinationTables as $missingDestinationTable => $missingDestinationTableColumns) {
                $this->sqlStatements['destination']['tables'][] = $this->createTableSql('destination', $missingDestinationTable);
            }
        }

        return $this;
    }

    /**
     * Get Sql Statements to create tables that are compared.
     *
     * @param string $connection
     * @param string $missingTable
     * @return string $sqlQuery
     */
    public function createTableSql(string $connection, string $missingTable): string
    {
        $columnsSql = '';
        foreach ($this->getCompare()[$connection]['tables'][$missingTable]['columns'] as $column) {
            $columnsSql .= "`$column->Field` $column->Type ";

            if ($column->Null !== null) {
                $columnNull = $column->Null === 'NO' ? 'NOT NULL' : 'NULL';
                $columnsSql .= "$columnNull ";
            }

            if ($column->Default !== null) {
                $columnsSql .= "DEFAULT '$column->Default' ";
            }

            if ($column->Extra !== null) {
                if ($column->Extra === 'auto_increment') {
                    $columnsSql .= "AUTO_INCREMENT PRIMARY KEY";
                }

                if ($column->Extra === 'on update CURRENT_TIMESTAMP') {
                    $columnsSql .= "ON UPDATE CURRENT_TIMESTAMP ";
                }
            }

            $columnsSql .= ', ';
        }

        $foreignKeysSql = '';

        if (isset($this->getCompare()[$connection]['tables'][$missingTable]['foreignKeys'])) {
            foreach ($this->getCompare()[$connection]['tables'][$missingTable]['foreignKeys'] as $foreignKey) {
                if ($foreignKey->REFERENCED_TABLE_NAME !== null && $foreignKey->REFERENCED_COLUMN_NAME !== null) {
                    $foreignKeysSql .= "CONSTRAINT `$foreignKey->CONSTRAINT_NAME` FOREIGN KEY (`$foreignKey->COLUMN_NAME`) REFERENCES `$foreignKey->REFERENCED_TABLE_NAME` (`$foreignKey->REFERENCED_COLUMN_NAME`)";
                }
            }
        }

        $sqlQuery = rtrim(($foreignKeysSql !== '') ? "$columnsSql $foreignKeysSql" : $columnsSql, ', ');

        return "CREATE TABLE IF NOT EXISTS `$missingTable` ($sqlQuery) ENGINE=InnoDB DEFAULT CHARSET=UTF8MB4 COLLATE=utf8mb4_general_ci;";
    }

    /**
     * Create columns that are compared.
     *
     * @return CompareDB
     */
    public function createColumns(): CompareDB
    {
        $missingSourceColumns = $this->getCompare()['source']['columns'] ?? [];
        $missingDestinationColumns = $this->getCompare()['destination']['columns'] ?? [];

        if (count($missingSourceColumns) > 0) {
            foreach ($missingSourceColumns as $missingSourceTable => $missingSourceTableColumns) {
                if (isset($missingSourceTableColumns['missing']) && count($missingSourceTableColumns['missing']) > 0) {
                    $this->columnSql('source', 'missing', $missingSourceTable, $missingSourceTableColumns['missing']);
                }

                if (isset($missingSourceTableColumns['different']) && count($missingSourceTableColumns['different']) > 0) {
                    $this->columnSql('source', 'different', $missingSourceTable, $missingSourceTableColumns['different']);
                }
            }
        }

        if (count($missingDestinationColumns) > 0) {
            foreach ($missingDestinationColumns as $missingDestinationTable => $missingDestinationTableColumns) {
                if (isset($missingDestinationTableColumns['missing']) && count($missingDestinationTableColumns['missing']) > 0) {
                    $this->columnSql('destination', 'missing', $missingDestinationTable, $missingDestinationTableColumns['missing']);
                }

                if (isset($missingDestinationTableColumns['different']) && count($missingDestinationTableColumns['different']) > 0) {
                    $this->columnSql('destination', 'different', $missingDestinationTable, $missingDestinationTableColumns['different']);
                }
            }
        }

        return $this;
    }

    /**
     * Get Sql Statements to add or modify columns that are compared.
     *
     * @param string $connection
     * @param string $type
     * @param string $table
     * @param array $columns
     * @return void
     */
    public function columnSql(string $connection, string $type, string $table, array $columns): void
    {
        $sqlQuery = [];
        if (count($columns) > 0) {
            if (isset($columns['attributes'])) {
                $columnSql = '';
                foreach ($columns['attributes'] as $column) {
                    $queryType  = $type === 'missing' ? 'ADD COLUMN' : 'MODIFY COLUMN';
                    $columnSql .= "$queryType {$this->createColumnsSql($column)}, ";
                }
                $sqlQuery = "ALTER TABLE `$table` " . rtrim($columnSql, ', ') . ';';
            }

            if (isset($columns['constraints'])) {
                $constraintSql = '';
                foreach ($columns['constraints'] as $constraint) {
                    $constraintSql .= $this->createConstraintsSql($type, $constraint);
                }

                if (!in_array($constraintSql, ['', " ", null], true)) {
                    $sqlQuery = "ALTER TABLE `$table` " . rtrim($constraintSql, ', ') . ';';
                }
            }

            if (!empty($sqlQuery)) {
                $this->sqlStatements[$connection]['columns'][$type][] = $sqlQuery;
            }
        }
    }

    /**
     * Create Columns SQL Statements.
     *
     * @param array $missingColumn
     * @return string $sqlQuery
     */
    public function createColumnsSql(array $missingColumn): string
    {
        $column = (object)$missingColumn;
        $sqlQuery = "`$column->Field` $column->Type ";

        if ($column->Null !== null) {
            $columnNull = $column->Null === 'NO' ? 'NOT NULL' : 'NULL';
            $sqlQuery .= "$columnNull ";
        }

        if ($column->Default !== null) {
            $sqlQuery .= "DEFAULT '$column->Default' ";
        }

        if ($column->Extra !== null) {
            if ($column->Extra === 'auto_increment') {
                $sqlQuery .= "AUTO_INCREMENT PRIMARY KEY";
            }

            if ($column->Extra === 'on update CURRENT_TIMESTAMP') {
                $sqlQuery .= "ON UPDATE CURRENT_TIMESTAMP ";
            }
        }

        return rtrim($sqlQuery, ', ');
    }

    /**
     * Get Sql Statements to create constraints that are compared.
     *
     * @param string $type
     * @param array $constraint
     * @return string $sqlQuery
     */
    public function createConstraintsSql(string $type, array $constraint): string
    {
        $sqlQuery = "";
        $constraint = (object)$constraint;

        // Check the type is missing or different
        if ($type === 'missing') {
            if ($constraint->CONSTRAINT_TYPE === 'PRIMARY KEY') {
                $sqlQuery = "ADD PRIMARY KEY (`$constraint->COLUMN_NAME`), ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'UNIQUE') {
                $sqlQuery = "ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` UNIQUE ($constraint->COLUMN_NAME), ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'FOREIGN KEY') {
                $sqlQuery = "ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` FOREIGN KEY (`$constraint->COLUMN_NAME`) REFERENCES `$constraint->REFERENCED_TABLE_NAME` (`$constraint->REFERENCED_COLUMN_NAME`) ON DELETE $constraint->DELETE_RULE ON UPDATE $constraint->UPDATE_RULE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'CHECK') {
                $sqlQuery = "ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` CHECK (`$constraint->COLUMN_NAME`) $constraint->CHECK_CLAUSE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'INDEX') {
                $sqlQuery = "ADD INDEX `$constraint->CONSTRAINT_NAME` (`$constraint->COLUMN_NAME`) USING $constraint->INDEX_TYPE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'FULLTEXT') {
                $sqlQuery = "ADD FULLTEXT `$constraint->CONSTRAINT_NAME` (`$constraint->COLUMN_NAME`) USING $constraint->INDEX_TYPE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'DEFAULT') {
                $sqlQuery = "$constraint->COLUMN_NAME SET DEFAULT $constraint->COLUMN_DEFAULT, ";
            }
        }

        if ($type === 'different') {
            if ($constraint->CONSTRAINT_TYPE === 'PRIMARY KEY') {
                $sqlQuery = "DROP PRIMARY KEY, ADD PRIMARY KEY (`$constraint->COLUMN_NAME`), ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'UNIQUE') {
                $sqlQuery = "DROP INDEX `$constraint->CONSTRAINT_NAME`, ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` UNIQUE ($constraint->COLUMN_NAME), ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'FOREIGN KEY') {
                $sqlQuery = "DROP FOREIGN KEY `$constraint->CONSTRAINT_NAME`, ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` FOREIGN KEY (`$constraint->COLUMN_NAME`) REFERENCES `$constraint->REFERENCED_TABLE_NAME` (`$constraint->REFERENCED_COLUMN_NAME`) ON DELETE $constraint->DELETE_RULE ON UPDATE $constraint->UPDATE_RULE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'CHECK') {
                $sqlQuery = "DROP CHECK `$constraint->CONSTRAINT_NAME`, ADD CONSTRAINT `$constraint->CONSTRAINT_NAME` CHECK (`$constraint->COLUMN_NAME`) $constraint->CHECK_CLAUSE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'INDEX') {
                $sqlQuery = "DROP INDEX `$constraint->CONSTRAINT_NAME`, ADD INDEX `$constraint->CONSTRAINT_NAME` (`$constraint->COLUMN_NAME`) USING $constraint->INDEX_TYPE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'FULLTEXT') {
                $sqlQuery = "DROP INDEX `$constraint->CONSTRAINT_NAME`, ADD FULLTEXT `$constraint->CONSTRAINT_NAME` (`$constraint->COLUMN_NAME`) USING $constraint->INDEX_TYPE, ";
            }

            if ($constraint->CONSTRAINT_TYPE === 'DEFAULT') {
                $sqlQuery = "$constraint->COLUMN_NAME SET DEFAULT $constraint->COLUMN_DEFAULT, ";
            }
        }

        return $sqlQuery;
    }

    /**
     * Create triggers that are compared.
     *
     * @return CompareDB
     */
    public function createTriggers(): CompareDB
    {
        $missingSourceTriggers = $this->getCompare()['source']['triggers'] ?? [];
        $missingDestinationTriggers = $this->getCompare()['destination']['triggers'] ?? [];

        if (count($missingSourceTriggers) > 0) {
            foreach ($missingSourceTriggers as $missingSourceTable => $missingSourceTrigger) {
                if (isset($missingSourceTrigger['missing']) && count($missingSourceTrigger['missing']) > 0) {
                    $this->triggerSql('source', 'missing', $missingSourceTable, $missingSourceTrigger['missing']);
                }

                if (isset($missingSourceTrigger['different']) && count($missingSourceTrigger['different']) > 0) {
                    $this->triggerSql('source', 'different', $missingSourceTable, $missingSourceTrigger['different']);
                }
            }
        }

        if (count($missingDestinationTriggers) > 0) {
            foreach ($missingDestinationTriggers as $missingDestinationTable => $missingDestinationTrigger) {
                if (isset($missingDestinationTrigger['missing']) && count($missingDestinationTrigger['missing']) > 0) {
                    $this->triggerSql('destination', 'missing', $missingDestinationTable, $missingDestinationTrigger['missing']);
                }

                if (isset($missingDestinationTrigger['different']) && count($missingDestinationTrigger['different']) > 0) {
                    $this->triggerSql('destination', 'different', $missingDestinationTable, $missingDestinationTrigger['different']);
                }
            }
        }

        return $this;
    }

    /**
     * Create trigger sql.
     *
     * @param string $connection
     * @param string $type
     * @param string $table
     * @param array $triggers
     * @return void
     */
    private function triggerSql(string $connection, string $type, string $table, array $triggers): void
    {
        $sqlQuery = '';
        foreach ($triggers as $trigger) {
            $trigger = (object) $trigger;

            $triggerStatement = $trigger->Statement;
            if (str_contains($trigger->Statement, 'BEGIN') && str_contains($trigger->Statement, 'END')) {
                $triggerStatement = str_replace(array('BEGIN', 'END'), '', $triggerStatement);
                if (str_contains($triggerStatement, ';')) {
                    $triggerStatement = str_replace(';', '', $triggerStatement);
                }
                $triggerStatement = trim($triggerStatement);
            }

            if ($type === 'missing') {
                $sqlQuery = "DELIMITER $$ CREATE TRIGGER `$trigger->Trigger` $trigger->Timing $trigger->Event ON `$table` FOR EACH ROW BEGIN $triggerStatement; END $$ DELIMITER ; ";
            }

            if ($type === 'different') {
                $createTrigger = "DELIMITER $$ CREATE TRIGGER `$trigger->Trigger` $trigger->Timing $trigger->Event ON `$table` FOR EACH ROW BEGIN $triggerStatement; END $$ DELIMITER ; ";
                $sqlQuery      = "DROP TRIGGER IF EXISTS `$trigger->Trigger`, $createTrigger";
            }
        }

        $this->sqlStatements[$connection]['triggers'][$type][] = rtrim($sqlQuery, ', ');
    }

    /**
     * Create procedures that are compared.
     *
     * @return CompareDB
     */
    public function createProcedures(): CompareDB
    {
        $missingSourceProcedures      = $this->getCompare()['source']['procedures'] ?? [];
        $missingDestinationProcedures = $this->getCompare()['destination']['procedures'] ?? [];

        if (count($missingSourceProcedures) > 0) {
            if (isset($missingSourceProcedures['missing']) && count($missingSourceProcedures['missing']) > 0) {
                foreach ($missingSourceProcedures['missing'] as $missingSourceProcedure) {
                    $this->procedureSql('source', 'missing', $missingSourceProcedure);
                }
            }

            if (isset($missingSourceProcedures['different']) && count($missingSourceProcedures['different']) > 0) {
                foreach ($missingSourceProcedures['different'] as $missingSourceProcedure) {
                    $this->procedureSql('source', 'different', $missingSourceProcedure);
                }
            }
        }

        if (count($missingDestinationProcedures) > 0) {
            if (isset($missingDestinationProcedures['missing']) && count($missingDestinationProcedures['missing']) > 0) {
                foreach ($missingDestinationProcedures['missing'] as $missingDestinationProcedure) {
                    $this->procedureSql('destination', 'missing', $missingDestinationProcedure);
                }
            }

            if (isset($missingDestinationProcedures['different']) && count($missingDestinationProcedures['different']) > 0) {
                foreach ($missingDestinationProcedures['different'] as $missingDestinationProcedure) {
                    $this->procedureSql('destination', 'different', $missingDestinationProcedure);
                }
            }
        }

        return $this;
    }

    /**
     * Create procedure sql.
     *
     * @param string $connection
     * @param string $type
     * @param array $procedure
     * @return void
     */
    private function procedureSql(string $connection, string $type, array $procedure): void
    {
        $sqlQuery = '';
        $procedure = (object) $procedure;

        if ($type === 'missing') {
            $sqlQuery = "DELIMITER $$ $procedure->CreateProcedure $$ DELIMITER ; ";
        }

        if ($type === 'different') {
            $createProcedure = "DELIMITER $$ $procedure->CreateProcedure $$ DELIMITER ; ";
            $sqlQuery        = "DROP PROCEDURE IF EXISTS `$procedure->Name`; $createProcedure";
        }

        $this->sqlStatements[$connection]['procedures'][$type][] = rtrim($sqlQuery, ', ');
    }

    /**
     * Create functions that are compared.
     *
     * @return CompareDB
     */
    public function createFunctions(): CompareDB
    {
        $missingSourceFunctions      = $this->getCompare()['source']['functions'] ?? [];
        $missingDestinationFunctions = $this->getCompare()['destination']['functions'] ?? [];

        if (count($missingSourceFunctions) > 0) {
            if (isset($missingSourceFunctions['missing']) && count($missingSourceFunctions['missing']) > 0) {
                foreach ($missingSourceFunctions['missing'] as $missingSourceFunction) {
                    $this->functionSql('source', 'missing', $missingSourceFunction);
                }
            }

            if (isset($missingSourceFunctions['different']) && count($missingSourceFunctions['different']) > 0) {
                foreach ($missingSourceFunctions['different'] as $missingSourceFunction) {
                    $this->functionSql('source', 'different', $missingSourceFunction);
                }
            }
        }

        if (count($missingDestinationFunctions) > 0) {
            if (isset($missingDestinationFunctions['missing']) && count($missingDestinationFunctions['missing']) > 0) {
                foreach ($missingDestinationFunctions['missing'] as $missingDestinationFunction) {
                    $this->functionSql('destination', 'missing', $missingDestinationFunction);
                }
            }

            if (isset($missingDestinationFunctions['different']) && count($missingDestinationFunctions['different']) > 0) {
                foreach ($missingDestinationFunctions['different'] as $missingDestinationFunction) {
                    $this->functionSql('destination', 'different', $missingDestinationFunction);
                }
            }
        }

        return $this;
    }

    /**
     * Create function sql.
     *
     * @param string $connection
     * @param string $type
     * @param array $function
     * @return void
     */
    private function functionSql(string $connection, string $type, array $function): void
    {
        $sqlQuery = '';
        $function = (object) $function;

        if ($type === 'missing') {
            $sqlQuery = "DELIMITER $$ $function->CreateFunction $$ DELIMITER ; ";
        }

        if ($type === 'different') {
            $createFunction = "DELIMITER $$ $function->CreateFunction $$ DELIMITER ; ";
            $sqlQuery       = "DROP FUNCTION IF EXISTS `$function->Name`; $createFunction";
        }

        $this->sqlStatements[$connection]['functions'][$type][] = rtrim($sqlQuery, ', ');
    }

    /**
     * Create events that are compared.
     *
     * @return CompareDB
     */
    public function createEvents(): CompareDB
    {
        $missingSourceEvents      = $this->getCompare()['source']['events'] ?? [];
        $missingDestinationEvents = $this->getCompare()['destination']['events'] ?? [];

        if (count($missingSourceEvents) > 0) {
            if (isset($missingSourceEvents['missing']) && count($missingSourceEvents['missing']) > 0) {
                foreach ($missingSourceEvents['missing'] as $missingSourceEvent) {
                    $this->eventSql('source', 'missing', $missingSourceEvent);
                }
            }

            if (isset($missingSourceEvents['different']) && count($missingSourceEvents['different']) > 0) {
                foreach ($missingSourceEvents['different'] as $missingSourceEvent) {
                    $this->eventSql('source', 'different', $missingSourceEvent);
                }
            }
        }

        if (count($missingDestinationEvents) > 0) {
            if (isset($missingDestinationEvents['missing']) && count($missingDestinationEvents['missing']) > 0) {
                foreach ($missingDestinationEvents['missing'] as $missingDestinationEvent) {
                    $this->eventSql('destination', 'missing', $missingDestinationEvent);
                }
            }

            if (isset($missingDestinationEvents['different']) && count($missingDestinationEvents['different']) > 0) {
                foreach ($missingDestinationEvents['different'] as $missingDestinationEvent) {
                    $this->eventSql('destination', 'different', $missingDestinationEvent);
                }
            }
        }

        return $this;
    }

    /**
     * Create event sql.
     *
     * @param string $connection
     * @param string $type
     * @param array $event
     * @return void
     */
    private function eventSql(string $connection, string $type, array $event): void
    {
        $sqlQuery = '';
        $event    = (object) $event;

        if ($type === 'missing') {
            $sqlQuery = "DELIMITER $$ $event->CreateEvent $$ DELIMITER ; ";
        }

        if ($type === 'different') {
            $createEvent = "DELIMITER $$ $event->CreateEvent $$ DELIMITER ; ";
            $sqlQuery    = "DROP EVENT IF EXISTS `$event->Name`; $createEvent";
        }

        $this->sqlStatements[$connection]['events'][$type][] = rtrim($sqlQuery, ', ');
    }

    /**
     * Create views that are compared.
     *
     * @return CompareDB
     */
    public function createViews(): CompareDB
    {
        $missingSourceViews      = $this->getCompare()['source']['views'] ?? [];
        $missingDestinationViews = $this->getCompare()['destination']['views'] ?? [];

        if (count($missingSourceViews) > 0) {
            if (isset($missingSourceViews['missing']) && count($missingSourceViews['missing']) > 0) {
                foreach ($missingSourceViews['missing'] as $missingSourceView) {
                    $this->viewSql('source', 'missing', $missingSourceView);
                }
            }

            if (isset($missingSourceViews['different']) && count($missingSourceViews['different']) > 0) {
                foreach ($missingSourceViews['different'] as $missingSourceView) {
                    $this->viewSql('source', 'different', $missingSourceView);
                }
            }
        }

        if (count($missingDestinationViews) > 0) {
            if (isset($missingDestinationViews['missing']) && count($missingDestinationViews['missing']) > 0) {
                foreach ($missingDestinationViews['missing'] as $missingDestinationView) {
                    $this->viewSql('destination', 'missing', $missingDestinationView);
                }
            }

            if (isset($missingDestinationViews['different']) && count($missingDestinationViews['different']) > 0) {
                foreach ($missingDestinationViews['different'] as $missingDestinationView) {
                    $this->viewSql('destination', 'different', $missingDestinationView);
                }
            }
        }

        return $this;
    }

    /**
     * Create view sql.
     *
     * @param string $connection
     * @param string $type
     * @param array $view
     * @return void
     */
    private function viewSql(string $connection, string $type, array $view): void
    {
        $sqlQuery              = '';
        $view                  = (object) $view;
        $reverseConnection     = $connection === 'source' ? 'destination' : 'source';
        $view->VIEW_DEFINITION = str_replace($this->$reverseConnection->getDatabaseName(), $this->$connection->getDatabaseName(), $view->VIEW_DEFINITION);

        if ($type === 'missing') {
            $sqlQuery = "CREATE OR REPLACE VIEW `$view->TABLE_NAME` AS $view->VIEW_DEFINITION; ";
        }

        if ($type === 'different') {
            $sqlQuery = "DROP VIEW IF EXISTS `$view->TABLE_NAME`; CREATE OR REPLACE VIEW `$view->TABLE_NAME` AS $view->VIEW_DEFINITION; ";
        }

        $this->sqlStatements[$connection]['views'][$type][] = rtrim($sqlQuery, ', ');
    }

    /**
     * Put All Sql in sql file.
     *
     * @return CompareDB
     */
    public function createSqlFile(): CompareDB
    {
        if (!empty($this->sqlStatements)) {
            if (isset($this->sqlStatements['source'])) {
                $this->getSqlFile('source');
            }

            if (isset($this->sqlStatements['destination'])) {
                $this->getSqlFile('destination');
            }
        }

        return $this;
    }

    /**
     * Get the sql file.
     *
     * @param string $connection
     * @return void
     */
    public function getSqlFile(string $connection): void
    {
        $sqlStatements = array_merge(
            $this->sqlStatements[$connection]['tables'] ?? [],
            $this->sqlStatements[$connection]['columns']['missing'] ?? [],
            $this->sqlStatements[$connection]['columns']['different'] ?? [],
            $this->sqlStatements[$connection]['triggers']['missing'] ?? [],
            $this->sqlStatements[$connection]['triggers']['different'] ?? [],
            $this->sqlStatements[$connection]['procedures']['missing'] ?? [],
            $this->sqlStatements[$connection]['procedures']['different'] ?? [],
            $this->sqlStatements[$connection]['functions']['missing'] ?? [],
            $this->sqlStatements[$connection]['functions']['different'] ?? [],
            $this->sqlStatements[$connection]['events']['missing'] ?? [],
            $this->sqlStatements[$connection]['events']['different'] ?? [],
            $this->sqlStatements[$connection]['views']['missing'] ?? [],
            $this->sqlStatements[$connection]['views']['different'] ?? []
        );

        $sqlStatements = implode("\r", $sqlStatements);
        $fileName      = $this->{$connection}->getDatabaseName() . '_' . date('Y-m-d_H-i-s') . '.sql';

        if (!empty($sqlStatements) && !Storage::disk('local')->exists($fileName)) {
            $sqlFile = Storage::disk('local')->put("compareDB/$connection/$fileName", $sqlStatements);
            $this->sqlStatements[$connection]['sqlFile'] = $sqlFile;
        }
    }

    /**
     * Get Compare Result
     *
     * @return array
     */
    public function compare(): array
    {
        return $this
            ->createTables()
            ->createColumns()
            ->createTriggers()
            ->createProcedures()
            ->createFunctions()
            ->createEvents()
            ->createViews()
            ->createSqlFile()
            ->sqlStatements;
    }
}
