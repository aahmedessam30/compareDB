<?php

namespace Essam\CompareDB\Services;

class Compare
{
    protected Initial $source;
    protected Initial $destination;
    protected array $compareResult = [];

    public function __construct()
    {
        $this->source      = new Initial('source');
        $this->destination = new Initial('destination');
    }

    /**
     * Loop Through Attributes and Compare it.
     *
     * @param string $connection
     * @param string $type
     * @param array $keys
     * @param array $sourceAttributes
     * @param array $destinationAttributes
     */
    protected function loopThroughAttributes(string $connection, string $type, array $keys, array $sourceAttributes, array $destinationAttributes): void
    {
        foreach ($sourceAttributes as $table => $attributes) {
            foreach ($attributes as $attribute) {
                $attribute = (array)$attribute;
                foreach ($keys as $key) {
                    $this->{"compare{$type}Attributes"}($connection, $key, $attribute, $table, $destinationAttributes);
                }
            }
        }
    }

    /**
     * Compare Tables.
     *
     * @return Compare
     */
    public function compareTables(): Compare
    {
        $sourceTables = $this->source->getTables()->intialResult['tables'];
        $destinationTables = $this->destination->getTables()->intialResult['tables'];

        $missingSourceTables = array_diff($destinationTables, $sourceTables);
        $missingDestinationTables = array_diff($sourceTables, $destinationTables);

        foreach ($missingSourceTables as $missingSourceTable) {
            $this->compareResult['source']['tables'][$missingSourceTable]['columns'] = $this->destination->getColumns()->intialResult['columns'][$missingSourceTable];
        }

        foreach ($missingDestinationTables as $missingDestinationTable) {
            $this->compareResult['destination']['tables'][$missingDestinationTable]['columns'] = $this->source->getColumns()->intialResult['columns'][$missingDestinationTable];
        }

        return $this;
    }

    /**
     * Compare Columns.
     *
     * @return Compare
     */
    public function compareColumns(): Compare
    {
        $sourceColumns      = $this->source->getColumns()->intialResult['columns'];
        $destinationColumns = $this->destination->getColumns()->intialResult['columns'];
        $attributes         = ['Type', 'Null', 'Key', 'Default', 'Extra'];

        $this->loopThroughAttributes('destination', 'Columns', $attributes, $sourceColumns, $destinationColumns);
        $this->loopThroughAttributes('source', 'Columns', $attributes, $destinationColumns, $sourceColumns);

        return $this;
    }

    /**
     * Compare Columns Attributes.
     *
     * @param string $connection
     * @param string $attribute
     * @param array $column
     * @param string $table
     * @param array $anotherColumns
     * @return void
     */
    protected function compareColumnsAttributes(string $connection, string $attribute, array $column, string $table, array $anotherColumns): void
    {
        if (array_key_exists($table, $anotherColumns)) {
            if (!array_key_exists($column['Field'], $anotherColumns[$table])) {
                if (!isset($this->compareResult[$connection]['columns'][$table]['missing']['constraints']) ||
                    !array_key_exists($column['Field'], $this->compareResult[$connection]['columns'][$table]['missing'])) {
                    $this->compareResult[$connection]['columns'][$table]['missing']['attributes'][$column['Field']] = $column;
                }
            }else{
                $anotherColumn = $anotherColumns[$table][$column['Field']];
                if (!isset($this->compareResult[$connection]['columns'][$table]['different']) ||
                    !array_key_exists($column['Field'], $this->compareResult[$connection]['columns'][$table]['different'])) {
                    if (($column[$attribute] !== $anotherColumn->{$attribute}) && $attribute !== 'Key') {
                        $this->compareResult[$connection]['columns'][$table]['different']['attributes'][$column['Field']] = $column;
                    }
                }
            }
        }
    }

