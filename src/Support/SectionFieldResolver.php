<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Fields\ArrayableString;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Modifiers\Modify;
use Statamic\Tags\FluentTag;
use stdClass;
use Stringable;
use Traversable;

use function Statamic\View\Blade\value as statamic_value;

final class SectionFieldResolver
{
    private const array ALLOWED_HREF_SCHEMES = [
        'http',
        'https',
        'mailto',
        'tel',
    ];

    public function string(mixed $value): string
    {
        $value = $this->raw($value);

        if ($value === null) {
            return '';
        }

        return is_scalar($value) || $value instanceof Stringable
            ? (string) $value
            : '';
    }

    public function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);

        return $value === '' ? null : $value;
    }

    public function bool(mixed $value): bool
    {
        return filter_var($this->raw($value), FILTER_VALIDATE_BOOL);
    }

    public function html(mixed $value): string
    {
        $value = $this->augmented($value);

        if ($value === null) {
            return '';
        }

        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        return is_scalar($value) || $value instanceof Stringable
            ? (string) $value
            : '';
    }

    public function collection(mixed $value): Collection
    {
        $value = $this->outerValue($value);

        if ($value === null || $value === '') {
            return collect();
        }

        $collection = match (true) {
            $value instanceof Collection => $value,
            $value instanceof Traversable => collect(iterator_to_array($value)),
            is_array($value) => collect($value),
            default => collect([$value]),
        };

        return $collection
            ->filter(fn (mixed $item): bool => $this->rowIsFilled($item))
            ->values();
    }

    public function entries(mixed $value): Collection
    {
        $value = $this->augmented($value);

        if ($value instanceof StatamicQueryBuilder && method_exists($value, 'get')) {
            $value = $value->get();
        }

        return $this->collection($value)
            ->map(fn (mixed $entry): mixed => $this->resolveEntry($entry))
            ->filter()
            ->values();
    }

    public function terms(mixed $value): Collection
    {
        $value = $this->raw($value);

        if ($value instanceof StatamicQueryBuilder && method_exists($value, 'get')) {
            $value = $value->get();
        }

        return $this->collection($value);
    }

    public function singleAsset(mixed $value): mixed
    {
        return $this->assets($value)->first();
    }

    public function assets(mixed $value): Collection
    {
        $value = $this->raw($value);

        if ($value instanceof StatamicQueryBuilder && method_exists($value, 'get')) {
            $value = $value->get();
        }

        if (is_string($value)) {
            return collect([$this->resolveAssetString($value)])
                ->filter()
                ->values();
        }

        return $this->collection($value)
            ->map(fn (mixed $asset): mixed => $this->resolveAsset($asset))
            ->filter()
            ->values();
    }

    public function date(mixed $value): ?CarbonInterface
    {
        $value = $this->raw($value);

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (empty($value) || (! is_scalar($value) && ! $value instanceof Stringable)) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    public function field(mixed $row, string $handle, mixed $default = null): mixed
    {
        if ($row instanceof Value) {
            $row = $row->raw();
        }

        if ($row instanceof Values) {
            return $row->getProxiedInstance()->get($handle, $default);
        }

        if ($row instanceof Collection) {
            return $row->get($handle, $default);
        }

        if (is_array($row)) {
            return array_key_exists($handle, $row) ? $row[$handle] : $default;
        }

        if (is_object($row) && property_exists($row, $handle)) {
            return $row->{$handle};
        }

        return $default;
    }

    /**
     * @param  array<int, string>  $handles
     * @return array<string, mixed>
     */
    public function fields(mixed $row, array $handles): array
    {
        return collect($handles)
            ->mapWithKeys(fn (string $handle): array => [
                $handle => $this->field($row, $handle),
            ])
            ->all();
    }

    public function formHandle(mixed $value): ?string
    {
        $value = $this->raw($value);

        if (is_object($value) && method_exists($value, 'handle')) {
            $value = $value->handle();
        }

        if (! is_scalar($value) && ! $value instanceof Stringable) {
            return null;
        }

        $handle = mb_trim((string) $value);

        return $handle === '' ? null : $handle;
    }

    /**
     * @return array{url: ?string, text: ?string, target: string}
     */
    public function link(mixed $value): array
    {
        $value = $this->raw($value);

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            $value = [];
        }

        return [
            'url' => $this->linkUrl($value['url'] ?? null),
            'text' => $this->nullableRawString($value['text'] ?? null),
            'target' => $this->linkTarget($value),
        ];
    }

    public function raw(mixed $value): mixed
    {
        while ($this->isStatamicWrapper($value)) {
            $value = $value instanceof Value
                ? $value->raw()
                : statamic_value($value);
        }

        return $value;
    }

    /**
     * @return array<int, mixed>
     */
    public function sectionItems(mixed $sections): array
    {
        $sections = $this->outerValue($sections);

        if ($sections === null || $sections === '') {
            return [];
        }

        if ($sections instanceof Collection) {
            return $sections->values()->all();
        }

        if ($sections instanceof Traversable) {
            return array_values(iterator_to_array($sections, false));
        }

        if (is_array($sections)) {
            return array_values($sections);
        }

        throw new InvalidArgumentException('Section input must resolve to an iterable value.');
    }

    /**
     * @return array<string, mixed>
     */
    public function sectionProps(mixed $section): array
    {
        $section = $this->outerValue($section);

        if ($section instanceof Collection) {
            $section = $section->all();
        } elseif ($section instanceof Traversable) {
            $section = iterator_to_array($section);
        } elseif ($section instanceof stdClass) {
            $section = get_object_vars($section);
        }

        if (! is_array($section)) {
            throw new InvalidArgumentException('Section rows must resolve to arrays or stdClass objects.');
        }

        return $section;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public function componentAttributes(array $props): array
    {
        unset($props['type']);

        return collect($props)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [
                $this->componentAttributeName($key) => $value,
            ])
            ->all();
    }

    private function componentAttributeName(string|int $key): string
    {
        $key = preg_replace('/_(\d+)/', '$1', (string) $key) ?? (string) $key;

        return Str::of($key)->replace('_', '-')->kebab()->toString();
    }

    private function augmented(mixed $value): mixed
    {
        while ($this->isStatamicWrapper($value)) {
            $value = statamic_value($value);
        }

        return $value;
    }

    private function outerValue(mixed $value): mixed
    {
        while ($this->isStatamicWrapper($value)) {
            $value = $value instanceof Value
                ? statamic_value($value->shallow())
                : statamic_value($value);
        }

        if ($value instanceof StatamicQueryBuilder && method_exists($value, 'get')) {
            return $value->get();
        }

        return $value;
    }

    private function rowIsFilled(mixed $row): bool
    {
        if ($row instanceof Value) {
            $row = $row->raw();
        }

        if ($row instanceof Values) {
            return $row->getProxiedInstance()
                ->filter(fn (mixed $value): bool => ! $this->valueIsBlank($value))
                ->isNotEmpty();
        }

        if ($row instanceof Collection) {
            return $row
                ->filter(fn (mixed $value): bool => ! $this->valueIsBlank($value))
                ->isNotEmpty();
        }

        if (is_array($row)) {
            return collect($row)
                ->filter(fn (mixed $value): bool => ! $this->valueIsBlank($value))
                ->isNotEmpty();
        }

        return ! $this->valueIsBlank($row);
    }

    private function valueIsBlank(mixed $value): bool
    {
        $value = $this->raw($value);

        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return $value === false;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return mb_trim((string) $value) === '';
        }

        return empty($value);
    }

    private function isStatamicWrapper(mixed $value): bool
    {
        return $value instanceof Value
            || $value instanceof Values
            || $value instanceof ArrayableString
            || $value instanceof FluentTag
            || $value instanceof Modify;
    }

    private function resolveAsset(mixed $asset): mixed
    {
        $asset = $this->normalizeAssetCandidate($asset);

        if (is_string($asset)) {
            return $this->resolveAssetString($asset);
        }

        return $asset;
    }

    private function normalizeAssetCandidate(mixed $value): mixed
    {
        $value = $this->augmented($value);

        if ($value instanceof StatamicQueryBuilder && method_exists($value, 'get')) {
            return $this->normalizeAssetCandidate($value->get());
        }

        if ($value instanceof Values) {
            return $this->normalizeAssetCandidate($value->all());
        }

        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item): mixed => $this->normalizeAssetCandidate($item));
        }

        if ($value instanceof Traversable) {
            return collect(iterator_to_array($value))->map(fn (mixed $item): mixed => $this->normalizeAssetCandidate($item));
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->normalizeAssetCandidate($item))
                ->all();
        }

        if ($value instanceof stdClass) {
            return $this->normalizeAssetCandidate(get_object_vars($value));
        }

        return $value;
    }

    private function resolveAssetString(string $asset): mixed
    {
        $asset = mb_trim($asset);

        if ($asset === '') {
            return null;
        }

        if (Str::contains($asset, '::')) {
            return Asset::find($asset);
        }

        $defaultContainerAsset = Asset::find('assets::'.$asset);

        if ($defaultContainerAsset !== null) {
            return $defaultContainerAsset;
        }

        if (Str::startsWith($asset, ['/', 'http://', 'https://'])) {
            return Asset::find($asset) ?? Asset::findByPath($asset);
        }

        return Asset::findByPath($asset);
    }

    private function resolveEntry(mixed $entry): mixed
    {
        if (is_int($entry) || is_string($entry)) {
            return Entry::find($entry);
        }

        return $entry;
    }

    private function linkUrl(mixed $url): ?string
    {
        $url = $this->nullableRawString($url);

        if ($url === null) {
            return null;
        }

        $url = mb_trim($url);

        if (! Str::startsWith($url, 'entry::')) {
            return $this->allowedHref($url);
        }

        $entry = Entry::find(Str::after($url, 'entry::'));

        if (! is_object($entry) || ! is_callable([$entry, 'url'])) {
            return null;
        }

        $entryUrl = $this->nullableRawString($entry->url());

        return $entryUrl === null
            ? null
            : $this->allowedHref(mb_trim($entryUrl));
    }

    private function allowedHref(string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $href) === 1) {
            return null;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        if ($scheme === false) {
            return null;
        }

        if ($scheme === null) {
            return $href;
        }

        return in_array(mb_strtolower($scheme), self::ALLOWED_HREF_SCHEMES, true)
            ? $href
            : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function linkTarget(array $value): string
    {
        $target = $this->nullableRawString($value['target'] ?? null);

        if ($target !== null) {
            return $target;
        }

        return filter_var($this->raw($value['target_blank'] ?? null), FILTER_VALIDATE_BOOL)
            ? '_blank'
            : '_self';
    }

    private function nullableRawString(mixed $value): ?string
    {
        $value = $this->raw($value);

        if ($value === null || (! is_scalar($value) && ! $value instanceof Stringable)) {
            return null;
        }

        $value = (string) $value;

        return mb_trim($value) === '' ? null : $value;
    }
}
