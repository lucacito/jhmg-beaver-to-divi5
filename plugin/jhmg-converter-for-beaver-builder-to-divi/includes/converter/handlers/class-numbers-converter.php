<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Number Counter → divi/number-counter, divi/circle-counter for
 * the circle layout, or divi/counters + divi/counter for the bars layout.
 */
class NumbersConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_counter_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'counter', $node );
        $attrs   = $style['divi_attrs'];
        $handled = $style['handled_keys'];
        $mapper  = new StyleMapper();

        $number  = trim( $this->firstText( $settings, [ 'number' ] ) );
        $number  = $number !== '' ? $number : '100';
        $percent = ( $this->text( $settings, 'number_type' ) ?: 'percent' ) === 'percent';
        $prefix  = $this->text( $settings, 'number_prefix' );
        $suffix  = $this->text( $settings, 'number_suffix' );
        $title   = trim( $this->firstText( $settings, [ 'before_number_text', 'after_number_text' ] ) );
        $circle  = $this->text( $settings, 'layout' ) === 'circle';

        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
            $mapper->applyTypography( $settings, 'text_typography', 'title.decoration.font.font', $attrs, $handled );
            $text_color = $this->color( $settings, 'text_color', $id );
            if ( $text_color !== null ) {
                $attrs['title']['decoration']['font']['font']['desktop']['value']['color'] = $text_color;
            }
        }
        $mapper->applyTypography( $settings, 'number_typography', 'number.decoration.font.font', $attrs, $handled );

        if ( $circle ) {
            $attrs['number']['innerContent']['desktop']['value'] = $number;
            $attrs['number']['advanced']['percentSign']['desktop']['value'] = $percent ? 'on' : 'off';
            $circle_color = $this->color( $settings, 'circle_color', $id );
            $circle_bg    = $this->color( $settings, 'circle_bg_color', $id );
            $circle_value = array_filter( [ 'color' => $circle_color, 'backgroundColor' => $circle_bg ] );
            if ( ! empty( $circle_value ) ) {
                $attrs['circle']['advanced']['circle']['desktop']['value'] = $circle_value;
            }
            $this->engine->logConverted( 'circle-counter' );
            $this->logUnmappedSettings( $id, $settings, $this->consumed( $settings, $handled ) );

            return $this->block( $id, 'divi/circle-counter', $attrs );
        }

        if ( $prefix !== '' || $suffix !== '' ) {
            $attrs['number']['innerContent']['desktop']['value'] = $prefix . $number . $suffix;
            $attrs['number']['advanced']['enablePercentSign']['desktop']['value'] = 'off';
        } else {
            $attrs['number']['innerContent']['desktop']['value'] = $number;
            $attrs['number']['advanced']['enablePercentSign']['desktop']['value'] = $percent ? 'on' : 'off';
        }

        // Bars layout → Divi's bar counters: one divi/counters holding one divi/counter.
        if ( $this->text( $settings, 'layout' ) === 'bars' ) {
            $max     = (float) ( $settings['max_number'] ?? 100 );
            $percent_value = $percent ? (float) $number : ( $max > 0 ? round( (float) $number / $max * 100 ) : 0 );
            $bar     = [ 'barProgress' => [ 'innerContent' => [ 'desktop' => [ 'value' => (string) max( 0, min( 100, $percent_value ) ) ] ] ] ];
            if ( $title !== '' ) {
                $bar['title']['innerContent']['desktop']['value'] = $title;
            }
            $bar_color = $this->color( $settings, 'bar_color', $id );
            if ( $bar_color !== null ) {
                $bar['barProgress']['decoration']['background']['desktop']['value']['color'] = $bar_color;
            }
            unset( $attrs['title'], $attrs['number'] );
            $this->engine->logConverted( 'counters' );
            $this->logUnmappedSettings( $id, $settings, $this->consumed( $settings, $handled ) );

            return $this->block( $id, 'divi/counters', $attrs, [ $this->block( $id . '-bar', 'divi/counter', $bar ) ] );
        }

        $this->engine->logConverted( 'number-counter' );
        $this->logUnmappedSettings( $id, $settings, $this->consumed( $settings, $handled ) );

        return $this->block( $id, 'divi/number-counter', $attrs );
    }

    private function consumed( array $settings, array $handled ): array {
        return array_merge(
            [
                'layout', 'number', 'number_unit', 'start_number', 'max_number', 'number_type', 'number_position', 'number_prefix', 'number_suffix',
                'before_number_text', 'after_number_text', 'animation_speed', 'delay', 'text_color', 'text_typography', 'text_typography_medium',
                'text_typography_responsive', 'number_color', 'number_typography', 'number_typography_medium', 'number_typography_responsive',
                'circle_width', 'circle_dash_width', 'circle_color', 'circle_bg_color', 'bar_color', 'bar_bg_color', 'bar_height', 'bar_height_unit',
            ],
            $handled
        );
    }
}
