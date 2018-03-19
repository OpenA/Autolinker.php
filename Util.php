<?php
/**
 * @class Util
 * @singleton
 *
 * A few utility methods for Autolinker.
 */
class Util {
	/**
	 * @property {Function} abstractMethod
	 *
	 * A function object which represents an abstract method.
	 */
	public function abstractMethod() { throw new Exception("abstract"); }
	
	/**
	 * @private
	 * @property {RegExp} trimRegex
	 *
	 * The regular expression used to trim the leading and trailing whitespace
	 * from a string.
	 */
	static $trimRegex = '/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/';
	
	/**
	 * Assigns (shallow copies) the properties of `src` onto `dest`.
	 *
	 * @param {Object} dest The destination object.
	 * @param {Object} src The source object.
	 * @return {Object} The destination object (`dest`)
	 */
	public function assign( $dest, $src ) {
		foreach ($src as $prop => $value) {
			if ( array_key_exists( $prop, $src )) {
				$dest->{$prop} = $value;
			}
		}
		return $dest;
	}
	
	/**
	 * Assigns (shallow copies) the properties of `src` onto `dest`, if the
	 * corresponding property on `dest` === `undefined`.
	 *
	 * @param {Object} dest The destination object.
	 * @param {Object} src The source object.
	 * @return {Object} The destination object (`dest`)
	 */
	public function defaults( $dest, $src ) {
		foreach ($src as $prop => $value) {
			if ( array_key_exists( $prop, $src ) && $dest[$prop] === null ) {
				$dest[$prop] = $value;
			}
		}
		return $dest;
	}

	/**
	 * Removes array elements based on a filtering function. Mutates the input
	 * array.
	 *
	 * Using this instead of the ES5 Array.prototype.filter() function, to allow
	 * Autolinker compatibility with IE8, and also to prevent creating many new
	 * arrays in memory for filtering.
	 *
	 * @param {Array} arr The array to remove elements from. This array is
	 *   mutated.
	 * @param {Function} fn A function which should return `true` to
	 *   remove an element.
	 * @return {Array} The mutated input `arr`.
	 */
	public function remove(array $arr, $fn) {
		for ($i = count($arr) - 1; $i >= 0; $i--) {
			if ($fn($arr[$i]) === true) {
				array_splice($arr, $i, 1);
			}
		}
	}

	/**
	 * Trims the leading and trailing whitespace from a string.
	 *
	 * @param {String} str The string to trim.
	 * @return {String}
	 */
	static function trim(string $str) {
		return str_replace(static::$trimRegex, '', $str);
	}
};

/**
 * Performs the functionality of what modern browsers do when `String.prototype.split()` is called
 * with a regular expression that contains capturing parenthesis.
 *
 * For example:
 *
 *     // Modern browsers:
 *     "a,b,c".split( /(,)/ );  // --> [ 'a', ',', 'b', ',', 'c' ]
 *
 *     // Old IE (including IE8):
 *     "a,b,c".split( /(,)/ );  // --> [ 'a', 'b', 'c' ]
 *
 * This method emulates the functionality of modern browsers for the old IE case.
 *
 * @param {String} str The string to split.
 * @param {RegExp} splitRegex The regular expression to split the input `str` on. The splitting
 *   character(s) will be spliced into the array, as in the "modern browsers" example in the
 *   description of this method.
 *   Note #1: the supplied regular expression **must** have the 'g' flag specified.
 *   Note #2: for simplicity's sake, the regular expression does not need
 *   to contain capturing parenthesis - it will be assumed that any match has them.
 * @return {String[]} The split array of strings, with the splitting character(s) included.
 */
function splitAndCapture($str, $splitRegex) {
	
	$result  = [];
	$lastIdx = 0;
	
	if ( ($len = preg_match_all( $splitRegex, $str, $match )) ) {
		
		for( $i = 0; $i < $len; $i++ ) {
			$matchedText = $match[ 0 ][ $i ];
			$index       = strpos($str, $matchedText);
			
			array_push($result,
				substr($str, $lastIdx, $index),
				$matchedText
			);
			$lastIdx = $index + strlen($matchedText);
		}
	}
	array_push($result, substr($str, $lastIdx));
	
	return $result;
}

/**
 * A truncation feature where the ellipsis will be placed at the end of the URL.
 *
 * @param {String} anchorText
 * @param {Number} truncateLen The maximum length of the truncated output URL string.
 * @param {String} ellipsisChars The characters to place within the url, e.g. "..".
 * @return {String} The truncated URL.
 */
function normalizeUrlsCfg( $urls = true ) { // default to `true`
	if ( gettype($urls) === 'boolean' ) {
		return Array( 'schemeMatches' => $urls, 'wwwMatches' => $urls, 'tldMatches' => $urls );
	} else {  // object form
		return Array(
			'schemeMatches' => (gettype($urls['schemeMatches']) === 'boolean' ? $urls['schemeMatches'] : true),
			'wwwMatches'    => (gettype($urls['wwwMatches'])    === 'boolean' ? $urls['wwwMatches']    : true),
			'tldMatches'    => (gettype($urls['tldMatches'])    === 'boolean' ? $urls['tldMatches']    : true)
		);
	}
}

