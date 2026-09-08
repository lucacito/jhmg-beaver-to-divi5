<?php
/**
 * Beaver Builder icon classes → Divi 5 icon attribute values.
 *
 * Beaver Builder stores an icon as a CSS class string: Font Awesome 5
 * (`fas fa-check`, `far fa-envelope`, `fab fa-facebook-f`), Dashicons
 * (`dashicons dashicons-wordpress-alt`) or Foundation (`fi-heart`). Divi 5
 * stores `{type, unicode, weight}` and ships Font Awesome in its icon library,
 * so FA classes map to the identical glyph via data/fa-icons.json (generated
 * from Divi's own iconList.json by scripts/build-fa-icon-map.php). Other
 * icon fonts have no Divi equivalent and fall back to a star, reported.
 */

namespace BeaverDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IconMap {

    const FALLBACK = [ 'unicode' => '&#xf005;', 'type' => 'fa', 'weight' => '900' ]; // fa-star, solid

    private static ?array $map = null;

    /**
     * @return array{icon: array{type:string,unicode:string,weight:string}, exact: bool, name: string}
     */
    public static function fromClass( string $class ): array {
        $tokens = preg_split( '/\s+/', trim( $class ) ) ?: [];
        $name   = '';
        $prefix = '';

        foreach ( $tokens as $token ) {
            if ( in_array( $token, [ 'fa', 'fas', 'far', 'fab', 'fal', 'fad' ], true ) ) {
                $prefix = $token;
                continue;
            }
            if ( str_starts_with( $token, 'fa-' ) ) {
                $name = substr( $token, 3 );
            }
        }

        if ( $name === '' ) {
            return [ 'icon' => self::FALLBACK, 'exact' => false, 'name' => trim( $class ) ];
        }

        $entry = self::map()[ strtolower( $name ) ] ?? null;
        if ( $entry === null ) {
            return [ 'icon' => self::FALLBACK, 'exact' => false, 'name' => $name ];
        }

        $weights = $entry['w'] ?? [ 900 ];
        $wanted  = $prefix === 'far' || $prefix === 'fab' || $prefix === 'fal' ? 400 : 900;
        $weight  = in_array( $wanted, $weights, true ) ? $wanted : (int) reset( $weights );

        return [
            'icon'  => [ 'unicode' => (string) $entry['u'], 'type' => 'fa', 'weight' => (string) $weight ],
            'exact' => true,
            'name'  => $name,
        ];
    }

    public static function isFontAwesome( string $class ): bool {
        return (bool) preg_match( '/(^|\s)fa-[a-z0-9-]+/i', $class );
    }

    /** @return array<string,array{u:string,w:int[]}> */
    private static function map(): array {
        if ( self::$map === null ) {
            $file      = BBDC_PLUGIN_DIR . 'data/fa-icons.json';
            $decoded   = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
            self::$map = is_array( $decoded ) ? $decoded : [];
        }

        return self::$map;
    }
}
