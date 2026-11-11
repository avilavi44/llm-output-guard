<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard\Tests;

use AviLavi\LlmOutputGuard\GeneratedItem;
use AviLavi\LlmOutputGuard\Guard;
use AviLavi\LlmOutputGuard\Source;
use AviLavi\LlmOutputGuard\SourceBank;
use AviLavi\LlmOutputGuard\Violation;
use AviLavi\LlmOutputGuard\ViolationKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GuardTest extends TestCase
{
    private SourceBank $bank;

    protected function setUp(): void
    {
        $this->bank = SourceBank::fromArray([
            'migration' => 'Moved 40 storefronts for Northwind Traders to GraphQL in 2023.',
            'speed' => 'Cut page load time by 35% using Redis and HTML5 caching.',
            'integrations' => 'Connected REST/webhooks between the shop and the CRM.',
        ]);
    }

    /**
     * @param  list<string>  $citations
     * @param  list<string>  $allowlist
     * @return list<Violation>
     */
    private function check(string $text, array $citations, array $allowlist = []): array
    {
        return (new Guard($this->bank, $allowlist))->check(new GeneratedItem('item', $text, $citations));
    }

    /**
     * @param  list<Violation>  $violations
     * @return list<array{0: ViolationKind, 1: string}>
     */
    private function summary(array $violations): array
    {
        return array_map(fn (Violation $v) => [$v->kind, $v->value], $violations);
    }

    public function test_a_faithful_rewording_passes(): void
    {
        $this->assertSame([], $this->check(
            'Migrated 40 storefronts to GraphQL for Northwind Traders.',
            ['migration'],
        ));
    }

    public function test_an_invented_number_is_a_violation(): void
    {
        $violations = $this->check('Moved 400 storefronts to GraphQL.', ['migration']);

        $this->assertSame([[ViolationKind::UnsupportedNumber, '400']], $this->summary($violations));
    }

    public function test_an_invented_term_is_a_violation(): void
    {
        $violations = $this->check('Moved the storefronts to GraphQL and Kubernetes.', ['migration']);

        $this->assertSame([[ViolationKind::UnsupportedTerm, 'Kubernetes']], $this->summary($violations));
    }

    public function test_every_violation_is_reported(): void
    {
        $violations = $this->check('Moved 99 sites to Kubernetes for Contoso.', ['migration']);

        $this->assertSame([
            [ViolationKind::UnsupportedNumber, '99'],
            [ViolationKind::UnsupportedTerm, 'Kubernetes'],
            [ViolationKind::UnsupportedTerm, 'Contoso'],
        ], $this->summary($violations));
    }

    public function test_only_the_cited_sources_count(): void
    {
        // Redis is in the bank, but not in the source this item cites.
        $violations = $this->check('Moved the storefronts to Redis.', ['migration']);

        $this->assertSame([[ViolationKind::UnsupportedTerm, 'Redis']], $this->summary($violations));
    }

    public function test_several_citations_pool_their_facts(): void
    {
        $this->assertSame([], $this->check('Used GraphQL and Redis.', ['migration', 'speed']));
    }

    public function test_a_term_never_vouches_for_a_number_inside_it(): void
    {
        $violations = $this->check('Worked for 5 years.', ['speed']);

        $this->assertSame([[ViolationKind::UnsupportedNumber, '5']], $this->summary($violations));
    }

    public function test_matching_ignores_case_and_number_format(): void
    {
        $bank = SourceBank::fromArray(['s' => 'Handled 1200 orders with graphql.']);
        $item = new GeneratedItem('item', 'handled 1,200 orders with GraphQL', ['s']);

        $this->assertSame([], (new Guard($bank))->check($item));
    }

    public function test_a_number_word_matches_digits_in_the_source(): void
    {
        $this->assertSame([], $this->check('Moved forty storefronts.', ['migration']));
    }

    public function test_a_compound_in_the_source_vouches_for_its_parts(): void
    {
        $this->assertSame([], $this->check('Built on REST APIs and webhooks.', ['integrations'], ['APIs']));
    }

    public function test_a_compound_in_the_text_needs_every_part(): void
    {
        $violations = $this->check('Compared Redis/Memcached here.', ['speed']);

        $this->assertSame([[ViolationKind::UnsupportedTerm, 'Redis/Memcached']], $this->summary($violations));
    }

    public function test_the_allowlist_covers_terms_and_numbers(): void
    {
        $this->assertSame([], $this->check(
            'Moved 24 storefronts for Acme.',
            ['migration'],
            ['Acme', '24'],
        ));
    }

    public function test_an_item_must_cite_something(): void
    {
        $violations = $this->check('A plain sentence.', []);

        $this->assertSame([[ViolationKind::MissingCitation, '']], $this->summary($violations));
    }

    public function test_an_unknown_citation_is_a_violation(): void
    {
        $violations = $this->check('Moved 40 storefronts.', ['migration', 'made-up']);

        $this->assertSame([[ViolationKind::UnknownSource, 'made-up']], $this->summary($violations));
    }

    public function test_violations_serialise_for_logs_and_apis(): void
    {
        $violations = $this->check('Moved 400 storefronts.', ['migration']);

        $this->assertSame([
            'item' => 'item',
            'kind' => 'unsupported_number',
            'value' => '400',
            'message' => 'The number "400" is not in the cited sources.',
        ], $violations[0]->toArray());
    }

    public function test_the_bank_refuses_duplicate_ids(): void
    {
        $source = new Source('a', 'text');

        $this->expectException(InvalidArgumentException::class);

        new SourceBank([$source, $source]);
    }
}
