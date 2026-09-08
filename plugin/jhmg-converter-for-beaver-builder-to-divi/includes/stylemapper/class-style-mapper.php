<?php
/**
 * Maps Beaver Builder design settings to Divi 5 block attribute paths.
 *
 * Beaver Builder keeps flat keys with `_medium` / `_responsive` (and `_large`)
 * suffixes and a sibling `<root>_unit` key for units. Divi 5 keeps a nested
 * `{breakpoint}.value` structure with `desktop`, `tablet` and `phone`
 * breakpoints. Every attribute path written here was read from Divi 5.7.4's
 * module.json files and style declarations — see docs/divi5-schema.md.
 *
 * map() returns three things: the Divi attrs, the Beaver Builder keys it
 * consumed (so the handler can report what it did not), and notes about
 * things it could only approximate or had to drop, which the engine records
 * under "not carried over".
 */

namespace BeaverDivi5Converter\StyleMapper;

use BeaverDivi5Converter\Helpers\Color;
use BeaverDivi5Converter\Helpers\Size;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StyleMapper {

    /** Beaver Builder suffix ⇒ Divi breakpoint. `_large` (≤1200px) has no Divi equivalent. */
    const BREAKPOINTS = [
        ''            => 'desktop',
        '_medium'     => 'tablet',
        '_responsive' => 'phone',
    ];

    const IGNORED_SUFFIXES = [ '_large' ];

    /** Beaver Builder column `size` (percent) ⇒ Divi fraction. Matched with a tolerance of 1.5. */
    const COLUMN_FRACTIONS = [
        '4_4' => 100,
        '4_5' => 80,
        '3_4' => 75,
        '2_3' => 66.66,
        '3_5' => 60,
        '1_2' => 50,
        '2_5' => 40,
        '1_3' => 33.33,
        '1_4' => 25,
        '1_5' => 20,
    ];

    /** Divi font decoration path per node kind (the primary text). */
    const FONT_PATH = [
        'heading' => 'title.decoration.font.font',
        'text'    => 'content.decoration.bodyFont.body.font',
        'button'  => 'button.decoration.font.font',
        'blurb'   => 'title.decoration.font.font',
        'cta'     => 'title.decoration.font.font',
        'counter' => 'number.decoration.font.font',
    ];

    /** Which Beaver Builder key carries the primary text colour, per kind. */
    const COLOR_KEY = [
        'heading' => 'color',
        'text'    => 'color',
        'button'  => 'text_color',
        'blurb'   => 'title_color',
        'cta'     => 'title_color',
        'counter' => 'number_color',
    ];

    /** Style flag names Divi's Font declaration understands. */
    const STYLE_FLAGS = [ 'italic', 'uppercase', 'capitalize', 'lowercase', 'underline', 'strikethrough' ];

    /**
     * @param string $kind row | column | group | heading | text | image | button | blurb | cta | icon | counter | generic
     * @return array{divi_attrs: array, handled_keys: string[], notes: array<int,array{kind:string,detail:string}>}
     */
    public function map( string $kind, array $settings ): array {
        $attrs   = [];
        $handled = [];
        $notes   = [];

        $this->mapSpacing( $kind, $settings, $attrs, $handled );
        $this->mapBackground( $kind, $settings, $attrs, $handled, $notes );
        $this->mapBorder( $kind, $settings, $attrs, $handled );
        $this->mapTypography( $kind, $settings, $attrs, $handled );
        $this->mapTextColor( $kind, $settings, $attrs, $handled, $notes );
        $this->mapContainerTextColors( $kind, $settings, $attrs, $handled );
        $this->mapAlignment( $kind, $settings, $attrs, $handled );
        $this->mapSizing( $kind, $settings, $attrs, $handled );
        $this->mapHtmlAttributes( $settings, $attrs, $handled );
        $this->mapVisibility( $settings, $attrs, $handled, $notes );
        $this->acknowledgeCommonKeys( $kind, $settings, $handled, $notes );

        return [
            'divi_attrs'   => $attrs,
            'handled_keys' => array_values( array_unique( $handled ) ),
            'notes'        => $notes,
        ];
    }

    // -------------------------------------------------------------------------
    // Public helpers used by handlers
    // -------------------------------------------------------------------------

    /** Beaver Builder column percentage ⇒ Divi fraction string, or null when it is not a standard one. */
    public static function columnSizeToFraction( float $percent ): ?string {
        foreach ( self::COLUMN_FRACTIONS as $fraction => $target ) {
            if ( abs( $percent - $target ) <= 1.5 ) {
                return $fraction;
            }
        }
        return null;
    }

    public static function fontPathFor( string $kind ): ?string {
        return self::FONT_PATH[ $kind ] ?? null;
    }

    /**
     * Applies one Beaver Builder typography field (and its breakpoint variants)
     * to a Divi font path. Public so handlers can map secondary text such as a
     * callout's description or a counter's label.
     *
     * @param string $key       Beaver Builder field name, e.g. 'typography', 'title_typography'.
     * @param string $font_path Divi path up to but not including `.{breakpoint}.value`.
     * @param bool   $align_as_orientation Write text_align to module.advanced.text orientation
     *                                     (Divi text/blurb bodies) instead of the font's textAlign.
     */
    public function applyTypography( array $settings, string $key, string $font_path, array &$attrs, array &$handled, bool $align_as_orientation = false ): void {
        foreach ( self::BREAKPOINTS as $suffix => $bp ) {
            $handled[] = $key . $suffix;
            $raw       = $settings[ $key . $suffix ] ?? null;
            if ( ! is_array( $raw ) || empty( $raw ) ) {
                continue;
            }

            $family = $raw['font_family'] ?? '';
            if ( is_string( $family ) && $family !== '' && strtolower( $family ) !== 'default' ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.family", $family );
            }

            $weight = $raw['font_weight'] ?? '';
            $flags  = [];
            if ( is_string( $weight ) || is_int( $weight ) ) {
                $weight = (string) $weight;
                if ( $weight !== '' && strtolower( $weight ) !== 'default' ) {
                    if ( str_ends_with( $weight, 'i' ) ) {
                        $flags[] = 'italic';
                        $weight  = substr( $weight, 0, -1 );
                    }
                    if ( $weight === 'italic' ) {
                        $flags[] = 'italic';
                    } elseif ( $weight !== '' ) {
                        self::write( $attrs, "{$font_path}.{$bp}.value.weight", $weight );
                    }
                }
            }

            $size = Size::fromLength( $raw['font_size'] ?? null );
            if ( $size !== '' ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.size", $size );
            }
            $line_height = Size::fromLength( $raw['line_height'] ?? null, '' );
            if ( $line_height !== '' ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.lineHeight", $line_height );
            }
            $spacing = Size::fromLength( $raw['letter_spacing'] ?? null );
            if ( $spacing !== '' ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.letterSpacing", $spacing );
            }

            $align = $raw['text_align'] ?? '';
            if ( is_string( $align ) && in_array( $align, [ 'left', 'center', 'right', 'justify' ], true ) ) {
                if ( $align_as_orientation ) {
                    self::write( $attrs, "module.advanced.text.text.{$bp}.value.orientation", $align === 'justify' ? 'left' : $align );
                } else {
                    self::write( $attrs, "{$font_path}.{$bp}.value.textAlign", $align );
                }
            }

            if ( ( $raw['font_style'] ?? '' ) === 'italic' ) {
                $flags[] = 'italic';
            }
            $transform = $raw['text_transform'] ?? '';
            if ( is_string( $transform ) && in_array( $transform, [ 'uppercase', 'lowercase', 'capitalize' ], true ) ) {
                $flags[] = $transform;
            }
            $decoration = $raw['text_decoration'] ?? '';
            if ( $decoration === 'underline' ) {
                $flags[] = 'underline';
            } elseif ( $decoration === 'line-through' ) {
                $flags[] = 'strikethrough';
            }
            $flags = array_values( array_unique( array_intersect( $flags, self::STYLE_FLAGS ) ) );
            if ( ! empty( $flags ) ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.style", $flags );
            }

            $shadow = $raw['text_shadow'] ?? null;
            if ( is_array( $shadow ) ) {
                $color = Color::normalize( $shadow['color'] ?? '' );
                if ( $color !== null ) {
                    self::write( $attrs, "{$font_path}.{$bp}.value.textShadow", [
                        'style'      => 'preset1',
                        'color'      => $color,
                        'horizontal' => Size::withUnit( $shadow['horizontal'] ?? '0', null ) ?: '0px',
                        'vertical'   => Size::withUnit( $shadow['vertical'] ?? '0', null ) ?: '0px',
                        'blur'       => Size::withUnit( $shadow['blur'] ?? '0', null ) ?: '0px',
                    ] );
                }
            }
        }
        foreach ( self::IGNORED_SUFFIXES as $suffix ) {
            $handled[] = $key . $suffix;
        }
    }

    /**
     * Applies a Beaver Builder `border` compound field to a Divi border +
     * box-shadow path pair. Public for handlers whose border targets a
     * sub-element (the button face, the photo).
     */
    public function applyBorder( array $settings, string $key, string $border_path, string $shadow_path, array &$attrs, array &$handled ): void {
        foreach ( self::BREAKPOINTS as $suffix => $bp ) {
            $handled[] = $key . $suffix;
            $raw       = $settings[ $key . $suffix ] ?? null;
            if ( ! is_array( $raw ) ) {
                continue;
            }

            $style = $raw['style'] ?? '';
            if ( is_string( $style ) && $style !== '' ) {
                self::write( $attrs, "{$border_path}.{$bp}.value.styles.all.style", $style );
            }
            $color = Color::normalize( $raw['color'] ?? '' );
            if ( $color !== null ) {
                self::write( $attrs, "{$border_path}.{$bp}.value.styles.all.color", $color );
            }

            $width = $raw['width'] ?? null;
            if ( is_array( $width ) ) {
                $sides  = [];
                foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                    $v = Size::withUnit( $width[ $side ] ?? '', null );
                    if ( $v !== '' ) {
                        $sides[ $side ] = $v;
                    }
                }
                if ( ! empty( $sides ) ) {
                    if ( count( array_unique( $sides ) ) === 1 && count( $sides ) === 4 ) {
                        self::write( $attrs, "{$border_path}.{$bp}.value.styles.all.width", reset( $sides ) );
                    } else {
                        foreach ( $sides as $side => $v ) {
                            self::write( $attrs, "{$border_path}.{$bp}.value.styles.{$side}.width", $v );
                        }
                    }
                }
            }

            $radius = $raw['radius'] ?? null;
            if ( is_array( $radius ) ) {
                $corners = [
                    'topLeft'     => Size::withUnit( $radius['top_left'] ?? '', null ),
                    'topRight'    => Size::withUnit( $radius['top_right'] ?? '', null ),
                    'bottomRight' => Size::withUnit( $radius['bottom_right'] ?? '', null ),
                    'bottomLeft'  => Size::withUnit( $radius['bottom_left'] ?? '', null ),
                ];
                if ( array_filter( $corners ) !== [] ) {
                    // Divi expects all four corners; an unset corner is 0.
                    self::write( $attrs, "{$border_path}.{$bp}.value.radius", array_map( static fn( string $v ) => $v !== '' ? $v : '0px', $corners ) );
                }
            }

            $shadow = $raw['shadow'] ?? null;
            if ( is_array( $shadow ) ) {
                $shadow_color = Color::normalize( $shadow['color'] ?? '' );
                if ( $shadow_color !== null ) {
                    self::write( $attrs, "{$shadow_path}.{$bp}.value", [
                        'style'      => 'preset1',
                        'position'   => 'outer',
                        'color'      => $shadow_color,
                        'horizontal' => Size::withUnit( $shadow['horizontal'] ?? '0', null ) ?: '0px',
                        'vertical'   => Size::withUnit( $shadow['vertical'] ?? '0', null ) ?: '0px',
                        'blur'       => Size::withUnit( $shadow['blur'] ?? '0', null ) ?: '0px',
                        'spread'     => Size::withUnit( $shadow['spread'] ?? '0', null ) ?: '0px',
                    ] );
                }
            }
        }
        foreach ( self::IGNORED_SUFFIXES as $suffix ) {
            $handled[] = $key . $suffix;
        }
    }

    /**
     * Beaver Builder dimension field (`padding_top`… + `padding_unit`, per
     * breakpoint) → a Divi spacing value at $path.{bp}.value.$prop.
     */
    public function applySpacing( array $settings, string $prop, string $path, array &$attrs, array &$handled, ?string $write_as = null ): void {
        $write_as = $write_as ?? $prop;
        foreach ( array_merge( self::BREAKPOINTS, [ '_large' => null ] ) as $suffix => $bp ) {
            $sides = [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                $handled[] = $prop . '_' . $side . $suffix;
                $value     = Size::fromSettings( $settings, $prop . '_' . $side, $prop, $suffix );
                if ( $value !== '' ) {
                    $sides[ $side ] = $value;
                }
            }
            $handled[] = $prop . $suffix . '_unit';
            $handled[] = $prop . $suffix;

            if ( $bp === null || empty( $sides ) ) {
                continue;
            }

            self::write( $attrs, "{$path}.{$bp}.value.{$write_as}", array_merge(
                [ 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' ],
                $sides,
                [ 'syncVertical' => 'off', 'syncHorizontal' => 'off' ]
            ) );
        }
    }

    /** Write a value into a nested array using a dot path. */
    public static function write( array &$target, string $dot_path, mixed $value ): void {
        $keys    = explode( '.', $dot_path );
        $current = &$target;
        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $current ) || ! is_array( $current[ $key ] ) ) {
                $current[ $key ] = [];
            }
            $current = &$current[ $key ];
        }
        $current = $value;
    }

    // -------------------------------------------------------------------------
    // Per-family mappers
    // -------------------------------------------------------------------------

    private function mapSpacing( string $kind, array $settings, array &$attrs, array &$handled ): void {
        // Divi rows manage inter-column spacing through gutters; a column margin
        // written with !important breaks that layout, so columns keep padding only.
        $props = $kind === 'column' ? [ 'padding' ] : [ 'margin', 'padding' ];

        // The button module's own padding is the button face, not the wrapper.
        if ( $kind === 'button' ) {
            $props = [ 'margin' ];
            $this->applySpacing( $settings, 'padding', 'button.decoration.spacing', $attrs, $handled );
        }

        foreach ( $props as $prop ) {
            $this->applySpacing( $settings, $prop, 'module.decoration.spacing', $attrs, $handled );
        }

        if ( $kind === 'column' ) {
            foreach ( array_merge( array_keys( self::BREAKPOINTS ), self::IGNORED_SUFFIXES ) as $suffix ) {
                foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                    $handled[] = 'margin_' . $side . $suffix;
                }
                $handled[] = 'margin' . $suffix . '_unit';
            }
        }
    }

    private function mapBackground( string $kind, array $settings, array &$attrs, array &$handled, array &$notes ): void {
        $bg_keys = [
            'bg_type', 'bg_color', 'bg_image', 'bg_image_src', 'bg_image_source', 'bg_image_url', 'bg_repeat',
            'bg_position', 'bg_x_position', 'bg_y_position', 'bg_attachment', 'bg_size', 'bg_gradient',
            'bg_overlay_type', 'bg_overlay_color', 'bg_overlay_gradient', 'bg_parallax_image',
            'bg_parallax_image_src', 'bg_parallax_speed', 'bg_parallax_offset', 'bg_video_source', 'bg_video',
            'bg_video_webm', 'bg_video_url_mp4', 'bg_video_url_webm', 'bg_video_service_url', 'bg_video_audio',
            'bg_video_play_pause', 'bg_video_mobile', 'bg_video_fallback', 'bg_video_fallback_src',
            'ss_source', 'ss_photos', 'ss_feed_url', 'ss_speed', 'ss_transition', 'ss_transitionDuration',
            'ss_randomize', 'bg_embed_code', 'background', 'bg_hover_color',
        ];
        foreach ( $bg_keys as $key ) {
            foreach ( array_merge( array_keys( self::BREAKPOINTS ), self::IGNORED_SUFFIXES ) as $suffix ) {
                $handled[] = $key . $suffix;
            }
        }

        $type = $settings['bg_type'] ?? null;
        $type = is_string( $type ) ? $type : '';

        // Modules such as callout, cta and icon carry a bare bg_color with no bg_type.
        if ( $type === '' ) {
            $color = Color::normalize( $settings['bg_color'] ?? '' );
            if ( $color !== null ) {
                self::write( $attrs, 'module.decoration.background.desktop.value.color', $color );
            } elseif ( $this->isUnresolvedGlobal( $settings['bg_color'] ?? '' ) ) {
                $notes[] = [ 'kind' => 'unresolved_global', 'detail' => 'bg_color=' . $settings['bg_color'] ];
            }
            return;
        }

        if ( $type === 'none' ) {
            return;
        }

        if ( $type === 'color' || $type === 'photo' || $type === 'video' || $type === 'parallax' || $type === 'slideshow' ) {
            $color = Color::normalize( $settings['bg_color'] ?? '' );
            if ( $color !== null ) {
                self::write( $attrs, 'module.decoration.background.desktop.value.color', $color );
            } elseif ( $this->isUnresolvedGlobal( $settings['bg_color'] ?? '' ) ) {
                $notes[] = [ 'kind' => 'unresolved_global', 'detail' => 'bg_color=' . $settings['bg_color'] ];
            }
        }

        if ( $type === 'gradient' ) {
            $this->applyGradient( $settings['bg_gradient'] ?? null, 'module.decoration.background.desktop.value.gradient', $attrs, false );
            return;
        }

        $image_url = '';
        if ( $type === 'photo' ) {
            $image_url = $this->photoBackgroundUrl( $settings );
        } elseif ( $type === 'video' ) {
            $image_url = $this->firstString( $settings, [ 'bg_video_fallback_src' ] );
            $notes[]   = [ 'kind' => 'background', 'detail' => $image_url !== '' ? 'video background replaced by its fallback image' : 'video background dropped (no fallback image)' ];
        } elseif ( $type === 'parallax' ) {
            $image_url = $this->firstString( $settings, [ 'bg_parallax_image_src' ] );
            $notes[]   = [ 'kind' => 'background', 'detail' => 'parallax background kept as a static image' ];
        } elseif ( $type === 'slideshow' ) {
            $notes[] = [ 'kind' => 'background', 'detail' => 'slideshow background dropped' ];
        } elseif ( $type === 'embed' ) {
            $notes[] = [ 'kind' => 'background', 'detail' => 'embedded-code background dropped' ];
        } elseif ( $type === 'multiple' ) {
            $image_url = $this->multipleBackground( $settings, $attrs, $notes );
        }

        if ( $image_url !== '' ) {
            foreach ( self::BREAKPOINTS as $suffix => $bp ) {
                $base = "module.decoration.background.{$bp}.value.image";
                $url  = $suffix === '' ? $image_url : $this->firstString( $settings, [ 'bg_image' . $suffix . '_src' ] );
                if ( $url === '' ) {
                    continue;
                }
                self::write( $attrs, "{$base}.url", $url );

                $position = $settings[ 'bg_position' . $suffix ] ?? '';
                if ( is_string( $position ) && $position !== '' && $position !== 'custom_pos' ) {
                    self::write( $attrs, "{$base}.position", $position );
                }
                $size = $settings[ 'bg_size' . $suffix ] ?? '';
                if ( is_string( $size ) && in_array( $size, [ 'cover', 'contain', 'auto' ], true ) ) {
                    self::write( $attrs, "{$base}.size", $size );
                }
                $repeat = $settings[ 'bg_repeat' . $suffix ] ?? '';
                if ( is_string( $repeat ) && in_array( $repeat, [ 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ], true ) ) {
                    self::write( $attrs, "{$base}.repeat", $repeat );
                }
            }
        }

        // Overlay: Divi paints a gradient over the image when overlaysImage is on.
        $overlay = $settings['bg_overlay_type'] ?? '';
        if ( $image_url !== '' && $overlay === 'color' ) {
            $color = Color::normalize( $settings['bg_overlay_color'] ?? '' );
            if ( $color !== null ) {
                $this->applyGradient(
                    [ 'type' => 'linear', 'angle' => '180', 'position' => 'center center', 'colors' => [ $color, $color ], 'stops' => [ '0', '100' ] ],
                    'module.decoration.background.desktop.value.gradient',
                    $attrs,
                    true
                );
            }
        } elseif ( $image_url !== '' && $overlay === 'gradient' ) {
            $this->applyGradient( $settings['bg_overlay_gradient'] ?? null, 'module.decoration.background.desktop.value.gradient', $attrs, true );
        } elseif ( $image_url === '' && in_array( $overlay, [ 'color', 'gradient' ], true ) && ! isset( $attrs['module']['decoration']['background']['desktop']['value']['color'] ) ) {
            // No image survived (video/slideshow dropped) — keep the overlay as the background itself.
            if ( $overlay === 'color' ) {
                $color = Color::normalize( $settings['bg_overlay_color'] ?? '' );
                if ( $color !== null ) {
                    self::write( $attrs, 'module.decoration.background.desktop.value.color', $color );
                }
            } else {
                $this->applyGradient( $settings['bg_overlay_gradient'] ?? null, 'module.decoration.background.desktop.value.gradient', $attrs, false );
            }
        }
    }

    private function photoBackgroundUrl( array $settings ): string {
        if ( ( $settings['bg_image_source'] ?? 'library' ) === 'url' ) {
            $url = $settings['bg_image_url'] ?? '';
            if ( is_array( $url ) ) {
                $url = $url['url'] ?? '';
            }
            return is_string( $url ) ? trim( $url ) : '';
        }
        return $this->firstString( $settings, [ 'bg_image_src' ] );
    }

    /** Beaver Builder 2.9 "Multiple Backgrounds" layers: colour and image layers only. */
    private function multipleBackground( array $settings, array &$attrs, array &$notes ): string {
        $layers = $settings['background'] ?? null;
        if ( ! is_array( $layers ) ) {
            return '';
        }
        $image_url = '';
        foreach ( $layers as $layer ) {
            if ( ! is_array( $layer ) ) {
                continue;
            }
            $state = is_array( $layer['state'] ?? null ) ? $layer['state'] : [];
            if ( ( $layer['type'] ?? '' ) === 'color' ) {
                $color = Color::normalize( $state['color'] ?? '' );
                if ( $color !== null && ! isset( $attrs['module']['decoration']['background']['desktop']['value']['color'] ) ) {
                    self::write( $attrs, 'module.decoration.background.desktop.value.color', $color );
                }
            } elseif ( ( $layer['type'] ?? '' ) === 'image' && $image_url === '' ) {
                $image_url = $this->firstString( $state, [ 'image_src', 'src', 'url' ] );
            } else {
                $notes[] = [ 'kind' => 'background', 'detail' => 'background layer of type ' . ( $layer['type'] ?? '?' ) . ' dropped' ];
            }
        }
        return $image_url;
    }

    /**
     * Beaver Builder gradient {type, angle, position, colors[], stops[]} →
     * Divi gradient {enabled, type, direction|directionRadial, stops[], overlaysImage}.
     */
    private function applyGradient( mixed $raw, string $path, array &$attrs, bool $overlays_image ): void {
        if ( ! is_array( $raw ) || ! is_array( $raw['colors'] ?? null ) ) {
            return;
        }
        $stops = [];
        foreach ( array_values( $raw['colors'] ) as $i => $c ) {
            $color = Color::normalize( $c );
            if ( $color === null ) {
                continue;
            }
            $stop    = $raw['stops'][ $i ] ?? ( $i === 0 ? '0' : '100' );
            $stops[] = [ 'color' => $color, 'position' => ( is_numeric( $stop ) ? (string) $stop : '0' ) . '%' ];
        }
        if ( count( $stops ) < 2 ) {
            if ( count( $stops ) === 1 ) {
                $stops[] = [ 'color' => $stops[0]['color'], 'position' => '100%' ];
            } else {
                return;
            }
        }
        $type = ( $raw['type'] ?? 'linear' ) === 'radial' ? 'radial' : 'linear';

        self::write( $attrs, "{$path}.enabled", 'on' );
        self::write( $attrs, "{$path}.type", $type );
        if ( $type === 'radial' ) {
            $position = $raw['position'] ?? 'center center';
            self::write( $attrs, "{$path}.directionRadial", is_string( $position ) && $position !== '' ? $position : 'center center' );
        } else {
            $angle = $raw['angle'] ?? '180';
            self::write( $attrs, "{$path}.direction", ( is_numeric( $angle ) ? (string) $angle : '180' ) . 'deg' );
        }
        self::write( $attrs, "{$path}.stops", $stops );
        if ( $overlays_image ) {
            self::write( $attrs, "{$path}.overlaysImage", 'on' );
        }
    }

    private function mapBorder( string $kind, array $settings, array &$attrs, array &$handled ): void {
        if ( $kind === 'button' ) {
            $this->applyBorder( $settings, 'border', 'button.decoration.border', 'button.decoration.boxShadow', $attrs, $handled );
            foreach ( array_keys( self::BREAKPOINTS ) as $suffix ) {
                $handled[] = 'border_hover_color' . $suffix;
            }
            return;
        }
        if ( $kind === 'image' ) {
            $this->applyBorder( $settings, 'border', 'image.decoration.border', 'image.decoration.boxShadow', $attrs, $handled );
            return;
        }
        $this->applyBorder( $settings, 'border', 'module.decoration.border', 'module.decoration.boxShadow', $attrs, $handled );
    }

    private function mapTypography( string $kind, array $settings, array &$attrs, array &$handled ): void {
        $font_path = self::fontPathFor( $kind );
        if ( $font_path === null ) {
            return;
        }
        $key = in_array( $kind, [ 'blurb', 'cta' ], true ) ? 'title_typography' : 'typography';
        $this->applyTypography( $settings, $key, $font_path, $attrs, $handled, $kind === 'text' );
    }

    private function mapTextColor( string $kind, array $settings, array &$attrs, array &$handled, array &$notes ): void {
        $font_path = self::fontPathFor( $kind );
        $key       = self::COLOR_KEY[ $kind ] ?? null;
        if ( $font_path === null || $key === null ) {
            return;
        }
        foreach ( self::BREAKPOINTS as $suffix => $bp ) {
            $handled[] = $key . $suffix;
            $raw       = $settings[ $key . $suffix ] ?? '';
            $color     = Color::normalize( $raw );
            if ( $color !== null ) {
                self::write( $attrs, "{$font_path}.{$bp}.value.color", $color );
            } elseif ( $this->isUnresolvedGlobal( $raw ) ) {
                $notes[] = [ 'kind' => 'unresolved_global', 'detail' => $key . '=' . $raw ];
            }
        }
        foreach ( self::IGNORED_SUFFIXES as $suffix ) {
            $handled[] = $key . $suffix;
        }
    }

    /**
     * Rows and columns can force text, link and heading colours on everything
     * inside them. Divi sections/rows have no font settings, so these become
     * Divi's custom CSS on the module: `main` for the text colour, and a
     * free-form rule for headings and links.
     */
    private function mapContainerTextColors( string $kind, array $settings, array &$attrs, array &$handled ): void {
        if ( ! in_array( $kind, [ 'row', 'column', 'group' ], true ) ) {
            return;
        }
        $handled = array_merge( $handled, [ 'text_color', 'link_color', 'hover_color', 'heading_color' ] );

        $text = Color::normalize( $settings['text_color'] ?? '' );
        if ( $text !== null ) {
            self::write( $attrs, 'css.desktop.value.main', 'color: ' . $text . ';' );
        }

        $rules   = [];
        $heading = Color::normalize( $settings['heading_color'] ?? '' );
        if ( $heading !== null ) {
            $rules[] = 'selector h1, selector h2, selector h3, selector h4, selector h5, selector h6 { color: ' . $heading . '; }';
        }
        $link = Color::normalize( $settings['link_color'] ?? '' );
        if ( $link !== null ) {
            $rules[] = 'selector a { color: ' . $link . '; }';
        }
        $hover = Color::normalize( $settings['hover_color'] ?? '' );
        if ( $hover !== null ) {
            $rules[] = 'selector a:hover { color: ' . $hover . '; }';
        }
        if ( ! empty( $rules ) ) {
            self::write( $attrs, 'css.desktop.value.freeForm', implode( ' ', $rules ) );
        }
    }

    private function mapAlignment( string $kind, array $settings, array &$attrs, array &$handled ): void {
        $key = $kind === 'cta' ? 'alignment' : 'align';
        foreach ( array_merge( array_keys( self::BREAKPOINTS ), self::IGNORED_SUFFIXES ) as $suffix ) {
            $handled[] = $key . $suffix;
        }
        if ( in_array( $kind, [ 'row', 'column', 'heading', 'text', 'group' ], true ) ) {
            return;
        }
        foreach ( self::BREAKPOINTS as $suffix => $bp ) {
            $value = $settings[ $key . $suffix ] ?? '';
            if ( ! is_string( $value ) || ! in_array( $value, [ 'left', 'center', 'right' ], true ) ) {
                continue;
            }
            switch ( $kind ) {
                case 'button':
                    self::write( $attrs, "module.advanced.alignment.{$bp}.value", $value );
                    break;
                case 'image':
                    self::write( $attrs, "module.advanced.align.{$bp}.value", $value );
                    break;
                case 'icon':
                    self::write( $attrs, "icon.advanced.align.{$bp}.value", $value );
                    break;
                default:
                    self::write( $attrs, "module.advanced.text.text.{$bp}.value.orientation", $value );
            }
        }
    }

    private function mapSizing( string $kind, array $settings, array &$attrs, array &$handled ): void {
        foreach ( array_merge( array_keys( self::BREAKPOINTS ), self::IGNORED_SUFFIXES ) as $suffix ) {
            $handled[] = 'min_height' . $suffix;
            $handled[] = 'min_height' . $suffix . '_unit';
            $handled[] = 'full_height';
            $handled[] = 'aspect_ratio' . $suffix;
        }

        $full_height = ( $settings['full_height'] ?? '' ) === 'full';
        foreach ( self::BREAKPOINTS as $suffix => $bp ) {
            $value = Size::fromSettings( $settings, 'min_height', 'min_height', $suffix );
            if ( $value === '' && $suffix === '' && $full_height ) {
                $value = '100vh';
            }
            if ( $value !== '' && ( $full_height || ( $settings['full_height'] ?? 'custom' ) !== 'default' ) ) {
                self::write( $attrs, "module.decoration.sizing.{$bp}.value.minHeight", $value );
            }
        }

        if ( $kind === 'image' ) {
            foreach ( self::BREAKPOINTS as $suffix => $bp ) {
                $handled[] = 'width' . $suffix;
                $handled[] = 'width' . $suffix . '_unit';
                $width     = Size::fromSettings( $settings, 'width', 'width', $suffix );
                if ( $width !== '' ) {
                    self::write( $attrs, "module.decoration.sizing.{$bp}.value.width", $width );
                }
            }
            $handled[] = 'width_large';
            $handled[] = 'width_large_unit';
        }
    }

    private function mapHtmlAttributes( array $settings, array &$attrs, array &$handled ): void {
        $handled[] = 'id';
        $handled[] = 'class';
        $id    = is_string( $settings['id'] ?? null ) ? trim( $settings['id'] ) : '';
        $class = is_string( $settings['class'] ?? null ) ? trim( $settings['class'] ) : '';
        if ( $id !== '' ) {
            self::write( $attrs, 'module.advanced.htmlAttributes.desktop.value.id', $id );
        }
        if ( $class !== '' ) {
            self::write( $attrs, 'module.advanced.htmlAttributes.desktop.value.class', $class );
        }
    }

    /**
     * `responsive_display` lists the breakpoints a node shows on. Older
     * builds store hyphenated ranges ("desktop-medium"), newer ones a
     * comma list ("desktop,large,medium"); "" means everywhere.
     */
    private function mapVisibility( array $settings, array &$attrs, array &$handled, array &$notes ): void {
        $handled[] = 'responsive_display';
        $handled[] = 'visibility_display';
        $handled[] = 'visibility_user_capability';

        $raw = $settings['responsive_display'] ?? '';
        if ( is_string( $raw ) && trim( $raw ) !== '' ) {
            $tokens = preg_split( '/[\s,\-]+/', strtolower( trim( $raw ) ) ) ?: [];
            $shown  = [
                'desktop' => in_array( 'desktop', $tokens, true ) || in_array( 'large', $tokens, true ),
                'tablet'  => in_array( 'medium', $tokens, true ),
                'phone'   => in_array( 'mobile', $tokens, true ) || in_array( 'responsive', $tokens, true ),
            ];
            // Old hyphen form lists the two ends of a range: "desktop-medium" = desktop + medium.
            if ( str_contains( $raw, '-' ) && ! str_contains( $raw, ',' ) ) {
                $ends = explode( '-', strtolower( trim( $raw ) ) );
                $order = [ 'desktop', 'medium', 'mobile' ];
                $from  = array_search( $ends[0], $order, true );
                $to    = array_search( end( $ends ), $order, true );
                if ( $from !== false && $to !== false ) {
                    $shown = [ 'desktop' => false, 'tablet' => false, 'phone' => false ];
                    foreach ( array_slice( $order, min( $from, $to ), abs( $to - $from ) + 1 ) as $device ) {
                        $shown[ [ 'desktop' => 'desktop', 'medium' => 'tablet', 'mobile' => 'phone' ][ $device ] ] = true;
                    }
                }
            }
            if ( in_array( false, $shown, true ) ) {
                foreach ( $shown as $bp => $visible ) {
                    self::write( $attrs, "module.decoration.disabledOn.{$bp}.value", $visible ? 'off' : 'on' );
                }
            }
        }

        $visibility = $settings['visibility_display'] ?? '';
        if ( is_string( $visibility ) && in_array( $visibility, [ 'logged_in', 'logged_out' ], true ) ) {
            $notes[] = [ 'kind' => 'visibility', 'detail' => str_replace( '_', '-', $visibility ) . ' users only' ];
        }
    }

    /**
     * Keys every node carries that have no Divi equivalent. Acknowledged so
     * they never surface as "skipped"; the ones a user would miss are noted.
     */
    private function acknowledgeCommonKeys( string $kind, array $settings, array &$handled, array &$notes ): void {
        $handled = array_merge( $handled, [
            'type', 'container_element', 'node_label', 'export', 'import', 'responsive_order', 'equal_height',
            'content_alignment', 'width', 'content_width', 'max_content_width', 'max_content_width_unit',
            'top_edge_transform', 'bottom_edge_transform', 'top_shape', 'bottom_shape', 'top_shape_color',
            'bottom_shape_color', 'top_shape_size', 'bottom_shape_size', 'top_shape_flip', 'bottom_shape_flip',
            'bg_overlay_gradient_medium', 'connections', 'data', 'size', 'size_medium', 'size_responsive', 'size_large',
            'animation', 'animation_delay', 'animation_duration',
        ] );

        $animation = $settings['animation'] ?? '';
        if ( is_array( $animation ) ) {
            $animation = $animation['style'] ?? '';
        }
        if ( is_string( $animation ) && $animation !== '' && $animation !== 'none' ) {
            $notes[] = [ 'kind' => 'animation', 'detail' => $animation ];
        }

        foreach ( [ 'top_shape', 'bottom_shape' ] as $shape ) {
            if ( is_string( $settings[ $shape ] ?? '' ) && ( $settings[ $shape ] ?? '' ) !== '' && ( $settings[ $shape ] ?? '' ) !== 'none' ) {
                $notes[] = [ 'kind' => 'shapes', 'detail' => $shape . '=' . $settings[ $shape ] ];
            }
        }
    }

    private function firstString( array $settings, array $keys ): string {
        foreach ( $keys as $key ) {
            $v = $settings[ $key ] ?? '';
            if ( is_string( $v ) && trim( $v ) !== '' ) {
                return trim( $v );
            }
        }
        return '';
    }

    private function isUnresolvedGlobal( mixed $raw ): bool {
        return is_string( $raw ) && Color::isGlobalRef( trim( $raw ) ) && Color::normalize( $raw ) === null;
    }
}
