<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

final readonly class SchemaExecutionDelegator implements SchemaExecutionDelegatorInterface
{
    private PromiseAdapter $promiseAdapter;

    public function __construct(private Schema $schema, PromiseAdapter $promiseAdapter = null)
    {
        $this->promiseAdapter = $promiseAdapter ?? new SyncPromiseAdapter();
    }

    /**
     * @throws \Exception
     */
    public function delegate(Schema $executionSchema, OperationDefinitionNode $operation, array $fragments = [], array $variables = []): Promise
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

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return $this->promiseAdapter;
    }
}
