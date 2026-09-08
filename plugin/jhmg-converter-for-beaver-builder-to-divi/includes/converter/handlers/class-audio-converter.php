<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Audio → divi/audio (first track, or the link). */
class AudioConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_audio_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $url   = '';

        if ( $this->text( $settings, 'audio_type' ) === 'link' ) {
            $url = trim( $this->text( $settings, 'link' ) );
        } else {
            $data = $settings['data'] ?? null;
            if ( is_array( $data ) ) {
                $url = trim( $this->text( $data, 'url' ) );
            }
            $audios = $settings['audios'] ?? [];
            if ( $url === '' && is_array( $audios ) && ! empty( $audios ) ) {
                $first = reset( $audios );
                if ( is_string( $first ) && preg_match( '#^https?://#', $first ) ) {
                    $url = $first;
                } elseif ( is_numeric( $first ) && function_exists( 'wp_get_attachment_url' ) ) {
                    $url = (string) wp_get_attachment_url( (int) $first );
                }
                if ( count( $audios ) > 1 ) {
                    $this->engine->logWarning( "Audio {$id}: playlist of " . count( $audios ) . ' tracks reduced to the first track.' );
                }
            }
        }

        $attrs = $style['divi_attrs'];
        if ( $url !== '' ) {
            $attrs['audio']['innerContent']['desktop']['value'] = $url;
        } else {
            $this->engine->logWarning( "Audio {$id}: no audio URL found." );
        }
        $data = is_array( $settings['data'] ?? null ) ? $settings['data'] : [];
        if ( trim( $this->text( $data, 'title' ) ) !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = trim( $this->text( $data, 'title' ) );
        }

        $this->engine->logConverted( 'audio' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'audio_type', 'audios', 'link', 'autoplay', 'loop', 'style', 'tracklist', 'tracknumbers', 'images', 'artists' ],
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/audio', $attrs );
    }
}
