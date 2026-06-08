<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Exceptions;

use RuntimeException;

final class MissingSectionClassComponentException extends RuntimeException
{
    public static function for(string $group, string $type, string $component, string $class): self
    {
        return new self(sprintf(
            'Missing class-based %s component [%s] for Statamic section type [%s]. Expected class [%s].',
            $group,
            $component,
            $type,
            $class,
        ));
    }
}
