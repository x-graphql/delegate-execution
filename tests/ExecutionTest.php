<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution\Test;

use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use XGraphQL\DelegateExecution\Execution;
use XGraphQL\DelegateExecution\ExecutionDelegatorInterface;
use XGraphQL\DelegateExecution\RootFieldsResolver;

class ExecutionTest extends TestCase
{
    use DummySchemaTrait;

    public function testDelegateSetup(): void
    {
        $delegator = $this->createStub(ExecutionDelegatorInterface::class);
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  dummy: String!
}
SDL
        );
        $rootType = $schema->getOperationType('query');

        $this->assertInstanceOf(ObjectType::class, $rootType);
        $this->assertNull($rootType->resolveFieldFn);

        Execution::delegate($schema, $delegator);

        $this->assertInstanceOf(RootFieldsResolver::class, $rootType->resolveFieldFn);
    }

    public function testExecution(): void
    {
        $delegateSchema = $this->createDummySchema();
        $delegator = new SchemaExecutionDelegator($delegateSchema);
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
    dummy: String!
    dummy_object: DummyObject!
    dummy_error: String
}

type DummyObject {
   dummy: String!
}
SDL
        );
        Execution::delegate($schema, $delegator);

        $result = GraphQL::executeQuery(
            $schema,
            <<<'GQL'
query {
  dummy
  dummy_object {
    dummy
  }
  dummy_error
}
GQL
        );

        $this->assertEquals(
            [
                'dummy' => 'dummy',
                'dummy_object' => [
                    'dummy' => 'dummy object field'
                ],
                'dummy_error' => null
            ],
            $result->data
        );

        $this->assertCount(1, $result->errors);
        $this->assertEquals(['dummy_error'], $result->errors[0]->path);
        $this->assertEquals('Dummy error', $result->errors[0]->getMessage());
    }

    public function testLimitAccessFieldOfDelegateSchema(): void
    {
        $delegateSchema = $this->createDummySchema();
        $delegator = new SchemaExecutionDelegator($delegateSchema);
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
    dummy: String!
}
SDL
        );
        Execution::delegate($schema, $delegator);

        $result = GraphQL::executeQuery(
            $schema,
            <<<'GQL'
query {
  dummy
  dummy_object {
    dummy
  }
}
GQL
        );

        $this->assertEquals('Cannot query field "dummy_object" on type "Query".', $result->errors[0]->getMessage());
    }

    public function testSchemaConflictFieldDefinitionWithDelegateSchema(): void
    {
        $delegateSchema = $this->createDummySchema();
        $delegator = new SchemaExecutionDelegator($delegateSchema);
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
    conflict_field: String!
}
SDL
        );
        Execution::delegate($schema, $delegator);

        $result = GraphQL::executeQuery(
            $schema,
            <<<'GQL'
query {
  conflict_field
}
GQL
        );

        $this->assertEquals('Delegated execution result is missing field value at path: `conflict_field`', $result->errors[0]->getMessage());
    }

    public function testSchemaConflictAbstractTypeWithDelegateSchema(): void
    {
        $delegateSchema = $this->createDummySchema();
        $delegator = new SchemaExecutionDelegator($delegateSchema);
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
    dummy_object: Unknown
}

interface Unknown {
  dummy: String!
}

type UnknownObject implements Unknown {
  id: ID!
}
SDL
        );
        Execution::delegate($schema, $delegator);

        $result = GraphQL::executeQuery(
            $schema,
            <<<'GQL'
query {
  dummy_object {
    dummy
  }
}
GQL
        );

        $this->assertEquals('Expect type: `DummyObject` implementing `Unknown` should be exist in schema', $result->errors[0]->getMessage());
    }
}
