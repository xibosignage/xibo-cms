// COMMON Functions Module
module.exports = {

    // Tooltips flag
    displayTooltips: true,

    /**
     * Show loading screen
     */
    showLoadingScreen: function(cloneName = 'genericLoadingScreen') {
        
        // Create a loading overlay clone, gave it a ID and append it to the same DOM object as the original
        let clone = $('.loading-overlay').clone();
        clone.attr('id', cloneName).addClass('loading').show();
        clone.appendTo($('.loading-overlay').parent());
    },

    /**
     * Hide loading screen
     */
    hideLoadingScreen: function(cloneName = 'genericLoadingScreen') {

        // Remove generic or named clone
        $('.loading-overlay#' + cloneName).remove();
    },

    /**
     * Refresh ( enable/disable) Tooltips
     */
    reloadTooltips: function(container, forcedOption = null) {
        // Use global var or option
        let enableTooltips = (forcedOption != null) ? forcedOption : this.displayTooltips;

        container.tooltip('destroy').tooltip({
            selector: (enableTooltips) ? '[data-toggle="tooltip"]' : '[data-toggle="tooltip"].tooltip-always-on'
        });
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
