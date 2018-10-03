<?php
/**
 * Represents a Mention match found in an input string which should be Autolinked.
 *
 * See this class's superclass ({@link Autolinker.match.Match}) for more details.
 */

class Mention extends Match {
	
	/**
	 * @cfg {String} serviceName
	 *
	 * The service to point mention matches to. See {@link Autolinker#mention}
	 * for available values.
	 */
	protected $serviceName;
	
	/**
	 * @cfg {String} mention (required)
	 *
	 * The Mention that was matched, without the '@' character.
	 */
	protected $mention;
	
	/**
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		
		$this->assign( $cfg );
		
		// @if DEBUG
		$this->requireStrict('mention', 'serviceName');
		// @endif
	}
	
	/**
	 * Returns the type of match that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'mention';
	}
	
	/**
	 * Returns the mention, without the '@' character.
	 *
	 * @return {String}
	 */
	function getMention() {
		return $this->mention;
	}
	
	/**
	 * Returns the configured {@link #serviceName} to point the mention to.
	 * Ex: 'instagram', 'twitter'.
	 *
	 * @return {String}
	 */
	function getServiceName() {
		return $this->serviceName;
	}
	
	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorHref() {
		switch( $this->serviceName ) {
			case 'twitter' :
				return 'https://twitter.com/'. $this->mention;
			case 'instagram' :
				return 'https://instagram.com/'. $this->mention;
			default :  // Shouldn't happen because Autolinker's constructor should block any invalid values, but just in case.
				throw new Exception( 'Unknown service name to point mention to: '. $this->serviceName );
		}
	}
	
	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorText() {
		return '@'. $this->mention;
	}
	
	/**
	 * Returns the CSS class suffixes that should be used on a tag built with
	 * the match. See {@link Autolinker.match.Match#getCssClassSuffixes} for
	 * details.
	 *
	 * @return {String[]}
	 */
	function getCssClassSuffixes() {
		$cssClassSuffixes = [ $this->getType() ];
		
		if( $this->serviceName ) {
			array_push( $cssClassSuffixes, $this->serviceName );
		}
		return $cssClassSuffixes;
	}
};
