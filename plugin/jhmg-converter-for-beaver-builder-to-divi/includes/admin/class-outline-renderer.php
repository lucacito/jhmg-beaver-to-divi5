<?php
/**
 * Renders a conversion outline as nested lists. Returns a string so it can be
 * asserted in tests without output buffering.
 */

namespace BeaverDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OutlineRenderer {

    public static function render( array $outline ): string {
        if ( empty( $outline ) ) {
            return '<p class="bdc-outline-empty">'
                . esc_html__( 'Nothing to show — this page converted to no Divi modules.', 'jhmg-converter-for-beaver-builder-to-divi' )
                . '</p>';
        }
        return self::render_list( $outline );
    }

    private static function render_list( array $nodes ): string {
        $html = '<ul class="bdc-outline">';
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            $type    = (string) ( $node['type'] ?? 'module' );
            $classes = 'bdc-outline-node bdc-outline-node--' . $type;
            if ( ! empty( $node['placeholder'] ) ) {
                $classes .= ' bdc-outline-node--placeholder';
            }
            $html .= '<li class="' . esc_attr( $classes ) . '"><span class="bdc-outline-label">' . esc_html( (string) ( $node['label'] ?? '' ) );
            if ( ! empty( $node['placeholder'] ) ) {
                $html .= ' <em>' . esc_html__( '(placeholder — rebuild by hand)', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</em>';
            }
            $html .= '</span>';
            if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
                $html .= self::render_list( $node['children'] );
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }
}
