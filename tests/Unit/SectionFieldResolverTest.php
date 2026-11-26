<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as StatamicEntry;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use SuperInteractive\StatamicSuperSections\Support\SectionFieldResolver;
use SuperInteractive\StatamicSuperSections\Tests\TestCase;

uses(TestCase::class);

function fakeAugmentedSectionValue(mixed $raw, mixed $augmented): Value
{
    $fieldtype = new readonly class($augmented)
    {
        public function __construct(private mixed $augmented) {}

        public function field(): null
        {
            return null;
        }

        public function augment(mixed $value): mixed
        {
            return $this->augmented;
        }

        public function shallowAugment(mixed $value): mixed
        {
            return $this->augment($value);
        }

        public function config(string $key): mixed
        {
            return null;
        }

        public function shouldParseAntlersFromRawString(): bool
        {
            return false;
        }
    };

    return new Value($raw, null, $fieldtype);
}

function fakeShallowSectionValue(mixed $raw, mixed $augmented): Value
{
    $fieldtype = new readonly class($augmented)
    {
        public function __construct(private mixed $augmented) {}

        public function field(): null
        {
            return null;
        }

        public function augment(mixed $value): mixed
        {
            return $this->augmented;
        }

        public function shallowAugment(mixed $value): mixed
        {
            return $value;
        }

        public function config(string $key): mixed
        {
            return null;
        }

        public function shouldParseAntlersFromRawString(): bool
        {
            return false;
        }
    };

    return new Value($raw, null, $fieldtype);
}

it('resolves primitive defaults safely', function (): void {
    $resolver = app(SectionFieldResolver::class);

    expect($resolver->string(null))->toBe('')
        ->and($resolver->nullableString(null))->toBeNull()
        ->and($resolver->bool(null))->toBeFalse()
        ->and($resolver->bool('false'))->toBeFalse()
        ->and($resolver->collection(null))->toBeInstanceOf(Collection::class)->toHaveCount(0)
        ->and($resolver->collection([null, '', 'filled'])->all())->toBe(['filled'])
        ->and($resolver->entries(null))->toBeInstanceOf(Collection::class)->toHaveCount(0)
        ->and($resolver->singleAsset(null))->toBeNull()
        ->and($resolver->assets(null))->toBeInstanceOf(Collection::class)->toHaveCount(0)
        ->and($resolver->formHandle(null))->toBeNull()
        ->and($resolver->link(null))->toBe(['url' => null, 'text' => null, 'target' => '_self']);
});

it('collects repeatable rows shallowly and preserves direct native row fields', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $icon = fakeAugmentedSectionValue('pets', '<svg>pets</svg>');
    $row = new Values([
        'icon' => $icon,
        'title' => new Value('Native block title'),
        'text' => fakeAugmentedSectionValue('Native block text', '<p>Native block text</p>'),
        'link' => new Value([
            'url' => '/native-block',
            'text' => 'Native block link',
            'target' => '_blank',
        ]),
    ]);

    $collection = $resolver->collection(new Value([
        $row,
        new Values([]),
        null,
        '',
    ]));

    expect($collection)->toHaveCount(1)
        ->and($collection->first())->toBe($row)
        ->and($resolver->field($row, 'icon'))->toBe($icon)
        ->and($resolver->raw($resolver->field($row, 'icon')))->toBe('pets')
        ->and($resolver->string($resolver->field($row, 'icon')))->toBe('pets')
        ->and($resolver->html($resolver->field($row, 'icon')))->toBe('<svg>pets</svg>')
        ->and($resolver->html($resolver->field($row, 'text')))->toBe('<p>Native block text</p>')
        ->and($resolver->link($resolver->field($row, 'link')))->toBe([
            'url' => '/native-block',
            'text' => 'Native block link',
            'target' => '_blank',
        ]);
});

it('uses shallow outer unwrapping so repeatable fields do not recursively augment rows', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $row = new Values([
        'icon' => fakeAugmentedSectionValue('pets', '<svg>pets</svg>'),
    ]);
    $sections = new Values([
        new Values([
            'type' => new Value('content_blocks_with_icon'),
            'blocks' => fakeShallowSectionValue([$row], [
                [
                    'icon' => '<svg>pets</svg>',
                ],
            ]),
        ]),
    ]);

    $collection = $resolver->collection(fakeShallowSectionValue([$row], [
        [
            'icon' => '<svg>pets</svg>',
        ],
    ]));
    $items = $resolver->sectionItems(fakeShallowSectionValue($sections, [
        [
            'type' => 'content_blocks_with_icon',
            'blocks' => [
                [
                    'icon' => '<svg>pets</svg>',
                ],
            ],
        ],
    ]));
    $props = $resolver->sectionProps($items[0]);
    $blocks = $resolver->collection($props['blocks']);

    expect($collection->first())->toBe($row)
        ->and($props['type'])->toBeInstanceOf(Value::class)
        ->and($blocks->first())->toBe($row)
        ->and($resolver->raw($resolver->field($blocks->first(), 'icon')))->toBe('pets');
});