    /**
     * Compare Constraints.
     *
     * @return Compare
     */
    public function compareConstraints(): Compare
    {
        $sourceConstraints = $this->source->getConstraints()->intialResult['constraints'];
        $destinationConstraints = $this->destination->getConstraints()->intialResult['constraints'];
        $attributes = ['CONSTRAINT_NAME', 'TABLE_NAME', 'COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME', 'UPDATE_RULE', 'DELETE_RULE'];

        $this->loopThroughAttributes('destination', 'Constraints', $attributes, $sourceConstraints, $destinationConstraints);
        $this->loopThroughAttributes('source', 'Constraints', $attributes, $destinationConstraints, $sourceConstraints);

        return $this;
    }

    /**
     * Compare Constraints Attributes.
     *
     * @param string $connection
     * @param string $attribute
     * @param array $constraint
     * @param string $table
     * @param array $anotherConstraints
     * @return void
     */
    public function compareConstraintsAttributes(string $connection, string $attribute, array $constraint, string $table, array $anotherConstraints): void
    {
        if (array_key_exists($table, $anotherConstraints)) {
            if (!array_key_exists($constraint['CONSTRAINT_NAME'], $anotherConstraints[$table])) {
                if (!isset($this->compareResult[$connection]['columns'][$table]['missing']['constraints']) ||
                    !array_key_exists($constraint['CONSTRAINT_NAME'], $this->compareResult[$connection]['columns'][$table]['missing']['constraints'])) {
                    $this->compareResult[$connection]['columns'][$table]['missing']['constraints'][$constraint['CONSTRAINT_NAME']] = $constraint;
                }
            } else {
                $anotherConstraint = $anotherConstraints[$table][$constraint['CONSTRAINT_NAME']];
                if (!isset($this->compareResult[$connection]['constraints'][$table]['different']) ||
                    !array_key_exists($constraint['CONSTRAINT_NAME'], $this->compareResult[$connection]['columns'][$table]['different']['constraints'])) {
                    if ($constraint[$attribute] !== $anotherConstraint->{$attribute}) {
                        $this->compareResult[$connection]['columns'][$table]['different']['constraints'][$constraint['CONSTRAINT_NAME']] = $constraint;
                    }
                }
            }
        }
    }

    /**
     * Compare Triggers.
     *
     * @return Compare
     */
    public function compareTriggers(): Compare
    {
        $sourceTriggers      = $this->source->getTriggers()->intialResult['triggers'] ?? [];
        $destinationTriggers = $this->destination->getTriggers()->intialResult['triggers'] ?? [];
        $attributes          = ['Trigger', 'Event', 'Table', 'Statement', 'Timing', 'sql_mode', 'Definer', 'character_set_client', 'collation_connection', 'Database Collation'];

        $this->loopThroughAttributes('destination', 'Triggers', $attributes, $sourceTriggers, $destinationTriggers);
        $this->loopThroughAttributes('source', 'Triggers', $attributes, $destinationTriggers, $sourceTriggers);

        return $this;
    }

    /**
     * Compare Triggers Attributes.
     *
     * @param string $connection
     * @param string $attribute
     * @param array $trigger
     * @param string $table
     * @param array $anotherTriggers
     * @return void
     */
    public function compareTriggersAttributes(string $connection, string $attribute, array $trigger, string $table, array $anotherTriggers): void
    {
        if (array_key_exists($table, $anotherTriggers)) {
            if (!array_key_exists($trigger['Trigger'], $anotherTriggers[$table])) {
                if (!isset($this->compareResult[$connection]['triggers'][$table]['missing']) ||
                    !array_key_exists($trigger['Trigger'], $this->compareResult[$connection]['triggers'][$table]['missing'])) {
                    $this->compareResult[$connection]['triggers'][$table]['missing'][$trigger['Trigger']] = $trigger;
                }
            } else {
                $anotherTrigger = $anotherTriggers[$table][$trigger['Trigger']];
                if (!isset($this->compareResult[$connection]['triggers'][$table]['different']) ||
                    !array_key_exists($trigger['Trigger'], $this->compareResult[$connection]['triggers'][$table]['different'])) {
                    if ($trigger[$attribute] !== $anotherTrigger->{$attribute}) {
                        $this->compareResult[$connection]['triggers'][$table]['different'][$trigger['Trigger']] = $trigger;
                    }
                }
            }
        }
    }

