<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * One piece of the source of truth: text a generated item may cite by id.
 */
final readonly class Source
{
    public function __construct(
        public string $id,
        public string $text,
    ) {}
}
