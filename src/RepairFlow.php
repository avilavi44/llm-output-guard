<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

use InvalidArgumentException;
use Throwable;

/**
 * Validate, one repair round, then revert whatever is still wrong.
 *
 * The flow always ends with text that is safe to use. A model that keeps inventing facts,
 * or a repair call that fails outright, costs quality but can never ship a bad claim.
 */
final class RepairFlow
{
    public function __construct(
        private readonly Guard $guard,
        private readonly SourceBank $sources,
    ) {}

    /**
     * @param  list<GeneratedItem>  $items
     * @param  (callable(list<Failure>): iterable<GeneratedItem>)|null  $repair  Called at most once, with the failing items only.
     */
    public function run(array $items, ?callable $repair = null): FlowResult
    {
        $outcomes = [];
        $failures = [];

        foreach ($items as $item) {
            if (isset($outcomes[$item->key])) {
                throw new InvalidArgumentException("Duplicate item key: {$item->key}");
            }

            $violations = $this->guard->check($item);
            $outcomes[$item->key] = new ItemOutcome($item->key, ItemStatus::Passed, $item->text);

            if ($violations !== []) {
                $failures[$item->key] = new Failure($item, $violations);
            }
        }

        if ($failures === []) {
            return new FlowResult($outcomes);
        }

        [$repaired, $error] = $this->repair($failures, $repair);

        foreach ($failures as $key => $failure) {
            $outcomes[$key] = $this->settle($failure, $repaired[$key] ?? null);
        }

        return new FlowResult($outcomes, $error);
    }

    /**
     * @param  array<string, Failure>  $failures
     * @return array{0: array<string, GeneratedItem>, 1: Throwable|null}
     */
    private function repair(array $failures, ?callable $repair): array
    {
        if ($repair === null) {
            return [[], null];
        }

        $repaired = [];

        try {
            foreach ($repair(array_values($failures)) as $item) {
                // Only items that failed may change: a repair cannot rewrite a passing one.
                if ($item instanceof GeneratedItem && isset($failures[$item->key])) {
                    $repaired[$item->key] = $item;
                }
            }
        } catch (Throwable $e) {
            return [[], $e];
        }

        return [$repaired, null];
    }

    private function settle(Failure $failure, ?GeneratedItem $repaired): ItemOutcome
    {
        $key = $failure->item->key;

        if ($repaired === null) {
            return $this->revert($failure->item, $failure->item, $failure->violations);
        }

        $violations = $this->guard->check($repaired);

        return $violations === []
            ? new ItemOutcome($key, ItemStatus::Repaired, $repaired->text, $failure->violations)
            : $this->revert($repaired, $failure->item, $violations);
    }

    /**
     * Falls back on the last draft's real citations, then the first draft's.
     *
     * @param  list<Violation>  $violations
     */
    private function revert(GeneratedItem $last, GeneratedItem $first, array $violations): ItemOutcome
    {
        $sources = $this->sources->findAll($last->citations) ?: $this->sources->findAll($first->citations);

        if ($sources === []) {
            return new ItemOutcome($last->key, ItemStatus::Dropped, null, $violations);
        }

        $text = implode(' ', array_map(fn (Source $source) => $source->text, $sources));

        return new ItemOutcome($last->key, ItemStatus::Reverted, $text, $violations);
    }
}
