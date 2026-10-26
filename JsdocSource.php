<?php

namespace Omnidocs\Jsdoc;

use Omnidocs\Exception\SourceException;
use Omnidocs\Html\Conventions;
use Omnidocs\Html\Headings;
use Omnidocs\Html\Prose;
use Omnidocs\Html\Text;
use Omnidocs\LinkerInterface;
use Omnidocs\Model\Node;
use Omnidocs\Model\Page;
use Omnidocs\Model\Rendered;
use Omnidocs\Model\Symbol;
use Omnidocs\SourceInterface;
use Omnidocs\Support\Path;

/**
 * A JavaScript library's API reference, from what JSDoc explains of its
 * sources (`jsdoc -X src > api.json`: every doclet, commented or not).
 *
 * A library written as browser scripts - an IIFE or a UMD factory that sets
 * `root.StickyStops = api` - has no module JSDoc could name: its doclets are
 * `<anonymous>~api.attach`. The source follows the assignments the doclets
 * record (`root.StickyStops = api`, `api.Stops = Stops`, `var Sticky =
 * window.Sticky = {}`) to give each its public name (StickyStops.attach,
 * StickyStops.Stops#pause); what never reaches a global stays private and
 * is left out. A page per global, its members in the order of the source,
 * each with its anchor; a commented member with its text, an uncommented
 * function with its signature alone; each links to its line.
 *
 * The index (the home) lists the globals - by group when `groups` names
 * them (a section per group in the tree, so that a one-page reading groups
 * them too), each a card: what it is (its own comment's first sentence,
 * else its file's `@file` comment), what it holds, counted ("17 options ·
 * 20 methods · 2 events": the keys of its `defaults` or `settings` object, its functions,
 * the `@event`s documented on it, its properties), and the links `links`
 * gives it (its part of the manual, its demonstration).
 *
 *   new JsdocSource('var/jsdoc.json', root: '/flowjs');
 */
final class JsdocSource implements SourceInterface
{
    /** @var array<string, list<array<string, mixed>>>|null by global, in the order of the source */
    private ?array $globals = null;

    /** @var array<string, true> the globals the scripts export (root.X = ...) */
    private array $exported = [];

    /** @var array<string, array<string, true>> by global, the keys of its defaults (StickyStops.defaults.keys) */
    private array $options = [];

    /** @var array<string, string> by file name, its @file comment */
    private array $files = [];

    private readonly Conventions $conventions;

    /**
     * @param string       $file  the JSON jsdoc -X wrote
     * @param string       $root  where the sources were when JSDoc read them (its meta.path's start), so that a
     *                            doclet's file is named as the repository names it ("src/js/stops.js")
     * @param list<string>                         $order  the globals first in this order, the others after, by name
     * @param array<string, list<string>>          $groups a group's title => its globals: the index and the tree by group
     * @param array<string, array<string, string>> $links  a global => its links on the index ("Manual" => "/docs/#sticky")
     */
    public function __construct(
        private readonly string $file,
        private readonly string $root = '',
        private readonly string $title = 'API reference',
        private readonly array $order = [],
        ?Conventions $conventions = null,
        private readonly array $groups = [],
        private readonly array $links = [],
    ) {
        $this->conventions = $conventions ?? new Conventions();
    }

    public function home(): ?Node
    {
        return [] !== $this->globals() ? new Node('', $this->title, null, 0) : null;
    }

    public function tree(): array
    {
        $tree = [];
        $i = 0;
        foreach ($this->grouped() as $title => $globals) {
            $pages = [];
            foreach ($globals as $global) {
                $pages[] = new Node(self::pathOf($global), $global, null, ++$i);
            }
            if ('' === $title) {
                array_push($tree, ...$pages);
            } else {
                $tree[] = Node::section('group/'.Path::slug($title), $title, $pages, $i);
            }
        }

        return $tree;
    }

