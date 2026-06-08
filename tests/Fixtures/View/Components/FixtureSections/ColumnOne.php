<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections;

use SuperInteractive\StatamicSuperSections\View\Components\SectionComponent;

final class ColumnOne extends SectionComponent
{
    public string $content;

    public function __construct(mixed $column1 = null)
    {
        $this->content = $this->string($column1);
    }

    public function render(): string
    {
        return '<section data-fixture-section="column-one">{{ $content }}</section>';
    }
}
