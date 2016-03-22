<?php

class XMLDocument {
	protected $dom;
	protected $target;
	protected $rootItem;

	public function XMLDocument() {
		// create doctype
		$this->dom      = new DOMDocument( "1.0" );
		$this->rootItem = $this->dom;
	}

	public function root( $item = null ) {
		$this->rootItem = ( $item == null ) ? $this->dom : $item;
	}

	public function target( $item = null ) {
		$this->target = $item;
	}

	public function item( $name, $value = null, $attributes = null ) {
		$target = ( $this->target == null ) ? $this->rootItem : $this->target;

		$item = $this->dom->createElement( $name );
		$target->appendChild( $item );

		if ( $value !== null ) {
			$item->appendChild( $this->dom->createTextNode( $value ) );
		}

		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $name => $value ) {
				// create attribute node
				$attribute = $this->dom->createAttribute( $name );
				$item->appendChild( $attribute );

				// create attribute value node
				$attributeValue = $this->dom->createTextNode( $value );
				$attribute->appendChild( $attributeValue );
			}
		}

		return $item;
	}

	public function dump( $return = false ) {
		$data = $this->parse();

		return var_export( $data, $return );
	}

	public function parse() {
		return $this->dom->saveXML();
	}
}