    /**
     * The globals by group, in the groups' order, each group's globals in the order of `order`; those of
     * no group after them, under "" (and all of them under "" without groups).
     *
     * @return array<string, list<string>>
     */
    private function grouped(): array
    {
        $all = array_keys($this->globals());
        $grouped = [];
        $placed = [];
        foreach ($this->groups as $title => $members) {
            $in = array_values(array_filter($all, static fn (string $g): bool => \in_array($g, $members, true)));
            if ([] !== $in) {
                $grouped[(string) $title] = $in;
                $placed += array_flip($in);
            }
        }
        $rest = array_values(array_filter($all, static fn (string $g): bool => !isset($placed[$g])));
        if ([] !== $rest) {
            $grouped[''] = $rest;
        }

        return $grouped;
    }

    /**
     * What a global holds, counted: the keys of its defaults, its functions, its events, its other properties.
     *
     * @return array{options: int, methods: int, events: int, properties: int}
     */
    public function counts(string $global): array
    {
        $counts = ['options' => \count($this->options[$global] ?? []), 'methods' => 0, 'events' => 0, 'properties' => 0];
        foreach ($this->globals()[$global] ?? [] as $doclet) {
            if ($doclet['omnidocs_name'] === $global) {
                continue;
            }
            $kind = $doclet['kind'] ?? 'member';
            ++$counts['function' === $kind || 'class' === $kind ? 'methods' : ('event' === $kind ? 'events' : 'properties')];
        }

        return $counts;
    }

    /** What a global is, in a sentence: its own comment's first one, else its file's @file comment's. */
    public function describe(string $global): string
    {
        $own = $this->own($global);
        if (null !== $own && '' !== self::summary($own)) {
            return self::summary($own);
        }
        foreach ($this->globals()[$global] ?? [] as $doclet) {
            $file = (string) ($doclet['meta']['filename'] ?? '');
            if (isset($this->files[$file])) {
                return self::summary(['description' => $this->files[$file]]);
            }
        }

        return '';
    }

    public function page(string $path, ?LinkerInterface $links = null): ?Page
    {
        if ('' === $path) {
            if ([] === $this->globals()) {
                return null;
            }
            $html = '';
            foreach ($this->grouped() as $title => $globals) {
                $html .= '<section class="api-group">'.('' !== $title ? '<p class="api-group-title">'.Conventions::escape($title).'</p>' : '').'<ul class="api-cards">';
                foreach ($globals as $global) {
                    $html .= $this->card($global, $links);
                }
                $html .= '</ul></section>';
            }

            return new Page('', $this->title, new Rendered('<div class="api-index">'.$html.'</div>', $this->title, [], [], Text::of($html)));
        }
        foreach (array_keys($this->globals()) as $global) {
            if (self::pathOf($global) === $path) {
                [$html, $symbols] = $this->build($global, $links);

                return new Page($path, $global, new Rendered($html, $global, Headings::of($html, 2, 3), [], Text::of($html)), null, $symbols);
            }
        }

        return null;
    }

    public function symbols(): array
    {
        $all = [];
        foreach (array_keys($this->globals()) as $global) {
            array_push($all, ...$this->build($global, null)[1]);
        }

        return $all;
    }

    /** A global's card on the index: its name (to its part), what it is, what it holds, its links. */
    private function card(string $global, ?LinkerInterface $links): string
    {
        $words = ['options' => ['option', 'options'], 'methods' => ['method', 'methods'], 'events' => ['event', 'events'], 'properties' => ['property', 'properties']];
        $held = [];
        foreach ($this->counts($global) as $key => $n) {
            if ($n > 0) {
                $held[] = $n.' '.$words[$key][1 === $n ? 0 : 1];
            }
        }
        $more = '';
        foreach ($this->links[$global] ?? [] as $label => $url) {
            $more .= ('' !== $more ? ' ' : '').'<a href="'.Conventions::escape((string) $url).'">'.Conventions::escape((string) $label).'</a>';
        }
        $about = $this->describe($global);

        return '<li class="api-card" data-global="'.Conventions::escape($global).'">'
            .'<p class="api-card-name"><a href="'.Conventions::escape(null !== $links ? $links->page(self::pathOf($global)) : '#').'"><code>'.Conventions::escape($global).'</code></a></p>'
            .('' !== $about ? '<p class="api-card-about">'.Conventions::escape($about).'</p>' : '')
            .([] !== $held ? '<p class="api-card-counts">'.Conventions::escape(implode(' · ', $held)).'</p>' : '')
            .('' !== $more ? '<p class="api-card-links">'.$more.'</p>' : '')
            .'</li>';
    }

