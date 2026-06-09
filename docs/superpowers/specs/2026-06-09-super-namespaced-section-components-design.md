# Super Namespaced Section Components Design

Date: 2026-06-09
Status: Ready for implementation planning

## Goal

Expose package-owned Blade collection components for Statamic page and article sections:

```blade
<x-super::page-sections :sections="$sections" />
<x-super::article-sections :sections="$sections" />
```

The consuming Statamic app should no longer need to copy `page-sections.blade.php` and `article-sections.blade.php` into `resources/views/components` just to loop through rendered section rows.

## Context

The package already has the reusable rendering primitive in `SectionCollectionComponent`. It accepts a `sections` prop, validates each row's `type`, maps each row into a child Blade component, verifies the expected class extends `SectionComponent`, and stores the rendered child HTML in `$renderedSections`.

What is missing is the public package component layer:

- A namespaced page collection component.
- A namespaced article collection component.
- Service provider registration that lets Laravel resolve them as `super::` Blade components.

The package should provide the collection wrappers only. The individual section implementations remain app-owned because each Statamic project defines its own section types, props, markup, and styling.

## Public API

The stable 1.0 API is namespaced:

```blade
<x-super::page-sections :sections="$pageSections" class="space-y-12" />
<x-super::article-sections :sections="$articleSections" />
```

`sections` accepts the same inputs currently supported by `SectionCollectionComponent`: arrays, Statamic augmented values, and other values handled by `SectionFieldResolver::sectionItems()`.

Attributes passed to the top-level component belong on the wrapper `<div>`, matching the local anonymous components currently used in apps:

```blade
<div {{ $attributes }}>
    @foreach ($renderedSections as $renderedSection)
        {!! $renderedSection !!}
    @endforeach
</div>
```

The package should not register global `<x-page-sections>` or `<x-article-sections>` aliases for 1.0. The `super::` namespace avoids collisions with app components and makes ownership clear.

## Architecture

Register the package component namespace from `ServiceProvider::bootAddon()`:

```php
Blade::componentNamespace(
    'SuperInteractive\\StatamicSuperSections\\View\\Components',
    'super',
);
```

This makes Laravel resolve:

- `<x-super::page-sections>` to `SuperInteractive\StatamicSuperSections\View\Components\PageSections`.
- `<x-super::article-sections>` to `SuperInteractive\StatamicSuperSections\View\Components\ArticleSections`.

Use `bootAddon()` rather than overriding `boot()` so Statamic's addon provider boot chain remains intact.

Add two concrete collection components:

- `SuperInteractive\StatamicSuperSections\View\Components\PageSections`
- `SuperInteractive\StatamicSuperSections\View\Components\ArticleSections`

Both extend `SectionCollectionComponent` and render the same wrapper Blade string. No publishable view, config file, or installer is needed for the 1.0 surface.

`PageSections` should define:

```php
protected static function group(): string
{
    return 'super::page-sections';
}

protected static function componentPrefix(): string
{
    return 'page-sections';
}

protected static function sectionClassNamespace(): string
{
    return app()->getNamespace().'View\\Components\\PageSections';
}
```

`ArticleSections` should define:

```php
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
```

The `componentPrefix()` values intentionally stay un-namespaced. Child section components are application components, not package components:

- A page row with `type: hero_banner` resolves to the Blade component `page-sections.hero-banner`.
- The expected class is `App\View\Components\PageSections\HeroBanner`, or the equivalent namespace returned by `app()->getNamespace()`.
- An article row with `type: text` resolves to `article-sections.text`.
- The expected class is `App\View\Components\ArticleSections\Text`, or the equivalent namespace returned by `app()->getNamespace()`.

This keeps the package responsible for the collection-level API and the app responsible for the actual section rendering.

## Data Flow

1. Blade receives `<x-super::page-sections :sections="$sections" />`.
2. Laravel resolves the namespaced component class through the `super` component namespace.
3. `SectionCollectionComponent` receives the `sections` prop and asks `SectionFieldResolver` for normalized section rows.
4. Each row's `type` is validated and converted to a kebab-case component handle.
5. The concrete collection class supplies the app class namespace and child component prefix.
6. The package verifies the expected child class exists and extends `SectionComponent`.
7. The child row is rendered through `<x-dynamic-component>`.
8. The top-level component prints a wrapper `<div>` containing the rendered child HTML.

## Error Handling

Keep the existing failure behavior.

Rows with blank `type` values throw `InvalidArgumentException` with the relevant group name:

```text
Section rows for [super::page-sections] must contain a stringable [type] value.
```

Rows with invalid handles throw `InvalidArgumentException` before any Blade component lookup:

```text
Section rows for [super::article-sections] must contain a valid [type] handle.
```

Rows whose expected app component class is missing or does not extend `SectionComponent` throw `MissingSectionClassComponentException`. The message should include the namespaced collection group, the child Blade component name, the original section type, and the expected app class:

```text
Missing class-based super::page-sections component [page-sections.not-real] for Statamic section type [not_real]. Expected class [App\View\Components\PageSections\NotReal].
```

## Testing

Use test-driven implementation.

Update the package test case so the package service provider is loaded during feature tests. The top-level `super::` components must be available because the service provider registered them, not because the test manually called `Blade::component()` for `PageSections` or `ArticleSections`.

Add feature coverage for:

- `<x-super::page-sections>` renders a wrapper `<div>` with passed attributes.
- `<x-super::page-sections>` renders child section HTML from a page section fixture.
- `<x-super::article-sections>` renders a wrapper `<div>` with passed attributes.
- `<x-super::article-sections>` renders child section HTML from an article section fixture.
- A missing page section class reports `super::page-sections`, `page-sections.<handle>`, and the expected app namespace.
- A missing article section class reports `super::article-sections`, `article-sections.<handle>`, and the expected app namespace.
- Existing `SectionCollectionComponent` fixture tests continue to pass.

The tests may register or alias child fixture components as app-owned section components. That is acceptable because this feature is not responsible for Laravel's normal discovery of app components. The tests should not manually register the package-owned top-level `super::` collection components.

Run:

```bash
composer test
composer pint
composer validate --strict
```

## Documentation

Update `README.md` with the namespaced usage:

```blade
<x-super::page-sections :sections="$sections" />
<x-super::article-sections :sections="$sections" />
```

Document the expected app-owned section class locations:

```text
App\View\Components\PageSections\Hero
App\View\Components\ArticleSections\Text
```

Also document that the child component names stay un-namespaced:

```text
page-sections.hero
article-sections.text
```

This is important because the `super::` prefix applies to the package collection components only.

## Non-Goals

- No global `<x-page-sections>` or `<x-article-sections>` aliases.
- No publishable Blade views for the wrapper components.
- No config file for changing the `super` prefix.
- No config file for changing app section namespaces.
- No automatic discovery or generation of individual section components.
- No support for anonymous child section views unless the existing class-based `SectionCollectionComponent` contract is intentionally expanded in a separate design.

## Release Impact

This is appropriate for the 1.0.0 release because it defines the package's stable Blade API and removes duplicated wrapper files from consuming apps.

The change is additive for apps currently using local `<x-page-sections>` or `<x-article-sections>` components. Those apps can migrate template usage to the package namespace and then remove their local wrapper files:

```blade
<x-super::page-sections :sections="$sections" />
<x-super::article-sections :sections="$sections" />
```
