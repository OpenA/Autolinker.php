<?php
/**
 * @class HtmlParser
 * @extends Object
 *
 * An HTML parser implementation which simply walks an HTML string and returns an array of
 * {@link HtmlNodes} that represent the basic HTML structure of the input string.
 *
 * Autolinker uses this to only link URLs/emails/mentions within text nodes, effectively ignoring / "walking
 * around" HTML tags.
*/
class HtmlParser extends Util {

	/**
	 * @private
	 * @property {RegExp} htmlRegex
	 *
	 * The regular expression used to pull out HTML tags from a string. Handles namespaced HTML tags and
	 * attribute names, as specified by http://www.w3.org/TR/html-markup/syntax.html.
	 *
	 * Capturing groups:
	 *
	 * 1. The "!DOCTYPE" tag name, if a tag is a &lt;!DOCTYPE&gt; tag.
	 * 2. If it is an end tag, this group will have the '/'.
	 * 3. If it is a comment tag, this group will hold the comment text (i.e.
	 *    the text inside the `&lt;!--` and `--&gt;`.
	 * 4. The tag name for a tag without attributes (other than the &lt;!DOCTYPE&gt; tag)
	 * 5. The tag name for a tag with attributes (other than the &lt;!DOCTYPE&gt; tag)
	 */
	static $htmlRegex = '/(?:<(!DOCTYPE)(?:\s+(?:(?=([^\s"\'>\/=\x00-\x1F\x7F]+))\2(?:\s*?=\s*?(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+))?|(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+)))*>)|(?:<(\/)?(?:!--([\s\S]+?)--|(?:([0-9a-zA-Z][0-9a-zA-Z:]*)\s*\/?)|(?:([0-9a-zA-Z][0-9a-zA-Z:]*)\s+(?:(?:\s+|\b)(?=([^\s"\'>\/=\x00-\x1F\x7F]+))\7(?:\s*?=\s*?(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+))?)*\s*\/?))>)/i';

	/**
	 * @private
	 * @property {RegExp} htmlCharacterEntitiesRegex
	 *
	 * The regular expression that matches common HTML character entities.
	 *
	 * Ignoring &amp; as it could be part of a query string -- handling it separately.
	 */
	static $htmlCharacterEntitiesRegex = '/(&nbsp;|&#160;|&lt;|&#60;|&gt;|&#62;|&quot;|&#34;|&#39;)/i'; //global inline
	
	/**
	 * Parses an HTML string and returns a simple array of {@link HtmlNodes}
	 * to represent the HTML structure of the input string.
	 *
	 * @param {String} html The HTML to parse.
	 * @return {Autolinker.htmlParser.HtmlNode[]}
	 */
	function parse( $html ) {
		$lastIndex = 0;
		$textAndEntityNodes;
		$nodes = [];  // will be the result of the method
		
		if ( ($len = preg_match_all( static::$htmlRegex, $html, $currentResult )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$tagText      = $currentResult[ 0 ][ $i ];
				$tagName      = $currentResult[ 1 ][ $i ];   // The <!DOCTYPE> tag (ex: "!DOCTYPE"), or another tag (ex: "a" or "img")
				$commentText  = $currentResult[ 4 ][ $i ];
				$isClosingTag = $currentResult[ 3 ][ $i ];
				$offset       = strpos($html, $tagText);
				$inBetweenTagsText = substr( $html, $lastIndex, $offset );
				
				if( !$tagName ) {
					 $tagName = !$currentResult[ 5 ][ $i ] ? $currentResult[ 6 ][ $i ] : $currentResult[ 5 ][ $i ];
				}
				
				// Push TextNodes and EntityNodes for any text found between tags
				if( $inBetweenTagsText ) {
					$textAndEntityNodes = $this->parseTextAndEntityNodes( $lastIndex, $inBetweenTagsText );
					foreach( $textAndEntityNodes as $entry => $node ) {
						array_push( $nodes, $node );
					}
				}
			
				// Push the CommentNode or ElementNode
				if( $commentText ) {
					array_push( $nodes, $this->createCommentNode( $offset, $tagText, $commentText ) );
				} else {
					array_push( $nodes, $this->createElementNode( $offset, $tagText, $tagName, !!$isClosingTag ) );
				}
				
				$lastIndex = $offset + strlen( $tagText );
			}
		}
		
		// Process any remaining text after the last HTML element. Will process all of the text if there were no HTML elements.
		if( $lastIndex < strlen($html) ) {
			$text = substr( $html, $lastIndex );
			
			// Push TextNodes and EntityNodes for any text found between tags
			if( $text ) {
				$textAndEntityNodes = $this->parseTextAndEntityNodes( $lastIndex, $text );
				
				// Note: the following 3 lines were previously:
				//   nodes.push.apply( nodes, textAndEntityNodes );
				// but this was causing a "Maximum Call Stack Size Exceeded"
				// error on inputs with a large number of html entities.
				foreach($textAndEntityNodes as $entry => $node) {
					array_push( $nodes, $node );
				}
			}
		}
		return $nodes;
	}

