# omnidocs/jsdoc

A JavaScript library's API reference from what JSDoc explains of its sources (`jsdoc -X src > api.json`:
every doclet, commented or not), as an [omnidocs](https://github.com/glitchr-studio/omnidocs) manual:
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
