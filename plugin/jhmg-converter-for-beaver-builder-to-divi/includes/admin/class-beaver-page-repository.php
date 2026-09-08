<?php
/**
 * Finds the Beaver Builder-built posts the direct-conversion picker lists.
 *
 * The query runs through an injectable runner so the class stays unit-testable
 * without a WordPress database.
 */

namespace BeaverDivi5Converter\Admin;

use BeaverDivi5Converter\Conversion\InstalledPostSource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BeaverPageRepository {

    /** Beaver Builder's own marker for a post built with its editor. */
    const ENABLED_META = '_fl_builder_enabled';

    /** Beaver Builder's list of post types it is allowed on (page by default). */
    const POST_TYPES_OPTION = '_fl_builder_post_types';

    const PER_PAGE = 20;

    /** @var callable(array):array */
    private $query_runner;

    public function __construct( ?callable $query_runner = null ) {
        $this->query_runner = $query_runner ?? [ $this, 'run_wp_query' ];
    }

    /**
     * The post types a Beaver Builder layout can live on: whatever the site
     * enabled in Beaver Builder's settings, plus the builder's own template and
     * Themer layout types, plus page and post.
     *
     * @return string[]
     */
    public static function post_types(): array {
        $enabled = get_option( self::POST_TYPES_OPTION, [] );
        $enabled = is_array( $enabled ) ? array_values( array_filter( array_map( 'strval', $enabled ) ) ) : [];

        return array_values( array_unique( array_merge( [ 'page', 'post' ], $enabled, [ 'fl-builder-template', InstalledPostSource::THEMER_POST_TYPE ] ) ) );
    }

    /** @return array The WP_Query arguments this repository issues. */
    public function query_args( array $args = [] ): array {
        $per_page = (int) ( $args['per_page'] ?? self::PER_PAGE );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );
        $search   = trim( (string) ( $args['search'] ?? '' ) );

        $query = [
            'post_type'      => self::post_types(),
            'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'meta_key'       => self::ENABLED_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => $per_page > 0 ? $per_page : self::PER_PAGE,
            'paged'          => $paged,
        ];

        // An explicit offset drives WP_Query's SQL OFFSET directly, so a caller
        // probing for one extra row can raise posts_per_page without inflating
        // the offset every later page is computed from.
        if ( isset( $args['offset'] ) ) {
            $query['offset'] = max( 0, (int) $args['offset'] );
        }
        if ( $search !== '' ) {
            $query['s'] = $search;
        }

        return $query;
    }

    /** @return array[] Rows: ['id','title','post_type','status','modified','converted']. */
    public function find( array $args = [] ): array {
        $rows = [];
        foreach ( ( $this->query_runner )( $this->query_args( $args ) ) as $post ) {
            $id = (int) ( $post->ID ?? 0 );
            if ( $id <= 0 ) {
                continue;
            }
            $title  = trim( (string) ( $post->post_title ?? '' ) );
            $rows[] = [
                'id'        => $id,
                'title'     => $title !== '' ? $title : __( '(no title)', 'jhmg-converter-for-beaver-builder-to-divi' ),
                'post_type' => (string) ( $post->post_type ?? '' ),
                'status'    => (string) ( $post->post_status ?? '' ),
                'modified'  => (string) ( $post->post_modified ?? '' ),
                'converted' => $this->already_converted( $id ),
            ];
        }
        return $rows;
    }

    public function has_any(): bool {
        return ! empty( ( $this->query_runner )( $this->query_args( [ 'per_page' => 1 ] ) ) );
    }

    /** True when some post records this one as its conversion source. Informational only. */
    private function already_converted( int $source_post_id ): bool {
        $found = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'meta_key'       => '_bbdc_source_post_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'     => $source_post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );
        return ! empty( $found );
    }

    /** @return array Post objects. */
    private function run_wp_query( array $args ): array {
        $query = new \WP_Query( $args );
        return $query->posts ?? [];
    }
}
