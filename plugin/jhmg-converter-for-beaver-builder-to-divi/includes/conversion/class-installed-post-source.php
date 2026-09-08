<?php
/**
 * Conversion input read directly from posts on this site.
 *
 * Strictly read-only. The source post is never modified — a conversion
 * always creates a new post, so a failed or unwanted conversion can never
 * cost the user their Beaver Builder original.
 */

namespace BeaverDivi5Converter\Conversion;

use BeaverDivi5Converter\Parsers\BeaverDocumentParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InstalledPostSource implements ConversionSource {

    /** Beaver Themer layouts; only header and footer have a Divi Theme Builder slot. */
    const THEMER_POST_TYPE = 'fl-theme-layout';
    const THEMER_TYPE_META = '_fl_theme_layout_type';

    /** @var int[] */
    private array $postIds;
    private BeaverDocumentParser $parser;

    public function __construct( array $post_ids, ?BeaverDocumentParser $parser = null ) {
        $this->postIds = array_values( array_filter( array_map( 'intval', $post_ids ) ) );
        $this->parser  = $parser ?? new BeaverDocumentParser();
    }

    public function items(): array {
        $items = [];
        foreach ( $this->postIds as $post_id ) {
            $items[] = $this->itemFor( $post_id );
        }
        return $items;
    }

    private function itemFor( int $post_id ): array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return $this->failed( $post_id, __( 'That page no longer exists.', 'jhmg-converter-for-beaver-builder-to-divi' ) );
        }

        $meta     = get_post_meta( $post_id );
        $document = $this->parser->parse( is_array( $meta ) ? $meta : [] );

        if ( empty( $document['nodes'] ) ) {
            $has_draft = get_post_meta( $post_id, '_fl_builder_draft', true ) !== '';
            return $this->failed(
                $post_id,
                $has_draft
                    ? __( 'This page has unpublished Beaver Builder changes only. Publish it in Beaver Builder first, then convert it.', 'jhmg-converter-for-beaver-builder-to-divi' )
                    : __( 'No published Beaver Builder layout found on that page.', 'jhmg-converter-for-beaver-builder-to-divi' ),
                (string) ( $post->post_title ?? '' )
            );
        }

        $post_type     = (string) ( $post->post_type ?? 'page' );
        $template_type = '';

        if ( $post_type === self::THEMER_POST_TYPE ) {
            $template_type = $this->templateType( $post_id );
            $post_type     = 'page';
        } elseif ( $post_type === 'fl-builder-template' ) {
            $post_type = 'page';
        } elseif ( $post_type !== 'page' ) {
            $post_type = 'post';
        }

        return [
            'title'         => (string) ( $post->post_title ?? '' ) ?: __( 'Imported Page', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'post_type'     => $post_type,
            'post_name'     => (string) ( $post->post_name ?? '' ),
            'template_type' => $template_type,
            'nodes'         => $document['nodes'],
            'settings'      => $document['settings'],
            'error'         => '',
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }

    private function templateType( int $post_id ): string {
        $type = (string) get_post_meta( $post_id, self::THEMER_TYPE_META, true );
        return in_array( $type, [ 'header', 'footer' ], true ) ? $type : '';
    }

    private function failed( int $post_id, string $error, string $title = '' ): array {
        return [
            'title'         => $title !== '' ? $title : __( 'Unknown page', 'jhmg-converter-for-beaver-builder-to-divi' ),
            'post_type'     => 'page',
            'post_name'     => '',
            'template_type' => '',
            'nodes'         => [],
            'settings'      => [],
            'error'         => $error,
            'source_ref'    => [ 'kind' => 'installed', 'post_id' => $post_id, 'file' => null ],
        ];
    }
}
