<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Button Group → a nested divi/row holding one full-width
 * column laid out as a flex row, with one divi/button per item.
 *
 * Divi buttons are block-level, so emitting them one after another would stack
 * a horizontal group vertically. Divi 5 lets a column hold a row, and a column
 * can be a flex container, which reproduces the inline group faithfully. A
 * vertical group keeps the plain stacked buttons.
 *
 * Styling lives on the group; each item carries only text, icon and link, so
 * the group's style keys are merged into every item before conversion.
 */
class ButtonGroupConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_buttons_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];
        $items    = is_array( $settings['items'] ?? null ) ? $settings['items'] : [];

        $shared = $settings;
        unset( $shared['items'], $shared['type'], $shared['button_group_label'], $shared['layout'], $shared['button_spacing'], $shared['button_spacing_unit'] );
        // The group's `padding` is the wrapper; the buttons' face padding is `button_padding`.
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            unset( $shared[ 'padding_' . $side ], $shared[ 'padding_' . $side . '_medium' ], $shared[ 'padding_' . $side . '_responsive' ] );
            if ( isset( $settings[ 'button_padding_' . $side ] ) ) {
                $shared[ 'padding_' . $side ] = $settings[ 'button_padding_' . $side ];
            }
            foreach ( [ '', '_medium', '_responsive', '_large' ] as $suffix ) {
                unset( $shared[ 'button_padding_' . $side . $suffix ] );
            }
        }
        foreach ( [ 'button_padding_unit', 'button_padding_medium_unit', 'button_padding_responsive_unit', 'button_padding_large_unit' ] as $unit_key ) {
            unset( $shared[ $unit_key ] );
        }
        // Alignment is handled by the flex row, not by each button.
        $align = $this->text( $settings, 'align' );
        unset( $shared['align'], $shared['align_medium'], $shared['align_responsive'], $shared['align_large'] );

        $buttons = [];
        foreach ( array_values( $items ) as $index => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $buttons[] = ( new ButtonConverter( $this->engine ) )->convertSettings( $id . '-' . ( $index + 1 ), array_merge( $shared, $item ), $node );
        }

        if ( empty( $buttons ) ) {
            $this->engine->logWarning( "Button group {$id} has no buttons." );
        }

        $this->logUnmappedSettings( $id, [ 'layout' => $settings['layout'] ?? '', 'button_group_label' => $settings['button_group_label'] ?? '' ], [ 'layout', 'button_group_label' ] );

        if ( $this->text( $settings, 'layout' ) === 'vertical' || count( $buttons ) < 2 ) {
            if ( $align !== '' ) {
                foreach ( $buttons as &$button ) {
                    $button['settings']['module']['advanced']['alignment']['desktop']['value'] = $align;
                }
                unset( $button );
            }
            return $buttons;
        }

        return $this->inlineRow( $id, $buttons, $align, Size::fromSettings( $settings, 'button_spacing', 'button_spacing' ) ?: '10px' );
    }

    /**
     * A row inside the current column whose single column is a flex row: the
     * Divi 5 way to put several block modules side by side.
     */
    private function inlineRow( string $id, array $blocks, string $align, string $gap ): array {
        $justify = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ][ $align ] ?? 'flex-start';

        $column = $this->block( $id . '-col', 'divi/column', [
            'module' => [
                'advanced'   => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ],
                'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'display' => 'flex', 'flexDirection' => 'row', 'flexWrap' => 'wrap', 'alignItems' => 'center', 'justifyContent' => $justify, 'columnGap' => $gap, 'rowGap' => $gap ] ] ] ],
            ],
        ], $blocks );

        $this->engine->logConverted( 'row' );
        $this->engine->logConverted( 'column' );

        return $this->block( $id, 'divi/row', [ 'module' => [ 'advanced' => [ 'columnStructure' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ], [ $column ] );
    }
}
