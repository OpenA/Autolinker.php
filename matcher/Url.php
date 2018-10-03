<?php
/**
 * Matcher to find URL matches in an input string.
 *
 * See this class's superclass ({@link Matcher}) for more details.
 */
class UrlMatch extends Matcher {

	/**
	 * @cfg {Object} stripPrefix (required)
	 *
	 * The Object form of {@link Autolinker#cfg-stripPrefix}.
	 */
	protected $stripPrefix;

	/**
	 * @cfg {Boolean} stripTrailingSlash (required)
	 * @inheritdoc Autolinker#stripTrailingSlash
	 */
	protected $stripTrailingSlash;

	/**
	 * @cfg {Boolean} decodePercentEncoding (required)
	 * @inheritdoc Autolinker#decodePercentEncoding
	 */
	protected $decodePercentEncoding;

	/**
	 * @property {RegExp} matcherRegex
	 *
	 * The regular expression to match URLs with an optional scheme, port
	 * number, path, query string, and hash anchor.
	 *
	 * Example matches:
	 *
	 *     http://google.com
	 *     www.google.com
	 *     google.com/path/to/file?q1=1&q2=2#myAnchor
	 *
	 *
	 * This regular expression will have the following capturing groups:
	 *
	 * 1.  Group that matches a scheme-prefixed URL (i.e. 'http://google.com').
	 *     This is used to match scheme URLs with just a single word, such as
	 *     'http://localhost', where we won't double check that the domain name
	 *     has at least one dot ('.') in it.
	 * 2.  Group that matches a 'www.' prefixed URL. This is only matched if the
	 *     'www.' text was not prefixed by a scheme (i.e.: not prefixed by
	 *     'http://', 'ftp:', etc.)
	 * 3.  A protocol-relative ('//') match for the case of a 'www.' prefixed
	 *     URL. Will be an empty string if it is not a protocol-relative match.
	 *     We need to know the character before the '//' in order to determine
	 *     if it is a valid match or the // was in a string we don't want to
	 *     auto-link.
	 * 4.  Group that matches a known TLD (top level domain), when a scheme
	 *     or 'www.'-prefixed domain is not matched.
	 * 5.  A protocol-relative ('//') match for the case of a known TLD prefixed
	 *     URL. Will be an empty string if it is not a protocol-relative match.
	 *     See #3 for more info.
	 */
	static $matcherRegex;

	/**
	 * A regular expression to use to check the character before a protocol-relative
	 * URL match. We don't want to match a protocol-relative URL if it is part
	 * of another word.
	 *
	 * For example, we want to match something like "Go to: //google.com",
	 * but we don't want to match something like "abc//google.com"
	 *
	 * This regular expression is used to test the character before the '//'.
	 *
	 * @type {RegExp} wordCharRegExp
	 */
	static $wordCharRegExp;

