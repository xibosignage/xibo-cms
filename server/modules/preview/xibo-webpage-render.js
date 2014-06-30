$(document).ready(function() { 

    isMSIE = navigator.userAgent.match(/MSIE/),
    MSIEVersion = navigator.userAgent.match(/MSIE (\d\.\d+)/) ? parseInt(RegExp.$1, 10) : null

    if (options.previewWidth == 0 && options.previewHeight == 0) {
        options.width = $(window).width();
        options.height = $(window).height();
    }
    else {
        // We are a preview
        options.width = options.previewWidth;
        options.height = options.previewHeight;
    }

    // Scale Factor
    options.scaleFactor = Math.min(options.width / options.originalWidth, options.height / options.originalHeight);

    // We need to scale the scale according to the size difference between the layout designer and the actual request size.
    if (options.scale_override != 1) {
        options.offsetTop = options.offsetTop * options.scaleFactor;
        options.offsetLeft = options.offsetLeft * options.scaleFactor;
        options.scale = options.scale * options.scaleFactor;
    }

    // Width should take into account the offset
    options.width = parseInt(options.width) + parseInt(options.offsetLeft);
    options.height = parseInt(options.height) + parseInt(options.offsetTop);

    // Add the width and height on the wrap.
    $("#wrap").css({
        "overflow": "hidden",
        "width": options.width,
        "height": options.height
    });

    // Margins on frame
    $("#iframe").css({"margin-top": -1 * options.offsetTop, "margin-left": -1 * options.offsetLeft});

    // Transform on the frame
    if (options.scale != 1) {

        if (isMSIE) {
            $("#iframe").css({
                zoom: options.scale,
                height: parseInt((options.height / options.scale) *  (1 / (MSIEVersion >= 9 ? 1 : options.scale)), 10),
                width: parseInt((options.width / options.scale) * (1 / (MSIEVersion >= 9 ? 1 : options.scale)), 10)
            })
        }
        else {

            $("#iframe").css({
                        'transform-origin': "0 0",
                '-webkit-transform-origin': "0 0",
                   '-moz-transform-origin': "0 0",
                     '-o-transform-origin': "0 0",
                        'transform': 'scale(' + options.scale + ')',
                '-webkit-transform': 'scale(' + options.scale + ')',
                   '-moz-transform': 'scale(' + options.scale + ')',
                     '-o-transform': 'scale(' + options.scale + ')',
                "width": options.width / options.scale,
                "height": options.height / options.scale
            });
        }
    }
});