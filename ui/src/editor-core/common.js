// COMMON Functions Module
module.exports = {

    // Tooltips flag
    displayTooltips: true,

    /**
     * Show loading screen
     */
    showLoadingScreen: function(cloneName = 'genericLoadingScreen') {
        let bumpVal = $('.loading-overlay.loading').data('bump') || 0;
        bumpVal++;
        
        if(bumpVal <= 1) {
            $('.loading-overlay').addClass('loading').show();
        }

        $('.loading-overlay').data('bump', bumpVal++);
    },

    /**
     * Hide loading screen
     */
    hideLoadingScreen: function(cloneName = 'genericLoadingScreen') {
        let bumpVal = $('.loading-overlay.loading').data('bump') || 1;
        bumpVal--;

        if(bumpVal <= 0) {
            $('.loading-overlay.loading').removeClass('loading');
        }

        $('.loading-overlay').data('bump', bumpVal);
    },

    /**
     * Refresh (enable/disable) Tooltips
     */
    reloadTooltips: function(container, forcedOption = null) {
        // Use global var or option
        let enableTooltips = (forcedOption != null) ? forcedOption : this.displayTooltips;

        container.tooltip('dispose').tooltip({
            boundary: 'window',
            trigger: 'hover',
            selector: (enableTooltips) ? '[data-toggle="tooltip"]:not(:disabled)' : '[data-toggle="tooltip"].tooltip-always-on:not(:disabled)'
        });

        // Remove rogue/detached tooltips
        container.find('.tooltip').remove();
    },

    /**
     * Format time
     * @param {String} timeInSeconds
     */
    timeFormat: function(timeInSeconds) {
        var h = Math.floor(timeInSeconds / 3600);
        var m = Math.floor(timeInSeconds % 3600 / 60);
        var s = Math.floor(timeInSeconds % 3600 % 60);

        var zeroBefore = function(time) {

            if(time < 10) {
                time = '0' + time;
            }
            return time;
        };

        var hDisplay = h > 0 ? zeroBefore(h)+ ':' : '';
        var mDisplay = (m > 0 || hDisplay != '') ? zeroBefore(m) + ':' : '';
        var sDisplay = mDisplay != '' ? zeroBefore(s) : s;

        return hDisplay + mDisplay + sDisplay;
    }
};
