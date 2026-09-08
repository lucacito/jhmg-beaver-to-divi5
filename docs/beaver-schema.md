# Beaver Builder layout schema (as stored)

Verified against Beaver Builder Lite 2.10.3.2 (`classes/class-fl-builder-model.php`,
`includes/*-settings.php`, `modules/*/*.php`) and the eleven bundled layouts in
`data/layout-*-lite.dat`. Nothing here is inferred from rendered HTML.

## Storage

| Where | What |
|---|---|
| post meta `_fl_builder_enabled` | `1` when the page is built with Beaver Builder (`FLBuilderModel::enable()` stores `true`). |
| post meta `_fl_builder_data` | The **published** layout: a PHP-serialized array `node_id ⇒ stdClass`. |
| post meta `_fl_builder_draft` | Unpublished work in the same shape. The converter ignores it. |
| post meta `_fl_builder_data_settings` | Layout-level `css` and `js` (stdClass). |
| option `_fl_builder_settings` | Global settings (row width 1100px, row padding 20, module margins 20, breakpoints 1200/992/768). |
| option `_fl_builder_post_types` | Post types Beaver Builder may edit (default `page`). |
| post type `fl-builder-template` | Saved templates (rows, columns, modules, layouts); same meta. |
| post type `fl-theme-layout` (Themer, Pro) | Header/footer/part layouts; `_fl_theme_layout_type` = `header` \| `footer` \| … |

## Node

```json
"5e3b3c684929a": {
  "node": "5e3b3c684929a",      // 13-char hex id
  "type": "row",                // row | column-group | column | module
  "parent": null,               // parent node id; null for rows
  "position": 0,                // order among siblings
  "settings": { ... }           // stdClass; "" on a column group with no settings
}
```

Hierarchy: `row → column-group → column → module`. A column may also contain a
`column-group` (nested columns). Container modules (`box`) have children whose
`parent` is the module's own id.

## Conventions inside `settings`

- **Responsive suffixes:** none = desktop, `_large` (≤ 1200px), `_medium` (≤ 992px), `_responsive` (≤ 768px).
- **Units** live in a sibling key: `min_height` + `min_height_unit`; dimension fields share one unit key per breakpoint: `padding_top`, `padding_unit`, `padding_top_medium`, `padding_medium_unit`.
- **Colours:** hex **without** `#` (`"64A6BD"`), or `rgba(...)`. Global colours (2.7+) appear as `var(--fl-global-<slug>)`.
- **Photo fields:** `<name>` = attachment id, `<name>_src` = URL (`photo`/`photo_src`, `bg_image`/`bg_image_src`). `data` holds the attachment record (`url`, `alt`, `caption`, `title`, `sizes`).
- **Link fields:** `<name>`, `<name>_target` (`_self`/`_blank`), `<name>_nofollow` (`yes`/`no`), `<name>_download`.
- **typography** (array): `font_family` ("Default" = inherit), `font_weight` ("default", "700", "700i"), `font_size` `{length, unit}`, `line_height` `{length, unit:""}`, `letter_spacing` `{length, unit}`, `text_align`, `text_transform`, `text_decoration`, `font_style`, `font_variant`, `text_shadow` `{color, horizontal, vertical, blur}`. Breakpoints: `typography_medium`, `typography_responsive`.
- **border** (array): `style`, `color`, `width` `{top,right,bottom,left}`, `radius` `{top_left, top_right, bottom_left, bottom_right}`, `shadow` `{color, horizontal, vertical, blur, spread}`.
- **Gradients:** `{type: linear|radial, angle, position, colors: [c1, c2], stops: [0, 100]}`.
- **Advanced tab on every node:** `margin_*`, (`padding_*` on rows/columns/some modules), `responsive_display`, `visibility_display`, `visibility_user_capability`, `animation`, `container_element`, `id`, `class`, `node_label`.

## Row settings

