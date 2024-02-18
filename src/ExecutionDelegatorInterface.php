<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;

/**
 * Implements this interface responsible for delegating query to somewhere to execute it (e.g: http, graphql schema, etc.)
 */
interface ExecutionDelegatorInterface
{
    /**
     * @param OperationDefinitionNode $operation
     * @param FragmentDefinitionNode[] $fragments
     * @param array<string, mixed> $variables
     * @return Promise promised value MUST be an instance of `GraphQL\Executor\ExecutionResult`.
     */
    public function delegate(OperationDefinitionNode $operation, array $fragments = [], array $variables = []): Promise;
}
