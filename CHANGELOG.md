# Changelog

Every version of omnidocs/jsdoc, newest first ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
[Semantic Versioning](https://semver.org/)).

## Unreleased

### Changed
- Requires `omnisense/omnidocs`, the core's new name (Omniscient is now Omnisense); the repository is `glitchr-omnisense/omnidocs-jsdoc`.

### Added
- The index as cards, by group (`groups`): what each global is (its comment, else its file's `@file`
  comment), what it holds counted (options - the keys of its `defaults` or `settings` -, methods,
  events, properties), its links (`links`: its manual, its demo); the tree by group, a section per
  group. `counts()`, `describe()`.
- `@event Global#name` documented on a global: an entry of its page (its name may hold colons).
- What a method sets on its instance (`Scene#sideways#onTrack`) is no entry.
- The source, its factory, omnidocs' conformance suite on a real tool's output.
