<?php
/**
 * Beaver Themer field connections → Divi 5 dynamic content.
 *
 * Beaver Builder stores a field connection inline as a `[wpbb object:field
 * attr='value']` shortcode, e.g. `© [wpbb site:year format='Y'] Acme` or a
 * heading whose text is `[wpbb post:title]`. Divi 5 stores dynamic content as
 * `$variable({"type":"content","value":{"name":"post_title","settings":{}}})$`
 * and resolves the token wherever it appears inside a string (DynamicData::
 * get_variable_values), so a connection can be swapped in place.
 *
 * Only connections with a documented Divi option are translated; anything else
 * is left as text and reported, never guessed.
 */

namespace BeaverDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class FieldConnections {

    /** `[wpbb object:field attr='v' attr="v" attr=v]` — Beaver Themer's connection shortcode. */
    const PATTERN = '/\[wpbb\s+([a-z_]+)(?::([a-z_]+))?((?:\s+[a-z_]+=(?:\'[^\']*\'|"[^"]*"|[^\s\]]+))*)\s*\]/i';

    /**
     * @return array{text: string, translated: string[], unmapped: string[]}
     *   `translated` lists the connections replaced by Divi dynamic content,
     *   `unmapped` the shortcodes left in the text.
     */
    public static function translate( string $text ): array {
        $translated = [];
        $unmapped   = [];
        if ( ! str_contains( $text, '[wpbb' ) ) {
            return [ 'text' => $text, 'translated' => [], 'unmapped' => [] ];
        }

        $result = preg_replace_callback( self::PATTERN, static function ( array $m ) use ( &$translated, &$unmapped ): string {
            $object = strtolower( $m[1] );
            $field  = strtolower( $m[2] ?? '' );
            $atts   = self::attributes( $m[3] ?? '' );
            $mapped = self::map( $object, $field, $atts );
            if ( $mapped === null ) {
                $unmapped[] = $m[0];
                return $m[0];
            }
            $translated[] = $m[0];
            return self::token( $mapped[0], $mapped[1] );
        }, $text );

        return [ 'text' => is_string( $result ) ? $result : $text, 'translated' => $translated, 'unmapped' => $unmapped ];
    }

    /** A Divi 5 dynamic-content token for the given option and settings. */
    public static function token( string $name, array $settings = [] ): string {
        $json = json_encode( [ 'type' => 'content', 'value' => [ 'name' => $name, 'settings' => (object) $settings ] ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return '$variable(' . $json . ')$';
    }

    /**
     * Beaver Themer connection → [Divi option name, settings], or null when Divi
     * has no equivalent. Option names and setting keys come from Divi's
     * DynamicContentOption* classes (builder-5/server/…/DynamicContent).
     *
     * @return array{0: string, 1: array}|null
     */
    private static function map( string $object, string $field, array $atts ): ?array {
        $format = $atts['format'] ?? '';
        $date   = static fn( string $name ) => [ $name, $format !== '' ? [ 'date_format' => 'custom', 'custom_date_format' => $format ] : [] ];

        if ( $object === 'post' ) {
            switch ( $field ) {
                case 'title':
                    return [ 'post_title', [] ];
                case 'excerpt':
                    $settings = [];
                    if ( isset( $atts['length'] ) && is_numeric( $atts['length'] ) ) {
                        $settings['words'] = (string) (int) $atts['length'];
                    }
                    return [ 'post_excerpt', $settings ];
                case 'date':
                    return $date( 'post_date' );
                case 'modified':
                case 'modified_date':
                    return $date( 'post_modified_date' );
                case 'url':
                case 'permalink':
                    return [ 'post_link_url', [] ];
                case 'link':
                    return [ 'post_link', [] ];
                case 'featured_image':
                    return [ 'post_featured_image', isset( $atts['size'] ) ? [ 'thumbnail_size' => $atts['size'] ] : [] ];
                case 'author_name':
                    $formats = [ 'display' => 'display_name', 'first' => 'first_name', 'last' => 'last_name', 'first_last' => 'first_last_name', 'nickname' => 'nickname', 'username' => 'username' ];
                    return [ 'post_author', [ 'name_format' => $formats[ $atts['type'] ?? 'display' ] ?? 'display_name' ] ];
                case 'author_bio':
                    return [ 'post_author_bio', [] ];
                case 'author_profile_picture':
                    return [ 'post_author_profile_picture', [] ];
                case 'author_url':
                case 'author_profile_url':
                    return [ 'post_author_url', [] ];
                case 'custom_field':
                case 'acf':
                    $key = $atts['key'] ?? $atts['name'] ?? '';
                    return $key !== '' ? [ 'post_meta_key', [ 'meta_key' => $key ] ] : null;
            }
            return null;
        }

        if ( $object === 'acf' ) {
            $key = $atts['name'] ?? $atts['key'] ?? '';
            return $key !== '' ? [ 'post_meta_key', [ 'meta_key' => $key ] ] : null;
        }

        if ( $object === 'site' ) {
            switch ( $field ) {
                case 'title':
                    return [ 'site_title', [] ];
                case 'tagline':
                case 'description':
                    return [ 'site_tagline', [] ];
                case 'year':
                    return [ 'current_date', [ 'date_format' => 'custom', 'custom_date_format' => $format !== '' ? $format : 'Y' ] ];
                case 'date':
                    return $date( 'current_date' );
            }
        }

        return null;
    }

    /** @return array<string, string> */
    private static function attributes( string $raw ): array {
        $atts = [];
        if ( preg_match_all( '/([a-z_]+)=(?:\'([^\']*)\'|"([^"]*)"|([^\s\]]+))/i', $raw, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $match ) {
                $atts[ strtolower( $match[1] ) ] = $match[2] !== '' ? $match[2] : ( $match[3] !== '' ? $match[3] : ( $match[4] ?? '' ) );
            }
        }
        return $atts;
    }
}
