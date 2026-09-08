<?php
/**
 * Durable record of recent conversion runs.
 *
 * Results otherwise live only in a one-hour transient. The coverage panel
 * needs unsupported module types across runs, and rollback needs the post IDs a
 * run created long after that hour is up, so both read from here.
 */

namespace BeaverDivi5Converter\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ImportHistory {

    const OPTION = 'bdc_import_history';

    /** Unbounded growth in wp_options is a common cause of slow-site reports. */
    const MAX_RUNS = 25;

    public function record( string $import_id, array $results ): void {
        $post_ids = [];
        foreach ( $results as $r ) {
            if ( ! empty( $r['success'] ) && ! empty( $r['post_id'] ) ) {
                $post_ids[] = (int) $r['post_id'];
            }
        }

        $real      = array_filter( $results, static fn( $r ) => empty( $r['skipped'] ) );
        $succeeded = count( array_filter( $real, static fn( $r ) => ! empty( $r['success'] ) ) );

        $runs = $this->all();
        array_unshift( $runs, [
            'id'          => $import_id,
            'at'          => gmdate( 'Y-m-d H:i:s' ),
            'post_ids'    => $post_ids,
            'unsupported' => self::module_types( $results ),
            'succeeded'   => $succeeded,
            'failed'      => count( $real ) - $succeeded,
            'rolled_back' => false,
        ] );

        update_option( self::OPTION, array_slice( $runs, 0, self::MAX_RUNS ) );
    }

    /** @return array Newest first. */
    public function all(): array {
        $runs = get_option( self::OPTION, [] );
        return is_array( $runs ) ? $runs : [];
    }

    public function find( string $import_id ): ?array {
        foreach ( $this->all() as $run ) {
            if ( ( $run['id'] ?? '' ) === $import_id ) {
                return $run;
            }
        }
        return null;
    }

    public function mark_rolled_back( string $import_id ): void {
        $runs = $this->all();
        foreach ( $runs as $i => $run ) {
            if ( ( $run['id'] ?? '' ) === $import_id ) {
                $runs[ $i ]['rolled_back'] = true;
            }
        }
        update_option( self::OPTION, $runs );
    }

    /**
     * Module types ranked by how many runs each appeared in.
     *
     * @return array<int, array{type:string, runs:int, last_seen:string}>
     */
    public function coverage(): array {
        $seen = [];
        foreach ( $this->all() as $run ) {
            foreach ( $run['unsupported'] ?? [] as $type ) {
                if ( ! isset( $seen[ $type ] ) ) {
                    $seen[ $type ] = [ 'type' => $type, 'runs' => 0, 'last_seen' => '' ];
                }
                $seen[ $type ]['runs']++;
                if ( ( $run['at'] ?? '' ) > $seen[ $type ]['last_seen'] ) {
                    $seen[ $type ]['last_seen'] = $run['at'] ?? '';
                }
            }
        }

        $coverage = array_values( $seen );
        usort( $coverage, static fn( $a, $b ) => $b['runs'] <=> $a['runs'] ?: strcmp( $a['type'], $b['type'] ) );

        return $coverage;
    }

    /** De-duplicated unsupported module slugs across a run's results, in first-seen order. */
    public static function module_types( array $results ): array {
        $types = [];
        foreach ( $results as $r ) {
            foreach ( $r['unsupported'] ?? [] as $entry ) {
                $type = $entry['module'] ?? $entry['type'] ?? null;
                if ( is_string( $type ) && $type !== '' && ! in_array( $type, $types, true ) ) {
                    $types[] = $type;
                }
            }
        }
        return $types;
    }
}
