<?php
/**
 * The "Not carried over" section shared by both report screens: things the
 * conversion could not express at all, each with the node it belonged to.
 */

namespace BeaverDivi5Converter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NotCarriedOverRenderer {

    /**
     * @param array $entries     From the report's `not_carried_over` key.
     * @param array $approximate From the report's `approximate_matches` key.
     * @param array $unresolved  From the report's `unresolved_globals` key.
     */
    public static function render( array $entries, array $approximate = [], array $unresolved = [] ): string {
        if ( empty( $entries ) && empty( $approximate ) && empty( $unresolved ) ) {
            return '';
        }

        $html = '<div class="bdc-not-carried"><h3>' . esc_html__( 'Not carried over', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</h3>';

        foreach ( self::groups() as $kind => $label ) {
            $rows = array_values( array_filter( $entries, static fn( array $e ): bool => ( $e['kind'] ?? '' ) === $kind ) );
            if ( empty( $rows ) ) {
                continue;
            }
            $html .= '<p class="bdc-not-carried-label"><strong>' . esc_html( $label ) . '</strong></p><ul class="bdc-not-carried-list">';
            foreach ( $rows as $row ) {
                $html .= '<li><code>' . esc_html( (string) ( $row['node_id'] ?? '' ) ) . '</code> — ' . esc_html( (string) ( $row['detail'] ?? '' ) ) . '</li>';
            }
            $html .= '</ul>';
        }

        if ( ! empty( $unresolved ) ) {
            $html .= '<p class="bdc-not-carried-label"><strong>' . esc_html__( 'Global colours that could not be resolved — the setting was left empty rather than guessed', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</strong></p><ul class="bdc-not-carried-list">';
            foreach ( $unresolved as $row ) {
                $html .= '<li><code>' . esc_html( (string) ( $row['node_id'] ?? '' ) ) . '</code> — ' . esc_html( (string) ( $row['setting_key'] ?? '' ) ) . ' = ' . esc_html( (string) ( $row['ref'] ?? '' ) ) . '</li>';
            }
            $html .= '</ul>';
        }

        if ( ! empty( $approximate ) ) {
            $html .= '<p class="bdc-not-carried-label"><strong>' . esc_html__( 'Approximate conversions — Beaver Builder Pro modules mapped from documentation rather than source; check them', 'jhmg-converter-for-beaver-builder-to-divi' ) . '</strong></p><ul class="bdc-not-carried-list">';
            foreach ( $approximate as $row ) {
                $html .= '<li><code>' . esc_html( (string) ( $row['node_id'] ?? '' ) ) . '</code> — ' . esc_html( (string) ( $row['module'] ?? '' ) ) . ' → ' . esc_html( (string) ( $row['matched_to'] ?? '' ) ) . '</li>';
            }
            $html .= '</ul>';
        }

        return $html . '</div>';
    }

    private static function groups(): array {
        return [
            'animation'   => __( 'Animations — removed', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'visibility'  => __( 'Logged-in / logged-out visibility rules — removed; the element shows to everyone', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'shapes'      => __( 'Row shape layers — removed', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'background'  => __( 'Backgrounds that could only be approximated', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'lightbox'    => __( 'Lightbox behaviour — the link or video opens normally instead', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'hover'       => __( 'Hover colours — the resting colours are kept', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'interaction' => __( 'Click actions and interactive behaviour — needs rebuilding in Divi', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'integration' => __( 'Third-party connections — reconnect in the Divi module', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'custom_code' => __( 'Layout-level custom CSS and JavaScript — copy it into Divi → Theme Options if still needed', 'jhmg-converter-for-beaver-builder-to-divi' ),
        ];
    }
}