`width` (fixed|full), `content_width` (fixed|full), `max_content_width` (+unit, breakpoints),
`full_height` (default|full|custom), `min_height` (+unit, breakpoints), `aspect_ratio`,
`content_alignment` (top|center|bottom), `text_color`, `link_color`, `hover_color`, `heading_color`,
`bg_type` (none|color|gradient|photo|multiple|video|embed|slideshow|parallax),
`bg_color`, `bg_gradient`, `bg_image_source` (library|url), `bg_image`, `bg_image_src`, `bg_image_url`,
`bg_repeat`, `bg_position` ("center center"…), `bg_x_position`, `bg_y_position`, `bg_attachment`, `bg_size`,
`bg_video_*`, `bg_video_fallback` (+`_src`), `ss_*` (slideshow), `bg_parallax_image` (+`_src`), `bg_parallax_speed`,
`bg_overlay_type` (none|color|gradient), `bg_overlay_color`, `bg_overlay_gradient`, `bg_embed_code`,
`background` (2.9 layers), `border`, `top_shape`/`bottom_shape` (+`_color`, `_size`, `_flip`), `top_edge_transform`/`bottom_edge_transform`.

## Column settings

`size` (percent, float), `size_medium`, `size_responsive`, `size_large`, `min_height`, `equal_height`,
`aspect_ratio`, `content_alignment`, the same `text_*`/`bg_*`/`border` family as rows, `margin_*`, `padding_*`,
`responsive_display`, `responsive_order`.

## Module settings (Lite)

| slug | keys the converter reads |
|---|---|
| `heading` | `heading`, `tag` (h1–h6), `link`(+target/nofollow), `color`, `typography` |
| `rich-text` | `text` (HTML), `color`, `typography` |
| `photo` | `photo_source` (library|url), `photo`, `photo_src`, `photo_url`, `url_title`, `data.alt`, `caption`, `show_caption` (0|hover|below), `link_type` (''|url|lightbox|file|page), `link_url`(+target/nofollow), `crop` (''|landscape|panorama|portrait|square|circle), `width`(+unit), `align`, `border` |
| `button` | `text`, `icon`, `icon_position`, `icon_animation`, `click_action` (link|button|lightbox|copy_text), `link`(+target/nofollow), `lightbox_*`, `width` (auto|full|custom), `custom_width`, `align`, `padding_*`, `text_color`, `text_hover_color`, `typography`, `style` (flat|gradient|adv-gradient), `bg_color`, `bg_hover_color`, `bg_gradient`, `border`, `border_hover_color` |
| `button-group` | `items[]` (button fields each), `layout`, `align`, `button_padding_*`, `button_spacing`, plus the button style family |
| `html` | `html` |
| `video` | `video_type` (media_library|embed), `video`, `data.url`, `video_webm`, `embed_code`, `video_lightbox`, `poster`, `poster_src`, `autoplay`, `loop` |
| `audio` | `audio_type` (media_library|link), `audios[]`, `data.url`, `link` |
| `sidebar` | `sidebar` (widget area id) |
| `icon` | `icon` (CSS class), `link`, `text`, `size`(+unit), `align`, `color`, `hover_color`, `bg_color`, `text_color`, `text_typography` |
| `callout` | `title`, `title_tag`, `text`, `image_type` (none|photo|icon), `photo`/`photo_src`, `photo_position`, `icon`, `icon_position`, `icon_color`, `cta_type` (none|link|button), `cta_text`, `link`(+target), `btn_*` (button fields), `bg_color`, `border`, `align`, `padding_*`, `title_color`, `title_typography`, `content_color`, `content_typography` |
| `cta` | `title`, `title_tag`, `text`, `layout`, `alignment`, `bg_color`, `border`, `wrap_padding_*`, `title_color`, `title_typography`, `text_color`, `text_typography`, `btn_text`, `btn_link`(+target), `btn_*` |
| `numbers` | `layout` (default|circle|bars), `number`, `start_number`, `max_number`, `number_type` (percent|standard), `number_prefix`, `number_suffix`, `before_number_text`, `after_number_text`, `text_color`, `text_typography`, `number_color`, `number_typography`, `circle_color`, `circle_bg_color`, `bar_color` |
| `star-rating` | `total`, `rating`, `unicode`, `fill` |
| `menu` | `menu` (nav_menu slug), `menu_layout` |
| `box` | `layout` (flex|grid|z_stack), `flex_direction`, `gap`(+unit), children nodes |
| `widget`, `reusable-block`, `acf-block` | placeholders only |

Pro modules (`separator`, `accordion`, `tabs`, `testimonials`, `pricing-table`, `contact-form`,
`subscribe-form`, `map`, `gallery`, `slideshow`, `content-slider`, `posts`, `post-grid`, `post-slider`,
`post-carousel`, `countdown`, `icon-group`, `social-buttons`, `login-form`, `search`) are read with the
field names from Beaver Builder's public documentation. The registry marks them approximate.

## Bundled templates

