<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder HTML module → divi/code, verbatim. */
class HtmlConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_code_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $this->deepMergeSettings( $style['divi_attrs'], [ 'content' => [ 'innerContent' => [ 'desktop' => [ 'value' => $this->text( $settings, 'html' ) ] ] ] ] );

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'html' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/code', $attrs );
    }
}
