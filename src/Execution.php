<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use XGraphQL\Delegate\DelegatorInterface;
use XGraphQL\DelegateExecution\Exception\LogicException;
use XGraphQL\DelegateExecution\Exception\RuntimeException;
use XGraphQL\Utils\SelectionSet;

final class Execution
{
    /**
     * @var \WeakMap<ObjectType>
     */
    private \WeakMap $hijackedObjects;

    /**
     * @var \WeakMap<OperationDefinitionNode>
     */
    private \WeakMap $delegatedPromises;

    private function __construct(
        private readonly DelegatorInterface $delegator,
        private readonly ?ErrorsReporterInterface $errorsReporter
    ) {
        $this->hijackedObjects = new \WeakMap();
        $this->delegatedPromises = new \WeakMap();
    }

    public static function delegate(
        Schema $schema,
        DelegatorInterface $delegator,
        ErrorsReporterInterface $errorsReporter = null,
    ): void {
        $execution = new Execution($delegator, $errorsReporter);

        foreach (['query', 'mutation', 'subscription'] as $operation) {
            $operationType = $schema->getOperationType($operation);

            if (null === $operationType) {
                continue;
            }

            $execution->hijackResolver($operationType);
        }
    }

    private function hijackResolver(ObjectType $type): void
    {
        if (isset($this->hijackedObjects[$type])) {
            return;
        }

        foreach ($type->getFields() as $fieldDef) {
            /** @var FieldDefinition $fieldDef */
            $fieldDef->resolveFn = $this->resolve(...);
        }

        $type->resolveFieldFn = null;

        $this->hijackedObjects[$type] = true;
    }

    private function resolve(mixed $value, array $args, mixed $context, ResolveInfo $info): Promise
    {
        $promise = $this->delegatedPromises[$info->operation] ??= $this->delegateToExecute(
            $info->schema,
            $info->operation,
            $info->fragments,
            $info->variableValues
        );

        return $promise->then(
            function (ExecutionResult $result) use ($info): mixed {
                $this->prepareReturnType($info->returnType);

                return $this->accessResultByPath($info->path, $result);
            }
        );
    }

    /**
     * @param Schema $schema
     * @param OperationDefinitionNode $operation
     * @param array<string, FragmentDefinitionNode> $fragments
     * @param array<string, mixed> $variables
     * @return Promise
     */
    private function delegateToExecute(Schema $schema, OperationDefinitionNode $operation, array $fragments, array $variables): Promise
    {
        try {
            /// We need to clone all fragments and operation to make sure it can not be mutated by delegator.
            $delegateOperation = $operation->cloneDeep();
            $delegateFragments = array_map(fn (FragmentDefinitionNode $fragment) => $fragment->cloneDeep(), $fragments);

            /// Add typename for detecting object type of interface or union
            SelectionSet::addTypename($delegateOperation->getSelectionSet());
            SelectionSet::addTypenameToFragments($delegateFragments);

            $promise = $this->delegator->delegateToExecute($schema, $delegateOperation, $delegateFragments, $variables);
        } catch (\Throwable $exception) {
            $result = new ExecutionResult(
                null,
                [
                    new Error('Error during delegate execution', [$operation], previous: $exception)
                ],
            );

            $promise = $this->delegator->getPromiseAdapter()->createFulfilled($result);
        }

        return $promise->then(
            function (ExecutionResult $result): ExecutionResult {
                if ([] !== $result->errors) {
                    $this->errorsReporter?->reportErrors($result->errors);
                }

                return $result;
            }
        );
    }

    private function prepareReturnType(Type $type): void
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        if ($type instanceof ObjectType) {
            $this->hijackResolver($type);
        }

        if ($type instanceof AbstractType) {
            $type->config['resolveType'] = $this->resolveAbstractType(...);
        }
    }

    private function resolveAbstractType(array $value, mixed $context, ResolveInfo $info): Type
    {
        /// __typename field should be existed in $value
        ///  because we have added it to delegated query
        $typename = $value[Introspection::TYPE_NAME_FIELD_NAME];

        if (!$info->schema->hasType($typename)) {
            $abstractType = $info->fieldDefinition->getType();

            if ($abstractType instanceof WrappingType) {
                $abstractType = $abstractType->getInnermostType();
            }

            throw new LogicException(
                sprintf('Expect type: `%s` implementing `%s` should be exist in schema', $typename, $abstractType)
            );
        }

        $implType = $info->schema->getType($typename);

        assert($implType instanceof ObjectType);

        $this->hijackResolver($implType);

        return $implType;
    }

    /**
     * @throws Error
     */
    private function accessResultByPath(array $path, ExecutionResult $result): mixed
    {
        foreach ($result->errors as $error) {
            if ($path === $error->path) {
                /// We should create new error to throw instead for remapping location in cases query delegated have been mutated.
                throw new Error($error->getMessage(), previous: $error);
            }
        }

        $data = $result->data ?? [];
        $pathAccessed = $path;

        while ([] !== $pathAccessed) {
            $pos = array_shift($pathAccessed);

            if (false === array_key_exists($pos, $data)) {
                throw new RuntimeException(
                    sprintf('Delegated execution result is missing field value at path: `%s`', implode('.', $path))
                );
            }

            $data = $data[$pos];
        }

        return $data;
    }
}
