<?php
set_include_path( __DIR__ );

include 'Util.php';
include 'AnchorTagBuilder.php';
include 'RegexLib.php';
include 'HtmlTag.php';

include 'matcher/Matcher.php';
include 'matcher/Email.php';
include 'matcher/Hashtag.php';
include 'matcher/Mention.php';
include 'matcher/Phone.php';
include 'matcher/Url.php';

include 'match/Match.php';
include 'match/Email.php';
include 'match/Hashtag.php';
include 'match/Mention.php';
include 'match/Phone.php';
include 'match/Url.php';

include 'htmlParser/HtmlNode.php';
include 'htmlParser/HtmlParser.php';
include 'htmlParser/ElementNode.php';
include 'htmlParser/CommentNode.php';
include 'htmlParser/EntityNode.php';
include 'htmlParser/TextNode.php';


class Autolinker {
	
	static $version = '1.6.2';
	
	var $htmlParser = null;
	var $matchers   = null;
	var $tagBuilder = null;
	
	var $truncate ;
	var $className;
	var $replaceFn;
	var $context  ;
		
	function __construct($cfg = []) {
		
		$this->urls = normalizeUrlsCfg( $cfg['urls'] );
		$this->email = gettype($cfg['email']) === 'boolean' ? $cfg['email'] : true;
		$this->phone = gettype($cfg['phone']) === 'boolean' ? $cfg['phone'] : true;
		$this->hashtag = $cfg['hashtag'] || false;
		$this->mention = $cfg['mention'] || false;
		$this->newWindow = gettype($cfg['newWindow']) === 'boolean' ? $cfg['newWindow'] : true;
		$this->stripPrefix = normalizeStripPrefixCfg( $cfg['stripPrefix'] );
		$this->stripTrailingSlash = gettype($cfg['stripTrailingSlash']) === 'boolean' ? $cfg['stripTrailingSlash'] : true;
		$this->decodePercentEncoding = gettype($cfg['decodePercentEncoding']) === 'boolean' ? $cfg['decodePercentEncoding'] : true;
		
		// Validate the value of the `mention` cfg
		$mention = $this->mention;
		if( $mention !== false && $mention !== 'twitter' && $mention !== 'instagram' ) {
			throw new Exception( "invalid `mention` cfg - see docs" );
		}
		
		// Validate the value of the `hashtag` cfg
		$hashtag = $this->hashtag;
		if( $hashtag !== false && $hashtag !== 'twitter' && $hashtag !== 'facebook' && $hashtag !== 'instagram' ) {
			throw new Exception( "invalid `hashtag` cfg - see docs" );
		}
		
		$this->truncate  = normalizeTruncateCfg( $cfg['truncate'] );
		$this->className = !$cfg['className'] ? ''    : $cfg['className'];
		$this->replaceFn = !$cfg['replaceFn'] ? null  : $cfg['replaceFn'];
		$this->context   = !$cfg['context']   ? $this : $cfg['context'];
	}
	
	function parse( $textOrHtml ) {
		
		$anchorTagStackCount = 0;  // used to only process text around anchor tags, and any inner text/html they may have;
		$htmlParser = $this->getHtmlParser();
		$htmlNodes  = $htmlParser->parse( $textOrHtml );
		$matches    = [];
		
		// Find all matches within the `textOrHtml` (but not matches that are
		// already nested within <a> tags)
		foreach ($htmlNodes as $node) {
			$nodeType = $node->getType();
			if ( $nodeType === 'element' && $node->getTagName() === 'a' ) { // Process HTML anchor element nodes in the input `textOrHtml` to find out when we're within an <a> tag
				if ( !$node->isClosing()) { // it's the start <a> tag
					$anchorTagStackCount++;
				} else {  // it's the end </a> tag
					$anchorTagStackCount = max( $anchorTagStackCount - 1, 0 ); // attempt to handle extraneous </a> tags by making sure the stack count never goes below 0
				}
			} else if ( $nodeType === 'text' && $anchorTagStackCount === 0) {  // Process text nodes that are not within an <a> tag
				$textNodeMatches = $this->parseText( $node->getText(), $node->getOffset() );
				foreach ($textNodeMatches as $txtNode) {
					array_push($matches, $txtNode);
				}
			}
		}
		// After we have found all matches, remove subsequent matches that
		// overlap with a previous match. This can happen for instance with URLs,
		// where the url 'google.com/#link' would match '#link' as a hashtag.
		$matches = $this->compactMatches( $matches );
		
		// And finally, remove matches for match types that have been turned
		// off. We needed to have all match types turned on initially so that
		// things like hashtags could be filtered out if they were really just
		// part of a URL match (for instance, as a named anchor).
		$matches = $this->removeUnwantedMatches( $matches );
		
		return $matches;
	}
	