it('reads direct row fields from arrays values collections and public objects without materializing them', function (): void {
    $resolver = app(SectionFieldResolver::class);
    $nativeTitle = new Value('Direct title');

    $arrayRow = ['title' => $nativeTitle];
    $valuesRow = new Values(['title' => $nativeTitle]);
    $collectionRow = collect(['title' => $nativeTitle]);
    $objectRow = new class($nativeTitle)
    {
        public function __construct(public mixed $title) {}
    };

    expect($resolver->field($arrayRow, 'title'))->toBe($nativeTitle)
        ->and($resolver->field($valuesRow, 'title'))->toBe($nativeTitle)
        ->and($resolver->field($collectionRow, 'title'))->toBe($nativeTitle)
        ->and($resolver->field($objectRow, 'title'))->toBe($nativeTitle)
        ->and($resolver->field($arrayRow, 'missing', 'fallback'))->toBe('fallback')
        ->and($resolver->fields($valuesRow, ['title', 'missing']))->toBe([
            'title' => $nativeTitle,
            'missing' => null,
        ]);
});

it('materializes statamic query builders for entries', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $entry = new class
    {
        public string $title = 'Query Entry';
    };

    $query = new readonly class($entry) implements StatamicQueryBuilder
    {
        public function __construct(private object $entry) {}

        public function get(array $columns = ['*']): Collection
        {
            return collect([$this->entry]);
        }
    };

    $entries = $resolver->entries(new Value($query));

    expect($entries)->toBeInstanceOf(Collection::class)
        ->and($entries->first())->toBe($entry);
});

it('resolves scalar entry ids for entry fields', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $entry = Mockery::mock(StatamicEntry::class);

    Entry::shouldReceive('find')
        ->once()
        ->with(123)
        ->andReturn($entry);

    Entry::shouldReceive('find')
        ->once()
        ->with('missing')
        ->andReturnNull();

    $entries = $resolver->entries(new Value([123, 'missing']));

    expect($entries)->toHaveCount(1)
        ->and($entries->first())->toBe($entry);
});

it('materializes terms as collections and dates as carbon instances', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $term = new class
    {
        public string $title = 'Care';
    };
    $query = new readonly class($term) implements StatamicQueryBuilder
    {
        public function __construct(private object $term) {}

        public function get(array $columns = ['*']): Collection
        {
            return collect([$this->term]);
        }
    };

    expect($resolver->terms(new Value($query))->first())->toBe($term)
        ->and($resolver->date(new Value('2026-06-02')))->toBeInstanceOf(CarbonInterface::class)
        ->and($resolver->date(new DateTimeImmutable('2026-06-02')))->toBeInstanceOf(CarbonInterface::class)
        ->and($resolver->date(null))->toBeNull();
});

it('does not materialize query builders when resolving primitive strings', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $query = new class implements StatamicQueryBuilder
    {
        public function get(array $columns = ['*']): Collection
        {
            throw new RuntimeException('Query builders should not be materialized for primitive strings.');
        }
    };

    expect($resolver->string(new Value($query)))->toBe('')
        ->and($resolver->nullableString(new Value($query)))->toBeNull();
});

it('returns the first native asset for single asset fields and a collection for multi asset fields', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $firstAsset = new class
    {
        public function url(): string
        {
            return '/assets/first.jpg';
        }
    };

    $secondAsset = new class
    {
        public function url(): string
        {
            return '/assets/second.jpg';
        }
    };

    $assets = new Value(collect([$firstAsset, $secondAsset]));

    expect($resolver->singleAsset($assets))->toBe($firstAsset)
        ->and($resolver->assets($assets))->toHaveCount(2)
        ->and($resolver->assets('missing-container::missing.jpg'))->toHaveCount(0)
        ->and($resolver->singleAsset('missing-container::missing.jpg'))->toBeNull();
});

it('resolves bare asset filenames from the default assets container', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $asset = new class {};

    Asset::shouldReceive('find')
        ->once()
        ->with('assets::avatar.jpg')
        ->andReturn($asset);

    expect($resolver->singleAsset('avatar.jpg'))->toBe($asset);
});

it('does not scan every asset when an asset path or url does not resolve', function (): void {
    $resolver = app(SectionFieldResolver::class);

    Asset::shouldReceive('find')->once()->with('assets::artikelen/missing.jpg')->andReturnNull();
    Asset::shouldReceive('find')->once()->with('assets::/assets/missing.jpg')->andReturnNull();
    Asset::shouldReceive('find')->once()->with('/assets/missing.jpg')->andReturnNull();
    Asset::shouldNotReceive('findByPath', 'all');

    expect($resolver->singleAsset('artikelen/missing.jpg'))->toBeNull()
        ->and($resolver->assets(['/assets/missing.jpg']))->toHaveCount(0);
});

it('resolves root-relative paths from the default assets container before trying them as urls', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $asset = new class {};

    Asset::shouldReceive('find')->once()->with('assets::/artikelen/photo.jpg')->andReturn($asset);

    expect($resolver->singleAsset('/artikelen/photo.jpg'))->toBe($asset);
});

