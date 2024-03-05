<?php

declare(strict_types=1);

namespace XGraphQL\DelegateExecution;

use GraphQL\Error\Error;

interface DelegatedErrorsReporterInterface
{
    /**
     * @param Error[] $errors
     * @return void
     */
    public function reportErrors(array $errors): void;
}
