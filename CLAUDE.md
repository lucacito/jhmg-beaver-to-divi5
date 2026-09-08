# Beaver Builder → Divi 5 Converter — working notes

- Never scrape HTML. Read `_fl_builder_data` only (published layouts). Never modify the source post.
- Divi attribute paths only as documented in `docs/divi5-schema.md` (read from the Divi 5.7.4 source). Do not invent block types or attribute shapes; check the module.json under
  `../jhmg-elementor-to-divi5/references/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/`.
- Beaver Builder field names for Lite modules come from the plugin source (`docs/beaver-schema.md`); Pro modules are documentation-based and registered `approximate: true`.
- Divi 5 nests: column → row → column. Use a nested flexed row for anything that must sit inline (button groups, icon groups), not stacked blocks.
- Every handler calls `logUnmappedSettings()`; nothing is dropped silently. Colours are never invented — unresolved globals are reported.
- Expected fixtures are a reviewed specification: run `scripts/render-fixture.php`, read the output, then `scripts/update-expected.php <name>`.
- Before claiming anything works: `vendor/bin/phpunit` (and `npx playwright test` when the Docker site is up). Report failures with output.
- The version lives in three places for free and two for Pro; the tests check them.
