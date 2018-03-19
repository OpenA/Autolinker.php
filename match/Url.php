<?php
/**
 * @class Url
 * @extends Match
 *
 * Represents a Url match found in an input string which should be Autolinked.
 *
 * See this class's superclass ({@link Match}) for more details.
 */
class Url extends Match {

	/**
	 * @cfg {String} url (required)
	 *
	 * The url that was matched.
	 */
	var $url;
	/**
	 * @cfg {"scheme"/"www"/"tld"} urlMatchType (required)
	 *
	 * The type of URL match that this class represents. This helps to determine
	 * if the match was made in the original text with a prefixed scheme (ex:
	 * 'http://www.google.com'), a prefixed 'www' (ex: 'www.google.com'), or
	 * was matched by a known top-level domain (ex: 'google.com').
	 */
	var $urlMatchType;
	/**
	 * @cfg {Boolean} protocolUrlMatch (required)
	 *
	 * `true` if the URL is a match which already has a protocol (i.e.
	 * 'http://'), `false` if the match was from a 'www' or known TLD match.
	 */
	var $protocolUrlMatch;
	/**
	 * @cfg {Boolean} protocolRelativeMatch (required)
	 *
	 * `true` if the URL is a protocol-relative match. A protocol-relative match
	 * is a URL that starts with '//', and will be either http:// or https://
	 * based on the protocol that the site is loaded under.
	 */
	var $protocolRelativeMatch;
	/**
	 * @cfg {Object} stripPrefix (required)
	 *
	 * The Object form of {@link Autolinker#cfg-stripPrefix}.
	 */
	var $stripPrefix;
	/**
	 * @cfg {Boolean} stripTrailingSlash (required)
	 * @inheritdoc Autolinker#cfg-stripTrailingSlash
	 */
	var $stripTrailingSlash;
	/**
	 * @cfg {Boolean} decodePercentEncoding (required)
	 * @inheritdoc Autolinker#cfg-decodePercentEncoding
	 */
	var $decodePercentEncoding;
	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		
		// @if DEBUG
		if( !preg_match('/^(?:scheme|www|tld)$/', $cfg['urlMatchType']) ) throw new Exception( '`urlMatchType` cfg must be one of: "scheme", "www", or "tld"' );
		if( !$cfg['url'] )                           throw new Exception( '`url` cfg required' );
		if( $cfg['protocolUrlMatch']      === null ) throw new Exception( '`protocolUrlMatch` cfg required' );
		if( $cfg['protocolRelativeMatch'] === null ) throw new Exception( '`protocolRelativeMatch` cfg required' );
		if( $cfg['stripPrefix']           === null ) throw new Exception( '`stripPrefix` cfg required' );
		if( $cfg['stripTrailingSlash']    === null ) throw new Exception( '`stripTrailingSlash` cfg required' );
		if( $cfg['decodePercentEncoding'] === null ) throw new Exception( '`decodePercentEncoding` cfg required' );
		// @endif
		
