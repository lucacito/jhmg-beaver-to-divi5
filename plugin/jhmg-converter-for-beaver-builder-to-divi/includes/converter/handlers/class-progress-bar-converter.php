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
 * Beaver Builder Pro "Progress Bar" module →
 *   - horizontal / vertical layouts: divi/counters holding one divi/counter per bar;
 *   - circular layout: one divi/circle-counter per bar.
 *
 * Pro module: field names come from exported layouts, so the conversion is
 * registered approximate. A bar with no number in the source is drawn full,
 * mirroring Beaver Builder's own default of 100 for its counter modules, and
 * the report says so.
 */
class ProgressBarConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_progress_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $layout = strtolower( $this->text( $settings, 'layout' ) ) ?: 'horizontal';
        if ( ! in_array( $layout, [ 'horizontal', 'vertical', 'circular' ], true ) ) {
            $layout = 'horizontal';
        }
        $bars = [];
        foreach ( is_array( $settings[ $layout ] ?? null ) ? $settings[ $layout ] : [] as $bar ) {
            if ( is_object( $bar ) ) {
                $bar = (array) $bar;
            }
            if ( is_array( $bar ) ) {
                $bars[] = $bar;
            }
        }
        if ( $bars === [] ) {
            $bars = [ [] ];
        }

        $style   = $this->mapStyle( 'counter', $node );
        $attrs   = $style['divi_attrs'];
        $handled = $style['handled_keys'];

        if ( $layout === 'circular' ) {
            $blocks = [];
            foreach ( $bars as $i => $bar ) {
                $blocks[] = $this->circle( $id . '-' . ( $i + 1 ), $bar, $settings, $handled, $id );
            }
            $this->spreadModuleSpacing( $blocks, $attrs['module'] ?? [] );
            $this->engine->logConverted( 'circle-counter' );
            $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );
            return $blocks;
        }

        if ( $layout === 'vertical' ) {
            $this->engine->logWarning( "Progress bar {$id}: vertical bars are drawn horizontally; Divi's bar counter has no vertical layout." );
        }
        if ( strtolower( $this->text( $settings, 'stripped' ) ) === 'yes' ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'striped bar pattern' );
        }
        $text_position = strtolower( $this->text( $settings, 'text_position' ) );
        if ( $text_position !== '' && $text_position !== 'above' ) {
            $this->engine->logWarning( "Progress bar {$id}: the label sits above the bar in Divi (source had it '{$text_position}')." );
        }

        $align = $this->text( $settings, 'title_alignment' ) ?: $this->text( $settings, 'overall_alignment' );
        $align = in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : '';

        $children = [];
        foreach ( $bars as $i => $bar ) {
            $counter = [];
            $prefix  = $layout . '_';
            $title   = trim( (string) ( is_string( $bar[ $prefix . 'before_number' ] ?? null ) ? $bar[ $prefix . 'before_number' ] : '' ) );
            if ( $title !== '' ) {
                $counter['title']['innerContent']['desktop']['value'] = $title;
            }
            $counter['barProgress']['innerContent']['desktop']['value'] = $this->percent( $id, $bar[ $prefix . 'number' ] ?? null );

            $fill = $this->fill( $bar, $id );
            if ( $fill !== [] ) {
                $counter['barProgress']['decoration']['background']['desktop']['value'] = $fill;
            }
            $track = $this->color( $bar, 'background_color', $id );
            if ( $track !== null ) {
                $counter['barCounter']['decoration']['background']['desktop']['value']['color'] = $track;
            }
            $thickness = Size::fromSettings( $settings, $layout . '_thickness', $layout . '_thickness' );
            if ( $thickness !== '' ) {
                $counter['barCounter']['decoration']['sizing']['desktop']['value']['height'] = $thickness;
            }
            if ( is_array( $settings['progress_border'] ?? null ) ) {
                $shadow_scratch = [];
                ( new StyleMapper() )->applyBorder( $settings, 'progress_border', 'barCounter.decoration.border', 'barCounter.decoration.boxShadow', $counter, $shadow_scratch );
            }

            ( new StyleMapper() )->applyTypography( $settings, 'text_typo', 'title.decoration.font.font', $counter, $handled );
            ( new StyleMapper() )->applyTypography( $settings, 'number_typo', 'barProgress.decoration.font.font', $counter, $handled );
            $text_color = $this->color( $settings, 'text_color', $id ) ?? $this->engine->inheritedColor( 'text_color' );
            if ( $text_color !== null ) {
                $counter['title']['decoration']['font']['font']['desktop']['value']['color'] = $text_color;
            }
            $number_color = $this->color( $settings, 'number_color', $id );
            if ( $number_color !== null ) {
                $counter['barProgress']['decoration']['font']['font']['desktop']['value']['color'] = $number_color;
            }
            if ( $align !== '' && ! isset( $counter['title']['decoration']['font']['font']['desktop']['value']['textAlign'] ) ) {
                $counter['title']['decoration']['font']['font']['desktop']['value']['textAlign'] = $align;
            }

            $children[] = $this->block( $id . '-' . ( $i + 1 ), 'divi/counter', $counter );
        }

        unset( $attrs['number'], $attrs['title'] );

        // Divi paints the percentage inside the bar; on a thin bar it would overflow it.
        $thickness = \BeaverDivi5Converter\Helpers\Size::number( Size::fromSettings( $settings, $layout . '_thickness', $layout . '_thickness' ) );
        if ( $thickness !== null && $thickness < 18 ) {
            $attrs['barProgress']['advanced']['usePercentages']['desktop']['value'] = 'off';
            $this->engine->logWarning( "Progress bar {$id}: the percentage label is hidden; Divi draws it inside the bar, which is only {$thickness}px tall." );
        }

        $this->engine->logConverted( 'counters' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $handled, self::CONSUMED ) );

        return $this->block( $id, 'divi/counters', $attrs, $children );
    }

    private function circle( string $id, array $bar, array $settings, array &$handled, string $source_id ): array {
        $attrs = [
            'number' => [
                'innerContent' => [ 'desktop' => [ 'value' => $this->percent( $source_id, $bar['circular_number'] ?? null ) ] ],
                'advanced'     => [ 'percentSign' => [ 'desktop' => [ 'value' => 'on' ] ] ],
            ],
        ];
        $title = trim( (string) ( is_string( $bar['circular_before_number'] ?? null ) ? $bar['circular_before_number'] : '' ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
        }
        $fill = $this->fill( $bar, $source_id );
        if ( isset( $fill['color'] ) ) {
            $attrs['circle']['advanced']['color']['desktop']['value'] = $fill['color'];
        } elseif ( isset( $fill['gradient']['stops'][0]['color'] ) ) {
            $attrs['circle']['advanced']['color']['desktop']['value'] = $fill['gradient']['stops'][0]['color'];
            $this->engine->logNotCarriedOver( 'background', $source_id, 'gradient fill on a circular progress bar (first colour kept)' );
        }
        ( new StyleMapper() )->applyTypography( $settings, 'text_typo', 'title.decoration.font.font', $attrs, $handled );
        ( new StyleMapper() )->applyTypography( $settings, 'number_typo', 'number.decoration.font.font', $attrs, $handled );
        $text_color = $this->color( $settings, 'text_color', $source_id );
        if ( $text_color !== null ) {
            $attrs['title']['decoration']['font']['font']['desktop']['value']['color'] = $text_color;
        }
        $number_color = $this->color( $settings, 'number_color', $source_id );
        if ( $number_color !== null ) {
            $attrs['number']['decoration']['font']['font']['desktop']['value']['color'] = $number_color;
        }
        return $this->block( $id, 'divi/circle-counter', $attrs );
    }

    /** The bar's fill as a Divi background value: a colour, or a gradient. */
    private function fill( array $bar, string $id ): array {
        $type = strtolower( (string) ( is_string( $bar['progress_bg_type'] ?? null ) ? $bar['progress_bg_type'] : 'color' ) );
        if ( $type === 'gradient' && is_array( $bar['gradient_field'] ?? null ) ) {
            $g   = $bar['gradient_field'];
            $one = Color::normalize( $g['color_one'] ?? '' );
            $two = Color::normalize( $g['color_two'] ?? '' );
            if ( $one !== null || $two !== null ) {
                $one ??= $two;
                $two ??= $one;
                $direction = strtolower( (string) ( is_string( $g['direction'] ?? null ) ? $g['direction'] : 'left_right' ) );
                $angle     = match ( $direction ) {
                    'right_left' => '270deg',
                    'top_bottom' => '180deg',
                    'bottom_top' => '0deg',
                    'angle'      => ( is_numeric( $g['angle'] ?? null ) ? (string) $g['angle'] : '90' ) . 'deg',
                    default      => '90deg',
                };
                return [ 'gradient' => [
                    'enabled'   => 'on',
                    'type'      => 'linear',
                    'direction' => $angle,
                    'stops'     => [ [ 'color' => $one, 'position' => '0' ], [ 'color' => $two, 'position' => '100' ] ],
                ] ];
            }
        }
        if ( $type === 'image' ) {
            $this->engine->logNotCarriedOver( 'background', $id, 'image fill on a progress bar' );
        }
        $color = $this->color( $bar, 'gradient_color', $id ) ?? $this->color( $bar, 'progress_color', $id ) ?? $this->color( $bar, 'bar_color', $id );
        return $color !== null ? [ 'color' => $color ] : [];
    }

    private function percent( string $id, mixed $raw ): string {
        if ( is_numeric( $raw ) ) {
            return (string) max( 0, min( 100, (float) $raw ) );
        }
        $this->engine->logWarning( "Progress bar {$id}: no number set in Beaver Builder; the bar is drawn full (100%), Beaver Builder's counter default." );
        return '100';
    }

    const CONSUMED = [
        'layout', 'horizontal', 'vertical', 'circular', 'overall_alignment', 'spacing', 'stripped', 'horizontal_style',
        'text_position', 'horizontal_thickness', 'horizontal_space_above', 'horizontal_space_below', 'horizontal_vert_padding',
        'horizontal_horz_padding', 'vertical_style', 'title_alignment', 'vertical_thickness', 'vertical_width',
        'vertical_responsive_thickness', 'vertical_responsive_width', 'vertical_responsive', 'circular_thickness', 'stroke_thickness',
        'circular_responsive_width', 'circular_responsive', 'progress_border', 'progress_border_medium', 'progress_border_responsive',
        'animation_speed', 'delay', 'text_tag_selection', 'text_typo', 'text_typo_medium', 'text_typo_responsive', 'text_color',
        'before_after_typo', 'before_after_typo_medium', 'before_after_typo_responsive', 'before_after_color',
        'number_typo', 'number_typo_medium', 'number_typo_responsive', 'number_color',
    ];
}
