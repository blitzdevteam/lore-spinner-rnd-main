<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Ai\Adaptation\ReintroTracker;
use PHPUnit\Framework\TestCase;

final class ReintroTrackerTest extends TestCase
{
    private ReintroTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new ReintroTracker;
    }

    public function testParsesColdOpenAndLaterMarkers(): void
    {
        $mustReintroduce = "Zoe knows about the hold — COLD OPEN\nEmmit's history with the company — LATER\nThe match in the suit pocket — LATER";

        $items = $this->tracker->parse($mustReintroduce);

        $this->assertCount(3, $items);
        $this->assertSame('Zoe knows about the hold', $items[0]['fact']);
        $this->assertSame('COLD OPEN', $items[0]['marker']);
        $this->assertSame("Emmit's history with the company", $items[1]['fact']);
        $this->assertSame('LATER', $items[1]['marker']);
        $this->assertSame('The match in the suit pocket', $items[2]['fact']);
        $this->assertSame('LATER', $items[2]['marker']);
    }

    public function testParsesDoubleHyphenMarkers(): void
    {
        $mustReintroduce = "The security seal -- COLD OPEN\nThe wing compartment -- LATER";

        $items = $this->tracker->parse($mustReintroduce);

        $this->assertCount(2, $items);
        $this->assertSame('The security seal', $items[0]['fact']);
        $this->assertSame('COLD OPEN', $items[0]['marker']);
    }

    public function testLinesWithoutMarkerDefaultToLater(): void
    {
        $items = $this->tracker->parse("A fact with no marker");

        $this->assertCount(1, $items);
        $this->assertSame('A fact with no marker', $items[0]['fact']);
        $this->assertSame('LATER', $items[0]['marker']);
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->tracker->parse(''));
        $this->assertSame([], $this->tracker->parse("  \n  \n  "));
    }

    public function testPendingFiltersOutReintroducedItems(): void
    {
        $items = $this->tracker->parse("Fact one — COLD OPEN\nFact two — LATER\nFact three — LATER");
        $reintroducedKeys = [$items[0]['key']]; // Fact one is done

        $pending = $this->tracker->pending($items, $reintroducedKeys);

        $this->assertCount(2, $pending);
        $this->assertSame('Fact two', $pending[0]['fact']);
        $this->assertSame('Fact three', $pending[1]['fact']);
    }

    public function testPendingWithNoReintroducedReturnsAll(): void
    {
        $items = $this->tracker->parse("Fact one — COLD OPEN\nFact two — LATER");

        $pending = $this->tracker->pending($items, []);

        $this->assertCount(2, $pending);
    }

    public function testRenderProducesPromptString(): void
    {
        $items = $this->tracker->parse("Fact one — COLD OPEN\nFact two — LATER");
        $pending = $this->tracker->pending($items, []);

        $rendered = $this->tracker->render($pending);

        $this->assertStringContainsString('Fact one — COLD OPEN', $rendered);
        $this->assertStringContainsString('Fact two — LATER', $rendered);
    }

    public function testRenderEmptyReturnsEmptyString(): void
    {
        $this->assertSame('', $this->tracker->render([]));
    }

    public function testKeysFromReportedFactsNormalizesText(): void
    {
        $keys = $this->tracker->keysFromReportedFacts([
            'Zoe knows about the hold',
            '  Emmit\'s history with the company  ',
        ]);

        $this->assertCount(2, $keys);
        $this->assertSame('zoe knows about the hold', $keys[0]);
        $this->assertSame("emmit's history with the company", $keys[1]);
    }

    public function testKeysFromReportedFactsHandlesNullAndEmpty(): void
    {
        $this->assertSame([], $this->tracker->keysFromReportedFacts(null));
        $this->assertSame([], $this->tracker->keysFromReportedFacts([]));
        $this->assertSame([], $this->tracker->keysFromReportedFacts(['', '  ']));
    }

    public function testFactKeyMatchesAcrossRephrasings(): void
    {
        $items = $this->tracker->parse("The match in the suit pocket — LATER");
        $reportedKeys = $this->tracker->keysFromReportedFacts([
            'The match in the suit pocket', // narrator reports the same fact
        ]);

        $pending = $this->tracker->pending($items, $reportedKeys);

        $this->assertCount(0, $pending, 'Fact should be filtered out when narrator reports it');
    }

    public function testParsesStructuredArrayFormat(): void
    {
        $mustReintroduce = [
            [
                'fact'              => 'Zoe knows about the hold',
                'marker'            => 'COLD_OPEN',
                'target_moment_key' => '',
                'key_terms'         => ['Zoe', 'hold'],
            ],
            [
                'fact'              => "Emmit's history with the company",
                'marker'            => 'LATER',
                'target_moment_key'  => 'S1_M02',
                'key_terms'         => ['Emmit', 'company'],
            ],
        ];

        $items = $this->tracker->parse($mustReintroduce);

        $this->assertCount(2, $items);
        $this->assertSame('Zoe knows about the hold', $items[0]['fact']);
        $this->assertSame('COLD OPEN', $items[0]['marker']);
        $this->assertSame('', $items[0]['target_moment_key']);
        $this->assertSame(['Zoe', 'hold'], $items[0]['key_terms']);
        $this->assertSame("Emmit's history with the company", $items[1]['fact']);
        $this->assertSame('LATER', $items[1]['marker']);
        $this->assertSame('S1_M02', $items[1]['target_moment_key']);
        $this->assertSame(['Emmit', 'company'], $items[1]['key_terms']);
    }

    public function testPendingFiltersByTargetMomentKey(): void
    {
        $items = $this->tracker->parse([
            [
                'fact'              => 'Fact for M01',
                'marker'            => 'LATER',
                'target_moment_key' => 'S1_M01',
                'key_terms'         => [],
            ],
            [
                'fact'              => 'Fact for M02',
                'marker'            => 'LATER',
                'target_moment_key' => 'S1_M02',
                'key_terms'         => [],
            ],
            [
                'fact'              => 'Fact for any moment',
                'marker'            => 'LATER',
                'target_moment_key' => '',
                'key_terms'         => [],
            ],
        ]);

        // At M01: only M01-targeted and untargeted items appear
        $pendingM01 = $this->tracker->pending($items, [], 'S1_M01');
        $this->assertCount(2, $pendingM01);
        $this->assertSame('Fact for M01', $pendingM01[0]['fact']);
        $this->assertSame('Fact for any moment', $pendingM01[1]['fact']);

        // At M02: only M02-targeted and untargeted items appear
        $pendingM02 = $this->tracker->pending($items, [], 'S1_M02');
        $this->assertCount(2, $pendingM02);
        $this->assertSame('Fact for M02', $pendingM02[0]['fact']);
        $this->assertSame('Fact for any moment', $pendingM02[1]['fact']);

        // No active moment: all appear
        $pendingAll = $this->tracker->pending($items, [], '');
        $this->assertCount(3, $pendingAll);
    }

    public function testRenderIncludesTargetMomentKey(): void
    {
        $items = $this->tracker->parse([
            [
                'fact'              => 'Emmit\'s history',
                'marker'            => 'LATER',
                'target_moment_key' => 'S1_M02',
                'key_terms'         => [],
            ],
        ]);

        $rendered = $this->tracker->render($items);

        $this->assertStringContainsString('target: S1_M02', $rendered);
    }

    public function testBackwardCompatibleWithFlatString(): void
    {
        $flatString = "Zoe knows about the hold — COLD OPEN\nEmmit's history — LATER";
        $items = $this->tracker->parse($flatString);

        $this->assertCount(2, $items);
        $this->assertSame('', $items[0]['target_moment_key']);
        $this->assertSame([], $items[0]['key_terms']);
    }
}
