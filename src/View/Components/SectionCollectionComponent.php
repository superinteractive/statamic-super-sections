<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;
use SuperInteractive\StatamicSuperSections\Exceptions\MissingSectionClassComponentException;
use SuperInteractive\StatamicSuperSections\Support\SectionFieldResolver;

abstract class SectionCollectionComponent extends Component
{
    /**
     * @var array<int, Htmlable>
     */
    public array $renderedSections;

    public function __construct(SectionFieldResolver $fields, mixed $sections = [])
    {
        $this->renderedSections = $this->renderSectionComponents(
            fields: $fields,
            sections: $sections,
        );
    }

    abstract protected static function group(): string;

    abstract protected static function componentPrefix(): string;

    protected static function sectionClassNamespace(): string
    {
        return static::class;
    }

    /**
     * @return array<int, Htmlable>
     */
    private function renderSectionComponents(SectionFieldResolver $fields, mixed $sections): array
    {
        return collect($fields->sectionItems($sections))
            ->map(fn (mixed $section): Htmlable => $this->renderSectionComponent(
                fields: $fields,
                section: $section,
            ))
            ->all();
    }

    private function renderSectionComponent(SectionFieldResolver $fields, mixed $section): Htmlable
    {
        $props = $fields->sectionProps($section);
        $type = $fields->string($props['type'] ?? null);
        $group = static::group();

        if ($type === '') {
            throw new InvalidArgumentException(sprintf(
                'Section rows for [%s] must contain a stringable [type] value.',
                $group,
            ));
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $type)) {
            throw new InvalidArgumentException(sprintf(
                'Section rows for [%s] must contain a valid [type] handle.',
                $group,
            ));
        }

        $handle = Str::of($type)->replace('_', ' ')->kebab()->toString();
        $component = static::componentPrefix().'.'.$handle;
        $class = static::sectionClassNamespace().'\\'.Str::of($handle)->replace('-', ' ')->studly()->toString();

        if (! class_exists($class) || ! is_subclass_of($class, SectionComponent::class)) {
            throw MissingSectionClassComponentException::for($group, $type, $component, $class);
        }

        return new HtmlString(Blade::render(
            '<x-dynamic-component :component="$component" {{ $attributes }} />',
            [
                'component' => $component,
                'attributes' => new ComponentAttributeBag($fields->componentAttributes($props)),
            ],
        ));
    }
}
