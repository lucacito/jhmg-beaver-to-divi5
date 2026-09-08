<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Video → divi/video for media-library files and YouTube /
 * Vimeo embeds (Divi resolves those through oEmbed); any other embed code is
 * kept verbatim in a divi/code block so it still plays.
 */
class VideoConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_video_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style  = $this->mapStyle( 'generic', $node );
        $mapped = array_merge(
            [ 'video_type', 'video', 'video_webm', 'embed_code', 'video_lightbox', 'poster', 'poster_src', 'poster_size', 'autoplay', 'loop', 'sticky_on_scroll',
              'play_pause', 'timer', 'time_rail', 'duration', 'volume', 'full_screen', 'schema_enabled', 'name', 'description', 'content_url', 'embed_url', 'thumbnail', 'thumbnail_src', 'up_date' ],
            $style['handled_keys']
        );

        $type  = $this->text( $settings, 'video_type' );
        $value = [];

        if ( $type === 'embed' ) {
            $embed = trim( $this->text( $settings, 'embed_code' ) );
            $url   = $this->oembedUrl( $embed );
            if ( $url === '' ) {
                $this->engine->logConverted( 'code' );
                $this->logUnmappedSettings( $id, $settings, $mapped );
                if ( $embed === '' ) {
                    $this->engine->logWarning( "Video {$id}: no embed code." );
                }
                return $this->codeBlock( $id, $embed );
            }
            $value['src'] = $url;
        } else {
            $src = $this->fileUrl( $settings, 'video', 'data' );
            if ( $src !== '' ) {
                $value['src'] = $src;
            }
            $webm = $this->fileUrl( $settings, 'video_webm', null );
            if ( $webm !== '' ) {
                $value['webm'] = $webm;
            }
        }

        $attrs = $style['divi_attrs'];
        $attrs = $this->deepMergeSettings( [ 'video' => [ 'innerContent' => [ 'desktop' => [ 'value' => $value ] ] ] ], $attrs );

        $poster = trim( $this->text( $settings, 'poster_src' ) );
        if ( $poster !== '' ) {
            $attrs['overlay']['innerContent']['desktop']['value']['image'] = [ 'src' => $poster ];
        }

        if ( $this->text( $settings, 'video_lightbox' ) === 'yes' ) {
            $this->engine->logNotCarriedOver( 'lightbox', $id, 'video opens in a lightbox' );
        }
        if ( empty( $value['src'] ) ) {
            $this->engine->logWarning( "Video missing source URL: {$id}" );
        }

        $this->engine->logConverted( 'video' );
        $this->logUnmappedSettings( $id, $settings, $mapped );

        return $this->block( $id, 'divi/video', $attrs );
    }

    /** A YouTube or Vimeo page URL out of an iframe, a bare URL, or a shortcode-free embed; '' otherwise. */
    private function oembedUrl( string $embed ): string {
        if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $embed, $m ) ) {
            return 'https://www.youtube.com/watch?v=' . $m[1];
        }
        if ( preg_match( '#vimeo\.com/(?:video/)?([0-9]+)#i', $embed, $m ) ) {
            return 'https://vimeo.com/' . $m[1];
        }
        if ( preg_match( '#^https?://\S+\.(mp4|m4v|webm|ogv)(\?\S*)?$#i', $embed ) ) {
            return $embed;
        }
        return '';
    }

    private function fileUrl( array $settings, string $key, ?string $data_key ): string {
        if ( $data_key !== null ) {
            $data = $settings[ $data_key ] ?? null;
            if ( is_array( $data ) && trim( $this->text( $data, 'url' ) ) !== '' ) {
                return trim( $this->text( $data, 'url' ) );
            }
        }
        $raw = $settings[ $key ] ?? '';
        if ( is_string( $raw ) && preg_match( '#^https?://#', $raw ) ) {
            return $raw;
        }
        if ( is_numeric( $raw ) && function_exists( 'wp_get_attachment_url' ) ) {
            return (string) wp_get_attachment_url( (int) $raw );
        }
        return '';
    }
}
