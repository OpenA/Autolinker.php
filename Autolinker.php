<?php
set_include_path( __DIR__ );

include 'Util.php';
include 'RegexLib.php';
include 'HtmlTag.php';
include 'AnchorTagBuilder.php';

include 'matcher/UrlMatchValidator.php';
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

	/**
	 * @property {String} version (readonly)
	 *
	 * The Autolinker version number in the form major.minor.patch
	 *
	 * Ex: 0.25.1
	 */
	const version = '1.7.1';

	/**
	 * @cfg {Boolean/Object} [urls]
	 *
	 * `true` if URLs should be automatically linked, `false` if they should not
	 * be. Defaults to `true`.
	 *
	 * Examples:
	 *
	 *     urls: true
	 *
	 *     // or
	 *
	 *     urls: {
	 *         schemeMatches : true,
	 *         wwwMatches    : true,
	 *         tldMatches    : true
	 *     }
	 *
	 * As shown above, this option also accepts an Object form with 3 properties
	 * to allow for more customization of what exactly gets linked. All default
	 * to `true`:
	 *
	 * @cfg {Boolean} [urls.schemeMatches] `true` to match URLs found prefixed
	 *   with a scheme, i.e. `http://google.com`, or `other+scheme://google.com`,
	 *   `false` to prevent these types of matches.
	 * @cfg {Boolean} [urls.wwwMatches] `true` to match urls found prefixed with
	 *   `'www.'`, i.e. `www.google.com`. `false` to prevent these types of
	 *   matches. Note that if the URL had a prefixed scheme, and
	 *   `schemeMatches` is true, it will still be linked.
	 * @cfg {Boolean} [urls.tldMatches] `true` to match URLs with known top
	 *   level domains (.com, .net, etc.) that are not prefixed with a scheme or
	 *   `'www.'`. This option attempts to match anything that looks like a URL
	 *   in the given text. Ex: `google.com`, `asdf.org/?page=1`, etc. `false`
	 *   to prevent these types of matches.
	 */
	protected $urls;

	/**
	 * @cfg {Boolean} [email=true]
	 *
	 * `true` if email addresses should be automatically linked, `false` if they
	 * should not be.
	 */
	protected $email = true;

	/**
	 * @cfg {Boolean} [phone=true]
	 *
	 * `true` if Phone numbers ("(555)555-5555") should be automatically linked,
	 * `false` if they should not be.
	 */
	protected $phone = true;

	/**
	 * @cfg {Boolean/String} [hashtag=false]
	 *
	 * A string for the service name to have hashtags (ex: "#myHashtag")
	 * auto-linked to. The currently-supported values are:
	 *
	 * - 'twitter'
	 * - 'facebook'
	 * - 'instagram'
	 *
	 * Pass `false` to skip auto-linking of hashtags.
	 */
	protected $hashtag = false;

	/**
	 * @cfg {String/Boolean} [mention=false]
	 *
	 * A string for the service name to have mentions (ex: "@myuser")
	 * auto-linked to. The currently supported values are:
	 *
	 * - 'twitter'
	 * - 'instagram'
	 *
	 * Defaults to `false` to skip auto-linking of mentions.
	 */
	protected $mention = false;

	/**
	 * @cfg {Boolean} [newWindow=true]
	 *
	 * `true` if the links should open in a new window, `false` otherwise.
	 */
	protected $newWindow = false;

	/**
	 * @cfg {Boolean/Object} [stripPrefix]
	 *
	 * `true` if 'http://' (or 'https://') and/or the 'www.' should be stripped
	 * from the beginning of URL links' text, `false` otherwise. Defaults to
	 * `true`.
	 *
	 * Examples:
	 *
	 *     stripPrefix: true
	 *
	 *     // or
	 *
	 *     stripPrefix: {
	 *         scheme : true,
	 *         www    : true
	 *     }
	 *
	 * As shown above, this option also accepts an Object form with 2 properties
	 * to allow for more customization of what exactly is prevented from being
	 * displayed. Both default to `true`:
	 *
	 * @cfg {Boolean} [stripPrefix.scheme] `true` to prevent the scheme part of
	 *   a URL match from being displayed to the user. Example:
	 *   `'http://google.com'` will be displayed as `'google.com'`. `false` to
	 *   not strip the scheme. NOTE: Only an `'http://'` or `'https://'` scheme
	 *   will be removed, so as not to remove a potentially dangerous scheme
	 *   (such as `'file://'` or `'javascript:'`)
	 * @cfg {Boolean} [stripPrefix.www] www (Boolean): `true` to prevent the
	 *   `'www.'` part of a URL match from being displayed to the user. Ex:
	 *   `'www.google.com'` will be displayed as `'google.com'`. `false` to not
	 *   strip the `'www'`.
	 */
	protected $stripPrefix;

	/**
	 * @cfg {Boolean} [stripTrailingSlash=true]
	 *
	 * `true` to remove the trailing slash from URL matches, `false` to keep
	 *  the trailing slash.
	 *
	 *  Example when `true`: `http://google.com/` will be displayed as
	 *  `http://google.com`.
	 */
	protected $stripTrailingSlash = true;

	/**
	 * @cfg {Boolean} [decodePercentEncoding=true]
	 *
	 * `true` to decode percent-encoded characters in URL matches, `false` to keep
	 *  the percent-encoded characters.
	 *
	 *  Example when `true`: `https://en.wikipedia.org/wiki/San_Jos%C3%A9` will
	 *  be displayed as `https://en.wikipedia.org/wiki/San_José`.
	 */
	protected $decodePercentEncoding = true;

	/**
	 * @cfg {Number/Object} [truncate=0]
	 *
	 * ## Number Form
	 *
	 * A number for how many characters matched text should be truncated to
	 * inside the text of a link. If the matched text is over this number of
	 * characters, it will be truncated to this length by adding a two period
	 * ellipsis ('..') to the end of the string.
	 *
	 * For example: A url like 'http://www.yahoo.com/some/long/path/to/a/file'
	 * truncated to 25 characters might look something like this:
	 * 'yahoo.com/some/long/pat..'
	 *
	 * Example Usage:
	 *
	 *     truncate: 25
	 *
	 *
	 *  Defaults to `0` for "no truncation."
	 *
	 *
	 * ## Object Form
	 *
	 * An Object may also be provided with two properties: `length` (Number) and
	 * `location` (String). `location` may be one of the following: 'end'
	 * (default), 'middle', or 'smart'.
	 *
	 * Example Usage:
	 *
	 *     truncate: { length: 25, location: 'middle' }
	 *
	 * @cfg {Number} [truncate.length=0] How many characters to allow before
	 *   truncation will occur. Defaults to `0` for "no truncation."
	 * @cfg {"end"/"middle"/"smart"} [truncate.location="end"]
	 *
	 * - 'end' (default): will truncate up to the number of characters, and then
	 *   add an ellipsis at the end. Ex: 'yahoo.com/some/long/pat..'
	 * - 'middle': will truncate and add the ellipsis in the middle. Ex:
	 *   'yahoo.com/s..th/to/a/file'
	 * - 'smart': for URLs where the algorithm attempts to strip out unnecessary
	 *   parts first (such as the 'www.', then URL scheme, hash, etc.),
	 *   attempting to make the URL human-readable before looking for a good
	 *   point to insert the ellipsis if it is still too long. Ex:
	 *   'yahoo.com/some..to/a/file'. For more details, see
	 *   {@link Autolinker.truncate.TruncateSmart}.
	 */
	protected $truncate = 0;

	/**
	 * @cfg {String} className
	 *
	 * A CSS class name to add to the generated links. This class will be added
	 * to all links, as well as this class plus match suffixes for styling
	 * url/email/phone/hashtag/mention links differently.
	 *
	 * For example, if this config is provided as "myLink", then:
	 *
	 * - URL links will have the CSS classes: "myLink myLink-url"
	 * - Email links will have the CSS classes: "myLink myLink-email", and
	 * - Phone links will have the CSS classes: "myLink myLink-phone"
	 * - Hashtag links will have the CSS classes: "myLink myLink-hashtag"
	 * - Mention links will have the CSS classes: "myLink myLink-mention myLink-[type]"
	 *   where [type] is either "instagram" or "twitter"
	 */
	protected $className;

	/**
	 * @cfg {Function} replaceFn
	 *
	 * A function to individually process each match found in the input string.
	 *
	 * See the class's description for usage.
	 *
	 * The `replaceFn` can be called with a different context object (`this`
	 * reference) using the {@link #context} cfg.
	 *
	 * This function is called with the following parameter:
	 *
	 * @cfg {Autolinker.match.Match} replaceFn.match The Match instance which
	 *   can be used to retrieve information about the match that the `replaceFn`
	 *   is currently processing. See {@link Autolinker.match.Match} subclasses
	 *   for details.
	 */
	protected $replaceFn;

	/**
	 * @cfg {Object} context
	 *
	 * The context object (`this` reference) to call the `replaceFn` with.
	 *
	 * Defaults to this Autolinker instance.
	 */
	protected $context;

	/**
	 * @property {Autolinker.htmlParser.HtmlParser} htmlParser
	 *
	 * The HtmlParser instance used to skip over HTML tags, while finding text
	 * nodes to process. This is lazily instantiated in the {@link #getHtmlParser}
	 * method.
	 */
	private $htmlParser;

	/**
	 * @property {Autolinker.matcher.Matcher[]} matchers
	 *
	 * The {@link Autolinker.matcher.Matcher} instances for this Autolinker
	 * instance.
	 *
	 * This is lazily created in {@link #getMatchers}.
	 */
	private $matchers;

	/**
	 * @property {Autolinker.AnchorTagBuilder} tagBuilder
	 *
	 * The AnchorTagBuilder instance used to build match replacement anchor tags.
	 * Note: this is lazily instantiated in the {@link #getTagBuilder} method.
	 */
	private $tagBuilder;

	function __construct($cfg = []) {
		
		$this->urls = $this->normalizeUrlsCfg( $cfg['urls'] );
		$this->email = gettype($cfg['email']) === 'boolean' ? $cfg['email'] : true;
		$this->phone = gettype($cfg['phone']) === 'boolean' ? $cfg['phone'] : true;
		$this->hashtag = $cfg['hashtag'] || false;
		$this->mention = $cfg['mention'] || false;
		$this->newWindow = gettype($cfg['newWindow']) === 'boolean' ? $cfg['newWindow'] : true;
		$this->stripPrefix = $this->normalizeStripPrefixCfg( $cfg['stripPrefix'] );
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
		
		$this->truncate  = $this->normalizeTruncateCfg( $cfg['truncate'] );
		$this->className = $cfg['className'] ?: '';
		$this->replaceFn = $cfg['replaceFn'] ?: null;
		$this->context   = $cfg['context']   ?: $this;
	}

	/**
	 * Parses the input `textOrHtml` looking for URLs, email addresses, phone
	 * numbers, username handles, and hashtags (depending on the configuration
	 * of the Autolinker instance), and returns an array of {@link Autolinker.match.Match}
	 * objects describing those matches (without making any replacements).
	 *
	 * This method is used by the {@link #link} method, but can also be used to
	 * simply do parsing of the input in order to discover what kinds of links
	 * there are and how many.
	 *
	 * Example usage:
	 *
	 *     var autolinker = new Autolinker( {
	 *         urls: true,
	 *         email: true
	 *     } );
	 *
	 *     var matches = autolinker.parse( "Hello google.com, I am asdf@asdf.com" );
	 *
	 *     console.log( matches.length );           // 2
	 *     console.log( matches[ 0 ].getType() );   // 'url'
	 *     console.log( matches[ 0 ].getUrl() );    // 'google.com'
	 *     console.log( matches[ 1 ].getType() );   // 'email'
	 *     console.log( matches[ 1 ].getEmail() );  // 'asdf@asdf.com'
	 *
	 * @param {String} textOrHtml The HTML or text to find matches within
	 *   (depending on if the {@link #urls}, {@link #email}, {@link #phone},
	 *   {@link #hashtag}, and {@link #mention} options are enabled).
	 * @return {Autolinker.match.Match[]} The array of Matches found in the
	 *   given input `textOrHtml`.
	 */
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
	 * @param {Autolinker.match.Match[]} matches
	 * @return {Autolinker.match.Match[]}
	 */
	private function compactMatches( $matches ) {
		// First, the matches need to be sorted in order of offset
		usort($matches, function( $a, $b ) {
			return ($a->getOffset() - $b->getOffset());
		});
		
		for( $i = 0, $k = 1; $i < count($matches); $i++, $k++ ) {
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
	 * @param {Autolinker.match.Match[]} matches The array of matches to remove
	 *   the unwanted matches from. Note: this array is mutated for the
	 *   removals.
	 * @return {Autolinker.match.Match[]} The mutated input `matches` array.
	 */
	private function removeUnwantedMatches( $matches ) {
		
		for ($i = count($matches) - 1; $i >= 0; $i--) {
			$type = $matches[ $i ]->getType();
			
			if (
				$type === 'url' && $this->{ $type }[ $matches[ $i ]->getUrlMatchType() ] === false || $this->{ $type } === false
			) {
				array_splice($matches, $i, 1);
			}
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
	private function parseText( $text, $offset = 0 ) {
		
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
				substr($textOrHtml, $lastIndex, $offset - $lastIndex),
				$this->createMatchReturnVal($match)
			);
			$lastIndex = $offset + strlen($match->getMatchedText());
		}
		array_push($newHtml, substr($textOrHtml, $lastIndex)); // handle the text after the last match
		
		return join('', $newHtml);
	}

	/**
	 * Creates the return string value for a given match in the input string.
	 *
	 * This method handles the {@link #replaceFn}, if one was provided.
	 *
	 * @param {Autolinker.match.Match} match The Match object that represents
	 *   the match.
	 * @return {String} The string that the `match` should be replaced with.
	 *   This is usually the anchor tag string, but may be the `matchStr` itself
	 *   if the match is not to be replaced.
	 */
	private function createMatchReturnVal( $match ) {
		// Handle a custom `replaceFn` being provided
		$replaceFnResult;
		if( !!$this->replaceFn ) {
			$replaceFnResult = call_user_func($this->replaceFn, $match);  // Autolinker instance is the context
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
	 * @return {Autolinker.htmlParser.HtmlParser}
	 */
	private function getHtmlParser() {
		return $this->htmlParser ?: ($this->htmlParser = new HtmlParser());
	}

	/**
	 * Lazily instantiates and returns the {@link Matcher}
	 * instances for this Autolinker instance.
	 *
	 * @return {Autolinker.matcher.Matcher[]}
	 */
	private function getMatchers() {
		
		if( !($matchers = $this->matchers) ) {
			
			$tagBuilder = $this->getTagBuilder();
			$matchers   = $this->matchers = Array(
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
	private function getTagBuilder() {
		
		return $this->tagBuilder ?: (
		
			$this->tagBuilder = new AnchorTagBuilder([
				'newWindow' => $this->newWindow,
				'truncate'  => $this->truncate,
				'className' => $this->className
			])
		);
	}

	/**
	 * Normalizes the {@link #urls} config into an Object with 3 properties:
	 * `schemeMatches`, `wwwMatches`, and `tldMatches`, all Booleans.
	 *
	 * See {@link #urls} config for details.
	 *
	 * @param {Boolean/Object} urls
	 * @return {Object}
	 */
	private function normalizeUrlsCfg( $urls = true ) { // default to `true`
		if ( gettype($urls) === 'boolean' ) {
			return Array( 'schemeMatches' => $urls, 'wwwMatches' => $urls, 'tldMatches' => $urls );
		} else {  // object form
			return Array(
				'schemeMatches' => (gettype($urls['schemeMatches']) === 'boolean' ? $urls['schemeMatches'] : true),
				'wwwMatches'    => (gettype($urls['wwwMatches'])    === 'boolean' ? $urls['wwwMatches']    : true),
				'tldMatches'    => (gettype($urls['tldMatches'])    === 'boolean' ? $urls['tldMatches']    : true)
			);
		}
	}

	/**
	 * Normalizes the {@link #stripPrefix} config into an Object with 2
	 * properties: `scheme`, and `www` - both Booleans.
	 *
	 * See {@link #stripPrefix} config for details.
	 *
	 * @param {Boolean/Object} stripPrefix
	 * @return {Object}
	 */
	private function normalizeStripPrefixCfg( $stripPrefix = true ) { // default to `true`
		if ( gettype($stripPrefix) === 'boolean' ) {
			return Array( 'scheme' => $stripPrefix, 'www' => $stripPrefix);
		} else {  // object form
			return Array(
				'scheme' => (gettype($stripPrefix['scheme']) === 'boolean' ? $stripPrefix['scheme'] : true),
				'www'    => (gettype($stripPrefix['www'])    === 'boolean' ? $stripPrefix['www']    : false)
			);
		}
	}

	/**
	 * Normalizes the {@link #truncate} config into an Object with 2 properties:
	 * `length` (Number), and `location` (String).
	 *
	 * See {@link #truncate} config for details.
	 *
	 * @param {Number/Object} truncate
	 * @return {Object}
	 */
	private function normalizeTruncateCfg( $truncate ) {
		
		$Cfg = Array(
			'length' => INF,
			'location' => 'end'
		);
		
		if ( gettype($truncate) === 'number' ) {
			$Cfg['length'] = $truncate;
			
		} else {
			// object, or undefined/null
			if (gettype($truncate['length']) === 'number')
				$Cfg['length'] = $truncate['length'];
			if (preg_match('/end|middle|smart/', $truncate['location']))
				$Cfg['location'] = $truncate['location'];
		}
		return $truncate;
	}

	/**
	 * Automatically links URLs, Email addresses, Phone Numbers, Twitter handles,
	 * Hashtags, and Mentions found in the given chunk of HTML. Does not link URLs
	 * found within HTML tags.
	 *
	 * For instance, if given the text: `You should go to http://www.yahoo.com`,
	 * then the result will be `You should go to &lt;a href="http://www.yahoo.com"&gt;http://www.yahoo.com&lt;/a&gt;`
	 *
	 * Example:
	 *
	 *     var linkedText = Autolinker.link( "Go to google.com", { newWindow: false } );
	 *     // Produces: "Go to <a href="http://google.com">google.com</a>"
	 *
	 * @param {String} textOrHtml The HTML or text to find matches within (depending
	 *   on if the {@link #urls}, {@link #email}, {@link #phone}, {@link #mention},
	 *   {@link #hashtag}, and {@link #mention} options are enabled).
	 * @param {Object} [options] Any of the configuration options for the Autolinker
	 *   class, specified in an Object (map). See the class description for an
	 *   example call.
	 * @return {String} The HTML text, with matches automatically linked.
	 */
	static function quickLink($textOrHtml, $options = []) {
		$autolinker = new Autolinker($options);
		return $autolinker->link($textOrHtml);
	}

	/**
	 * Parses the input `textOrHtml` looking for URLs, email addresses, phone
	 * numbers, username handles, and hashtags (depending on the configuration
	 * of the Autolinker instance), and returns an array of {@link Autolinker.match.Match}
	 * objects describing those matches (without making any replacements).
	 *
	 * Note that if parsing multiple pieces of text, it is slightly more efficient
	 * to create an Autolinker instance, and use the instance-level {@link #parse}
	 * method.
	 *
	 * Example:
	 *
	 *     var matches = Autolinker::quickParse( "Hello google.com, I am asdf@asdf.com", [
	 *         'urls' => true,
	 *         'email' => true
	 *     ]);
	 *
	 *     echo count( matches.length );     // 2
	 *     echo matches[ 0 ]->getType() );   // 'url'
	 *     echo matches[ 0 ]->getUrl() );    // 'google.com'
	 *     echo matches[ 1 ]->getType() );   // 'email'
	 *     echo matches[ 1 ]->getEmail() );  // 'asdf@asdf.com'
	 *
	 * @param {String} textOrHtml The HTML or text to find matches within
	 *   (depending on if the {@link #urls}, {@link #email}, {@link #phone},
	 *   {@link #hashtag}, and {@link #mention} options are enabled).
	 * @param {Object} [options] Any of the configuration options for the Autolinker
	 *   class, specified in an Object (map). See the class description for an
	 *   example call.
	 * @return {Autolinker.match.Match[]} The array of Matches found in the
	 *   given input `textOrHtml`.
	 */
	static function quickParse($textOrHtml, $options = []) {
		$autolinker = new Autolinker($options);
		return $autolinker->parse($textOrHtml);
	}
}
