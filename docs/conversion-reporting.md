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
| `skipped_settings` | Setting keys no handler consumed (`node: key`). Bookkeeping keys and `_large` breakpoints are excluded. |
| `unresolved_globals` | Beaver Builder global colours the site could not resolve; the property was left unset, never guessed. |
| `not_carried_over` | Things Divi cannot express, by kind: `animation`, `visibility`, `shapes`, `background`, `lightbox`, `hover`, `interaction`, `integration`, `custom_code`. |
| `unsupported` | Modules with no handler; each left a labelled placeholder. |
| `quality.module_coverage` | converted ÷ (converted + approximate + unsupported), percent. |

Read it back: `wp post meta get <id> _bdc_conversion_report`.
