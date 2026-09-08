<?php
/**
 * Beaver Builder colour values, normalised for Divi.
 *
 * Beaver Builder stores hex colours without the leading `#` ("64A6BD"),
 * rgb()/rgba() strings as-is, and — since its global colours arrived — CSS
 * custom-property references such as `var(--fl-global-brand)`.
 */

namespace BeaverDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Color {

    /** Filter name through which a site (or Pro) supplies global colour values: slug ⇒ colour. */
    const GLOBALS_FILTER = 'bbdc_global_colors';

    /**
     * A CSS colour Divi can use, or null when the value is empty or unknown.
     * Global colour references resolve through resolveGlobal(); when they
     * cannot, null is returned so the caller can report the gap instead of
     * painting with an invented colour.
     */
    public static function normalize( mixed $raw ): ?string {
        if ( ! is_string( $raw ) ) {
            return null;
        }

        $value = trim( $raw );
        if ( $value === '' ) {
            return null;
        }

        if ( self::isGlobalRef( $value ) ) {
            return self::resolveGlobal( $value );
        }

        if ( preg_match( '/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value, $m ) ) {
            return '#' . $m[1];
        }

        if ( preg_match( '/^(rgba?|hsla?)\(/i', $value ) || $value === 'transparent' || $value === 'currentColor' ) {
            return $value;
        }

        // A named colour ("white") — pass through; the browser understands it.
        if ( preg_match( '/^[a-z]+$/i', $value ) ) {
            return $value;
        }

        return null;
    }

    public static function isGlobalRef( string $value ): bool {
        return str_starts_with( $value, 'var(' ) || str_starts_with( $value, 'fl-global-' ) || str_starts_with( $value, 'global_' );
    }

    /**
     * The slug inside a global reference: `var(--fl-global-brand)` → `brand`,
     * `fl-global-brand` → `brand`.
     */
    public static function globalSlug( string $value ): string {
        $slug = trim( $value );
        if ( preg_match( '/var\(\s*--([a-z0-9_-]+)\s*\)/i', $slug, $m ) ) {
            $slug = $m[1];
        }
        foreach ( [ 'fl-global-', 'global_' ] as $prefix ) {
            if ( str_starts_with( $slug, $prefix ) ) {
                $slug = substr( $slug, strlen( $prefix ) );
            }
        }
        return $slug;
    }

    /** Resolved colour for a global reference, or null when nothing on the site knows it. */
    public static function resolveGlobal( string $value ): ?string {
        $slug    = self::globalSlug( $value );
        // Literal so Plugin Check can read the hook name; keep in step with self::GLOBALS_FILTER.
        $globals = function_exists( 'apply_filters' ) ? apply_filters( 'bbdc_global_colors', [] ) : [];

        if ( ! is_array( $globals ) ) {
            return null;
        }

        $hit = $globals[ $slug ] ?? $globals[ 'fl-global-' . $slug ] ?? null;
        if ( ! is_string( $hit ) || $hit === '' ) {
            return null;
        }

        return self::isGlobalRef( $hit ) ? null : self::normalize( $hit );
    }

    /** `#rrggbb` + opacity (0–1) → `rgba()`; non-hex colours are returned unchanged. */
    public static function withOpacity( string $color, float $opacity ): string {
        if ( ! preg_match( '/^#([0-9a-f]{6})$/i', $color, $m ) ) {
            return $color;
        }
        [ $r, $g, $b ] = sscanf( $m[1], '%02x%02x%02x' );

        return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( number_format( max( 0, min( 1, $opacity ) ), 2, '.', '' ), '0' ), '.' ) );
    }
}
