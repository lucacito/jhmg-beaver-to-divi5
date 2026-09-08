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
 * PowerPack "Icon List" (pp-iconlist) → one divi/blurb per item, icon on the
 * left. Divi 5 has no list module; a blurb with left placement is how Divi
 * itself builds icon lists.
 *
 * PowerPack is a third-party add-on; field names come from exported layouts,
 * so the module is registered approximate.
 */
class PpIconListConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_pp_iconlist_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'blurb', $node );
        $module  = $style['divi_attrs']['module'] ?? [];
        $handled = $style['handled_keys'];

        $list_type = $this->text( $settings, 'list_type' ) ?: 'icon';
        $shared    = $list_type === 'icon' ? $this->text( $settings, 'list_icon' ) : '';
        if ( $list_type !== 'icon' ) {
            $this->engine->logWarning( "Icon list {$id}: list type '{$list_type}' is shown with plain text; only icon lists carry their marker." );
        }

        $color   = $this->color( $settings, 'icon_color', $id );
        $size    = Size::fromSettings( $settings, 'icon_size', 'icon_size' );
        $gap     = Size::fromSettings( $settings, 'icon_space', 'icon_space' );
        $spacing = Size::fromSettings( $settings, 'item_margin', 'item_margin' );
        $icon_bg = $this->color( $settings, 'icon_bg', $id );
        $padding = Size::fromSettings( $settings, 'icon_padding', 'icon_padding' );

        $items  = is_array( $settings['list_items'] ?? null ) ? array_values( $settings['list_items'] ) : [];
        $blocks = [];
        foreach ( $items as $i => $item ) {
            $text = '';
            $own  = '';
            if ( is_string( $item ) ) {
                $text = $item;
            } elseif ( is_array( $item ) ) {
                $text = (string) ( $item['text'] ?? $item['list_item_text'] ?? $item['content'] ?? '' );
                $own  = (string) ( $item['icon'] ?? $item['list_item_icon'] ?? '' );
            }
            $text = trim( $text );
            if ( $text === '' ) {
                continue;
            }

            $attrs = [
                'imageIcon' => [ 'advanced' => [ 'placement' => [ 'desktop' => [ 'value' => 'left' ] ] ] ],
                'content'   => [ 'innerContent' => [ 'desktop' => [ 'value' => RichTextConverter::autop( $text ) ] ] ],
            ];
            $class = $own !== '' ? $own : $shared;
            if ( $class !== '' ) {
                $mapped = IconMap::fromClass( $class );
                if ( ! $mapped['exact'] ) {
                    $this->engine->logWarning( "Icon list {$id}: icon '{$class}' has no Divi equivalent; a star icon is used." );
                }
                $attrs['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $mapped['icon'] ];
                if ( $color !== null ) {
                    $attrs['imageIcon']['advanced']['color']['desktop']['value'] = $color;
                }
                if ( $size !== '' ) {
                    $attrs['imageIcon']['decoration']['sizing']['desktop']['value']['iconFontSize'] = $size;
                }
                if ( $icon_bg !== null ) {
                    $attrs['imageIcon']['decoration']['background']['desktop']['value']['color'] = $icon_bg;
                }
                if ( $padding !== '' && (float) $padding !== 0.0 ) {
                    $attrs['imageIcon']['decoration']['spacing']['desktop']['value']['padding'] = [ 'top' => $padding, 'right' => $padding, 'bottom' => $padding, 'left' => $padding ];
                }
            }

            ( new StyleMapper() )->applyTypography( $settings, 'text_typography', 'content.decoration.bodyFont.body.font', $attrs, $handled );
            $text_color = $this->color( $settings, 'text_color', $id );
            if ( $text_color !== null ) {
                $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $text_color;
            }
            if ( $spacing !== '' && $i < count( $items ) - 1 ) {
                $attrs['module']['decoration']['spacing']['desktop']['value']['margin']['bottom'] = $spacing;
            }
            if ( $gap !== '' ) {
                $attrs['css']['desktop']['value']['freeForm'] = "selector .et_pb_main_blurb_image { margin-right: {$gap}; }";
            }

            $blocks[] = $this->block( $id . '-' . ( $i + 1 ), 'divi/blurb', $this->applyInheritedBlurbColors( $attrs ) );
        }

        if ( $blocks === [] ) {
            $this->engine->logWarning( "Icon list {$id}: no items; nothing was emitted." );
            return [];
        }

        // Module-level attrs go on the first item; margins are spread over first/last.
        foreach ( [ 'html', 'disabledOn', 'attributes' ] as $keep ) {
            if ( isset( $module['advanced'][ $keep ] ) ) {
                $blocks[0]['settings']['module']['advanced'][ $keep ] = $module['advanced'][ $keep ];
            }
            if ( isset( $module['decoration'][ $keep ] ) ) {
                $blocks[0]['settings']['module']['decoration'][ $keep ] = $module['decoration'][ $keep ];
            }
        }
        $this->spreadModuleSpacing( $blocks, $module );

        if ( $this->color( $settings, 'icon_color_hover', $id ) !== null || $this->color( $settings, 'icon_bg_hover', $id ) !== null ) {
            $this->engine->logNotCarriedOver( 'hover', $id, 'icon hover colours' );
        }
        if ( is_array( $settings['icon_border'] ?? null ) && array_filter( $settings['icon_border'], static fn( $v ) => is_string( $v ) && $v !== '' ) !== [] ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'icon border' );
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, [
            'list_type', 'list_icon', 'list_items', 'item_margin', 'icon_space', 'icon_bg', 'icon_bg_hover', 'icon_color',
            'icon_color_hover', 'icon_border', 'icon_border_color_hover', 'icon_size', 'icon_padding', 'text_typography',
            'text_typography_medium', 'text_typography_responsive', 'text_color',
        ] ) );

        return $blocks;
    }

    /** The row/column text colour cascade, as mapStyle('blurb') would apply it to one block. */
    private function applyInheritedBlurbColors( array $attrs ): array {
        $text = $this->engine->inheritedColor( 'text_color' );
        if ( $text !== null && ! isset( $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] ) ) {
            $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $text;
        }
        return $attrs;
    }
}
