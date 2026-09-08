<?php
/**
 * Settings that Beaver Builder add-ons inject into every row, column and
 * module of a site, whether or not the feature is used.
 *
 * Ultimate Addons for Beaver Builder (UABB) and PowerPack extend the row and
 * column settings forms with gradients, animated/particle backgrounds, shape
 * separators, expandable rows, down arrows and column shadows. Every node
 * saved on such a site carries all of those keys at their defaults — around a
 * hundred per row. They only take effect when the family's gate is on (an
 * "enable" toggle set to yes, or a background type that belongs to the add-on).
 *
 * The converter treats an inactive family as inert: its keys are neither
 * mapped nor listed as skipped, and the report counts how many nodes carried
 * them. An active family is a real feature the page loses; it is reported once
 * per node under "not carried over", naming the add-on and the feature.
 */

namespace BeaverDivi5Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AddonSettings {

    /** Background types Beaver Builder itself offers on rows and columns. */
    const CORE_BG_TYPES = [ '', 'none', 'color', 'photo', 'video', 'slideshow', 'parallax', 'embed', 'gradient', 'multiple' ];

    /** Keys Beaver Builder writes at runtime that never describe the layout. */
    const RUNTIME_KEYS = [ 'responsive_display_filtered', 'undefined', 'bt_default_module' ];

    /** Key patterns produced by the settings form itself (editor scratch, search widgets, field separators). */
    const RUNTIME_PATTERNS = [ '/^flrich\d+_/', '/^field_separator_/', '/-search$/', '/^as_values_/' ];

