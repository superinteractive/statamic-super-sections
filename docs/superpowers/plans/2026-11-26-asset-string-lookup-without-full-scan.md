# Asset String Lookup Without Full Scan Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop `SectionFieldResolver` from loading every asset in every container when an asset string does not resolve.

**Architecture:** `resolveAssetString()` (`src/Support/SectionFieldResolver.php:449-472`) is the only place that resolves asset strings. `singleAsset()`, `assets()` and `resolveAsset()` all call it. It falls back to `Asset::findByPath()`. In Statamic core (`vendor/statamic/cms/src/Assets/AssetRepository.php:111-116`), that method is `$this->all()->filter(...)`, so it hydrates every asset. The fix removes both `findByPath()` calls and keeps only the lookups that query by ID or URL.

**Tech Stack:** PHP 8.4, Statamic 6.42 (`^5.0 || ^6.0`), statamic/eloquent-driver 5.13, Pest.

**Spec:** The production incident report pasted in this session. One cold homepage render hydrated 60,752 `AssetModel`s (551 MB against a 128 MB `memory_limit`). The cause was entry 59459: its `image` is `artikelen/thumbnails/anp-575097049.jpg`, the file is on disk, and the file has no `assets_meta` row.

## Root cause

1. A bare string such as `artikelen/thumbnails/x.jpg` first tries `Asset::find('assets::…')`. With the Eloquent driver this is one indexed query, and it returns `null` when no `assets_meta` row exists.
2. The code then calls `Asset::findByPath($asset)`, which runs `Asset::all()`. That loads every asset in every container.
3. **`findByPath()` cannot ever match these strings.** It compares against `resolvedPath()`, which is `container()->diskPath().'/'.path()` (`vendor/statamic/cms/src/Assets/Asset.php:404-407`). That is an absolute disk path. A relative path (`artikelen/…`) or a URL (`/assets/…`) never equals it. So the fallback has no benefit and a very high cost. The fix deletes it and does not replace it.

The suggestion in the report (`AssetContainer::all()->map(fn ($c) => $c->asset($path))`) is rejected. It adds a cross-container search that the code never did successfully. With the Stache driver, `$container->asset()` also calls `exists()`, which lists every file in the container (`Asset.php:224-231`). That is the same class of problem.

## Global Constraints

- Change only `src/Support/SectionFieldResolver.php` and `tests/Unit/SectionFieldResolverTest.php`. Add no new symbols.
- Do not call `Asset::all()`, `Asset::findByPath()`, `Asset::wherePath()` or `AssetContainer::asset()` from the resolver.
- Strings with `::` keep resolving through `Asset::find($asset)`.
- Bare strings keep resolving from the `assets` container through `Asset::find('assets::'.$asset)`. The existing test `resolves bare asset filenames from the default assets container` must keep passing unchanged.
- Run the full suite with `--parallel`.
- Do not commit until the user asks.

## Review Focus

1. A bare relative path that is on disk but has no `assets_meta` row (the production case) → `null`, with one `Asset::find` call and no scan. Pinned in Task 1.
2. A root-relative URL that misses (`/assets/missing.jpg`) → `null`, with one `Asset::find($url)` call. It must not first try `assets::/assets/…`. Pinned in Task 1.
3. A multi-asset field with a list of strings → each item goes through the same `resolveAssetString()`, so one item that misses does not scan. Pinned in Task 1 through `assets([...])`.
4. A bare path that exists only in a container other than `assets` → still `null`. This is no change, because `findByPath()` never matched it. The return value is documented here and is not tested.
5. An absolute URL (`https://…/assets/x.jpg`) that exists → resolves through core `findByUrl()`. This branch is the same as item 2, so it has no separate test.

---

### Task 1: Remove the `findByPath()` fallback from `resolveAssetString()`

**Files:**
- Modify: `src/Support/SectionFieldResolver.php:449-472`
- Test: `tests/Unit/SectionFieldResolverTest.php` (add after `resolves bare asset filenames from the default assets container`, about line 325)

**Interfaces:**
- Consumes: nothing new.
- Produces: no signature change. `private function resolveAssetString(string $asset): mixed` keeps its name and return type.

- [ ] **Step 1: Write the failing test**

```php
it('does not scan every asset when an asset path or url does not resolve', function (): void {
    $resolver = app(SectionFieldResolver::class);

    Asset::shouldReceive('find')->once()->with('assets::artikelen/missing.jpg')->andReturnNull();
    Asset::shouldReceive('find')->once()->with('/assets/missing.jpg')->andReturnNull();
    Asset::shouldNotReceive('findByPath', 'all');

    expect($resolver->singleAsset('artikelen/missing.jpg'))->toBeNull()
        ->and($resolver->assets(['/assets/missing.jpg']))->toHaveCount(0);
});
```

- [ ] **Step 2: Run the test to verify that it fails**

Run: `vendor/bin/pest --filter="does not scan every asset"`
Expected: FAIL. Mockery reports an unexpected `findByPath` call, or an unexpected `find('assets::/assets/missing.jpg')` call.

- [ ] **Step 3: Simplify `resolveAssetString()`**

Keep the existing lookup order and remove only the two `findByPath()` calls:
- A string that contains `::` returns `Asset::find($asset)`.
- Any other string first tries `Asset::find('assets::'.$asset)`. If that misses and the string starts with `/`, `http://` or `https://`, it tries `Asset::find($asset)` (core `findByUrl()`). Otherwise it returns `null`.

Ruling (after the final review): an earlier draft sent URL-like strings straight to `Asset::find($asset)`. That broke file-driver sites, because core's `Asset::path()` getter strips a leading `/` (`vendor/statamic/cms/src/Assets/Asset.php:391`). So `assets::/artikelen/x.jpg` resolves there. The test `resolves root-relative paths from the default assets container before trying them as urls` pins this.

- [ ] **Step 4: Run the test to verify that it passes**

Run: `vendor/bin/pest --filter="does not scan every asset|default assets container|first native asset"`
Expected: 3 passed.

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/pest --parallel` and `vendor/bin/pint --test`
Expected: all tests pass. Pint reports no changes.

- [ ] **Step 6: Commit (only after the user approves)**

```bash
git add src/Support/SectionFieldResolver.php tests/Unit/SectionFieldResolverTest.php
git commit -m "Stop scanning every asset when an asset string does not resolve"
```

## After the package change (outside this repository, the user decides)

- Tag a patch release (v1.2.2) and run `composer update superinteractive/statamic-super-sections` in the affected project.
- The 51 entries without `assets_meta` rows will then render without an image instead of crashing. To show their images again, run `php please statamic:eloquent:sync-assets --container=assets` on production. That writes to the production database, so it needs the user's approval.
