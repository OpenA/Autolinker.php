<?php
/**
 * @class Phone
 * @extends Match
 *
 * Represents a Phone number match found in an input string which should be
 * Autolinked.
 *
 * See this class's superclass ({@link Match}) for more
 * details.
 */
class Phone extends Match {

	/**
	 * @protected
	 * @property {String} number (required)
	 *
	 * The phone number that was matched, without any delimiter characters.
	 *
	 * Note: This is a string to allow for prefixed 0's.
	 */
	var $number;
	
	/**
	 * @protected
	 * @property  {Boolean} plusSign (required)
	 *
	 * `true` if the matched phone number started with a '+' sign. We'll include
	 * it in the `tel:` URL if so, as this is needed for international numbers.
	 *
	 * Ex: '+1 (123) 456 7879'
	 */
	var $plusSign;

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		
		// @if DEBUG
		if( !$cfg['number'] ) throw new Exception( '`number` cfg required' );
		if( $cfg['plusSign'] == null ) throw new Exception( '`plusSign` cfg required' );
		// @endif
		
		$this->number = $cfg['number'];
		$this->plusSign = $cfg['plusSign'];
	}
	
	/**
	 * Returns a string name for the type of match that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'phone';
	}
	
	/**
	 * Returns the phone number that was matched as a string, without any
	 * delimiter characters.
	 *
	 * Note: This is a string to allow for prefixed 0's.
	 *
	 * @return {String}
	 */
	function getNumber() {
		return $this->number;
	}
	
	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @return {String}
	 */
	 function getAnchorHref() {
		return 'tel:'. ( $this->plusSign ? '+' : '' ) . $this->number;
	}
	
	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorText() {
		return $this->matchedText;
	}
};

?>
