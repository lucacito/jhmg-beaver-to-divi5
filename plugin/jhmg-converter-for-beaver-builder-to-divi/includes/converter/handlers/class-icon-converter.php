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
 * Beaver Builder Icon → divi/icon, or divi/blurb (icon + text) when the
 * module carries text beside the icon.
 */
class IconConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_icon_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $mapped = IconMap::fromClass( $this->text( $settings, 'icon' ) );
        if ( ! $mapped['exact'] ) {
            $this->engine->logWarning( "Icon {$id}: '" . $this->text( $settings, 'icon' ) . "' has no Divi equivalent; a star icon is used." );
        }

        $color = $this->color( $settings, 'color', $id );
        $size  = Size::fromSettings( $settings, 'size', 'size' );
        $text  = trim( $this->text( $settings, 'text' ) );
        $link  = $this->linkValue( $settings, 'link' );

        if ( $this->color( $settings, 'hover_color', $id ) !== null || $this->color( $settings, 'bg_hover_color', $id ) !== null ) {
            $this->engine->logNotCarriedOver( 'hover', $id, 'icon hover colours' );
        }

        $consumed = array_merge(
            [ 'icon', 'text', 'size', 'size_unit', 'size_medium', 'size_responsive', 'color', 'hover_color', 'bg_color', 'bg_hover_color', 'three_d', 'duo_color1', 'duo_color2', 'sr_text', 'text_spacing', 'text_color', 'text_typography', 'text_typography_medium', 'text_typography_responsive' ],
            $this->linkKeys( 'link' )
        );

        if ( $text === '' ) {
            $style = $this->mapStyle( 'icon', $node );
            $attrs = $style['divi_attrs'];

            $value = $mapped['icon'];
            if ( isset( $link['url'] ) ) {
                $value['linkUrl'] = $link['url'];
                if ( isset( $link['target'] ) ) {
                    $value['linkTarget'] = '_blank';
                }
            }
            $attrs = $this->deepMergeSettings( [ 'icon' => [ 'innerContent' => [ 'desktop' => [ 'value' => $value ] ] ] ], $attrs );
            if ( $color !== null ) {
                $attrs['icon']['advanced']['color']['desktop']['value'] = $color;
            }
            $attrs['icon']['advanced']['size']['desktop']['value'] = $size !== '' ? $size : '30px';

            $this->engine->logConverted( 'icon' );
            $this->logUnmappedSettings( $id, $settings, array_merge( $consumed, $style['handled_keys'] ) );

            return $this->block( $id, 'divi/icon', $attrs );
        }

        // Icon with text: a blurb with the icon on the left and the text as body.
        $style = $this->mapStyle( 'blurb', $node );
        $attrs = $style['divi_attrs'];

        $attrs['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $mapped['icon'] ];
        $attrs['imageIcon']['advanced']['placement']['desktop']['value'] = 'left';
        if ( $color !== null ) {
            $attrs['imageIcon']['advanced']['color']['desktop']['value'] = $color;
        }
        $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );

        $handled = $style['handled_keys'];
        ( new StyleMapper() )->applyTypography( $settings, 'text_typography', 'content.decoration.bodyFont.body.font', $attrs, $handled );
        $text_color = $this->color( $settings, 'text_color', $id );
        if ( $text_color !== null ) {
            $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $text_color;
        }
        if ( isset( $link['url'] ) ) {
            $attrs['module']['advanced']['link']['desktop']['value'] = $link;
        }

        $this->engine->logConverted( 'blurb' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $consumed, $handled ) );

        return $this->block( $id, 'divi/blurb', $attrs );
    }
}
