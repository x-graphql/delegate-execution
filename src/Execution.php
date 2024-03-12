<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Type\Schema;
use XGraphQL\Delegate\DelegatorInterface;

final class Execution
{
    public static function delegate(
        Schema $schema,
        DelegatorInterface $delegator,
        ErrorsReporterInterface $errorsReporter = null,
    ): void {
        foreach (['query', 'mutation', 'subscription'] as $operation) {
            $rootType = $schema->getOperationType($operation);

            if (null !== $rootType) {
                $rootType->resolveFieldFn = new RootFieldsResolver($delegator, $errorsReporter);
            }
        }
    }
}
