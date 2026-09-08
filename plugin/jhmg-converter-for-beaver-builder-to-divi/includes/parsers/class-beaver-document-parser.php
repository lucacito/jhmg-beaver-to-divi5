<?php
/**
 * Reads a Beaver Builder layout out of post meta.
 *
 * Beaver Builder stores a layout as a PHP-serialized flat map of node id ⇒
 * stdClass in `_fl_builder_data` (published) — see FLBuilderModel::get_layout_data().
 * WordPress hands it back already unserialized as an array of stdClass objects;
 * an export file, a fixture or a raw database read hands it over as a string.
 * This parser accepts all of those and always returns plain nested arrays.
 */

namespace BeaverDivi5Converter\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BeaverDocumentParser {

    const DATA_META     = '_fl_builder_data';
    const SETTINGS_META = '_fl_builder_data_settings';

    /**
     * @param array $post_meta The full post meta array as get_post_meta( $id ) returns it
     *                         (each key ⇒ array of values) or a flat key ⇒ value array.
     * @return array{nodes: array<string,array>, settings: array}
     */
    public function parse( array $post_meta ): array {
        return [
            'nodes'    => $this->parseValue( $this->metaValue( $post_meta, self::DATA_META ) ),
            'settings' => $this->normalize( $this->maybeUnserialize( $this->metaValue( $post_meta, self::SETTINGS_META ) ) ) ?: [],
        ];
    }

    /**
     * Normalises one layout value — serialized string, JSON string, array of
     * stdClass, or already-plain arrays — into `node_id ⇒ node array`.
     *
     * Nodes that are not objects/arrays with a `type` are dropped: Beaver Builder
     * itself discards them in clean_layout_data().
     *
     * @return array<string,array{node:string,type:string,parent:?string,position:int,settings:array}>
     */
    public function parseValue( mixed $raw ): array {
        $data = $this->maybeUnserialize( $raw );

        if ( is_string( $data ) ) {
            $decoded = json_decode( $data, true );
            $data    = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $data = $this->normalize( $data );

        if ( ! is_array( $data ) ) {
            return [];
        }

        // A fixture or JSON export may wrap the map: {"nodes": {...}}.
        if ( isset( $data['nodes'] ) && is_array( $data['nodes'] ) ) {
            $data = $data['nodes'];
        }

        $nodes = [];
        foreach ( $data as $key => $node ) {
            if ( ! is_array( $node ) || empty( $node['type'] ) || ! is_string( $node['type'] ) ) {
                continue;
            }

            $id = (string) ( $node['node'] ?? $key );
            if ( $id === '' ) {
                continue;
            }

            $parent = $node['parent'] ?? null;
            $parent = is_string( $parent ) && $parent !== '' ? $parent : null;

            $settings = $node['settings'] ?? [];
            if ( ! is_array( $settings ) ) {
                // Beaver Builder serialises an empty settings object as "" on
                // column groups; treat any non-array as no settings.
                $settings = [];
            }

            $nodes[ $id ] = [
                'node'     => $id,
                'type'     => $node['type'],
                'parent'   => $parent,
                'position' => (int) ( $node['position'] ?? 0 ),
                'settings' => $settings,
            ];
        }

        return $nodes;
    }

    /**
     * Unserialize a PHP-serialized layout without instantiating anything but
     * stdClass. A payload that smuggles in another class is refused outright:
     * PHP would turn it into __PHP_Incomplete_Class, which normalize() turns
     * into an empty array — but the safer answer to hostile input is nothing.
     */
    private function maybeUnserialize( mixed $raw ): mixed {
        if ( ! is_string( $raw ) ) {
            return $raw;
        }

        $trimmed = trim( $raw );
        if ( $trimmed === '' || ! preg_match( '/^[aOsbidN][:;]/', $trimmed ) ) {
            return $raw;
        }

        if ( preg_match( '/O:\d+:"(?!stdClass")/', $trimmed ) ) {
            return null;
        }

        $value = @unserialize( $trimmed, [ 'allowed_classes' => [ 'stdClass' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

        return $value === false && $trimmed !== 'b:0;' ? $raw : $value;
    }

    /** stdClass (at any depth) → array. Scalars pass through. */
    private function normalize( mixed $value ): mixed {
        if ( $value instanceof \__PHP_Incomplete_Class ) {
            return [];
        }
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = $this->normalize( $v );
            }
        }
        return $value;
    }

    /** get_post_meta( $id ) shape (key ⇒ [values]) or a flat key ⇒ value array. */
    private function metaValue( array $post_meta, string $key ): mixed {
        if ( ! array_key_exists( $key, $post_meta ) ) {
            return null;
        }
        $value = $post_meta[ $key ];
        if ( is_array( $value ) && array_key_exists( 0, $value ) && count( $value ) === 1 && ! isset( $value['nodes'] ) ) {
            // get_post_meta() without $single wraps every value in a list. A
            // one-element numerically-keyed array is that wrapper, unless it is
            // itself the node map (node maps are keyed by hex ids, never 0).
            $inner = $value[0];
            if ( ! is_array( $inner ) || ! isset( $inner['type'] ) ) {
                return $inner;
            }
        }
        return $value;
    }
}
