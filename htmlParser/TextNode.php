<?php
/**
 * @class TextNode
 * @extends HtmlNode
 *
 * Represents a text node that has been parsed by the {@link HtmlParser}.
 *
 * See this class's superclass ({@link HtmlNode}) for more
 * details.
 */

class TextNode extends HtmlNode {

	/**
	 * Returns a string name for the type of node that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'text';
	}
};

?>
