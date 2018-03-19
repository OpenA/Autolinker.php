<?php
/**
 * @class PhoneMatch
 * @extends Matcher
 *
 * Matcher to find Phone number matches in an input string.
 *
 * See this class's superclass ({@link Matcher}) for more
 * details.
 */
class PhoneMatch extends Matcher {

	/**
	 * The regular expression to match Phone numbers. Example match:
	 *
	 *     (123) 456-7890
	 *
	 * This regular expression has the following capturing groups:
	 *
	 * 1. The prefixed '+' sign, if there is one.
	 *
	 * @static
	 * @property {RegExp} matcherRegex
	 */
	static $matcherRegex = '/(?:(\+)?\d{1,3}[-\040.]?)?\(?\d{3}\)?[-\040.]?\d{3}[-\040.]?\d{4}([,;]*[0-9]+#?)*/';
	
	// ex: (123) 456-7890, 123 456 7890, 123-456-7890, +18004441234,,;,10226420346#, 
	// +1 (800) 444 1234, 10226420346#, 1-800-444-1234,1022,64,20346#
	
	/**
	 * @inheritdoc
	 */
	function parseMatches($text) {
		$tagBuilder   = $this->tagBuilder;
		$matches      = [];
		
		if( ($len = preg_match_all( static::$matcherRegex, $text, $match )) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ 0 ][ $i ]; // Remove non-numeric values from phone number string
				$plusSign  = !!$match[ 1 ][ $i ]; // match[ 1 ] is the prefixed plus sign, if there is one
				preg_replace('/[^0-9,;#]/', '', $matchedText, $cleanNumber); // strip out non-digit characters exclude comma semicolon and #
				
				if ($this->testMatch($match[ 2 ][ $i ]) && $this->testMatch($matchedText)) {
					array_push($matches, new Phone([
						'tagBuilder'  => $tagBuilder  ,
						'matchedText' => $matchedText ,
						'offset'      => strpos( $text, $matchedText ),
						'number'      => $cleanNumber ,
						'plusSign'    => $plusSign
					]));
				}
			}
		}
		return $matches;
	}
	
	function testMatch($text) {
		return !!preg_match('/\D/', $text);
	}
};

?>
