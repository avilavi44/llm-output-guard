<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard\Tests;

use AviLavi\LlmOutputGuard\Claim;
use AviLavi\LlmOutputGuard\TermExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TermExtractorTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function terms(string $text): array
    {
        $claims = array_filter((new TermExtractor)->claims($text), fn (Claim $c) => ! $c->isNumber);

        return array_values(array_map(fn (Claim $c) => $c->text, $claims));
    }

    /**
     * @return list<string>
     */
    private function numbers(string $text): array
    {
        $claims = array_filter((new TermExtractor)->claims($text), fn (Claim $c) => $c->isNumber);

        return array_values(array_map(fn (Claim $c) => $c->normalized, $claims));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function namedTerms(): array
    {
        return [
            'inner capital' => ['we shipped GraphQL support', ['GraphQL']],
            'acronym at sentence start' => ['AWS hosted it', ['AWS']],
            'capitalised mid-sentence' => ['built in Kotlin last', ['Kotlin']],
            'dot inside' => ['written in Node.js here', ['Node.js']],
            'plus and hash' => ['uses C++ and C# daily', ['C++', 'C#']],
            'digit inside' => ['moved to OAuth2 flows', ['OAuth2']],
            'abbreviated money' => ['saved 50k a year', ['50k']],
            'possessive is stripped' => ['improved Northwind\'s site', ['Northwind']],
            'plain words are ignored' => ['built a fast internal tool', []],
            'sentence start is ignored' => ['Migrated the site. Rebuilt the theme.', []],
            'start of a new line is ignored' => ["first line\nSecond line", []],
            'pronoun is ignored' => ['then I shipped it and I\'m done', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('namedTerms')]
    public function test_it_finds_named_terms(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->terms($text));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function numbersInText(): array
    {
        return [
            'integer' => ['cut 40 pages', ['40']],
            'percent' => ['grew by 40%', ['40']],
            'thousands separator' => ['served 1,200,000 users', ['1200000']],
            'decimal' => ['ran 3.5 times faster', ['3.5']],
            'trailing zero' => ['ran 3.50 times faster', ['3.5']],
            'sentence-ending dot' => ['it took 12.', ['12']],
            'year range' => ['from 2019-2023', ['2019', '2023']],
            'number word' => ['led twelve people', ['12']],
            'capitalised number word' => ['Twelve people joined', ['12']],
            '"one" is not a number' => ['one of the tools', []],
            'digits in a term are not a number' => ['built with HTML5', []],
            'a version is a term, not a number' => ['pinned to 8.2.1', []],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('numbersInText')]
    public function test_it_finds_numbers(string $text, array $expected): void
    {
        $this->assertSame($expected, $this->numbers($text));
    }

    public function test_each_claim_is_listed_once(): void
    {
        $claims = (new TermExtractor)->claims('used Kotlin and then Kotlin for 12 and 12 more');

        $this->assertCount(2, $claims);
    }
}
