<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Testimonials → one divi/testimonial per entry. */
class TestimonialsConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_testimonial_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style  = $this->mapStyle( 'generic', $node );
        $blocks = [];
        foreach ( array_values( is_array( $settings['testimonials'] ?? null ) ? $settings['testimonials'] : [] ) as $index => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $attrs   = $style['divi_attrs'];
            $content = trim( $this->firstText( $item, [ 'testimonial', 'content', 'text' ] ) );
            if ( $content !== '' ) {
                $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $content );
            }
            $author = trim( $this->firstText( $item, [ 'name', 'author', 'heading' ] ) );
            if ( $author !== '' ) {
                $attrs['author']['innerContent']['desktop']['value'] = $author;
            }
            $job = trim( $this->firstText( $item, [ 'title', 'job_title', 'position' ] ) );
            if ( $job !== '' ) {
                $attrs['jobTitle']['innerContent']['desktop']['value'] = $job;
            }
            $company = trim( $this->firstText( $item, [ 'company' ] ) );
            if ( $company !== '' ) {
                $attrs['company']['innerContent']['desktop']['value'] = [ 'text' => $company ];
            }
            $photo = trim( $this->firstText( $item, [ 'photo_src', 'image_src', 'photo_url' ] ) );
            if ( $photo !== '' ) {
                $attrs['portrait']['innerContent']['desktop']['value'] = [ 'src' => $photo ];
            }
            $blocks[] = $this->block( $id . '-' . ( $index + 1 ), 'divi/testimonial', $attrs );
            $this->engine->logConverted( 'testimonial' );
        }

        if ( empty( $blocks ) ) {
            $this->engine->logWarning( "Testimonials {$id} has no entries." );
        }
        if ( count( $blocks ) > 1 && $this->text( $settings, 'layout' ) !== 'compact' ) {
            $this->engine->logWarning( "Testimonials {$id}: the rotating slider became " . count( $blocks ) . ' stacked testimonials.' );
        }

        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'testimonials', 'layout', 'heading', 'heading_size', 'autoplay', 'pause', 'transition', 'speed', 'direction', 'arrows', 'dots', 'text_size', 'text_color', 'text_typography' ], $style['handled_keys'] ) );

        return $blocks;
    }
}
