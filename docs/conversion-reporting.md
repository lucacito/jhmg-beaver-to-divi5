# Conversion reporting

Every conversion produces a report, shown on the check screen and the results screen and stored in
`_bdc_conversion_report` on the new post.

```json
{
  "converted":           { "section": 3, "row": 3, "column": 6, "heading": 3, "text": 3, "button": 3, "code": 1 },
  "approximate":         { "accordion": 1 },
  "approximate_matches": [ { "node_id": "5e3b…", "module": "accordion", "matched_to": "AccordionConverter" } ],
  "warnings":            [ "Image missing alt text: 5e3b…" ],
  "skipped_settings":    [ "5e3b…: mystery_key" ],
  "unresolved_globals":  [ { "node_id": "…", "setting_key": "bg_color", "ref": "var(--fl-global-brand)" } ],
  "not_carried_over":    [ { "kind": "animation", "node_id": "…", "detail": "fade-in" } ],
  "quality":             { "module_coverage": 92, "settings_issues": 1 },
  "unsupported":         [ { "id": "…", "type": "module", "module": "acf-block" } ]
}
```

| Field | Meaning |
|---|---|
| `converted` | Count per Divi module produced by a source-verified handler. |
| `approximate` / `approximate_matches` | Beaver Builder Pro modules handled from documentation. Counted in the denominator of coverage, not the numerator. |
| `warnings` | Non-fatal issues: empty rows/columns, missing alt text, missing sources, layouts that had to be approximated. |
| `skipped_settings` | Setting keys no handler consumed (`node: key`). Excluded: bookkeeping keys, `_large` breakpoints, compound values that only hold form scaffolding (units, `Default` fonts, a gradient with no colours), toggles that are off (`no`, `none`, `off`, `false`), Beaver's runtime flags and editor scratch keys, and add-on families at their defaults (see below). |
| `field_connections` | Number of Beaver Themer `[wpbb …]` connections rewritten as Divi dynamic content. Connections Divi cannot express appear under `not_carried_over` (kind `integration`). |
| `addon_settings_ignored` | Ultimate Addons / PowerPack setting families found at their defaults, as `label => number of rows/columns`. Nothing was lost; the count explains why the page carried so many settings the converter did not need. An add-on feature that is switched on appears under `not_carried_over` (kind `addon`) instead, once per node. |
| `unresolved_globals` | Beaver Builder global colours the site could not resolve; the property was left unset, never guessed. |
| `not_carried_over` | Things Divi cannot express, by kind: `animation`, `visibility`, `shapes`, `background`, `lightbox`, `hover`, `interaction`, `integration`, `custom_code`, `addon` (an Ultimate Addons / PowerPack feature that was switched on). |
| `unsupported` | Modules with no handler; each left a labelled placeholder. |
| `quality.module_coverage` | converted ÷ (converted + approximate + unsupported), percent. |

Read it back: `wp post meta get <id> _bdc_conversion_report`.
