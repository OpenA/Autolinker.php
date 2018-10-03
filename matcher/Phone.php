<?php
/**
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
	 * @property {RegExp} matcherRegex
	 */
	static $matcherRegex = '/(?:(?:(?:(\+)?\d{1,3}[-\040.]?)?\(?\d{3}\)?[-\040.]?\d{3}[-\040.]?\d{4})|(?:(\+)(?:9[976]\d|8[987530]\d|6[987]\d|5[90]\d|42\d|3[875]\d|2[98654321]\d|9[8543210]|8[6421]|6[6543210]|5[87654321]|4[987654310]|3[9643210]|2[70]|7|1)[-\040.]?(?:\d[-\040.]?){6,12}\d+))([,;]+[0-9]+#?)*/';
	
	// ex: (123) 456-7890, 123 456 7890, 123-456-7890, +18004441234,,;,10226420346#, 
	// +1 (800) 444 1234, 10226420346#, 1-800-444-1234,1022,64,20346#
	
	/**
	 * @inheritdoc
	 */
	function parseMatches($text) {
		$tagBuilder   = $this->tagBuilder;
		$matches      = [];
		
		if( ($len = preg_match_all( self::$matcherRegex, $text, $match, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) ) {
			
			for( $i = 0; $i < $len; $i++ ) {
				$matchedText = $match[ $i ][ 0 ][ 0 ]; // Remove non-numeric values from phone number string
				$plusSign    = $match[ $i ][ 1 ][ 0 ] ?
				             : $match[ $i ][ 2 ][ 0 ]; // match[ 1 ] or match[ 2 ] is the prefixed plus sign, if there is one
				$endNumber   = $match[ $i ][ 3 ][ 0 ];
				$offset      = $match[ $i ][ 0 ][ 1 ];
				$cleanNumber = preg_replace('/[^0-9,;#]/', '', $matchedText); // strip out non-digit characters exclude comma semicolon and #
				$before      = $offset === 0 ? '' : substr($text, $offset - 1, 1);
				$after       = substr($text, $offset + strlen($matchedText), 1);
				$contextClear = !preg_match('/\d/', $before) && !preg_match('/\d/', $after);
				
				if ($this->testMatch($endNumber) && $this->testMatch($matchedText) && $contextClear) {
					array_push($matches, new Phone([
						'tagBuilder'  => $tagBuilder,
						'matchedText' => $matchedText,
						'offset'      => $offset,
						'number'      => $cleanNumber,
						'plusSign'    => $plusSign
					]));
				}
			}
		}
		return $matches;
	}
	
	function testMatch($text) {
		return preg_match('/\D/', $text) !== false;
	}
};
