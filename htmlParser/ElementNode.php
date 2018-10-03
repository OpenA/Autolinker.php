<?php
/**
 * Represents an HTML element node that has been parsed by the {@link HtmlParser}.
 *
 * See this class's superclass ({@link HtmlNode}) for more
 * details.
 */
class ElementNode extends HtmlNode {

	/**
	 * @cfg {String} tagName (required)
	 *
	 * The name of the tag that was matched.
	 */
	protected $tagName = '';

	/**
	 * @cfg {Boolean} closing (required)
	 *
	 * `true` if the element (tag) is a closing tag, `false` if its an opening
	 * tag.
	 */
	protected $closing = false;

	/**
	 * Returns a string name for the type of node that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'element';
	}

	/**
	 * Returns the HTML element's (tag's) name. Ex: for an &lt;img&gt; tag,
	 * returns "img".
	 *
	 * @return {String}
	 */
	function getTagName() {
		return $this->tagName;
	}

	/**
	 * Determines if the HTML element (tag) is a closing tag. Ex: &lt;div&gt;
	 * returns `false`, while &lt;/div&gt; returns `true`.
	 *
	 * @return {Boolean}
	 */
	function isClosing() {
		return $this->closing;
	}
};
