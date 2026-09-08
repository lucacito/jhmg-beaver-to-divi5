<?php
/**
 * The immutable result of a dry run. Building one writes nothing;
 * ConversionCommitter is the only thing that turns a plan into posts.
 */

namespace BeaverDivi5Converter\Conversion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ConversionPlan {

    /** @var array[] */
    private array $items;
    private int $limit;
    private bool $truncated;

    public function __construct( array $items, int $limit = 1, bool $truncated = false ) {
        $this->items     = array_values( $items );
        $this->limit     = $limit;
        $this->truncated = $truncated;
    }

    /** One plan item with every key present, so consumers never null-check a field a source did not set. */
    public static function item( array $fields ): array {
        $source_ref = $fields['source_ref'] ?? [];

        return [
            'title'         => (string) ( $fields['title'] ?? 'Imported Page' ),
            'post_type'     => (string) ( $fields['post_type'] ?? 'page' ),
            'post_name'     => (string) ( $fields['post_name'] ?? '' ),
            'template_type' => (string) ( $fields['template_type'] ?? '' ),
            'source_ref'    => [
                'kind'    => (string) ( $source_ref['kind'] ?? 'upload' ),
                'post_id' => isset( $source_ref['post_id'] ) ? (int) $source_ref['post_id'] : null,
                'file'    => isset( $source_ref['file'] ) ? (string) $source_ref['file'] : null,
            ],
            'blocks'        => $fields['blocks'] ?? [],
            'content'       => (string) ( $fields['content'] ?? '' ),
            'report'        => $fields['report'] ?? [],
            'unsupported'   => $fields['unsupported'] ?? [],
            'outline'       => $fields['outline'] ?? [],
            'error'         => (string) ( $fields['error'] ?? '' ),
        ];
    }

    /** @return array[] */
    public function items(): array {
        return $this->items;
    }

    public function limit(): int {
        return $this->limit;
    }

    public function truncated(): bool {
        return $this->truncated;
    }

    public function count(): int {
        return count( $this->items );
    }

    public function hasFailures(): bool {
        foreach ( $this->items as $item ) {
            if ( ( $item['error'] ?? '' ) !== '' ) {
                return true;
            }
        }
        return false;
    }

    public function toArray(): array {
        return [ 'items' => $this->items, 'limit' => $this->limit, 'truncated' => $this->truncated ];
    }
}
