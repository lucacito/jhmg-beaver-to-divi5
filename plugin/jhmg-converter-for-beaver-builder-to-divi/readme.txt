=== JHMG Converter For Beaver Builder to Divi 5 ===
Contributors: lucaslopvet
Tags: divi migration, beaver builder, page builder converter, beaver builder to divi, divi 5
Requires at least: 5.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert Beaver Builder pages into native Divi 5 layouts. Pick a page from your site, check the result before anything is written, convert with one click, undo any run.

== Description ==

Convert pages built with Beaver Builder into native Divi 5 block layouts — rows become sections, column groups become rows, columns become columns, and every Beaver Builder Lite module maps to its Divi 5 counterpart with its content, links, images, colours, typography, spacing and backgrounds carried across.

If Beaver Builder is installed on this site, pick the page from a list — no export needed. Converting from another site? Export it from Tools → Export there (Beaver Builder stores its layouts inside the export) and upload the file here.

Before you convert, click **Check this page** to see a conversion report: the structure the conversion will produce, laid out as an outline, and everything that could not be carried over, by name. Nothing is written until you click Convert. Converting always creates a new Divi draft — your Beaver Builder page is never modified — and every run can be undone with one click.

### What converts

**Layout:** rows (full and fixed width, backgrounds, overlays, gradients, padding, minimum height), column groups, columns (widths, backgrounds, borders), nested column groups, and the Box container.

**Beaver Builder Lite modules** — every one of them, from the plugin's own source: Heading, Text Editor, Photo, Button, Button Group, HTML, Video, Audio, Sidebar, Icon, Callout, Call to Action, Number Counter, Star Rating, Menu, Box. Widget, Reusable Block and ACF Block modules leave a labelled placeholder holding their text.

**Beaver Builder Pro modules** — Separator, Accordion, Tabs, Testimonials, Pricing Table, Contact Form, Subscribe Form, Map, Gallery, Slideshow, Content Slider, Posts (grid, slider, carousel), Countdown, Icon Group, Social Buttons, Login Form, Search, List and Progress Bar convert with their content. These mappings come from Beaver Builder's documentation and exported layouts rather than its source, so the report marks them as approximate and lists them for you to check.

**PowerPack for Beaver Builder** — Advanced Heading, Icon List and Fluent Forms modules convert too (the form is embedded through its Fluent Forms shortcode with PowerPack's styling carried as CSS). Sites running Ultimate Addons or PowerPack save around a hundred add-on settings on every row and column; the converter recognises those families, ignores the ones left at their defaults and reports the ones you switched on, so the report only lists what the page really loses.

**Design settings:** margins and padding (with units and tablet/phone values), background colours, photos, gradients and overlays, borders, radius and shadows, typography (family, weight, size, line height, letter spacing, alignment, transform, decoration, shadow), text colours, minimum heights, widths, alignment, custom IDs and classes, and responsive visibility. Font Awesome icons become the identical Divi icon.

**Reported, not silently lost:** animations, logged-in/logged-out visibility, row shape layers, video and slideshow backgrounds, lightbox links, hover colours, click actions, third-party connections, global colours that could not be resolved, and any setting the converter did not map.

### Free vs Pro

**Free:**

* Convert a page straight from your Beaver Builder site — pick it from a list
* Check any page before converting: see the structure and exactly what will not carry over
* Upload a WordPress export or a Beaver Builder template file to convert a page from another site
* Every Beaver Builder Lite module and the popular Pro modules
* A detailed per-page report, one-click undo for every run
* Unlimited conversions — one page at a time

**[Pro add-on](https://divi5lab.com/plugins/beaver-builder-to-divi-5):**

* Convert as many pages as you like in one run — from this site or from one export file
* Send Beaver Themer headers and footers straight into the Divi Theme Builder
* Priority support and regular updates

### Step by step

1. Install and activate this plugin on your Divi 5 site.
2. Go to **Tools → Beaver Builder → Divi 5**.
3. Pick the page you want to convert and click **Check this page**.
4. Read the report, then click **Convert to Divi 5** — a new Divi draft is created.
5. Review it in the Divi Builder, then publish when ready.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/jhmg-converter-for-beaver-builder-to-divi`, or install through the Plugins screen.
2. Activate the plugin. Divi 5.0 or newer must be active for the converter to run.
3. Go to Tools → Beaver Builder → Divi 5 to begin.

== Frequently Asked Questions ==

= Do I need Beaver Builder installed? =

Only to pick pages from the list on this site. To convert pages from another site, export them there and upload the export file here — Beaver Builder is not needed on the destination.

= Will this change my Beaver Builder pages? =

No. Converting always creates a new Divi draft; the original page is never modified, and every run can be undone from the Recent conversions list.

= What about draft layouts? =

Only published Beaver Builder layouts convert. If a page has unpublished Beaver Builder changes only, publish it in Beaver Builder first.

= What happens to modules the converter does not know? =

They leave a labelled placeholder that keeps their text and position, and they are listed in the report and on the coverage panel so you know what to rebuild by hand.

= Does it need Divi 5? =

Yes. The converter writes Divi 5 block content. On Divi 4 or without Divi it explains itself and does nothing.

== Screenshots ==

1. The converter — pick an installed Beaver Builder page, or upload an export
2. The conversion report shown before anything is written
3. Conversion results with per-page issues and one-click undo

== External services ==

This plugin can optionally send a short report to divi5lab.com so that the most
commonly missing Beaver Builder modules get built first.

* **Service:** divi5lab.com coverage endpoint — https://divi5lab.com/api/plugin/coverage
* **What is sent:** two fields — `widget_types` (the names of Beaver Builder module
  types your conversions could not convert, for example `acf-block`) and `product` (a
  fixed identifier for this plugin). Nothing else — no site address, no page content,
  no personal data, no licence or account information.
* **When:** at most once a week, and only after you explicitly turn sharing on from
  the Conversion coverage panel. Sharing is opt-in, off by default, and nothing is sent until you enable it.
* **Turning it off:** use "Stop sharing" on the same panel at any time.
* Terms: https://divi5lab.com/terms — Privacy policy: https://divi5lab.com/privacy

== Changelog ==

= 1.0.0 =
* First release: convert Beaver Builder pages into native Divi 5 block layouts
* Convert directly from installed pages, with a check-before-convert report and one-click undo
* Upload WordPress exports and Beaver Builder template files
* Every Beaver Builder Lite module, plus content-level conversion of the popular Pro modules
* Font Awesome icons map to the identical Divi icons

== Upgrade Notice ==

= 1.0.0 =
First release.
