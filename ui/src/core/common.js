// COMMON Functions Module
module.exports = {
    /**
     * Show loading screen
     */
    showLoadingScreen: function() {
        $('.custom-overlay').addClass('loading');
        $('.custom-overlay').show();
    },

    /**
     * Hide loading screen
     */
    hideLoadingScreen: function() {
        $('.custom-overlay').removeClass('loading');
        $('.custom-overlay').hide();
    }
};