	/**
	 * @param {Object} cfg The configuration properties for the Match instance,
	 *   specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct($cfg);
		
		$this->assign( $cfg );
		
		// @if DEBUG
		$this->requireStrict('stripPrefix', 'stripTrailingSlash');
		// @endif
	}

	/**
	 * @inheritdoc
	 */
	function parseMatches( $text ) {
		$stripPrefix           = $this->stripPrefix;
		$stripTrailingSlash    = $this->stripTrailingSlash;
		$decodePercentEncoding = $this->decodePercentEncoding;
		$tagBuilder            = $this->tagBuilder;
		$matches               = [];
		
		if ( ($len = preg_match_all( self::$matcherRegex, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchStr                 = $match[ $i ][ 0 ][ 0 ];
				$schemeUrlMatch           = $match[ $i ][ 1 ][ 0 ];
				$wwwUrlMatch              = $match[ $i ][ 4 ][ 0 ];
				$wwwProtocolRelativeMatch = $match[ $i ][ 5 ][ 0 ];
				//$tldUrlMatch            = $match[ $i ][ 8 ][ 0 ];  -- not needed at the moment
				$tldProtocolRelativeMatch = $match[ $i ][ 9 ][ 0 ];
				$offset                   = $match[ $i ][ 0 ][ 1 ];
				$protocolRelativeMatch    = $wwwProtocolRelativeMatch || $tldProtocolRelativeMatch;
				$prevChar                 = $text{$offset - 1};
				
				if( !UrlMatchValidator::isValid( $matchStr, $schemeUrlMatch )) {
					continue;
				}
				
				// If the match is preceded by an '@' character, then it is either
				// an email address or a username. Skip these types of matches.
				if( $offset > 0 && $prevChar === '@' ) {
					continue;
				}
				
				// If it's a protocol-relative '//' match, but the character before the '//'
				// was a word character (i.e. a letter/number), then we found the '//' in the
				// middle of another word (such as "asdf//asdf.com"). In this case, skip the
				// match.
				if( $offset > 0 && $protocolRelativeMatch && preg_match( self::$wordCharRegExp, $prevChar) ) {
					continue;
				}
				
				if( preg_match('/\?$/', $matchStr) ) {
					$matchStr = substr($matchStr, 0, strlen($matchStr) - 1);
				}
				
				// Handle a closing parenthesis at the end of the match, and exclude
				// it if there is not a matching open parenthesis in the match
				// itself.
				if( $this->matchHasUnbalancedClosingParen( $matchStr ) ) {
					$matchStr = substr( $matchStr, 0, strlen($matchStr) - 1 );  // remove the trailing ")"
				} else {
					// Handle an invalid character after the TLD
					$pos = $this->matchHasInvalidCharAfterTld( $matchStr, $schemeUrlMatch );
					if( $pos > -1 ) {
						$matchStr = substr( $matchStr, 0, $pos ); // remove the trailing invalid chars
					}
				}
				
				$urlMatchType = ($protocolUrlMatch = !!$schemeUrlMatch) ? 'scheme' : ( $wwwUrlMatch ? 'www' : 'tld' );
				
				array_push($matches, new Url([
					'tagBuilder'            => $tagBuilder,
					'matchedText'           => $matchStr,
					'offset'                => $offset,
					'urlMatchType'          => $urlMatchType,
					'url'                   => $matchStr,
					'protocolUrlMatch'      => $protocolUrlMatch,
					'protocolRelativeMatch' => !!$protocolRelativeMatch,
					'stripPrefix'           => $stripPrefix,
					'stripTrailingSlash'    => $stripTrailingSlash,
					'decodePercentEncoding' => $decodePercentEncoding
				]));
			}
		}
		
		return $matches;
	}

	// ---------------------------------------
	// Utility Functionality

	/**
	 * Determines if a match found has an unmatched closing parenthesis. If so,
	 * this parenthesis will be removed from the match itself, and appended
	 * after the generated anchor tag.
	 *
	 * A match may have an extra closing parenthesis at the end of the match
	 * because the regular expression must include parenthesis for URLs such as
	 * "wikipedia.com/something_(disambiguation)", which should be auto-linked.
	 *
	 * However, an extra parenthesis *will* be included when the URL itself is
	 * wrapped in parenthesis, such as in the case of "(wikipedia.com/something_(disambiguation))".
	 * In this case, the last closing parenthesis should *not* be part of the
	 * URL itself, and this method will return `true`.
	 *
	 * @param {String} matchStr The full match string from the {@link #matcherRegex}.
	 * @return {Boolean} `true` if there is an unbalanced closing parenthesis at
	 *   the end of the `matchStr`, `false` otherwise.
	 */
	private function matchHasUnbalancedClosingParen( $matchStr ) {
		$lastChar = $matchStr{ strlen($matchStr) - 1 };
		
		if( $lastChar === ')' ) {
			$numOpenParens  = preg_match_all('/\(/', $matchStr, $openParensMatch );
			$numCloseParens = preg_match_all('/\)/', $matchStr, $closeParensMatch);
			
			if( $numOpenParens < $numCloseParens ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if there's an invalid character after the TLD in a URL. Valid
	 * characters after TLD are ':/?#'. Exclude scheme matched URLs from this
	 * check.
	 *
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} schemeUrlMatch The match URL string for a scheme
	 *   match. Ex: 'http://yahoo.com'. This is used to match something like
	 *   'http://localhost', where we won't double check that the domain name
	 *   has at least one '.' in it.
	 * @return {Number} the position where the invalid character was found. If
	 *   no such character was found, returns -1
	 */
	private function matchHasInvalidCharAfterTld( $urlMatch, $schemeUrlMatch ) {
		if ( $urlMatch ) {
			
			$offset = 0;
			
			if ( $schemeUrlMatch ) {
				$offset   = strpos($urlMatch, ':');
				$urlMatch = substr($urlMatch, $offset);
			}
			
			$alphaNumeric = RegexLib::$alphaNumericCharsStr;
			
			if ( preg_match("/^((.?\/\/)?[-.$alphaNumeric]*[-$alphaNumeric]\.[-$alphaNumeric]+)/u", $urlMatch, $res) ) {
				$offset  += ($slen = strlen($res[1]));
				$urlMatch = substr($urlMatch, $slen);
				if (preg_match('/^[^-.A-Za-z0-9:\/?#]/', $urlMatch)) {
					return $offset;
				}
			}
		}
		return -1;
	}
};

UrlMatch::$wordCharRegExp = '~['. RegexLib::$alphaNumericCharsStr .']~u';
UrlMatch::$matcherRegex = RegexLib::getUrlMatch();
