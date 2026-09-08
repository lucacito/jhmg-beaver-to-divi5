<?php

namespace BeaverDivi5Converter\Converter;

use BeaverDivi5Converter\Converter\Registry\ConverterRegistry;
use BeaverDivi5Converter\Parsers\BeaverDocumentParser;
use BeaverDivi5Converter\Parsers\NodeTree;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Walks a Beaver Builder node tree and dispatches every node to the handler
 * the registry knows for it, collecting the report as it goes.
 *
 * One engine per conversion: it accumulates counts and warnings across calls.
 */
class ConverterEngine {
    private ConverterRegistry $registry;
    private array $unsupported        = [];
    private array $counts             = [];
    private array $approximateCounts  = [];
    private array $approximateMatches = [];
    private array $warnings           = [];
    private array $skippedSettings    = [];
    private array $unresolvedGlobals  = [];
    private array $notCarriedOver     = [];
    /** @var array<string, string[]> add-on family label => node ids that carried it at its defaults */
    private array $addonDefaults      = [];
    private bool  $countingApproximate = false;
    /** @var array<int,array<string,string>> Colours rows and columns force on their descendants. */
    private array $inheritedColors = [];

    public function __construct() {
        $this->registry = new ConverterRegistry( $this );
    }

    public function registry(): ConverterRegistry {
        return $this->registry;
    }

    /**
     * @param array $document One of: ['nodes' => flat map, 'settings' => …],
     *   a flat node map, ['roots' => tree], or a list of tree nodes.
     * @return array{divi: array{elements: array}, unsupported: array, report: array}
     */
    public function convert( array $document ): array {
        [ $roots, $layout_settings ] = $this->treeFrom( $document );

        $this->recordLayoutSettings( $layout_settings );

        $elements = [];
        foreach ( $this->convertChildren( $roots ) as $block ) {
            $elements[] = $this->ensureSection( $block );
        }

        return [
            'divi'        => [ 'elements' => $elements ],
            'unsupported' => $this->unsupported,
            'report'      => $this->getReport(),
        ];
    }

    /** @return array{0: array, 1: array} [tree roots, layout settings] */
    private function treeFrom( array $document ): array {
        if ( isset( $document['roots'] ) && is_array( $document['roots'] ) ) {
            return [ $document['roots'], [] ];
        }

        // A list whose entries carry 'children' is already a tree.
        if ( array_is_list( $document ) && isset( $document[0]['children'] ) ) {
            return [ $document, [] ];
        }

        $settings = is_array( $document['settings'] ?? null ) ? $document['settings'] : [];
        $nodes    = ( new BeaverDocumentParser() )->parseValue( $document );
        $tree     = NodeTree::build( $nodes );

        foreach ( $tree['orphans'] as $orphan ) {
            $this->logWarning( "Node {$orphan} points at a parent that does not exist; it was placed at the top level." );
        }

        return [ $tree['roots'], $settings ];
    }

    /** Layout-level custom CSS/JS has no home in a Divi page; it is reported, never emitted. */
    private function recordLayoutSettings( array $settings ): void {
        foreach ( [ 'css' => 'custom CSS', 'js' => 'custom JavaScript' ] as $key => $label ) {
            $value = $settings[ $key ] ?? '';
            if ( is_string( $value ) && trim( $value ) !== '' ) {
                $this->logNotCarriedOver( 'custom_code', 'layout', $label . ' (' . strlen( trim( $value ) ) . ' characters)' );
            }
        }
    }

    /** Every root block must be a section; wrap whatever else surfaced there. */
    private function ensureSection( array $block ): array {
        $name = $block['name'] ?? '';
        if ( $name === 'divi/section' ) {
            return $block;
        }
        $id = (string) ( $block['id'] ?? uniqid( 'bdc' ) );

        if ( $name === 'divi/row' ) {
            return $this->section( $id, [ $block ] );
        }
        if ( $name === 'divi/column' ) {
            return $this->section( $id, [ [ 'id' => $id . '-row', 'name' => 'divi/row', 'settings' => BaseBeaverConverter::ROW_RESET, 'elements' => [ $block ] ] ] );
        }

        return $this->section( $id, [ [
            'id'       => $id . '-row',
            'name'     => 'divi/row',
            'settings' => BaseBeaverConverter::ROW_RESET,
            'elements' => [ [ 'id' => $id . '-col', 'name' => 'divi/column', 'settings' => [], 'elements' => [ $block ] ] ],
        ] ] );
    }

    private function section( string $id, array $rows ): array {
        return [ 'id' => $id . '-section', 'name' => 'divi/section', 'settings' => [], 'elements' => $rows ];
    }

