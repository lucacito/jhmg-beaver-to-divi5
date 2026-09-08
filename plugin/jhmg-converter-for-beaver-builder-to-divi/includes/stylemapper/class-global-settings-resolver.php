<?php
/**
 * Beaver Builder's global layout settings, as far as the conversion needs them.
 *
 * Beaver Builder keeps them in the `_fl_builder_settings` option (an object
 * merged over FLBuilderModel::get_global_settings() defaults). Only the row
 * width family matters here: Divi's own defaults replace everything else, and
 * injecting Beaver Builder's 20px row padding / module margins into every node
 * would fight them.
 */

namespace BeaverDivi5Converter\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GlobalSettingsResolver {

    const OPTION = '_fl_builder_settings';
    const FILTER = 'bdc_global_settings';

    /** Beaver Builder's shipped defaults (includes/global-settings.php). */
    const DEFAULTS = [
        'row_width'                 => '1100',
        'row_width_unit'            => 'px',
        'row_width_default'         => 'fixed',
        'row_content_width_default' => 'fixed',
        'row_padding'               => '20',
        'module_margins'            => '20',
    ];

    /** @return array<string,mixed> Defaults overlaid with the site's option and the filter. */
    public static function all(): array {
        $stored = function_exists( 'get_option' ) ? get_option( self::OPTION, [] ) : [];
        if ( is_object( $stored ) ) {
            $stored = get_object_vars( $stored );
        }
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        // An empty string means "unset" in Beaver Builder's forms.
        $stored = array_filter( $stored, static fn( $v ) => $v !== '' && $v !== null );

        $merged = array_merge( self::DEFAULTS, $stored );

        return function_exists( 'apply_filters' ) ? (array) apply_filters( self::FILTER, $merged ) : $merged;
    }

    /** Fixed-row content width as a CSS length, e.g. "1100px". */
    public static function rowWidth(): string {
        $all  = self::all();
        $unit = is_string( $all['row_width_unit'] ?? null ) && $all['row_width_unit'] !== '' ? $all['row_width_unit'] : 'px';

        return \BeaverDivi5Converter\Helpers\Size::withUnit( $all['row_width'] ?? '', $unit );
    }

    /** 'fixed' | 'full' — what a row with no explicit `width` means. */
    public static function rowWidthDefault(): string {
        return ( self::all()['row_width_default'] ?? 'fixed' ) === 'full' ? 'full' : 'fixed';
    }

    /** 'fixed' | 'full' — what a full-width row's content does by default. */
    public static function rowContentWidthDefault(): string {
        return ( self::all()['row_content_width_default'] ?? 'fixed' ) === 'full' ? 'full' : 'fixed';
    }
}
