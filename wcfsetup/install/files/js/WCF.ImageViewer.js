/**
 * ImageViewer for WCF.
 * Based upon "Slimbox 2" by Christophe Beyls 2007-20120, http://www.digitalia.be/software/slimbox2, MIT-style license.
 * 
 * @author	Alexander Ebert
 * @copyright	2001-2012 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 */
WCF.ImageViewer = Class.extend({
	/**
	 * Initializes the ImageViewer for every a-tag with the attribute rel = imageviewer.
	 */
	init: function() {
		WCF.DOMNodeInsertedHandler.addCallback('WCF.ImageViewer', $.proxy(this._domNodeInserted, this));
		
		WCF.DOMNodeInsertedHandler.enable();
		
		// navigation buttons
		$('<span><span class="icon icon24 icon-chevron-left jsTooltip" title="' + WCF.Language.get('wcf.imageViewer.previous') + '" /></span>').appendTo($('#lbPrevLink'));
		$('<span><span class="icon icon24 icon-chevron-right jsTooltip" title="' + WCF.Language.get('wcf.imageViewer.next') + '" /></span>').appendTo($('#lbNextLink'));
		
		// close and enlarge icons
		$('<span class="icon icon24 icon-remove jsTooltip" title="' + WCF.Language.get('wcf.imageViewer.close') + '" />').appendTo($('#lbCloseLink'));
		var $buttonEnlarge = $('<span class="icon icon24 icon-resize-full jsTooltip" title="' + WCF.Language.get('wcf.imageViewer.enlarge') + '" id="lbEnlarge" />').insertAfter($('#lbCloseLink'));
		
		WCF.DOMNodeInsertedHandler.disable();
		
		// handle enlarge button
		$buttonEnlarge.click($.proxy(this._enlarge, this));
		
		this._initImageViewer();
	},
	
	/**
	 * Executes actions upon DOMNodeInserted events.
	 */
	_domNodeInserted: function() {
		this._initImageSizeCheck();
		this._initImageViewer();
	},
	
	/**
	 * Initializes the image viewer for all links with class ".jsImageViewer"
	 */
	_initImageViewer: function() {
		WCF.DOMNodeInsertedHandler.enable();
		
		$('a.jsImageViewer').each(function(index, link) {
			var $link = $(link).removeClass('jsImageViewer');
			$link.slimbox({
				counterText: WCF.Language.get('wcf.imageViewer.counter'),
				loop: true
			});
		});
		
		WCF.DOMNodeInsertedHandler.disable();
	},
	
	/**
	 * Redirects to image for full view.
	 */
	_enlarge: function() {
		var $url = $('#lbImage').css('backgroundImage');
		if ($url) {
			$url = $url.substring(4, $url.length - 1);
			window.location = $url;
		}
	},
	
	/**
	 * Initializes the image size check.
	 */
	_initImageSizeCheck: function() {
		$('.jsResizeImage').on('load', $.proxy(this._checkImageSize, this));
	},
	
	/**
	 * Checks the image size.
	 */
	_checkImageSize: function(event) {
		var $image = $(event.currentTarget);
		$image.removeClass('jsResizeImage');
		var $dimensions = $image.getDimensions();
		var $maxWidth = $image.parents('div').innerWidth();
		
		if ($dimensions.width > $maxWidth) {
			$image.css({
				height: Math.round($dimensions.height * ($maxWidth / $dimensions.width)) + 'px',
				width: $maxWidth + 'px'
			});
			
			if (!$image.parents('a').length) {
				$image.wrap('<a href="' + $image.attr('src') + '" />');
				$image.parent().slimbox();
			}
		}
	}
});