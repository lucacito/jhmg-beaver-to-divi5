<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Tabs → divi/tabs + divi/tab. */
class TabsConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_tabs_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style    = $this->mapStyle( 'generic', $node );
        $children = [];
        foreach ( array_values( is_array( $settings['items'] ?? null ) ? $settings['items'] : [] ) as $index => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $child = [];
            $label = trim( $this->firstText( $item, [ 'label', 'title' ] ) );
            if ( $label !== '' ) {
                $child['title']['innerContent']['desktop']['value'] = $label;
            }
            $content = trim( $this->firstText( $item, [ 'content', 'text' ] ) );
            if ( $content !== '' ) {
                $child['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $content );
            }
            $children[] = $this->block( $id . '-tab-' . ( $index + 1 ), 'divi/tab', $child );
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Tabs {$id} has no tabs." );
        }
        if ( $this->text( $settings, 'layout' ) === 'vertical' ) {
            $this->engine->logWarning( "Tabs {$id}: vertical tabs have no Divi equivalent; horizontal tabs are used." );
        }

        $this->engine->logConverted( 'tabs' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'items', 'layout', 'border_color', 'label_active_color', 'label_color', 'content_color', 'label_size' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/tabs', $style['divi_attrs'], $children );
    }
}
