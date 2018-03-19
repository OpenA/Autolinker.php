<?php
/**
 * @class Hashtag
 * @extends Match
 *
 * Represents a Hashtag match found in an input string which should be
 * Autolinked.
 *
 * See this class's superclass ({@link Match}) for more
 * details.
 */
class Hashtag extends Match {

	/**
	 * @cfg {String} serviceName
	 *
	 * The service to point hashtag matches to. See {@link Autolinker#hashtag}
	 * for available values.
	 */
	private $serviceName;

	/**
	 * @cfg {String} hashtag (required)
	 *
	 * The Hashtag that was matched, without the '#'.
	 */
	private $hashtag;

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		//Autolinker.match.Match.prototype.constructor.call( this, cfg );
		
		// @if DEBUG
		// TODO: if( !serviceName ) throw new Exception( '`serviceName` cfg required' );
		if( !$cfg['hashtag'] ) throw new Exception( '`hashtag` cfg required' );
		// @endif
		
		$this->serviceName = $cfg['serviceName'];
		$this->hashtag = $cfg['hashtag'];
	}

	/**
	 * Returns the type of match that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'hashtag';
	}

	/**
	 * Returns the configured {@link #serviceName} to point the Hashtag to.
	 * Ex: 'facebook', 'twitter'.
	 *
	 * @return {String}
	 */
	function getServiceName() {
		return $this->serviceName;
	}

	/**
	 * Returns the matched hashtag, without the '#' character.
	 *
	 * @return {String}
	 */
	function getHashtag() {
		return $this->hashtag;
	}

	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorHref() {
		$serviceName = $this->serviceName;
		$hashtag = $this->hashtag;
		
		switch( $serviceName ) {
			case 'twitter' :
				return 'https://twitter.com/hashtag/'. $hashtag;
			case 'facebook' :
				return 'https://www.facebook.com/hashtag/'. $hashtag;
			case 'instagram' :
				return 'https://instagram.com/explore/tags/'. $hashtag;
			default :  // Shouldn't happen because Autolinker's constructor should block any invalid values, but just in case.
				throw new Exception( 'Unknown service name to point hashtag to: '. $serviceName );
		}
	}

	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorText() {
		return '#'. $this->hashtag;
	}
};

?>
