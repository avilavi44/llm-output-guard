<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

use InvalidArgumentException;

/**
 * The source of truth, looked up by id.
 */
final class SourceBank
{
    /** @var array<string, Source> */
    private array $sources = [];

    /**
     * @param  iterable<Source>  $sources
     */
    public function __construct(iterable $sources)
    {
        foreach ($sources as $source) {
            if (isset($this->sources[$source->id])) {
                throw new InvalidArgumentException("Duplicate source id: {$source->id}");
            }

            $this->sources[$source->id] = $source;
        }
    }

    /**
     * @param  array<string, string>  $texts  Source text keyed by id.
     */
    public static function fromArray(array $texts): self
    {
        $sources = [];

        foreach ($texts as $id => $text) {
            $sources[] = new Source((string) $id, $text);
        }

        return new self($sources);
    }

    public function find(string $id): ?Source
    {
        return $this->sources[$id] ?? null;
    }

    /**
     * The sources that exist among the given ids, in the order given.
     *
     * @param  list<string>  $ids
     * @return list<Source>
     */
    public function findAll(array $ids): array
    {
        return array_values(array_filter(
            array_map($this->find(...), array_values(array_unique($ids))),
        ));
    }
}