    /**
     * Compare Procedures.
     *
     * @return Compare
     */
    public function compareProcedures(): Compare
    {
        $sourceProcedures      = $this->source->getProcedures()->intialResult['procedures'] ?? [];
        $destinationProcedures = $this->destination->getProcedures()->intialResult['procedures'] ?? [];
        $attributes            = ['Name', 'Definer', 'Security_type', 'Comment', 'character_set_client', 'collation_connection', 'Database Collation', 'CreateProcedure'];

        $this->compareProceduresAttributes('destination', $attributes, $sourceProcedures, $destinationProcedures);
        $this->compareProceduresAttributes('source', $attributes, $destinationProcedures, $sourceProcedures);

        return $this;
    }

    /**
     * Loop Through Procedures.
     *
     * @param string $connection
     * @param array $attributes
     * @param array $procedures
     * @param array $anotherProcedures
     * @return void
     */
    public function compareProceduresAttributes(string $connection, array $attributes, array $procedures, array $anotherProcedures): void
    {
        foreach ($procedures as $procedure) {
            foreach ($attributes as $attribute) {
                $procedure = (array) $procedure;
                if (!array_key_exists($procedure['Name'], $anotherProcedures)) {
                    if (!isset($this->compareResult[$connection]['procedures']['missing']) ||
                        !array_key_exists($procedure['Name'], $this->compareResult[$connection]['procedures']['missing'])) {
                        $this->compareResult[$connection]['procedures']['missing'][$procedure['Name']] = $procedure;
                    }
                } else {
                    $anotherProcedure = $anotherProcedures[$procedure['Name']];
                    if (!isset($this->compareResult[$connection]['procedures']['different']) ||
                        !array_key_exists($procedure['Name'], $this->compareResult[$connection]['procedures']['different'])) {
                        if ($procedure[$attribute] !== $anotherProcedure->{$attribute}) {
                            if($attribute === 'CreateProcedure'){
                                $procedureBody        = $this->getProcedureBody($procedure[$attribute]);
                                $anotherProcedureBody = $this->getProcedureBody($anotherProcedure->{$attribute});

                                if($procedureBody !== $anotherProcedureBody){
                                    $this->compareResult[$connection]['procedures']['different'][$procedure['Name']] = $procedure;
                                }
                            }else{
                                $this->compareResult[$connection]['procedures']['different'][$procedure['Name']] = $procedure;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the body of a procedure
     *
     * @param string $procedure
     * @return string
     */
    public function getProcedureBody(string $procedure): string
    {
        $procedureBody = substr($procedure, strpos($procedure, 'BEGIN'), strpos($procedure, 'END'));
        $procedureBody = str_replace(array('BEGIN', 'END', "\r", "\n", "\t"), '', $procedureBody);
        $procedureBody = preg_replace('/\s+/', ' ', $procedureBody);
        $procedureBody = str_replace(' ;', ';', $procedureBody);

        return trim($procedureBody);
    }

    /**
     * Compare Functions.
     *
     * @return Compare
     */
    public function compareFunctions(): Compare
    {
        $sourceFunctions      = $this->source->getFunctions()->intialResult['functions'] ?? [];
        $destinationFunctions = $this->destination->getFunctions()->intialResult['functions'] ?? [];
        $attributes           = ['Name', 'Definer', 'Security_type', 'Comment', 'character_set_client', 'collation_connection', 'Database Collation', 'CreateFunction'];

        $this->compareFunctionsAttributes('destination', $attributes, $sourceFunctions, $destinationFunctions);
        $this->compareFunctionsAttributes('source', $attributes, $destinationFunctions, $sourceFunctions);

        return $this;
    }

    /**
     * Loop Through Functions.
     *
     * @param string $connection
     * @param array $attributes
     * @param array $functions
     * @param array $anotherFunctions
     * @return void
     */
    public function compareFunctionsAttributes(string $connection, array $attributes, array $functions, array $anotherFunctions): void
    {
        foreach ($functions as $function) {
            foreach ($attributes as $attribute) {
                $function = (array) $function;
                if (!array_key_exists($function['Name'], $anotherFunctions)) {
                    if (!isset($this->compareResult[$connection]['functions']['missing']) ||
                        !array_key_exists($function['Name'], $this->compareResult[$connection]['functions']['missing'])) {
                        $this->compareResult[$connection]['functions']['missing'][$function['Name']] = $function;
                    }
                } else {
                    $anotherFunction = $anotherFunctions[$function['Name']];
                    if (!isset($this->compareResult[$connection]['functions']['different']) ||
                        !array_key_exists($function['Name'], $this->compareResult[$connection]['functions']['different'])) {
                        if ($function[$attribute] !== $anotherFunction->{$attribute}) {
                            if($attribute === 'CreateFunction'){
                                $functionBody        = $this->getFunctionBody($function[$attribute]);
                                $anotherFunctionBody = $this->getFunctionBody($anotherFunction->{$attribute});

                                if($functionBody !== $anotherFunctionBody){
                                    $this->compareResult[$connection]['functions']['different'][$function['Name']] = $function;
                                }
                            }else{
                                $this->compareResult[$connection]['functions']['different'][$function['Name']] = $function;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the body of a function
     *
     * @param string $function
     * @return string
     */
    public function getFunctionBody(string $function): string
    {
        $functionBody = substr($function, strpos($function, 'BEGIN'), strpos($function, 'END'));
        $functionBody = str_replace(array('BEGIN', 'END', "\r", "\n", "\t"), '', $functionBody);
        $functionBody = preg_replace('/\s+/', ' ', $functionBody);
        $functionBody = str_replace(' ;', ';', $functionBody);

        return trim($functionBody);
    }

    /**
     * Compare Events.
     *
     * @return Compare
     */
    public function compareEvents(): Compare
    {
        $sourceEvents = $this->source->getEvents()->intialResult['events'] ?? [];
        $destinationEvents = $this->destination->getEvents()->intialResult['events'] ?? [];
        $attributes = ['Name', 'Definer', 'Time zone', 'Execute at', 'Interval value', 'Interval field', 'Starts', 'Ends', 'Status', 'Originator', 'character_set_client', 'collation_connection', 'Database Collation', 'Create Event'];

        $this->compareEventsAttributes('destination', $attributes, $sourceEvents, $destinationEvents);
        $this->compareEventsAttributes('source', $attributes, $destinationEvents, $sourceEvents);

        return $this;
    }

    /**
     * Compare Events Attributes.
     *
     * @param string $connection
     * @param array $attributes
     * @param array $events
     * @param array $anotherEvents
     * @return void
     */
    public function compareEventsAttributes(string $connection, array $attributes, array $events, array $anotherEvents): void
    {
        foreach ($events as $event) {
            foreach ($attributes as $attribute) {
                $event = (array) $event;
                if (!array_key_exists($event['Name'], $anotherEvents)) {
                    if (!isset($this->compareResult[$connection]['events']['missing']) ||
                        !array_key_exists($event['Name'], $this->compareResult[$connection]['events']['missing'])) {
                        $this->compareResult[$connection]['events']['missing'][$event['Name']] = $event;
                    }
                } else {
                    $anotherEvent = $anotherEvents[$event['Name']];
                    if (!isset($this->compareResult[$connection]['events']['different']) ||
                        !array_key_exists($event['Name'], $this->compareResult[$connection]['events']['different'])) {
                        if ($event[$attribute] !== $anotherEvent->{$attribute}) {
                            if($attribute === 'Create Event'){
                                $eventBody        = $this->getEventBody($event[$attribute]);
                                $anotherEventBody = $this->getEventBody($anotherEvent->{$attribute});

                                if($eventBody !== $anotherEventBody){
                                    $this->compareResult[$connection]['events']['different'][$event['Name']] = $event;
                                }
                            }else{
                                $this->compareResult[$connection]['events']['different'][$event['Name']] = $event;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the body of an event
     *
     * @param string $event
     * @return string
     */
    public function getEventBody(string $event): string
    {
        $eventBody = substr($event, strpos($event, 'DO'), strpos($event, 'END'));
        $eventBody = str_replace(array('DO', 'END', "\r", "\n", "\t"), '', $eventBody);
        $eventBody = preg_replace('/\s+/', ' ', $eventBody);
        $eventBody = str_replace(' ;', ';', $eventBody);

        return trim($eventBody);
    }

    /**
     * Compare Views.
     *
     * @return Compare
     */
    public function compareViews(): Compare
    {
        $sourceViews = $this->source->getViews()->intialResult['views'] ?? [];
        $destinationViews = $this->destination->getViews()->intialResult['views'] ?? [];
        $attributes = ['TABLE_NAME', 'VIEW_DEFINITION', 'CHECK_OPTION', 'IS_UPDATABLE', 'DEFINER', 'SECURITY_TYPE', 'CHARACTER_SET_CLIENT', 'COLLATION_CONNECTION'];

        $this->compareViewsAttributes('destination', $attributes, $sourceViews, $destinationViews);
        $this->compareViewsAttributes('source', $attributes, $destinationViews, $sourceViews);

        return $this;
    }

    /**
     * Compare Views Attributes.
     *
     * @param string $connection
     * @param array $attributes
     * @param array $views
     * @param array $anotherViews
     * @return void
     */
    public function compareViewsAttributes(string $connection, array $attributes, array $views, array $anotherViews): void
    {
        foreach ($views as $view) {
            foreach ($attributes as $attribute) {
                $view = (array) $view;
                if (!array_key_exists($view['TABLE_NAME'], $anotherViews)) {
                    if (!isset($this->compareResult[$connection]['views']['missing']) ||
                        !array_key_exists($view['TABLE_NAME'], $this->compareResult[$connection]['views']['missing'])) {
                        $this->compareResult[$connection]['views']['missing'][$view['TABLE_NAME']] = $view;
                    }
                } else {
                    $anotherView = $anotherViews[$view['TABLE_NAME']];
                    if (!isset($this->compareResult[$connection]['views']['different']) ||
                        !array_key_exists($view['TABLE_NAME'], $this->compareResult[$connection]['views']['different'])) {
                        if ($view[$attribute] !== $anotherView->{$attribute}) {
                            if($attribute === 'VIEW_DEFINITION'){
                                $viewBody        = $this->getViewBody($view[$attribute]);
                                $anotherViewBody = $this->getViewBody($anotherView->{$attribute});

                                if($viewBody !== $anotherViewBody){
                                    $this->compareResult[$connection]['views']['different'][$view['TABLE_NAME']] = $view;
                                }
                            }else{
                                $this->compareResult[$connection]['views']['different'][$view['TABLE_NAME']] = $view;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the body of a view
     *
     * @param string $view
     * @return string
     */
    public function getViewBody(string $view): string
    {
        $viewBody = substr($view, strpos($view, 'AS'), strpos($view, 'WITH'));
        $viewBody = str_replace(array('AS', 'WITH', "\r", "\n", "\t"), '', $viewBody);
        $viewBody = preg_replace('/\s+/', ' ', $viewBody);
        $viewBody = str_replace(' ;', ';', $viewBody);;

        return trim($viewBody);
    }

    /**
     * Get All Information About Tables.
     *
     * @return array
     */
    public function getCompare(): array
    {
        return $this
            ->compareTables()
            ->compareColumns()
            ->compareConstraints()
            ->compareTriggers()
            ->compareProcedures()
            ->compareFunctions()
            ->compareEvents()
            ->compareViews()
            ->compareResult;
    }
}

