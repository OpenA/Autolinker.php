<?php
/*global Autolinker */
/**
 * @class Autolinker.htmlParser.EntityNode
 * @extends Autolinker.htmlParser.HtmlNode
 *
 * Represents a known HTML entity node that has been parsed by the {@link HtmlParser}.
 * Ex: '&amp;nbsp;', or '&amp#160;' (which will be retrievable from the {@link #getText}
 * method.
 *
 * Note that this class will only be returned from the HtmlParser for the set of
 * checked HTML entity nodes  defined by the {@link HtmlParser#htmlCharacterEntitiesRegex}.
 *
 * See this class's superclass ({@link HtmlNode}) for more
 * details.
 */

class EntityNode extends HtmlNode {

	/**
	 * Returns a string name for the type of node that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'entity';
	}
};
?>
