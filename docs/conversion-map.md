# Conversion map

| Beaver Builder | Divi 5 | Fidelity |
|---|---|---|
| row | `divi/section` (+ one `divi/row` per column group) | exact; min-height and vertical alignment placed on the row; fixed width → row `maxWidth` |
| column-group | `divi/row` with `columnStructure` | exact for standard fractions; otherwise one flexed column of `divi/group`s with percentage widths |
| column | `divi/column` | exact |
| nested column-group | `divi/row` inside the column | exact (Divi 5 nesting) |
| box | `divi/group` (flex) | direction and gap kept; grid/z-stack approximated |
| heading | `divi/heading` | exact |
| rich-text | `divi/text` | exact (wpautop applied like Beaver Builder) |
| photo | `divi/image` (+ `divi/text` caption) | exact; circle crop → radius; other crops use the original image |
| button | `divi/button` | exact incl. icon; hover colours reported |
| button-group | nested `divi/row` › flexed `divi/column` › `divi/button`s | inline layout kept |
| html | `divi/code` | exact |
| video | `divi/video` (YouTube/Vimeo/file) or `divi/code` (other embeds) | exact |
| audio | `divi/audio` | first track |
| sidebar | `divi/sidebar` | exact |
| icon | `divi/icon`, or `divi/blurb` when it has text | exact glyph via the FA map |
| callout | `divi/blurb` (+ `divi/button`) | exact; CTA link appended to the body |
| cta | `divi/cta` | exact |
| numbers | `divi/number-counter` / `divi/circle-counter` / `divi/counters` | exact per layout |
| star-rating | `divi/text` (Unicode stars) | approximation |
| menu | `divi/menu` | same menu; layout variants reported |
| widget, reusable-block, acf-block | `divi/code` placeholder | text kept, reported |
| separator (Pro) | `divi/divider` | approximate |
| accordion / tabs (Pro) | `divi/accordion` / `divi/tabs` | approximate |
| testimonials (Pro) | `divi/testimonial` per entry | approximate |
| pricing-table (Pro) | `divi/pricing-tables` | approximate |
| contact-form (Pro) | `divi/contact-form` with fields | approximate |
| subscribe-form (Pro) | `divi/signup` | approximate; provider connection reported |
| map (Pro) | `divi/code` (Google Maps embed) | approximate |
| gallery / slideshow (Pro) | `divi/gallery` | approximate |
| content-slider (Pro) | `divi/slider` | approximate |
| posts, post-grid, post-slider, post-carousel (Pro) | `divi/blog` | approximate |
| countdown (Pro) | `divi/countdown-timer` | approximate |
| icon-group (Pro) | nested `divi/row` › flexed column › `divi/icon`s | approximate |
| social-buttons (Pro) | `divi/social-media-follow` | approximate; share vs follow reported |
| login-form (Pro) | `divi/login` | approximate |
| search (Pro) | `divi/search` | approximate |
| list (Pro, 2.6+) | `ul`/`ol` → one `divi/text`; `div` (icon list) → one `divi/blurb` per item, icon left, item heading as blurb title | approximate; fields from exported layouts |
| progress-bar (Pro) | `divi/counters` › one `divi/counter` per bar (gradient/colour fill, track colour, thickness, radius); circular → `divi/circle-counter` | approximate; a bar with no number is drawn full (Beaver's counter default) and reported; the percent label is hidden on bars thinner than 18px |
| pp-heading (PowerPack Advanced Heading) | prefix `divi/text` + `divi/heading` (secondary title inline) + `divi/divider` / `divi/icon` separator + sub-title `divi/text` | approximate; each piece goes through the Lite handler |
| pp-iconlist (PowerPack Icon List) | one `divi/blurb` per item, icon left | approximate |
| pp-fluent-form (PowerPack Fluent Forms) | `divi/code` with `[fluentform id="…"]` + PowerPack's form styling as CSS on Fluent Forms' markup; custom title/description → heading + text | approximate; needs Fluent Forms on the Divi site |
| anything else | `divi/code` placeholder | reported as unsupported |

Design settings: see `docs/divi5-schema.md` for every attribute path and the spec §6 for the mapping table.

## Layout defaults carried from Beaver Builder

Beaver Builder's box model is reproduced instead of Divi's defaults, because the two differ everywhere a
setting is left blank:

| Beaver Builder | Divi 5 output |
|---|---|
| Row padding — global setting, 20px all round by default (`_fl_builder_settings` → `row_padding*`) | `divi/section` padding on every side the row leaves blank (Divi's own default is 54px top/bottom) |
| Column group — no spacing | `divi/row` padding `0` (Divi's default is 27px top/bottom); the same reset is applied to the nested rows built for button/icon groups and to wrapper rows |
| Column padding — global setting, blank by default | `divi/column` padding on blank sides when the site set one |
| Column margin — sits on `.fl-col-content`, inside the column's width slot | carried as column **padding** (a Divi column margin would change its share of the row); for flex-group fallbacks the margin stays a margin and the flex basis becomes `calc(<pct>% - margins)` |
| Module margins — global setting, 20px all round by default; `.fl-module`'s clearfix stops them collapsing | module margin on every blank side; the column's `rowGap` is `0` so stacked modules sit exactly margin + margin apart. Blocks a composite handler delegates (pieces of one PowerPack heading) carry only their explicit spacing; the source module's margins land on the first/last block |
| Column width | `module.advanced.type` fraction **and** `module.decoration.sizing.flexType` on Divi 5's 24-grid (`1_3` → `8_24`, `1_6` → `4_24`, fifths as `n_5`); without `flexType` Divi 5.12 renders a column as `24_24` and it shrinks to its content |
| Row/column `text_color` | body text, links (`bodyFont.link`) and headings inside text modules (`headingFont.h1…h6`), exactly as Beaver's row CSS does; `link_color` / `heading_color` override each |
| Rich-text `color` | body, links and headings (`.fl-rich-text *`) |

## Add-on settings (Ultimate Addons, PowerPack)

Sites running these add-ons save ~100 extra keys on every row and column (gradients, animated and particle
backgrounds, shape and border separators, expandable rows, down arrows, column shadows, PowerPack overlays and
scrolling backgrounds). `Helpers\AddonSettings` groups them into families, each with a gate (an enable toggle,
or a non-core `bg_type`). An inactive family is inert: its keys are neither mapped nor listed, and the report
counts the nodes under `addon_settings_ignored`. An active family is one "not carried over" entry per node
naming the add-on and the feature; an add-on `bg_type` is also reported as a dropped background. Keys switched
off (`no`, `none`, `off`, `false`), Beaver's runtime flags and the settings form's scratch keys are never listed.
