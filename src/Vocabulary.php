<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * Everything a body of text can vouch for: its numbers and its words.
 *
 * Numbers and words are kept apart on purpose, so "HTML5" never vouches for the number 5.
 */
final readonly class Vocabulary
{
    /**
     * @param  array<string, true>  $numbers
     * @param  array<string, true>  $words
     */
    public function __construct(
        private array $numbers,
        private array $words,
    ) {}

    public function merge(self $other): self
    {
        return new self($this->numbers + $other->numbers, $this->words + $other->words);
    }

    public function supports(Claim $claim): bool
    {
        if ($claim->isNumber) {
            return isset($this->numbers[$claim->normalized]);
        }

        if (isset($this->words[$claim->normalized])) {
            return true;
        }

        // "Stripe/PayPal" is supported when each half is.
        $parts = TermExtractor::parts($claim->normalized);

        return count($parts) > 1
            && array_filter($parts, fn (string $part) => ! isset($this->words[$part])) === [];
    }
}
