<?php
/**
 * @class HashtagMatch
 * @extends Matcher
 *
 * Matcher to find Hashtag matches in an input string.
 */
class HashtagMatch extends Matcher {

	/**
	 * @cfg {String} serviceName
	 *
	 * The service to point hashtag matches to. See {@link Autolinker#hashtag}
	 * for available values.
	 */
	var $serviceName;
	
	/**
	 * The regular expression to match Hashtags. Example match:
	 *
	 *     #asdf
	 *
	 * @static
	 * @property {RegExp} matcherRegex
	 */
	static $matcherRegex;

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
		
		if( ($len = preg_match_all( static::$matcherRegex, $text, $match )) ) {
		
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ 0 ][ $i ];
				$offset      = strpos( $text, $matchedText );
				
				// If we found the match at the beginning of the string, or we found the match
				// and there is a whitespace char in front of it (meaning it is not a '#' char
				// in the middle of a word), then it is a hashtag match.
				if( $offset === 0 || preg_match( $nonWordCharRegex, $text{$offset - 1} )) {
					
					array_push($matches, new Hashtag([
						'tagBuilder'  => $tagBuilder,
						'matchedText' => $matchedText,
						'offset'      => $offset,
						'serviceName' => $serviceName,
						'hashtag'     => substr( $matchedText, 1 ) // strip off the '#' character at the beginning
					]));
				}
			}
		}
		return $matches;
	}
};

HashtagMatch::$matcherRegex     = '/#[_'. RegexLib::$alphaNumericCharsStr .']{1,139}/';
HashtagMatch::$nonWordCharRegex = '/[^' . RegexLib::$alphaNumericCharsStr .']/';

?>
