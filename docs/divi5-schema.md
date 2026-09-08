# Divi 5 block schema (the target)

Read from Divi 5.7.4 and re-checked against Divi 5.12.1 (`references/Divi.zip`; the attribute paths
below are unchanged between the two — `includes/builder-5/visual-builder/packages/module-library/src/components/*/module.json`
and `server/Packages/StyleLibrary/Declarations/*`). Every path the converter writes is listed here.

## Storage

`post_content` holds WordPress block comments wrapped in `divi/placeholder`; each block carries
`builderVersion`. Post meta: `_et_pb_use_builder = on`, `_et_pb_use_divi_5 = on`,
`_et_builder_version = VB|Divi|<version>`. HTML inside attribute JSON is stored with `<` as `<`
(`JSON_HEX_TAG`), and content passed to `wp_update_post()` must be `wp_slash()`ed.

```
<!-- wp:divi/placeholder -->
<!-- wp:divi/section {"builderVersion":"5.7.4", ...} -->
<!-- wp:divi/row {...} --><!-- wp:divi/column {...} -->
<!-- wp:divi/heading {"title":{"innerContent":{"desktop":{"value":"Hello"}}}} /-->
<!-- /wp:divi/column --><!-- /wp:divi/row --><!-- /wp:divi/section -->
<!-- /wp:divi/placeholder -->
```

Nesting: section → row → column → modules; a column may also hold a `divi/row` (nested rows) or a
`divi/group` (flex container). Rows hold columns only.

## Responsive attributes

Every value is `{breakpoint: {value: …}}` with `desktop`, `tablet`, `phone`.

## Structural

| Path | Value |
|---|---|
| row `module.advanced.columnStructure.desktop.value` | `"4_4"`, `"1_2,1_2"`, `"2_3,1_3"`, `"1_3,1_3,1_3"`, `"1_4,…"`, `"1_5,…"`, `"2_5,3_5"`, `"3_4,1_4"`, `"4_5,1_5"` |
| column `module.advanced.type.desktop.value` | one fraction |
| row/section `module.decoration.sizing.{bp}.value` | `{width, maxWidth, minHeight, height, alignment}` |
| `module.decoration.layout.{bp}.value` | `{display, flexDirection, flexWrap, justifyContent, alignItems, alignContent, columnGap, rowGap}` |

## Decoration (any module)

| Path | Value |
|---|---|
| `module.decoration.spacing.{bp}.value.margin` / `.padding` | `{top,right,bottom,left,syncVertical,syncHorizontal}` (CSS lengths) |
| `module.decoration.background.{bp}.value.color` | CSS colour |
| `…background.{bp}.value.image` | `{url, position ("center center"), size (cover|contain|auto), repeat}` |
| `…background.desktop.value.gradient` | `{enabled:"on", type: linear|radial, direction:"135deg" \| directionRadial:"top left"|"center", stops:[{color, position:"23"}], overlaysImage:"on"}` — stop positions are bare numbers; Divi appends `%` itself and rejects a position carrying a unit |
| `module.decoration.border.{bp}.value.styles.all` | `{style, color, width}` (or per side `styles.top…`) |
| `…border.{bp}.value.radius` | `{topLeft, topRight, bottomRight, bottomLeft}` |
| `module.decoration.boxShadow.{bp}.value` | `{style:"preset1", position: outer|inner, color, horizontal, vertical, blur, spread}` |
| `module.decoration.disabledOn.{bp}.value` | `"on"` hides the module at that breakpoint |
| `module.advanced.htmlAttributes.desktop.value` | `{id, class}` |
| `module.advanced.link.desktop.value` | `{url, target:"_blank", rel:["nofollow"]}` |
| `module.advanced.text.text.{bp}.value.orientation` | `left|center|right` |
| `css.desktop.value.main` / `.freeForm` | custom CSS on the module / free-form rules with `selector` |

## Fonts

`<font path>.{bp}.value` = `{family, weight, size, lineHeight, letterSpacing, textAlign, color, style:[italic|uppercase|capitalize|lowercase|underline|strikethrough], textShadow:{style:"preset1", color, horizontal, vertical, blur}, headingLevel}`.

Font paths: heading `title.decoration.font.font`; text `content.decoration.bodyFont.body.font`;
button `button.decoration.font.font`; blurb/cta title `title.decoration.font.font`, body
`content.decoration.bodyFont.body.font`; counters `number.decoration.font.font`, `title.decoration.font.font`.

