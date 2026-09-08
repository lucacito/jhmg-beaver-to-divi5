<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder 2.11 Popup module. Divi 5 has no popup module, so the
 * popup's content is kept as a divi/group hidden on every device: nothing is
 * lost, nothing shows inline, and the group can be wired to a popup plugin.
 * The trigger, schedule and dismiss behaviour are reported as not carried over.
 *
 * Documented but not yet shipped at the time of writing; registered approximate.
 */
class PopupConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_popup_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'group', $node );
        $attrs = $style['divi_attrs'];

        foreach ( [ 'desktop', 'tablet', 'phone' ] as $bp ) {
            $attrs['module']['decoration']['disabledOn'][ $bp ]['value'] = 'on';
        }
        $popup_id = $this->firstText( $settings, [ 'popup_id', 'id' ] );
        if ( $popup_id !== '' ) {
            $attrs['module']['advanced']['htmlAttributes']['desktop']['value']['id'] = $popup_id;
        }

        $this->engine->pushInheritedColors( $this->containerColors( $settings, $id ) );
        try {
            $children = $this->convertStructureChildren( $node['children'] ?? [] );
        } finally {
            $this->engine->popInheritedColors();
        }

        $triggers = [];
        foreach ( [ 'show_on', 'trigger', 'triggers' ] as $key ) {
            $value = $settings[ $key ] ?? null;
            if ( is_string( $value ) && $value !== '' ) {
                $triggers[] = $value;
            } elseif ( is_array( $value ) ) {
                $triggers = array_merge( $triggers, array_filter( $value, 'is_string' ) );
            }
        }
        $detail = 'popup' . ( $triggers !== [] ? ' (shown on: ' . implode( ', ', array_unique( $triggers ) ) . ')' : '' ) . ' — content kept as a hidden group; needs a popup plugin in Divi';
        $this->engine->logNotCarriedOver( 'interaction', $id, $detail );
        $this->engine->logWarning( "Popup {$id}: Divi 5 has no popup module; its content is kept in a group hidden on every device." );

        $this->engine->logConverted( 'group' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $style['handled_keys'], self::CONSUMED ) );

        return $this->block( $id, 'divi/group', $attrs, $children );
    }

    const CONSUMED = [
        'popup_id', 'show_on', 'trigger', 'triggers', 'show_delay', 'delay', 'show_on_percent_scrolled', 'scroll_percent', 'show_once',
        'show_start_date', 'show_end_date', 'close_on_esc', 'close_on_click_outside', 'close_button', 'loop_navigation_arrows',
        'position', 'width', 'width_unit', 'height', 'height_unit', 'max_width', 'max_width_unit', 'min_height', 'min_height_unit',
        'backdrop', 'backdrop_color', 'close_button_type', 'close_button_icon', 'close_button_text', 'close_button_attachment',
        'close_button_position', 'close_button_padding', 'close_button_size', 'close_button_color', 'close_button_bg_color',
        'close_button_border', 'arrows_offset', 'arrows_size', 'arrows_icon_color', 'arrows_bg_color', 'arrows_border',
    ];
}
