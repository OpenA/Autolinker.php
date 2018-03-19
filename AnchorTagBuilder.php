<?php
/**
 * @protected
 * @class AnchorTagBuilder
 * @extends Object
 *
 * Builds anchor (&lt;a&gt;) tags for the Autolinker utility when a match is
 * found.
 *
 * Normally this class is instantiated, configured, and used internally by an
 * {@link Autolinker} instance, but may actually be used indirectly in a
 * {@link Autolinker#replaceFn replaceFn} to create {@link Autolinker.HtmlTag HtmlTag}
 * instances which may be modified before returning from the
 * {@link Autolinker#replaceFn replaceFn}. For example:
 *
 *     var html = Autolinker.link( "Test google.com", {
 *         replaceFn : function( match ) {
 *             var tag = match.buildTag();  // returns an {@link Autolinker.HtmlTag} instance
 *             tag.setAttr( 'rel', 'nofollow' );
 *
 *             return tag;
 *         }
 *     } );
 *
 *     // generated html:
 *     //   Test <a href="http://google.com" target="_blank" rel="nofollow">google.com</a>
 */
class AnchorTagBuilder {

	/**
	 * @cfg {Boolean} newWindow
	 * @inheritdoc Autolinker#newWindow
	 */
	var $newWindow;
	/**
	 * @cfg {Object} truncate
	 * @inheritdoc Autolinker#truncate
	 */
	var $truncate;
	/**
	 * @cfg {String} className
	 * @inheritdoc Autolinker#className
	 */
	var $className;
	/**
	 * @constructor
	 * @param {Object} [cfg] The configuration options for the AnchorTagBuilder instance, specified in an Object (map).
	 */
	function __construct( $cfg = [] ) {
		$this->newWindow = $cfg['newWindow'];
		$this->truncate  = $cfg['truncate'];
		$this->className = $cfg['className'];
	}
	
	/**
	 * Generates the actual anchor (&lt;a&gt;) tag to use in place of the
	 * matched text, via its `match` object.
	 *
	 * @param {Match} match The Match instance to generate an
	 *   anchor tag from.
	 * @return {HtmlTag} The HtmlTag instance for the anchor tag.
	 */
	function build( $match ) {
		return new HtmlTag([
			'tagName'   => 'a',
			'attrs'     => $this->createAttrs( $match ),
			'innerHtml' => $this->processAnchorText( $match->getAnchorText() )
		]);
	}
	
	/**
	 * Creates the Object (map) of the HTML attributes for the anchor (&lt;a&gt;)
	 *   tag being generated.
	 *
	 * @protected
	 * @param {Autolinker.match.Match} match The Match instance to generate an
	 *   anchor tag from.
	 * @return {Object} A key/value Object (map) of the anchor tag's attributes.
	 */
	function createAttrs( $match ) {
		$attrs = Array(
			'href' => $match->getAnchorHref()  // we'll always have the `href` attribute
		);
		$cssClass = $this->createCssClass( $match );
		if( $cssClass ) {
			$attrs[ 'class' ] = $cssClass;
		}
		if( $this->newWindow ) {
			$attrs[ 'target' ] = '_blank';
			$attrs[ 'rel' ] = 'noopener noreferrer';
		}
		if( $this->truncate ) {
			if( $this->truncate['length'] < strlen($match->getAnchorText()) ) {
				$attrs[ 'title' ] = $attrs['href'];
			}
		}
		return $attrs;
	}

	/**
	 * Creates the CSS class that will be used for a given anchor tag, based on
	 * the `matchType` and the {@link #className} config.
	 *
	 * Example returns:
	 *
	 * - ""                                      // no {@link #className}
	 * - "myLink myLink-url"                     // url match
	 * - "myLink myLink-email"                   // email match
	 * - "myLink myLink-phone"                   // phone match
	 * - "myLink myLink-hashtag"                 // hashtag match
	 * - "myLink myLink-mention myLink-twitter"  // mention match with Twitter service
	 *
	 * @private
	 * @param {Autolinker.match.Match} match The Match instance to generate an
	 *   anchor tag from.
	 * @return {String} The CSS class string for the link. Example return:
	 *   "myLink myLink-url". If no {@link #className} was configured, returns
	 *   an empty string.
	 */
	function createCssClass( $match ) {
		$className = $this->className;
		
		if( !$className ) {
			return "";
		} else {
			$returnClasses = [ $className ];
			$cssClassSuffixes = $match->getCssClassSuffixes();
			for( $i = 0, $len = strlen($cssClassSuffixes); $i < $len; $i++ ) {
				array_push($returnClasses, $className .'-'. $cssClassSuffixes[ $i ] );
			}
			return join(' ', $returnClasses);
		}
	}
	
	/**
	 * Processes the `anchorText` by truncating the text according to the
	 * {@link #truncate} config.
	 *
	 * @private
	 * @param {String} anchorText The anchor tag's text (i.e. what will be
	 *   displayed).
	 * @return {String} The processed `anchorText`.
	 */
	function processAnchorText( $anchorText ) {
		$anchorText = $this->doTruncate( $anchorText );
		return $anchorText;
	}

	/**
	 * Performs the truncation of the `anchorText` based on the {@link #truncate}
	 * option. If the `anchorText` is longer than the length specified by the
	 * {@link #truncate} option, the truncation is performed based on the
	 * `location` property. See {@link #truncate} for details.
	 *
	 * @private
	 * @param {String} anchorText The anchor tag's text (i.e. what will be
	 *   displayed).
	 * @return {String} The truncated anchor text.
	 */
	function doTruncate( $anchorText ) {
		$truncate = $this->truncate;
		if( !$truncate || !$truncate['length'] )
			return $anchorText;
		
		$truncateLength = $truncate['length'];
		$truncateLocation = $truncate['location'];
		
		if( $truncateLocation === 'smart' ) {
			return TruncateSmart( $anchorText, $truncateLength );
		} else if( $truncateLocation === 'middle' ) {
			return TruncateMiddle( $anchorText, $truncateLength );
		} else {
			return TruncateEnd( $anchorText, $truncateLength );
		}
	}
};

?>
