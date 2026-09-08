<?php

namespace BeaverDivi5Converter\Pro\Exporters;

use BeaverDivi5Converter\Exporters\DiviExporter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles saving a converted Divi header into the Divi Theme Builder structure.
 *
 * Divi Theme Builder stores headers across three post types:
 *
 *   et_theme_builder  — Container post that lists template IDs via _et_template meta.
 *   et_template       — One entry per "rule set"; links header/body/footer layout IDs
 *                       to display conditions (use_on / exclude_from).
 *   et_header_layout  — The actual header content post (Divi 5 block content).
 *
 * To install a global header:
 *   1. Create (or update) an et_header_layout post with the converted content.
 *   2. Create an et_template post marked as default (no condition needed).
 *   3. Get or create the live et_theme_builder post and append the template ID.
 */
class DiviThemeBuilderExporter {

    /** Records which import a layout or template came from, so a re-import updates it. */
    const SOURCE_META = '_bdc_tb_source';

    private DiviExporter $exporter;

    public function __construct( ?DiviExporter $exporter = null ) {
        $this->exporter = $exporter ?? new DiviExporter();
    }

    /**
     * Create an et_header_layout post, save the converted Divi content to it,
     * then wire it up as the global site header in the Divi Theme Builder.
     *
     * Both the layout post and the template post are always created as 'publish'.
     * Divi's own et_theme_builder_insert_layout() hardcodes this — several internal
     * checks gate on publish status, so a draft layout post will not appear.
     *
     * @param  string $title      Header title shown in Theme Builder admin.
     * @param  array  $divi_data  Result from ConverterEngine::convert().
     * @return array{post_id: int, template_id: int, theme_builder_id: int, success: bool, error: string}
     */
    public function saveHeader( string $title, array $divi_data, array $source_ref = [] ): array {
        try {
            $source_key     = $this->sourceKey( 'header', $title, $source_ref );
            $header_post_id = $this->upsertLayoutPost( 'et_header_layout', $title, $source_key, 'Imported Header' );

            if ( $header_post_id === 0 ) {
                return $this->errorResult( 'Could not create et_header_layout post.' );
            }

            $this->exporter->save( $header_post_id, $divi_data );

            [ $template_id, $theme_builder_id ] = $this->wireGlobalHeader( $title, $header_post_id, $source_key );

            return [
                'post_id'          => $header_post_id,
                'template_id'      => $template_id,
                'theme_builder_id' => $theme_builder_id,
                'success'          => true,
                'error'            => '',
            ];
        } catch ( \Throwable $e ) {
            return $this->errorResult( $e->getMessage() );
        }
    }

