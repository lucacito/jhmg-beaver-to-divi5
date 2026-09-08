<?php

namespace BeaverDivi5Converter\Converter;

use BeaverDivi5Converter\Helpers\Color;
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

        return [ 'divi_attrs' => $attrs, 'handled_keys' => $result['handled_keys'] ];
    }

    /**
     * Fills in the text colour a Beaver Builder row or column forced on this
     * module, wherever the module set none of its own. Buttons keep their own
     * colour: Beaver Builder's button rule outranks the row rule.
     */
    private function applyInheritedColors( string $kind, array $attrs ): array {
        $text    = $this->engine->inheritedColor( 'text_color' );
        $heading = $this->engine->inheritedColor( 'heading_color' ) ?? $text;
        if ( $text === null && $heading === null ) {
            return $attrs;
        }

        $targets = [
            'heading' => [ 'title.decoration.font.font' => $heading ],
            'text'    => [ 'content.decoration.bodyFont.body.font' => $text ],
            'blurb'   => [ 'title.decoration.font.font' => $heading, 'content.decoration.bodyFont.body.font' => $text ],
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

        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || in_array( $key, $mapped_keys, true ) || in_array( $key, $always_ignore, true ) ) {
                continue;
            }
            if ( $value === '' || $value === null || $value === [] || $value === false ) {
                continue;
            }
            if ( is_array( $value ) && $this->isAllEmpty( $value ) ) {
                continue;
            }
            if ( str_ends_with( $key, '_large' ) || str_ends_with( $key, '_large_unit' ) || str_ends_with( $key, '_unit' ) ) {
                continue;
            }
            $this->engine->logSkippedSetting( "{$node_id}: {$key}" );
        }
    }

    private function isAllEmpty( array $value ): bool {
        foreach ( $value as $v ) {
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