function normalizeStripPrefixCfg( $stripPrefix = true ) { // default to `true`
	if ( gettype($stripPrefix) === 'boolean' ) {
		return Array( 'scheme' => $stripPrefix, 'www' => $stripPrefix);
	} else {  // object form
		return Array(
			'scheme' => (gettype($stripPrefix['scheme']) === 'boolean' ? $stripPrefix['scheme'] : true),
			'www'    => (gettype($stripPrefix['www'])    === 'boolean' ? $stripPrefix['www']    : true)
		);
	}
}

function normalizeTruncateCfg( $truncate ) {
	if ( gettype($truncate) === 'number' ) {
		return Array( 'length' => $truncate, 'location' => 'end' );
	} else {  // object, or undefined/null
		return Util::defaults( (!$truncate ? [] : $truncate), Array(
			'length'   =>  INF,
			'location' => 'end'
		));
	}
}

/**
 * Truncates the `str` at `len - ellipsisChars.length`, and adds the `ellipsisChars` to the
 * end of the string (by default, two periods: '..'). If the `str` length does not exceed
 * `len`, the string will be returned unchanged.
 *
 * @param {String} str The string to truncate and add an ellipsis to.
 * @param {Number} truncateLen The length to truncate the string at.
 * @param {String} [ellipsisChars=...] The ellipsis character(s) to add to the end of `str`
 *   when truncated. Defaults to '...'
 */
function TruncateEnd($anchorText, $truncateLen, $ellipsisChars = false) {
	if (strlen($anchorText) > $truncateLen) {
		
		if ($ellipsisChars === false) {
			$ellipsisChars = '&hellip;';
			$ellipsisLength = 3;
		} else {
			$ellipsisLength = strlen($ellipsisChars);
		}
		$anchorText = substr($anchorText, 0, $truncateLen - $ellipsisLength) + $ellipsisChars;
	}
	return $anchorText;
};

/**
 * Date: 2015-10-05
 * Author: Kasper Søfren <soefritz@gmail.com> (https://github.com/kafoso)
 *
 * A truncation feature, where the ellipsis will be placed in the dead-center of the URL.
 *
 * @param {String} url             A URL.
 * @param {Number} truncateLen     The maximum length of the truncated output URL string.
 * @param {String} ellipsisChars   The characters to place within the url, e.g. "..".
 * @return {String} The truncated URL.
 */
function TruncateMiddle($url, $truncateLen, $ellipsisChars = false){
	if (strlen($url) <= $truncateLen) {
		return $url;
	}
	if($ellipsisChars === false) {
		$ellipsisChars = '&hellip;';
		$ellipsisLengthBeforeParsing = 8;
		$ellipsisLength = 3;
	} else {
		$ellipsisLength = $ellipsisLengthBeforeParsing = strlen($ellipsisChars);
	}
	$availableLength = $truncateLen - $ellipsisLength;
	$end = "";
	if ($availableLength > 0) {
		$end = substr($url, intval(-1 * floor($availableLength / 2)));
	}
	return substr(substr($url, 0, intval(ceil($availableLength / 2 ))) . $ellipsisChars . $end, 0, $availableLength + $ellipsisLengthBeforeParsing);
};

/**
 * Date: 2015-10-05
 * Author: Kasper Søfren <soefritz@gmail.com> (https://github.com/kafoso)
 *
 * A truncation feature, where the ellipsis will be placed at a section within
 * the URL making it still somewhat human readable.
 *
 * @param {String} url						 A URL.
 * @param {Number} truncateLen		 The maximum length of the truncated output URL string.
 * @param {String} ellipsisChars	 The characters to place within the url, e.g. "...".
 * @return {String} The truncated URL.
 */
