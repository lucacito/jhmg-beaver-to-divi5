# Beaver Builder → Divi 5 Converter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a free WordPress plugin (plus a Pro add-on) that converts Beaver Builder layouts into native Divi 5 block pages, with a check-before-convert report, undo, tests, and a Docker environment.

**Architecture:** A pure-PHP pipeline mirrors the proven Elementor converter: parser (BB serialized node map → nested tree) → `ConverterEngine` dispatching per-node handlers through a registry, with a `StyleMapper` translating BB design settings to Divi 5 attribute paths → `DiviBlockSerializer` emitting `<!-- wp:divi/* -->` blocks → `DiviExporter` writing the post. A preflight/plan/commit trio keeps every write behind an explicit Convert click, and `ImportHistory`/`ImportRollback` make it undoable.

**Tech Stack:** PHP 8.0+ (WordPress plugin, no runtime deps), PHPUnit 13 with WP function stubs, Playwright 1.60 + Docker (WordPress + MySQL) for end-to-end, Divi 5 Deterministic Validator (vendored, pure PHP) as an output gate.

**Spec:** `docs/superpowers/specs/2026-09-08-beaver-to-divi5-converter-design.md`

## Global Constraints

- Plugin slugs/text domains: `jhmg-converter-for-beaver-builder-to-divi` (free), `jhmg-converter-for-beaver-builder-to-divi-pro` (Pro). Namespaces `BeaverDivi5Converter\` / `BeaverDivi5Converter\Pro\`. Constant prefixes `BDC_` / `BDCP_`.
- Requires PHP 8.0, WordPress 5.9; converter output requires Divi ≥ 5.0.0 (`DiviRequirement`).
- Never modify the source Beaver Builder post. Conversions always create a new post.
- Never scrape HTML; read `_fl_builder_data` only. Only published layouts (`_fl_builder_data`, not `_fl_builder_draft`).
- Every handler must call `logUnmappedSettings()`; nothing is dropped silently.
- Colours never invented: unresolved global colour tokens are reported, not substituted.
- Free converts one item per run (`bdc_direct_conversion_limit` default 1); Pro raises it.
- `wp_update_post`/`wp_insert_post` content must be `wp_slash()`ed.
- Version lives in three places (plugin header, `BDC_PLUGIN_VERSION`, readme `Stable tag`) and a test asserts they agree.
- Divi attribute paths only as listed in the spec §5/§6 (verified against Divi 5.7.4 source).

---

## File structure

```
jhmg-beaver-to-divi5/
├── composer.json, phpunit.xml, package.json, playwright.config.ts, test.sh, .gitignore
├── docker-compose.yml
├── scripts/docker/{setup_wp.sh, set-beaver-data.php, import-bb-template.php, convert-run.php, convert-to-new-page.php}
├── scripts/{bb-dat-to-json.php, module-coverage.php}
├── references/  (gitignored: Divi.zip, beaver-builder-lite-version.2.10.3.2.zip)
├── fixtures/
│   ├── beaver/*.json          flat BB node maps (input) — hand-written unit fixtures
│   ├── divi/*.json            expected engine output for each beaver fixture
│   ├── beaver-templates/*.json the 11 bundled BB Lite layouts (from data/*.dat)
│   └── beaver-import/*.xml|dat|json  upload-format fixtures
├── plugin/jhmg-converter-for-beaver-builder-to-divi/
│   ├── jhmg-converter-for-beaver-builder-to-divi.php   bootstrap
│   ├── readme.txt, uninstall.php, languages/, assets/css/frontend.css
│   └── includes/
│       ├── helpers/  class-autoloader, class-plugin, class-divi-requirement, class-color, class-size
│       ├── parsers/  class-beaver-document-parser, class-node-tree, class-beaver-import-parser, class-wxr-reader
│       ├── converter/  class-converter-interface, class-converter-engine, class-base-beaver-converter
│       │   ├── registry/class-converter-registry
│       │   └── handlers/class-*-converter (one per BB node/module)
│       ├── stylemapper/  class-style-mapper, class-global-settings-resolver
│       ├── exporters/  class-divi-block-serializer, class-divi-exporter
│       ├── conversion/ class-conversion-source, class-installed-post-source, class-conversion-plan,
│       │               class-conversion-preflight, class-conversion-committer, class-conversion-outline
│       ├── admin/  class-admin-page, class-direct-conversion-page, class-beaver-page-repository,
│       │           class-batch-importer, class-coverage-panel, class-outline-renderer,
│       │           class-not-carried-over-renderer, class-review-prompt
│       ├── history/  class-import-history, class-import-rollback
│       └── telemetry/ class-coverage-telemetry
├── plugin/jhmg-converter-for-beaver-builder-to-divi-pro/
│   ├── jhmg-converter-for-beaver-builder-to-divi-pro.php, uninstall.php, languages/
│   └── includes/ class-autoloader, class-plugin, admin/class-pro-page, admin/class-themer-repository,
│                 exporters/class-divi-theme-builder-exporter, licensing/class-license-client, licensing/class-license-page
├── tests/  bootstrap.php, *Test.php, e2e/*.spec.ts, support/divi5-validator/*.php
└── docs/  beaver-schema.md, divi5-schema.md, conversion-map.md, conversion-workflow.md, conversion-reporting.md, README.md, RELEASE.md, CLAUDE.md
```

Autoloader rule (same as the Elementor plugin): `BeaverDivi5Converter\Converter\Handlers\HeadingConverter` → `includes/converter/handlers/class-heading-converter.php`; classes directly under the root namespace resolve to `includes/helpers/`.

---

### Task 1: Project scaffold, bootstrap, requirement guard, test harness

**Files:**
- Create: `composer.json`, `phpunit.xml`, `package.json`, `.gitignore`, `test.sh`
- Create: `plugin/jhmg-converter-for-beaver-builder-to-divi/jhmg-converter-for-beaver-builder-to-divi.php`
- Create: `includes/helpers/class-autoloader.php`, `class-plugin.php`, `class-divi-requirement.php`
- Create: `tests/bootstrap.php`, `tests/PluginBootstrapTest.php`, `tests/DiviRequirementTest.php`

**Interfaces:**
- Produces: `BDC_PLUGIN_DIR`, `BDC_PLUGIN_VERSION='1.0.0'`; `BeaverDivi5Converter\Helpers\DiviRequirement::{detected_version,is_satisfied,failure_reason,message,render_notice,on_activation}`; `BeaverDivi5Converter\Plugin::instance()->init()`.
- Test bootstrap globals: `$GLOBALS['__test_posts']`, `__test_postmeta`, `__test_options`, `__test_transients`, `__test_redirects`, `__test_trashed`, `__test_divi_version`; helpers `bdc_test_reset_hooks()`, `bdc_test_reset_divi()`.

- [ ] **Step 1:** `composer.json` with `phpunit/phpunit ^13.2` and PSR-4 `BeaverDivi5Converter\\ → plugin/jhmg-converter-for-beaver-builder-to-divi/includes/`, `BeaverDivi5Converter\\Pro\\ → plugin/…-pro/includes/`, `Divi5Validator\\ → tests/support/divi5-validator/`. Run `composer install`.
- [ ] **Step 2:** Port `tests/bootstrap.php` from the Elementor repo, renaming `edc_` helpers to `bdc_` and the required plugin files. Add `maybe_unserialize()` and `is_serialized()` stubs (WordPress functions the parser relies on).
- [ ] **Step 3:** Write `tests/DiviRequirementTest.php` (satisfied at 5.7.4; `too_old` at 4.27; `missing` when no Divi; activation stores/clears the option). Run: `vendor/bin/phpunit tests/DiviRequirementTest.php` → fails (class missing).
- [ ] **Step 4:** Write bootstrap file + Autoloader + Plugin + DiviRequirement (ported, renamed). Run tests → pass.
- [ ] **Step 5:** `git add -A && git commit -m "chore: scaffold free plugin, requirement guard, test harness"`.

### Task 2: Beaver Builder document parser and node tree

**Files:**
- Create: `includes/parsers/class-beaver-document-parser.php`, `includes/parsers/class-node-tree.php`
- Create: `scripts/bb-dat-to-json.php`, `fixtures/beaver-templates/*.json` (generated), `fixtures/beaver/contact-page.json`
- Test: `tests/BeaverDocumentParserTest.php`, `tests/NodeTreeTest.php`

**Interfaces:**
- `BeaverDocumentParser::parse( array $post_meta ): array` → `['nodes' => array<string,array{node,type,parent,position,settings}>, 'settings' => array]`; also `parseValue( mixed $raw ): array` (string serialized / JSON / array-of-objects).
- `NodeTree::build( array $nodes ): array` → list of root nodes, each `['id','type','parent','position','settings'=>array,'children'=>[…]]`, children sorted by `position`; module nodes have `settings['type']`. Orphans (parent not found) become roots with a warning entry: `NodeTree::build()` returns `['roots'=>[], 'orphans'=>[ids]]`.

- [ ] **Step 1:** Test: parsing a JSON-encoded flat map yields normalised arrays (stdClass gone), `type` and `parent` preserved; serialized string with `stdClass` objects parses; serialized string containing a non-stdClass object becomes `__PHP_Incomplete_Class` and is rejected with empty nodes; empty meta → empty nodes.
- [ ] **Step 2:** Test: `NodeTree::build()` orders roots by position, nests `column-group` under `row`, `column` under group, `module` under column, and box children under the box module; orphan reported.
- [ ] **Step 3:** Implement both classes. Normalisation: `json_decode(json_encode($value), true)` after unserialize with `['allowed_classes'=>['stdClass']]`.
- [ ] **Step 4:** `scripts/bb-dat-to-json.php <dat> <out.json>` — writes `{"nodes":{…},"settings":{…},"name":…}`; generate `fixtures/beaver-templates/*.json` for all 11 layouts.
- [ ] **Step 5:** Run tests → pass. Commit `feat(parser): read Beaver Builder layout data into a node tree`.

### Task 3: Colour, size and global-settings helpers

**Files:**
- Create: `includes/helpers/class-color.php`, `includes/helpers/class-size.php`, `includes/stylemapper/class-global-settings-resolver.php`
- Test: `tests/ColorTest.php`, `tests/SizeTest.php`, `tests/GlobalSettingsResolverTest.php`

**Interfaces:**
- `Color::normalize( mixed $raw ): ?string` — `'64A6BD'`→`'#64A6BD'`, `'#fff'`→`'#fff'`, `'rgba(1,2,3,.5)'` passthrough, `''`/non-string → null, `'var(--fl-global-brand)'` → resolved via `Color::resolveGlobal()` (reads `bdc_global_colors` filter; null when unknown). `Color::isGlobalRef(string): bool`.
- `Size::withUnit( mixed $value, ?string $unit, string $default = 'px' ): string` — `'20'`+`'%'`→`'20%'`; `''`→`''`; numeric-with-unit strings pass through.
- `GlobalSettingsResolver::rowWidth(): string` (`'1100px'` default; reads option `_fl_builder_settings` → `row_width` + `row_width_unit`), `::rowWidthDefault(): 'fixed'|'full'`, `::rowContentWidthDefault()`; all overridable by `bdc_global_settings` filter.

- [ ] Steps: tests first (each helper ≥ 6 cases), implement, run, commit `feat(helpers): colour, size and global-settings helpers`.

### Task 4: StyleMapper

**Files:**
- Create: `includes/stylemapper/class-style-mapper.php`
- Test: `tests/StyleMapperTest.php`

**Interfaces:**
- `StyleMapper::map( string $kind, array $settings ): array{divi_attrs: array, handled_keys: string[]}` where `$kind` ∈ `row | column | heading | text | image | button | blurb | cta | icon | counter | generic`.
- Static: `StyleMapper::columnSizeToFraction( float $percent ): ?string` (100→`4_4`, 50→`1_2`, 33.33→`1_3`, 66.66→`2_3`, 25→`1_4`, 75→`3_4`, 20→`1_5`, 40→`2_5`, 60→`3_5`, 80→`4_5`; ±1.5 tolerance; else null), `StyleMapper::BREAKPOINTS = ['' => 'desktop', '_medium' => 'tablet', '_responsive' => 'phone']`.
- `StyleMapper::fontPathFor( string $kind ): ?string`.

- [ ] **Step 1:** Tests (one method per mapping family): spacing with units and breakpoints; `_large` marked handled but not emitted; column maps padding only; background colour/photo/gradient/overlay/video-fallback; border + shadow; typography (family "Default" and weight "default" ignored, size/lineHeight/letterSpacing with units, `text_align` → textAlign for heading / orientation for text, style flags, text shadow); colour normalisation applied everywhere; min-height; photo width; id/class → `module.advanced.htmlAttributes.desktop.value`; `responsive_display` → `module.decoration.disabledOn.desktop.value` (read the exact key names from `references` Divi `DisabledOn` declaration before writing the test).
- [ ] **Step 2:** Implement, using the Elementor `StyleMapper::transformPath` writer pattern. Run tests → pass. Commit `feat(stylemapper): map Beaver Builder design settings to Divi 5 attributes`.

### Task 5: Engine, registry, structural handlers, serializer, exporter

**Files:**
- Create: `includes/converter/class-converter-interface.php`, `class-converter-engine.php`, `class-base-beaver-converter.php`, `registry/class-converter-registry.php`
- Create handlers: `class-row-converter.php`, `class-column-group-converter.php`, `class-column-converter.php`, `class-generic-fallback-converter.php`
- Create: `includes/exporters/class-divi-block-serializer.php`, `class-divi-exporter.php`
- Fixtures: `fixtures/beaver/{simple-row,two-columns,stacked-groups,nested-columns,unsupported-module,empty-row}.json` + `fixtures/divi/*.json`
- Test: `tests/ConverterEngineTest.php`, `tests/ConverterFixtureTest.php`, `tests/DiviBlockSerializerTest.php`, `tests/DiviExporterTest.php`

**Interfaces:**
- `ConverterInterface::convert( array $node ): array` — returns one block `['id','name','settings','elements']` or a list of blocks.
- `ConverterEngine::convert( array $document ): array{divi: array{elements: array}, unsupported: array, report: array}`; `convertChildren( array $nodes ): array`; `convertNode( array $node ): array`; `logConverted(string)`, `logWarning(string)`, `logSkippedSetting(string)`, `logNotCarriedOver(string $kind, string $node_id, string $detail)`, `logUnresolvedGlobal(string $node_id, string $key, string $ref)`, `flagApproximate(string $node_id, string $module, string $matched_to)`, `getReport()`.
- `ConverterRegistry::register( string $nodeType, $entry )`, `registerModule( string $slug, $entry )`, `getConverter( array $node ): ?ConverterInterface`, `defaultConverter( array $node ): ConverterInterface`, `knownModuleSlugs(): string[]`.
- `BaseBeaverConverter` protected helpers: `convertChildren`, `convertStructureChildren` (column-group child → row, box → group), `deepMergeSettings`, `ensureColumnChildren`, `rowSettingsFromColumns`, `logUnmappedSettings( string $id, array $settings, array $mapped )`, `setting( array $settings, string $key, $default )`, `linkAttrs( array $settings, string $key ): array` (url/target/rel).
- Row → `divi/section` with one `divi/row` per column-group; `DiviBlockSerializer::serialize( array $divi_data ): string`; `DiviExporter::export( array ): array` (meta) and `save( int $post_id, array $divi_data ): bool` (wp_slash on content, clears Divi caches).

- [ ] **Step 1:** Fixture pairs written by hand (the beaver side uses the real BB key names; ids like `row-1`, `group-1`, `col-1`, `mod-1`).
- [ ] **Step 2:** Tests: fixture equality (structural only), unsupported module leaves a `divi/code` placeholder with its text and is counted unsupported, empty row emits a warning, serializer wraps in `divi/placeholder` and injects `builderVersion`, exporter writes `_et_pb_use_divi_5=on` and slashed content.
- [ ] **Step 3:** Implement. Run → pass. Commit `feat(engine): convert rows, column groups and columns into Divi 5 sections, rows and columns`.

### Task 6: Content handlers — Lite basics

**Files:** handlers `class-heading-converter.php`, `class-rich-text-converter.php`, `class-photo-converter.php`, `class-button-converter.php`, `class-button-group-converter.php`, `class-html-converter.php`, `class-video-converter.php`, `class-audio-converter.php`, `class-sidebar-converter.php`; fixtures `heading, rich-text, photo, photo-caption, button, button-group, html, video-embed, video-file, audio, sidebar`; `tests/ConverterFixtureTest.php` provider extended.

- [ ] Write fixtures + expected; implement each handler; run; commit `feat(handlers): heading, text, photo, button, html, video, audio, sidebar`.

### Task 7: Content handlers — Lite composites, placeholders, Pro-module approximations

**Files:** `class-icon-converter.php`, `class-callout-converter.php`, `class-cta-converter.php`, `class-numbers-converter.php`, `class-star-rating-converter.php`, `class-menu-converter.php`, `class-box-converter.php`, `class-separator-converter.php`, `class-accordion-converter.php`, `class-tabs-converter.php`, `class-testimonials-converter.php`, `class-pricing-table-converter.php`, `class-contact-form-converter.php`, `class-subscribe-form-converter.php`, `class-map-converter.php`, `class-gallery-converter.php`, `class-content-slider-converter.php`, `class-posts-converter.php`, `class-countdown-converter.php`, `class-icon-group-converter.php`, `class-social-buttons-converter.php`, `class-login-form-converter.php`, `class-search-converter.php`; fixtures for each Lite one plus `separator`, `accordion`, `tabs`; `tests/ProModuleApproximationTest.php` (every Pro handler survives an empty settings array and an unexpected-shape array, and flags approximate).

- [ ] Implement, test, commit `feat(handlers): icon, callout, cta, numbers, menu, box, star rating and Beaver Builder Pro module approximations`.

### Task 8: Report completeness, not-carried-over, bundled-template smoke tests with the Divi 5 validator

**Files:**
- Modify: `class-converter-engine.php` (`recordNotCarriedOver()` for `animation`, `visibility_display`, shape/transform keys, bg video/slideshow/parallax, lightbox, layout css/js)
- Create: `tests/support/divi5-validator/` (copy of `../Divi 5 Deterministic Validator/src/*.php`), `tests/ConversionReportTest.php`, `tests/BundledTemplateConversionTest.php`

- [ ] Smoke test: for each `fixtures/beaver-templates/*.json`: convert, serialize, `Divi5Validator\Validator::validateContent()` is valid (ignoring `MULTIPLE_H1`), every non-empty `heading`/`text`/`button text` string appears in the serialized output, section count equals BB root row count.
- [ ] Commit `test: prove every bundled Beaver Builder layout converts to valid Divi 5`.

### Task 9: Conversion pipeline (preflight / plan / commit / outline)

**Files:** `includes/conversion/*` (six classes, ported and renamed), tests `ConversionPlanTest`, `ConversionPreflightTest` (write-nothing invariant), `ConversionCommitterTest`, `ConversionOutlineTest`, `InstalledPostSourceTest` (reads `_fl_builder_data`, rejects posts without published data, Themer post types carry template_type).

- [ ] Commit `feat(conversion): preflight, plan, commit and outline`.

### Task 10: Upload intake (WXR / .dat / JSON) and batch importer

**Files:** `includes/parsers/class-wxr-reader.php`, `class-beaver-import-parser.php`, `includes/admin/class-batch-importer.php`; fixtures `fixtures/beaver-import/{export.xml, two-pages.xml, template.dat, tree.json, entity.xml}`; tests `WxrReaderTest`, `BeaverImportParserTest`, `BatchImporterTest`.

**Interfaces:** `BeaverImportParser::parse( string $file_path, string $file_name = '' ): array` → items `['title','post_type','post_name','template_type','nodes','settings']`; throws `RuntimeException` for unreadable/unrecognised/entity-bearing input.

- [ ] Commit `feat(import): read Beaver Builder exports (WXR, .dat, JSON)`.

### Task 11: Admin screens, history, rollback, telemetry, review prompt, readme

**Files:** `includes/admin/*`, `includes/history/*`, `includes/telemetry/*`, `readme.txt`, `uninstall.php`, `languages/index.php`, `assets/css/frontend.css`; tests `BeaverPageRepositoryTest`, `DirectConversionPageTest`, `DirectConversionRenderTest`, `OutlineRendererTest`, `NotCarriedOverTest`, `ImportHistoryTest`, `ImportRollbackTest`, `CoverageTelemetryTest`, `CoveragePanelTest`, `ReviewPromptTest`, `ReleaseMetadataTest`.

- [ ] Port from the Elementor plugin with `edc`→`bdc`, Elementor→Beaver Builder copy, `_elementor_edit_mode=builder` → `_fl_builder_enabled=1`, post types from `BeaverPageRepository::postTypes()`. Commit `feat(admin): Tools screen, check-before-convert, undo, coverage, telemetry`.

### Task 12: Pro add-on

**Files:** `plugin/jhmg-converter-for-beaver-builder-to-divi-pro/**`; tests `ProPluginTest`, `LicenseClientTest`, `ThemerRepositoryTest`, `ThemeBuilderExporterTest`, `ThemeBuilderDedupeTest`.

- [ ] `LicenseClient` copied verbatim from the canonical source (namespace changed only). `DiviThemeBuilderExporter` ported with `_bdc_tb_source`. `ProPage` tabs: License, Themer. Commit `feat(pro): licensed add-on with unlimited runs and Themer header/footer export`.

### Task 13: Docker environment, WP-CLI helpers, Playwright e2e

**Files:** `docker-compose.yml`, `scripts/docker/*`, `playwright.config.ts`, `tests/e2e/{divi-integration.spec.ts, conversion-workflow.spec.ts}`.

- [ ] `docker compose up -d`, `scripts/docker/setup_wp.sh`; `npm test`. Fix what fails. Commit `test(e2e): Docker WordPress with Divi 5 + Beaver Builder, Playwright coverage`.

### Task 14: Documentation and release metadata

**Files:** `docs/beaver-schema.md`, `docs/divi5-schema.md`, `docs/conversion-map.md`, `docs/conversion-workflow.md`, `docs/conversion-reporting.md`, `README.md`, `RELEASE.md`, `CLAUDE.md`, `scripts/module-coverage.php`.

- [ ] Commit `docs: schemas, conversion map, workflow, release checklist`.
