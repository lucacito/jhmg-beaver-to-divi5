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
| anything else | `divi/code` placeholder | reported as unsupported |

Design settings: see `docs/divi5-schema.md` for every attribute path and the spec §6 for the mapping table.
