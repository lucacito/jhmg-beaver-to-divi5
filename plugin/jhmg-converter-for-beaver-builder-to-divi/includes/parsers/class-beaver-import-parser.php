<?php
/**
 * Turns an uploaded file into conversion items.
 *
 * Accepted formats:
 *  - WordPress export XML (WXR) — every post carrying a `_fl_builder_data` meta.
 *  - A Beaver Builder layout template pack (.dat): serialized {layout: [{nodes, settings}]}.
 *  - JSON: {"nodes": {...}} or {"title": ..., "nodes": {...}} (this plugin's fixture format).
 */

namespace BeaverDivi5Converter\Parsers;

use BeaverDivi5Converter\Helpers\Arr;
use BeaverDivi5Converter\Conversion\InstalledPostSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BeaverImportParser {

    const MAX_ITEMS = 500;

    private BeaverDocumentParser $documents;
    private WxrReader $wxr;

    public function __construct( ?BeaverDocumentParser $documents = null, ?WxrReader $wxr = null ) {
        $this->documents = $documents ?? new BeaverDocumentParser();
        $this->wxr       = $wxr ?? new WxrReader();
    }

    /**
     * @return array[] Items shaped ['title','post_type','post_name','template_type','nodes','settings','error','source_ref'].
     * @throws \RuntimeException On unreadable or unrecognised input.
     */
    public function parse( string $file_path, string $file_name = '' ): array {
        if ( ! is_readable( $file_path ) ) {
            throw new \RuntimeException( 'Import file is not readable.' );
        }

        $raw = (string) file_get_contents( $file_path );
        if ( trim( $raw ) === '' ) {
            throw new \RuntimeException( 'Import file is empty.' );
        }

        $items = $this->parseString( $raw, $file_name );

        if ( empty( $items ) ) {
            throw new \RuntimeException( 'No Beaver Builder layouts found in the file. Export the page from Tools → Export (with Beaver Builder installed), or upload a Beaver Builder template .dat file.' );
        }

        return array_slice( $items, 0, self::MAX_ITEMS );
    }

    /** @return array[] */
    public function parseString( string $raw, string $file_name = '' ): array {
        $trimmed = ltrim( $raw );

        if ( str_starts_with( $trimmed, '<?xml' ) || str_starts_with( $trimmed, '<rss' ) ) {
            return $this->fromWxr( $raw, $file_name );
        }

        if ( preg_match( '/^a:\d+:\{/', $trimmed ) ) {
            return $this->fromTemplatePack( $raw, $file_name );
        }

        if ( str_starts_with( $trimmed, '{' ) || str_starts_with( $trimmed, '[' ) ) {
            return $this->fromJson( $raw, $file_name );
        }

        throw new \RuntimeException( 'Unrecognised file type. Upload a WordPress export (.xml), a Beaver Builder template (.dat) or a layout JSON file.' );
    }

    private function fromWxr( string $xml, string $file_name ): array {
        $items = [];
        foreach ( $this->wxr->read( $xml ) as $post ) {
            $nodes = $this->documents->parseValue( $post['meta']['_fl_builder_data'] ?? null );
            if ( empty( $nodes ) ) {
                continue;
            }
            $settings = $this->layoutSettings( $post['meta']['_fl_builder_data_settings'] ?? null );

            $template_type = '';
            $post_type     = (string) $post['post_type'];
            if ( $post_type === InstalledPostSource::THEMER_POST_TYPE ) {
                $type          = (string) ( $post['meta']['_fl_theme_layout_type'] ?? '' );
                $template_type = in_array( $type, [ 'header', 'footer' ], true ) ? $type : '';
            }

            $items[] = $this->item( $post['title'], $post_type, $post['post_name'], $template_type, $nodes, $settings, $file_name );

            if ( count( $items ) >= self::MAX_ITEMS ) {
                break;
            }
        }
        return $items;
    }

    private function fromTemplatePack( string $raw, string $file_name ): array {
        if ( preg_match( '/O:\d+:"(?!stdClass")/', $raw ) ) {
            throw new \RuntimeException( 'The template file contains serialized objects that are not layout data. Refusing to read it.' );
        }
        $data = @unserialize( $raw, [ 'allowed_classes' => [ 'stdClass' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        if ( ! is_array( $data ) ) {
            throw new \RuntimeException( 'The template file could not be read.' );
        }

        $items = [];
        foreach ( $data as $type => $templates ) {
            if ( ! is_array( $templates ) ) {
                continue;
            }
            foreach ( $templates as $index => $template ) {
                $template = json_decode( (string) json_encode( $template ), true );
                if ( ! is_array( $template ) ) {
                    continue;
                }
                $nodes = $this->documents->parseValue( $template['nodes'] ?? [] );
                if ( empty( $nodes ) ) {
                    continue;
                }
                $title   = (string) ( $template['name'] ?? ( $this->titleFromFileName( $file_name ) . ' ' . ( $index + 1 ) ) );
                $items[] = $this->item( $title, 'page', '', '', $nodes, $this->layoutSettings( $template['settings'] ?? null ), $file_name );
            }
        }
        return $items;
    }

    private function fromJson( string $raw, string $file_name ): array {
        $decoded = json_decode( $raw, true );
        if ( ! is_array( $decoded ) ) {
            throw new \RuntimeException( 'Import file is not valid JSON: ' . esc_html( json_last_error_msg() ) );
        }

        // A list of documents, or one document.
        $documents = Arr::isList( $decoded ) && isset( $decoded[0]['nodes'] ) ? $decoded : [ $decoded ];

        $items = [];
        foreach ( $documents as $index => $document ) {
            if ( ! is_array( $document ) ) {
                continue;
            }
            $nodes = $this->documents->parseValue( $document );
            if ( empty( $nodes ) ) {
                continue;
            }
            $title   = (string) ( $document['title'] ?? $document['name'] ?? $this->titleFromFileName( $file_name ) );
            $items[] = $this->item( $title, (string) ( $document['post_type'] ?? 'page' ), (string) ( $document['post_name'] ?? '' ), (string) ( $document['template_type'] ?? '' ), $nodes, $this->layoutSettings( $document['settings'] ?? null ), $file_name );
        }
        return $items;
    }

    private function layoutSettings( mixed $raw ): array {
        if ( is_string( $raw ) && preg_match( '/^[aO]:\d+:/', trim( $raw ) ) ) {
            if ( preg_match( '/O:\d+:"(?!stdClass")/', $raw ) ) {
                return [];
            }
            $raw = @unserialize( trim( $raw ), [ 'allowed_classes' => [ 'stdClass' ] ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        }
        $raw = json_decode( (string) json_encode( $raw ), true );
        return is_array( $raw ) ? array_intersect_key( $raw, [ 'css' => 1, 'js' => 1 ] ) : [];
    }

    private function item( string $title, string $post_type, string $post_name, string $template_type, array $nodes, array $settings, string $file_name ): array {
        return [
            'title'         => trim( $title ) !== '' ? trim( $title ) : 'Imported Page',
            'post_type'     => in_array( $post_type, [ 'page', 'post' ], true ) ? $post_type : 'page',
            'post_name'     => $post_name,
            'template_type' => in_array( $template_type, [ 'header', 'footer' ], true ) ? $template_type : '',
            'nodes'         => $nodes,
            'settings'      => $settings,
            'error'         => '',
            'source_ref'    => [ 'kind' => 'upload', 'post_id' => null, 'file' => basename( $file_name ) ],
        ];
    }

    private function titleFromFileName( string $file_name ): string {
        $base = pathinfo( $file_name, PATHINFO_FILENAME );
        return $base === '' ? 'Imported Page' : ucwords( str_replace( [ '-', '_' ], ' ', $base ) );
    }
}
