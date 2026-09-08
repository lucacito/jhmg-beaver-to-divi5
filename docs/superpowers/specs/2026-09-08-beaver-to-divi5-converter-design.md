# Beaver Builder → Divi 5 Converter — Design

Date: 2026-09-08
Status: approved for implementation (autonomous build; decisions recorded here)
Sibling project this is modelled on: `../jhmg-elementor-to-divi5` (free 3.0.0 + Pro 1.2.0)

## 1. Goal

A WordPress plugin pair that converts pages built with Beaver Builder into native
Divi 5 pages, the same way the Elementor converter does for Elementor:

- **Free** — `jhmg-converter-for-beaver-builder-to-divi`: convert one installed
  Beaver Builder page per run (unlimited runs), or one page from an uploaded
  export; a "Check this page" report before anything is written; every run
  undoable.
- **Pro add-on** — `jhmg-converter-for-beaver-builder-to-divi-pro`: convert many
  pages in one run, convert Beaver Themer header/footer layouts straight into the
  Divi Theme Builder, licensed updates from divi5lab.com.

The converter transforms Beaver Builder's **stored layout data** into Divi 5's
**block attribute structure**. It never scrapes rendered HTML.

```
_fl_builder_data (serialized node map)
        │  BeaverDocumentParser + NodeTree
        ▼
nested node tree  (row → column-group → column → module)
        │  ConverterEngine + registry of per-node handlers + StyleMapper
        ▼
Divi 5 block tree  (divi/section → divi/row → divi/column → divi/*)
        │  DiviBlockSerializer + DiviExporter
        ▼
new Divi 5 page  (post_content = <!-- wp:divi/... --> blocks, Divi meta)
```

## 2. Naming, identifiers, extension points

| Thing | Free | Pro |
|---|---|---|
| Plugin dir / slug / text domain | `jhmg-converter-for-beaver-builder-to-divi` | `jhmg-converter-for-beaver-builder-to-divi-pro` |
| PHP namespace | `BeaverDivi5Converter\` | `BeaverDivi5Converter\Pro\` |
| Constants | `BDC_PLUGIN_FILE`, `BDC_PLUGIN_DIR`, `BDC_PLUGIN_URL`, `BDC_PLUGIN_VERSION` | `BDCP_*`, `BDCP_PRODUCT_SLUG = beaver-to-divi5-pro`, `BDCP_API_BASE` |
| Admin screen | `tools.php?page=bdc-converter` ("Beaver Builder → Divi 5") | `tools.php?page=bdcp-pro` |
| Version | 1.0.0 | 1.0.0 |

Hooks the free plugin exposes (Pro consumes them):
`bdc_loaded`, `bdc_pro_active`, `bdc_direct_conversion_limit`,
`bdc_theme_builder_exporter`, `bdc_global_settings`.

Post meta written on converted posts: `_bdc_divi_data`, `_bdc_conversion_report`,
`_bdc_import_source` (`direct` | `file_upload`), `_bdc_source_post_id`, plus the
Divi meta (`_et_pb_use_builder=on`, `_et_pb_use_divi_5=on`, `_et_builder_version`).

Options: `bdc_import_history`, `bdc_telemetry_consent`, `bdc_telemetry_last_sent`,
`bdc_divi_requirement_failed`, review-prompt user meta. Telemetry product id:
`beaver-to-divi5`. Product page: `https://divi5lab.com/plugins/beaver-builder-to-divi-5`.

## 3. Source format: what Beaver Builder stores (verified against BB Lite 2.10.3.2)

- Builder flag: post meta `_fl_builder_enabled` = `1`.
- Layout: post meta `_fl_builder_data` = PHP-serialized **flat map** `node_id ⇒ stdClass`.
  Draft work lives in `_fl_builder_draft` and is ignored (only published layouts convert).
- Layout-level CSS/JS: `_fl_builder_data_settings` (`css`, `js`) — carried into the report as
  "custom CSS/JS not converted", not emitted.
