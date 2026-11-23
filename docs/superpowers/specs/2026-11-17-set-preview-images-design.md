# Set Preview Images From The Repository

## Intent

On Statamic 6, every project that uses this package gets working Replicator/Bard set preview images from `resources/set-previews/*.jpg`. There are no asset database rows, no sync command, no deploy step and no role permission edits. Today each project does this setup itself, and with the Eloquent asset driver it needs `statamic:eloquent:sync-assets` after each change.

## Goals

- Set previews show for every CP user, editors included.
- With the Eloquent asset driver, preview lookups never read or write asset rows.
- Projects keep only their JPGs, their `image:` keys and the `set_previews` container YAML.

## Non-goals

- No install or ensure command for the container. The starter kit and current projects already ship the YAML, and the README documents it.
- No opt-out or override config key.
- No EN/NL translations in the package. The container title comes from the project's YAML, so the project owns its translation.
- No override for the file (Stache) asset driver.
- No support for Statamic 5. The feature does nothing there.

## Anti-goals

- Any database write for preview images.
- Widening access to any ability other than `view`, or to any container other than the preview container.

## Constraints

- Composer stays `statamic/cms: ^5.0 || ^6.0`. The feature runs only on Statamic 6+.
- Keep new symbols to a minimum: no wrappers, `declare(strict_types=1)`, full types, Pint.
- Do not commit.

## Approaches

1. **Eloquent `AssetRepository::findById` override (chosen).** Return an unsaved asset for files on the preview disk. This is verified to write nothing, because the driver's `Asset::meta()` generates the metadata without saving it.
2. Create rows on boot or on first lookup. Rejected: this is still a database write, and the sync step under another name.
3. Override the `Sets` fieldtype or the driver's `asset` or `model` class. Rejected: the `Sets` preview methods are private, and the class options only apply after a row is found.

For the container, the user picked "keep the YAML in projects" over a package command or an in-memory container.

## Design

All of this is in `ServiceProvider`, inside a Statamic 6+ guard:

- **Disk:** if `filesystems.disks.set_previews` is not set, register a `local` disk with root `resource_path('set-previews')` and `throw` set to false.
- **Config:** set `statamic.assets.set_preview_images` to `['container' => 'set_previews', 'folder' => null]`.
- **Repository:** only when `config('statamic.eloquent-driver.assets.driver') === 'eloquent'` and the driver's `AssetRepository` class exists, bind `Statamic::repository(AssetRepositoryContract::class, ...)` in `bootAddon()`, after the driver's `register()`. The class extends `Statamic\Eloquent\Assets\AssetRepository` and overrides only `findById`. For the container that `Sets::previewImageConfig()` returns, it gives back `$this->make()->container($container)->path($path)` when the file exists on the container disk, and `null` when it does not. Every other container calls `parent::findById()`.
- **Read access:** a `Gate::before` rule returns `true` for the `view` ability on an asset in the preview container. In every other case it returns `null`, so the normal policy applies.

## Tests (Pest)

- Eloquent driver: a preview JPG with no row resolves through `Asset::find('set_previews::x.jpg')`, and no row is written.
- Eloquent driver: other containers still need rows.
- A non-super CP user gets a 200 from the preview's thumbnail route, and gets a 403 for another container's asset.
- File driver: the asset repository is not replaced.
- Use `Storage::fake('set_previews')` and `UploadedFile::fake()->image(...)`.
- This needs `statamic/eloquent-driver` in `require-dev`.

## README

Document where the previews live, the container YAML that the project keeps, the database-free lookup, the read-access rule, the clash with other addons that replace the asset repository, `.meta` in `.gitignore` for the file driver, and the Statamic 6+ requirement.

## Left to the builder

- How to detect Statamic 6, for example `version_compare(Statamic::version(), '6.0.0', '>=')`.
- How to test the Eloquent driver: migrations and config inside Testbench.