    /**
     * Create an et_footer_layout post, save the converted Divi content to it,
     * then wire it up as the global site footer in the Divi Theme Builder.
     *
     * @param  string $title      Footer title shown in Theme Builder admin.
     * @param  array  $divi_data  Result from ConverterEngine::convert().
     * @return array{post_id: int, template_id: int, theme_builder_id: int, success: bool, error: string}
     */
    public function saveFooter( string $title, array $divi_data, array $source_ref = [] ): array {
        try {
            $source_key     = $this->sourceKey( 'footer', $title, $source_ref );
            $footer_post_id = $this->upsertLayoutPost( 'et_footer_layout', $title, $source_key, 'Imported Footer' );

            if ( $footer_post_id === 0 ) {
                return $this->errorResult( 'Could not create et_footer_layout post.' );
            }

            $this->exporter->save( $footer_post_id, $divi_data );

            [ $template_id, $theme_builder_id ] = $this->wireGlobalFooter( $title, $footer_post_id, $source_key );

            return [
                'post_id'          => $footer_post_id,
                'template_id'      => $template_id,
                'theme_builder_id' => $theme_builder_id,
                'success'          => true,
                'error'            => '',
            ];
        } catch ( \Throwable $e ) {
            return $this->errorResult( $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------


    /**
     * Create an et_template post that marks this header as the global default,
     * then attach it to the live et_theme_builder container post.
     *
     * @return int[] [ $template_id, $theme_builder_id ]
     */
    private function wireGlobalHeader( string $title, int $header_layout_id, string $source_key ): array {
        $template_id = $this->upsertTemplatePost( $title, $source_key, 'header', $header_layout_id );

        $theme_builder_id = $this->getOrCreateThemeBuilderPost();

        if ( $theme_builder_id > 0 && $template_id > 0 ) {
            $this->attachTemplate( $theme_builder_id, $template_id );
        }

        return [ $template_id, $theme_builder_id ];
    }


    /**
     * Returns the ID of the live (published) et_theme_builder post.
     * Creates one if none exists yet.
     */
    private function getOrCreateThemeBuilderPost(): int {
        // Use Divi's own function when available to avoid conflicts with its internal state.
        if ( function_exists( 'et_theme_builder_get_theme_builder_post_id' ) ) {
            $id = (int) \et_theme_builder_get_theme_builder_post_id( true, true );
            if ( $id > 0 ) {
                return $id;
            }
        }

        // Manual fallback: query for an existing published et_theme_builder post.
        $query = new \WP_Query( [
            'post_type'              => 'et_theme_builder',
            'post_status'            => 'publish',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query'             => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'key'     => '_et_library_theme_builder',
                'compare' => 'NOT EXISTS',
            ] ],
        ] );

        if ( ! empty( $query->posts ) ) {
            return (int) $query->posts[0];
        }

        // Create a fresh one.
        $new_id = wp_insert_post( [
            'post_type'   => 'et_theme_builder',
            'post_title'  => 'Theme Builder',
            'post_status' => 'publish',
        ] );

        return ( is_wp_error( $new_id ) || (int) $new_id === 0 ) ? 0 : (int) $new_id;
    }


    /**
     * A stable identity for "the same header or footer, imported again".
     *
     * Without one, every re-import was a fresh wp_insert_post(): the e2e
     * container ended up with five published copies of one 9018-byte footer and
     * three copies of one header, and three separate et_template posts each
     * marked _et_default. Divi then picks among the defaults, so from the
     * customer's side the site's global header starts changing for no visible
     * reason — and re-importing to "fix" it makes it worse.
     *
     * A conversion from an installed page keys on that page's ID. An uploaded
     * file has nothing that stable, so it keys on the title, which is precisely
     * the case a re-import of the same file reproduces.
     */
    private function sourceKey( string $type, string $title, array $source_ref ): string {
        $post_id = isset( $source_ref['post_id'] ) ? (int) $source_ref['post_id'] : 0;

        if ( ( $source_ref['kind'] ?? '' ) === 'installed' && $post_id > 0 ) {
            return $type . ':post-' . $post_id;
        }

        $slug = function_exists( 'sanitize_title' ) ? sanitize_title( $title ) : strtolower( trim( $title ) );
        $slug = $slug !== '' ? $slug : 'untitled';

        return $type . ':title-' . $slug;
    }

    /** The post of this type previously created for this source, or 0. */
    private function findBySourceKey( string $post_type, string $source_key ): int {
        $found = get_posts( [
            'post_type'              => $post_type,
            'post_status'            => 'any',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key'               => self::SOURCE_META,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'meta_value'             => $source_key,
        ] );

        return ! empty( $found ) ? (int) $found[0] : 0;
    }

    /**
     * The layout post holding the converted content — reused when this source
     * has been imported before, so a re-import replaces its own previous result
     * instead of stacking another published layout beside it.
     */
    private function upsertLayoutPost( string $post_type, string $title, string $source_key, string $fallback_title ): int {
        $existing = $this->findBySourceKey( $post_type, $source_key );

        if ( $existing > 0 ) {
            wp_update_post( [
                'ID'          => $existing,
                'post_title'  => $title ?: $fallback_title,
                // Divi gates several internal checks on publish status, so a
                // layout that somehow went to draft is brought back.
                'post_status' => 'publish',
            ] );

            return $existing;
        }

        $post_id = wp_insert_post( [
            'post_type'    => $post_type,
            'post_title'   => $title ?: $fallback_title,
            'post_status'  => 'publish',
            'post_content' => '',
        ] );

        if ( is_wp_error( $post_id ) || (int) $post_id === 0 ) {
            return 0;
        }

        $post_id = (int) $post_id;

        // Divi's et_theme_builder_insert_layout() does both of these after
        // wp_insert_post: the layout_type term is what makes the Theme Builder
        // UI treat this post as a layout entry.
        if ( function_exists( 'wp_set_object_terms' ) ) {
            wp_set_object_terms( $post_id, 'layout', 'layout_type', true );
        }
        update_post_meta( $post_id, '_et_pb_show_page_creation', 'on' );
        update_post_meta( $post_id, self::SOURCE_META, $source_key );

        return $post_id;
    }

    /**
     * The et_template rule-set post, reused the same way. Creating a second one
     * marked _et_default is what made Divi's choice of global header look
     * arbitrary.
     *
     * @param string $slot 'header' or 'footer'.
     */
    private function upsertTemplatePost( string $title, string $source_key, string $slot, int $layout_id ): int {
        $existing = $this->findBySourceKey( 'et_template', $source_key );

        if ( $existing > 0 ) {
            $template_id = $existing;
            wp_update_post( [
                'ID'          => $template_id,
                'post_title'  => $title ?: ( $slot === 'header' ? 'Global Header' : 'Global Footer' ),
                'post_status' => 'publish',
            ] );
        } else {
            $template_id = wp_insert_post( [
                'post_type'   => 'et_template',
                'post_title'  => $title ?: ( $slot === 'header' ? 'Global Header' : 'Global Footer' ),
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $template_id ) || (int) $template_id === 0 ) {
                return 0;
            }

            $template_id = (int) $template_id;
            update_post_meta( $template_id, self::SOURCE_META, $source_key );
        }

        update_post_meta( $template_id, '_et_default', '1' );
        update_post_meta( $template_id, '_et_enabled', '1' );
        update_post_meta( $template_id, "_et_{$slot}_layout_id", $layout_id );
        update_post_meta( $template_id, "_et_{$slot}_layout_enabled", '1' );

        return $template_id;
    }

    /**
     * Attaches a template to the Theme Builder container, once.
     *
     * _et_template is legitimately a repeated meta key — a container holds one
     * entry per rule set — so this cannot be update_post_meta(), which would
     * collapse every other template on the site into this one. Adding only when
     * absent stops the duplication without touching anyone else's rule sets.
     */
    private function attachTemplate( int $theme_builder_id, int $template_id ): void {
        $attached = get_post_meta( $theme_builder_id, '_et_template', false );
        $attached = is_array( $attached ) ? array_map( 'intval', $attached ) : [];

        if ( in_array( $template_id, $attached, true ) ) {
            return;
        }

        add_post_meta( $theme_builder_id, '_et_template', $template_id );
    }

    /**
     * @return int[] [ $template_id, $theme_builder_id ]
     */
    private function wireGlobalFooter( string $title, int $footer_layout_id, string $source_key ): array {
        $template_id = $this->upsertTemplatePost( $title, $source_key, 'footer', $footer_layout_id );

        $theme_builder_id = $this->getOrCreateThemeBuilderPost();

        if ( $theme_builder_id > 0 && $template_id > 0 ) {
            $this->attachTemplate( $theme_builder_id, $template_id );
        }

        return [ $template_id, $theme_builder_id ];
    }


    private function errorResult( string $error ): array {
        return [
            'post_id'          => 0,
            'template_id'      => 0,
            'theme_builder_id' => 0,
            'success'          => false,
            'error'            => $error,
        ];
    }
}
