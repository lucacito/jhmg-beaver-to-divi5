<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\IconMap;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Callout → divi/blurb (title, text, photo or icon), plus a
 * divi/button when the callout's call to action is a button.
 */
class CalloutConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_blurb_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'blurb', $node );
        $attrs   = $style['divi_attrs'];
        $handled = $style['handled_keys'];
        $mapper  = new StyleMapper();

        $title = trim( $this->text( $settings, 'title' ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = [ 'text' => $title ];
        }
        $tag = strtolower( $this->text( $settings, 'title_tag' ) );
        if ( preg_match( '/^h[1-6]$/', $tag ) ) {
            $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        }

        $text = trim( $this->text( $settings, 'text' ) );
        if ( $text !== '' ) {
            $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );
        }
        $mapper->applyTypography( $settings, 'content_typography', 'content.decoration.bodyFont.body.font', $attrs, $handled );
        $content_color = $this->color( $settings, 'content_color', $id );
        if ( $content_color !== null ) {
            $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $content_color;
        }

        $image_type = $this->text( $settings, 'image_type' ) ?: 'photo';
        if ( $image_type === 'photo' ) {
            $src = trim( $this->firstText( $settings, [ 'photo_src', 'photo_url' ] ) );
            if ( $src !== '' ) {
                $attrs['imageIcon']['innerContent']['desktop']['value'] = [ 'src' => $src ];
                $position = $this->text( $settings, 'photo_position' );
                $attrs['imageIcon']['advanced']['placement']['desktop']['value'] = in_array( $position, [ 'left', 'right' ], true ) ? 'left' : 'top';
            }
        } elseif ( $image_type === 'icon' ) {
            $icon_class = $this->text( $settings, 'icon' );
            if ( $icon_class !== '' ) {
                $mapped = IconMap::fromClass( $icon_class );
                if ( ! $mapped['exact'] ) {
                    $this->engine->logWarning( "Callout {$id}: icon '{$icon_class}' has no Divi equivalent; a star icon is used." );
                }
                $attrs['imageIcon']['innerContent']['desktop']['value'] = [ 'useIcon' => 'on', 'icon' => $mapped['icon'] ];
                $position = $this->text( $settings, 'icon_position' );
                $attrs['imageIcon']['advanced']['placement']['desktop']['value'] = str_starts_with( $position, 'left' ) || str_starts_with( $position, 'right' ) ? 'left' : 'top';
                $icon_color = $this->color( $settings, 'icon_color', $id );
                if ( $icon_color !== null ) {
                    $attrs['imageIcon']['advanced']['color']['desktop']['value'] = $icon_color;
                }
            }
        }

        $cta_type = $this->text( $settings, 'cta_type' );
        $link     = $this->linkValue( $settings, 'link' );
        $blocks   = [];

        if ( $cta_type === 'link' && isset( $link['url'] ) ) {
            $cta_text = trim( $this->text( $settings, 'cta_text' ) );
            $current  = $attrs['content']['innerContent']['desktop']['value'] ?? '';
            $anchor   = '<p><a href="' . esc_url( $link['url'] ) . '"' . ( isset( $link['target'] ) ? ' target="_blank"' : '' ) . '>' . esc_html( $cta_text !== '' ? $cta_text : $link['url'] ) . '</a></p>';
            $attrs['content']['innerContent']['desktop']['value'] = $current . $anchor;
        }

        $this->engine->logConverted( 'blurb' );
        $blocks[] = $this->block( $id, 'divi/blurb', $attrs );

        if ( $cta_type === 'button' ) {
            $button_settings = $this->buttonSettings( $settings );
            if ( isset( $link['url'] ) ) {
                $button_settings['link']        = $link['url'];
                $button_settings['link_target'] = $settings['link_target'] ?? '_self';
            }
            $blocks[] = ( new ButtonConverter( $this->engine ) )->convertSettings( $id . '-button', $button_settings, $node );
        }

        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'title', 'title_tag', 'text', 'content_color', 'content_typography', 'content_typography_medium', 'content_typography_responsive',
                'title_color', 'title_typography', 'image_type', 'photo', 'photo_src', 'photo_url', 'photo_position', 'photo_crop', 'photo_width', 'photo_width_unit', 'photo_align', 'photo_border', 'photo_source',
                'icon', 'sr_text', 'icon_position', 'icon_color', 'icon_hover_color', 'icon_bg_color', 'icon_bg_hover_color', 'icon_3d', 'icon_size', 'icon_size_unit', 'icon_duo_color1', 'icon_duo_color2',
                'cta_type', 'cta_text', 'link_color', 'link_hover_color', 'link_typography',
            ],
            $this->linkKeys( 'link' ),
            array_keys( $this->buttonSettings( $settings ) ),
            array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'btn_' ) ),
            $handled
        ) );

        return count( $blocks ) === 1 ? $blocks[0] : $blocks;
    }

    /** The callout's btn_* keys renamed to the button module's own keys. */
    private function buttonSettings( array $settings ): array {
        $button = [ 'type' => 'button' ];
        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || ! str_starts_with( $key, 'btn_' ) ) {
                continue;
            }
            $button[ substr( $key, 4 ) ] = $value;
        }
        return $button;
    }
}