- Node object: `node` (13-hex id), `type` ∈ `row | column-group | column | module`,
  `parent` (node id or null), `position` (int), `settings` (stdClass).
- Hierarchy: `row` (root, parent null) → `column-group` → `column` → `module`.
  A `column` may also contain a `column-group` (nested columns). The Lite `box`
  module is a container: its children carry `parent = <box node id>`.
- Module slug: `settings.type` (e.g. `heading`, `rich-text`, `photo`, `button`).
- Responsive suffixes: none = desktop, `_large` (≤1200), `_medium` (≤992),
  `_responsive` (≤768). Units live in a sibling key: `padding_top` + `padding_unit`,
  `min_height` + `min_height_unit`, `padding_top_medium` + `padding_medium_unit`.
- Colors are hex **without** `#` (`"64A6BD"`) or `rgba(...)` strings.
- Compound fields (arrays): `typography` {font_family, font_weight, font_size{length,unit},
  line_height{length,unit}, letter_spacing{length,unit}, text_align, text_transform,
  text_decoration, font_style, font_variant, text_shadow{color,horizontal,vertical,blur}};
  `border` {style, color, width{top,right,bottom,left}, radius{top_left,top_right,
  bottom_left,bottom_right}, shadow{color,horizontal,vertical,blur,spread}};
  gradients {type, angle, position, colors[], stops[]}.
- Photo fields: `<name>` = attachment id, `<name>_src` = URL (`photo`/`photo_src`,
  `bg_image`/`bg_image_src`). Link fields: `<name>`, `<name>_target`, `<name>_nofollow`.
- Templates: post type `fl-builder-template` (same meta). Themer (Pro): post type
  `fl-theme-layout`, meta `_fl_theme_layout_type` ∈ header | footer | … (same layout meta).
- Bundled layout templates ship in `data/layout-*-lite.dat`: serialized
  `['layout' => [ {name, nodes, settings, …} ]]`. These are real BB layouts and are
  used as fixtures.
