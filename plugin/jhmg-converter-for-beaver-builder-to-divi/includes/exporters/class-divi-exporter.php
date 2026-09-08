<?php

namespace BeaverDivi5Converter\Exporters;

use BeaverDivi5Converter\Helpers\DiviRequirement;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Writes a converted layout onto a post: block content plus the meta Divi 5
 * reads to recognise the page as its own.
 */
class DiviExporter {
    private DiviBlockSerializer $serializer;

    public function __construct( ?DiviBlockSerializer $serializer = null ) {
        $this->serializer = $serializer ?? new DiviBlockSerializer();
    }

    /** @return array<string,string> Post meta key ⇒ value. */
    public function export( array $divi_data ): array {
        $meta = [];

        $meta['_et_pb_use_builder']  = 'on';
        $meta['_et_builder_version'] = sprintf( 'VB|Divi|%s', defined( 'ET_BUILDER_VERSION' ) ? ET_BUILDER_VERSION : DiviRequirement::MINIMUM_DIVI_VERSION );
        $meta['_bdc_divi_data']      = (string) wp_json_encode( $divi_data );

        if ( isset( $divi_data['report'] ) ) {
            $meta['_bdc_conversion_report'] = (string) wp_json_encode( array_merge(
                $divi_data['report'],
                [ 'unsupported' => $divi_data['unsupported'] ?? [] ]
            ) );
        }

        // Must be the string 'on' — Divi checks === 'on' to recognise a Divi 5 post.
        $meta['_et_pb_use_divi_5'] = 'on';

        return $meta;
    }

    public function save( int $post_id, array $divi_data ): bool {
        $meta         = $this->export( $divi_data );
        $post_content = $this->serializer->serialize( $divi_data );

        // wp_update_post() unslashes its input; without wp_slash() the JSON
        // escapes inside block attributes lose their backslashes and the page
        // renders "u003Cp" where a paragraph should be.
        wp_update_post( [
            'ID'           => $post_id,
            'post_content' => wp_slash( $post_content ),
        ] );

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        if ( class_exists( 'ET_Core_PageResource' ) ) {
            \ET_Core_PageResource::remove_static_resources( $post_id, 'all' );
        }
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_modules' );
        delete_post_meta( $post_id, '_divi_dynamic_assets_cached_feature_used' );

        return true;
    }
}
