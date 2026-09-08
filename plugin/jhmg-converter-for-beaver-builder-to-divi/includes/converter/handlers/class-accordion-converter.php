<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Accordion → divi/accordion + divi/accordion-item. */
class AccordionConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_accordion_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style    = $this->mapStyle( 'generic', $node );
        $children = [];
        foreach ( array_values( is_array( $settings['items'] ?? null ) ? $settings['items'] : [] ) as $index => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $child = [];
            $label = trim( $this->firstText( $item, [ 'label', 'title', 'heading' ] ) );
            if ( $label !== '' ) {
                $child['title']['innerContent']['desktop']['value'] = $label;
            }
            $content = trim( $this->firstText( $item, [ 'content', 'text' ] ) );
            if ( $content !== '' ) {
                $child['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $content );
            }
            $children[] = $this->block( $id . '-item-' . ( $index + 1 ), 'divi/accordion-item', $child );
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Accordion {$id} has no items." );
        }

        $this->engine->logConverted( 'accordion' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'items', 'collapse', 'open_first', 'label_size', 'border_color', 'label_color', 'content_color' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/accordion', $style['divi_attrs'], $children );
    }
}
