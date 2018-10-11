// COMMON Functions Module
module.exports = {
    /**
     * Show loading screen
     */
    showLoadingScreen: function() {
        $('.loading-overlay').addClass('loading');
        $('.loading-overlay').show();
    },

    /**
     * Hide loading screen
     */
    hideLoadingScreen: function() {
        $('.loading-overlay').removeClass('loading');
        $('.loading-overlay').hide();
    }
};
