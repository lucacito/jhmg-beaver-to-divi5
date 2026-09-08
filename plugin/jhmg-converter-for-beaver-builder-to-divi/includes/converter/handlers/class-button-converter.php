<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\IconMap;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Button → divi/button. */
class ButtonConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        return $this->convertSettings( (string) ( $node['id'] ?? uniqid( 'bdc_button_' ) ), is_array( $node['settings'] ?? null ) ? $node['settings'] : [], $node );
    }

    /**
     * Also used by the button group, whose items share the button's fields
     * but inherit their styling from the group.
     */
    public function convertSettings( string $id, array $settings, array $node ): array {
        $style = $this->mapStyle( 'button', [ 'id' => $id, 'settings' => $settings ] );
        $attrs = $style['divi_attrs'];

        $value = [];
        $text  = $this->text( $settings, 'text' );
        if ( $text !== '' ) {
            $value['text'] = $text;
        }

        $click = $this->text( $settings, 'click_action' ) ?: 'link';
        if ( $click === 'link' || $click === '' ) {
            $link = $this->linkValue( $settings, 'link' );
            if ( isset( $link['url'] ) ) {
                $value['linkUrl'] = $link['url'];
            }
            if ( isset( $link['target'] ) ) {
                $value['linkTarget'] = '_blank';
            }
            if ( isset( $link['rel'] ) ) {
                $value['rel'] = $link['rel'];
            }
        } elseif ( $click === 'lightbox' ) {
            $video = $this->text( $settings, 'lightbox_video_link' );
            if ( $video !== '' ) {
                $value['linkUrl'] = $video;
            }
            $this->engine->logNotCarriedOver( 'lightbox', $id, 'button opens a lightbox' );
        } else {
            $this->engine->logNotCarriedOver( 'interaction', $id, "button click action '{$click}'" );
        }

        $attrs = $this->deepMergeSettings( $attrs, [ 'button' => [ 'innerContent' => [ 'desktop' => [ 'value' => $value ] ] ] ] );

        // Face colour. bg_color is the button face, not the wrapper.
        $bg = $this->color( $settings, 'bg_color', $id );
        if ( $bg !== null && ( $this->text( $settings, 'style' ) !== 'adv-gradient' ) ) {
            $attrs['button']['decoration']['background']['desktop']['value']['color'] = $bg;
            unset( $attrs['module']['decoration']['background'] );
            // Beaver Builder paints white text on any custom button colour unless told otherwise.
            if ( ! isset( $attrs['button']['decoration']['font']['font']['desktop']['value']['color'] ) ) {
                $attrs['button']['decoration']['font']['font']['desktop']['value']['color'] = '#ffffff';
            }
        }
        if ( $this->text( $settings, 'style' ) === 'adv-gradient' ) {
            $this->engine->logWarning( "Button {$id}: advanced gradient background not carried; flat colour used." );
            unset( $attrs['module']['decoration']['background'] );
        }
        $this->tidyModuleDecoration( $attrs );

        if ( $this->color( $settings, 'bg_hover_color', $id ) !== null || $this->color( $settings, 'text_hover_color', $id ) !== null ) {
            $this->engine->logNotCarriedOver( 'hover', $id, 'button hover colours' );
        }

        $icon_class = $this->text( $settings, 'icon' );
        if ( $icon_class !== '' ) {
            $mapped = IconMap::fromClass( $icon_class );
            $attrs['button']['decoration']['button']['desktop']['value']['icon'] = [
                'enable'    => 'on',
                'settings'  => $mapped['icon'],
                'placement' => $this->text( $settings, 'icon_position' ) === 'before' ? 'left' : 'right',
                'onHover'   => $this->text( $settings, 'icon_animation' ) === 'enable' ? 'on' : 'off',
            ];
            if ( ! $mapped['exact'] ) {
                $this->engine->logWarning( "Button {$id}: icon '{$icon_class}' has no Divi equivalent; a star icon is used." );
            }
        }

        if ( $this->text( $settings, 'width' ) === 'full' ) {
            $attrs['css']['desktop']['value']['main'] = 'width: 100%; text-align: center;';
        } elseif ( $this->text( $settings, 'width' ) === 'custom' ) {
            $custom = \BeaverDivi5Converter\Helpers\Size::fromSettings( $settings, 'custom_width', 'custom_width' );
            if ( $custom !== '' ) {
                $attrs['css']['desktop']['value']['main'] = 'width: ' . $custom . '; text-align: center;';
            }
        }

        $this->engine->logConverted( 'button' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [
                'text', 'icon', 'icon_position', 'icon_animation', 'click_action', 'button', 'copy_text', 'copy_success_message',
                'lightbox_content_type', 'lightbox_content_html', 'lightbox_video_link', 'width', 'custom_width', 'custom_width_medium',
                'custom_width_responsive', 'style', 'bg_color', 'bg_hover_color', 'text_hover_color', 'button_transition',
                'bg_gradient', 'bg_gradient_hover', 'border_hover_color', 'duo_color1', 'duo_color2', 'bg_color_medium',
                'bg_color_responsive', 'bg_hover_color_medium', 'bg_hover_color_responsive', 'text_hover_color_medium',
                'text_hover_color_responsive', 'button_transition_medium', 'button_transition_responsive',
            ],
            $this->linkKeys( 'link' ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/button', $attrs );
    }

    private function tidyModuleDecoration( array &$attrs ): void {
        if ( isset( $attrs['module']['decoration'] ) && empty( $attrs['module']['decoration'] ) ) {
            unset( $attrs['module']['decoration'] );
        }
        if ( isset( $attrs['module'] ) && empty( $attrs['module'] ) ) {
            unset( $attrs['module'] );
        }
    }
}