    /** @return array<int,array> A flat list of blocks (handlers may return one block or several). */
    public function convertChildren( array $nodes ): array {
        $converted = [];
        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) ) {
                continue;
            }
            foreach ( self::asList( $this->convertNode( $node ) ) as $block ) {
                $converted[] = $block;
            }
        }
        return $converted;
    }

    public function convertNode( array $node ): array {
        $converter = $this->registry->getConverter( $node );

        if ( $converter instanceof ConverterInterface ) {
            if ( ! $this->registry->isApproximate( $node ) ) {
                return $converter->convert( $node );
            }

            $this->flagApproximate( (string) ( $node['id'] ?? '' ), (string) ( $node['settings']['type'] ?? $node['type'] ), $this->registry->converterName( $node ) );
            $this->countingApproximate = true;
            try {
                return $converter->convert( $node );
            } finally {
                $this->countingApproximate = false;
            }
        }

        $this->logUnsupported( $node );

        // Structural nodes have no meaningful placeholder; an orphan column is a
        // container, not content.
        if ( ( $node['type'] ?? '' ) !== 'module' ) {
            return [];
        }

        return $this->registry->defaultConverter( $node )->convert( $node );
    }

    /** A handler result normalised to a list of blocks. */
    public static function asList( array $result ): array {
        if ( empty( $result ) ) {
            return [];
        }
        if ( isset( $result['name'] ) ) {
            return [ $result ];
        }
        return array_values( array_filter( $result, static fn( $b ) => is_array( $b ) && ! empty( $b ) ) );
    }

    // -------------------------------------------------------------------------
    // Inherited colours
    // -------------------------------------------------------------------------

    /**
     * A Beaver Builder row or column can force a text, heading and link colour
     * on everything inside it (`.fl-row-content-wrap *`). Divi sections have no
     * such setting, so the converter pushes those colours here while converting
     * the container's children and modules pick them up when they set none.
     *
     * @param array<string,string> $colors Any of text_color, heading_color, link_color (normalised).
     */
    public function pushInheritedColors( array $colors ): void {
        $this->inheritedColors[] = array_filter( $colors, static fn( $c ) => is_string( $c ) && $c !== '' );
    }

    public function popInheritedColors(): void {
        array_pop( $this->inheritedColors );
    }

    /** The nearest enclosing value for a colour key, or null. */
    public function inheritedColor( string $key ): ?string {
        for ( $i = count( $this->inheritedColors ) - 1; $i >= 0; $i-- ) {
            if ( isset( $this->inheritedColors[ $i ][ $key ] ) ) {
                return $this->inheritedColors[ $i ][ $key ];
            }
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Reporting
    // -------------------------------------------------------------------------

    public function logConverted( string $type ): void {
        if ( $this->countingApproximate ) {
            $this->approximateCounts[ $type ] = ( $this->approximateCounts[ $type ] ?? 0 ) + 1;
            return;
        }
        $this->counts[ $type ] = ( $this->counts[ $type ] ?? 0 ) + 1;
    }

    public function flagApproximate( string $node_id, string $module, string $matched_to ): void {
        $this->approximateMatches[] = [ 'node_id' => $node_id, 'module' => $module, 'matched_to' => $matched_to ];
    }

    public function logWarning( string $message ): void {
        $this->warnings[] = $message;
    }

    public function logSkippedSetting( string $message ): void {
        $this->skippedSettings[] = $message;
    }

    /** An add-on settings family found at its defaults on a node (nothing to convert, nothing lost). */
    public function logAddonDefaults( string $label, string $node_id ): void {
        if ( ! in_array( $node_id, $this->addonDefaults[ $label ] ?? [], true ) ) {
            $this->addonDefaults[ $label ][] = $node_id;
        }
    }

    /** @param string $kind animation | visibility | shapes | background | lightbox | hover | custom_code | interaction */
    public function logNotCarriedOver( string $kind, string $node_id, string $detail ): void {
        $entry = [ 'kind' => $kind, 'node_id' => $node_id, 'detail' => $detail ];
        if ( ! in_array( $entry, $this->notCarriedOver, true ) ) {
            $this->notCarriedOver[] = $entry;
        }
    }

    public function logUnresolvedGlobal( string $node_id, string $setting_key, string $ref ): void {
        $entry = [ 'node_id' => $node_id, 'setting_key' => $setting_key, 'ref' => $ref ];
        if ( ! in_array( $entry, $this->unresolvedGlobals, true ) ) {
            $this->unresolvedGlobals[] = $entry;
        }
    }

    private function logUnsupported( array $node ): void {
        $this->unsupported[] = [
            'id'     => $node['id'] ?? null,
            'type'   => $node['type'] ?? null,
            'module' => ( $node['type'] ?? '' ) === 'module' ? ( $node['settings']['type'] ?? null ) : null,
        ];
    }

    public function getUnsupported(): array {
        return $this->unsupported;
    }

    public function getNotCarriedOver(): array {
        return $this->notCarriedOver;
    }

    public function getReport(): array {
        $converted   = array_sum( $this->counts );
        // One approximate module may emit several Divi blocks (a PowerPack heading
        // becomes prefix + heading + sub-title); coverage counts source modules.
        $approximate = count( $this->approximateMatches );
        $unsupported = count( $this->unsupported );
        $all         = $converted + $approximate + $unsupported;

        return [
            'converted'           => $this->counts,
            'approximate'         => $this->approximateCounts,
            'approximate_matches' => $this->approximateMatches,
            'warnings'            => $this->warnings,
            'skipped_settings'    => $this->skippedSettings,
            'unresolved_globals'  => $this->unresolvedGlobals,
            'not_carried_over'    => $this->notCarriedOver,
            'addon_settings_ignored' => array_map( 'count', $this->addonDefaults ),
            'quality'             => [
                'module_coverage' => $all > 0 ? (int) round( $converted / $all * 100 ) : 100,
                'settings_issues' => count( $this->skippedSettings ),
            ],
        ];
    }
}