it('resolves form objects and handles to nullable handle strings', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $form = new class
    {
        public function handle(): string
        {
            return 'contact';
        }
    };

    expect($resolver->formHandle(new Value($form)))->toBe('contact')
        ->and($resolver->formHandle('newsletter'))->toBe('newsletter')
        ->and($resolver->formHandle(''))->toBeNull();
});

it('keeps super link fields as array shapes', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('url')
        ->once()
        ->andReturn('/hondenrassen');

    Entry::shouldReceive('find')
        ->once()
        ->with('51')
        ->andReturn($entry);

    expect($resolver->link(new Value([
        'url' => '/target',
        'text' => 'Target',
        'target' => '',
    ])))->toBe([
        'url' => '/target',
        'text' => 'Target',
        'target' => '_self',
    ])
        ->and($resolver->link(['url' => '/missing-target', 'text' => 'Missing target']))->toBe([
            'url' => '/missing-target',
            'text' => 'Missing target',
            'target' => '_self',
        ])
        ->and($resolver->link([
            'url' => 'entry::51',
            'text' => 'Hondenrassen',
            'target_blank' => true,
        ]))->toBe([
            'url' => '/hondenrassen',
            'text' => 'Hondenrassen',
            'target' => '_blank',
        ]);
});

it('preserves string zero values in nullable link fields', function (): void {
    $resolver = app(SectionFieldResolver::class);

    expect($resolver->link([
        'url' => '0',
        'text' => '0',
    ]))->toBe([
        'url' => '0',
        'text' => '0',
        'target' => '_self',
    ]);
});

it('allows common editorial link hrefs', function (string $input, string $expected): void {
    $resolver = app(SectionFieldResolver::class);

    expect($resolver->link([
        'url' => $input,
        'text' => 'Label',
    ])['url'])->toBe($expected);
})->with([
    'relative path' => ['/dogs', '/dogs'],
    'trimmed relative path' => ['  /dogs  ', '/dogs'],
    'anchor' => ['#breeds', '#breeds'],
    'http' => ['http://example.com', 'http://example.com'],
    'https' => ['https://example.com', 'https://example.com'],
    'mailto' => ['mailto:hello@example.com', 'mailto:hello@example.com'],
    'tel' => ['tel:+31612345678', 'tel:+31612345678'],
]);

it('rejects hrefs outside the addon allowlist', function (string $input): void {
    $resolver = app(SectionFieldResolver::class);

    expect($resolver->link([
        'url' => $input,
        'text' => 'Label',
    ])['url'])->toBeNull();
})->with([
    'blank' => [''],
    'whitespace' => ['   '],
    'protocol relative' => ['//example.com'],
    'javascript scheme' => ['javascript:alert(1)'],
    'data scheme' => ['data:text/html,<script>alert(1)</script>'],
    'sms scheme' => ['sms:+31612345678'],
    'control character' => ["https://example.com/\npath"],
]);

it('prepares section items, props, and component attributes for dispatchers', function (): void {
    $resolver = app(SectionFieldResolver::class);

    $sections = new Value(new Values([
        new Values([
            'type' => new Value('text_image_large_buttons'),
            'button_text' => new Value('Read more'),
            'buttons' => new Value([
                new Values([
                    'link' => new Value([
                        'url' => '/button',
                        'text' => 'Button',
                        'target' => '',
                    ]),
                ]),
            ]),
        ]),
    ]));

    $items = $resolver->sectionItems($sections);
    $props = $resolver->sectionProps($items[0]);

    expect($items)->toHaveCount(1)
        ->and($props['type'])->toBeInstanceOf(Value::class)
        ->and($resolver->string($props['type']))->toBe('text_image_large_buttons')
        ->and($props['button_text'])->toBeInstanceOf(Value::class)
        ->and($props['buttons'])->toBeInstanceOf(Value::class)
        ->and($resolver->componentAttributes($props))->toHaveKey('button-text')
        ->and($resolver->componentAttributes($props))->not->toHaveKey('type');
});

it('maps numeric field handles to blade-compatible component attributes', function (): void {
    $resolver = app(SectionFieldResolver::class);

    expect($resolver->componentAttributes([
        'type' => 'text',
        'button_text' => 'Read more',
        'column_1' => 'First column',
        'image_2' => 'Second image',
        'hero_image_2' => 'Hero image',
    ]))->toBe([
        'button-text' => 'Read more',
        'column1' => 'First column',
        'image2' => 'Second image',
        'hero-image2' => 'Hero image',
    ]);
});

it('keeps zero values as filled collection rows', function (): void {
    $resolver = app(SectionFieldResolver::class);
    $wrappedZero = new Value('0');

    $rows = $resolver->collection([
        null,
        '',
        '   ',
        false,
        '0',
        0,
        $wrappedZero,
        ['title' => '0'],
        ['title' => '   '],
    ]);

    expect($rows)->toHaveCount(4)
        ->and($rows->all())->toBe([
            '0',
            0,
            $wrappedZero,
            ['title' => '0'],
        ]);
});
