<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components;

use SuperInteractive\StatamicSuperSections\View\Components\SectionCollectionComponent;

final class FixtureSections extends SectionCollectionComponent
{
    public function render(): string
    {
        return '<div {{ $attributes }}>@foreach ($renderedSections as $renderedSection){!! $renderedSection !!}@endforeach</div>';
    }

    protected static function group(): string
    {
        return 'fixture-sections';
    }

    protected static function componentPrefix(): string
    {
        return 'fixture-sections';
    }
}
