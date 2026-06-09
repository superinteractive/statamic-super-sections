<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Statamic\Fields\Value;
use SuperInteractive\StatamicSuperSections\Exceptions\MissingSectionClassComponentException;
use SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections\Text;
use SuperInteractive\StatamicSuperSections\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (! class_exists('App\\View\\Components\\PageSections\\Text', false)) {
        class_alias(Text::class, 'App\\View\\Components\\PageSections\\Text');
    }

    if (! class_exists('App\\View\\Components\\ArticleSections\\Text', false)) {
        class_alias(Text::class, 'App\\View\\Components\\ArticleSections\\Text');
    }

    Blade::component('App\\View\\Components\\PageSections\\Text', 'page-sections.text');
    Blade::component('App\\View\\Components\\ArticleSections\\Text', 'article-sections.text');
});

it('renders page sections through the super namespaced collection component', function (): void {
    $html = Blade::render(
        '<x-super::page-sections :sections="$sections" class="page-wrapper" data-test="page-sections" />',
        [
            'sections' => [
                [
                    'type' => 'text',
                    'text' => new Value('<strong>Page body</strong>'),
                ],
            ],
        ],
    );

    expect($html)
        ->toContain('class="page-wrapper"')
        ->toContain('data-test="page-sections"')
        ->toContain('data-fixture-section="text"')
        ->toContain('<strong>Page body</strong>');
});

it('renders article sections through the super namespaced collection component', function (): void {
    $html = Blade::render(
        '<x-super::article-sections :sections="$sections" class="article-wrapper" data-test="article-sections" />',
        [
            'sections' => [
                [
                    'type' => 'text',
                    'text' => new Value('<em>Article body</em>'),
                ],
            ],
        ],
    );

    expect($html)
        ->toContain('class="article-wrapper"')
        ->toContain('data-test="article-sections"')
        ->toContain('data-fixture-section="text"')
        ->toContain('<em>Article body</em>');
});

it('reports missing page section classes with the super group and app namespace', function (): void {
    try {
        Blade::render(
            '<x-super::page-sections :sections="$sections" />',
            [
                'sections' => [
                    [
                        'type' => 'not_real',
                    ],
                ],
            ],
        );
    } catch (ViewException $viewException) {
        expect($viewException->getPrevious())
            ->toBeInstanceOf(MissingSectionClassComponentException::class)
            ->and($viewException->getPrevious()?->getMessage())
            ->toContain('Missing class-based super::page-sections component [page-sections.not-real]')
            ->toContain('App\\View\\Components\\PageSections\\NotReal');

        return;
    }

    $this->fail('Expected missing page section component exception.');
});

it('reports missing article section classes with the super group and app namespace', function (): void {
    try {
        Blade::render(
            '<x-super::article-sections :sections="$sections" />',
            [
                'sections' => [
                    [
                        'type' => 'not_real',
                    ],
                ],
            ],
        );
    } catch (ViewException $viewException) {
        expect($viewException->getPrevious())
            ->toBeInstanceOf(MissingSectionClassComponentException::class)
            ->and($viewException->getPrevious()?->getMessage())
            ->toContain('Missing class-based super::article-sections component [article-sections.not-real]')
            ->toContain('App\\View\\Components\\ArticleSections\\NotReal');

        return;
    }

    $this->fail('Expected missing article section component exception.');
});
