<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 *
 * @throws JsonException
 */
function superSectionsComposerMetadata(): array
{
    $json = file_get_contents(__DIR__.'/../../composer.json');

    expect($json)->toBeString();

    $composer = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    expect($composer)->toBeArray();

    return $composer;
}

it('declares the renamed package metadata and compatibility constraints', function (): void {
    $composer = superSectionsComposerMetadata();

    expect($composer['name'])->toBe('superinteractive/statamic-super-sections')
        ->and($composer)->not->toHaveKey('version')
        ->and($composer['type'])->toBe('statamic-addon')
        ->and($composer['require']['php'])->toBe('^8.4')
        ->and($composer['require']['statamic/cms'])->toBe('^5.0 || ^6.0')
        ->and($composer['require-dev']['orchestra/testbench'])->toBe('^8.36 || ^9.15 || ^10.8 || ^11.0')
        ->and($composer['autoload']['psr-4'])->toHaveKey('SuperInteractive\\StatamicSuperSections\\')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('SuperInteractive\\StatamicSuperSections\\Tests\\')
        ->and($composer['extra']['laravel']['providers'])->toBe([
            'SuperInteractive\\StatamicSuperSections\\ServiceProvider',
        ])
        ->and($composer['extra']['statamic']['name'])->toBe('Statamic Super Sections')
        ->and($composer['extra']['statamic']['description'])->toContain('Super Sections');
});

it('declares publish-ready author and support metadata', function (): void {
    $composer = superSectionsComposerMetadata();

    expect($composer['authors'][0]['email'])->toBe('hello@superinteractive.com')
        ->and($composer['support']['issues'])->toBe('https://github.com/superinteractive/statamic-super-sections/issues')
        ->and($composer['support']['source'])->toBe('https://github.com/superinteractive/statamic-super-sections')
        ->and($composer)->not->toHaveKey('minimum-stability')
        ->and($composer)->not->toHaveKey('prefer-stable');
});

it('documents the field resolver compatibility conventions', function (): void {
    $readmePath = __DIR__.'/../../README.md';

    expect($readmePath)->toBeFile();

    $readme = file_get_contents($readmePath);

    expect($readme)->toBeString()
        ->toContain('column_1')
        ->toContain('$column1')
        ->toContain('"0"')
        ->toContain('http, https, mailto, and tel')
        ->toContain('protocol-relative URLs');
});
