<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution\Test;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use XGraphQL\DelegateExecution\SchemaExecutionDelegator;

class SchemaExecutionDelegatorTest extends TestCase
{
    use DummySchemaTrait;

    public function testConstructor(): void
    {
        $schema = $this->createStub(Schema::class);
        $instance = new SchemaExecutionDelegator($schema);

        $this->assertInstanceOf(SchemaExecutionDelegator::class, $instance);
        $this->assertInstanceOf(SyncPromiseAdapter::class, $instance->getPromiseAdapter());
        $this->assertEquals($schema, $instance->getSchema());
    }

    public function testCanDelegateQuery()
    {
        $schema = $this->createDummySchema();
        $adapter = new SyncPromiseAdapter();
        $instance = new SchemaExecutionDelegator($schema, $adapter);
        $operation = Parser::operationDefinition(
            <<<'GQL'
query test($include: Boolean!) {
    a: dummy @include(if: $include)
    ...b
}
GQL
        );
        $fragment = Parser::fragmentDefinition(
            <<<'GQL'
fragment b on Query {
   b: dummy_error
}
GQL
        );

        $promise = $instance->delegate($operation, [$fragment], ['include' => true]);

        $this->assertInstanceOf(Promise::class, $promise);

        $result = $adapter->wait($promise);

        $this->assertInstanceOf(ExecutionResult::class, $result);
        $this->assertEquals(['a' => 'dummy', 'b' => null], $result->data);
        $this->assertCount(1, $result->errors);
        $this->assertEquals(['b'], $result->errors[0]->getPath());
        $this->assertEquals('Dummy error', $result->errors[0]->getMessage());
    }
}
