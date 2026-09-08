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
    const FILTER = 'bbdc_global_settings';

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

        // Literal so Plugin Check can read the hook name; keep in step with self::FILTER.
        return function_exists( 'apply_filters' ) ? (array) apply_filters( 'bbdc_global_settings', $merged ) : $merged;
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

    /**
     * The padding Beaver Builder gives every row that sets none of its own
     * (global settings → Rows → Padding, 20px all round by default), as a Divi
     * spacing value. Divi's own section default is 54px top and bottom, which
     * is why converted pages ran taller than the originals.
     *
     * @return array{top: string, right: string, bottom: string, left: string, syncVertical: string, syncHorizontal: string}
     */
    public static function rowPadding(): array {
        return self::dimension( 'row_padding', '20' ) ?? [ 'top' => '20px', 'right' => '20px', 'bottom' => '20px', 'left' => '20px', 'syncVertical' => 'off', 'syncHorizontal' => 'off' ];
    }

    /**
     * The margin Beaver Builder gives every module that sets none of its own
     * (global settings → Modules → Margins, 20px all round by default). Its
     * `.fl-module` clearfix keeps these margins from collapsing, so two stacked
     * modules sit 40px apart and a module sits 40px inside a default row.
     *
     * @return array|null Null when the site cleared the global value.
     */
    public static function moduleMargins(): ?array {
        return self::dimension( 'module_margins', '20' );
    }

    /**
     * Global column padding (global settings → Columns → Padding). Beaver
     * Builder ships it empty, so this is null unless the site set one.
     */
    public static function columnPadding(): ?array {
        return self::dimension( 'column_padding', '' );
    }

    /**
     * A global "dimension" field (`<key>`, `<key>_top`…`<key>_left`, `<key>_unit`)
     * as a Divi spacing value, or null when every side is blank.
     */
    private static function dimension( string $key, string $shipped_default ): ?array {
        $all   = self::all();
        $unit  = is_string( $all[ $key . '_unit' ] ?? null ) && $all[ $key . '_unit' ] !== '' ? $all[ $key . '_unit' ] : 'px';
        $value = [];
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $raw = $all[ $key . '_' . $side ] ?? $all[ $key ] ?? $shipped_default;
            $raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
            if ( $raw === '' ) {
                continue;
            }
            $value[ $side ] = \BeaverDivi5Converter\Helpers\Size::withUnit( $raw, $unit );
        }
        if ( $value === [] ) {
            return null;
        }
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $value[ $side ] = $value[ $side ] ?? '0px';
        }
        return $value + [ 'syncVertical' => 'off', 'syncHorizontal' => 'off' ];
    }
}