function TruncateSmart($url, $truncateLen, $ellipsisChars = false) {
	if( $ellipsisChars === false) {
		$ellipsisChars = '&hellip;';
		$ellipsisLength = 3;
		$ellipsisLengthBeforeParsing = 8;
	} else {
		$ellipsisLength = $ellipsisLengthBeforeParsing = strlen($ellipsisChars);
	}
	
	$parse_url = function($url){ // Functionality inspired by PHP function of same name
		$urlObj = Array();
		$urlSub = $url;
		$match = preg_match('/^([a-z]+):\/\//i', $urlSub);
		if ($match) {
			$urlObj['scheme'] = $match[1];
			$urlSub = substr($urlSub, strlen($match[0]));
		}
		$match = preg_match('/^(.*?)(?=(\?|#|\/|$))/i', $urlSub);
		if ($match) {
			$urlObj['host'] = $match[1];
			$urlSub = substr($urlSub, strlen($match[0]));
		}
		$match = preg_match('/^\/(.*?)(?=(\?|#|$))/i', $urlSub);
		if ($match) {
			$urlObj['path'] = $match[1];
			$urlSub = substr($urlSub, strlen($match[0]));
		}
		$match = preg_match('/^\?(.*?)(?=(#|$))/i', $urlSub);
		if ($match) {
			$urlObj['query'] = $match[1];
			$urlSub = substr($urlSub, strlen($match[0]));
		}
		$match = preg_match('/^#(.*?)$/i', $urlSub);
		if ($match) {
			$urlObj['fragment'] = $match[1];
			//$urlSub = substr($urlSub, strlen($match[0]));  -- not used. Uncomment if adding another block.
		}
		return $urlObj;
	};
	
	$buildUrl = function($urlObj){
		$url = "";
		if ($urlObj['scheme'] && $urlObj['host']) {
			$url .= $urlObj['scheme'] . "://";
		}
		if ($urlObj['host']) {
			$url .= $urlObj['host'];
		}
		if ($urlObj['path']) {
			$url .= "/" . $urlObj['path'];
		}
		if ($urlObj['query']) {
			$url .= "?" . $urlObj['query'];
		}
		if ($urlObj['fragment']) {
			$url .= "#" . $urlObj['fragment'];
		}
		return $url;
	};
	
	$buildSegment = function($segment, $remainingAvailableLength) {
		$remainingAvailableLengthHalf = $remainingAvailableLength / 2;
		$startOffset = ceil($remainingAvailableLengthHalf);
		$endOffset = floor($remainingAvailableLengthHalf) * -1;
		$end = "";
		if ($endOffset < 0) {
			$end = substr($segment, intval($endOffset));
		}
		return substr($segment, 0, intval($startOffset)) . $ellipsisChars . $end;
	};
	if (strlen($url) <= $truncateLen) {
		return $url;
	}
	$availableLength = $truncateLen - $ellipsisLength;
	$urlObj = $parse_url($url);
	// Clean up the URL
	if ($urlObj['query']) {
		$matchQuery = preg_match('/^(.*?)(?=(\?|\#))(.*?)$/i', $urlObj['query']);
		if ($matchQuery) {
			// Malformed URL; two or more "?". Removed any content behind the 2nd.
			$urlObj['query'] = substr($urlObj['query'], 0, strlen($matchQuery[1]));
			$url = $buildUrl($urlObj);
		}
	}
	if (strlen($url) <= $truncateLen) {
		return $url;
	}
	if ($urlObj['host']) {
		$urlObj['host'] = preg_replace('/^www\./', "", $urlObj['host']);
		$url = $buildUrl($urlObj);
	}
	if (strlen($url) <= $truncateLen) {
		return $url;
	}
	// Process and build the URL
	$str = "";
	if ($urlObj['host']) {
		$str .= $urlObj['host'];
	}
	if (strlen($str) >= $availableLength) {
		if (strlen($urlObj['host']) == $truncateLen) {
			return substr((substr($urlObj['host'], 0, $truncateLen - $ellipsisLength) . $ellipsisChars), 0, $availableLength + $ellipsisLengthBeforeParsing);
		}
		return substr($buildSegment($str, $availableLength), 0, $availableLength + $ellipsisLengthBeforeParsing);
	}
	$pathAndQuery = "";
	if ($urlObj['path']) {
		$pathAndQuery .= "/" . $urlObj['path'];
	}
	if ($urlObj['query']) {
		$pathAndQuery .= "?" . $urlObj['query'];
	}
	if ($pathAndQuery) {
		if (strlen($str . $pathAndQuery) >= $availableLength) {
			if (strlen($str . $pathAndQuery) == $truncateLen) {
				return substr($str . $pathAndQuery, 0, $truncateLen);
			}
			$remainingAvailableLength = $availableLength - substr($str);
			return substr(($str . $buildSegment($pathAndQuery, $remainingAvailableLength)), 0, $availableLength + $ellipsisLengthBeforeParsing);
		} else {
			$str .= $pathAndQuery;
		}
	}
	if ($urlObj['fragment']) {
		$fragment = "#" . $urlObj['fragment'];
		if (strlen($str . $fragment) >= $availableLength) {
			if (strlen($str . $fragment) == $truncateLen) {
				return substr(($str . $fragment), 0, $truncateLen);
			}
			$remainingAvailableLength2 = $availableLength - strlen($str);
			return substr(($str . $buildSegment($fragment, $remainingAvailableLength2)), 0, $availableLength + $ellipsisLengthBeforeParsing);
		} else {
			$str .= $fragment;
		}
	}
	if ($urlObj['scheme'] && $urlObj['host']) {
		$scheme = $urlObj['scheme'] . "://";
		if (strlen($str . $scheme) < $availableLength) {
			return substr(($scheme . $str), 0, $truncateLen);
		}
	}
	if (strlen($str) <= $truncateLen) {
		return $str;
	}
	$end = "";
	if ($availableLength > 0) {
		$end = substr($str, intval(-1 * floor($availableLength / 2)));
	}
	return substr((substr($str, 0, intval(ceil($availableLength / 2 ))) . $ellipsisChars . $end), 0, $availableLength + $ellipsisLengthBeforeParsing);
};

?>
