<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Gallery / Slideshow → divi/gallery over the same attachment ids. */
class GalleryConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_gallery_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $ids = [];
        foreach ( (array) ( $settings['photos'] ?? $settings['gallery'] ?? [] ) as $photo ) {
            if ( is_numeric( $photo ) ) {
                $ids[] = (int) $photo;
            } elseif ( is_array( $photo ) && is_numeric( $photo['id'] ?? null ) ) {
                $ids[] = (int) $photo['id'];
            }
        }
        if ( ! empty( $ids ) ) {
            $attrs['image']['advanced']['galleryIds']['desktop']['value'] = $ids;
        } else {
            $this->engine->logWarning( "Gallery {$id}: no media-library photos found" . ( $this->text( $settings, 'source' ) === 'smugmug' ? ' (SmugMug galleries cannot be carried)' : '' ) . '.' );
        }
        if ( ( $settings['type'] ?? '' ) === 'slideshow' ) {
            $this->engine->logWarning( "Slideshow {$id} became a gallery grid." );
        }

        $this->engine->logConverted( 'gallery' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'photos', 'gallery', 'source', 'feed_url', 'layout', 'columns', 'photo_size', 'photo_spacing', 'show_captions', 'click_action', 'thumbs', 'speed', 'transition', 'transition_duration', 'auto_play', 'play_pause', 'arrows', 'crop', 'nav_type' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/gallery', $attrs );
    }
}
