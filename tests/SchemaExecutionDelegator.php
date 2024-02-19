<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution\Test;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;
use XGraphQL\DelegateExecution\ExecutionDelegatorInterface;

final readonly class SchemaExecutionDelegator implements ExecutionDelegatorInterface
{
    private PromiseAdapter $promiseAdapter;

    public function __construct(private Schema $schema, PromiseAdapter $promiseAdapter = null)
    {
        $this->promiseAdapter = $promiseAdapter ?? new SyncPromiseAdapter();
    }

    /**
     * @throws \Exception
     */
    public function delegate(OperationDefinitionNode $operation, array $fragments = [], array $variables = []): Promise
    {
        $source = new DocumentNode([
            'definitions' => new NodeList([...$fragments, $operation])
        ]);

        return GraphQL::promiseToExecute(
            promiseAdapter: $this->promiseAdapter,
            schema: $this->schema,
            source: $source,
            variableValues: $variables,
            operationName: $operation->name?->value,
        );
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return $this->promiseAdapter;
    }
}
