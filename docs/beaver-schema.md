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