	/**
	 * After we have found all matches, we need to remove subsequent matches
	 * that overlap with a previous match. This can happen for instance with
	 * URLs, where the url 'google.com/#link' would match '#link' as a hashtag.
	 *
	 * @private
	 * @param {Autolinker.match.Match[]} matches
	 * @return {Autolinker.match.Match[]}
	 */
	function compactMatches( $matches ) {
		// First, the matches need to be sorted in order of offset
		usort($matches, function( $a, $b ) {
			return ($a->offset - $b->offset);
		});
		
		for( $i = 0, $k = 1, $mcount = count($matches) - 1; $i < $mcount; $i++, $k++ ) {
			$match = $matches[ $i ];
			$offset = $match->getOffset();
			$matchedTextLength = strlen($match->getMatchedText());
			$endIdx = $offset + $matchedTextLength;
			
			if( $k < $mcount ) {
				// Remove subsequent matches that equal offset with current match
				if( $matches[ $k ]->getOffset() === $offset ) {
					$removeIdx = strlen($matches[ $k ]->getMatchedText()) > $matchedTextLength ? $i : $k;
					array_splice($matches, $removeIdx, 1);
				} else
				// Remove subsequent matches that overlap with the current match
				if( $matches[ $k ]->getOffset() <= $endIdx ) {
					array_splice($matches, $k, 1);
				}
			}
		}
		return $matches;
	}
	
	/**
	 * Removes matches for matchers that were turned off in the options. For
	 * example, if {@link #hashtag hashtags} were not to be matched, we'll
	 * remove them from the `matches` array here.
	 *
	 * @private
	 * @param {Autolinker.match.Match[]} matches The array of matches to remove
	 *   the unwanted matches from. Note: this array is mutated for the
	 *   removals.
	 * @return {Autolinker.match.Match[]} The mutated input `matches` array.
	 */
	function removeUnwantedMatches( $matches ) {
		
		if( !$this->hashtag ) Util::remove( $matches, function( $match ) { return $match->getType() === 'hashtag'; } );
		if( !$this->email )   Util::remove( $matches, function( $match ) { return $match->getType() === 'email'; } );
		if( !$this->phone )   Util::remove( $matches, function( $match ) { return $match->getType() === 'phone'; } );
		if( !$this->mention ) Util::remove( $matches, function( $match ) { return $match->getType() === 'mention'; } );
		if( !$this->urls['schemeMatches'] ) {
			Util::remove( $matches, function( $m ) { return $m->getType() === 'url' && $m->getUrlMatchType() === 'scheme'; } );
		}
		if( !$this->urls['wwwMatches'] ) {
			Util::remove( $matches, function( $m ) { return $m->getType() === 'url' && $m->getUrlMatchType() === 'www'; } );
		}
		if( !$this->urls['tldMatches'] ) {
			Util::remove( $matches, function( $m ) { return $m->getType() === 'url' && $m->getUrlMatchType() === 'tld'; } );
		}
		return $matches;
	}
	
