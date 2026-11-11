# llm-output-guard

A small PHP 8.2 library that checks text written by a language model against a source of truth. It has no runtime dependencies apart from `ext-mbstring`.

## The problem

Ask a model to reword facts you gave it, and now and then it will change a number or add a product you never mentioned. In a CV, a report or a product page, that is worse than a clumsy sentence. Asking the model to be careful does not prevent it, so the check has to happen in code, after the model has answered.

## How it works

Each generated item says which sources it is based on, by id. The guard pulls out every number and every named term in the item and requires each one to appear in those cited sources, or in an allowlist. An item is checked only against what it cites, so a true fact pinned to the wrong source still fails.

A named term is a word that looks like a proper noun or a product: capitalised mid-sentence (`Kotlin`), with an inner capital (`GraphQL`), a digit (`OAuth2`), or a `.`, `+` or `#` inside (`Node.js`, `C++`).

`RepairFlow` runs the full process:

1. Validate every item.
2. Give the failing items and their violations to your repair callable, once.
3. Validate the repairs. Any item still failing reverts to the text of its cited sources.
4. An item with no real source to revert to is dropped.

The text that comes out is always safe to use. If the repair call throws, the failing items revert and the exception is kept on the result.

## Usage

```bash
composer require avilavi/llm-output-guard
```

Check a single item:

```php
use AviLavi\LlmOutputGuard\{Failure, GeneratedItem, Guard, RepairFlow, SourceBank};

$bank = SourceBank::fromArray([
    'launch' => 'Launched the Northwind store on Shopify in 2023, handling 12,000 orders a month.',
    'speed' => 'Cut checkout time by 35% using Redis.',
]);

$guard = new Guard($bank, allowlist: ['API']);

$item = new GeneratedItem('speed', 'Cut checkout time by 50% using Redis and Kafka.', ['speed']);

foreach ($guard->check($item) as $violation) {
    echo $violation->message(), "\n";
}

// The number "50" is not in the cited sources.
// The term "Kafka" is not in the cited sources.
```

Run the whole flow. The library does not talk to any model; you bring that part:

```php
$result = (new RepairFlow($guard, $bank))->run($items, function (array $failures) use ($model) {
    return array_map(
        fn (Failure $failure) => new GeneratedItem(
            $failure->item->key,
            $model->rewrite($failure->item->text, $failure->feedback()),
            $failure->item->citations,
        ),
        $failures,
    );
});

$result->texts();                        // ['launch' => '...', 'speed' => '...']
$result->outcomes['speed']->status;      // ItemStatus::Passed, Repaired, Reverted or Dropped
$result->outcomes['speed']->violations;  // list<Violation>
$result->toArray();                      // for logs or an API response
```

## What it does not catch

This is a lexical check, and it is meant to be one layer rather than the only one.

- It checks that a fact appears in the source, not that the sentence means the same thing. "Cut errors by 35%" passes against a source that says "grew errors by 35%".
- A plain capitalised word at the start of a sentence is ignored, because its capital may be there only by position. `Kotlin was used` is not flagged, while `used Kotlin` is.
- Number words are matched up to "twenty", then the tens, "hundred", "thousand", "million" and "billion". "One" is ignored because it is so often a pronoun.
- It is written for Latin-script English. Other languages need their own rules for what counts as a name.

## Tests

```bash
composer install
composer test
```

## License

MIT
