<?php
/**
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
	 * @property {RegExp} matcherRegex
	 */
	static $matcherRegex;

	/**
	 * @inheritdoc
	 */
	function parseMatches( $text ) {
		$tagBuilder = $this->tagBuilder;
		$matches    = [];
		
		if ( ($len = preg_match_all( self::$matcherRegex, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ $i ][ 0 ][ 0 ];
				$offset      = $match[ $i ][ 0 ][ 1 ];
				
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
};

EmailMatch::$matcherRegex = RegexLib::getEmailMatch();