	/**
	 * Parses the input `text` looking for URLs, email addresses, phone
	 * numbers, username handles, and hashtags (depending on the configuration
	 * of the Autolinker instance), and returns an array of {@link Autolinker.match.Match}
	 * objects describing those matches.
	 *
	 * This method processes a **non-HTML string**, and is used to parse and
	 * match within the text nodes of an HTML string. This method is used
	 * internally by {@link #parse}.
	 *
	 * @private
	 * @param {String} text The text to find matches within (depending on if the
	 *   {@link #urls}, {@link #email}, {@link #phone},
	 *   {@link #hashtag}, and {@link #mention} options are enabled). This must be a non-HTML string.
	 * @param {Number} [offset=0] The offset of the text node within the
	 *   original string. This is used when parsing with the {@link #parse}
	 *   method to generate correct offsets within the {@link Autolinker.match.Match}
	 *   instances, but may be omitted if calling this method publicly.
	 * @return {Autolinker.match.Match[]} The array of Matches found in the
	 *   given input `text`.
	 */
	 function parseText( $text, $offset = 0 ) {
		
		$matchers = $this->getMatchers();
		$matches = [];
		
		foreach ($matchers as $nM) {
			$textMatches = $nM->parseMatches( $text );
			
			// Correct the offset of each of the matches. They are originally
			// the offset of the match within the provided text node, but we
			// need to correct them to be relative to the original HTML input
			// string (i.e. the one provided to #parse).
			foreach ($textMatches as $txtM) {
				$txtM->setOffset( $offset + $txtM->getOffset() );
				array_push($matches, $txtM);
			}
		}
		return $matches;
	}
	
	/**
	 * Automatically links URLs, Email addresses, Phone numbers, Hashtags,
	 * and Mentions (Twitter, Instagram) found in the given chunk of HTML. Does not link
	 * URLs found within HTML tags.
	 *
	 * For instance, if given the text: `You should go to http://www.yahoo.com`,
	 * then the result will be `You should go to
	 * &lt;a href="http://www.yahoo.com"&gt;http://www.yahoo.com&lt;/a&gt;`
	 *
	 * This method finds the text around any HTML elements in the input
	 * `textOrHtml`, which will be the text that is processed. Any original HTML
	 * elements will be left as-is, as well as the text that is already wrapped
	 * in anchor (&lt;a&gt;) tags.
	 *
	 * @param {String} textOrHtml The HTML or text to autolink matches within
	 *   (depending on if the {@link #urls}, {@link #email}, {@link #phone}, {@link #hashtag}, and {@link #mention} options are enabled).
	 * @return {String} The HTML, with matches automatically linked.
	 */
	function link( $textOrHtml ) {
		if( !$textOrHtml ) { return ""; }  // handle `null` and `undefined`
		
		$matches   = $this->parse( $textOrHtml );
		$newHtml   = [];
		$lastIndex = 0;
		
		foreach ($matches as $match) {
			$offset = $match->getOffset();
			array_push($newHtml,
				substr($textOrHtml, $lastIndex, ($offset - $lastIndex)),
				$this->createMatchReturnVal($match)
			);
			$lastIndex = $offset + strlen($match->getMatchedText());
		}
		array_push($newHtml, substr($textOrHtml, $lastIndex)); // handle the text after the last match
		
		return join($newHtml, '');
	}
	
