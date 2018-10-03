<?php
/**
 * Represents an HTML tag, which can be used to easily build/modify HTML tags programmatically.
 *
 * Autolinker uses this abstraction to create HTML tags, and then write them out as strings. You may also use
 * this class in your code, especially within a {@link Autolinker#replaceFn replaceFn}.
 *
 * ## Examples
 *
 * Example instantiation:
 *
 *     var tag = new Autolinker.HtmlTag( {
 *         tagName : 'a',
 *         attrs   : { 'href': 'http://google.com', 'class': 'external-link' },
 *         innerHtml : 'Google'
 *     } );
 *
 *     tag.toAnchorString();  // <a href="http://google.com" class="external-link">Google</a>
 *
 *     // Individual accessor methods
 *     tag.getTagName();                 // 'a'
 *     tag.getAttr( 'href' );            // 'http://google.com'
 *     tag.hasClass( 'external-link' );  // true
 *
 *
 * Using mutator methods (which may be used in combination with instantiation config properties):
 *
 *     var tag = new Autolinker.HtmlTag();
 *     tag.setTagName( 'a' );
 *     tag.setAttr( 'href', 'http://google.com' );
 *     tag.addClass( 'external-link' );
 *     tag.setInnerHtml( 'Google' );
 *
 *     tag.getTagName();                 // 'a'
 *     tag.getAttr( 'href' );            // 'http://google.com'
 *     tag.hasClass( 'external-link' );  // true
 *
 *     tag.toAnchorString();  // <a href="http://google.com" class="external-link">Google</a>
 *
 *
 * ## Example use within a {@link Autolinker#replaceFn replaceFn}
 *
 *     var html = Autolinker.link( "Test google.com", {
 *         replaceFn : function( match ) {
 *             var tag = match.buildTag();  // returns an {@link Autolinker.HtmlTag} instance, configured with the Match's href and anchor text
 *             tag.setAttr( 'rel', 'nofollow' );
 *
 *             return tag;
 *         }
 *     } );
 *
 *     // generated html:
 *     //   Test <a href="http://google.com" target="_blank" rel="nofollow">google.com</a>
 *
 *
 * ## Example use with a new tag for the replacement
 *
 *     var html = Autolinker.link( "Test google.com", {
 *         replaceFn : function( match ) {
 *             var tag = new Autolinker.HtmlTag( {
 *                 tagName : 'button',
 *                 attrs   : { 'title': 'Load URL: ' + match.getAnchorHref() },
 *                 innerHtml : 'Load URL: ' + match.getAnchorText()
 *             } );
 *
 *             return tag;
 *         }
 *     } );
 *
 *     // generated html:
 *     //   Test <button title="Load URL: http://google.com">Load URL: google.com</button>
 */
class HtmlTag extends Util {

	/**
	 * @cfg {String} tagName
	 *
	 * The tag name. Ex: 'a', 'button', etc.
	 *
	 * Not required at instantiation time, but should be set using {@link #setTagName} before {@link #toAnchorString}
	 * is executed.
	 */
	protected $tagName = '';

	/**
	 * @cfg {Object.<String, String>} attrs
	 *
	 * An key/value Object (map) of attributes to create the tag with. The keys are the attribute names, and the
	 * values are the attribute values.
	 */
	protected $attrs;

	/**
	 * @cfg {String} $outerHTML
	 *
	 * Alias of {@link #innerHtml}, accepted for consistency with the browser DOM api, but prefer the camelCased version
	 * for acronym names.
	 */
	protected $innerHtml = '';

	/**
	 * @property {RegExp} whitespaceRegex
	 *
	 * Regular expression used to match whitespace in a string of CSS classes.
	 */
	protected $whitespaceRegex = '/\s+/';

	/**
	 * @param {Object} [cfg] The configuration properties for this class, in an Object (map)
	 */
	function __construct( $cfg ) {
		$this->assign( $cfg );
	}

	/**
	 * Sets the tag name that will be used to generate the tag with.
	 *
	 * @param {String} tagName
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function setTagName( $tagName ) {
		$this->tagName = $tagName;
	}

	/**
	 * Retrieves the tag name.
	 *
	 * @return {String}
	 */
	function getTagName() {
		return $this->tagName;
	}

	/**
	 * Sets an attribute on the HtmlTag.
	 *
	 * @param {String} attrName The attribute name to set.
	 * @param {String} attrValue The attribute value to set.
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function setAttr( $attrName, $attrValue ) {
		$this->attrs[ $attrName ] = $attrValue;
	}

	/**
	 * Retrieves an attribute from the HtmlTag. If the attribute does not exist, returns `undefined`.
	 *
	 * @param {String} attrName The attribute name to retrieve.
	 * @return {String} The attribute's value, or `undefined` if it does not exist on the HtmlTag.
	 */
	function getAttr( $attrName ) {
		return $this->attrs[ $attrName ];
	}

