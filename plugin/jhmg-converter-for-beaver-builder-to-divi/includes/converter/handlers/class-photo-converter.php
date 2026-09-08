<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Photo → divi/image, plus a divi/text for a caption shown
 * below the photo (Divi's image module has no caption of its own).
 */
class PhotoConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_image_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'image', $node );
        $attrs = $style['divi_attrs'];

        $src = $this->source( $settings );
        $alt = $this->alt( $settings );

        $image = [];
        if ( $src !== '' ) {
            $image['src'] = $src;
        }
        if ( $alt !== '' ) {
            $image['alt'] = $alt;
        }

        $link_type = $this->text( $settings, 'link_type' );
        if ( $link_type === 'url' ) {
            $link = $this->linkValue( $settings, 'link_url' );
            if ( ! empty( $link ) ) {
                $image['linkUrl'] = $link['url'];
                if ( isset( $link['target'] ) ) {
                    $image['linkTarget'] = '_blank';
                }
            }
        } elseif ( $link_type === 'lightbox' ) {
            $attrs['image']['advanced']['lightbox']['desktop']['value'] = 'on';
        } elseif ( $link_type === 'file' && $src !== '' ) {
            $image['linkUrl'] = $src;
        } elseif ( $link_type === 'page' ) {
            $this->engine->logNotCarriedOver( 'lightbox', $id, 'link to the attachment page' );
        }

        // Divi 5's fit group handles object-fit; a circle crop is a 50% radius.
        if ( $this->text( $settings, 'crop' ) === 'circle' ) {
            $attrs['image']['decoration']['border']['desktop']['value']['radius'] = [ 'topLeft' => '50%', 'topRight' => '50%', 'bottomRight' => '50%', 'bottomLeft' => '50%' ];
        } elseif ( $this->text( $settings, 'crop' ) !== '' ) {
            $this->engine->logWarning( "Photo {$id}: the '" . $this->text( $settings, 'crop' ) . "' crop is a Beaver Builder server-side crop; the original image is used." );
        }

        $attrs = $this->deepMergeSettings( [ 'image' => [ 'innerContent' => [ 'desktop' => [ 'value' => $image ] ] ] ], $attrs );

        $this->engine->logConverted( 'image' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'photo', 'photo_src', 'photo_source', 'photo_url', 'link_type', 'crop', 'show_caption', 'caption', 'caption_typography', 'fill_container' ],
            $this->linkKeys( 'link_url' ),
            $style['handled_keys']
        ) );

        if ( $src === '' ) {
            $this->engine->logWarning( "Photo {$id}: no image URL found (photo_src/photo_url both empty)." );
        }
        if ( $alt === '' ) {
            $this->engine->logWarning( "Image missing alt text: {$id}" );
        }

        $blocks = [ $this->block( $id, 'divi/image', $attrs ) ];

        $caption = $this->text( $settings, 'caption' );
        if ( $caption !== '' && in_array( $this->text( $settings, 'show_caption' ), [ 'below', 'hover' ], true ) ) {
            $blocks[] = $this->block( $id . '-caption', 'divi/text', [
                'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => '<p class="bdc-photo-caption">' . esc_html( $caption ) . '</p>' ] ] ],
            ] );
            $this->engine->logConverted( 'text' );
        }

        return count( $blocks ) === 1 ? $blocks[0] : $blocks;
    }

    private function source( array $settings ): string {
        if ( $this->text( $settings, 'photo_source' ) === 'url' ) {
            return trim( $this->text( $settings, 'photo_url' ) );
        }
        $src = trim( $this->text( $settings, 'photo_src' ) );
        if ( $src === '' ) {
            $data = $settings['data'] ?? null;
            if ( is_array( $data ) ) {
                $src = trim( $this->text( $data, 'url' ) );
            }
        }
        if ( $src === '' && function_exists( 'wp_get_attachment_url' ) && is_numeric( $settings['photo'] ?? null ) ) {
            $src = (string) wp_get_attachment_url( (int) $settings['photo'] );
        }
        return $src;
    }

    /** Beaver Builder falls back alt → description → caption → title at render time. */
    private function alt( array $settings ): string {
        $data = is_array( $settings['data'] ?? null ) ? $settings['data'] : [];
        foreach ( [ 'alt', 'description', 'caption', 'title' ] as $key ) {
            $v = $this->text( $data, $key );
            if ( trim( $v ) !== '' ) {
                return trim( $v );
            }
        }
        if ( $this->text( $settings, 'photo_source' ) === 'url' ) {
            $caption = trim( $this->text( $settings, 'caption' ) );
            if ( $caption !== '' ) {
                return $caption;
            }
            return trim( $this->text( $settings, 'url_title' ) );
        }
        return trim( $this->text( $settings, 'caption' ) );
    }
}
