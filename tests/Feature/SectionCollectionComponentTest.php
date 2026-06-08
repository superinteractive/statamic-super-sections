<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Statamic\Fields\Value;
use SuperInteractive\StatamicSuperSections\Exceptions\MissingSectionClassComponentException;
use SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections;
use SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections\ColumnOne;
use SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections\Text;
use SuperInteractive\StatamicSuperSections\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Blade::component(FixtureSections::class, 'fixture-sections');
    Blade::component(ColumnOne::class, 'fixture-sections.column-one');
    Blade::component(Text::class, 'fixture-sections.text');
});

it('renders section rows through class based dynamic blade components', function (): void {
    $html = Blade::render(
        '<x-fixture-sections :sections="$sections" class="wrapper" data-test="fixture" />',
        [
            'sections' => [
                [
                    'type' => 'text',
                    'text' => new Value('<strong>Package body</strong>'),
                ],
            ],
        ],
    );

    expect($html)
        ->toContain('class="wrapper"')
        ->toContain('data-test="fixture"')
        ->toContain('data-fixture-section="text"')
        ->toContain('<strong>Package body</strong>');
});

it('maps numeric field handles to numeric blade component props while rendering sections', function (): void {
    $html = Blade::render(
        '<x-fixture-sections :sections="$sections" />',
        [
            'sections' => [
                [
                    'type' => 'column_one',
                    'column_1' => new Value('First column'),
                ],
            ],
        ],
    );

    expect($html)
        ->toContain('data-fixture-section="column-one"')
        ->toContain('First column');
});

it('throws the package exception when the expected section class is missing', function (): void {
    try {
        Blade::render(
            '<x-fixture-sections :sections="$sections" />',
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
            ->toContain('Missing class-based fixture-sections component [fixture-sections.not-real]')
            ->toContain('FixtureSections\\NotReal');

        return;
    }

    $this->fail('Expected missing fixture section component exception.');
});

it('rejects malformed section type handles before resolving a blade component', function (): void {
    try {
        Blade::render(
            '<x-fixture-sections :sections="$sections" />',
            [
                'sections' => [
                    [
                        'type' => '../text',
                    ],
                ],
            ],
        );
    } catch (ViewException $viewException) {
        expect($viewException->getPrevious())
            ->toBeInstanceOf(InvalidArgumentException::class)
            ->and($viewException->getPrevious()?->getMessage())
            ->toBe('Section rows for [fixture-sections] must contain a valid [type] handle.');

        return;
    }

    $this->fail('Expected invalid fixture section type exception.');
});
