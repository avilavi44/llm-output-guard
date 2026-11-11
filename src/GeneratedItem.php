<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * One unit of model output: its text and the ids of the sources it claims to be based on.
 */
final readonly class GeneratedItem
{
    /**
     * @param  string  $key  Identifies the item across the repair round.
     * @param  list<string>  $citations  Source ids.
     */
    public function __construct(
        public string $key,
        public string $text,
        public array $citations,
    ) {}
}
