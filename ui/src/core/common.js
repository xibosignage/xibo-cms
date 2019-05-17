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

        if(enableTooltips) {
            container.find('[data-toggle="tooltip"]').tooltip({delay: tooltipDelay});
        } else {
            container.find('[data-toggle="tooltip"]').tooltip({delay: tooltipDelay});
            container.find('[data-toggle="tooltip"]:not(.tooltip-always-on)').tooltip('destroy');
        }
    }
};
