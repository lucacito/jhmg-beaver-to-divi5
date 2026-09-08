<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Pricing Table → divi/pricing-tables + divi/pricing-table per column. */
class PricingTableConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_pricing_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style    = $this->mapStyle( 'generic', $node );
        $children = [];
        foreach ( array_values( is_array( $settings['pricing_columns'] ?? null ) ? $settings['pricing_columns'] : [] ) as $index => $column ) {
            if ( ! is_array( $column ) ) {
                continue;
            }
            $attrs = [];
            $title = trim( $this->firstText( $column, [ 'title' ] ) );
            if ( $title !== '' ) {
                $attrs['title']['innerContent']['desktop']['value'] = $title;
            }
            $price = trim( $this->firstText( $column, [ 'price' ] ) );
            if ( $price !== '' ) {
                $attrs['price']['innerContent']['desktop']['value'] = $price;
            }
            $duration = trim( $this->firstText( $column, [ 'duration', 'period' ] ) );
            if ( $duration !== '' ) {
                $attrs['currencyFrequency']['innerContent']['desktop']['value'] = [ 'per' => $duration ];
            }
            $features = $column['features'] ?? '';
            $lines    = is_array( $features ) ? $features : preg_split( '/\r?\n/', (string) $features );
            $lines    = array_values( array_filter( array_map( static fn( $l ) => is_scalar( $l ) ? trim( (string) $l ) : '', (array) $lines ) ) );
            if ( ! empty( $lines ) ) {
                $attrs['content']['innerContent']['desktop']['value'] = '<ul>' . implode( '', array_map( static fn( string $l ) => '<li>' . esc_html( $l ) . '</li>', $lines ) ) . '</ul>';
            }
            $button = [];
            $text   = trim( $this->firstText( $column, [ 'button_text', 'btn_text' ] ) );
            if ( $text !== '' ) {
                $button['text'] = $text;
            }
            $link = $this->linkValue( $column, 'button_url' );
            if ( isset( $link['url'] ) ) {
                $button['linkUrl'] = $link['url'];
                if ( isset( $link['target'] ) ) {
                    $button['linkTarget'] = '_blank';
                }
            }
            if ( ! empty( $button ) ) {
                $attrs['button']['innerContent']['desktop']['value'] = $button;
            }
            $bg = $this->color( $column, 'background_color', $id );
            if ( $bg !== null ) {
                $attrs['module']['decoration']['background']['desktop']['value']['color'] = $bg;
            }
            $children[] = $this->block( $id . '-table-' . ( $index + 1 ), 'divi/pricing-table', $attrs );
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Pricing table {$id} has no columns." );
        }

        $this->engine->logConverted( 'pricing-tables' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'pricing_columns', 'layout', 'spacing', 'border_size', 'border_radius', 'highlight', 'columns' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/pricing-tables', $style['divi_attrs'], $children );
    }
}
