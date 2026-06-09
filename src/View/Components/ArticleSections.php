<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\View\Components;

final class ArticleSections extends SectionCollectionComponent
{
    public function render(): string
    {
        return '<div {{ $attributes }}>@foreach ($renderedSections as $renderedSection){!! $renderedSection !!}@endforeach</div>';
    }

    protected static function group(): string
    {
        return 'super::article-sections';
    }

    protected static function componentPrefix(): string
    {
        return 'article-sections';
    }

    protected static function sectionClassNamespace(): string
    {
        return app()->getNamespace().'View\\Components\\ArticleSections';
    }
}
