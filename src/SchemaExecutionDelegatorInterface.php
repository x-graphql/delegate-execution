<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Type\Schema;

interface SchemaExecutionDelegatorInterface extends ExecutionDelegatorInterface
{
    /**
     * @return Schema used to delegate query to execute
     */
    public function getSchema(): Schema;
}
