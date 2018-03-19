<?php
/**
 * @abstract
 * @class Matcher
 *
 * An abstract class and interface for individual matchers to find matches in
 * an input string with linkified versions of them.
 *
 * Note that Matchers do not take HTML into account - they must be fed the text
 * nodes of any HTML string, which is handled by {@link Autolinker#parse}.
 */
class Matcher extends Util {

	/**
	 * @cfg {Autolinker.AnchorTagBuilder} tagBuilder (required)
	 *
	 * Reference to the AnchorTagBuilder instance to use to generate HTML tags
	 * for {@link Matches}.
	 */
	var $tagBuilder;
	
	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Matcher
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		// @if DEBUG
		if( !($this->tagBuilder = $cfg['tagBuilder']) ) throw new Exception( '`tagBuilder` cfg required' );
		// @endif
	}
	
	/**
	 * Parses the input `text` and returns the array of {@link Matches}
	 * for the matcher.
	 *
	 * @abstract
	 * @param {String} text The text to scan and replace matches in.
	 * @return {Match[]}
	 */
	function parseMatches( $text ) {
		parent::abstractMethod();
	}
}

?>
