<?php
/**
 * @class EmailMatch
 * @extends Matcher
 *
 * Matcher to find email matches in an input string.
 *
 * See this class's superclass ({@link Matcher}) for more details.
 */

class EmailMatch extends Matcher {
	
	/**
	 * The regular expression to match email addresses. Example match:
	 *
	 *     person@place.com
	 *
	 * @static
	 * @property {RegExp} matcherRegex
	 */
	static $matcherRegex;
	
	/**
	 * @inheritdoc
	 */
	function parseMatches( $text ) {
		$tagBuilder = $this->tagBuilder;
		$matches    = [];
		
		if ( ($len = preg_match_all( static::$matcherRegex, $text, $match )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ 0 ][ $i ];
				$offset      = strpos($text, $matchedText);
				
				array_push($matches, new Email([
					'tagBuilder'  => $tagBuilder,
					'matchedText' => $matchedText,
					'offset'      => $offset,
					'email'       => $matchedText
				]));
			}
		}
		return $matches;
	}
	
	function getMatcherRegex() {
		return static::$matcherRegex;
	}
};

$x = (function() {
	$validCharacters = RegexLib::$alphaNumericCharsStr .'!#$%&\'*+\\-\\/=?^_`{|}~';
	$validRestrictedCharacters = $validCharacters .'\\s"(),:;<.>@\\[\\]';

	EmailMatch::$matcherRegex = join('', [
		"/(?:[$validCharacters](?:[$validCharacters]|\\.(?!\\.|@))*|\\\"[$validRestrictedCharacters]+\\\")@",
		RegexLib::getDomainNameStr(1), '\\.',
		RegexLib::$tldRegex, // match our known top level domains (TLDs) '.com', '.net', etc
	'/i']);
		
	return null;
}); $x = $x();

?>
