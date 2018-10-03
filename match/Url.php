<?php
/**
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
	protected $url;

	/**
	 * @cfg {"scheme"/"www"/"tld"} urlMatchType (required)
	 *
	 * The type of URL match that this class represents. This helps to determine
	 * if the match was made in the original text with a prefixed scheme (ex:
	 * 'http://www.google.com'), a prefixed 'www' (ex: 'www.google.com'), or
	 * was matched by a known top-level domain (ex: 'google.com').
	 */
	protected $urlMatchType;

	/**
	 * @cfg {Boolean} protocolUrlMatch (required)
	 *
	 * `true` if the URL is a match which already has a protocol (i.e.
	 * 'http://'), `false` if the match was from a 'www' or known TLD match.
	 */
	protected $protocolUrlMatch;

	/**
	 * @cfg {Boolean} protocolRelativeMatch (required)
	 *
	 * `true` if the URL is a protocol-relative match. A protocol-relative match
	 * is a URL that starts with '//', and will be either http:// or https://
	 * based on the protocol that the site is loaded under.
	 */
	protected $protocolRelativeMatch;

	/**
	 * @cfg {Object} stripPrefix (required)
	 *
	 * The Object form of {@link Autolinker#cfg-stripPrefix}.
	 */
	protected $stripPrefix;

	/**
	 * @cfg {Boolean} stripTrailingSlash (required)
	 * @inheritdoc Autolinker#cfg-stripTrailingSlash
	 */
	protected $stripTrailingSlash;

	/**
	 * @cfg {Boolean} decodePercentEncoding (required)
	 * @inheritdoc Autolinker#cfg-decodePercentEncoding
	 */
	protected $decodePercentEncoding;

	/**
	 * @param {Object} cfg The configuration properties for the Match
	 *   instance, specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		
		$this->assign( $cfg );
		
		// @if DEBUG
		if (!preg_match('/^(?:scheme|www|tld)$/', $this->urlMatchType))
			throw new Exception('`urlMatchType` cfg must be one of: "scheme", "www", or "tld"');
		$this->requireStrict(
			'url',
			'protocolUrlMatch',
			'protocolRelativeMatch',
			'stripPrefix',
			'stripTrailingSlash',
			'decodePercentEncoding');
		// @endif
	}

	/**
	 * @property {RegExp} schemePrefixRegex
	 *
	 * A regular expression used to remove the 'http://' or 'https://' from
	 * URLs.
	 */
	private static $schemePrefixRegex = '/^(https?:\/\/)?/i';

	/**
	 * @property {RegExp} wwwPrefixRegex
	 *
	 * A regular expression used to remove the 'www.' from URLs.
	 */
	private static $wwwPrefixRegex = '/^(https?:\/\/)?(www\.)?/i';

	/**
	 * @property {RegExp} protocolRelativeRegex
	 *
	 * The regular expression used to remove the protocol-relative '//' from the {@link #url} string, for purposes
	 * of {@link #getAnchorText}. A protocol-relative URL is, for example, "//yahoo.com"
	 */
	private static $protocolRelativeRegex = '/^\/\//';

	/**
	 * @property {Boolean} protocolPrepended
	 *
	 * Will be set to `true` if the 'http://' protocol has been prepended to the {@link #url} (because the
	 * {@link #url} did not have a protocol)
	 */
	private $protocolPrepended = false;

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
		return preg_replace( '/&amp;/', '&', $this->getUrl() );  // any &amp;'s in the URL should be converted back to '&' if they were displayed as &amp; in the source html
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
	 * @param {String} url The text of the anchor that is being generated, for
	 *   which to strip off the url scheme.
	 * @return {String} The `url`, with the scheme stripped.
	 */
	private function stripSchemePrefix( $url ) {
		return preg_replace( self::$schemePrefixRegex, '', $url );
	}

	/**
	 * Strips the 'www' prefix from the given `url`.
	 *
	 * @param {String} url The text of the anchor that is being generated, for
	 *   which to strip off the 'www' if it exists.
	 * @return {String} The `url`, with the 'www' stripped.
	 */
	private function stripWwwPrefix( $url ) {
		return preg_replace( self::$wwwPrefixRegex, '$1', $url );  // leave any scheme ($1), it one exists
	}

	/**
	 * Strips any protocol-relative '//' from the anchor text.
	 *
	 * @param {String} text The text of the anchor that is being generated, for which to strip off the
	 *   protocol-relative prefix (such as stripping off "//")
	 * @return {String} The `anchorText`, with the protocol-relative prefix stripped.
	 */
	private function stripProtocolRelativePrefix( $text ) {
		return preg_replace( self::$protocolRelativeRegex, '', $text );
	}

	/**
	 * Removes any trailing slash from the given `anchorText`, in preparation for the text to be displayed.
	 *
	 * @param {String} anchorText The text of the anchor that is being generated, for which to remove any trailing
	 *   slash ('/') that may exist.
	 * @return {String} The `anchorText`, with the trailing slash removed.
	 */
	private function removeTrailingSlash( $anchorText ) {
		if( $anchorText{ strlen($anchorText) - 1 } === '/' ) {
			$anchorText = substr( $anchorText, 0, -1 );
		}
		return $anchorText;
	}

	/**
	 * Decodes percent-encoded characters from the given `anchorText`, in preparation for the text to be displayed.
	 *
	 * @param {String} anchorText The text of the anchor that is being generated, for which to decode any percent-encoded characters.
	 * @return {String} The `anchorText`, with the percent-encoded characters decoded.
	 */
	private function removePercentEncoding( $anchorText ) {
		try {
			return rawUrlDecode( 
				preg_replace(
					['/%22/', '/%26/', '/%27/', '/%3C/', '/%3E/'],
					['&quot;', '&amp;', '&#39;', '&lt;', '&gt;' ],
					$anchorText
				));
		} catch (exception $e) {
			// Invalid escape sequence.
			return $anchorText;
		}
	}
};