	/**
	 * Sets one or more attributes on the HtmlTag.
	 *
	 * @param {Object.<String, String>} attrs A key/value Object (map) of the attributes to set.
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function setAttrs( $attrs ) {
		foreach ($attrs as $attrName => $attrValue)
			$this->attrs[ $attrName ] = $attrValue;
	}

	/**
	 * Retrieves the attributes Object (map) for the HtmlTag.
	 *
	 * @return {Object.<String, String>} A key/value object of the attributes for the HtmlTag.
	 */
	function getAttrs() {
		return $this->attrs;
	}

	/**
	 * Sets the provided `cssClass`, overwriting any current CSS classes on the HtmlTag.
	 *
	 * @param {String} cssClass One or more space-separated CSS classes to set (overwrite).
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function setClass( $cssClass ) {
		$this->attrs['class'] = $cssClass;
	}

	/**
	 * Convenience method to add one or more CSS classes to the HtmlTag. Will not add duplicate CSS classes.
	 *
	 * @param {String} cssClass One or more space-separated CSS classes to add.
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function addClass( $cssClass ) {
		$classAttr = $this->attrs[ 'class' ];
		$classes   = !$classAttr ? [] : preg_split( '/\s+/', $classAttr );
		$newClasses = preg_split( '/\s+/', $cssClass );
		
		foreach ( $newClasses as $newClass) {
			if( array_search( $newClass, $classes ) === false ) {
				array_push( $classes, $newClass );
			}
		}
		$this->attrs[ 'class' ] = join( ' ', $classes );
	}

	/**
	 * Convenience method to remove one or more CSS classes from the HtmlTag.
	 *
	 * @param {String} cssClass One or more space-separated CSS classes to remove.
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function removeClass( $cssClass ) {
		$classAttr = $this->attrs[ 'class' ];
		$classes   = !$classAttr ? [] : preg_split( '/\s+/', $classAttr );
		$removeClasses = preg_split( '/\s+/', $cssClass );
		
		foreach ( $removeClasses as $removeClass) {
			$idx = array_search( $removeClass, $classes );
			if( $idx !== false ) {
				array_splice( $classes, $idx, 1 );
			}
		}
		$this->attrs[ 'class' ] = join( ' ', $classes );
	}

	/**
	 * Convenience method to retrieve the CSS class(es) for the HtmlTag, which will each be separated by spaces when
	 * there are multiple.
	 *
	 * @return {String}
	 */
	function getClass() {
		return $this->attrs[ 'class' ] ?: "";
	}

	/**
	 * Convenience method to check if the tag has a CSS class or not.
	 *
	 * @param {String} cssClass The CSS class to check for.
	 * @return {Boolean} `true` if the HtmlTag has the CSS class, `false` otherwise.
	 */
	 function hasClass( $cssClass ) {
		return strpos( ' '. $this->attrs[ 'class' ] .' ', ' '. $cssClass .' ') !== false;
	}

	/**
	 * Sets the outer HTML for the tag.
	 *
	 * @param {String} html The inner HTML to set.
	 * @return {Autolinker.HtmlTag} This HtmlTag instance, so that method calls may be chained.
	 */
	function setInnerHtml( $html ) {
		$this->innerHtml = $html;
	}

	/**
	 * Retrieves the inner HTML for the tag.
	 *
	 * @return {String}
	 */
	function getInnerHtml() {
		return $this->innerHtml;
	}

	/**
	 * Override of superclass method used to generate the HTML string for the tag.
	 *
	 * @return {String}
	 */
	function toAnchorString() {
		$tagName  = $this->tagName;
		$attrsStr = $this->buildAttrsStr();
		
		return join('', ['<', $tagName, $attrsStr, '>', $this->innerHtml, '</', $tagName, '>']);
	}

	/**
	 * Support method for {@link #toAnchorString}, returns the string space-separated key="value" pairs, used to populate
	 * the stringified HtmlTag.
	 *
	 * @return {String} Example return: `attr1="value1" attr2="value2"`
	 */
	protected function buildAttrsStr() {
		$attrsStr = '';
		// no `attrs` Object (map) has been set, return empty string
		if($attrs = $this->attrs) {
			foreach($attrs as $prop => $value) {
				// prepend a space if there are actually attributes
				$attrsStr .= ' '. $prop .'="'. $value .'"';
			}
		}
		return $attrsStr;
	}
};
