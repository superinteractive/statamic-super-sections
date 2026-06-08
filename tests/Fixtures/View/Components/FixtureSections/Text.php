<?php

declare(strict_types=1);

namespace SuperInteractive\StatamicSuperSections\Tests\Fixtures\View\Components\FixtureSections;

use SuperInteractive\StatamicSuperSections\View\Components\SectionComponent;

final class Text extends SectionComponent
{
    public string $content;

    public function __construct(mixed $text = null)
    {
        $this->content = $this->html($text);
    }

    public function render(): string
    {
        return '<section data-fixture-section="text">{!! $content !!}</section>';
    }
}
