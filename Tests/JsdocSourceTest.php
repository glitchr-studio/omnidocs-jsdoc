<?php

namespace Omnidocs\Jsdoc\Tests;

use Omnidocs\Jsdoc\JsdocSourceFactory;
use Omnidocs\Model\Node;
use Omnidocs\SourceInterface;
use Omnidocs\Testing\RecordingLinker;
use Omnidocs\Testing\SourceContractTests;
use PHPUnit\Framework\TestCase;

/**
 * Real output: `jsdoc -X src/js` (JSDoc 4.0.5) on glitchr-studio/flowjs at c417356, the doclets of its
 * stops.js and sortable.js - two UMD factories that set root.StickyStops and root.StickySortable.
 */
final class JsdocSourceTest extends TestCase
{
    use SourceContractTests;

    protected function source(): SourceInterface
    {
        return (new JsdocSourceFactory())->create(['file' => __DIR__.'/Fixtures/flowjs-stops-sortable.json', 'root' => '/flowjs', 'order' => ['StickyStops']]);
    }

    protected function documentsSymbols(): bool
    {
        return true;
    }

    public function testEachGlobalTheScriptsSetIsAPage(): void
    {
        self::assertSame([['stickystops', 'StickyStops'], ['stickysortable', 'StickySortable']], array_map(static fn (Node $n): array => [$n->path, $n->title], $this->source()->tree()));
    }

    public function testTheFactorysInnerNamesAreTheGlobalsPublicOnes(): void
    {
        $names = array_map(static fn ($s): string => $s->name, $this->source()->symbols());

        self::assertContains('StickyStops.attach', $names, '<anonymous>~api.attach, api being root.StickyStops');
        self::assertContains('StickyStops.pause', $names);
        self::assertContains('StickyStops.Stops#refresh', $names, 'api.Stops = Stops: its instances\' methods');
        self::assertNotContains('StickyStops.easing', $names, 'an inner helper never set on the global: private');
        foreach ($names as $name) {
            self::assertStringNotContainsString('<anonymous>', $name);
        }
    }

    public function testAMemberSaysItsCommentItsSignatureAndItsLine(): void
    {
        $links = new RecordingLinker();
        $html = (string) $this->source()->page('stickystops', $links)?->html();

        self::assertMatchesRegularExpression('#<h2 id="attach"><code>StickyStops\.attach\([^)]*\)</code> <span class="api-kind">function</span></h2>#', $html);
        self::assertStringContainsString('The stops of a scroller (the window by default); made if needed.', $html);
        self::assertStringContainsString('href="repo:src/js/stops.js#L', $html, 'its line, in the repository');
        $attach = null;
        foreach ($this->source()->symbols() as $symbol) {
            $attach = 'StickyStops.attach' === $symbol->name ? $symbol : $attach;
        }
        self::assertNotNull($attach);
        self::assertSame('stickystops', $attach->page);
        self::assertStringStartsWith('src/js/stops.js#L', (string) $attach->source);
    }
}
