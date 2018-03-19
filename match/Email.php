<?php
/**
 * @class Email
 * @extends Match
 *
 * Represents a Email match found in an input string which should be Autolinked.
 *
 * See this class's superclass ({@link Match}) for more details.
 */
class Email extends Match {

	/**
	 * @cfg {String} email (required)
	 *
	 * The email address that was matched.
	 */
	private $email;

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct ( $cfg ) {
		parent::__construct($cfg);
		//Autolinker.match.Match.prototype.constructor.call( this, cfg );
		
		// @if DEBUG
		if( !$cfg['email'] ) throw new Exception( '`email` cfg required' );
		// @endif
		
		$this->email = $cfg['email'];
	}
	
	/**
	 * Returns a string name for the type of match that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'email';
	}
	
	/**
	 * Returns the email address that was matched.
	 *
	 * @return {String}
	 */
	function getEmail() {
		return $this->email;
	}
	
	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorHref() {
		return 'mailto:'. $this->email;
	}
	
	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorText() {
		return $this->email;
	}
};

?>
