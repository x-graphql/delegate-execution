<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use XGraphQL\DelegateExecution\Exception\LogicException;
use XGraphQL\Utils\SelectionSet;

final class RootFieldsResolver
{
    /**
     * @var \WeakMap<OperationDefinitionNode, Promise>
     */
    private \WeakMap $delegatedPromises;

    public function __construct(
        private readonly ExecutionDelegatorInterface $delegator,
        private readonly ?DelegatedErrorsReporterInterface $delegatedErrorsReporter = null,
    ) {
        $this->delegatedPromises = new \WeakMap();
    }

    public function __invoke(mixed $value, array $arguments, mixed $context, ResolveInfo $info): Promise
    {
        if (!isset($this->delegatedPromises[$info->operation])) {
            /// We need to clone all fragments and operation to make sure it can not be mutated by delegator.
            $operation = $info->operation->cloneDeep();
            $fragments = array_map(fn (FragmentDefinitionNode $fragment) => $fragment->cloneDeep(), $info->fragments);

            /// Add typename for detecting object type of interface or union
            SelectionSet::addTypename($operation->getSelectionSet());
            SelectionSet::addTypenameToFragments($fragments);

            $this->delegatedPromises[$info->operation] = $this
                ->delegator
                ->delegate(
                    $info->schema,
                    $operation,
                    $fragments,
                    $info->variableValues
                )->then(
                    function (ExecutionResult $result): ExecutionResult {
                        if ([] !== $result->errors) {
                            $this->delegatedErrorsReporter?->reportErrors($result->errors);
                        }

                        return $result;
                    }
                );
        }

        return $this->resolve($info);
    }

    private function resolve(ResolveInfo $info): Promise
    {
        $type = $info->returnType;

        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        if ($type instanceof AbstractType) {
            $type->config['resolveType'] ??= $this->resolveAbstractType(...);
        }

        if ($type instanceof ObjectType) {
            $type->resolveFieldFn ??= $this->resolveObjectFields(...);
        }

        $promise = $this->delegatedPromises[$info->operation];

        return $promise->then(fn (ExecutionResult $result) => $this->accessResultByPath($info->path, $result));
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

        if ($implType instanceof ObjectType) {
            $implType->resolveFieldFn = $this->resolveObjectFields(...);
        }

        /// If impl type is not object, executor should throw error.
        return $implType;
    }

    private function resolveObjectFields(array $value, array $args, mixed $context, ResolveInfo $info): Promise
    {
        return $this->resolve($info);
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
                throw new Error(
                    sprintf('Delegated execution result is missing field value at path: `%s`', implode('.', $path))
                );
            }

            $data = $data[$pos];
        }

        return $data;
    }
}
