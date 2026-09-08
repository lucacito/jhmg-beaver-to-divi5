<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Sidebar → divi/sidebar (same widget area id). */
class SidebarConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_sidebar_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $area = trim( $this->text( $settings, 'sidebar' ) );
        if ( $area !== '' ) {
            $attrs['sidebar']['innerContent']['desktop']['value']['area'] = $area;
        }

        $this->engine->logConverted( 'sidebar' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'sidebar' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/sidebar', $attrs );
    }
}
