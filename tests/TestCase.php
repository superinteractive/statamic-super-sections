<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Stache;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use SuperInteractive\StatamicSuperSections\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    /**
     * Keep Stache writes in a per-test temp directory, then create the
     * `set_previews` and `assets` containers, each holding a fake `hero.jpg`.
     */
    protected function setUpSetPreviews(): void
    {
        $directory = sys_get_temp_dir().'/statamic-super-sections-'.Str::random(8);
        File::ensureDirectoryExists($directory);
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        Stache::stores()->each(fn ($store) => $store->directory($directory.'/'.$store->key()));
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        File::put($directory.'/roles.yaml', "editor:\n  title: Editor\n  permissions:\n    - 'access cp'\n");
        config()->set('statamic.users.repositories.file.paths.roles', $directory.'/roles.yaml');

        foreach (['set_previews', 'assets'] as $handle) {
            Storage::fake($handle)->put('hero.jpg', UploadedFile::fake()->image('hero.jpg')->getContent());
            AssetContainer::make($handle)->disk($handle)->save();
        }
    }

    protected function editor(): UserContract
    {
        return tap(User::make()->email('editor@example.com')->assignRole('editor'))->save();
    }
}
