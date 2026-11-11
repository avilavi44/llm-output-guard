<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * What became of one item.
 */
final readonly class ItemOutcome
{
    /**
     * @param  string|null  $text  The text that is safe to use. Null when the item was dropped.
     * @param  list<Violation>  $violations  The problems with the last rejected draft, if any.
     */
    public function __construct(
        public string $key,
        public ItemStatus $status,
        public ?string $text,
        public array $violations = [],
    ) {}

    /**
     * @return array{key: string, status: string, text: string|null, violations: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status->value,
            'text' => $this->text,
            'violations' => array_map(fn (Violation $v) => $v->toArray(), $this->violations),
        ];
    }
}
