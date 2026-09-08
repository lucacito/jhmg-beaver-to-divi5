<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Countdown → divi/countdown-timer. */
class CountdownConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_countdown_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $date = trim( $this->firstText( $settings, [ 'date', 'due_date' ] ) );
        $time = trim( $this->text( $settings, 'time' ) );
        if ( $date !== '' ) {
            $attrs['module']['advanced']['countdownDate']['desktop']['value'] = trim( $date . ' ' . $time );
        } else {
            $this->engine->logWarning( "Countdown {$id}: no date set." );
        }
        $title = trim( $this->firstText( $settings, [ 'title', 'heading' ] ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
        }

        $this->engine->logConverted( 'countdown-timer' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'date', 'due_date', 'time', 'time_zone', 'title', 'heading', 'layout', 'number_color', 'label_color', 'show_days', 'show_hours', 'show_minutes', 'show_seconds', 'redirect_url', 'redirect_enabled', 'circle_width', 'circle_color', 'circle_bg_color', 'number_size', 'number_size_unit', 'separator_type', 'separator_color' ], $style['handled_keys'] ) );

        return $this->block( $id, 'divi/countdown-timer', $attrs );
    }
}
