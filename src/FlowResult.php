<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

use Throwable;

/**
 * The result of a full validate, repair and revert run.
 */
final readonly class FlowResult
{
    /**
     * @param  array<string, ItemOutcome>  $outcomes  Keyed by item key, in input order.
     * @param  Throwable|null  $repairError  What the repair callable threw, if it did.
     */
    public function __construct(
        public array $outcomes,
        public ?Throwable $repairError = null,
    ) {}

    /**
     * The safe text of every item that has one, keyed by item key.
     *
     * @return array<string, string>
     */
    public function texts(): array
    {
        $texts = [];

        foreach ($this->outcomes as $key => $outcome) {
            if ($outcome->text !== null) {
                $texts[$key] = $outcome->text;
            }
        }

        return $texts;
    }

    /**
     * True when every item passed the first check, with no repair or revert.
     */
    public function isClean(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->status !== ItemStatus::Passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_values(array_map(fn (ItemOutcome $o) => $o->toArray(), $this->outcomes));
    }
}
