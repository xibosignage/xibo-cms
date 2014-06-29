/*
 * jQuery Zoomer v1.1
 *
 * By HubSpot  >('_')<
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Example usage:

$('iframe').zoomer({ width: 200, zoom: 0.5 });

 *
 */

(function($){

    var methods,
        pluginName = 'zoomer',
        defaults = {
            width: 'auto',
            height: 'auto',
            zoom: 0.4,
            tranformOrigin: '0 0',
            //loading
            loadingType: 'message', // other type: 'spinner'
            loadingMessage: '',
            spinnerURL: 'http://oi46.tinypic.com/6y375z.jpg', // requires loadingType: 'spinner'
            //hover
            message: 'Open Page',
            ieMessageButtonClass: 'btn btn-secondary', // used in IE only
            messageURL: false,
            onComplete: function() {}
        },
        visible = {
            visibility: 'visible'
        },
        invisible = {
            visibility: 'hidden'
        },
        unselectable = {
            '-webkit-user-select': 'none',
            '-khtml-user-select':  'none',
            '-moz-user-select':    'none',
            '-o-user-select':      'none',
            'user-select': 'none',
            'overflow': 'hidden'
        },
        absolute = {
            top: 0,
            position: 'absolute'
        },
        relative = {
            position: 'relative'
        },
        isMSIE = navigator.userAgent.match(/MSIE/),
        MSIEVersion = navigator.userAgent.match(/MSIE (\d\.\d+)/) ? parseInt(RegExp.$1, 10) : null
    ;

    methods = {

        init: function(opts) {
            return this.each(function(){
                var $el = $(this),
                    options = $.extend({}, defaults, opts)
                ;

                options.src = $el.attr('src');

                $el.data(pluginName, options);

                $el[pluginName]('zoomer');
            });
        },

        zoomer: function() {
            var $el = $(this), options = $el.data(pluginName);

            $el
                .css(invisible)
                .css(unselectable)
            ;

            if (options.zoom === 'auto') {
                if (options.width === 'auto' && options.height === 'auto') {
                    $.error('jQuery.zoomer: You must set either zoom or height and width.');
                    return;
                }
                options.zoom = options.width / $(window).width();
            }

            if (options.width === 'auto') {
                options.width = $(window).height() * options.zoom;
            }

            if (options.height === 'auto') {
                options.height = $(window).height() * options.zoom;
            }

            if (options.loadingType === 'spinner') {
                options.loadingMessage = '<img style="padding: ' + parseInt((options.height - 17) / 2, 10) + 'px 0" src="' + options.spinnerURL + '" />';
            }

            //fix bug in older version of chrome:
            //http://stackoverflow.com/questions/5159713/
            if (navigator.userAgent.indexOf('Chrome/10.0.648') > -1) {
                options.zoom = Math.sqrt(1 / options.zoom);
            }

            options.externalSrc = true;

            

            $el[pluginName]('setUpWrapper');

            $el[pluginName]('zoom');

            return $el;
        },

        setUpWrapper: function() {
            var $el = $(this), options = $el.data(pluginName);

            if (!$el.parents('.zoomer-wrapper').length) {
                $el
                    .wrap(
                        $('<div/>')
                            .addClass('zoomer-wrapper')
                            .css(unselectable)
                            .css(relative)
                    )
                    .wrap(
                        $('<div/>')
                            .addClass('zoomer-small')
                            .css(invisible)
                            .css(unselectable)
                    )
                ;
            }

            options.zoomerWrapper = $el.parents('.zoomer-wrapper');

            options.zoomerSmall = $el.parents('.zoomer-small');

            options.zoomerCover = $('<div/>')
                .addClass('zoomer-cover')
                .css(unselectable)
                .css(absolute)
                .css({
                    textAlign: 'center',
                    fontSize: '15px'
                })
            ;

            options.zoomerLoader = $('<div/>')
                .addClass('zoomer-loader')
                .css(invisible)
                .css(unselectable)
                .css(absolute)
                .css({
                    textAlign: 'center',
                    fontSize: '15px',
                    lineHeight: (parseInt(options.height, 10) - parseInt((options.height - 80) / 10, 10)) + 'px',
                    background: '#fff'
                })
                .html(options.loadingMessage)
            ;

            options.zoomerWrapper
                .append(options.zoomerCover)
                .append(options.zoomerLoader)
            ;

            if (isMSIE) { options.zoomerLoader.css(invisible); }

            return $el[pluginName]('updateWrapper')[pluginName]('fadeOut');
        },

        updateWrapper: function() {
            var $el = $(this), options = $el.data(pluginName);

            $.each([options.zoomerWrapper.get(0), options.zoomerCover.get(0), options.zoomerLoader.get(0), options.zoomerSmall.get(0)], function(){
                $(this).css({
                    height: options.height,
                    width: options.width
                });
            });

            return $el;
        },

        fadeIn: function() {
            var $el = $(this), options = $el.data(pluginName);

            if (isMSIE) { return $el; }

            $el.css(invisible);

            options.zoomerSmall
                .stop()
                .css('opacity', 0)
                .css(visible)
                .animate({ 'opacity': 1 }, 150, function(){
                    $el
                        .css(visible)
                        .css('opacity', 0)
                        .animate({ 'opacity': 1 }, 500)
                    ;
                })
            ;

            options.zoomerLoader
                .show()
                .animate({ 'opacity': 0 }, 300, function(){
                    $(this).hide();
                })
            ;

            return $el;
        },

        fadeOut: function() {
            var $el = $(this), options = $el.data(pluginName);

            if (isMSIE) { return $el; }

            options.zoomerSmall
                .stop()
                .animate({ 'opacity': 0 }, 300, function(){
                    $(this).css('visibility', 'hidden');
                })
            ;

            options.zoomerLoader
                .css('opacity', 0)
                .css(visible)
                .show()
                .animate({ opacity: 1 }, 100)
            ;

            return $el;
        },

        zoom: function() {
            var $el = $(this), options = $el.data(pluginName);

            if (isMSIE) {
                setTimeout(function(){
                    $el
                        .css({
                            zoom: options.zoom,
                            height: parseInt((options.height / options.zoom) *  (1 / (MSIEVersion >= 9 ? 1 : options.zoom)), 10),
                            width: parseInt((options.width / options.zoom) * (1 / (MSIEVersion >= 9 ? 1 : options.zoom)), 10)
                        })
                        .css(visible)
                    ;

                    options.zoomerLink.remove();

                    options.zoomerCover
                        .unbind('hover mouseover mouseout')
                        .addClass(options.ieMessageButtonClass)
                        .html(options.message)
                        .css({
                            width: 94,
                            height: 14,
                            fontSize: 12,
                            padding: '6px 18px 6px 18px',
                            top: parseInt(options.height - (12 + (2 * 6) + 2 + 10), 10),
                            left: parseInt((options.width - (94 + (2 * 18))) / 2, 10)
                        })
                        .show()
                    ;

                    if (!options.click) {
                        options.click = function() {
                            location.href = options.messageURL || options.src;
                        };
                    }

                    options.zoomerCover.unbind('click').bind('click', options.click);

                    options.onComplete($el);
                }, 1000);

                return $el;
            }

            if (options.externalSrc) {
                $el
                    .css({
                        height: options.height / options.zoom,
                        width: options.width / options.zoom,
                                'transform-origin': options.tranformOrigin,
                        '-webkit-transform-origin': options.tranformOrigin,
                           '-moz-transform-origin': options.tranformOrigin,
                             '-o-transform-origin': options.tranformOrigin,
                                'transform': 'scale(' + options.zoom + ')',
                        '-webkit-transform': 'scale(' + options.zoom + ')',
                           '-moz-transform': 'scale(' + options.zoom + ')',
                             '-o-transform': 'scale(' + options.zoom + ')'
                    })
                    .css(visible)
                ;

                $el[pluginName]('fadeIn');

                options.onComplete($el);

                return $el;
            }

            $el
                .css({
                    height: options.height / options.zoom,
                    width: options.width / options.zoom
                })
                .load(function(){
                    $el.contents().find('html').css({
                                'transform-origin': options.tranformOrigin,
                        '-webkit-transform-origin': options.tranformOrigin,
                           '-moz-transform-origin': options.tranformOrigin,
                             '-o-transform-origin': options.tranformOrigin,
                                'transform': 'scale(' + options.zoom + ')',
                        '-webkit-transform': 'scale(' + options.zoom + ')',
                           '-moz-transform': 'scale(' + options.zoom + ')',
                             '-o-transform': 'scale(' + options.zoom + ')'
                    });

                    $el[pluginName]('fadeIn');

                    options.onComplete($el);
                })
            ;

            return $el;
        },

        src: function(src) {
            var $el = $(this),
                options = $el.data(pluginName)
            ;

            options.src = src;

            $el[pluginName]('fadeOut').attr('src', src);

            return $el;
        },

        refresh: function() {
            var $el = $(this), options = $el.data(pluginName);

            return $el[pluginName]('src', options.src);
        },

        zoomedBodyHeight: function() {
            var $el = $(this), options = $el.data(pluginName);

            if (options.externalSrc) {
                return $.error('jQuery.zoomer: cannot access bodyHeight of an external iFrame');
            }

            return options.zoom * $($el.get(0).contentWindow.document).height();
        }
    };

    $.fn[pluginName] = function(options) {
        if (methods[options]) {
            return methods[options].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof options === 'object' || ! options) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('jQuery.' + pluginName + ': Method ' +  options + ' does not exist');
        }
    };

})(jQuery);