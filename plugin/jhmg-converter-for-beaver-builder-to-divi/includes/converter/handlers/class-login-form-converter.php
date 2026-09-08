<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Login Form → divi/login. */
class LoginFormConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_login_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $title = trim( $this->firstText( $settings, [ 'title', 'heading' ] ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
        }
        $text = trim( $this->firstText( $settings, [ 'text', 'content' ] ) );
        if ( $text !== '' ) {
            $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );
        }
        $redirect = trim( $this->firstText( $settings, [ 'redirect', 'redirect_url' ] ) );
        if ( $redirect !== '' ) {
            $attrs['module']['advanced']['redirectUrl']['desktop']['value'] = $redirect;
        }

        $this->engine->logConverted( 'login' );
        $this->logUnmappedSettings( $id, $settings, array_merge( [ 'title', 'heading', 'text', 'content', 'redirect', 'redirect_url', 'btn_text', 'show_remember', 'show_lost_password', 'show_register', 'layout', 'logged_in_action', 'username_placeholder', 'password_placeholder' ], array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'btn_' ) ), $style['handled_keys'] ) );

        return $this->block( $id, 'divi/login', $attrs );
    }
}
