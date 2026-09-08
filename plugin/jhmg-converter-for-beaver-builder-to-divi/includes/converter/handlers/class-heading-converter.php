<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Heading module → divi/heading. */
class HeadingConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_heading_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'heading', $node );
        $attrs = $style['divi_attrs'];

        $text = $this->text( $settings, 'heading' );
        $tag  = strtolower( $this->text( $settings, 'tag' ) );
        $tag  = preg_match( '/^h[1-6]$/', $tag ) ? $tag : 'h2';

        // innerContent first, then whatever decoration StyleMapper produced.
        $title = [ 'innerContent' => [ 'desktop' => [ 'value' => $text ] ] ];
        if ( ! empty( $attrs['title']['decoration'] ) ) {
            $title['decoration'] = $attrs['title']['decoration'];
        }
        $title['decoration']['font']['font']['desktop']['value']['headingLevel'] = $tag;
        $attrs['title'] = $title;

        $link = $this->linkValue( $settings, 'link' );
        if ( ! empty( $link ) ) {
            $attrs['module']['advanced']['link']['desktop']['value'] = $link;
        }

        $this->engine->logConverted( 'heading' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'heading', 'tag' ], $this->linkKeys( 'link' ), $style['handled_keys'] ) );

        return $this->block( $id, 'divi/heading', $attrs );
    }
}
