<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Statamic\Contracts\Assets\AssetRepository as AssetRepositoryContract;
use Statamic\Facades\Asset;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Sets;
use SuperInteractive\StatamicSuperSections\Assets\AssetRepository;
use SuperInteractive\StatamicSuperSections\ServiceProvider;
use SuperInteractive\StatamicSuperSections\Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->setUpSetPreviews();
});

it('registers the set_previews disk', function (): void {
    expect(config('filesystems.disks.set_previews'))->toBe([
        'driver' => 'local',
        'root' => resource_path('set-previews'),
        'throw' => false,
    ]);
});

it('keeps a project defined set_previews disk', function (): void {
    config()->set('filesystems.disks.set_previews.root', '/custom');

    app()->getProvider(ServiceProvider::class)->bootAddon();

    expect(config('filesystems.disks.set_previews.root'))->toBe('/custom');
});

it('points set preview images at the set_previews container', function (): void {
    expect(config('statamic.assets.set_preview_images'))->toBe(['container' => 'set_previews', 'folder' => null])
        ->and(Sets::previewImageConfig())->toBe(['container' => 'set_previews', 'folder' => null]);
});

it('lets a cp user without asset permissions load a preview thumbnail', function (): void {
    $this->actingAs($this->editor())
        ->get(Asset::find('set_previews::hero.jpg')->thumbnailUrl())
        ->assertOk();
});

it('does not let that user view other containers', function (): void {
    $this->actingAs($this->editor())
        ->getJson(Asset::find('assets::hero.jpg')->thumbnailUrl())
        ->assertForbidden();
});

it('does not widen other abilities on preview assets', function (): void {
    $asset = Asset::find('set_previews::hero.jpg');
    $gate = Gate::forUser($this->editor());

    expect($gate->allows('view', $asset))->toBeTrue()
        ->and($gate->denies('edit', $asset))->toBeTrue()
        ->and($gate->denies('delete', $asset))->toBeTrue();
});

it('still requires cp access', function (): void {
    $user = tap(User::make()->email('guest@example.com'))->save();

    expect($this->actingAs($user)->get(Asset::find('set_previews::hero.jpg')->thumbnailUrl())->status())
        ->not->toBe(200);
});

it('leaves the asset repository alone with the file driver', function (): void {
    expect(app(AssetRepositoryContract::class))->not->toBeInstanceOf(AssetRepository::class);
});
