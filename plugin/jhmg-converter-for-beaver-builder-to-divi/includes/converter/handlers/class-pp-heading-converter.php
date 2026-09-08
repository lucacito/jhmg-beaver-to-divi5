<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\IconMap;
use BeaverDivi5Converter\Helpers\Size;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PowerPack "Advanced Heading" (pp-heading) → the Lite pieces it is made of:
 * an optional prefix line (divi/text), the heading (divi/heading, with the
 * secondary title inline when "dual heading" is on), an optional separator
 * (divi/divider and/or divi/icon) and the sub-title (divi/text).
 *
 * PowerPack is a third-party add-on; its field names were read from exported
 * layouts rather than source, so the module is registered approximate.
 */
class PpHeadingConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_pp_heading_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $align = $this->text( $settings, 'heading_alignment' );
        $align = in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : 'left';

        // Module-level attrs (margins, id/class, visibility) from the source node itself.
        $style   = $this->mapStyle( 'heading', $node );
        $module  = $style['divi_attrs']['module'] ?? [];
        $handled = $style['handled_keys'];

        $before = [];
        $after  = [];
        $tail   = [];

        // Prefix line.
        $prefix = trim( $this->text( $settings, 'prefix_text' ) );
        if ( $prefix !== '' ) {
            $tag      = strtolower( $this->text( $settings, 'prefix_tag' ) );
            $tag      = preg_match( '/^(h[1-6]|p)$/', $tag ) ? $tag : 'p';
            $before[] = $this->delegate( RichTextConverter::class, $id . '-prefix', [
                'type'                  => 'rich-text',
                'text'                  => "<{$tag}>" . $prefix . "</{$tag}>",
                'color'                 => $this->setting( $settings, 'prefix_text_color' ),
                'typography'            => $this->typographyWithAlign( $settings, 'prefix_typography', $align ),
                'typography_medium'     => $this->setting( $settings, 'prefix_typography_medium' ),
                'typography_responsive' => $this->setting( $settings, 'prefix_typography_responsive' ),
            ] );
        }

        // Separator: a line, an icon, or both.
        $separator = strtolower( $this->text( $settings, 'heading_separator' ) );
        $position  = strtolower( $this->text( $settings, 'heading_separator_postion' ) ) ?: 'middle';
        $separator_blocks = [];
        if ( $separator !== '' && $separator !== 'no_spacer' ) {
            if ( str_contains( $separator, 'line' ) ) {
                $separator_blocks[] = $this->dividerBlock( $id, $settings, $align, $position );
            }
            if ( str_contains( $separator, 'icon' ) ) {
                $icon = $this->iconBlock( $id, $settings, $align );
                if ( $icon !== null ) {
                    $separator_blocks[] = $icon;
                }
            }
            if ( $separator_blocks === [] ) {
                $this->engine->logWarning( "Advanced heading {$id}: separator style '{$separator}' is not carried over." );
            }
        }

        // The heading itself, through the Lite heading handler.
        $title = $this->text( $settings, 'heading_title' );
        if ( $this->text( $settings, 'dual_heading' ) === 'yes' ) {
            $second = trim( $this->text( $settings, 'heading_title2' ) );
            if ( $second !== '' ) {
                $css = [];
                $c2  = $this->text( $settings, 'heading2_color_type' ) === 'gradient' ? null : $this->color( $settings, 'heading2_color', $id );
                if ( $c2 !== null ) {
                    $css[] = 'color:' . $c2;
                }
                $gap = Size::fromSettings( $settings, 'heading2_left_margin', 'heading2_left_margin' );
                if ( $gap !== '' ) {
                    $css[] = 'margin-left:' . $gap;
                }
                $title .= ' <span' . ( $css !== [] ? ' style="' . implode( ';', $css ) . '"' : '' ) . '>' . $second . '</span>';
                if ( $this->text( $settings, 'heading2_color_type' ) === 'gradient' ) {
                    $this->engine->logNotCarriedOver( 'background', $id, 'gradient text colour on the secondary title' );
                }
            }
        }
        if ( $this->text( $settings, 'heading_color_type' ) === 'gradient' ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'gradient text colour on the heading' );
        }

        $heading_settings = [
            'type'                  => 'heading',
            'heading'               => $title,
            'tag'                   => $this->text( $settings, 'heading_tag' ) ?: 'h2',
            'color'                 => $this->text( $settings, 'heading_color_type' ) === 'gradient' ? '' : $this->setting( $settings, 'heading_color' ),
            'typography'            => $this->typographyWithAlign( $settings, 'title_typography', $align ),
            'typography_medium'     => $this->setting( $settings, 'title_typography_medium' ),
            'typography_responsive' => $this->setting( $settings, 'title_typography_responsive' ),
            'bg_color'              => $this->setting( $settings, 'heading_bg_color' ),
            'margin_top'            => $this->setting( $settings, 'heading_top_margin' ),
            'margin_bottom'         => $this->setting( $settings, 'heading_bottom_margin' ),
            'margin_unit'           => 'px',
            'padding_top'           => $this->setting( $settings, 'heading_padding_top' ),
            'padding_right'         => $this->setting( $settings, 'heading_padding_right' ),
            'padding_bottom'        => $this->setting( $settings, 'heading_padding_bottom' ),
            'padding_left'          => $this->setting( $settings, 'heading_padding_left' ),
            'padding_unit'          => 'px',
        ];
        if ( $this->text( $settings, 'enable_link' ) === 'yes' ) {
            $heading_settings['link']          = $this->setting( $settings, 'heading_link' );
            $heading_settings['link_target']   = $this->setting( $settings, 'heading_link_target' );
            $heading_settings['link_nofollow'] = $this->setting( $settings, 'heading_link_nofollow' );
        }
        $heading = $this->delegate( HeadingConverter::class, $id, $heading_settings );
        foreach ( [ 'html', 'disabledOn', 'attributes' ] as $keep ) {
            // Custom id/class and visibility belong on the heading, the main block.
            if ( isset( $module['advanced'][ $keep ] ) ) {
                $heading['settings']['module']['advanced'][ $keep ] = $module['advanced'][ $keep ];
            }
            if ( isset( $module['decoration'][ $keep ] ) ) {
                $heading['settings']['module']['decoration'][ $keep ] = $module['decoration'][ $keep ];
            }
        }

        // Sub-title.
        $sub = trim( $this->text( $settings, 'heading_sub_title' ) );
        if ( $sub !== '' ) {
            $tail[] = $this->delegate( RichTextConverter::class, $id . '-sub', [
                'type'                  => 'rich-text',
                'text'                  => $sub,
                'color'                 => $this->setting( $settings, 'sub_heading_color' ),
                'typography'            => $this->typographyWithAlign( $settings, 'desc_typography', $align ),
                'typography_medium'     => $this->setting( $settings, 'desc_typography_medium' ),
                'typography_responsive' => $this->setting( $settings, 'desc_typography_responsive' ),
                'margin_top'            => $this->setting( $settings, 'sub_heading_top_margin' ),
                'margin_bottom'         => $this->setting( $settings, 'sub_heading_bottom_margin' ),
                'margin_unit'           => 'px',
            ] );
        }

        switch ( $position ) {
            case 'top':
                $before = array_merge( $before, $separator_blocks );
                break;
            case 'bottom':
                $tail = array_merge( $tail, $separator_blocks );
                break;
            default:
                $after = $separator_blocks;
        }

        $blocks = array_merge( $before, [ $heading ], $after, $tail );
        $this->spreadModuleSpacing( $blocks, $module );

        if ( $this->text( $settings, 'heading_custom_icon_select_src' ) !== '' && ! str_contains( $separator, 'icon' ) ) {
            $this->engine->logWarning( "Advanced heading {$id}: the custom icon image is not carried over." );
        }
        foreach ( [ 'heading_hover_color', 'heading2_hover_color' ] as $hover ) {
            if ( $this->color( $settings, $hover, $id ) !== null ) {
                $this->engine->logNotCarriedOver( 'hover', $id, 'heading hover colour' );
                break;
            }
        }

        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $blocks;
    }

    private function dividerBlock( string $id, array $settings, string $align, string $position ): array {
        $line = [
            'show'     => 'on',
            'style'    => $this->text( $settings, 'heading_line_style' ) ?: 'solid',
            'position' => 'center',
            'weight'   => Size::fromSettings( $settings, 'line_height', 'line_height' ) ?: '1px',
        ];
        $color = $this->color( $settings, 'line_color', $id );
        if ( $color !== null ) {
            $line['color'] = $color;
        }
        $attrs = [
            'divider' => [ 'advanced' => [ 'line' => [ 'desktop' => [ 'value' => $line ] ] ] ],
            'module'  => [ 'decoration' => [ 'sizing' => [ 'desktop' => [ 'value' => [
                'width' => Size::fromSettings( $settings, 'line_width', 'line_width' ) ?: '100px',
            ] ] ] ] ],
        ];
        $this->alignSizedModule( $attrs, $align );
        $gap = Size::fromSettings( $settings, 'font_title_line_space', 'font_title_line_space' );
        if ( $gap !== '' ) {
            $side = $position === 'top' ? 'bottom' : 'top';
            StyleMapper::write( $attrs, "module.decoration.spacing.desktop.value.margin.{$side}", $gap );
        }
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            $v = Size::fromSettings( $settings, 'separator_margin_' . $side, 'separator_margin' );
            if ( $v !== '' ) {
                StyleMapper::write( $attrs, "module.decoration.spacing.desktop.value.margin.{$side}", $v );
            }
        }
        return $this->block( $id . '-line', 'divi/divider', $attrs );
    }

    private function iconBlock( string $id, array $settings, string $align ): ?array {
        if ( $this->text( $settings, 'heading_icon_select' ) === 'custom_icon_select' ) {
            $src = $this->text( $settings, 'heading_custom_icon_select_src' );
            if ( $src === '' ) {
                return null;
            }
            return $this->block( $id . '-icon', 'divi/image', [
                'image'  => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'src' => $src, 'alt' => '' ] ] ] ],
                'module' => [ 'advanced' => [ 'align' => [ 'desktop' => [ 'value' => $align ] ] ] ],
            ] );
        }
        $class = $this->text( $settings, 'heading_font_icon_select' );
        if ( $class === '' ) {
            return null;
        }
        $mapped = IconMap::fromClass( $class );
        if ( ! $mapped['exact'] ) {
            $this->engine->logWarning( "Advanced heading {$id}: icon '{$class}' has no Divi equivalent; a star icon is used." );
        }
        $attrs = [ 'icon' => [ 'innerContent' => [ 'desktop' => [ 'value' => $mapped['icon'] ] ] ] ];
        $color = $this->color( $settings, 'font_icon_color', $id );
        if ( $color !== null ) {
            $attrs['icon']['advanced']['color']['desktop']['value'] = $color;
        }
        $attrs['icon']['advanced']['size']['desktop']['value']  = Size::fromSettings( $settings, 'font_icon_font_size', 'font_icon_font_size' ) ?: '16px';
        $attrs['icon']['advanced']['align']['desktop']['value'] = $align;
        $bg = $this->color( $settings, 'font_icon_bg_color', $id );
        if ( $bg !== null ) {
            $attrs['module']['decoration']['background']['desktop']['value']['color'] = $bg;
        }
        $gap = Size::fromSettings( $settings, 'font_icon_line_space', 'font_icon_line_space' );
        if ( $gap !== '' ) {
            $attrs['module']['decoration']['spacing']['desktop']['value']['margin']['top'] = $gap;
        }
        return $this->block( $id . '-icon', 'divi/icon', $attrs );
    }

    /** A Beaver typography array with the module's alignment filled in when the typography set none. */
    private function typographyWithAlign( array $settings, string $key, string $align ): array {
        $raw = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : [];
        if ( ! is_string( $raw['text_align'] ?? null ) || $raw['text_align'] === '' ) {
            $raw['text_align'] = $align;
        }
        return $raw;
    }

    const CONSUMED = [
        'prefix_tag', 'prefix_text', 'heading_tag', 'heading_title', 'dual_heading', 'heading_title2', 'heading_style',
        'heading_alignment', 'enable_link', 'heading_link', 'heading_link_target', 'heading_link_nofollow', 'heading_sub_title',
        'heading_separator', 'heading_separator_postion', 'heading_line_style', 'font_title_line_space', 'hide_separator',
        'heading_icon_select', 'heading_font_icon_select', 'heading_custom_icon_select', 'heading_custom_icon_select_src',
        'font_icon_line_space', 'line_width', 'line_width_unit', 'line_height', 'line_color', 'font_icon_font_size',
        'font_icon_color', 'font_icon_bg_color', 'font_icon_border_width', 'font_icon_border_style', 'font_icon_border_color',
        'font_icon_border_radius', 'prefix_text_color', 'heading_color_type', 'heading_color', 'heading_gradient_setting',
        'heading_hover_color', 'heading_bg_color', 'heading_border_style', 'heading_border_color', 'heading_top_margin',
        'heading_bottom_margin', 'heading2_color_type', 'heading2_color', 'heading2_gradient_setting', 'heading2_hover_color',
        'heading2_bg_color', 'heading2_border_style', 'heading2_border_color', 'heading2_left_margin', 'sub_heading_color',
        'sub_heading_top_margin', 'sub_heading_bottom_margin', 'prefix_typography', 'title_typography', 'title2_typography',
        'desc_typography', 'prefix_typography_medium', 'prefix_typography_responsive', 'title_typography_medium',
        'title_typography_responsive', 'desc_typography_medium', 'desc_typography_responsive',
        'font_icon_padding_top', 'font_icon_padding_right', 'font_icon_padding_bottom', 'font_icon_padding_left',
        'separator_margin_top', 'separator_margin_right', 'separator_margin_bottom', 'separator_margin_left',
        'heading_border_top', 'heading_border_right', 'heading_border_bottom', 'heading_border_left',
        'heading_padding_top', 'heading_padding_right', 'heading_padding_bottom', 'heading_padding_left',
        'heading2_border_top', 'heading2_border_right', 'heading2_border_bottom', 'heading2_border_left',
        'heading2_padding_top', 'heading2_padding_right', 'heading2_padding_bottom', 'heading2_padding_left',
    ];
}
