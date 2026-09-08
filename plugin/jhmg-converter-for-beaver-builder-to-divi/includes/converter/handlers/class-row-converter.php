<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;
use BeaverDivi5Converter\Helpers\Size;
use BeaverDivi5Converter\StyleMapper\GlobalSettingsResolver;
use BeaverDivi5Converter\StyleMapper\StyleMapper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder row → divi/section.
 *
 * A Beaver Builder row paints its background, padding and minimum height on
 * `.fl-row-content-wrap`, the full-width wrapper — which is what a Divi
 * section is. Its content sits in `.fl-row-content`, capped at the row width
 * (1100px by default), which is what a Divi row is. Each column group inside
 * the row becomes one Divi row, so a row with two stacked groups becomes a
 * section holding two rows.
 */
class RowConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_section_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style         = $this->mapStyle( 'row', $node );
        $section_attrs = $style['divi_attrs'];

        // A side the row leaves blank gets Beaver Builder's global row padding,
        // exactly as Beaver Builder renders it; Divi's section default (54px) would not.
        $padding = $section_attrs['module']['decoration']['spacing']['desktop']['value']['padding'] ?? [];
        $global  = GlobalSettingsResolver::rowPadding();
        foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
            if ( ! isset( $padding[ $side ] ) || $padding[ $side ] === '' ) {
                $padding[ $side ] = $global[ $side ];
            }
        }
        $section_attrs['module']['decoration']['spacing']['desktop']['value']['padding'] = $padding + [ 'syncVertical' => 'off', 'syncHorizontal' => 'off' ];

        // Min-height and vertical alignment belong on the Divi row, the content
        // layer, not on the section: Divi sizes sections by their content.
        [ $section_attrs, $row_extra ] = $this->extractRowSizingLayout( $section_attrs );
        $row_extra                     = $this->deepMergeSettings( $row_extra, $this->verticalAlignment( $settings, $row_extra ) );
        $width_settings                = $this->rowWidthSettings( $settings );

        $groups = [];
        $loose  = [];
        foreach ( $node['children'] ?? [] as $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            if ( ( $child['type'] ?? '' ) === 'column-group' ) {
                $groups[] = $child;
            } else {
                $loose[] = $child;
            }
        }

        $rows = [];
        $this->engine->pushInheritedColors( $this->containerColors( $settings, $id ) );
        try {
        foreach ( $groups as $index => $group ) {
            $row = $this->engine->convertNode( $group );
            if ( empty( $row ) || ( $row['name'] ?? '' ) !== 'divi/row' ) {
                continue;
            }
            // Sizing (min-height) is a property of the whole Beaver Builder row;
            // applying it to every stacked group would multiply it.
            $extra           = $index === 0 ? $this->deepMergeSettings( $row_extra, $width_settings ) : $width_settings;
            $row['settings'] = $this->deepMergeSettings( $extra, $row['settings'] ?? [] );
            $rows[]          = $row;
        }

        if ( ! empty( $loose ) ) {
            $blocks = $this->convertStructureChildren( $loose );
            if ( ! empty( $blocks ) ) {
                $rows[] = $this->block(
                    $id . '-row',
                    'divi/row',
                    $this->deepMergeSettings( $width_settings, [ 'module' => [ 'advanced' => [ 'columnStructure' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ] ),
                    $this->ensureColumnChildren( $id, $blocks )
                );
            }
        }
        } finally {
            $this->engine->popInheritedColors();
        }

        if ( empty( $rows ) ) {
            $this->engine->logWarning( "Empty row after conversion: {$id}" );
            $rows[] = $this->block( $id . '-row', 'divi/row', $width_settings, [
                $this->block( $id . '-col', 'divi/column', [ 'module' => [ 'advanced' => [ 'type' => [ 'desktop' => [ 'value' => '4_4' ] ] ] ] ] ),
            ] );
        }

        $this->engine->logConverted( 'section' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'width', 'content_width', 'max_content_width', 'max_content_width_unit', 'max_content_width_medium', 'max_content_width_responsive', 'max_content_width_large', 'content_alignment', 'full_height', 'aspect_ratio' ],
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/section', $section_attrs, $rows );
    }

    /** @return array{0: array, 1: array} [section attrs without sizing/layout, the removed sizing/layout] */
    private function extractRowSizingLayout( array $section_attrs ): array {
        $row_extra = [];
        foreach ( [ 'sizing', 'layout' ] as $key ) {
            if ( isset( $section_attrs['module']['decoration'][ $key ] ) ) {
                $row_extra['module']['decoration'][ $key ] = $section_attrs['module']['decoration'][ $key ];
                unset( $section_attrs['module']['decoration'][ $key ] );
            }
        }
        if ( isset( $section_attrs['module']['decoration'] ) && empty( $section_attrs['module']['decoration'] ) ) {
            unset( $section_attrs['module']['decoration'] );
        }
        if ( isset( $section_attrs['module'] ) && empty( $section_attrs['module'] ) ) {
            unset( $section_attrs['module'] );
        }
        return [ $section_attrs, $row_extra ];
    }

    /** `content_alignment` only means something once the row has a height to align within. */
    private function verticalAlignment( array $settings, array $row_extra ): array {
        if ( empty( $row_extra['module']['decoration']['sizing'] ) ) {
            return [];
        }
        $map   = [ 'top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end' ];
        $align = $settings['content_alignment'] ?? 'center';
        if ( ! is_string( $align ) || ! isset( $map[ $align ] ) ) {
            return [];
        }
        return [ 'module' => [ 'decoration' => [ 'layout' => [ 'desktop' => [ 'value' => [ 'alignItems' => $map[ $align ] ] ] ] ] ] ];
    }

    /**
     * Row width: `width` (fixed|full) and `content_width` (fixed|full) fall back
     * to the site's Beaver Builder global settings. A fixed content area gets
     * the row's own `max_content_width` or the global row width.
     */
    private function rowWidthSettings( array $settings ): array {
        $width         = $this->text( $settings, 'width' ) ?: GlobalSettingsResolver::rowWidthDefault();
        $content_width = $this->text( $settings, 'content_width' ) ?: GlobalSettingsResolver::rowContentWidthDefault();

        if ( $width === 'full' && $content_width === 'full' ) {
            return [ 'module' => [ 'decoration' => [ 'sizing' => [ 'desktop' => [ 'value' => [ 'width' => '100%', 'maxWidth' => '100%' ] ] ] ] ] ];
        }

        $sizing = [];
        foreach ( StyleMapper::BREAKPOINTS as $suffix => $bp ) {
            $max = Size::fromSettings( $settings, 'max_content_width', 'max_content_width', $suffix );
            if ( $max === '' && $suffix === '' ) {
                $max = GlobalSettingsResolver::rowWidth();
            }
            if ( $max !== '' ) {
                $sizing[ $bp ] = [ 'value' => [ 'maxWidth' => $max ] ];
            }
        }

        return empty( $sizing ) ? [] : [ 'module' => [ 'decoration' => [ 'sizing' => $sizing ] ] ];
    }
}