## Modules and their content keys

| Block | Content |
|---|---|
| `divi/heading` | `title.innerContent` (string) |
| `divi/text` | `content.innerContent` (HTML) |
| `divi/image` | `image.innerContent` `{src, alt, linkUrl, linkTarget}`; `image.advanced.lightbox`; `image.decoration.border/boxShadow`; `module.advanced.align` |
| `divi/button` | `button.innerContent` `{text, linkUrl, linkTarget, rel}`; `button.decoration.{background,border,boxShadow,font,spacing}`; `button.decoration.button.desktop.value.icon` `{enable, settings:{type:"fa", unicode, weight}, placement: left|right, onHover}`; `module.advanced.alignment` |
| `divi/code` | `content.innerContent` (raw HTML) |
| `divi/video` | `video.innerContent` `{src, webm}`; `overlay.innerContent.desktop.value.image.src` |
| `divi/audio` | `audio.innerContent` (URL string); `title.innerContent`, `artistName`, `albumName` |
| `divi/sidebar` | `sidebar.innerContent.desktop.value.area` |
| `divi/icon` | `icon.innerContent` `{type, unicode, weight, linkUrl, linkTarget}`; `icon.advanced.{color,size,align}` |
| `divi/blurb` | `title.innerContent` `{text}`; `content.innerContent`; `imageIcon.innerContent` `{useIcon:"on", icon:{…}}` or `{src}`; `imageIcon.advanced.{color, placement: top|left}` |
| `divi/cta` | `title.innerContent`, `content.innerContent`, `button.innerContent` `{text, linkUrl, linkTarget}`, `button.decoration.*` |
| `divi/number-counter` | `number.innerContent`, `number.advanced.enablePercentSign`, `title.innerContent` |
| `divi/circle-counter` | `number.innerContent`, `number.advanced.percentSign`, `circle.advanced.circle.desktop.value` `{color, backgroundColor}`, `title.innerContent` |
| `divi/counters` › `divi/counter` | `title.innerContent`, `barProgress.innerContent` (percent) |
| `divi/menu` | `menu.advanced.menuId.desktop.value` (nav_menu term id) |
| `divi/divider` | `divider.advanced.line.desktop.value` `{show:"on", color, style, weight}`; `module.decoration.sizing` width/alignment |
| `divi/group` | children; `module.decoration.layout` |
| `divi/accordion` › `divi/accordion-item` | `title.innerContent`, `content.innerContent` |
| `divi/tabs` › `divi/tab` | `title.innerContent`, `content.innerContent` |
| `divi/testimonial` | `content`, `author`, `jobTitle`, `company.innerContent {text}`, `portrait.innerContent {src}` |
| `divi/pricing-tables` › `divi/pricing-table` | `title`, `subtitle`, `price`, `currencyFrequency.innerContent {per}`, `content.innerContent` (`<ul>`), `button.innerContent {text, linkUrl}` |
| `divi/contact-form` › `divi/contact-field` | `button.innerContent`, `email.advanced.receiver`, `email.innerContent` (success message), `redirect.*`; field: `fieldItem.innerContent` (label), `fieldItem.advanced.{id,type,required}` |
| `divi/signup` | `title`, `content`, `button.innerContent {text}`, `field.advanced.nameField`, `success.advanced.message` |
| `divi/gallery` | `image.advanced.galleryIds.desktop.value` (ids) |
| `divi/slider` › `divi/slide` | `title`, `content`, `button.innerContent`, `module.decoration.background` |
| `divi/blog` | `post.innerContent.desktop.value.perPage` |
| `divi/countdown-timer` | `module.advanced.countdownDate.desktop.value`, `title.innerContent` |
| `divi/social-media-follow` › `divi/social-media-follow-network` | `socialNetwork.innerContent {title, link, label}` |
| `divi/login` | `title`, `content`, `module.advanced.redirectUrl` |
| `divi/search` | `search.innerContent {placeholder, buttonText}` |

Font Awesome: Divi ships FA5 in its icon list (`icon-library/src/components/icon-font/iconList.json`),
keyed by FA name with weights 900 (solid) and 400 (regular/brands). `data/fa-icons.json` is built from
it by `scripts/build-fa-icon-map.php`.
