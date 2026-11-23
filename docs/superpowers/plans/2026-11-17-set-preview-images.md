# Set Preview Images Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On Statamic 6, Replicator/Bard set previews load from `resources/set-previews/*.jpg` with no asset database rows and no role edits.

**Architecture:** `ServiceProvider::bootAddon()` does four things behind a Statamic 6 guard. It registers the `set_previews` disk, sets `statamic.assets.set_preview_images`, adds a `Gate::before` rule for `view` on the preview container, and, only when the Eloquent asset driver is active, binds an asset repository that overrides `findById` for the preview container. Projects keep the container YAML.

**Tech Stack:** PHP 8.4, Statamic 6 (`statamic/cms` 6.20.1 in `vendor/`), `statamic/eloquent-driver` ^5 (new dev dependency), Pest, Orchestra Testbench through `Statamic\Testing\AddonTestCase`.

**Spec:** `docs/superpowers/specs/2026-11-17-set-preview-images-design.md`

## Global Constraints

- Composer `require` stays `"statamic/cms": "^5.0 || ^6.0"`. The whole feature is skipped unless `Composer\InstalledVersions::satisfies(new Composer\Semver\VersionParser, 'statamic/cms', '^6.0')`.
- Disk handle `set_previews`, root `resource_path('set-previews')`, driver `local`, `throw` false. Register it only when `config('filesystems.disks.set_previews')` is null.
- Preview config: `statamic.assets.set_preview_images` = `['container' => 'set_previews', 'folder' => null]`. There is no opt-out key. Because addons boot after app providers, a project cannot override this value.
- Read the preview container handle with `Statamic\Fieldtypes\Sets::previewImageConfig()['container'] ?? null`, not a hard-coded string.
- Bind the repository only when `config('statamic.eloquent-driver.assets.driver') === 'eloquent'` and `class_exists(\Statamic\Eloquent\Assets\AssetRepository::class)`.
- `declare(strict_types=1)`, full type declarations, as few new symbols as possible, no wrappers. Run `vendor/bin/pint`.
- Do not commit, push or change git state. The user's policy forbids it.
- Run the full suite with `vendor/bin/pest --parallel`.

## Review Focus

1. A fieldset `image:` names a JPG that is not on disk. Expected: `Asset::find` returns `null`, nothing is thrown and no row is written. (Task 2)
2. The project already defines a `set_previews` disk with another root. Expected: the package leaves it as it is. (Task 1)
3. The project has no `set_previews` container YAML. Expected: `previewImageConfig()` returns `null`, the repository calls the parent and the gate rule grants nothing. (Task 2)
4. An editor tries `edit` or `delete` on a preview asset. Expected: denied, because only `view` is widened. (Task 1)
5. A user without `access cp` opens the thumbnail route. Expected: still blocked by the CP middleware. (Task 1)

---

### Task 1: Disk, preview config and read access

**Files:**
- Modify: `src/ServiceProvider.php`
- Test: `tests/Feature/SetPreviewImagesTest.php` (create)

