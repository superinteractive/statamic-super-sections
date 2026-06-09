# Statamic Super Sections

Reusable Laravel Blade component primitives for rendering Statamic section rows from pages, articles, Bard sets, replicators, and similar structured content.

## Installation

```bash
composer require superinteractive/statamic-super-sections
```

The addon is discovered automatically by Laravel and Statamic.

## Rendering Sections

Render page and article section rows with the package-provided collection components:

```blade
<x-super::page-sections :sections="$sections" />
<x-super::article-sections :sections="$sections" />
```

Attributes passed to the collection component are applied to the wrapper `<div>`:

```blade
<x-super::page-sections :sections="$sections" class="space-y-12" />
```

The `super::` namespace applies only to the package-owned collection wrappers. Individual section components remain app-owned, class-backed Blade components:

```text
App\View\Components\PageSections\Hero
App\View\Components\ArticleSections\Text
```

Child component names stay un-namespaced:

```text
page-sections.hero
article-sections.text
```

## Local Development

When developing this package with the Super Statamic Starter Kit source app, work from the package repository:

```bash
cd Packages/statamic-super-sections
```

## Field Resolver Conventions

`SectionFieldResolver` keeps Statamic values close to their native form until a section component asks for a specific normalized value.

Field handles are passed to Blade section components as Laravel-compatible attributes:

- `button_text` becomes `button-text`, which maps to `$buttonText`.
- `column_1` becomes `column1`, which maps to `$column1`.
- `hero_image_2` becomes `hero-image2`, which maps to `$heroImage2`.

Repeatable rows are filtered only when every value is blank. `null`, `false`, empty strings, whitespace-only strings, and empty arrays are blank. `0` and `"0"` are filled values and are preserved.

## Link Href Policy

Link URLs are trimmed before output. Relative paths, anchors, and the schemes http, https, mailto, and tel are allowed.

The resolver rejects blank hrefs, protocol-relative URLs, control characters, and schemes outside the allowlist such as `javascript:`, `data:`, and `sms:`.

This is an allowlist for safe editorial href output, not a full URL validator. Projects that need additional schemes should extend the addon with an explicit configuration surface before allowing those schemes.
