<?php

namespace Essam\CompareDB\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class Initial
{
    protected string $connectionName;
    public array $intialResult = [];

    public function __construct($connection)
    {
        $this->connectionName = $connection;
    }

    /**
     * Get Connection.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return DB::connection("compareDB.$this->connectionName");
    }

    /**
     * Get Database Name.
     *
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->getConnection()->getDatabaseName();
    }

    /**
     * Get Query.
     *
     * @param string $query
     * @return array
     */
    public function query(string $query): array
    {
        return $this->getConnection()->select($query);
    }

    /**
     * Get database tables.
     *
     * @return Initial
     */
    public function getTables(): Initial
    {
        $this->intialResult['tables'] = collect($this->query('SHOW TABLES'))
            ->pluck("Tables_in_{$this->getDatabaseName()}")
            ->toArray();

        return $this;
    }

    /**
     * Get table columns.
     *
     * @return Initial
     */
    public function getColumns(): Initial
    {
        $tables = $this->intialResult['tables'];

        foreach ($tables as $table) {
            $this->intialResult['columns'][$table] =
                collect($this->query("SHOW COLUMNS FROM $table"))->keyBy('Field')->toArray();
        }

        return $this;
    }

    /**
     * Get table constraints.
     *
     * @return Initial
     */
    public function getConstraints(): Initial
    {
        collect($this->query("SELECT tc.constraint_schema, tc.constraint_name, tc.table_name, tc.constraint_type,
                                    kcu.table_name, kcu.column_name, kcu.referenced_table_name, kcu.referenced_column_name,
                                    rc.update_rule, rc.delete_rule
                                    FROM information_schema.table_constraints tc

                                    JOIN information_schema.key_column_usage kcu
                                    ON tc.constraint_catalog = kcu.constraint_catalog
                                    AND tc.constraint_schema = kcu.constraint_schema
                                    AND tc.constraint_name = kcu.constraint_name
                                    AND tc.table_name = kcu.table_name

                                    LEFT JOIN information_schema.referential_constraints rc
                                    ON tc.constraint_catalog = rc.constraint_catalog
                                    AND tc.constraint_schema = rc.constraint_schema
                                    AND tc.constraint_name = rc.constraint_name
                                    AND tc.table_name = rc.table_name
                                    WHERE tc.constraint_schema = '{$this->getDatabaseName()}'"))
            ->groupBy('TABLE_NAME')
            ->each(function ($table, $tableName) {
                $this->intialResult['constraints'][$tableName] = $table->keyBy('CONSTRAINT_NAME')->toArray();
            })
            ->toArray();

        return $this;
    }

    /**
     * Get tables triggers.
     *
     * @return Initial
     */
    public function getTriggers(): Initial
    {
        $tables = $this->intialResult['tables'];

        foreach ($tables as $table) {
            $this->intialResult['triggers'][$table] = collect($this->query("SHOW TRIGGERS FROM {$this->getDatabaseName()} WHERE `Table` = '$table'"))
                ->keyBy('Trigger')
                ->toArray();
        }

        return $this;
    }

    /**
     * Get tables procedures.
     *
     * @return Initial
     */
    public function getProcedures(): Initial
    {
        $this->intialResult['procedures'] =
            collect($this->query("SHOW PROCEDURE STATUS WHERE Db = '{$this->getDatabaseName()}'"))
                ->each(function ($procedure) {
                    $procedure->CreateProcedure =
                        collect($this->query("SHOW CREATE PROCEDURE `$procedure->Db`.`$procedure->Name`"))
                            ->pluck('Create Procedure')
                            ->first();
                })
                ->keyBy('Name')
                ->toArray();

        return $this;
    }

    /**
     * Get tables functions.
     *
     * @return Initial
     */
    public function getFunctions(): Initial
    {
        $this->intialResult['functions'] =
            collect($this->query("SHOW FUNCTION STATUS WHERE Db = '{$this->getDatabaseName()}'"))
                ->each(function ($function) {
                    $function->CreateFunction =
                        collect($this->query("SHOW CREATE FUNCTION `$function->Db`.`$function->Name`"))
                            ->pluck('Create Function')
                            ->first();
                })
                ->keyBy('Name')
                ->toArray();

        return $this;
    }

    /**
     * Get tables events.
     *
     * @return Initial
     */
    public function getEvents(): Initial
    {
        $this->intialResult['events'] =
            collect($this->query("SHOW EVENTS FROM {$this->getDatabaseName()}"))
                ->each(function ($event) {
                    $event->CreateEvent =
                        collect($this->query("SHOW CREATE EVENT `$event->Db`.`$event->Name`"))
                            ->pluck('Create Event')
                            ->first();
                })
                ->keyBy('Name')
                ->toArray();

        return $this;
    }

    /**
     * Get tables views.
     *
     * @return Initial
     */
    public function getViews(): Initial
    {
        $this->intialResult['views'] =
            collect($this->query("SELECT * FROM information_schema.views WHERE TABLE_SCHEMA = '{$this->getDatabaseName()}'"))
                ->keyBy('TABLE_NAME')
                ->toArray();

        return $this;
    }
}