		$this->urlMatchType          = $cfg['urlMatchType'];
		$this->url                   = $cfg['url'];
		$this->protocolUrlMatch      = $cfg['protocolUrlMatch'];
		$this->protocolRelativeMatch = $cfg['protocolRelativeMatch'];
		$this->stripPrefix           = $cfg['stripPrefix'];
		$this->stripTrailingSlash    = $cfg['stripTrailingSlash'];
		$this->decodePercentEncoding = $cfg['decodePercentEncoding'];
	}
	
	/**
	 * @private
	 * @property {RegExp} schemePrefixRegex
	 *
	 * A regular expression used to remove the 'http://' or 'https://' from
	 * URLs.
	 */
	static $schemePrefixRegex = '/^(https?:\/\/)?/i';
	
	/**
	 * @private
	 * @property {RegExp} wwwPrefixRegex
	 *
	 * A regular expression used to remove the 'www.' from URLs.
	 */
	static $wwwPrefixRegex = '/^(https?:\/\/)?(www\.)?/i';
	
	/**
	 * @private
	 * @property {RegExp} protocolRelativeRegex
	 *
	 * The regular expression used to remove the protocol-relative '//' from the {@link #url} string, for purposes
	 * of {@link #getAnchorText}. A protocol-relative URL is, for example, "//yahoo.com"
	 */
	static $protocolRelativeRegex = '/^\/\//';
	
	/**
	 * @private
	 * @property {Boolean} protocolPrepended
	 *
	 * Will be set to `true` if the 'http://' protocol has been prepended to the {@link #url} (because the
	 * {@link #url} did not have a protocol)
	 */
	var $protocolPrepended = false;
	
	/**
	 * Returns a string name for the type of match that this class represents.
	 *
	 * @return {String}
	 */
	function getType() {
		return 'url';
	}
	
	/**
	 * Returns a string name for the type of URL match that this class
	 * represents.
	 *
	 * This helps to determine if the match was made in the original text with a
	 * prefixed scheme (ex: 'http://www.google.com'), a prefixed 'www' (ex:
	 * 'www.google.com'), or was matched by a known top-level domain (ex:
	 * 'google.com').
	 *
	 * @return {"scheme"/"www"/"tld"}
	 */
	function getUrlMatchType() {
		return $this->urlMatchType;
	}
	
	/**
	 * Returns the url that was matched, assuming the protocol to be 'http://' if the original
	 * match was missing a protocol.
	 *
	 * @return {String}
	 */
	function getUrl() {
		$url = $this->url;
		
		// if the url string doesn't begin with a protocol, assume 'http://'
		if( !$this->protocolRelativeMatch && !$this->protocolUrlMatch && !$this->protocolPrepended ) {
			$url = $this->url = 'http://'. $url;
			$this->protocolPrepended = true;
		}
		return $url;
	}
	
	/**
	 * Returns the anchor href that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorHref() {
		return preg_replace( '/&amp;/', '&', $this->url );  // any &amp;'s in the URL should be converted back to '&' if they were displayed as &amp; in the source html
	}
	
	/**
	 * Returns the anchor text that should be generated for the match.
	 *
	 * @return {String}
	 */
	function getAnchorText() {
		$anchorText = $this->getMatchedText();
		
		if( $this->protocolRelativeMatch ) {
			// Strip off any protocol-relative '//' from the anchor text
			$anchorText = $this->stripProtocolRelativePrefix( $anchorText );
		}
		if( $this->stripPrefix['scheme'] ) {
			$anchorText = $this->stripSchemePrefix( $anchorText );
		}
		if( $this->stripPrefix['www'] ) {
			$anchorText = $this->stripWwwPrefix( $anchorText );
		}
		if( $this->stripTrailingSlash ) {
			$anchorText = $this->removeTrailingSlash( $anchorText );  // remove trailing slash, if there is one
		}
		if( $this->decodePercentEncoding ) {
			$anchorText = $this->removePercentEncoding( $anchorText );
		}
		return $anchorText;
	}
	
	// ---------------------------------------

	// Utility Functionality
	
	/**
	 * Strips the scheme prefix (such as "http://" or "https://") from the given
	 * `url`.
	 *
	 * @private
	 * @param {String} url The text of the anchor that is being generated, for
	 *   which to strip off the url scheme.
	 * @return {String} The `url`, with the scheme stripped.
	 */
	function stripSchemePrefix( $url ) {
		return str_replace( static::$schemePrefixRegex, '', $url );
	}
	
	/**
	 * Strips the 'www' prefix from the given `url`.
	 *
	 * @private
	 * @param {String} url The text of the anchor that is being generated, for
	 *   which to strip off the 'www' if it exists.
	 * @return {String} The `url`, with the 'www' stripped.
	 */
	function stripWwwPrefix( $url ) {
		return str_replace( static::$wwwPrefixRegex, '$1', $url );  // leave any scheme ($1), it one exists
	}
	
	/**
	 * Strips any protocol-relative '//' from the anchor text.
	 *
	 * @private
	 * @param {String} text The text of the anchor that is being generated, for which to strip off the
	 *   protocol-relative prefix (such as stripping off "//")
	 * @return {String} The `anchorText`, with the protocol-relative prefix stripped.
	 */
	function stripProtocolRelativePrefix( $text ) {
		return str_replace( static::$protocolRelativeRegex, '', $text );
	}
	
	/**
	 * Removes any trailing slash from the given `anchorText`, in preparation for the text to be displayed.
	 *
	 * @private
	 * @param {String} anchorText The text of the anchor that is being generated, for which to remove any trailing
	 *   slash ('/') that may exist.
	 * @return {String} The `anchorText`, with the trailing slash removed.
	 */
	function removeTrailingSlash( $anchorText ) {
		if( $anchorText{ strlen($anchorText) - 1 } === '/' ) {
			$anchorText = substr( $anchorText, 0, -1 );
		}
		return $anchorText;
	}
	
	/**
	 * Decodes percent-encoded characters from the given `anchorText`, in preparation for the text to be displayed.
	 *
	 * @private
	 * @param {String} anchorText The text of the anchor that is being generated, for which to decode any percent-encoded characters.
	 * @return {String} The `anchorText`, with the percent-encoded characters decoded.
	 */
	function removePercentEncoding( $anchorText ) {
		try {
			return rawUrlDecode( 
				preg_replace(
					['/%22/i', '/%26/i', '/%27/i', '/%3C/i', '/%3E/i'],
					['&quot;', '&amp;', '&#39;', '&lt;', '&gt;' ],
					$anchorText
				));
		} catch (exception $e) {
			// Invalid escape sequence.
			return $anchorText;
		}
	}
};

?>
