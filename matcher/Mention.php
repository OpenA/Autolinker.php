<?php
/**
 * @class MentionMatch
 * @extends Matcher
 *
 * Matcher to find/replace username matches in an input string.
 */
class MentionMatch extends Matcher {

	/**
	 * Hash of regular expression to match username handles. Example match:
	 *
	 *     @asdf
	 *
	 * @static
	 * @property {Object} matcherRegexes
	 */
	static $matcherRegexes;

	/**
	 * The regular expression to use to check the character before a username match to
	 * make sure we didn't accidentally match an email address.
	 *
	 * For example, the string "asdf@asdf.com" should not match "@asdf" as a username.
	 *
	 * @static
	 * @property {RegExp} nonWordCharRegex
	 */
	static $nonWordCharRegex;

	/**
	 * @constructor
	 * @param {Object} cfg The configuration properties for the Match instance,
	 *   specified in an Object (map).
	 */
	function __construct( $cfg ) {
		parent::__construct( $cfg );
		//Autolinker.matcher.Matcher.prototype.constructor.call( this, cfg );
		
		$this->serviceName = $cfg['serviceName'];
	}

	/**
	 * @inheritdoc
	 */
	function parseMatches( $text ) {
		$nonWordCharRegex = static::$nonWordCharRegex;
		$serviceName      = $this->serviceName;
		$tagBuilder       = $this->tagBuilder;
		$matches          = [];
		
		if ( !($matcherRegex = static::$matcherRegexes[$serviceName]) ) {
			return $matches;
		}
		
		if( ($len = preg_match_all($matcherRegex, $text, $match)) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ 0 ][ $i ];
				$offset = strpos($text, $matchedText);
				
				// If we found the match at the beginning of the string, or we found the match
				// and there is a whitespace char in front of it (meaning it is not an email
				// address), then it is a username match.
				if( $offset === 0 || preg_match( $nonWordCharRegex, $text{$offset - 1} )) {
					$matchedText = preg_replace('/\.+$/', '', $matchedText); // strip off trailing .
					$mention = substr( $matchedText, 1 );  // strip off the '@' character at the beginning
					
					array_push($matches, new Mention( Array(
						'tagBuilder'  => $tagBuilder,
						'matchedText' => $matchedText,
						'offset'      => $offset,
						'serviceName' => $serviceName,
						'mention'     => $mention
					)));
				}
			}
		}
		return $matches;
	}
};

MentionMatch::$nonWordCharRegex = '/[^'. RegexLib::$alphaNumericCharsStr .']/';
MentionMatch::$matcherRegexes   = [
	'twitter'   => '/@[_' . RegexLib::$alphaNumericCharsStr .']{1,20}/',
	'instagram' => '/@[_.'. RegexLib::$alphaNumericCharsStr .']{1,50}/'
];

?>
