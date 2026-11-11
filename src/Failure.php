<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * An item that failed the first check, as handed to the repair callable.
 */
final readonly class Failure
{
    /**
     * @param  list<Violation>  $violations
     */
    public function __construct(
        public GeneratedItem $item,
        public array $violations,
    ) {}

    /**
     * The violations as one block of text, ready to put in a repair prompt.
     */
    public function feedback(): string
    {
        return implode("\n", array_map(fn (Violation $v) => $v->message(), $this->violations));
    }
}
