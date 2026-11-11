<?php

declare(strict_types=1);

namespace AviLavi\LlmOutputGuard\Tests;

use AviLavi\LlmOutputGuard\Failure;
use AviLavi\LlmOutputGuard\FlowResult;
use AviLavi\LlmOutputGuard\GeneratedItem;
use AviLavi\LlmOutputGuard\Guard;
use AviLavi\LlmOutputGuard\ItemStatus;
use AviLavi\LlmOutputGuard\RepairFlow;
use AviLavi\LlmOutputGuard\SourceBank;
use AviLavi\LlmOutputGuard\ViolationKind;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RepairFlowTest extends TestCase
{
    private const MIGRATION = 'Moved 40 storefronts to GraphQL in 2023.';

    private const SPEED = 'Cut page load time by 35% using Redis.';

    private SourceBank $bank;

    protected function setUp(): void
    {
        $this->bank = SourceBank::fromArray(['migration' => self::MIGRATION, 'speed' => self::SPEED]);
    }

    /**
     * @param  list<GeneratedItem>  $items
     */
    private function runFlow(array $items, ?callable $repair = null): FlowResult
    {
        return (new RepairFlow(new Guard($this->bank), $this->bank))->run($items, $repair);
    }

    private function clean(string $key = 'clean'): GeneratedItem
    {
        return new GeneratedItem($key, 'Cut load time by 35% with Redis.', ['speed']);
    }

    private function bad(string $key = 'bad'): GeneratedItem
    {
        return new GeneratedItem($key, 'Moved 400 storefronts to GraphQL.', ['migration']);
    }

    public function test_clean_items_never_call_repair(): void
    {
        $result = $this->runFlow([$this->clean()], fn () => $this->fail('repair should not run'));

        $this->assertTrue($result->isClean());
        $this->assertSame(ItemStatus::Passed, $result->outcomes['clean']->status);
        $this->assertSame(['clean' => 'Cut load time by 35% with Redis.'], $result->texts());
    }

    public function test_repair_receives_only_the_failing_items_with_their_violations(): void
    {
        $seen = [];

        $this->runFlow([$this->clean(), $this->bad()], function (array $failures) use (&$seen) {
            $seen = $failures;

            return [];
        });

        $this->assertCount(1, $seen);
        $this->assertInstanceOf(Failure::class, $seen[0]);
        $this->assertSame('bad', $seen[0]->item->key);
        $this->assertSame(ViolationKind::UnsupportedNumber, $seen[0]->violations[0]->kind);
        $this->assertSame('The number "400" is not in the cited sources.', $seen[0]->feedback());
    }

    public function test_a_good_repair_is_used(): void
    {
        $fixed = new GeneratedItem('bad', 'Moved 40 storefronts to GraphQL.', ['migration']);

        $result = $this->runFlow([$this->bad()], fn () => [$fixed]);

        $outcome = $result->outcomes['bad'];
        $this->assertSame(ItemStatus::Repaired, $outcome->status);
        $this->assertSame('Moved 40 storefronts to GraphQL.', $outcome->text);
        $this->assertSame('400', $outcome->violations[0]->value, 'keeps what was wrong with the first draft');
        $this->assertFalse($result->isClean());
    }

    public function test_repair_runs_once_and_a_bad_repair_reverts_to_source_text(): void
    {
        $calls = 0;
        $stillBad = new GeneratedItem('bad', 'Moved 41 storefronts to GraphQL.', ['migration']);

        $result = $this->runFlow([$this->bad()], function () use (&$calls, $stillBad) {
            $calls++;

            return [$stillBad];
        });

        $outcome = $result->outcomes['bad'];
        $this->assertSame(1, $calls);
        $this->assertSame(ItemStatus::Reverted, $outcome->status);
        $this->assertSame(self::MIGRATION, $outcome->text);
        $this->assertSame('41', $outcome->violations[0]->value, 'reports the last draft');
    }

    public function test_an_item_the_repair_skips_reverts(): void
    {
        $result = $this->runFlow([$this->bad()], fn () => []);

        $this->assertSame(ItemStatus::Reverted, $result->outcomes['bad']->status);
    }

    public function test_without_a_repair_callable_failures_revert(): void
    {
        $result = $this->runFlow([$this->bad()]);

        $this->assertSame(ItemStatus::Reverted, $result->outcomes['bad']->status);
        $this->assertSame(self::MIGRATION, $result->outcomes['bad']->text);
    }

    public function test_a_throwing_repair_reverts_and_keeps_the_error(): void
    {
        $error = new RuntimeException('model timed out');

        $result = $this->runFlow([$this->bad()], fn () => throw $error);

        $this->assertSame(ItemStatus::Reverted, $result->outcomes['bad']->status);
        $this->assertSame($error, $result->repairError);
    }

    public function test_a_repair_cannot_rewrite_a_passing_item(): void
    {
        $sneaky = new GeneratedItem('clean', 'Cut load time by 35%.', ['speed']);

        $result = $this->runFlow([$this->clean(), $this->bad()], fn () => [$sneaky]);

        $this->assertSame(ItemStatus::Passed, $result->outcomes['clean']->status);
        $this->assertSame('Cut load time by 35% with Redis.', $result->outcomes['clean']->text);
    }

    public function test_several_citations_revert_to_their_joined_text(): void
    {
        $item = new GeneratedItem('both', 'Made up 7 claims.', ['migration', 'speed']);

        $result = $this->runFlow([$item]);

        $this->assertSame(self::MIGRATION.' '.self::SPEED, $result->outcomes['both']->text);
    }

    public function test_a_repair_with_only_fake_citations_reverts_to_the_first_drafts_sources(): void
    {
        $fake = new GeneratedItem('bad', 'Moved 40 storefronts.', ['invented']);

        $result = $this->runFlow([$this->bad()], fn () => [$fake]);

        $this->assertSame(ItemStatus::Reverted, $result->outcomes['bad']->status);
        $this->assertSame(self::MIGRATION, $result->outcomes['bad']->text);
    }

    public function test_an_item_with_no_real_source_is_dropped(): void
    {
        $item = new GeneratedItem('orphan', 'Moved 40 storefronts.', ['invented']);

        $result = $this->runFlow([$item, $this->clean()]);

        $this->assertSame(ItemStatus::Dropped, $result->outcomes['orphan']->status);
        $this->assertNull($result->outcomes['orphan']->text);
        $this->assertSame(['clean'], array_keys($result->texts()), 'dropped items have no text');
    }

    public function test_every_text_that_comes_out_passes_the_guard(): void
    {
        $guard = new Guard($this->bank);
        $items = [$this->clean(), $this->bad('a'), new GeneratedItem('b', 'Invented 9 things in Rust.', ['speed'])];

        $result = $this->runFlow($items, fn () => []);

        foreach ($result->outcomes as $key => $outcome) {
            $citations = $key === 'a' ? ['migration'] : ['speed'];

            $this->assertSame([], $guard->check(new GeneratedItem($key, (string) $outcome->text, $citations)));
        }
    }

    public function test_results_serialise_in_input_order(): void
    {
        $result = $this->runFlow([$this->bad(), $this->clean()]);

        $array = $result->toArray();

        $this->assertSame(['bad', 'clean'], array_column($array, 'key'));
        $this->assertSame('reverted', $array[0]['status']);
        $this->assertSame('unsupported_number', $array[0]['violations'][0]['kind']);
    }

    public function test_duplicate_item_keys_are_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->runFlow([$this->clean('same'), $this->clean('same')]);
    }
}
