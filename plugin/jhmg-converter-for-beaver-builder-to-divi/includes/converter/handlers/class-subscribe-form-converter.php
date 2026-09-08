<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Subscribe Form → divi/signup. The mailing-list connection cannot be carried. */
class SubscribeFormConverter extends BaseBeaverConverter {

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bbdc_signup_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $title = trim( $this->text( $settings, 'title' ) );
        if ( $title !== '' ) {
            $attrs['title']['innerContent']['desktop']['value'] = $title;
        }
        $text = trim( $this->text( $settings, 'text' ) );
        if ( $text !== '' ) {
            $attrs['content']['innerContent']['desktop']['value'] = RichTextConverter::autop( $text );
        }
        $button = trim( $this->firstText( $settings, [ 'btn_text', 'button_text' ] ) );
        if ( $button !== '' ) {
            $attrs['button']['innerContent']['desktop']['value'] = [ 'text' => $button ];
        }
        $name_type = $this->text( $settings, 'name_field_type' );
        $attrs['field']['advanced']['nameField']['desktop']['value'] = $name_type === 'none' ? 'off' : 'on';
        $success = trim( $this->text( $settings, 'success_message' ) );
        if ( $success !== '' ) {
            $attrs['success']['advanced']['message']['desktop']['value'] = $success;
        }

        $service = trim( $this->text( $settings, 'service' ) );
        $this->engine->logNotCarriedOver( 'integration', $id, 'mailing-list connection' . ( $service !== '' ? " ({$service})" : '' ) . ' must be reconnected in the Divi Email Optin module' );

        $this->engine->logConverted( 'signup' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'title', 'text', 'btn_text', 'button_text', 'name_field_type', 'success_message', 'success_action', 'success_url', 'service', 'service_account', 'list_id', 'terms_checkbox', 'terms_text', 'layout', 'name_field_placeholder', 'email_field_placeholder' ],
            array_filter( array_keys( $settings ), static fn( string $k ) => str_starts_with( $k, 'btn_' ) ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/signup', $attrs );
    }
}