	/**
	 * Creates the return string value for a given match in the input string.
	 *
	 * This method handles the {@link #replaceFn}, if one was provided.
	 *
	 * @private
	 * @param {Autolinker.match.Match} match The Match object that represents
	 *   the match.
	 * @return {String} The string that the `match` should be replaced with.
	 *   This is usually the anchor tag string, but may be the `matchStr` itself
	 *   if the match is not to be replaced.
	 */
	function createMatchReturnVal( $match ) {
		// Handle a custom `replaceFn` being provided
		$replaceFnResult;
		if( !!$this->replaceFn ) {
			$replaceFnResult = $this->replaceFn( $match );  // Autolinker instance is the context
		}
		if( gettype($replaceFnResult) === 'string' ) {
			return $replaceFnResult;  // `replaceFn` returned a string, use that
			
		} else if( $replaceFnResult === false ) {
			return $match->getMatchedText();  // no replacement for the match
			
		} else if( $replaceFnResult instanceof HtmlTag ) {
			return $replaceFnResult->toAnchorString();
			
		} else {  // replaceFnResult === true, or no/unknown return value from function
			// Perform Autolinker's default anchor tag generation
			$anchorTag = $match->buildTag();  // returns an Autolinker.HtmlTag instance
			
			return $anchorTag->toAnchorString();
		}
	}
	
	/**
	 * Lazily instantiates and returns the {@link #htmlParser} instance for this
	 * Autolinker instance.
	 *
	 * @protected
	 * @return {Autolinker.htmlParser.HtmlParser}
	 */
	 function getHtmlParser() {
		$htmlParser = $this->htmlParser;
		
		if( !$htmlParser ) {
			$htmlParser = $this->htmlParser = new HtmlParser();
		}
		return $htmlParser;
	}
	
	/**
	 * Lazily instantiates and returns the {@link Matcher}
	 * instances for this Autolinker instance.
	 *
	 * @protected
	 * @return {Autolinker.matcher.Matcher[]}
	 */
	function getMatchers() {
		
		if( !($matchers = $this->matchers) ) {
			
			$tagBuilder = $this->getTagBuilder();
			$matchers   = $this->matchers  = Array(
				new HashtagMatch([ 'tagBuilder' => $tagBuilder, 'serviceName' => $this->hashtag ]),
				new EmailMatch(  [ 'tagBuilder' => $tagBuilder ]),
				new PhoneMatch(  [ 'tagBuilder' => $tagBuilder ]),
				new MentionMatch([ 'tagBuilder' => $tagBuilder, 'serviceName' => $this->mention ]),
				new UrlMatch(    [ 'tagBuilder' => $tagBuilder, 'stripPrefix' => $this->stripPrefix,
				   'stripTrailingSlash'    => $this->stripTrailingSlash,
				   'decodePercentEncoding' => $this->decodePercentEncoding ])
			);
		}
		return $matchers;
	}
	
	/**
	 * Returns the {@link #tagBuilder} instance for this Autolinker instance, lazily instantiating it
	 * if it does not yet exist.
	 *
	 * This method may be used in a {@link #replaceFn} to generate the {@link Autolinker.HtmlTag HtmlTag} instance that
	 * Autolinker would normally generate, and then allow for modifications before returning it. For example:
	 *
	 *     var html = Autolinker.link( "Test google.com", {
	 *         replaceFn : function( match ) {
	 *             var tag = match.buildTag();  // returns an {@link Autolinker.HtmlTag} instance
	 *             tag.setAttr( 'rel', 'nofollow' );
	 *
	 *             return tag;
	 *         }
	 *     } );
	 *
	 *     // generated html:
	 *     //   Test <a href="http://google.com" target="_blank" rel="nofollow">google.com</a>
	 *
	 * @return {Autolinker.AnchorTagBuilder}
	 */
	function getTagBuilder() {
		
		if( !($tagBuilder = $this->tagBuilder) ) {
			
			$tagBuilder = $this->tagBuilder = new AnchorTagBuilder([
				'newWindow' => $this->newWindow,
				'truncate'  => $this->truncate,
				'className' => $this->className
			]);
		}
		return $tagBuilder;
	}
	
	static function quickLink($textOrHtml, $options = []) {
		$autolinker = new Autolinker($options);
		return $autolinker->link($textOrHtml);
	}
	static function quickParse($textOrHtml, $options = []) {
		$autolinker = new Autolinker($options);
		return $autolinker->parse($textOrHtml);
	}
}

?>
