<?php
/**
 * Finds Beaver Themer header and footer layouts on this site.
 *
 * Themer stores each layout as an `fl-theme-layout` post whose type lives in
 * `_fl_theme_layout_type`; the layout data is the usual `_fl_builder_data`.
 */

namespace BeaverDivi5Converter\Pro\Admin;

use BeaverDivi5Converter\Conversion\InstalledPostSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThemerRepository {

    /** @var callable(array):array */
    private $query_runner;

    public function __construct( ?callable $query_runner = null ) {
        $this->query_runner = $query_runner ?? [ $this, 'run_wp_query' ];
    }

    public function query_args(): array {
        return [
            'post_type'      => InstalledPostSource::THEMER_POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'private' ],
            'meta_key'       => '_fl_builder_enabled', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'orderby'        => 'title',
            'order'          => 'ASC',
            'posts_per_page' => 100,
        ];
    }

    /** @return array[] Rows: ['id','title','layout_type','status'] for header and footer layouts only. */
    public function find(): array {
        $rows = [];
        foreach ( ( $this->query_runner )( $this->query_args() ) as $post ) {
            $id   = (int) ( $post->ID ?? 0 );
            $type = (string) get_post_meta( $id, InstalledPostSource::THEMER_TYPE_META, true );
            if ( $id <= 0 || ! in_array( $type, [ 'header', 'footer' ], true ) ) {
                continue;
            }
            $rows[] = [
                'id'          => $id,
                'title'       => trim( (string) ( $post->post_title ?? '' ) ) ?: __( '(no title)', 'jhmg-converter-for-beaver-builder-to-divi-pro' ),
                'layout_type' => $type,
                'status'      => (string) ( $post->post_status ?? '' ),
            ];
        }
        return $rows;
    }

    private function run_wp_query( array $args ): array {
        $query = new \WP_Query( $args );
        return $query->posts ?? [];
    }
}
