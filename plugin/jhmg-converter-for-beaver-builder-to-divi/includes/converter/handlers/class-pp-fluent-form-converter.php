<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Color;
use BeaverDivi5Converter\Helpers\Size;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PowerPack "Fluent Forms" (pp-fluent-form) → a divi/code module holding the
 * Fluent Forms shortcode, with PowerPack's form styling rewritten as custom CSS
 * against Fluent Forms' own markup (which does not change between builders).
 * An optional custom title/description becomes a heading and a text module.
 *
 * Needs the Fluent Forms plugin on the Divi site, as it did on the Beaver
 * Builder site. PowerPack is a third-party add-on; registered approximate.
 */
class PpFluentFormConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_pp_fluent_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style   = $this->mapStyle( 'text', $node );
        $module  = $style['divi_attrs']['module'] ?? [];
        $handled = $style['handled_keys'];

        $blocks = [];
        if ( $this->text( $settings, 'form_custom_title_desc' ) === 'yes' ) {
            $title = trim( $this->text( $settings, 'custom_title' ) );
            if ( $title !== '' ) {
                $blocks[] = $this->delegate( HeadingConverter::class, $id . '-title', [
                    'type'          => 'heading',
                    'heading'       => $title,
                    'tag'           => $this->text( $settings, 'title_tag' ) ?: 'h3',
                    'color'         => $this->setting( $settings, 'title_color' ),
                    'typography'    => $this->setting( $settings, 'title_typography' ),
                    'margin_top'    => $this->setting( $settings, 'title_margin_top' ),
                    'margin_right'  => $this->setting( $settings, 'title_margin_right' ),
                    'margin_bottom' => $this->setting( $settings, 'title_margin_bottom' ),
                    'margin_left'   => $this->setting( $settings, 'title_margin_left' ),
                    'margin_unit'   => 'px',
                ] );
            }
            $description = trim( $this->text( $settings, 'custom_description' ) );
            if ( $description !== '' ) {
                $blocks[] = $this->delegate( RichTextConverter::class, $id . '-description', [
                    'type'          => 'rich-text',
                    'text'          => $description,
                    'color'         => $this->setting( $settings, 'description_color' ),
                    'typography'    => $this->setting( $settings, 'description_typography' ),
                    'margin_top'    => $this->setting( $settings, 'description_margin_top' ),
                    'margin_right'  => $this->setting( $settings, 'description_margin_right' ),
                    'margin_bottom' => $this->setting( $settings, 'description_margin_bottom' ),
                    'margin_left'   => $this->setting( $settings, 'description_margin_left' ),
                    'margin_unit'   => 'px',
                ] );
            }
        }

        $form_id = trim( $this->text( $settings, 'select_form_field' ) );
        if ( $form_id === '' || ! preg_match( '/^\d+$/', $form_id ) ) {
            $this->engine->logWarning( "Fluent Form {$id}: no form selected in Beaver Builder; an empty Fluent Forms shortcode was written." );
            $shortcode = '[fluentform id=""]';
        } else {
            $shortcode = '[fluentform id="' . $form_id . '"]';
        }
        $this->engine->logWarning( "Fluent Form {$id}: the form is embedded through its shortcode; Fluent Forms must be active on the Divi site." );

        $code = $this->codeBlock( $id, $shortcode );
        foreach ( [ 'html', 'disabledOn', 'attributes' ] as $keep ) {
            if ( isset( $module['advanced'][ $keep ] ) ) {
                $code['settings']['module']['advanced'][ $keep ] = $module['advanced'][ $keep ];
            }
            if ( isset( $module['decoration'][ $keep ] ) ) {
                $code['settings']['module']['decoration'][ $keep ] = $module['decoration'][ $keep ];
            }
        }
        $css = $this->formCss( $settings, $id );
        if ( $css !== '' ) {
            StyleMapper::write( $code['settings'], 'css.desktop.value.freeForm', $css );
        }
        $blocks[] = $code;

        $this->spreadModuleSpacing( $blocks, $module );

        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $blocks;
    }

    /** PowerPack's form design settings as CSS against Fluent Forms' markup. */
    private function formCss( array $settings, string $id ): string {
        $rules = [];
        $add   = static function ( string $selector, array $declarations ) use ( &$rules ): void {
            $declarations = array_filter( $declarations, static fn( $v ) => $v !== '' && $v !== null );
            if ( $declarations !== [] ) {
                $rules[] = $selector . ' { ' . implode( ' ', array_map( static fn( string $p, string $v ) => "{$p}: {$v};", array_keys( $declarations ), $declarations ) ) . ' }';
            }
        };
        $color = fn( string $key ): string => (string) ( $this->color( $settings, $key, $id ) ?? '' );

        // The form container.
        $form = [ 'padding' => $this->box( $settings, 'form_padding' ) ];
        if ( $this->text( $settings, 'form_bg_type' ) === 'image' && $this->text( $settings, 'form_bg_image_src' ) !== '' ) {
            $form['background-image']  = "url('" . $this->text( $settings, 'form_bg_image_src' ) . "')";
            $form['background-size']   = $this->text( $settings, 'form_bg_size' );
            $form['background-repeat'] = $this->text( $settings, 'form_bg_repeat' );
        } else {
            $form['background-color'] = $color( 'form_bg_color' );
        }
        $add( 'selector .fluentform', $form + $this->borderCss( $settings['form_border'] ?? null ) );

        // Labels.
        if ( $this->text( $settings, 'display_labels' ) === 'none' ) {
            $add( 'selector .fluentform .ff-el-input--label', [ 'display' => 'none' ] );
        }
        $add( 'selector .fluentform .ff-el-input--label label', [ 'color' => $color( 'label_color' ) ] + $this->typographyCss( $settings['label_typography'] ?? null ) );

        // Section titles / descriptions.
        $add( 'selector .fluentform .ff-el-section-title', [ 'color' => $color( 'section_title_color' ) ] + $this->typographyCss( $settings['section_title_typography'] ?? null ) );
        $add( 'selector .fluentform .ff-el-section-break .ff-section_break_desk', [ 'color' => $color( 'section_description_color' ) ] + $this->typographyCss( $settings['section_description_typography'] ?? null ) );
        $add( 'selector .fluentform .ff-el-section-break', [ 'background-color' => $color( 'section_field_bg_color' ), 'margin' => $this->box( $settings, 'section_field_margin' ), 'padding' => $this->box( $settings, 'section_field_padding' ) ] + $this->borderCss( $settings['section_field_border'] ?? null ) );

        // Inputs.
        $input = [
            'color'            => $color( 'input_field_text_color' ),
            'background-color' => $color( 'input_field_bg_color' ),
            'padding'          => $this->box( $settings, 'input_field_padding' ),
        ] + $this->borderCss( $settings['input_border'] ?? null ) + $this->typographyCss( $settings['input_typography'] ?? null );
        $add( 'selector .fluentform .ff-el-form-control', $input );
        $height = Size::fromSettings( $settings, 'input_field_height', 'input_field_height' );
        if ( $height !== '' ) {
            $add( 'selector .fluentform input.ff-el-form-control:not([type=checkbox]):not([type=radio]), selector .fluentform select.ff-el-form-control', [ 'height' => $height ] );
        }
        $textarea = Size::fromSettings( $settings, 'input_textarea_height', 'input_textarea_height' );
        if ( $textarea !== '' ) {
            $add( 'selector .fluentform textarea.ff-el-form-control', [ 'height' => $textarea ] );
        }
        $margin = Size::fromSettings( $settings, 'input_field_margin', 'input_field_margin' );
        if ( $margin !== '' ) {
            $add( 'selector .fluentform .ff-el-group', [ 'margin-bottom' => $margin ] );
        }
        if ( $this->text( $settings, 'input_placeholder_display' ) === 'none' ) {
            $add( 'selector .fluentform .ff-el-form-control::placeholder', [ 'opacity' => '0' ] );
        } else {
            $add( 'selector .fluentform .ff-el-form-control::placeholder', [ 'color' => $color( 'input_placeholder_color' ) ] );
        }
        $add( 'selector .fluentform .ff-el-form-control:focus', [ 'border-color' => $color( 'input_field_focus_color' ) ] );

        // Submit button.
        $button = [
            'color'            => $color( 'button_text_color' ),
            'background-color' => $color( 'button_bg_color' ),
            'padding'          => $this->box( $settings, 'button_padding' ),
            'width'            => $this->text( $settings, 'button_width' ) === 'true' ? '100%' : '',
        ] + $this->borderCss( $settings['button_border'] ?? null ) + $this->typographyCss( $settings['button_typography'] ?? null );
        $add( 'selector .fluentform .ff-btn-submit', $button );
        $add( 'selector .fluentform .ff-btn-submit:hover', [ 'color' => $color( 'button_text_color_hover' ), 'background-color' => $color( 'button_background_color_hover' ) ] );
        $alignment = $this->text( $settings, 'button_alignment' );
        if ( in_array( $alignment, [ 'left', 'center', 'right' ], true ) ) {
            $add( 'selector .fluentform .ff_submit_btn_wrapper', [ 'text-align' => $alignment ] );
        }

        // Validation and success messages.
        $add( 'selector .fluentform .ff-el-is-error .text-danger, selector .fluentform .ff-errors-in-stack .error', [ 'color' => $color( 'error_message_color' ) ] + $this->typographyCss( $settings['error_typography'] ?? null ) );
        $error_border = $color( 'error_input_field_border_color' );
        if ( $error_border !== '' ) {
            $error_width = Size::fromSettings( $settings, 'error_input_field_border_width', 'error_input_field_border_width' ) ?: '1px';
            $add( 'selector .fluentform .ff-el-is-error .ff-el-form-control', [ 'border' => "{$error_width} solid {$error_border}" ] );
        }
        $add( 'selector .fluentform .ff-message-success', [ 'color' => $color( 'success_message_color' ), 'background-color' => $color( 'success_message_bg_color' ) ] + $this->borderCss( $settings['success_message_border'] ?? null ) + $this->typographyCss( $settings['success_message_typography'] ?? null ) );

        if ( $this->text( $settings, 'radio_cb_style' ) === 'yes' ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'custom radio/checkbox styling on the Fluent Form' );
        }

        return implode( ' ', $rules );
    }

    /** "<prefix>_top/right/bottom/left" (+ unit) as a CSS shorthand, '' when unset. */
    private function box( array $settings, string $prefix ): string {
        $sides = [];
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $v = Size::fromSettings( $settings, "{$prefix}_{$side}", $prefix );
            if ( $v === '' ) {
                return '';
            }
            $sides[] = $v;
        }
        return implode( ' ', $sides );
    }

    /** A Beaver Builder border compound as CSS declarations. */
    private function borderCss( mixed $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $css   = [];
        $style = is_string( $raw['style'] ?? null ) ? $raw['style'] : '';
        $color = Color::normalize( $raw['color'] ?? '' );
        if ( $style !== '' && $style !== 'none' ) {
            $css['border-style'] = $style;
        }
        if ( $color !== null ) {
            $css['border-color'] = $color;
        }
        if ( is_array( $raw['width'] ?? null ) ) {
            $widths = [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                $widths[] = Size::withUnit( $raw['width'][ $side ] ?? '', null ) ?: '0px';
            }
            if ( $widths !== [ '0px', '0px', '0px', '0px' ] ) {
                $css['border-width'] = implode( ' ', $widths );
            }
        }
        if ( is_array( $raw['radius'] ?? null ) ) {
            $radii = [];
            foreach ( [ 'top_left', 'top_right', 'bottom_right', 'bottom_left' ] as $corner ) {
                $radii[] = Size::withUnit( $raw['radius'][ $corner ] ?? '', null ) ?: '0px';
            }
            if ( $radii !== [ '0px', '0px', '0px', '0px' ] ) {
                $css['border-radius'] = implode( ' ', $radii );
            }
        }
        if ( is_array( $raw['shadow'] ?? null ) ) {
            $shadow_color = Color::normalize( $raw['shadow']['color'] ?? '' );
            if ( $shadow_color !== null ) {
                $parts = [];
                foreach ( [ 'horizontal', 'vertical', 'blur', 'spread' ] as $part ) {
                    $parts[] = Size::withUnit( $raw['shadow'][ $part ] ?? '0', null ) ?: '0px';
                }
                $css['box-shadow'] = implode( ' ', $parts ) . ' ' . $shadow_color;
            }
        }
        return $css;
    }

    /** A Beaver Builder typography compound as CSS declarations (defaults omitted). */
    private function typographyCss( mixed $raw ): array {
        if ( ! is_array( $raw ) ) {
            return [];
        }
        $css    = [];
        $family = is_string( $raw['font_family'] ?? null ) ? $raw['font_family'] : '';
        if ( $family !== '' && $family !== 'Default' ) {
            $css['font-family'] = "'" . str_replace( "'", '', $family ) . "'";
        }
        $weight = is_string( $raw['font_weight'] ?? null ) ? $raw['font_weight'] : '';
        if ( $weight !== '' && $weight !== 'default' ) {
            $css['font-weight'] = $weight;
        }
        $size = Size::fromLength( $raw['font_size'] ?? null );
        if ( $size !== '' ) {
            $css['font-size'] = $size;
        }
        $line_height = Size::fromLength( $raw['line_height'] ?? null, '' );
        if ( $line_height !== '' ) {
            $css['line-height'] = $line_height;
        }
        $spacing = Size::fromLength( $raw['letter_spacing'] ?? null );
        if ( $spacing !== '' ) {
            $css['letter-spacing'] = $spacing;
        }
        foreach ( [ 'text_align' => 'text-align', 'text_transform' => 'text-transform', 'text_decoration' => 'text-decoration', 'font_style' => 'font-style' ] as $key => $property ) {
            $v = is_string( $raw[ $key ] ?? null ) ? $raw[ $key ] : '';
            if ( $v !== '' && $v !== 'none' ) {
                $css[ $property ] = $v;
            }
        }
        return $css;
    }

    const CONSUMED = [
        'select_form_field', 'form_custom_title_desc', 'custom_title', 'custom_description', 'form_bg_type', 'form_bg_color',
        'form_bg_image', 'form_bg_image_src', 'form_bg_size', 'form_bg_repeat', 'form_bg_overlay', 'form_border', 'title_color',
        'description_color', 'display_labels', 'label_color', 'section_field_bg_color', 'section_field_border',
        'input_field_text_color', 'input_field_bg_color', 'input_border', 'input_field_focus_color', 'input_field_width',
        'input_field_height', 'input_textarea_height', 'input_field_margin', 'input_placeholder_display', 'input_placeholder_color',
        'radio_cb_style', 'radio_cb_size', 'radio_cb_color', 'radio_cb_checked_color', 'radio_cb_border_width',
        'radio_cb_border_color', 'radio_cb_radius', 'radio_cb_checkbox_radius', 'button_text_color', 'button_text_color_hover',
        'button_bg_color', 'button_background_color_hover', 'button_border', 'button_width', 'button_alignment', 'error_message',
        'error_message_color', 'error_input_field_border_color', 'error_input_field_border_width', 'success_message_bg_color',
        'success_message_color', 'success_message_border', 'title_tag', 'title_typography', 'description_typography',
        'label_typography', 'radio_check_typography', 'input_typography', 'button_typography', 'section_title_typography',
        'section_title_color', 'section_description_typography', 'section_description_color', 'error_typography',
        'success_message_typography', 'form_padding_top', 'form_padding_right', 'form_padding_bottom', 'form_padding_left',
        'title_margin_top', 'title_margin_right', 'title_margin_bottom', 'title_margin_left', 'description_margin_top',
        'description_margin_right', 'description_margin_bottom', 'description_margin_left', 'section_field_margin_top',
        'section_field_margin_right', 'section_field_margin_bottom', 'section_field_margin_left', 'section_field_padding_top',
        'section_field_padding_right', 'section_field_padding_bottom', 'section_field_padding_left', 'input_field_padding_top',
        'input_field_padding_right', 'input_field_padding_bottom', 'input_field_padding_left', 'button_padding_top',
        'button_padding_right', 'button_padding_bottom', 'button_padding_left',
        'form_border_medium', 'form_border_responsive', 'section_field_border_medium', 'section_field_border_responsive',
        'input_border_medium', 'input_border_responsive', 'button_border_medium', 'button_border_responsive',
        'success_message_border_medium', 'success_message_border_responsive',
    ];
}
