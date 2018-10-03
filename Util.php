<?php
/**
 * @singleton
 *
 * A few utility methods for Autolinker.
 */
abstract class Util {

	/**
	 * Assigns (shallow copies) class properties with `$cfg`.
	 *
	 * @param {Array} cfg The named array.
	 */
	function assign( $cfg ) {
		$dest = (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PROTECTED);
		
		foreach ($dest as $obj) {
			$prop = $obj->name;
			if ( isset( $cfg[ $prop ] )) {
				$this->{ $prop } = $cfg[ $prop ];
			}
		}
	}

	/**
	 * Check class require properties.
	 *
	 * @param {String} args property names.
	 */
	function requireStrict( /* ... */ ) {
		$params = [];
		foreach (func_get_args() as $name) {
			if ($this->{ $name } === null) {
				array_push($params, "`$name`");
			}
		}
		if (count($params)) {
			throw new Exception( join(', ', $params) .' cfg required' );
		}
	}
};

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
