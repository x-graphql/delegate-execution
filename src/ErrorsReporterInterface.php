<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Error\Error;

/**
 * Help to report errors during delegate GraphQL schema execution
 */
interface ErrorsReporterInterface
{
    /**
     * @param Error[] $errors
     * @return void
     */
    public function reportErrors(array $errors): void;
}
