<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * One reason a generated item cannot be trusted.
 */
final readonly class Violation
{
    /**
     * @param  string  $value  The offending number, term or source id. Empty for a missing citation.
     */
    public function __construct(
        public string $itemKey,
        public ViolationKind $kind,
        public string $value = '',
    ) {}

    /**
     * A sentence fit for a log, or for a repair prompt.
     */
    public function message(): string
    {
        return match ($this->kind) {
            ViolationKind::MissingCitation => 'The item cites no source.',
            ViolationKind::UnknownSource => "The cited source \"{$this->value}\" does not exist.",
            ViolationKind::UnsupportedNumber => "The number \"{$this->value}\" is not in the cited sources.",
            ViolationKind::UnsupportedTerm => "The term \"{$this->value}\" is not in the cited sources.",
        };
    }

    /**
     * @return array{item: string, kind: string, value: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'item' => $this->itemKey,
            'kind' => $this->kind->value,
            'value' => $this->value,
            'message' => $this->message(),
        ];
    }
}
