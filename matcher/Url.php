<?php
/**
 * @class UrlMatch
 * @extends Matcher
 *
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
	var $stripPrefix;
	
	/**
	 * @cfg {Boolean} stripTrailingSlash (required)
	 * @inheritdoc Autolinker#stripTrailingSlash
	 */
	var $stripTrailingSlash;
	
	/**
	 * @cfg {Boolean} decodePercentEncoding (required)
	 * @inheritdoc Autolinker#decodePercentEncoding
	 */
	var $decodePercentEncoding;

	/**
	 * @static
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
	 * @static
	 * @type {RegExp} wordCharRegExp
	 */
	static $wordCharRegExp;
	
	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match instance,
	 *   specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct($cfg);
		
		// @if DEBUG
		if( $cfg['stripPrefix']        === null ) throw new Exception( '`stripPrefix` cfg required' );
		if( $cfg['stripTrailingSlash'] === null ) throw new Exception( '`stripTrailingSlash` cfg required' );
		// @endif
		
		$this->stripPrefix           = $cfg['stripPrefix'];
		$this->stripTrailingSlash    = $cfg['stripTrailingSlash'];
		$this->decodePercentEncoding = $cfg['decodePercentEncoding'];
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
		
		if ( ($len = preg_match_all( static::$matcherRegex, $text, $match )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchStr              = $match[ 0 ][ $i ];
				$schemeUrlMatch        = $match[ 1 ][ $i ];
				$tldUrlMatch           = $match[ 4 ][ $i ];
				$protocolRelativeMatch = $match[ 5 ][ $i ];
				$urlMatchType          = $match[ 6 ][ $i ];
				$offset                = strpos($text, $matchStr);
				$prevChar              = $text{$offset - 1};
				
				if( !static::isValid( $matchStr, $schemeUrlMatch )) {
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
				if( $offset > 0 && !!$protocolRelativeMatch && preg_match( static::$wordCharRegExp, $prevChar) ) {
					continue;
				}
				
				if( preg_match('/\?$/', $matchStr) ) {
					$matchStr = substr($matchStr, 0, strlen($matchStr) - 1);
				}
				
				// Handle a closing parenthesis at the end of the match, and exclude
				// it if there is not a matching open parenthesis in the match
				// itself.
				if( static::matchHasUnbalancedClosingParen( $matchStr ) ) {
					$matchStr = substr( $matchStr, 0, strlen($matchStr) - 1 );  // remove the trailing ")"
				} else {
					// Handle an invalid character after the TLD
					$pos = static::matchHasInvalidCharAfterTld( $matchStr, $schemeUrlMatch );
					if( $pos > -1 ) {
						$matchStr = substr( $matchStr, 0, $pos ); // remove the trailing invalid chars
					}
				}
				
				$urlMatchType = ($protocolUrlMatch = !!$schemeUrlMatch) ? 'scheme' : ($urlMatchType == 'www' ? 'www' : 'tld');
				
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
	 * @private
	 * @param {String} matchStr The full match string from the {@link #matcherRegex}.
	 * @return {Boolean} `true` if there is an unbalanced closing parenthesis at
	 *   the end of the `matchStr`, `false` otherwise.
	 */
	static function matchHasUnbalancedClosingParen( $matchStr ) {
		$lastChar = $matchStr{ strlen($matchStr) - 1 };
		
		if( $lastChar === ')' ) {
			$numOpenParens  = ( preg_match('/\(/', $matchStr, $openParensMatch ) && count( $openParensMatch )) || 0;
			$numCloseParens = ( preg_match('/\)/', $matchStr, $closeParensMatch) && count( $closeParensMatch)) || 0;
			
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
	 * @private
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} schemeUrlMatch The match URL string for a scheme
	 *   match. Ex: 'http://yahoo.com'. This is used to match something like
	 *   'http://localhost', where we won't double check that the domain name
	 *   has at least one '.' in it.
	 * @return {Number} the position where the invalid character was found. If
	 *   no such character was found, returns -1
	 */
	static function matchHasInvalidCharAfterTld( $urlMatch, $schemeUrlMatch ) {
		if ( !$urlMatch ) {
			return -1;
		}
		
		$offset = 0;
		if ( $schemeUrlMatch ) {
			$offset   = strpos($urlMatch, ':');
			$urlMatch = substr($urlMatch, $offset);
		}
		
		$alphaNumeric = RegexLib::$alphaNumericCharsStr;
		
		if ( !preg_match("/^((.?\\/\\/)?[-.$alphaNumeric]*[-$alphaNumeric]\\.[-$alphaNumeric]+)/", $urlMatch, $res) ) {
			return -1;
		}
		
		$offset  += ($slen = strlen($res[1]));
		$urlMatch = substr($urlMatch, $slen);
		if (preg_match('/^[^-.A-Za-z0-9:\/?#]/', $urlMatch)) {
			return $offset;
		}
		return -1;
	}
	
	/**
	 * Regex to test for a full protocol, with the two trailing slashes. Ex: 'http://'
	 *
	 * @static
	 * @property {RegExp} hasFullProtocolRegex
	 */
	static $hasFullProtocolRegex = "^[A-Za-z][-.+A-Za-z0-9]*:\\/\\/";

	/**
	 * Regex to find the URI scheme, such as 'mailto:'.
	 *
	 * This is used to filter out 'javascript:' and 'vbscript:' schemes.
	 *
	 * @static
	 * @property {RegExp} uriSchemeRegex
	 */
	static $uriSchemeRegex = '/^[A-Za-z][-.+A-Za-z0-9]*:/';

	/**
	 * Regex to determine if at least one word char exists after the protocol (i.e. after the ':')
	 *
	 * @static
	 * @property {RegExp} hasWordCharAfterProtocolRegex
	 */
	static $hasWordCharAfterProtocolRegex;

	/**
	 * Regex to determine if the string is a valid IP address
	 *
	 * @static
	 * @property {RegExp} ipRegex
	 */
	static $ipRegex = "[0-9][0-9]?[0-9]?\\.[0-9][0-9]?[0-9]?\\.[0-9][0-9]?[0-9]?\\.[0-9][0-9]?[0-9]?(:[0-9]*)?\\/?$";

	/**
	 * Determines if a given URL match found by the {@link UrlMatch}
	 * is valid. Will return `false` for:
	 *
	 * 1) URL matches which do not have at least have one period ('.') in the
	 *    domain name (effectively skipping over matches like "abc:def").
	 *    However, URL matches with a protocol will be allowed (ex: 'http://localhost')
	 * 2) URL matches which do not have at least one word character in the
	 *    domain name (effectively skipping over matches like "git:1.0").
	 * 3) A protocol-relative url match (a URL beginning with '//') whose
	 *    previous character is a word character (effectively skipping over
	 *    strings like "abc//google.com")
	 *
	 * Otherwise, returns `true`.
	 *
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} protocolUrlMatch The match URL string for a protocol
	 *   match. Ex: 'http://yahoo.com'. This is used to match something like
	 *   'http://localhost', where we won't double check that the domain name
	 *   has at least one '.' in it.
	 * @return {Boolean} `true` if the match given is valid and should be
	 *   processed, or `false` if the match is invalid and/or should just not be
	 *   processed.
	 */
	static function isValid( $urlMatch, $protocolUrlMatch ) {
		if(
			( !!$protocolUrlMatch && !static::isValidUriScheme( $protocolUrlMatch ) ) ||
			static::urlMatchDoesNotHaveProtocolOrDot( $urlMatch, $protocolUrlMatch ) ||    // At least one period ('.') must exist in the URL match for us to consider it an actual URL, *unless* it was a full protocol match (like 'http://localhost')
			(static::urlMatchDoesNotHaveAtLeastOneWordChar( $urlMatch, $protocolUrlMatch ) && // At least one letter character must exist in the domain name after a protocol match. Ex: skip over something like "git:1.0"
			   !static::isValidIpAddress( $urlMatch )) || // Except if it's an IP address
			static::containsMultipleDots( $urlMatch )
		) {
			return false;
		}
		return true;
	}
	
	static function isValidIpAddress( $uriSchemeMatch ) {
		return !!preg_match('/'. static::$hasFullProtocolRegex . static::$ipRegex .'/', $uriSchemeMatch);
	}

	static function containsMultipleDots( $urlMatch ) {
		$stringBeforeSlash = $urlMatch;
		if (!!preg_match('/'. static::$hasFullProtocolRegex .'/', $urlMatch)) {
			$stringBeforeSlash = explode('://', $urlMatch)[1];
		}
		return strpos(explode('/', $stringBeforeSlash)[0], '..') !== false;
	}

	/**
	 * Determines if the URI scheme is a valid scheme to be autolinked. Returns
	 * `false` if the scheme is 'javascript:' or 'vbscript:'
	 *
	 * @private
	 * @param {String} uriSchemeMatch The match URL string for a full URI scheme
	 *   match. Ex: 'http://yahoo.com' or 'mailto:a@a.com'.
	 * @return {Boolean} `true` if the scheme is a valid one, `false` otherwise.
	 */
	static function isValidUriScheme( $uriSchemeMatch ) {
		preg_match( static::$uriSchemeRegex, $uriSchemeMatch, $uriScheme);
		
		return ( $uriScheme = strtolower($uriScheme[ 0 ]) ) !== 'javascript:' && $uriScheme !== 'vbscript:' ;
	}

	/**
	 * Determines if a URL match does not have either:
	 *
	 * a) a full protocol (i.e. 'http://'), or
	 * b) at least one dot ('.') in the domain name (for a non-full-protocol
	 *    match).
	 *
	 * Either situation is considered an invalid URL (ex: 'git:d' does not have
	 * either the '://' part, or at least one dot in the domain name. If the
	 * match was 'git:abc.com', we would consider this valid.)
	 *
	 * @private
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} protocolUrlMatch The match URL string for a protocol
	 *   match. Ex: 'http://yahoo.com'. This is used to match something like
	 *   'http://localhost', where we won't double check that the domain name
	 *   has at least one '.' in it.
	 * @return {Boolean} `true` if the URL match does not have a full protocol,
	 *   or at least one dot ('.') in a non-full-protocol match.
	 */
	static function urlMatchDoesNotHaveProtocolOrDot( $urlMatch, $protocolUrlMatch ) {
		return ( !!$urlMatch && ( !$protocolUrlMatch || !preg_match('/'. static::$hasFullProtocolRegex .'/', $protocolUrlMatch) ) && strpos($urlMatch, '.') === false );
	}

	/**
	 * Determines if a URL match does not have at least one word character after
	 * the protocol (i.e. in the domain name).
	 *
	 * At least one letter character must exist in the domain name after a
	 * protocol match. Ex: skip over something like "git:1.0"
	 *
	 * @private
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} protocolUrlMatch The match URL string for a protocol
	 *   match. Ex: 'http://yahoo.com'. This is used to know whether or not we
	 *   have a protocol in the URL string, in order to check for a word
	 *   character after the protocol separator (':').
	 * @return {Boolean} `true` if the URL match does not have at least one word
	 *   character in it after the protocol, `false` otherwise.
	 */
	static function urlMatchDoesNotHaveAtLeastOneWordChar( $urlMatch, $protocolUrlMatch ) {
		return !!$urlMatch && !!$protocolUrlMatch && !preg_match(static::$hasWordCharAfterProtocolRegex, $urlMatch);
	}
};

$x = (function() {
	$schemeRegex = "(?:[A-Za-z][-.+A-Za-z0-9]{0,63}:(?![A-Za-z][-.+A-Za-z0-9]{0,63}:\\/\\/)(?!\\d+\\/?)(?:\\/\\/)?)";  // match protocol, allow in format "http://" or "mailto:". However, do not match the first part of something like 'link:http://www.google.com' (i.e. don't match "link:"). Also, make sure we don't interpret 'google.com:8000' as if 'google.com' was a protocol here (i.e. ignore a trailing port number in this regex)
	$alphaNumCharsStr = RegexLib::$alphaNumericCharsStr;

	// Allow optional path, query string, and hash anchor, not ending in the following characters: "?!:,.;"
	// http://blog.codinghorror.com/the-problem-with-urls/
	$urlSuffixRegex = "[\\/?#](?:[$alphaNumCharsStr\\-+&@#\\/%=~_()|'$*\\[\\]?!:,.;✓]*[$alphaNumCharsStr\\-+&@#\\/%=~_()|'$*\\[\\]✓])?";
	
	UrlMatch::$wordCharRegExp = "/[$alphaNumCharsStr]/i";
	UrlMatch::$hasWordCharAfterProtocolRegex = '/:[^\\s]*?['. RegexLib::$alphaCharsStr .']/';
	
	UrlMatch::$matcherRegex   = join("", [
		"/(?:", // parens to cover match for scheme (optional), and domain
			"(",  // *** Capturing group $1, for a scheme-prefixed url (ex: http://google.com)
				$schemeRegex,
				RegexLib::getDomainNameStr(2),
			")",
			
			"|",
			
			/*"(",  // *** Capturing group $4 for a 'www.' prefixed url (ex: www.google.com)
				"(\\/\\/)?",  // *** Capturing group $5 for an optional protocol-relative URL. Must be at the beginning of the string or start with a non-word character (handled later)
				"(?:www\\.)",  // starting with 'www.'
				RegexLib::getDomainNameStr(6),
			")",
			
			"|",  regular expression is too large for default php runtime [ maximum 65856 chars ] */
			
			"(",  // *** Capturing group $8, for known a TLD url (ex: google.com)
				"(\\/\\/)?",  // *** Capturing group $9 for an optional protocol-relative URL. Must be at the beginning of the string or start with a non-word character (handled later)
				RegexLib::getDomainNameStr(6) ."\\.",  //=> or 10 ^+ www.
				RegexLib::$tldRegex, // match our known top level domains (TLDs)
				"(?![-$alphaNumCharsStr])", // TLD not followed by a letter, behaves like unicode-aware \b
			")",
		")",
		
		"(?::[0-9]+)?", // port
		
		"(?:$urlSuffixRegex)?/i" // match for path, query string, and/or hash anchor - optional
	]);
	return null;
}); $x = $x();

?>
