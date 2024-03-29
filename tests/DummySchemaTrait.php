<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution\Test;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

trait DummySchemaTrait
{
    private function createDummySchema(): Schema
    {
        $dummyInterface = new InterfaceType([
            'name' => 'DummyInterface',
            'fields' => [
                'dummy' => Type::nonNull(Type::string()),
            ],
            'resolveType' => fn () => 'DummyObject'
        ]);

        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'dummy' => [
                        'type' => Type::string(),
                        'resolve' => fn () => 'dummy'
                    ],
                    'dummy_error' => [
                        'type' => Type::string(),
                        'resolve' => fn () => throw new Error('Dummy error')
                    ],
                    'dummy_object' => [
                        'type' => $dummyInterface,
                        'resolve' => fn () => [
                            'dummy' => 'dummy object field'
                        ]
                    ]
                ]
            ]),
            'mutation' => new ObjectType([
                'name' => 'Mutation',
                'fields' => [
                    'dummy' => [
                        'type' => Type::string(),
                        'resolve' => fn () => 'dummy'
                    ]
                ]
            ]),
            'types' => [
                new ObjectType([
                    'name' => 'DummyObject',
                    'fields' => [
                        'dummy' => Type::nonNull(Type::string()),
                    ],
                    'interfaces' => [$dummyInterface]
                ]),
            ],
        ]);
    }
}
