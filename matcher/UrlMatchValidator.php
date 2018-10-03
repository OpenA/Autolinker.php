<?php
/**
 * @singleton
 *
 * Used by Autolinker to filter out false URL positives from the
 * {@link Autolinker.matcher.Url UrlMatcher}.
 *
 * Due to the limitations of regular expressions (including the missing feature
 * of look-behinds in JS regular expressions), we cannot always determine the
 * validity of a given match. This class applies a bit of additional logic to
 * filter out any false positives that have been matched by the
 * {@link Autolinker.matcher.Url UrlMatcher}.
 */
abstract class UrlMatchValidator {

	/**
	 * Regex to test for a full protocol, with the two trailing slashes. Ex: 'http://'
	 *
	 * @property {RegExp} hasFullProtocolRegex
	 */
	private static $hasFullProtocolRegex = '/^[A-Za-z][-.+A-Za-z0-9]*:\/\//';

	/**
	 * Regex to find the URI scheme, such as 'mailto:'.
	 *
	 * This is used to filter out 'javascript:' and 'vbscript:' schemes.
	 *
	 * @property {RegExp} uriSchemeRegex
	 */
	private static $uriSchemeRegex = '/^[A-Za-z][-.+A-Za-z0-9]*:/';

	/**
	 * Regex to determine if the string is a valid IP address
	 *
	 * @property {RegExp} ipRegex
	 */
	private static $ipRegex = '/^[A-Za-z][-.+A-Za-z0-9]*:\/\/[0-9][0-9]?[0-9]?\.[0-9][0-9]?[0-9]?\.[0-9][0-9]?[0-9]?\.[0-9][0-9]?[0-9]?(:[0-9]*)?\/?$/';

	/**
	 * Determines if a given URL match found by the {@link Autolinker.matcher.Url UrlMatcher}
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
	public function isValid( $urlMatch, $protocolUrlMatch ) {
		if(
			( $protocolUrlMatch && !self::isValidUriScheme( $protocolUrlMatch ) ) ||
			self::urlMatchDoesNotHaveProtocolOrDot( $urlMatch, $protocolUrlMatch ) ||    // At least one period ('.') must exist in the URL match for us to consider it an actual URL, *unless* it was a full protocol match (like 'http://localhost')
			(self::urlMatchDoesNotHaveAtLeastOneWordChar( $urlMatch, $protocolUrlMatch ) && // At least one letter character must exist in the domain name after a protocol match. Ex: skip over something like "git:1.0"
			   !self::isValidIpAddress( $urlMatch )) || // Except if it's an IP address
			self::containsMultipleDots( $urlMatch )
		) {
			return false;
		}
		
		return true;
	}

	public function isValidIpAddress( $uriSchemeMatch ) {
		return preg_match( self::$ipRegex, $uriSchemeMatch ) !== false;
	}

	public function containsMultipleDots( $urlMatch ) {
		$stringBeforeSlash = $urlMatch;
		if (preg_match( self::$hasFullProtocolRegex, $urlMatch )) {
			$stringBeforeSlash = explode('://', $urlMatch)[1];
		}
		return strpos(explode('/', $stringBeforeSlash)[0], "..") !== false;
	}

	/**
	 * Determines if the URI scheme is a valid scheme to be autolinked. Returns
	 * `false` if the scheme is 'javascript:' or 'vbscript:'
	 *
	 * @param {String} uriSchemeMatch The match URL string for a full URI scheme
	 *   match. Ex: 'http://yahoo.com' or 'mailto:a@a.com'.
	 * @return {Boolean} `true` if the scheme is a valid one, `false` otherwise.
	 */
	private static function isValidUriScheme( $uriSchemeMatch ) {
		$uriScheme = strtoLower( preg_match( self::$uriSchemeRegex, $uriSchemeMatch )[ 0 ] );
		
		return ( $uriScheme !== 'javascript:' && $uriScheme !== 'vbscript:' );
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
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} protocolUrlMatch The match URL string for a protocol
	 *   match. Ex: 'http://yahoo.com'. This is used to match something like
	 *   'http://localhost', where we won't double check that the domain name
	 *   has at least one '.' in it.
	 * @return {Boolean} `true` if the URL match does not have a full protocol,
	 *   or at least one dot ('.') in a non-full-protocol match.
	 */
	private static function urlMatchDoesNotHaveProtocolOrDot( $urlMatch, $protocolUrlMatch ) {
		return ( !!$urlMatch && ( !$protocolUrlMatch || !preg_match( self::$hasFullProtocolRegex, $protocolUrlMatch ) ) && strpos($urlMatch, '.') === false );
	}

	/**
	 * Determines if a URL match does not have at least one word character after
	 * the protocol (i.e. in the domain name).
	 *
	 * At least one letter character must exist in the domain name after a
	 * protocol match. Ex: skip over something like "git:1.0"
	 *
	 * @param {String} urlMatch The matched URL, if there was one. Will be an
	 *   empty string if the match is not a URL match.
	 * @param {String} protocolUrlMatch The match URL string for a protocol
	 *   match. Ex: 'http://yahoo.com'. This is used to know whether or not we
	 *   have a protocol in the URL string, in order to check for a word
	 *   character after the protocol separator (':').
	 * @return {Boolean} `true` if the URL match does not have at least one word
	 *   character in it after the protocol, `false` otherwise.
	 */
	private static function urlMatchDoesNotHaveAtLeastOneWordChar( $urlMatch, $protocolUrlMatch ) {
		if( $urlMatch && $protocolUrlMatch ) {
			return !preg_match( "~:[^\\s]*?[". RegexLib::$alphaCharsStr ."]~u", $urlMatch );
		} else {
			return false;
		}
	}
};
