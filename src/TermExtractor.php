<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard;

/**
 * Finds the facts in a piece of text that a reader would take as checkable: numbers and named terms.
 *
 * A named term is a word that looks like a proper noun or a product: capitalised in the
 * middle of a sentence, with an inner capital (GraphQL), a digit (OAuth2), or a . + # inside
 * it (Node.js, C++). A plain capitalised word at the start of a sentence is ignored,
 * because it is capitalised only by position.
 */
final class TermExtractor
{
    /** "one" is left out: it is used as a pronoun too often. */
    private const NUMBER_WORDS = [
        'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6', 'seven' => '7',
        'eight' => '8', 'nine' => '9', 'ten' => '10', 'eleven' => '11', 'twelve' => '12',
        'thirteen' => '13', 'fourteen' => '14', 'fifteen' => '15', 'sixteen' => '16',
        'seventeen' => '17', 'eighteen' => '18', 'nineteen' => '19', 'twenty' => '20',
        'thirty' => '30', 'forty' => '40', 'fifty' => '50', 'sixty' => '60', 'seventy' => '70',
        'eighty' => '80', 'ninety' => '90', 'hundred' => '100', 'thousand' => '1000',
        'million' => '1000000', 'billion' => '1000000000',
    ];

    /** Capitalised by grammar, so they name nothing. */
    private const PRONOUNS = ['i', "i'm", "i've", "i'd", "i'll"];

    /**
     * A plain number ("1,200", "3.5", "40"), or a word that may hold joining marks.
     * The possessive quantifiers stop "50k" being read as the number 5 and then "0k".
     */
    private const TOKEN = '~
        (?<number> (?: \d{1,3} (?: ,\d{3} )++ | \d++ ) (?: \.\d++ )?+ (?! \.? [\p{L}\p{N}] ) )
        | (?<word> [\p{L}\p{N}]+ (?: [.+\#/&\'’-]+ [\p{L}\p{N}]+ )* [+\#]* )
    ~xu';

    /**
     * The numbers and named terms in generated text, each listed once.
     *
     * @return list<Claim>
     */
    public function claims(string $text): array
    {
        $claims = [];

        foreach ($this->tokens($text) as [$token, $isNumber, $startsSentence]) {
            $wordNumber = $isNumber ? null : (self::NUMBER_WORDS[mb_strtolower($token)] ?? null);

            if ($isNumber || $wordNumber !== null) {
                $claim = new Claim(true, $token, $wordNumber ?? self::normalizeNumber($token));
            } elseif (self::isNamed($token, $startsSentence)) {
                $claim = new Claim(false, $token, mb_strtolower($token));
            } else {
                continue;
            }

            $claims[($claim->isNumber ? 'n:' : 't:').$claim->normalized] = $claim;
        }

        return array_values($claims);
    }

    /**
     * Everything a source or allowlist text vouches for, named or not.
     */
    public function vocabulary(string $text): Vocabulary
    {
        $numbers = [];
        $words = [];

        foreach ($this->tokens($text) as [$token, $isNumber]) {
            $lower = mb_strtolower($token);

            if ($isNumber) {
                $numbers[self::normalizeNumber($token)] = true;

                continue;
            }

            if (isset(self::NUMBER_WORDS[$lower])) {
                $numbers[self::NUMBER_WORDS[$lower]] = true;
            }

            foreach ([$lower, ...self::parts($lower)] as $word) {
                $words[$word] = true;
            }
        }

        return new Vocabulary($numbers, $words);
    }

    /**
     * @return list<string> The pieces of a compound such as "rest/webhooks" or "mid-market".
     */
    public static function parts(string $token): array
    {
        return preg_split('~[/&-]~', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @return list<array{0: string, 1: bool, 2: bool}> Token, is it a number, does it start a sentence.
     */
    private function tokens(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        $tokens = [];

        foreach ($matches as $match) {
            [$token, $offset] = $match[0];
            $isNumber = $match['number'][0] !== null;

            if (! $isNumber) {
                $token = preg_replace("~['’]s$~iu", '', $token);
            }

            $tokens[] = [$token, $isNumber, self::startsSentence(substr($text, 0, $offset))];
        }

        return $tokens;
    }

    private static function startsSentence(string $before): bool
    {
        $before = rtrim($before, " \t");

        return $before === '' || preg_match('~[.!?:;•·—–|(\["“\n]$~u', $before) === 1;
    }

    private static function isNamed(string $token, bool $startsSentence): bool
    {
        if (in_array(str_replace('’', "'", mb_strtolower($token)), self::PRONOUNS, true)) {
            return false;
        }

        return preg_match('~\p{N}~u', $token) === 1         // OAuth2, 50k
            || preg_match('~.\p{Lu}~u', $token) === 1       // GraphQL, AWS
            || preg_match('~[+\#]|.\..~u', $token) === 1    // C++, C#, Node.js
            || (! $startsSentence && preg_match('~^\p{Lu}~u', $token) === 1);
    }

    private static function normalizeNumber(string $number): string
    {
        $number = str_replace(',', '', $number);

        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return ltrim($number, '0') === '' ? '0' : $number;
    }
}
