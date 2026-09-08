<?php

namespace BeaverDivi5Converter\Converter;

use BeaverDivi5Converter\Helpers\AddonSettings;
use BeaverDivi5Converter\Helpers\Color;
use BeaverDivi5Converter\StyleMapper\GlobalSettingsResolver;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class BaseBeaverConverter implements ConverterInterface {
    protected ConverterEngine $engine;

    public function __construct( ConverterEngine $engine ) {
        $this->engine = $engine;
    }

    // -------------------------------------------------------------------------
    // Children
    // -------------------------------------------------------------------------

    /** Every child converted through the registry, as a flat list of blocks. */
    protected function convertChildren( array $node ): array {
        return $this->engine->convertChildren( $node['children'] ?? [] );
    }

    /**
     * Converts a column's (or box's) children with structure awareness:
     * a nested column-group becomes a divi/row right here, so it never gets
     * dispatched as if it sat directly under a row.
     */
    protected function convertStructureChildren( array $children ): array {
        $result = [];
        foreach ( $children as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            foreach ( ConverterEngine::asList( $this->engine->convertNode( $child ) ) as $block ) {
                $result[] = $block;
            }
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Settings access
    // -------------------------------------------------------------------------

    protected function setting( array $settings, string $key, mixed $default = '' ): mixed {
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    /** A string setting, '' when absent or not a string. */
    protected function text( array $settings, string $key ): string {
        $value = $settings[ $key ] ?? '';
        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }
        return is_string( $value ) ? $value : '';
    }

    /** First non-empty string among several candidate keys. */
    protected function firstText( array $settings, array $keys ): string {
        foreach ( $keys as $key ) {
            $v = $this->text( $settings, $key );
            if ( trim( $v ) !== '' ) {
                return $v;
            }
        }
        return '';
    }

    /**
     * Beaver Builder link field ⇒ Divi link value {url, target?, rel?}.
     * Stored as `<key>`, `<key>_target`, `<key>_nofollow` (`yes`/`no`).
     */
    protected function linkValue( array $settings, string $key ): array {
        $url = trim( $this->text( $settings, $key ) );
        if ( $url === '' ) {
            return [];
        }
        $value = [ 'url' => $url ];
        if ( ( $settings[ $key . '_target' ] ?? '' ) === '_blank' ) {
            $value['target'] = '_blank';
        }
        if ( ( $settings[ $key . '_nofollow' ] ?? '' ) === 'yes' ) {
            $value['rel'] = [ 'nofollow' ];
        }
        return $value;
    }

    /** The keys a link field occupies, for logUnmappedSettings(). */
    protected function linkKeys( string $key ): array {
        return [ $key, $key . '_target', $key . '_nofollow', $key . '_download' ];
    }

    /** A normalised colour or null; unresolved global references are reported. */
    protected function color( array $settings, string $key, string $node_id ): ?string {
        $raw   = $settings[ $key ] ?? '';
        $color = Color::normalize( $raw );
        if ( $color === null && is_string( $raw ) && Color::isGlobalRef( trim( $raw ) ) ) {
            $this->engine->logUnresolvedGlobal( $node_id, $key, trim( $raw ) );
        }
        return $color;
    }

    // -------------------------------------------------------------------------
    // Style mapping
    // -------------------------------------------------------------------------

    /**
     * Runs the StyleMapper for a node and forwards its notes to the report.
     *
     * @return array{divi_attrs: array, handled_keys: string[]}
     */
    protected function mapStyle( string $kind, array $node ): array {
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
        $result   = ( new StyleMapper() )->map( $kind, $settings );
        $node_id  = (string) ( $node['id'] ?? '' );

        foreach ( $result['notes'] as $note ) {
            if ( $note['kind'] === 'unresolved_global' ) {
                [ $key, $ref ] = array_pad( explode( '=', $note['detail'], 2 ), 2, '' );
                $this->engine->logUnresolvedGlobal( $node_id, $key, $ref );
                continue;
            }
            $this->engine->logNotCarriedOver( $note['kind'], $node_id, $note['detail'] );
        }

        $attrs = $this->applyInheritedColors( $kind, $result['divi_attrs'] );

        // A module side left blank in Beaver Builder takes the site's global module
        // margin (20px by default). Blocks a composite handler delegates are pieces
        // of one module and carry only their own explicit spacing.
        if ( ! in_array( $kind, [ 'row', 'column', 'group' ], true ) && empty( $node['delegated'] ) ) {
            $attrs = $this->fillDefaultMargins( $attrs );
        }

        return [ 'divi_attrs' => $attrs, 'handled_keys' => $result['handled_keys'] ];
    }

    private function fillDefaultMargins( array $attrs ): array {
        $global = GlobalSettingsResolver::moduleMargins();
        if ( $global === null ) {
            return $attrs;
        }
        $margin = $attrs['module']['decoration']['spacing']['desktop']['value']['margin'] ?? [];
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            if ( ! isset( $margin[ $side ] ) || $margin[ $side ] === '' ) {
                $margin[ $side ] = $global[ $side ];
            }
        }
        $attrs['module']['decoration']['spacing']['desktop']['value']['margin'] = $margin + [ 'syncVertical' => 'off', 'syncHorizontal' => 'off' ];
        return $attrs;
    }

    /**
     * Fills in the text colour a Beaver Builder row or column forced on this
     * module, wherever the module set none of its own. Buttons keep their own
     * colour: Beaver Builder's button rule outranks the row rule.
     */
    private function applyInheritedColors( string $kind, array $attrs ): array {
        // Beaver Builder's row/column rules: text_color colours everything, and
        // headings/links follow it unless heading_color / link_color say otherwise.
        $text    = $this->engine->inheritedColor( 'text_color' );
        $heading = $this->engine->inheritedColor( 'heading_color' ) ?? $text;
        $link    = $this->engine->inheritedColor( 'link_color' ) ?? $text;
        if ( $text === null && $heading === null && $link === null ) {
            return $attrs;
        }

        $text_headings = [];
        foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $level ) {
            $text_headings[ "content.decoration.headingFont.{$level}.font" ] = $heading;
        }

        $targets = [
            'heading' => [ 'title.decoration.font.font' => $heading ],
            'text'    => [ 'content.decoration.bodyFont.body.font' => $text, 'content.decoration.bodyFont.link.font' => $link ] + $text_headings,
            'blurb'   => [ 'title.decoration.font.font' => $heading, 'content.decoration.bodyFont.body.font' => $text, 'content.decoration.bodyFont.link.font' => $link ],
            'cta'     => [ 'title.decoration.font.font' => $heading, 'content.decoration.bodyFont.body.font' => $text ],
            'counter' => [ 'number.decoration.font.font' => $text, 'title.decoration.font.font' => $text ],
            'icon'    => [ 'icon.advanced.color' => $text ],
        ][ $kind ] ?? [];

        foreach ( $targets as $path => $color ) {
            if ( $color === null ) {
                continue;
            }
            $existing = $attrs;
            foreach ( explode( '.', $path . '.desktop.value' ) as $key ) {
                $existing = is_array( $existing ) && array_key_exists( $key, $existing ) ? $existing[ $key ] : null;
            }
            $has_color = $path === 'icon.advanced.color' ? $existing !== null : ( is_array( $existing ) && isset( $existing['color'] ) );
            if ( ! $has_color ) {
                StyleMapper::write( $attrs, $path . '.desktop.value' . ( $path === 'icon.advanced.color' ? '' : '.color' ), $color );
            }
        }

        return $attrs;
    }

    /** The container colours a row or column forces on its descendants, normalised. */
    protected function containerColors( array $settings, string $node_id ): array {
        return [
            'text_color'    => $this->color( $settings, 'text_color', $node_id ),
            'heading_color' => $this->color( $settings, 'heading_color', $node_id ),
            'link_color'    => $this->color( $settings, 'link_color', $node_id ),
        ];
    }

    // -------------------------------------------------------------------------
    // Block assembly
    // -------------------------------------------------------------------------

    protected function block( string $id, string $name, array $settings, array $children = [] ): array {
        return [ 'id' => $id, 'name' => $name, 'settings' => $settings, 'elements' => $children ];
    }

    /** Recursively merges two settings arrays; nested arrays merge rather than replace. */
    protected function deepMergeSettings( array $a, array $b ): array {
        foreach ( $b as $k => $v ) {
            if ( is_array( $v ) && isset( $a[ $k ] ) && is_array( $a[ $k ] ) ) {
                $a[ $k ] = $this->deepMergeSettings( $a[ $k ], $v );
            } else {
                $a[ $k ] = $v;
            }
        }
        return $a;
    }

    /** Guarantees a list holds only divi/column blocks by wrapping anything else in one. */
    protected function ensureColumnChildren( string $id, array $children ): array {
        if ( empty( $children ) ) {
            return [];
        }
        foreach ( $children as $child ) {
            if ( ( $child['name'] ?? '' ) !== 'divi/column' ) {
                return [ $this->block( $id . '-col', 'divi/column', [ 'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ], $children ) ];
            }
        }
        return $children;
    }

    /** Row settings (columnStructure) derived from its converted columns' fractions. */
    protected function rowSettingsFromColumns( array $columns ): array {
        $fractions = [];
        foreach ( $columns as $column ) {
            if ( ( $column['name'] ?? '' ) !== 'divi/column' ) {
                continue;
            }
            $fraction = $column['settings']['module']['advanced']['type']['desktop']['value'] ?? null;
            if ( $fraction === null ) {
                return [];
            }
            $fractions[] = $fraction;
        }
        if ( empty( $fractions ) ) {
            return [];
        }
        return [ 'module' => [ 'advanced' => [ 'columnStructure' => [ 'desktop' => [ 'value' => implode( ',', $fractions ) ] ] ] ] ];
    }

    /** A divi/code block carrying raw HTML or a shortcode. */
    protected function codeBlock( string $id, string $html ): array {
        return $this->block( $id, 'divi/code', [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $html ] ] ] ] );
    }

    /**
     * Runs another handler over settings shaped like the Lite module it expects.
     * Composite add-on modules (PowerPack headings, lists) are built from Lite
     * pieces this way, so every piece is mapped by its source-verified handler.
     *
     * @param class-string<BaseBeaverConverter> $handler
     */
    protected function delegate( string $handler, string $id, array $settings, bool $as_module = false ): array {
        return ( new $handler( $this->engine ) )->convert( [ 'id' => $id, 'type' => 'module', 'settings' => $settings, 'delegated' => ! $as_module ] );
    }

    /**
     * Aligns a module narrower than its column (a divider, a separator) the way
     * Beaver Builder does with `margin: auto`. Divi 5's sizing declaration only
     * emits `align-self` when the module's styles are told the parent is a flex
     * layout, so the auto margins are written as CSS too; the module's default
     * side margins are cleared on the sides that must be `auto`.
     */
    protected function alignSizedModule( array &$attrs, string $align ): void {
        $align = in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : 'center';
        $attrs['module']['decoration']['sizing']['desktop']['value']['alignment'] = $align;
        $attrs['module']['decoration']['sizing']['desktop']['value']['alignSelf'] = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $align ];

        $auto = [ 'left' => [ 'right' ], 'center' => [ 'left', 'right' ], 'right' => [ 'left' ] ][ $align ];
        foreach ( $auto as $side ) {
            unset( $attrs['module']['decoration']['spacing']['desktop']['value']['margin'][ $side ] );
        }
        $rule     = 'selector { ' . implode( ' ', array_map( static fn( string $s ) => "margin-{$s}: auto !important;", $auto ) ) . ' }';
        $existing = $attrs['css']['desktop']['value']['freeForm'] ?? '';
        $attrs['css']['desktop']['value']['freeForm'] = trim( $existing . ' ' . $rule );
    }

    /**
     * Settings for a Divi row that stands in for something Beaver Builder gives
     * no padding: a column group, or the row wrapped around an inline group of
     * buttons/icons. Divi's own row default (27px top and bottom) would add space
     * the source never had.
     */
    const ROW_RESET = [ 'module' => [ 'decoration' => [ 'spacing' => [ 'desktop' => [ 'value' => [ 'padding' => [ 'top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px', 'syncVertical' => 'off', 'syncHorizontal' => 'off' ] ] ] ] ] ] ];

    /**
     * Moves the module-level top margin onto the first block and the bottom
     * margin onto the last, for handlers that emit several blocks in a column.
     *
     * @param array $blocks Blocks in document order; edited in place.
     * @param array $module The `module` attrs StyleMapper produced for the source node.
     */
    protected function spreadModuleSpacing( array &$blocks, array $module ): void {
        if ( $blocks === [] ) {
            return;
        }
        $margin = $module['decoration']['spacing']['desktop']['value']['margin'] ?? [];
        if ( ! is_array( $margin ) ) {
            return;
        }
        $first = array_key_first( $blocks );
        $last  = array_key_last( $blocks );
        foreach ( [ 'top' => $first, 'bottom' => $last ] as $side => $index ) {
            if ( isset( $margin[ $side ] ) && $margin[ $side ] !== '' ) {
                StyleMapper::write( $blocks[ $index ]['settings'], "module.decoration.spacing.desktop.value.margin.{$side}", $margin[ $side ] );
            }
        }
        foreach ( [ 'left', 'right' ] as $side ) {
            if ( isset( $margin[ $side ] ) && $margin[ $side ] !== '' ) {
                foreach ( array_keys( $blocks ) as $index ) {
                    // A block that aligns itself (a centred divider) keeps its auto margins.
                    if ( isset( $blocks[ $index ]['settings']['module']['decoration']['sizing']['desktop']['value']['alignment'] ) ) {
                        continue;
                    }
                    StyleMapper::write( $blocks[ $index ]['settings'], "module.decoration.spacing.desktop.value.margin.{$side}", $margin[ $side ] );
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    /**
     * Reports every setting key the handler did not consume, so nothing is
     * dropped in silence. Beaver Builder bookkeeping keys and keys with no
     * conceivable Divi meaning are ignored.
     */
    protected function logUnmappedSettings( string $node_id, array $settings, array $mapped_keys = [] ): void {
        static $always_ignore = [
            'type', 'connections', 'data', 'node_label', 'container_element', 'export', 'import',
            'responsive_display', 'visibility_display', 'visibility_user_capability', 'animation',
            'id', 'class', 'srcset', 'attributes', 'template_id', 'template_node_id', 'global',
            'lightbox_image_size', 'title_hover', 'url_title', 'button_type', 'label_text',
            'bg_parallax_speed', 'bg_repeat', 'top_edge_transform', 'bottom_edge_transform',
        ];

        // Add-on families: inert keys are counted, active ones reported once per node.
        $addons = AddonSettings::classify( $settings, isset( $settings['type'] ) );
        foreach ( array_unique( $addons['ignored'] ) as $label ) {
            $this->engine->logAddonDefaults( $label, $node_id );
        }
        $addon_keys = array_keys( $addons['ignored'] );
        foreach ( $addons['active'] as $family ) {
            $this->engine->logNotCarriedOver( $family['kind'], $node_id, $family['label'] . ' (' . $family['addon'] . ')' );
            $addon_keys = array_merge( $addon_keys, $family['keys'] );
        }

        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || in_array( $key, $mapped_keys, true ) || in_array( $key, $always_ignore, true ) || in_array( $key, $addon_keys, true ) ) {
                continue;
            }
            if ( $value === '' || $value === null || $value === [] || $value === false ) {
                continue;
            }
            if ( AddonSettings::isRuntimeKey( $key, $value ) ) {
                continue;
            }
            // An unmapped toggle that is switched off describes nothing the page loses.
            if ( is_string( $value ) && in_array( strtolower( $value ), [ 'no', 'none', 'off', 'false' ], true ) ) {
                continue;
            }
            if ( is_array( $value ) && $this->isAllEmpty( $value ) ) {
                continue;
            }
            if ( str_ends_with( $key, '_large' ) || str_ends_with( $key, '_large_unit' ) || str_ends_with( $key, '_unit' ) ) {
                continue;
            }
            if ( ( $key === 'bb_css_code' || $key === 'bb_js_code' ) && is_string( $value ) && trim( $value ) !== '' ) {
                $this->engine->logNotCarriedOver( 'custom_code', $node_id, $key === 'bb_css_code' ? 'node CSS' : 'node JavaScript' );
                continue;
            }
            $this->engine->logSkippedSetting( "{$node_id}: {$key}" );
        }
    }

    /**
     * True when a compound setting carries nothing the user chose: every leaf is
     * blank once the form's own scaffolding is set aside — unit selectors, the
     * "Default" font family/weight, and a gradient's angle/position/stops when
     * no colour was picked.
     */
    private function isAllEmpty( array $value ): bool {
        $is_gradient = array_key_exists( 'colors', $value ) && is_array( $value['colors'] );
        foreach ( $value as $key => $v ) {
            if ( $key === 'unit' || str_ends_with( (string) $key, '_unit' ) ) {
                continue;
            }
            if ( $is_gradient && in_array( $key, [ 'type', 'angle', 'position', 'stops' ], true ) ) {
                continue;
            }
            if ( ( $key === 'font_family' && $v === 'Default' ) || ( $key === 'font_weight' && $v === 'default' ) ) {
                continue;
            }
            if ( is_array( $v ) ) {
                if ( ! $this->isAllEmpty( $v ) ) {
                    return false;
                }
            } elseif ( $v !== '' && $v !== null && $v !== false ) {
                return false;
            }
        }
        return true;
    }
}