`fixtures/beaver-templates/*.json` are the node maps of Beaver Builder Lite's own layout templates
(`data/layout-*-lite.dat`, regenerated with `scripts/bb-dat-to-json.php`). They use heading, rich-text,
photo, button, icon and html across 3–8 rows each and are the smoke-test corpus.

## Pro modules seen in exported layouts (documentation-based, registered approximate)

- **list** (2.6+): `list_items[]` `{heading, content, list_item_icon, heading_text_color, content_text_color, bg_color, icon_color, list_item_padding_*}`; `list_type` `ul|ol|div`; `ul_icon`, `ol_icon` (CSS list-style keywords); `div_icon` (FA class); `list_icon_placement` (`content_left`…); `heading_tag`; `list_icon_color`; `icon_size`; `icon_width`; `heading_typography`, `content_typography`, `heading_color`, `content_color`; `separator_style`, `separator_color`, `separator_size`; `common_list_item_padding_*`; `list_bg_color`, `list_border`, `list_padding_*`.
- **progress-bar**: `layout` `horizontal|vertical|circular`; one repeater per layout (`horizontal[]` …) with `<layout>_number`, `<layout>_before_number`, `circular_after_number`, `progress_bg_type` `color|gradient|image`, `gradient_field {color_one, color_two, direction, angle}`, `gradient_color` (the solid fill), `background_color` (the track); `<layout>_thickness`; `progress_border`; `stripped`; `text_position`; `overall_alignment`, `title_alignment`; `text_typo`, `text_color`, `number_typo`, `number_color`.

## Third-party modules (PowerPack for Beaver Builder)

- **pp-heading**: `prefix_text`, `prefix_tag`, `prefix_text_color`, `prefix_typography`; `heading_title`, `heading_tag`, `heading_alignment`, `heading_color_type` (`solid|gradient`), `heading_color`, `title_typography`, `heading_bg_color`, `heading_top_margin`, `heading_bottom_margin`, `heading_padding_*`; `dual_heading`, `heading_title2`, `heading2_color`, `heading2_left_margin`; `enable_link`, `heading_link`, `heading_link_target`, `heading_link_nofollow`; `heading_separator` (`no_spacer|line|…icon…`), `heading_separator_postion` (`top|middle|bottom`), `heading_line_style`, `line_width` (+unit), `line_height`, `line_color`, `font_title_line_space`, `heading_icon_select`, `heading_font_icon_select`, `heading_custom_icon_select_src`, `font_icon_*`; `heading_sub_title` (HTML), `sub_heading_color`, `desc_typography`, `sub_heading_top_margin`, `sub_heading_bottom_margin`.
- **pp-iconlist**: `list_type` (`icon`), `list_icon` (FA class), `list_items[]` (strings, or `{text|list_item_text, icon|list_item_icon}`), `item_margin`, `icon_space`, `icon_color`, `icon_size`, `icon_bg`, `icon_padding`, `icon_border`, `text_typography`, `text_color`, hover colours.
- **pp-fluent-form**: `select_form_field` (Fluent Forms id); `form_custom_title_desc`, `custom_title`, `title_tag`, `custom_description`; container `form_bg_type/color/image_src/size/repeat`, `form_border`, `form_padding_*`; `display_labels`, `label_color`, `label_typography`; inputs `input_field_text_color`, `input_field_bg_color`, `input_border`, `input_field_height`, `input_textarea_height`, `input_field_padding_*`, `input_field_margin`, `input_placeholder_display/color`, `input_field_focus_color`, `input_typography`; button `button_text_color(_hover)`, `button_bg_color`, `button_background_color_hover`, `button_border`, `button_typography`, `button_padding_*`, `button_width`, `button_alignment`; messages `error_*`, `success_message_*`; radio/checkbox `radio_cb_*`.

## Add-on settings injected on every row and column

Ultimate Addons for Beaver Builder and PowerPack extend the row/column forms; every saved node then carries
their keys at defaults. Families and gates (see `Helpers\AddonSettings`):

