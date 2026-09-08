<?php
/**
 * Beaver Builder size values.
 *
 * A unit field stores its number in `<name>` and its unit in a sibling key
 * `<name>_unit` (`min_height` = "70", `min_height_unit` = "vh"); dimension
 * fields share one unit key for all four sides (`padding_unit`). Responsive
 * variants insert the breakpoint before `_unit`: `padding_medium_unit`.
 */

namespace BeaverDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Size {

    /**
     * "20" + "%" → "20%"; "1.5" + "em" → "1.5em"; "" → ""; "20px" → "20px"
     * (a value that already carries a unit is trusted).
     */
    public static function withUnit( mixed $value, ?string $unit, string $default_unit = 'px' ): string {
        if ( is_int( $value ) || is_float( $value ) ) {
            $value = (string) $value;
        }
        if ( ! is_string( $value ) ) {
            return '';
        }

        $value = trim( $value );
        if ( $value === '' ) {
            return '';
        }

        if ( ! is_numeric( $value ) ) {
            // "auto", "calc(...)", "20px" — already a CSS length.
            return preg_match( '/^-?[0-9.]+[a-z%]+$/i', $value ) || in_array( $value, [ 'auto', 'inherit', 'initial' ], true ) || str_starts_with( $value, 'calc(' )
                ? $value
                : '';
        }

        // null means "no unit recorded" → the field's default; an explicit ''
        // means unitless, which is how Beaver Builder stores line-height.
        $unit = $unit === null ? $default_unit : trim( $unit );

        return $value . $unit;
    }

    /**
     * Reads `<key><suffix>` with its unit `<root><suffix>_unit`, e.g.
     * ('padding_top', 'padding', '_medium') → padding_top_medium + padding_medium_unit.
     */
    public static function fromSettings( array $settings, string $key, string $unit_root, string $suffix = '', string $default_unit = 'px' ): string {
        $value = $settings[ $key . $suffix ] ?? null;
        $unit  = $settings[ $unit_root . $suffix . '_unit' ] ?? null;

        // Dimension fields always record a unit; a blank one is Beaver Builder's
        // "px" default, never "unitless".
        $unit = is_string( $unit ) && trim( $unit ) !== '' ? $unit : null;

        return self::withUnit( $value, $unit, $default_unit );
    }

    /** A BB `{length, unit}` typography-style value → CSS length, '' when empty. */
    public static function fromLength( mixed $raw, string $default_unit = 'px' ): string {
        if ( ! is_array( $raw ) ) {
            return '';
        }
        $unit = $raw['unit'] ?? null;

        return self::withUnit( $raw['length'] ?? '', is_string( $unit ) ? $unit : null, $default_unit );
    }

    /** "20px" → 20.0; "" → null. */
    public static function number( string $css ): ?float {
        if ( $css === '' || ! preg_match( '/^(-?[0-9.]+)/', $css, $m ) ) {
            return null;
        }
        return (float) $m[1];
    }
}
