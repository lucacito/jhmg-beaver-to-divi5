<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Search → divi/search. */
class SearchConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_search_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $value = array_filter( [
            'placeholder' => trim( $this->firstText( $settings, [ 'input_placeholder', 'placeholder' ] ) ),
            'buttonText'  => trim( $this->firstText( $settings, [ 'btn_text', 'button_text' ] ) ),
        ] );
        if ( ! empty( $value ) ) {
            $attrs['search']['innerContent']['desktop']['value'] = $value;
        }

        $this->engine->logConverted( 'search' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'layout', 'input_placeholder', 'placeholder', 'btn_text', 'button_text', 'btn_action', 'btn_icon', 'results_layout', 'show_post_type', 'post_types', 'input_width', 'input_width_unit' ], array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'btn_' ) || str_starts_with( $k, 'input_' ) ), $style['handled_keys'] ) );

        return $this->block( $id, 'divi/search', $attrs );
    }
}
