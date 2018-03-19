<?php
/**
 * @abstract
 * @class Match
 *
 * Represents a match found in an input string which should be Autolinked. A Match object is what is provided in a
 * {@link Autolinker#replaceFn replaceFn}, and may be used to query for details about the match.
 *
 * For example:
 *
 *     var input = "...";  // string with URLs, Email Addresses, and Mentions (Twitter, Instagram)
 *
 *     var linkedText = Autolinker.link( input, {
 *         replaceFn : function( match ) {
 *             console.log( "href = ", match.getAnchorHref() );
 *             console.log( "text = ", match.getAnchorText() );
 *
 *             switch( match.getType() ) {
 *                 case 'url' :
 *                     console.log( "url: ", match.getUrl() );
 *
 *                 case 'email' :
 *                     console.log( "email: ", match.getEmail() );
 *
 *                 case 'mention' :
 *                     console.log( "mention: ", match.getMention() );
 *             }
 *         }
 *     } );
 *
 * See the {@link Autolinker} class for more details on using the {@link Autolinker#replaceFn replaceFn}.
 */
class Match extends Util {

	/**
	 * @cfg {Autolinker.AnchorTagBuilder} tagBuilder (required)
	 *
	 * Reference to the AnchorTagBuilder instance to use to generate an anchor
	 * tag for the Match.
	 */
	var $tagBuilder;
	
	/**
	 * @cfg {String} matchedText (required)
	 *
	 * The original text that was matched by the {@link Matcher}.
	 */
	var $matchedText = '';
	
	/**
	 * @cfg {Number} offset (required)
	 *
	 * The offset of where the match was made in the input string.
	 */
	var $offset = 0;

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		// @if DEBUG
		if( $cfg['tagBuilder']  === null ) throw new Exception( '`tagBuilder` cfg required' );
		if( $cfg['matchedText'] === null ) throw new Exception( '`matchedText` cfg required' );
		if( $cfg['offset']      === null ) throw new Exception( '`offset` cfg required' );
		// @endif
		
		$this->tagBuilder  = $cfg['tagBuilder'];
		$this->matchedText = $cfg['matchedText'];
		$this->offset      = $cfg['offset'];
	}
	
	/**
	 * Returns a string name for the type of match that this class represents.
	 *
	 * @abstract
	 * @return {String}
	 */
	function getType() { parent::abstractMethod(); }
	
	/**
	 * Returns the original text that was matched.
	 *
	 * @return {String}
	 */
	function getMatchedText() {
		return $this->matchedText;
	}
	
	/**
	 * Sets the {@link #offset} of where the match was made in the input string.
	 *
	 * A {@link Matcher} will be fed only HTML text nodes,
	 * and will therefore set an original offset that is relative to the HTML
	 * text node itself. However, we want this offset to be relative to the full
	 * HTML input string, and thus if using {@link Autolinker#parse} (rather
	 * than calling a {@link Matcher} directly), then this
	 * offset is corrected after the Matcher itself has done its job.
	 *
	 * @param {Number} offset
	 */
	function setOffset( $offset ) {
		$this->offset = $offset;
	}


	/**
	 * Returns the offset of where the match was made in the input string. This
	 * is the 0-based index of the match.
	 *
	 * @return {Number}
	 */
	function getOffset() {
		return $this->offset;
	}

	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @abstract
	 * @return {String}
	 */
	function getAnchorHref() { parent::abstractMethod(); }
	
	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @abstract
	 * @return {String}
	 */
	function getAnchorText() { parent::abstractMethod(); }
	
	/**
	 * Returns the CSS class suffix(es) for this match.
	 *
	 * A CSS class suffix is appended to the {@link Autolinker#className} in
	 * the {@link AnchorTagBuilder} when a match is translated into
	 * an anchor tag.
	 *
	 * For example, if {@link Autolinker#className} was configured as 'myLink',
	 * and this method returns `[ 'url' ]`, the final class name of the element
	 * will become: 'myLink myLink-url'.
	 *
	 * The match may provide multiple CSS class suffixes to be appended to the
	 * {@link Autolinker#className} in order to facilitate better styling
	 * options for different match criteria. See {@link MentionObj}
	 * for an example.
	 *
	 * By default, this method returns a single array with the match's
	 * {@link #getType type} name, but may be overridden by subclasses.
	 *
	 * @return {String[]}
	 */
	function getCssClassSuffixes() {
		return [ $this->getType() ];
	}
	
	/**
	 * Builds and returns an {@link Autolinker.HtmlTag} instance based on the
	 * Match.
	 *
	 * This can be used to easily generate anchor tags from matches, and either
	 * return their HTML string, or modify them before doing so.
	 *
	 * Example Usage:
	 *
	 *     var tag = match.buildTag();
	 *     tag.addClass( 'cordova-link' );
	 *     tag.setAttr( 'target', '_system' );
	 *
	 *     tag.toAnchorString();  // <a href="http://google.com" class="cordova-link" target="_system">Google</a>
	 */
	function buildTag() {
		return $this->tagBuilder->build( $this );
	}
};

?>
