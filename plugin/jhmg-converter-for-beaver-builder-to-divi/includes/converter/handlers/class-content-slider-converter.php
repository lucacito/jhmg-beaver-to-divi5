<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Content Slider → divi/slider + divi/slide per slide. */
class ContentSliderConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_slider_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style    = $this->mapStyle( 'generic', $node );
        $children = [];
        foreach ( array_values( is_array( $settings['slides'] ?? null ) ? $settings['slides'] : [] ) as $index => $slide ) {
            if ( ! is_array( $slide ) ) {
                continue;
            }
            $attrs = [];
            $title = trim( $this->firstText( $slide, [ 'title', 'heading', 'label' ] ) );
            if ( $title !== '' ) {
                $attrs['title']['innerContent']['desktop']['value'] = $title;
            }
            $text = trim( $this->firstText( $slide, [ 'text', 'content' ] ) );
            if ( $text !== '' ) {
                $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );
            }
            $button = [];
            $cta    = trim( $this->firstText( $slide, [ 'cta_text', 'btn_text' ] ) );
            if ( $cta !== '' ) {
                $button['text'] = $cta;
            }
            $link = $this->linkValue( $slide, 'link' );
            if ( isset( $link['url'] ) ) {
                $button['linkUrl'] = $link['url'];
            }
            if ( ! empty( $button ) ) {
                $attrs['button']['innerContent']['desktop']['value'] = $button;
            }
            $bg = trim( $this->firstText( $slide, [ 'bg_photo_src', 'bg_photo_url', 'photo_src', 'bg_image_src' ] ) );
            if ( $bg !== '' ) {
                $attrs['module']['decoration']['background']['desktop']['value']['image']['url'] = $bg;
                $attrs['module']['decoration']['background']['desktop']['value']['image']['size'] = 'cover';
            }
            $bg_color = $this->color( $slide, 'bg_color', $id );
            if ( $bg_color !== null ) {
                $attrs['module']['decoration']['background']['desktop']['value']['color'] = $bg_color;
            }
            $children[] = $this->block( $id . '-slide-' . ( $index + 1 ), 'divi/slide', $attrs );
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Content slider {$id} has no slides." );
        }

        $this->engine->logConverted( 'slider' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'slides', 'height', 'height_unit', 'auto_play', 'delay', 'transition', 'speed', 'arrows', 'dots', 'loop', 'max_width', 'max_width_unit' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/slider', $style['divi_attrs'], $children );
    }
}
