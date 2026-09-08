# Conversion workflow

```
Beaver Builder post (_fl_builder_data)          upload (.xml / .dat / .json)
        │  InstalledPostSource                            │  BeaverImportParser
        └──────────────────────┬─────────────────────────┘
                               ▼
                     ConversionPreflight  ── writes nothing; one fresh ConverterEngine per item
                               │
                          ConversionPlan  ── blocks, serialized content, report, outline
                               │  (the "Check this page" screen renders this)
                               ▼
                     ConversionCommitter  ── the only class that creates posts
                               │
                     DiviExporter.save()  ── block content + Divi meta, wp_slash()ed
                               │
                       ImportHistory      ── recorded per run, undoable via ImportRollback
```

## In the admin

Tools → Beaver Builder → Divi 5: pick a page → **Check this page** → report → **Convert to Divi 5** →
results (Edit / View / Publish) → Recent conversions (Undo).

## From the command line (Docker)

```bash
scripts/docker/setup_wp.sh                      # boot everything, seed and convert a fixture
WP=$(docker compose ps -q wordpress)
docker exec -i $WP bash -lc "TEMPLATE=layout-03-Home-lite wp eval-file /tmp/import-bb-template.php --allow-root"   # → source id
docker exec -i $WP bash -lc "SOURCE_PAGE_ID=<id> wp eval-file /tmp/convert-to-new-page.php --allow-root"            # → new id
```

Other helpers: `set-beaver-data.php` (attach a fixture JSON to a page), `convert-run.php` (convert in place).

## Output post

| Key | Value |
|---|---|
| `post_content` | `<!-- wp:divi/placeholder -->…` block markup |
| `_et_pb_use_builder`, `_et_pb_use_divi_5` | `on` |
| `_et_builder_version` | `VB\|Divi\|<version>` |
| `_bbdc_divi_data` | the intermediate block tree (JSON) |
| `_bbdc_conversion_report` | the report (JSON) |
| `_bbdc_import_source` | `direct` or `file_upload` |
| `_bbdc_source_post_id` | the Beaver Builder post it came from (direct conversions) |
