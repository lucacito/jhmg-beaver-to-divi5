<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Star Rating → divi/text with the rating drawn in Unicode stars. */
class StarRatingConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_stars_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'text', $node );
        $attrs = $style['divi_attrs'];

        $total  = max( 1, (int) ( $settings['total'] ?? 5 ) );
        $rating = (float) ( $settings['rating'] ?? 0 );
        $filled = max( 0, min( $total, (int) round( $rating ) ) );
        $glyph  = trim( $this->text( $settings, 'unicode' ) ) ?: '★';

        $html  = '<p class="bdc-star-rating" aria-label="' . esc_attr( $rating . ' out of ' . $total ) . '">';
        $html .= '<span class="bdc-star-rating-filled">' . str_repeat( $glyph, $filled ) . '</span>';
        $html .= '<span class="bdc-star-rating-empty">' . str_repeat( '☆', $total - $filled ) . '</span></p>';

        $attrs['content']['innerContent']['desktop']['value'] = $html;
        $fill = $this->color( $settings, 'fill', $id );
        if ( $fill !== null ) {
            $attrs['content']['decoration']['bodyFont']['body']['font']['desktop']['value']['color'] = $fill;
        }

        $this->engine->logConverted( 'text' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'total', 'rating', 'icon', 'unicode', 'font', 'ratio', 'fill', 'empty', 'size', 'size_unit', 'align' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/text', $attrs );
    }
}