	/**
	 * Parses text and HTML entity nodes from a given string. The input string
	 * should not have any HTML tags (elements) within it.
	 *
	 * @private
	 * @param {Number} offset The offset of the text node match within the
	 *   original HTML string.
	 * @param {String} text The string of text to parse. This is from an HTML
	 *   text node.
	 * @return {Autolinker.htmlParser.HtmlNode[]} An array of HtmlNodes to
	 *   represent the {@link TextNodes} and
	 *   {@link EntityNodes} found.
	 */
	function parseTextAndEntityNodes( $offset, $text ) {
		$nodes = [];
		$textAndEntityTokens = splitAndCapture( $text, static::$htmlCharacterEntitiesRegex );  // split at HTML entities, but include the HTML entities in the results array

		// Every even numbered token is a TextNode, and every odd numbered token is an EntityNode
		// For example: an input `text` of "Test &quot;this&quot; today" would turn into the
		//   `textAndEntityTokens`: [ 'Test ', '&quot;', 'this', '&quot;', ' today' ]
		for( $i = 0, $len = count($textAndEntityTokens); $i < $len; $i += 2 ) {
			$textToken = $textAndEntityTokens[ $i ];
			$entityToken = $textAndEntityTokens[ $i + 1 ];
			
			if( $textToken ) {
				array_push($nodes, $this->createTextNode( $offset, $textToken ));
				$offset += strlen($textToken);
			}
			if( $entityToken ) {
				array_push($nodes, $this->createEntityNode( $offset, $entityToken ));
				$offset += strlen($entityToken);
			}
		}
		return $nodes;
	}

	/**
	 * Factory method to create an {@link CommentNode}.
	 *
	 * @private
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} tagText The full text of the tag (comment) that was
	 *   matched, including its &lt;!-- and --&gt;.
	 * @param {String} commentText The full text of the comment that was matched.
	 */
	function createCommentNode( $offset, $tagText, $commentText ) {
		return new CommentNode([
			'offset'  => $offset,
			'text'    => $tagText,
			'comment' => parent::trim( $commentText )
		]);
	}

	/**
	 * Factory method to create an {@link ElementNode}.
	 *
	 * @private
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} tagText The full text of the tag (element) that was
	 *   matched, including its attributes.
	 * @param {String} tagName The name of the tag. Ex: An &lt;img&gt; tag would
	 *   be passed to this method as "img".
	 * @param {Boolean} isClosingTag `true` if it's a closing tag, false
	 *   otherwise.
	 * @return {Autolinker.htmlParser.ElementNode}
	 */
	function createElementNode( $offset, $tagText, $tagName, $isClosingTag ) {
		return new ElementNode([
			'offset'  => $offset,
			'text'    => $tagText,
			'tagName' => $tagName,
			'closing' => $isClosingTag
		]);
	}
	
	/**
	 * Factory method to create a {@link EntityNode}.
	 *
	 * @private
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} text The text that was matched for the HTML entity (such
	 *   as '&amp;nbsp;').
	 * @return {Autolinker.htmlParser.EntityNode}
	 */
	function createEntityNode( $offset, $text ) {
		return new EntityNode([ 'offset' => $offset, 'text' => $text ]);
	}
	
	/**
	 * Factory method to create a {@link TextNode}.
	 *
	 * @private
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} text The text that was matched.
	 * @return {Autolinker.htmlParser.TextNode}
	 */
	function createTextNode( $offset, $text ) {
		return new TextNode([ 'offset' => $offset, 'text' => $text ]);
	}
};

?>
