<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\View\Components;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use SuperInteractive\StatamicSuperSections\Support\SectionFieldResolver;

abstract class SectionComponent extends Component
{
    private ?SectionFieldResolver $sectionFieldResolver = null;

    protected function string(mixed $value): string
    {
        return $this->fieldResolver()->string($value);
    }

    protected function nullableString(mixed $value): ?string
    {
        return $this->fieldResolver()->nullableString($value);
    }

    protected function bool(mixed $value): bool
    {
        return $this->fieldResolver()->bool($value);
    }

    protected function html(mixed $value): string
    {
        return $this->fieldResolver()->html($value);
    }

    protected function collection(mixed $value): Collection
    {
        return $this->fieldResolver()->collection($value);
    }

    protected function entries(mixed $value): Collection
    {
        return $this->fieldResolver()->entries($value);
    }

    protected function terms(mixed $value): Collection
    {
        return $this->fieldResolver()->terms($value);
    }

    protected function singleAsset(mixed $value): mixed
    {
        return $this->fieldResolver()->singleAsset($value);
    }

    protected function assets(mixed $value): Collection
    {
        return $this->fieldResolver()->assets($value);
    }

    protected function formHandle(mixed $value): ?string
    {
        return $this->fieldResolver()->formHandle($value);
    }

    protected function date(mixed $value): ?CarbonInterface
    {
        return $this->fieldResolver()->date($value);
    }

    /**
     * @return array{url: ?string, text: ?string, target: string}
     */
    protected function link(mixed $value): array
    {
        return $this->fieldResolver()->link($value);
    }

    protected function raw(mixed $value): mixed
    {
        return $this->fieldResolver()->raw($value);
    }

    protected function field(mixed $row, string $handle, mixed $default = null): mixed
    {
        return $this->fieldResolver()->field($row, $handle, $default);
    }

    /**
     * @param  array<int, string>  $handles
     * @return array<string, mixed>
     */
    protected function fields(mixed $row, array $handles): array
    {
        return $this->fieldResolver()->fields($row, $handles);
    }

    private function fieldResolver(): SectionFieldResolver
    {
        return $this->sectionFieldResolver ??= app(SectionFieldResolver::class);
    }
}
