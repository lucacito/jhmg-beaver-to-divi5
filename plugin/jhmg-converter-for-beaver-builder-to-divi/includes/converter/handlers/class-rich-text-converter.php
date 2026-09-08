<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Text Editor (rich-text) → divi/text.
 *
 * Beaver Builder renders the text through wpautop() at display time, so a
 * value with bare line breaks and no block-level tags gets the same treatment
 * here; Divi's text module renders its HTML as stored.
 */
class RichTextConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_text_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'text', $node );
        $attrs = $style['divi_attrs'];

        $attrs['content']['innerContent']['desktop']['value'] = $this->autop( $this->text( $settings, 'text' ) );

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'text' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/text', $attrs );
    }

    /** wpautop() when WordPress is loaded; a minimal paragraph wrap otherwise. */
    public static function autop( string $html ): string {
        $html = trim( $html );
        if ( $html === '' ) {
            return '';
        }
        if ( function_exists( 'wpautop' ) ) {
            return trim( wpautop( $html ) );
        }
        if ( preg_match( '/<(p|div|h[1-6]|ul|ol|table|blockquote|pre|section|article|figure)\b/i', $html ) ) {
            return $html;
        }
        $paragraphs = preg_split( '/\n\s*\n/', $html ) ?: [ $html ];
        return implode( "\n", array_map( static fn( string $p ) => '<p>' . trim( $p ) . '</p>', array_filter( $paragraphs, static fn( $p ) => trim( $p ) !== '' ) ) );
    }
}
