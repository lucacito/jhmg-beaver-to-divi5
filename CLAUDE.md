# Beaver Builder → Divi 5 Converter — working notes

- Never scrape HTML. Read `_fl_builder_data` only (published layouts). Never modify the source post.
- Divi attribute paths only as documented in `docs/divi5-schema.md` (read from the Divi source; reference zip is Divi 5.12.1 at `references/Divi.zip`). Do not invent block types or attribute shapes; unzip the reference and check `Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json` and `server/Packages/StyleLibrary/Declarations/`.
- Divi appends the unit to gradient stop positions itself: write `position: "23"`, never `"23%"`, or the whole background declaration (image included) is dropped.
- Beaver Builder field names for Lite modules come from the plugin source (`docs/beaver-schema.md`); Pro modules are documentation-based and registered `approximate: true`.
- Divi 5 nests: column → row → column. Use a nested flexed row for anything that must sit inline (button groups, icon groups), not stacked blocks.
- Every handler calls `logUnmappedSettings()`; nothing is dropped silently. Colours are never invented — unresolved globals are reported.
- Expected fixtures are a reviewed specification: run `scripts/render-fixture.php`, read the output, then `scripts/update-expected.php <name>`.
- Before claiming anything works: `vendor/bin/phpunit` (and `npx playwright test` when the Docker site is up). Report failures with output.
- The version lives in three places for free and two for Pro; the tests check them.
- Row/column `text_color` / `heading_color` / `link_color` cascade into child modules through the engine's inherited-colour stack (`pushInheritedColors` in Row/Column converters, applied in `BaseBeaverConverter::mapStyle`).