    /** @return array{string, list<Symbol>} */
    private function build(string $global, ?LinkerInterface $links): array
    {
        $taken = [];
        $html = '';
        $symbols = [];
        $path = self::pathOf($global);
        $own = $this->own($global);
        if (null !== $own) {
            $html .= Prose::toHtml((string) ($own['description'] ?? ''));
            $html .= $this->source($own, $links, true);
        }
        foreach ($this->globals()[$global] as $doclet) {
            $name = (string) $doclet['omnidocs_name'];
            if ($name === $global) {
                continue;
            }
            // Its id without its global's name: the page is the global's ("attach" on StickyStops' page).
            $id = 'event' === ($doclet['kind'] ?? null)
                ? Headings::id('event-'.str_replace(':', '-', (string) ($doclet['name'] ?? $name)), $taken)
                : Headings::id(str_replace(['#', '.'], '-', str_starts_with($name, $global.'.') ? substr($name, \strlen($global) + 1) : $name), $taken);
            $signature = 'event' === ($doclet['kind'] ?? null) ? (string) ($doclet['name'] ?? $name) : self::signature($doclet, $name);
            $kind = (string) ($doclet['kind'] ?? 'member');
            $html .= '<h2 id="'.$id.'"><code>'.Conventions::escape($signature).'</code> <span class="api-kind">'.Conventions::escape(self::kindOf($doclet)).'</span></h2>';
            $html .= $this->source($doclet, $links, false);
            $html .= Prose::toHtml((string) ($doclet['description'] ?? ''));
            $rows = '';
            foreach ((array) ($doclet['params'] ?? []) as $param) {
                if (\is_array($param) && isset($param['name'])) {
                    $type = isset($param['type']['names']) ? implode('|', (array) $param['type']['names']) : '';
                    $rows .= '<dt><code>'.Conventions::escape((string) $param['name']).'</code>'.('' !== $type ? ' <span class="api-kind">'.Conventions::escape($type).'</span>' : '').'</dt><dd>'.Prose::toHtml((string) ($param['description'] ?? '')).'</dd>';
                }
            }
            foreach ((array) ($doclet['returns'] ?? []) as $return) {
                if (\is_array($return)) {
                    $rows .= '<dt>Returns</dt><dd>'.Prose::toHtml((string) ($return['description'] ?? '')).'</dd>';
                }
            }
            if ('' !== $rows) {
                $html .= '<dl class="api-params">'.$rows.'</dl>';
            }
            $symbols[] = new Symbol($kind, $name, $path, $id, self::summary($doclet), $signature, $global, $this->fileOf($doclet).'#L'.(int) ($doclet['meta']['lineno'] ?? 0));
        }

        return [$html, $symbols];
    }

    private function source(array $doclet, ?LinkerInterface $links, bool $paragraph): string
    {
        $file = $this->fileOf($doclet);
        $line = (int) ($doclet['meta']['lineno'] ?? 0);
        $url = null !== $links ? $links->file($file, 'L'.$line) : null;
        if (null === $url) {
            return '';
        }
        $link = '<a class="api-source" href="'.Conventions::escape($url).'">'.Conventions::escape(basename($file)).':'.$line.'</a>';

        return $paragraph ? '<p class="api-declaration">'.$link.'</p>' : '<p class="api-declaration">'.$link.'</p>';
    }

    /** @return array<string, mixed>|null the global's own doclet, when it has a comment */
    private function own(string $global): ?array
    {
        foreach ($this->globals()[$global] ?? [] as $doclet) {
            if ($doclet['omnidocs_name'] === $global && '' !== trim((string) ($doclet['description'] ?? ''))) {
                return $doclet;
            }
        }

        return null;
    }

