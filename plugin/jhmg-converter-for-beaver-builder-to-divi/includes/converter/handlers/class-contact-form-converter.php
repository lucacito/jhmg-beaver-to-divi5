<?php

namespace BeaverDivi5Converter\Converter\Handlers;

use BeaverDivi5Converter\Converter\BaseBeaverConverter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Beaver Builder Pro Contact Form → divi/contact-form with one divi/contact-field per enabled field. */
class ContactFormConverter extends BaseBeaverConverter {

    private const FIELDS = [
        // key => [toggle key, label key, divi field type, default shown, required]
        'name'    => [ 'name_toggle', 'name_placeholder', 'input', true, 'on' ],
        'email'   => [ 'email_toggle', 'email_placeholder', 'email', true, 'on' ],
        'phone'   => [ 'phone_toggle', 'phone_placeholder', 'input', false, 'off' ],
        'subject' => [ 'subject_toggle', 'subject_placeholder', 'input', false, 'off' ],
        'message' => [ 'message_toggle', 'message_placeholder', 'text', true, 'on' ],
    ];

    public function convert( array $node ): array {
        $id       = (string) ( $node['id'] ?? uniqid( 'bdc_form_' ) );
        $settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : [];

        $style = $this->mapStyle( 'generic', $node );
        $attrs = $style['divi_attrs'];

        $button = trim( $this->firstText( $settings, [ 'btn_text', 'button_text' ] ) );
        if ( $button !== '' ) {
            $attrs['button']['innerContent']['desktop']['value'] = $button;
        }
        $to = trim( $this->firstText( $settings, [ 'mailto_email', 'email_to' ] ) );
        if ( $to !== '' ) {
            $attrs['email']['advanced']['receiver']['desktop']['value'] = $to;
        }
        $success = trim( $this->firstText( $settings, [ 'success_message' ] ) );
        if ( $success !== '' ) {
            $attrs['email']['innerContent']['desktop']['value'] = $success;
        }
        if ( $this->text( $settings, 'success_action' ) === 'redirect' && trim( $this->text( $settings, 'success_url' ) ) !== '' ) {
            $attrs['redirect']['advanced']['useRedirect']['desktop']['value'] = 'on';
            $attrs['redirect']['innerContent']['desktop']['value'] = trim( $this->text( $settings, 'success_url' ) );
        }

        $children = [];
        foreach ( self::FIELDS as $field => [ $toggle, $label_key, $type, $default, $required ] ) {
            $raw   = $settings[ $toggle ] ?? null;
            $shown = $raw === null ? $default : in_array( (string) $raw, [ 'show', '1', 'yes', 'true' ], true );
            if ( ! $shown ) {
                continue;
            }
            $label = trim( $this->text( $settings, $label_key ) ) ?: ucfirst( $field );
            $children[] = $this->block( $id . '-' . $field, 'divi/contact-field', [
                'fieldItem' => [
                    'innerContent' => [ 'desktop' => [ 'value' => $label ] ],
                    'advanced'     => [
                        'id'       => [ 'desktop' => [ 'value' => $field ] ],
                        'type'     => [ 'desktop' => [ 'value' => $type ] ],
                        'required' => [ 'desktop' => [ 'value' => $required ] ],
                    ],
                ],
            ] );
        }

        $this->engine->logConverted( 'contact-form' );
        $this->logUnmappedSettings( $id, $settings, array_merge(
            [ 'btn_text', 'button_text', 'mailto_email', 'email_to', 'success_message', 'success_action', 'success_url', 'subject_hidden', 'terms_checkbox', 'terms_text', 'recaptcha_toggle', 'recaptcha_validate_type' ],
            array_filter( array_keys( $settings ), static fn( string $k ) => str_ends_with( $k, '_toggle' ) || str_ends_with( $k, '_placeholder' ) || str_starts_with( $k, 'btn_' ) || str_starts_with( $k, 'form_' ) ),
            $style['handled_keys']
        ) );

        return $this->block( $id, 'divi/contact-form', $attrs, $children );
    }
}
