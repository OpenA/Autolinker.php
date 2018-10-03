<?php
/**
 * An HTML parser implementation which simply walks an HTML string and returns an array of
 * {@link HtmlNodes} that represent the basic HTML structure of the input string.
 *
 * Autolinker uses this to only link URLs/emails/mentions within text nodes, effectively ignoring / "walking
 * around" HTML tags.
*/
class HtmlParser {

	/**
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
	private static $htmlRegex = '/(?:<(!DOCTYPE)(?:\s+(?:(?=([^\s"\'>\/=\x00-\x1F\x7F]+))\2(?:\s*?=\s*?(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+))?|(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+)))*>)|(?:<(\/)?(?:!--([\s\S]+?)--|(?:([0-9a-zA-Z][0-9a-zA-Z:]*)\s*\/?)|(?:([0-9a-zA-Z][0-9a-zA-Z:]*)\s+(?:(?:\s+|\b)(?=([^\s"\'>\/=\x00-\x1F\x7F]+))\7(?:\s*?=\s*?(?:"[^"]*?"|\'[^\']*?\'|[^\'"=<>`\s]+))?)*\s*\/?))>)/i';

	/**
	 * @property {RegExp} htmlCharacterEntitiesRegex
	 *
	 * The regular expression that matches common HTML character entities.
	 *
	 * Ignoring &amp; as it could be part of a query string -- handling it separately.
	 */
	private static $htmlCharacterEntitiesRegex = '/(&nbsp;|&#160;|&lt;|&#60;|&gt;|&#62;|&quot;|&#34;|&#39;)/i';

	/**
	 * @property {RegExp} trimRegex
	 *
	 * The regular expression used to trim the leading and trailing whitespace
	 * from a string.
	 */
	private static $trimRegex = '/^[\s\x{FEFF}\xA0]+|[\s\x{FEFF}\xA0]+$/u';

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
		
		if ( ($len = preg_match_all( self::$htmlRegex, $html, $currentResult, PREG_SET_ORDER | PREG_OFFSET_CAPTURE )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$tagText      = $currentResult[ $i ][ 0 ][ 0 ];
				$tagName      = $currentResult[ $i ][ 1 ][ 0 ]; // The <!DOCTYPE> tag (ex: "!DOCTYPE"), or another tag (ex: "a" or "img")
				$commentText  = $currentResult[ $i ][ 4 ][ 0 ];
				$isClosingTag = $currentResult[ $i ][ 3 ][ 0 ];
				$offset       = $currentResult[ $i ][ 0 ][ 1 ];
				$inBetweenTagsText = substr( $html, $lastIndex, $offset );
				
				if( !$tagName ) {
					 $tagName = $currentResult[ $i ][ 5 ][ 0 ] ?: $currentResult[ $i ][ 6 ][ 0 ];
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
	 * Performs the functionality of what modern browsers do when `String.prototype.split()` is called
	 * with a regular expression that contains capturing parenthesis.
	 *
	 * For example:
	 *
	 *     // Modern browsers:
	 *     "a,b,c".split( /(,)/ );  // --> [ 'a', ',', 'b', ',', 'c' ]
	 *
	 *     // Old IE (including IE8):
	 *     "a,b,c".split( /(,)/ );  // --> [ 'a', 'b', 'c' ]
	 *
	 * This method emulates the functionality of modern browsers for the old IE case.
	 *
	 * @param {String} str The string to split.
	 * @param {RegExp} splitRegex The regular expression to split the input `str` on. The splitting
	 *   character(s) will be spliced into the array, as in the "modern browsers" example in the
	 *   description of this method.
	 *   Note #1: the supplied regular expression **must** have the 'g' flag specified.
	 *   Note #2: for simplicity's sake, the regular expression does not need
	 *   to contain capturing parenthesis - it will be assumed that any match has them.
	 * @return {String[]} The split array of strings, with the splitting character(s) included.
	 */
	private function splitAndCapture( $str, $lastIdx = 0 ) {
		
		$result = [];
	
		if ( ($len = preg_match_all( self::$htmlCharacterEntitiesRegex, $str, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE )) ) {
		
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ $i ][ 0 ][ 0 ];
				$offset      = $match[ $i ][ 0 ][ 1 ];
				
				array_push($result,
					substr($str, $lastIdx, $offset - $lastIdx),
					$matchedText
				);
				$lastIdx = $offset + strlen($matchedText);
			}
		}
		array_push($result, substr($str, $lastIdx));
	
		return $result;
	}

	/**
	 * Parses text and HTML entity nodes from a given string. The input string
	 * should not have any HTML tags (elements) within it.
	 *
	 * @param {Number} offset The offset of the text node match within the
	 *   original HTML string.
	 * @param {String} text The string of text to parse. This is from an HTML
	 *   text node.
	 * @return {Autolinker.htmlParser.HtmlNode[]} An array of HtmlNodes to
	 *   represent the {@link TextNodes} and
	 *   {@link EntityNodes} found.
	 */
	private function parseTextAndEntityNodes( $offset, $text ) {
		// split at HTML entities, but include the HTML entities in the results array
		$textAndEntityTokens = $this->splitAndCapture( $text, $offset );
		$nodes = [];
		
		// Every even numbered token is a TextNode, and every odd numbered token is an EntityNode
		// For example: an input `text` of "Test &quot;this&quot; today" would turn into the
		//   `textAndEntityTokens`: [ 'Test ', '&quot;', 'this', '&quot;', ' today' ]
		for( $i = 0; $i < count($textAndEntityTokens); $i += 2 ) {
			$textToken   = $textAndEntityTokens[ $i ];
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
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} tagText The full text of the tag (comment) that was
	 *   matched, including its &lt;!-- and --&gt;.
	 * @param {String} commentText The full text of the comment that was matched.
	 */
	private function createCommentNode( $offset, $tagText, $commentText ) {
		return new CommentNode([
			'offset'  => $offset,
			'text'    => $tagText,
			'comment' => preg_replace( self::$trimRegex, '', $commentText )
		]);
	}

	/**
	 * Factory method to create an {@link ElementNode}.
	 *
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
	private function createElementNode( $offset, $tagText, $tagName, $isClosingTag ) {
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
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} text The text that was matched for the HTML entity (such
	 *   as '&amp;nbsp;').
	 * @return {Autolinker.htmlParser.EntityNode}
	 */
	private function createEntityNode( $offset, $text ) {
		return new EntityNode([ 'offset' => $offset, 'text' => $text ]);
	}

	/**
	 * Factory method to create a {@link TextNode}.
	 *
	 * @param {Number} offset The offset of the match within the original HTML
	 *   string.
	 * @param {String} text The text that was matched.
	 * @return {Autolinker.htmlParser.TextNode}
	 */
	private function createTextNode( $offset, $text ) {
		return new TextNode([ 'offset' => $offset, 'text' => $text ]);
	}
};