| Family | Keys | Active when |
|---|---|---|
| UABB row gradient | `uabb_row_gradient_*`, `uabb_row_radial_*`, `uabb_row_linear_*`, `uabb_row_uabb_direction` | `bg_type = uabb_gradient` |
| UABB column gradient | `uabb_col_*` | `bg_type = uabb_gradient` |
| UABB animated background | `animation_type`, `bird_*`, `fog_*`, `waves_*`, `net_*`, `dots_*`, `rings_*`, `cells_*` | `bg_type` contains `anim` |
| Particle background | `enable_particles`, `uabb_row_particles_*`, `uabb_particles_*`, `part_*` | `enable_particles = yes` or `bg_type` contains `particle` |
| PowerPack scrolling image | `pp_bg_image*`, `pp_infinite_overlay`, `scrolling_*` | `bg_type` contains `scroll` |
| PowerPack overlay width | `pp_bg_overlay_type` | value other than `full_width` |
| UABB shape separator | `separator_shape*`, `uabb_row_separator_*`, `bot_separator_*` | `separator_shape` / `bot_separator_shape` set |
| UABB border separator | `enable_separator`, `separator_type/color/shadow/height/position/tablet/mobile/opacity*` | `enable_separator = yes` |
| UABB expandable row | `enable_expandable`, `er_*` | `enable_expandable = yes` |
| UABB down arrow | `enable_down_arrow`, `da_*` | `enable_down_arrow = yes` |
| UABB column shadow | `col_drop_shadow`, `col_hover_shadow`, `col_responsive_shadow`, `col_small_shadow`, `col_shadow_*` | either shadow toggle `yes` |
| Beaver Builder row edge shapes | `top_edge_*`, `bottom_edge_*` | `top_edge_shape` / `bottom_edge_shape` set (reported under `shapes`) |

Runtime keys never listed: `responsive_display_filtered`, `undefined`, `bt_default_module`, `visibility_logic = "[]"`,
`flrich<digits>_*` (editor scratch), `field_separator_*`, `*-search`, `as_values_*`.

## Global settings used for defaults

`_fl_builder_settings` (shipped defaults in `includes/global-settings.php`): `row_width` 1100px, `row_padding` 20
(dimension: `row_padding_top`… + `row_padding_unit`), `column_padding` blank, `module_margins` 20. Every blank
row/column/module side is rendered with these values by `FLBuilder::render_global_css`, so the converter fills
them in the same way (`StyleMapper\GlobalSettingsResolver`).

## Modules from Beaver Builder's reference not covered above

- **woocommerce** (Pro; wraps WooCommerce shortcodes): `layout` = `single_product | product_page | products | add_to_cart | categories | cart | checkout | order_tracking | my_account`; `product_id`; products: source (`products_source`: ids | category | tag | recent | featured | sale | best_selling | top_rated), ids, category/tag slugs, number of products, columns (1–6), sort by (default | popularity | rating | date | price | id), sort direction; categories: autoselect parent, parent category ID, category IDs, sort by, direction, columns. Field keys are read with several spellings (documentation names only).
- **loop** (2.11, Beaver Themer; container module): query `source` (custom_query | main_query | taxonomy_query), post type, offset, exclude current post, authors, taxonomy + terms; layout: item sizing (columns | item size), number of columns (large/medium/small), min/max width, gap; pagination (numbers | scroll | none), posts per page, no-results message, show search. Children are modules whose `parent` is the loop node, with field connections in their settings.
- **popup** (2.11; container module): `popup_id`, show on (delay | scroll | exit), show delay, scroll percent, show once, schedule dates, close on ESC/click outside, close button + styling, position, width/height, backdrop.
- **bigcommerce-products** (Pro): use pagination, products per page, featured-only, sale-only, recent-only → `[bigcommerce_product]`.
- **north-commerce** (Pro): `layout` (none | product_page | product_gallery | product_slider | cart | checkout), `product_slug`, button colours/border.
- **reusable-block** (Lite; "WordPress Patterns" in the UI, source `modules/reusable-block`): `block_id` = `block-<wp_block id>`; rendered by `do_blocks('<!-- wp:block {"ref":id} /-->')`. Beaver Builder also registers an alias module per pattern (`fl-reusable-block-<id>`) whose saved type is still `reusable-block`.
- **widget** (Lite, source `modules/widget`): `widget` (or `widget_class`) = widget PHP class, `widget_title`, `widget_key`, and the widget's own form values under `widget-<id_base>`; rendered by `the_widget()`.
- **acf-block** (Lite): the block is rebuilt from `acf` settings through ACF's block engine — left as a placeholder.

## Field connections

Beaver Themer stores a connection inline in the setting value as `[wpbb object:field attr='value']`
(`[wpbb post:title]`, `[wpbb site:year format='Y']`, `[wpbb post:featured_image size='large']`, `[wpbb acf name='x']`…);
the `connections` map beside the settings only records which properties are connected.