    /**
     * The doclets that reach a global, by global, each with its public name.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function globals(): array
    {
        if (null !== $this->globals) {
            return $this->globals;
        }
        if (!is_file($this->file)) {
            throw new SourceException(\sprintf('No JSDoc output at %s (jsdoc -X src > %s).', $this->file, basename($this->file)));
        }
        $doclets = json_decode((string) file_get_contents($this->file), true);
        if (!\is_array($doclets) || !array_is_list($doclets)) {
            throw new SourceException(\sprintf('%s is not what jsdoc -X writes: a list of doclets.', $this->file));
        }
        // The names each file gives its globals: "api" is StickyStops in stops.js.
        $aliases = [];
        $pending = [];
        $this->exported = [];
        $this->files = [];
        foreach ($doclets as $doclet) {
            if (\is_array($doclet) && 'file' === ($doclet['kind'] ?? null) && isset($doclet['meta']['filename']) && '' !== trim((string) ($doclet['description'] ?? ''))) {
                $this->files[(string) $doclet['meta']['filename']] = (string) $doclet['description'];
            }
            if (!\is_array($doclet) || !isset($doclet['longname'], $doclet['meta']['filename'])) {
                continue;
            }
            $code = (array) ($doclet['meta']['code'] ?? []);
            $file = (string) $doclet['meta']['filename'];
            $long = (string) $doclet['longname'];
            // root.StickyStops = api: what a UMD factory exports, by its inner name (root: the factory's own
            // parameter for the global object). A page-level window.onpopstate = handler is no export.
            if (preg_match('/^root\.([A-Za-z_$][\w$]*)$/', $long, $m) && 'Identifier' === ($code['type'] ?? null) && \is_string($code['value'] ?? null)) {
                $aliases[$file][$code['value']] = $m[1];
                $aliases[$file][$m[1]] ??= $m[1];
                $this->exported[$m[1]] = true;
            }
            // var Sticky = window.Sticky = {}: the inner Sticky is the global.
            if (preg_match('/^<anonymous>~([A-Za-z_$][\w$]*)$/', $long, $m) && 'AssignmentExpression' === ($code['type'] ?? null) && \is_string($code['value'] ?? null) && preg_match('/^(?:root|window)\.([A-Za-z_$][\w$]*)$/', $code['value'], $g)) {
                $pending[$file][$m[1]] = $g[1];
            }
        }
        foreach ($pending as $file => $names) {
            foreach ($names as $inner => $global) {
                if (isset($this->exported[$global])) {
                    $aliases[$file][$inner] = $global;
                }
            }
        }
        // api.Stops = Stops: the inner Stops is StickyStops.Stops (until nothing new is found).
        do {
            $added = false;
            foreach ($doclets as $doclet) {
                $code = (array) ($doclet['meta']['code'] ?? []);
                $file = (string) ($doclet['meta']['filename'] ?? '');
                if ('Identifier' !== ($code['type'] ?? null) || !\is_string($code['value'] ?? null) || isset($aliases[$file][$code['value']])) {
                    continue;
                }
                $public = $this->publicName((string) ($doclet['longname'] ?? ''), $aliases[$file] ?? []);
                if (null !== $public && 1 === preg_match('/^[\w$.]+$/', $public)) {
                    $aliases[$file][$code['value']] = $public;
                    $added = true;
                }
            }
        } while ($added);

        $globals = [];
        $seen = [];
        foreach ($doclets as $doclet) {
            if (!\is_array($doclet) || !\in_array($doclet['kind'] ?? null, ['function', 'member', 'constant', 'class', 'namespace', 'event', 'typedef'], true) || 'package' === ($doclet['kind'] ?? null)) {
                continue;
            }
            $file = (string) ($doclet['meta']['filename'] ?? '');
            if ('event' === ($doclet['kind'] ?? null)) {
                // @event Global#name: an event documented on a global (its name may hold colons: scene:progress).
                $of = (string) ($doclet['memberof'] ?? '');
                $name = isset($this->exported[(string) preg_split('/[.#~]/', $of)[0]]) ? $of.'#event:'.($doclet['name'] ?? '') : null;
            } else {
                $name = $this->publicName((string) ($doclet['longname'] ?? ''), $aliases[$file] ?? []);
            }
            // Nothing a method sets on its instance (Scene#sideways#onTrack): what a method does inside is its own.
            if (null === $name || substr_count($name, '#') > 1 || preg_match('/#[\w$]+[.#]/', $name)) {
                continue;
            }
            // A key of an exported defaults or settings object (StickyStops.defaults.keys, Sticky.settings.debug,
            // StickySortable.Tree.defaults.indent):
            // counted as an option of the global, listed only when commented.
            if (preg_match('/^([A-Za-z_$][\w$]*)(?:\.[A-Z][\w$]*)?\.(?:defaults|settings)\.[\w$]+$/', $name, $o)) {
                $this->options[$o[1]][$name] = true;
            }
            $documented = !($doclet['undocumented'] ?? false);
            // Uncommented: a function says its signature; a plain value says nothing.
            if (!$documented && 'function' !== ($doclet['kind'] ?? null) && 'class' !== ($doclet['kind'] ?? null) && !\in_array($name, array_values($aliases[$file] ?? []), true)) {
                continue;
            }
            $global = (string) preg_split('/[.#~]/', $name)[0];
            $key = $name;
            if (isset($seen[$key])) {
                // The same name twice (an assignment and its declaration): the commented one wins.
                [$g, $i] = $seen[$key];
                if ($documented && ($globals[$g][$i]['undocumented'] ?? false)) {
                    $globals[$g][$i] = ['omnidocs_name' => $name] + $doclet;
                }
                continue;
            }
            $globals[$global][] = ['omnidocs_name' => $name] + $doclet;
            $seen[$key] = [$global, \count($globals[$global]) - 1];
        }
        $rank = array_flip($this->order);
        uksort($globals, static fn (string $a, string $b): int => [$rank[$a] ?? \PHP_INT_MAX, $a] <=> [$rank[$b] ?? \PHP_INT_MAX, $b]);

        /** @var array<string, list<array<string, mixed>>> $globals */
        return $this->globals = $globals;
    }

    /**
     * "<anonymous>~api.attach" → "StickyStops.attach", "window.Sticky.get" → "Sticky.get"; null for what stays private.
     *
     * @param array<string, string> $aliases
     */
    private function publicName(string $longname, array $aliases): ?string
    {
        if (preg_match('/^(?:root|window|self|globalThis)\.(.+)$/', $longname, $m)) {
            return isset($this->exported[(string) preg_split('/[.#~]/', $m[1])[0]]) ? $m[1] : null;
        }
        if (!preg_match('/^<anonymous>~([A-Za-z_$][\w$]*)(.*)$/', $longname, $m)) {
            return null;
        }
        if (!isset($aliases[$m[1]]) || str_contains($m[2], '~')) {
            return null; // an inner function of a function: private
        }

        return $aliases[$m[1]].$m[2];
    }

    private function fileOf(array $doclet): string
    {
        $dir = (string) ($doclet['meta']['path'] ?? '');
        $root = rtrim($this->root, '/');
        if ('' !== $root && str_starts_with($dir, $root)) {
            $dir = substr($dir, \strlen($root));
        }

        return (string) Path::normalise(trim($dir, '/').'/'.($doclet['meta']['filename'] ?? ''));
    }

    private static function signature(array $doclet, string $name): string
    {
        if (\in_array($doclet['kind'] ?? null, ['function', 'class'], true)) {
            $params = [];
            foreach ((array) ($doclet['params'] ?? []) as $param) {
                if (\is_array($param) && isset($param['name']) && !str_contains((string) $param['name'], '.')) {
                    $params[] = ($param['optional'] ?? false) ? '['.$param['name'].']' : (string) $param['name'];
                }
            }
            if ([] === $params) {
                $params = array_map('strval', (array) ($doclet['meta']['code']['paramnames'] ?? []));
            }

            return $name.'('.implode(', ', $params).')';
        }

        return $name;
    }

    private static function kindOf(array $doclet): string
    {
        return match ($doclet['kind'] ?? 'member') {
            'function' => str_contains((string) ($doclet['longname'] ?? ''), '#') ? 'method' : 'function',
            'member' => 'property',
            'event' => 'event',
            default => (string) $doclet['kind'],
        };
    }

    /** The first sentence, as plain text. */
    private static function summary(array $doclet): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', (string) ($doclet['description'] ?? '')));
        if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $text, $m)) {
            $text = $m[1];
        }

        return str_replace('`', '', mb_strlen($text) > 200 ? mb_substr($text, 0, 199).'…' : $text);
    }

    /** "StickyStops" → "stickystops". */
    private static function pathOf(string $global): string
    {
        return Path::slug($global);
    }
}