**Interfaces:**
- Produces: the `set_previews` disk, the config value above and the gate rule. Task 2 relies on the `bootAddon()` guard block existing, so it can add the repository binding inside it.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/SetPreviewImagesTest.php` with `uses(TestCase::class)` and the `Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk` trait. That trait keeps containers and users out of `tests/__fixtures__`. In `beforeEach`:
  - point `statamic.users.repositories.file.paths.roles` at a temp YAML file holding `editor: {title: Editor, permissions: ['access cp']}`;
  - create `AssetContainer::make('set_previews')->disk('set_previews')->save()` and `AssetContainer::make('assets')->disk('assets')->save()`;
  - call `Storage::fake('set_previews')` and `Storage::fake('assets')`, and put `UploadedFile::fake()->image('hero.jpg')` on each.

  Tests:
  - `it('registers the set_previews disk')`: `config('filesystems.disks.set_previews')` equals `['driver' => 'local', 'root' => resource_path('set-previews'), 'throw' => false]`.
  - `it('keeps a project defined set_previews disk')`: set `filesystems.disks.set_previews.root` to `'/custom'`, then call `app()->getProvider(ServiceProvider::class)->bootAddon()` again. The root is still `'/custom'`.
  - `it('points set preview images at the set_previews container')`: `config('statamic.assets.set_preview_images')` equals `['container' => 'set_previews', 'folder' => null]`, and `Sets::previewImageConfig()` equals the same array.
  - `it('lets a cp user without asset permissions load a preview thumbnail')`: act as a non-super user with role `editor`, `get(Asset::find('set_previews::hero.jpg')->thumbnailUrl())` → `assertOk()`.
  - `it('does not let that user view other containers')`: same user, the thumbnail of `assets::hero.jpg` → `assertForbidden()`.
  - `it('does not widen other abilities on preview assets')`: `Gate::forUser($editor)->denies('edit', $previewAsset)` and `->denies('delete', $previewAsset)` are both true.
  - `it('still requires cp access')`: a user with no role opens the preview thumbnail URL. Assert `->status()` is not `200`.

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/SetPreviewImagesTest.php`. Expected: FAIL. The disk and config are missing, and the thumbnail requests get 403 for the editor.

- [ ] **Step 3: Implement in `ServiceProvider::bootAddon(): void`.** After the existing `Blade::componentNamespace` call, return early unless the Statamic 6 check passes. Then:
  - set the disk with `config()->set(...)` when it is absent;
  - set the preview config;
  - add `Gate::before(fn ($user, string $ability, array $arguments): ?bool => ...)`. It returns `true` only when `$ability === 'view'`, `$arguments[0]` is an instance of `Statamic\Contracts\Assets\Asset`, and its `containerHandle()` equals the preview container handle. Otherwise it returns `null`.

- [ ] **Step 4: Run** `vendor/bin/pest tests/Feature/SetPreviewImagesTest.php`. Expected: PASS.

### Task 2: Database-free lookup with the Eloquent asset driver

**Files:**
- Modify: `composer.json`. Add `"statamic/eloquent-driver": "^5.0"` to `require-dev`, then run `composer update statamic/eloquent-driver`.
- Create: `src/Assets/AssetRepository.php`
- Modify: `src/ServiceProvider.php` (inside the Task 1 guard block)
- Create: `tests/EloquentAssetsTestCase.php`
- Test: `tests/Feature/SetPreviewEloquentAssetsTest.php`

**Interfaces:**
- Consumes: the Task 1 guard block in `bootAddon()`.
- Produces: `SuperInteractive\StatamicSuperSections\Assets\AssetRepository extends Statamic\Eloquent\Assets\AssetRepository` with `public function findById($id): ?Statamic\Contracts\Assets\Asset`.

- [ ] **Step 1: Create `tests/EloquentAssetsTestCase.php`**, extending `Tests\TestCase`. The driver reads its config in `register()`, and Testbench ignores package discovery. So this class:
  - adds `Statamic\Eloquent\ServiceProvider::class` to `getPackageProviders()`;
  - in `defineEnvironment($app)`, sets `statamic.eloquent-driver.assets.driver` to `eloquent`, the asset model to `Statamic\Eloquent\Assets\AssetModel`, `statamic.eloquent-driver.asset_containers.driver` to `file`, and a `sqlite` `:memory:` connection as default;
  - in `defineDatabaseMigrations()`, loads the driver's `database/migrations/2024_03_07_100000_create_asset_table.php`, through `loadMigrationsFrom` or by requiring it and calling `->up()`.

