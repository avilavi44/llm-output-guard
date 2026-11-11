<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * A number or a named term found in generated text. Each one needs support from a source.
 */
final readonly class Claim
{
    /**
     * @param  string  $text  As written, for messages.
     * @param  string  $normalized  The form used to compare ("1,200" becomes "1200").
     */
    public function __construct(
        public bool $isNumber,
        public string $text,
        public string $normalized,
    ) {}
}
