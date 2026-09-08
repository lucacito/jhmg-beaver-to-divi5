<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Widget module (Lite source: modules/widget). The settings hold
 * the widget's PHP class in `widget` (or `widget_class`) and the widget's own
 * form values under `widget-<id_base>`; Beaver Builder renders it with
 * `the_widget()`.
 *
 * Divi 5 has a Sidebar module for widget areas but nothing for a single
 * widget, so the widget is rendered the same way at conversion time and its
 * HTML goes into a divi/code module (a static copy, which the report says).
 * When the widget class is not available the widget is named in a comment.
 */
class WidgetConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_widget_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $class = urldecode( $this->firstText( $settings, [ 'widget', 'widget_class' ] ) );
        $title = $this->text( $settings, 'widget_title' ) ?: $this->widgetFormTitle( $settings ) ?: $this->text( $settings, 'title' ) ?: $class;

        $html = $class !== '' ? $this->render( $class, $settings ) : null;
        if ( $html === null ) {
            $label = $title !== '' ? $title : 'widget';
            $this->engine->logWarning( "Widget {$id}: '{$label}' could not be rendered here (widget class " . ( $class !== '' ? "'{$class}' " : '' ) . 'not available); a labelled placeholder marks its place.' );
            $this->engine->logNotCarriedOver( 'integration', $id, 'widget ' . $label );
            $html = '<!-- beaver builder module: widget ' . esc_html( $class ) . ' -->'
                . '<div class="bdc-unconverted-module" data-beaver-module="widget">' . esc_html( $label ) . '</div>';
        } else {
            $this->engine->logNotCarriedOver( 'integration', $id, "widget '{$title}' copied as static HTML; it no longer updates with the widget's data" );
        }

        $block = $this->codeBlock( $id, $html );
        $block['settings'] = $this->deepMergeSettings( $block['settings'], [ 'module' => $style['divi_attrs']['module'] ?? [] ] );

        $consumed = [ 'widget', 'widget_class', 'widget_title', 'widget_key', 'title' ];
        foreach ( array_keys( $settings ) as $key ) {
            if ( is_string( $key ) && str_starts_with( $key, 'widget-' ) ) {
                $consumed[] = $key;
            }
        }
        $this->engine->logConverted( 'code' );
        $this->logUnmappedSettings( $id, $settings, array_merge( $style['handled_keys'], $consumed ) );

        return $block;
    }

    /** The title the user typed into the widget's own form (`widget-<id_base>.title`), if any. */
    private function widgetFormTitle( array $settings ): string {
        foreach ( $settings as $key => $value ) {
            if ( ! is_string( $key ) || ! str_starts_with( $key, 'widget-' ) ) {
                continue;
            }
            $value = is_object( $value ) ? get_object_vars( $value ) : $value;
            if ( is_array( $value ) && is_string( $value['title'] ?? null ) && trim( $value['title'] ) !== '' ) {
                return trim( $value['title'] );
            }
        }
        return '';
    }

    /** the_widget() output, or null when WordPress or the widget class is missing. */
    private function render( string $class, array $settings ): ?string {
        if ( ! function_exists( 'the_widget' ) || ! class_exists( $class ) ) {
            return null;
        }
        $instance = new $class();
        $key      = 'widget-' . ( $instance->id_base ?? '' );
        $values   = $settings[ $key ] ?? [];
        $values   = is_object( $values ) ? get_object_vars( $values ) : ( is_array( $values ) ? $values : [] );

        ob_start();
        the_widget( $class, $values, [ 'widget_id' => 'bdc_widget_' . substr( md5( $class . wp_json_encode( $values ) ), 0, 8 ) ] );
        $html = trim( (string) ob_get_clean() );

        return $html !== '' ? $html : null;
    }
}
