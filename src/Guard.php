<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * Checks one generated item against the sources it cites.
 *
 * An item is only checked against what it cites, not against the whole bank: a true fact
 * pinned to the wrong source is still a violation.
 */
final class Guard
{
    private readonly Vocabulary $allowed;

    /**
     * @param  list<string>  $allowlist  Terms and numbers that are fine anywhere.
     */
    public function __construct(
        private readonly SourceBank $sources,
        array $allowlist = [],
        private readonly TermExtractor $extractor = new TermExtractor,
    ) {
        $this->allowed = $this->extractor->vocabulary(implode("\n", $allowlist));
    }

    /**
     * @return list<Violation> Empty when the item is clean.
     */
    public function check(GeneratedItem $item): array
    {
        if ($item->citations === []) {
            return [new Violation($item->key, ViolationKind::MissingCitation)];
        }

        $violations = [];
        $known = $this->allowed;

        foreach (array_unique($item->citations) as $id) {
            $source = $this->sources->find($id);

            if ($source === null) {
                $violations[] = new Violation($item->key, ViolationKind::UnknownSource, $id);

                continue;
            }

            $known = $known->merge($this->extractor->vocabulary($source->text));
        }

        foreach ($this->extractor->claims($item->text) as $claim) {
            if (! $known->supports($claim)) {
                $kind = $claim->isNumber ? ViolationKind::UnsupportedNumber : ViolationKind::UnsupportedTerm;
                $violations[] = new Violation($item->key, $kind, $claim->text);
            }
        }

        return $violations;
    }
}
