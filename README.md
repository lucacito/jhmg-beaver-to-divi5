# Beaver Builder → Divi 5 Converter

Two WordPress plugins that convert Beaver Builder layouts into native Divi 5 block pages:

- `plugin/jhmg-converter-for-beaver-builder-to-divi` — free (wordpress.org)
- `plugin/jhmg-converter-for-beaver-builder-to-divi-pro` — Pro add-on (divi5lab.com)

Spec: `docs/superpowers/specs/2026-09-08-beaver-to-divi5-converter-design.md`.
Docs: `docs/beaver-schema.md`, `docs/divi5-schema.md`, `docs/conversion-map.md`,
`docs/conversion-workflow.md`, `docs/conversion-reporting.md`.

## Develop

```bash
composer install && npm install
vendor/bin/phpunit                      # unit + fixture + bundled-template tests (no WordPress needed)
scripts/docker/setup_wp.sh              # WordPress + Divi 5 + Beaver Builder Lite on http://localhost:8010 (admin/admin)
npx playwright test                     # end-to-end against the container
```

`references/` must hold `Divi.zip` and `beaver-builder-lite-version.2.10.3.2.zip` (see `references/README.md`).

## Fixtures

- `fixtures/beaver/*.json` → `fixtures/divi/*.json`: hand-reviewed input/expected pairs. Regenerate an
  expected file only after reviewing `php scripts/render-fixture.php fixtures/beaver/<name>.json --report`,
  with `php scripts/update-expected.php <name>`.
- `fixtures/beaver-templates/*.json`: Beaver Builder Lite's bundled layouts (`scripts/bb-dat-to-json.php`).
- `fixtures/beaver-import/*`: upload formats (WXR, .dat, JSON).
- `beaver templates/*.xml`: real Beaver Builder exports from a site running Ultimate Addons, PowerPack and
  Beaver Builder Pro. `tests/ThirdPartyTemplateConversionTest.php` requires each to convert validator-clean with
  no placeholder and no skipped setting; drop more exports in to widen the net. Single-module fixtures for the
  modules they introduced (`list`, `progress-bar`, `pp-heading`, `pp-icon-list`, `pp-fluent-form`) were cut from them.

## Adding a module handler

1. Read the module's fields from Beaver Builder's source (`modules/<slug>/<slug>.php`).
2. Create `includes/converter/handlers/class-<slug>-converter.php` extending `BaseBeaverConverter`; call
   `mapStyle()`, write the Divi content keys (see `docs/divi5-schema.md`), `logConverted()`, and pass every key
   you consumed to `logUnmappedSettings()`.
3. Register it in `ConverterRegistry::registerDefaults()` (`approximate: true` if the fields are not source-verified).
4. Add `fixtures/beaver/<slug>.json`, review the render, generate the expected file, run `vendor/bin/phpunit`.
