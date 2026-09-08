<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\IconMap;
use BeaverDivi5Converter\Helpers\Size;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro "List" module (2.6+) →
 *   - list type ul / ol: one divi/text holding the list, markers set through CSS;
 *   - list type div (icon list): one divi/blurb per item, the icon on the side
 *     the source chose, each item's heading as the blurb title.
 *
 * Pro module: field names come from exported layouts and Beaver Builder's
 * documentation, so the conversion is registered approximate.
 */
class ListConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_list_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $items = [];
        foreach ( is_array( $settings['list_items'] ?? null ) ? $settings['list_items'] : [] as $item ) {
            if ( is_object( $item ) ) {
                $item = (array) $item;
            }
            if ( ! is_array( $item ) ) {
                continue;
            }
            $heading = trim( (string) ( is_string( $item['heading'] ?? null ) ? $item['heading'] : '' ) );
            $content = trim( (string) ( is_string( $item['content'] ?? null ) ? $item['content'] : '' ) );
            if ( $heading === '' && $content === '' ) {
                continue;
            }
            $items[] = [ 'heading' => $heading, 'content' => $content, 'raw' => $item ];
        }
        if ( $items === [] ) {
            $this->engine->logWarning( "List {$id}: no items; nothing was emitted." );
            $this->logUnmappedSettings( $id, $settings, self::CONSUMED );
            return [];
        }

        $type        = strtolower( $this->text( $settings, 'list_type' ) ) ?: 'div';
        $heading_tag = strtolower( $this->text( $settings, 'heading_tag' ) );
        $heading_tag = preg_match( '/^h[1-6]$/', $heading_tag ) ? $heading_tag : 'h3';

        return $type === 'ul' || $type === 'ol'
            ? $this->markerList( $id, $node, $settings, $items, $type, $heading_tag )
            : $this->iconList( $id, $node, $settings, $items, $heading_tag );
    }

    /** ul / ol → a single divi/text. */
    private function markerList( string $id, array $node, array $settings, array $items, string $type, string $heading_tag ): array {
        $html = "<{$type}>";
        foreach ( $items as $item ) {
            $html .= '<li>';
            if ( $item['heading'] !== '' ) {
                $html .= "<{$heading_tag}>" . $item['heading'] . "</{$heading_tag}>";
            }
            $html .= $item['content'] . '</li>';
        }
        $html .= "</{$type}>";

        $block  = $this->delegate( RichTextConverter::class, $id, array_merge( $this->moduleLevel( $settings ), [
            'type'                  => 'rich-text',
            'text'                  => $html,
            'color'                 => $this->setting( $settings, 'content_color' ),
            'typography'            => $this->setting( $settings, 'content_typography' ),
            'typography_medium'     => $this->setting( $settings, 'content_typography_medium' ),
            'typography_responsive' => $this->setting( $settings, 'content_typography_responsive' ),
        ] ), true );
        $marker = $this->text( $settings, $type === 'ul' ? 'ul_icon' : 'ol_icon' );
        $rules  = [];
        if ( $marker !== '' ) {
            $rules[] = "selector {$type} { list-style-type: {$marker}; }";
        }
        $heading_color = $this->color( $settings, 'heading_color', $id );
        if ( $heading_color !== null ) {
            $rules[] = "selector li {$heading_tag} { color: {$heading_color}; }";
        }
        if ( $rules !== [] ) {
            StyleMapper::write( $block['settings'], 'css.desktop.value.freeForm', implode( ' ', $rules ) );
        }
        if ( is_array( $settings['heading_typography'] ?? null ) && array_filter( $settings['heading_typography'], static fn( $v ) => is_string( $v ) && $v !== '' && $v !== 'Default' && $v !== 'default' ) !== [] ) {
            $this->engine->logWarning( "List {$id}: item heading typography is not carried over inside a plain {$type} list." );
        }

        $this->logUnmappedSettings( $id, $settings, self::CONSUMED );

        return $block;
    }

    /** div (icon list) → one divi/blurb per item. */
    private function iconList( string $id, array $node, array $settings, array $items, string $heading_tag ): array {
        $style   = $this->mapStyle( 'blurb', $node );
        $module  = $style['divi_attrs']['module'] ?? [];
        $handled = $style['handled_keys'];

        $shared_icon  = $this->text( $settings, 'div_icon' );
        $shared_color = $this->color( $settings, 'list_icon_color', $id );
        $icon_size    = Size::fromSettings( $settings, 'icon_size', 'icon_size' );
        $icon_width   = Size::fromSettings( $settings, 'icon_width', 'icon_width' );
        $placement    = strtolower( $this->text( $settings, 'list_icon_placement' ) );
        $side         = str_contains( $placement, 'top' ) ? 'top' : 'left';
        if ( str_contains( $placement, 'right' ) ) {
            $this->engine->logWarning( "List {$id}: icons sit on the right in Beaver Builder; Divi's blurb places them on the left." );
        }

        $heading_color = $this->color( $settings, 'heading_color', $id );
        $content_color = $this->color( $settings, 'content_color', $id );

        $separator_style = strtolower( $this->text( $settings, 'separator_style' ) );
        $separator       = null;
        if ( $separator_style !== '' && $separator_style !== 'none' ) {
            $separator = [
                'style' => $separator_style,
                'width' => Size::withUnit( $settings['separator_size'] ?? '1', null ) ?: '1px',
            ];
            $sep_color = $this->color( $settings, 'separator_color', $id );
            if ( $sep_color !== null ) {
                $separator['color'] = $sep_color;
            }
        }

        $blocks = [];
        $count  = count( $items );
        foreach ( $items as $i => $item ) {
            $raw   = $item['raw'];
            $attrs = [ 'imageIcon' => [ 'advanced' => [ 'placement' => [ 'desktop' => [ 'value' => $side ] ] ] ] ];

            if ( $item['heading'] !== '' ) {
                $attrs['title']['innerContent']['desktop']['value'] = $item['heading'];
            }
            $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] = $heading_tag;
            if ( $item['content'] !== '' ) {
                $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $item['content'] );
            }

            $icon_class = is_string( $raw['list_item_icon'] ?? null ) && $raw['list_item_icon'] !== '' ? $raw['list_item_icon'] : $shared_icon;
            if ( $icon_class !== '' ) {
                $mapped = IconMap::fromClass( $icon_class );
                if ( ! $mapped['exact'] ) {
                    $this->engine->logWarning( "List {$id}: icon '{$icon_class}' has no Divi equivalent; a star icon is used." );
                }
                $attrs['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $mapped['icon'] ];
                $icon_color = $this->color( $raw, 'icon_color', $id ) ?? $shared_color;
                if ( $icon_color !== null ) {
                    $attrs['imageIcon']['advanced']['color']['desktop']['value'] = $icon_color;
                }
                if ( $icon_size !== '' ) {
                    $attrs['imageIcon']['decoration']['sizing']['desktop']['value']['iconFontSize'] = $icon_size;
                }
                if ( $icon_width !== '' && $side === 'left' ) {
                    $attrs['css']['desktop']['value']['freeForm'] = "selector .et_pb_main_blurb_image { width: {$icon_width}; flex: 0 0 {$icon_width}; }";
                }
            }

            ( new StyleMapper() )->applyTypography( $settings, 'heading_typography', 'title.decoration.font.font', $attrs, $handled );
            ( new StyleMapper() )->applyTypography( $settings, 'content_typography', 'content.decoration.bodyFont.body.font', $attrs, $handled );
            $title_color = $this->color( $raw, 'heading_text_color', $id ) ?? $heading_color ?? $this->engine->inheritedColor( 'heading_color' ) ?? $this->engine->inheritedColor( 'text_color' );
            if ( $title_color !== null ) {
                $attrs['title']['decoration']['font']['font']['desktop']['value']['color'] = $title_color;
            }
            $body_color = $this->color( $raw, 'content_text_color', $id ) ?? $content_color ?? $this->engine->inheritedColor( 'text_color' );
            if ( $body_color !== null ) {
                $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $body_color;
            }
            $bg = $this->color( $raw, 'bg_color', $id );
            if ( $bg !== null ) {
                $attrs['module']['decoration']['background']['desktop']['value']['color'] = $bg;
            }

            $padding = [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $edge ) {
                $v = Size::fromSettings( $raw, 'list_item_padding_' . $edge, 'list_item_padding' );
                if ( $v === '' ) {
                    $v = Size::fromSettings( $settings, 'common_list_item_padding_' . $edge, 'common_list_item_padding' );
                }
                if ( $v !== '' ) {
                    $padding[ $edge ] = $v;
                }
            }
            if ( $padding !== [] ) {
                $attrs['module']['decoration']['spacing']['desktop']['value']['padding'] = $padding;
            }
            if ( $separator !== null && $i < $count - 1 ) {
                $attrs['module']['decoration']['border']['desktop']['value']['styles']['bottom'] = $separator;
            }

            $blocks[] = $this->block( $id . '-' . ( $i + 1 ), 'divi/blurb', $attrs );
        }

        foreach ( [ 'html', 'disabledOn', 'attributes' ] as $keep ) {
            if ( isset( $module['advanced'][ $keep ] ) ) {
                $blocks[0]['settings']['module']['advanced'][ $keep ] = $module['advanced'][ $keep ];
            }
            if ( isset( $module['decoration'][ $keep ] ) ) {
                $blocks[0]['settings']['module']['decoration'][ $keep ] = $module['decoration'][ $keep ];
            }
        }
        $this->spreadModuleSpacing( $blocks, $module );

        if ( $this->color( $settings, 'list_bg_color', $id ) !== null || $this->hasValue( $settings['list_border'] ?? null ) ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'background/border around the whole list' );
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $blocks;
    }

    /** Beaver's module-level keys, passed through to a delegated handler unchanged. */
    private function moduleLevel( array $settings ): array {
        $keys = [ 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'margin_unit', 'id', 'class', 'responsive_display', 'animation', 'visibility_display', 'visibility_user_capability', 'visibility_logic' ];
        return array_intersect_key( $settings, array_flip( $keys ) );
    }

    private function hasValue( mixed $value ): bool {
        if ( is_array( $value ) ) {
            foreach ( $value as $v ) {
                if ( $this->hasValue( $v ) ) {
                    return true;
                }
            }
            return false;
        }
        return is_string( $value ) && $value !== '';
    }

    const CONSUMED = [
        'list_items', 'list_type', 'ul_icon', 'ol_icon', 'div_icon', 'list_icon_placement', 'heading_tag', 'content_tag',
        'list_bg_color', 'list_border', 'list_icon_color', 'icon_size', 'icon_width', 'heading_color', 'heading_typography',
        'heading_typography_medium', 'heading_typography_responsive', 'content_color', 'content_typography',
        'content_typography_medium', 'content_typography_responsive', 'separator_style', 'separator_color', 'separator_size',
        'list_padding_top', 'list_padding_right', 'list_padding_bottom', 'list_padding_left',
        'common_list_item_padding_top', 'common_list_item_padding_right', 'common_list_item_padding_bottom', 'common_list_item_padding_left',
        'icon_padding_top', 'icon_padding_right', 'icon_padding_bottom', 'icon_padding_left',
        'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'margin_unit',
    ];
}
