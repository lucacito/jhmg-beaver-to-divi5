<?php
/**
 * Reads the posts out of a WordPress export (WXR) file.
 *
 * Beaver Builder has no export format of its own: Tools → Export (and Beaver
 * Builder's own "export templates" filter there) writes WXR, with the layout
 * sitting serialized inside a `_fl_builder_data` postmeta. This reader returns
 * every item's title, type, slug and meta; it does not unserialize anything.
 */

namespace BeaverDivi5Converter\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WxrReader {

    const WP_NS = 'http://wordpress.org/export/1.2/';

    /**
     * @return array[] Items shaped ['title','post_type','post_name','status','meta' => key ⇒ value].
     * @throws \RuntimeException On XML that is malformed, or that declares a DOCTYPE or entities.
     */
    public function read( string $xml ): array {
        if ( preg_match( '/<!(DOCTYPE|ENTITY)/i', $xml ) ) {
            throw new \RuntimeException( 'The export file declares a DOCTYPE or entities, which an export never does. Refusing to read it.' );
        }

        $previous = libxml_use_internal_errors( true );
        $dom      = new \DOMDocument();
        $loaded   = $dom->loadXML( $xml, LIBXML_NONET | LIBXML_NOCDATA );
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        if ( ! $loaded ) {
            throw new \RuntimeException( 'The export file is not well-formed XML.' );
        }

        $items = [];
        foreach ( $dom->getElementsByTagName( 'item' ) as $item ) {
            $items[] = [
                'title'     => $this->text( $item, 'title' ),
                'post_type' => $this->wp( $item, 'post_type' ),
                'post_name' => $this->wp( $item, 'post_name' ),
                'status'    => $this->wp( $item, 'status' ),
                'meta'      => $this->meta( $item ),
            ];
        }

        return $items;
    }

    /** @return array<string,string> */
    private function meta( \DOMElement $item ): array {
        $meta = [];
        foreach ( $item->getElementsByTagNameNS( self::WP_NS, 'postmeta' ) as $postmeta ) {
            $key   = $this->wp( $postmeta, 'meta_key' );
            $value = $this->wp( $postmeta, 'meta_value' );
            if ( $key !== '' && ! isset( $meta[ $key ] ) ) {
                $meta[ $key ] = $value;
            }
        }
        return $meta;
    }

    private function wp( \DOMElement $parent, string $name ): string {
        $nodes = $parent->getElementsByTagNameNS( self::WP_NS, $name );
        foreach ( $nodes as $node ) {
            if ( $node->parentNode === $parent ) {
                return (string) $node->textContent;
            }
        }
        return $nodes->length > 0 ? (string) $nodes->item( 0 )->textContent : '';
    }

    private function text( \DOMElement $parent, string $name ): string {
        foreach ( $parent->childNodes as $child ) {
            if ( $child instanceof \DOMElement && $child->localName === $name && ( $child->namespaceURI === null || $child->namespaceURI === '' ) ) {
                return (string) $child->textContent;
            }
        }
        return '';
    }
}
