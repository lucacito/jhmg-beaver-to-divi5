<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Beaver Builder Pro Social Buttons (share buttons) → divi/social-media-follow.
 * Divi's module links to profiles rather than sharing the page, so the
 * networks are kept and the difference is reported.
 */
class SocialButtonsConverter extends BaseBeaverConverter {

    private const NETWORKS = [ 'facebook', 'twitter', 'linkedin', 'pinterest', 'email', 'reddit', 'tumblr', 'whatsapp', 'telegram', 'xing', 'buffer', 'digg', 'evernote', 'pocket' ];

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_social_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style    = $this->mapStyle( 'generic', $node );
        $children = [];
        $url      = $this->text( $settings, 'url_type' ) === 'custom' ? trim( $this->text( $settings, 'custom_url' ) ) : '';

        foreach ( self::NETWORKS as $network ) {
            $raw = $settings[ 'show_' . $network ] ?? null;
            if ( $raw === null || ! in_array( (string) $raw, [ '1', 'yes', 'true', 'show' ], true ) ) {
                continue;
            }
            $children[] = $this->block( $id . '-' . $network, 'divi/social-media-follow-network', [
                'socialNetwork' => [ 'innerContent' => [ 'desktop' => [ 'value' => [ 'title' => $network, 'link' => $url, 'label' => ucfirst( $network ) ] ] ] ],
            ] );
        }

        if ( empty( $children ) ) {
            $this->engine->logWarning( "Social buttons {$id}: no networks enabled." );
        }
        $this->engine->logNotCarriedOver( 'interaction', $id, 'share buttons became social follow icons; set each network link' );

        $this->engine->logConverted( 'social-media-follow' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'url_type', 'custom_url', 'size', 'align', 'style', 'spacing', 'spacing_unit', 'show_labels' ],
            array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'show_' ) ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/social-media-follow', $style['divi_attrs'], $children );
    }
}
