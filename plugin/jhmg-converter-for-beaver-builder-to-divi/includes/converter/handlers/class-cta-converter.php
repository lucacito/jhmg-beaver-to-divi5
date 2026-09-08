<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\IconMap;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Call to Action → divi/cta. */
class CtaConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_cta_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'cta', $node );
        $attrs   = $style['divi_attrs'];
        $handled = $style['handled_keys'];
        $mapper  = new StyleMapper();

        $title = trim( $this->text( $settings, 'title' ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
        }
        $tag = strtolower( $this->text( $settings, 'title_tag' ) );
        if ( preg_match( '/^h[1-6]$/', $tag ) ) {
            $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        $text = trim( $this->text( $settings, 'text' ) );
        if ( $text !== '' ) {
            $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );
        }
        $mapper->applyTypography( $settings, 'text_typography', 'content.decoration.bodyFont.body.font', $attrs, $handled );
        $text_color = $this->color( $settings, 'text_color', $id );
        if ( $text_color !== null ) {
            $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $text_color;
        }

        $button = [];
        $btn    = trim( $this->text( $settings, 'btn_text' ) );
        if ( $btn !== '' ) {
            $button['text'] = $btn;
        }
        $link = $this->linkValue( $settings, 'btn_link' );
        if ( isset( $link['url'] ) ) {
            $button['linkUrl'] = $link['url'];
            if ( isset( $link['target'] ) ) {
                $button['linkTarget'] = '_blank';
            }
        }
        if ( ! empty( $button ) ) {
            $attrs['button']['innerContent']['desktop']['value'] = $button;
        }

        $btn_bg = $this->color( $settings, 'btn_bg_color', $id );
        if ( $btn_bg !== null ) {
            $attrs['button']['decoration']['background']['desktop']['value']['color'] = $btn_bg;
        }
        $btn_color = $this->color( $settings, 'btn_text_color', $id );
        if ( $btn_color !== null ) {
            $attrs['button']['decoration']['font']['font']['desktop']['value']['color'] = $btn_color;
        }
        $mapper->applyTypography( $settings, 'btn_typography', 'button.decoration.font.font', $attrs, $handled );
        $mapper->applySpacing( $settings, 'btn_padding', 'button.decoration.spacing', $attrs, $handled, 'padding' );
        $mapper->applyBorder( $settings, 'btn_border', 'button.decoration.border', 'button.decoration.boxShadow', $attrs, $handled );
        $mapper->applySpacing( $settings, 'wrap_padding', 'module.decoration.spacing', $attrs, $handled, 'padding' );

        $icon_class = $this->text( $settings, 'btn_icon' );
        if ( $icon_class !== '' ) {
            $mapped = IconMap::fromClass( $icon_class );
            $attrs['button']['decoration']['button']['desktop']['value']['icon'] = [
                'enable'    => 'on',
                'settings'  => $mapped['icon'],
                'placement' => $this->text( $settings, 'btn_icon_position' ) === 'before' ? 'left' : 'right',
                'onHover'   => $this->text( $settings, 'btn_icon_animation' ) === 'enable' ? 'on' : 'off',
            ];
        }
        if ( $this->color( $settings, 'btn_bg_hover_color', $id ) !== null || $this->color( $settings, 'btn_text_hover_color', $id ) !== null ) {
            $this->engine->logNotCarriedOver( 'hover', $id, 'button hover colours' );
        }

        $this->engine->logConverted( 'cta' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'title', 'title_tag', 'text', 'text_color', 'text_typography', 'text_typography_medium', 'text_typography_responsive', 'layout', 'alignment', 'title_color', 'title_typography' ],
            $this->linkKeys( 'btn_link' ),
            array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'btn_' ) || str_starts_with( $k, 'wrap_padding' ) ),
            $handled
        ) );

        return $this->block( $id, 'divi/cta', $attrs );
    }
}
