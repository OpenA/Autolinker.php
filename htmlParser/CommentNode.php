<?php
/**
 * @class Autolinker.htmlParser.CommentNode
 * @extends Autolinker.htmlParser.HtmlNode
 *
 * Represents an HTML comment node that has been parsed by the
 * {@link HtmlParser}.
 *
 * See this class's superclass ({@link HtmlNode}) for more
 * details.
 */

class CommentNode extends HtmlNode {

	/**
	 * @cfg {String} comment (required)
	 *
	 * The text inside the comment tag. This text is stripped of any leading or
	 * trailing whitespace.
	 */
	var $comment = '';

	/**
	 * Returns a string name for the type of node that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'comment';
	}

	/**
	 * Returns the comment inside the comment tag.
	 *
	 * @return {String}
	 */
	function getComment() {
		return $this->comment;
	}
};

?>
