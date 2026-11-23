<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Assets\AssetRepository as AssetRepositoryContract;
use Statamic\Eloquent\Assets\AssetModel;
use Statamic\Eloquent\Assets\AssetRepository as EloquentAssetRepository;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Statamic;
use SuperInteractive\StatamicSuperSections\Assets\AssetRepository;
use SuperInteractive\StatamicSuperSections\ServiceProvider;
use SuperInteractive\StatamicSuperSections\Tests\EloquentAssetsTestCase;

uses(EloquentAssetsTestCase::class);

beforeEach(function (): void {
    $this->setUpSetPreviews();

    Storage::disk('set_previews')->put('sub/nested.jpg', UploadedFile::fake()->image('nested.jpg')->getContent());
});

it('binds the package asset repository', function (): void {
    expect(app(AssetRepositoryContract::class))->toBeInstanceOf(AssetRepository::class);
});

it('keeps a project asset repository that extends the package repository', function (): void {
    $projectRepository = (new class extends AssetRepository {})::class;
    Statamic::repository(AssetRepositoryContract::class, $projectRepository);

    new ServiceProvider(app())->bootAddon();

    expect(app(AssetRepositoryContract::class))->toBeInstanceOf($projectRepository)
        ->and(Asset::find('set_previews::hero.jpg'))->not->toBeNull()
        ->and(AssetModel::count())->toBe(0);
});

it('does not replace another custom asset repository', function (): void {
    $customRepository = (new class extends EloquentAssetRepository {})::class;
    Statamic::repository(AssetRepositoryContract::class, $customRepository);

    new ServiceProvider(app())->bootAddon();

    expect(app(AssetRepositoryContract::class))->toBeInstanceOf($customRepository);
});

it('finds a preview image without a database row', function (): void {
    $asset = Asset::find('set_previews::hero.jpg');

    expect($asset)->not->toBeNull()
        ->and($asset->isImage())->toBeTrue()
        ->and($asset->thumbnailUrl())->toBeString()
        ->and(AssetModel::count())->toBe(0);
});

it('finds nested preview images', function (): void {
    expect(Asset::find('set_previews::sub/nested.jpg')?->path())->toBe('sub/nested.jpg');
});

it('returns null for a missing preview image', function (): void {
    expect(Asset::find('set_previews::missing.jpg'))->toBeNull()
        ->and(AssetModel::count())->toBe(0);
});

it('still requires rows for other containers', function (): void {
    expect(Asset::find('assets::hero.jpg'))->toBeNull();
});

it('delegates to the driver when the preview container does not exist', function (): void {
    AssetContainer::find('set_previews')->delete();

    expect(Asset::find('set_previews::hero.jpg'))->toBeNull();
});

it('lets a cp user load the preview thumbnail', function (): void {
    $this->actingAs($this->editor())
        ->get(Asset::find('set_previews::hero.jpg')->thumbnailUrl())
        ->assertOk();

    expect(AssetModel::count())->toBe(0);
});

it('returns null for a preview path outside the disk', function (): void {
    expect(Asset::find('set_previews::../../composer.json'))->toBeNull();
});
