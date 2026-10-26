# omnidocs/jsdoc

A JavaScript library's API reference from what JSDoc explains of its sources (`jsdoc -X src > api.json`:
every doclet, commented or not), as an [omnidocs](https://github.com/glitchr-omnisense/omnidocs) manual:
a page per global, its functions, methods and properties in the order of the source - a commented one
with its text and parameters, an uncommented function with its signature - each at its anchor, each
linked to its line.

A library written as browser scripts - an IIFE or a UMD factory that ends `root.StickyStops = api` -
has no module JSDoc could name: its doclets are `<anonymous>~api.attach`. The source follows the
assignments the doclets record (`root.StickyStops = api`, `api.Stops = Stops`, `var Sticky =
window.Sticky = {}`) to give each its public name (`StickyStops.attach`, `StickyStops.Stops#refresh`);
what never reaches a global stays private and is left out.

```sh
npx jsdoc@4 -X src/js > var/jsdoc.json
```
```php
$api = (new Omnidocs\Jsdoc\JsdocSourceFactory())->create(['file' => 'var/jsdoc.json', 'root' => getcwd()]);
```

| Option | Default | Meaning |
|---|---|---|
| `file` | (required) | the JSON `jsdoc -X` wrote |
| `root` | `""` | where the sources were when JSDoc read them: each file named as the repository names it |
| `title` | `API reference` | the reference's title |
| `order` | `[]` | the globals first, in this order |
| `groups` | `[]` | a group's title => its globals (`{"Navigation": ["Transparent", "FlowFrame"]}`): the index's cards by group, and the tree by group (a section per group, so that a one-page reading groups them in its table of contents) |
| `links` | `[]` | a global => its links on the index card, label => address (`{"StickyStops": {"Manual": "/docs/#stops", "Demo": "/#stops"}}`) |

## The index

The reference's home lists the globals, by group when `groups` names them, each a card
(`li.api-card`): its name, linked to its page; what it is in a sentence - its own comment's first one,
else the `@file` comment of the file it is written in (`/** @file StickyScenes, scenes the scroll
plays. */`); what it holds, counted (`.api-card-counts`: "18 options · 45 methods · 2 events · 2
properties" - the keys of its `defaults` or `settings` object, its functions, the `@event`s documented
on it, its other properties); the links `links` gives it. `JsdocSource::counts($global)` and
`describe($global)` give the same to a site's own template.

An event is documented where it is dispatched, on its global - its name may hold colons:

```js
/**
 * Told on the scene (it bubbles) at each frame its progress changes.
 * @event StickyScenes#scene:progress
 */
```

It has its entry on the global's page (`#event-scene-progress`), as an event.

## Why JSDoc, not TypeDoc

Tried on flowjs' real sources (2026-10-25, JSDoc 4.0.5 and TypeDoc 0.28.20 with `allowJs`): TypeDoc
documents modules' exports, and of flowjs' seven UMD scripts it saw only the one whose factory returns
an object literal (StickyStops, its nine members); the six others (Flow, Transparent, Sticky, Ajaxer, Cookie,
StickySortable) build their API at run time and came out empty. JSDoc reads every doclet of every
script (3 988), comments or not; their names are recovered as above: 7 globals, 299 public entries.

## Verified, and not

- Verified (omnidocs' harness, PHP 8.4): omnidocs' conformance suite on JSDoc 4.0.5's real output for
  flowjs' stops.js and sortable.js (Tests/Fixtures), and on the whole of flowjs (`omnidocs real`);
  `BareTest`.
- Verified in use: the flowjs site's API reference.
- Not verified: ES modules (`export function`), whose doclets JSDoc names `module:x~y` - read, but no
  library of the ecosystem is written so; JSDoc's own `@module`, `@namespace` tags beyond their names.

Licence: MIT.
