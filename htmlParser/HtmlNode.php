<?php
/*global Autolinker */
/**
 * @abstract
 * @class HtmlNode
 *
 * Represents an HTML node found in an input string. An HTML node is one of the
 * following:
 *
 * 1. An {@link ElementNode}, which represents
 *    HTML tags.
 * 2. A {@link CommentNode}, which represents
 *    HTML comments.
 * 3. A {@link TextNode}, which represents text
 *    outside or within HTML tags.
 * 4. A {@link EntityNode}, which represents
 *    one of the known HTML entities that Autolinker looks for. This includes
 *    common ones such as &amp;quot; and &amp;nbsp;
 */

class HtmlNode extends Util {

	/**
	 * @cfg {Number} offset (required)
	 *
	 * The offset of the HTML node in the original text that was parsed.
	 */
	var $offset = 0;

	/**
	 * @cfg {String} text (required)
	 *
	 * The text that was matched for the HtmlNode.
	 *
	 * - In the case of an {@link ElementNode},
	 *   this will be the tag's text.
	 * - In the case of an {@link CommentNode},
	 *   this will be the comment's text.
	 * - In the case of a {@link TextNode}, this
	 *   will be the text itself.
	 * - In the case of a {@link EntityNode},
	 *   this will be the text of the HTML entity.
	 */
	var $text = '';

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match instance,
	 * specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::assign( $this, $cfg );
		
		// @if DEBUG
		if( $this->offset === null ) throw new Exception( '`offset` cfg required' );
		if( $this->text   === null ) throw new Exception( '`text` cfg required' );
		// @endif
	}

	/**
	 * Returns a string name for the type of node that this class represents.
	 *
	 * @abstract
	 * @return {String}
	 */
	function getType() {
		parent::abstractMethod();
	}
	
	/**
	 * Retrieves the {@link #offset} of the HtmlNode. This is the offset of the
	 * HTML node in the original string that was parsed.
	 *
	 * @return {Number}
	 */
	function getOffset() {
		return $this->offset;
	}

	/**
	 * Retrieves the {@link #text} for the HtmlNode.
	 *
	 * @return {String}
	 */
	function getText() {
		return $this->text;
	}
};
?>
