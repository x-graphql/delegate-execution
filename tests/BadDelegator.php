<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution\Test;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;
use XGraphQL\Delegate\DelegatorInterface;

final readonly class BadDelegator implements DelegatorInterface
{
    public function delegateToExecute(Schema $executionSchema, OperationDefinitionNode $operation, array $fragments = [], array $variables = []): Promise
    {
        throw new \RuntimeException('Bad execution delegator');
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return new SyncPromiseAdapter();
    }
}