- Export: WordPress WXR XML (Tools → Export, or BB's own template export) — the
  `_fl_builder_data` meta value appears serialized inside `<wp:meta_value>`.

Full field reference: `docs/beaver-schema.md`.

## 4. Structural mapping

| Beaver Builder | Divi 5 | Notes |
|---|---|---|
| `row` | `divi/section` | background, padding, min-height, text colour, id/class live here (BB paints them on `.fl-row-content-wrap`). |
| `column-group` (child of row) | `divi/row` | one `divi/row` per group, so a BB row with two stacked groups becomes a section holding two rows. |
| `column` | `divi/column` | `size` % → Divi fraction (`module.advanced.type`) + `columnStructure` on the row; non-standard sizes fall back to a flex width on the column. |
| `column-group` inside a `column` | `divi/row` inside the column | Divi 5 allows column → row → column nesting (verified in the validator project). |
| `module` | see §5 | |
| `box` module | `divi/group` | children converted structure-aware; flex direction/gap approximated. |
| row `width` = full + `content_width` = full | row `innerSizing`/sizing 100% | fixed rows get `max_content_width` or global `row_width` (default 1100px) as row max-width. |
| row `min_height`, `content_alignment` | on the `divi/row` (`module.decoration.sizing.minHeight`, layout alignItems) | same placement the Elementor converter settled on. |
| empty row / column | emitted with a warning | never dropped silently. |

## 5. Module mapping

Lite modules (schema read from source — exact):

| BB module | Divi 5 | Content keys carried |
|---|---|---|
| `heading` | `divi/heading` | `heading`, `tag` → headingLevel, `link`+target+nofollow, `color`, `typography` (+medium/responsive) |
| `rich-text` | `divi/text` | `text` (HTML passthrough), `color`, `typography` |
| `photo` | `divi/image` (+ `divi/text` caption when `show_caption`=below) | `photo_src` / `photo_url`, alt from `data.alt`→caption→title, `link_url` (+target), `width`, `align`, `border` (incl. shadow), `crop`=circle → 50% radius |
| `button` | `divi/button` | `text`, `link`(+target/nofollow), `bg_color`, `text_color`, `border`, `padding_*`, `align`, `typography`, icon (`icon` FA class → button icon when it is a Font Awesome class) |
| `button-group` | list of `divi/button` | each `items[]` entry |
| `html` | `divi/code` | `html` verbatim |
| `video` | `divi/video`, or `divi/code` when the embed is not YouTube/Vimeo/a file URL | `data.url` / `embed_code`, `poster_src` → overlay image |
| `audio` | `divi/audio` | first `audios[]` attachment URL or `link` |
| `sidebar` | `divi/sidebar` | `sidebar` → `sidebar.innerContent…area` |
| `icon` | `divi/icon` (no text) or `divi/blurb` (with `text`) | `icon` (FA class → Divi icon), `size`, `color`, `align`, `link` |
| `callout` | `divi/blurb` (+ `divi/button` when `cta_type`=button) | `title`/`title_tag`, `text`, photo or icon, `link`, `cta_text` |
| `cta` | `divi/cta` | `title`, `text`, `btn_text`, `btn_link`(+target), colours |
| `numbers` | `divi/number-counter` (`layout` default/bars) or `divi/circle-counter` (`layout`=circle) | `number`, prefix/suffix, `before_number_text` as title, colours |
| `star-rating` | `divi/text` | unicode stars from `rating`/`total` |
| `menu` | `divi/menu` | `menu` slug → nav_menu term id (`menu.advanced.menuId`) |
| `box` | `divi/group` | see §4 |
| `widget`, `reusable-block`, `acf-block` | `divi/code` placeholder | reported as unsupported; text preserved when any |

Pro modules (source not available locally; field names come from Beaver Builder's public
documentation and are **flagged approximate** in the report — the handler reads several
candidate keys and never throws):
`separator`→`divi/divider`, `accordion`→`divi/accordion`, `tabs`→`divi/tabs`,
`testimonials`→`divi/testimonial` (first) , `pricing-table`→`divi/pricing-tables`,
`contact-form`→`divi/contact-form`, `subscribe-form`→`divi/signup`, `map`→`divi/map`,
`slideshow`/`gallery`→`divi/gallery`, `content-slider`→`divi/slider`,
`posts`/`post-grid`/`post-slider`/`post-carousel`→`divi/blog`, `countdown`→`divi/countdown-timer`,
`icon-group`→`divi/blurb` list, `social-buttons`→`divi/social-media-follow`,
`login-form`→`divi/login`, `search`→`divi/search`, `number-counter`→`divi/number-counter`.

Anything else → `GenericFallbackConverter`: a `divi/code` block holding an HTML comment
naming the module plus any text it carried; reported as unsupported.

## 6. Design settings (StyleMapper)

`StyleMapper::map( string $node_kind, array $settings ): ['divi_attrs','handled_keys']`.
Breakpoints: `''→desktop`, `_medium→tablet`, `_responsive→phone`; `_large` is
acknowledged (marked handled) but not emitted — Divi 5 has no matching breakpoint.

| BB setting | Divi 5 path |
|---|---|
| `margin_*`/`padding_*` (+unit, +breakpoint) | `module.decoration.spacing.{bp}.value.{margin,padding}` (columns: padding only) |
| `bg_type=color`, `bg_color` | `module.decoration.background.{bp}.value.color` |
| `bg_type=photo`, `bg_image_src`/`bg_image_url`, `bg_repeat`, `bg_position`, `bg_size`, `bg_attachment` | `…background.{bp}.value.image.{url,repeat,position,size}` |
| `bg_type=gradient`, `bg_gradient` | `…background.desktop.value.gradient.{enabled,type,direction,stops}` |
| `bg_overlay_type` color/gradient over a photo | `…background.desktop.value.gradient` with `overlaysImage=on` (colour overlay → 2-stop gradient of the same colour) |
| `bg_type` video/slideshow/parallax | photo fallback (`bg_video_fallback_src` / first `ss_photos` / `bg_parallax_image_src`) + "not carried over: background" |
| `border` {style,color,width,radius} | `module.decoration.border.{bp}.value.{styles.all.*, radius}` |
| `border.shadow` | `module.decoration.boxShadow.{bp}.value` |
| `typography` | `<font path>.{bp}.value.{family,weight,size,lineHeight,letterSpacing,textAlign,style[],textShadow}` — font path per module (heading `title.decoration.font.font`, text `content.decoration.bodyFont.body.font`, button `button.decoration.font.font`, blurb title/body, cta title/body) |
| `color` / `text_color` | `<font path>.desktop.value.color` |
| `align` | heading → `textAlign`; button → `module.advanced.alignment`; image → `module.advanced.align`; icon → `icon.advanced.align`; others → `module.advanced.text.text.{bp}.value.orientation` |
| `min_height` (+unit/bp) | `module.decoration.sizing.{bp}.value.minHeight` |
| `width` (photo) | `module.decoration.sizing.{bp}.value.width` |
| `id`, `class` | `module.advanced.htmlAttributes.desktop.value.{id,class}` |
| `responsive_display` | `module.decoration.disabledOn.desktop.value` (shape read from Divi's `DisabledOn` declaration at implementation) |
| `animation`, `visibility_display` (logged in/out), edge shapes/transforms, hover colours, lightbox links, `container_element`, `node_label`, `responsive_order`, `equal_height` | not emitted; the first four are listed under "Not carried over", the rest are acknowledged silently |

Colours are normalised by `Color::normalize()`: 3/4/6/8-digit hex gains `#`,
`rgb(a)` passes through, `var(--fl-global-…)` global colour tokens are resolved from
Beaver Builder's global colour presets when the site has them and otherwise reported as
unresolved (never replaced with an invented colour).

Global settings (`bdc_global_settings` filter; `GlobalSettingsResolver`): read
Beaver Builder's `_fl_builder_settings` option when present (row width, default row
width mode); fall back to BB's documented defaults (row width 1100px, fixed). Only
the row width is applied; per-node defaults (row padding 20px, module margins 20px)
are deliberately not injected — Divi's own defaults take over.

## 7. Engine, registry, reporting

Same shape as the Elementor engine, minus the settings-shape guessing:

- `ConverterEngine::convert( array $document )` accepts the flat node map
  (`['nodes'=>…]`) or an already nested tree; returns
  `['divi'=>['elements'=>[…]], 'unsupported'=>[…], 'report'=>[…]]`.
- `ConverterRegistry` keys: `row`, `column-group`, `column`, `module:<slug>`; entries are
  class names or factory closures; `defaultConverter()` returns the generic fallback.
- Report: `converted` (per Divi module), `approximate` + `approximate_matches` (Pro-module
  handlers whose field names are unverified), `warnings`, `skipped_settings`
  (`"<node>: <key>"`), `unresolved_globals`, `not_carried_over` (kinds: `animation`,
  `visibility`, `shapes`, `background`, `lightbox`, `custom_code`), `quality`.
- Every handler calls `logUnmappedSettings()` so any key it did not consume is reported.

## 8. Intake

- **Direct** (`InstalledPostSource`): posts where `_fl_builder_enabled = 1`; post types =
  BB's enabled post types (`_fl_builder_post_types` option, default page) ∪
  `page, post, fl-builder-template, fl-theme-layout`. `BeaverDocumentParser` accepts the
  value as WordPress returns it (array of stdClass), as a serialized string (unserialized
  with `allowed_classes => ['stdClass']`), or as JSON (fixtures). Objects are normalised to
  arrays. `fl-theme-layout` posts carry `template_type` header/footer from
  `_fl_theme_layout_type`.
- **Upload** (`BeaverImportParser`): WXR XML (every `<item>` whose postmeta has
  `_fl_builder_data`; DOM parsing with `LIBXML_NONET`, documents containing `<!DOCTYPE`
  or `<!ENTITY` are refused), BB `.dat` template packs, and JSON trees. The free plugin
  converts the first item and reports truncation; Pro converts all
  (`bdc_direct_conversion_limit` governs both paths).

## 9. Admin flow (free)

Tools → Beaver Builder → Divi 5. Landing page: "Convert a page already on this site"
picker (search, paging, "already converted" badge) → **Check this page** → conversion
report (outline, module counts, could-not-convert list, not-carried-over) → **Convert to
Divi 5** → batch result screen (per page: view/edit/publish links, issues) → recorded in
`ImportHistory` (25 runs) with one-click **Undo** (trash only, ownership-guarded);
upload form (XML / .dat / .json, post type + status); coverage panel with opt-in weekly
telemetry of unsupported module names; review prompt after three clean runs; Pro card.
`DiviRequirement` hides every control and explains itself when Divi 5 is missing.

## 10. Pro add-on

- Raises `bdc_direct_conversion_limit` to unlimited.
- `DiviThemeBuilderExporter` (ported from the Elementor Pro): header/footer layouts →
  `et_header_layout`/`et_footer_layout` + `et_template` + Theme Builder container, keyed by
  source so re-imports update instead of duplicating. Registered through
  `bdc_theme_builder_exporter`.
- Themer tab: lists `fl-theme-layout` posts of type header/footer, converts them into the
  Theme Builder in one click.
- License tab backed by the canonical JHMG `LicenseClient` (soft enforcement: notices
  and update delivery only). Product `beaver-to-divi5-pro`, option prefix `bdcp`.

## 11. Testing

- **PHPUnit** (WP function stubs in `tests/bootstrap.php`, ported): parser tests, node-tree
  tests, per-handler fixture tests (`fixtures/beaver/*.json` → `fixtures/divi/*.json`),
  StyleMapper tests, engine/report tests, preflight write-nothing test, committer,
  repository, direct-conversion page, import history/rollback, telemetry, release
  metadata (version in three places, readme disclosures), Pro tests.
- **Bundled-template smoke tests**: every BB Lite `layout-*.dat` converts without
  exception, keeps every heading/text/button string, and the serialized output passes the
  Divi 5 Deterministic Validator (the validator's `src/` is vendored into
  `tests/support/divi5-validator/`).
- **Playwright** against the Docker site (`localhost:8010`): fixture pages converted via
  WP-CLI helper scripts; Divi recognises the page; frontend shows sections, headings,
  buttons, images; report meta has the expected counts; no JS errors.

## 12. Local environment

`docker-compose.yml`: `wordpress:php8.3-apache` on port 8010 + `mysql:8.0`. Mounts both
plugin dirs, `fixtures/`, and the two reference zips (`references/Divi.zip` — Divi 5.12.1 —
and `references/beaver-builder-lite-version.2.10.3.2.zip`, both gitignored).
`scripts/docker/setup_wp.sh` installs WP-CLI, core (admin/admin), Divi from the zip
(activated), Beaver Builder Lite (activated), both converter plugins, then seeds and
converts a fixture page. Helper scripts run through `wp eval-file`:
`set-beaver-data.php` (fixture JSON → serialized `_fl_builder_data` + enabled flag),
`import-bb-template.php` (bundled `.dat` → page), `convert-run.php`, `convert-to-new-page.php`.

## 13. Out of scope for 1.0.0

Beaver Builder global styles/colour presets beyond row width; Themer parts, archive,
singular and 404 layouts; WooCommerce modules; Beaver Builder Pro module *styling*
(content is carried, styling only where field names are certain); visual pixel preview.