- [ ] **Step 2: Write the failing tests** in `tests/Feature/SetPreviewEloquentAssetsTest.php` with `uses(EloquentAssetsTestCase::class)`. Use the same container and fake-disk setup as Task 1 (`hero.jpg` on both disks), plus `sub/nested.jpg` on `set_previews`. The model is `Statamic\Eloquent\Assets\AssetModel`.
  - `it('binds the package asset repository')`: `app(AssetRepositoryContract::class)` is an instance of `SuperInteractive\StatamicSuperSections\Assets\AssetRepository`.
  - `it('finds a preview image without a database row')`: `Asset::find('set_previews::hero.jpg')` is not null, `->isImage()` is true, `->thumbnailUrl()` is a string, and `AssetModel::count()` is `0` after the call.
  - `it('finds nested preview images')`: `Asset::find('set_previews::sub/nested.jpg')?->path()` equals `'sub/nested.jpg'`.
  - `it('returns null for a missing preview image')`: `Asset::find('set_previews::missing.jpg')` is null and `AssetModel::count()` is `0`.
  - `it('still requires rows for other containers')`: `Asset::find('assets::hero.jpg')` is null.
  - `it('delegates to the driver when the preview container does not exist')`: delete the `set_previews` container, then `Asset::find('set_previews::hero.jpg')` is null.
  - `it('lets a cp user load the preview thumbnail')`: the same editor-role request as Task 1 → `assertOk()`, and `AssetModel::count()` is `0` afterwards.

  Then add to `tests/Feature/SetPreviewImagesTest.php` (file driver): `it('leaves the asset repository alone with the file driver')`. It asserts that `app(AssetRepositoryContract::class)` is not an instance of the package `AssetRepository`.

- [ ] **Step 3: Run** `vendor/bin/pest tests/Feature/SetPreviewEloquentAssetsTest.php`. Expected: FAIL. The bind assertion fails and the row-free find returns null.

- [ ] **Step 4: Implement `findById` in `src/Assets/AssetRepository.php`.** Split `$id` on `::` into `$container` and `$path`. If `$container !== (Sets::previewImageConfig()['container'] ?? null)`, return `parent::findById($id)`. Otherwise build `$this->make()->container($container)->path($path)` and return it when `$asset->disk()->exists($path)`, or `null` when the file is missing. Never call `save()` or `writeMeta()`.

- [ ] **Step 5: Bind it** inside the Task 1 guard block in `bootAddon()`, behind the driver check from Global Constraints: `Statamic::repository(AssetRepositoryContract::class, Assets\AssetRepository::class)`. If the bind test passes but `Asset::find` still reaches the driver's repository, the facade was resolved earlier. In that case add `Asset::clearResolvedInstance(AssetRepositoryContract::class)` right after the bind.

- [ ] **Step 6: Run** `vendor/bin/pest tests/Feature/SetPreviewEloquentAssetsTest.php tests/Feature/SetPreviewImagesTest.php`. Expected: PASS.

### Task 3: README and full verification

**Files:**
- Modify: `README.md` (new `## Set Preview Images` section before `## Local Development`)

- [ ] **Step 1: Write the section.** Cover:
  - Statamic 6+ only, and nothing happens on 5;
  - put JPGs in `resources/set-previews/` and reference them by filename in the set's `image:` key;
  - the container YAML the project keeps, with the exact contents: `title: "Set Previews"`, `disk: set_previews`, `allow_uploads`, `allow_downloading`, `allow_renaming`, `allow_moving` and `create_folders` all `false`, `sort_by: basename`, `sort_dir: asc`;
  - the package registers the disk and sets `statamic.assets.set_preview_images`, so projects should remove both from their config;
  - Eloquent asset driver: no rows, no `statamic:eloquent:sync-assets`, and a clash with any other addon that replaces the asset repository;
  - file asset driver: Statamic writes `.meta/` next to the JPGs, so add `resources/set-previews/.meta` to `.gitignore`;
  - any CP user can view preview thumbnails without a `view set_previews assets` permission.

- [ ] **Step 2: Run** `vendor/bin/pint`, then `vendor/bin/pest --parallel`. Expected: Pint reports no remaining issues and every test passes. The existing `ComposerMetadataTest` checks only `orchestra/testbench` in `require-dev`, so the new dev dependency does not break it.