    /**
     * @return array<string, array{label: string, addon: string, kind: string, prefixes: string[], keys: string[], structure_only: bool, gate: callable(array): bool}>
     */
    public static function families(): array {
        $yes  = static fn( array $s, string $key ): bool => in_array( strtolower( (string) ( is_scalar( $s[ $key ] ?? null ) ? $s[ $key ] : '' ) ), [ 'yes', 'true', '1' ], true );
        $bg   = static fn( array $s ): string => strtolower( is_string( $s['bg_type'] ?? null ) ? $s['bg_type'] : '' );
        $set  = static fn( array $s, string $key ): bool => is_string( $s[ $key ] ?? null ) && ! in_array( strtolower( $s[ $key ] ), [ '', 'none', 'no' ], true );

        return [
            'uabb-row-gradient'   => [
                'label' => 'row gradient background', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'uabb_row_gradient_', 'uabb_row_radial_', 'uabb_row_linear_' ], 'keys' => [ 'uabb_row_uabb_direction' ],
                'gate' => static fn( array $s ): bool => $bg( $s ) === 'uabb_gradient',
            ],
            'uabb-column-gradient' => [
                'label' => 'column gradient background', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'uabb_col_' ], 'keys' => [],
                'gate' => static fn( array $s ): bool => $bg( $s ) === 'uabb_gradient',
            ],
            'uabb-animated-bg'    => [
                'label' => 'animated background', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'bird_', 'fog_', 'waves_', 'net_', 'dots_', 'rings_', 'cells_' ], 'keys' => [ 'animation_type' ],
                'gate' => static fn( array $s ): bool => str_contains( $bg( $s ), 'anim' ),
            ],
            'particles-bg'        => [
                'label' => 'particle background', 'addon' => 'Ultimate Addons / PowerPack', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'uabb_row_particles_', 'uabb_particles_', 'part_' ], 'keys' => [ 'enable_particles' ],
                'gate' => static fn( array $s ): bool => $yes( $s, 'enable_particles' ) || str_contains( $bg( $s ), 'particle' ),
            ],
            'pp-scrolling-bg'     => [
                'label' => 'scrolling image background', 'addon' => 'PowerPack', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'pp_bg_image', 'scrolling_' ], 'keys' => [ 'pp_infinite_overlay' ],
                'gate' => static fn( array $s ): bool => str_contains( $bg( $s ), 'scroll' ),
            ],
            'pp-overlay-width'    => [
                'label' => 'content-width background overlay', 'addon' => 'PowerPack', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [], 'keys' => [ 'pp_bg_overlay_type' ],
                'gate' => static fn( array $s ): bool => is_string( $s['pp_bg_overlay_type'] ?? null ) && ! in_array( $s['pp_bg_overlay_type'], [ '', 'full_width' ], true ),
            ],
            'uabb-shape-separator' => [
                'label' => 'row shape separator', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'separator_shape', 'uabb_row_separator_', 'bot_separator_' ], 'keys' => [],
                'gate' => static fn( array $s ): bool => $set( $s, 'separator_shape' ) || $set( $s, 'bot_separator_shape' ),
            ],
            'uabb-border-separator' => [
                'label' => 'row/column border separator', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'separator_type', 'separator_color', 'separator_shadow', 'separator_height', 'separator_position', 'separator_tablet', 'separator_mobile', 'separator_opacity' ],
                'keys' => [ 'enable_separator' ],
                'gate' => static fn( array $s ): bool => $yes( $s, 'enable_separator' ),
            ],
            'uabb-expandable-row' => [
                'label' => 'expandable row', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'er_' ], 'keys' => [ 'enable_expandable' ],
                'gate' => static fn( array $s ): bool => $yes( $s, 'enable_expandable' ),
            ],
            'uabb-down-arrow'     => [
                'label' => 'row down arrow', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'da_' ], 'keys' => [ 'enable_down_arrow' ],
                'gate' => static fn( array $s ): bool => $yes( $s, 'enable_down_arrow' ),
            ],
            'uabb-column-shadow'  => [
                'label' => 'column shadow', 'addon' => 'Ultimate Addons', 'kind' => 'addon', 'structure_only' => true,
                'prefixes' => [ 'col_shadow_' ], 'keys' => [ 'col_drop_shadow', 'col_hover_shadow', 'col_responsive_shadow', 'col_small_shadow' ],
                'gate' => static fn( array $s ): bool => $yes( $s, 'col_drop_shadow' ) || $yes( $s, 'col_hover_shadow' ),
            ],
            'bb-row-shapes'       => [
                'label' => 'row edge shape', 'addon' => 'Beaver Builder', 'kind' => 'shapes', 'structure_only' => true,
                'prefixes' => [ 'top_edge_', 'bottom_edge_' ], 'keys' => [],
                'gate' => static fn( array $s ): bool => $set( $s, 'top_edge_shape' ) || $set( $s, 'bottom_edge_shape' ),
            ],
        ];
    }

    /**
     * Sorts a node's settings keys into add-on families.
     *
     * @param array $settings  The node's settings.
     * @param bool  $is_module True for a module (families that only exist on rows/columns are not matched).
     * @return array{ignored: array<string, string>, active: array<string, array{label: string, addon: string, kind: string, keys: string[]}>}
     *   `ignored` maps each inert key to its family label; `active` lists, per family, the keys of a feature the node really uses.
     */
    public static function classify( array $settings, bool $is_module ): array {
        $ignored = [];
        $active  = [];

        foreach ( self::families() as $name => $family ) {
            if ( $family['structure_only'] && $is_module ) {
                continue;
            }
            $keys = self::familyKeys( $settings, $family );
            if ( $keys === [] ) {
                continue;
            }
            if ( ( $family['gate'] )( $settings ) ) {
                $active[ $name ] = [ 'label' => $family['label'], 'addon' => $family['addon'], 'kind' => $family['kind'], 'keys' => $keys ];
                continue;
            }
            foreach ( $keys as $key ) {
                $ignored[ $key ] = $family['addon'] . ' ' . $family['label'];
            }
        }

        return [ 'ignored' => $ignored, 'active' => $active ];
    }

    /** True for keys Beaver Builder or its settings form writes that carry no design. */
    public static function isRuntimeKey( string $key, mixed $value ): bool {
        if ( in_array( $key, self::RUNTIME_KEYS, true ) ) {
            return true;
        }
        if ( $key === 'visibility_logic' && ( $value === '[]' || $value === [] ) ) {
            return true;
        }
        foreach ( self::RUNTIME_PATTERNS as $pattern ) {
            if ( preg_match( $pattern, $key ) ) {
                return true;
            }
        }
        return false;
    }

    /** True when a row/column background type belongs to an add-on rather than Beaver Builder. */
    public static function isAddonBackgroundType( string $type ): bool {
        return ! in_array( strtolower( $type ), self::CORE_BG_TYPES, true );
    }

    /** @return string[] */
    private static function familyKeys( array $settings, array $family ): array {
        $found = [];
        foreach ( array_keys( $settings ) as $key ) {
            if ( ! is_string( $key ) ) {
                continue;
            }
            if ( in_array( $key, $family['keys'], true ) ) {
                $found[] = $key;
                continue;
            }
            foreach ( $family['prefixes'] as $prefix ) {
                if ( str_starts_with( $key, $prefix ) ) {
                    $found[] = $key;
                    break;
                }
            }
        }
        return $found;
    }
}
