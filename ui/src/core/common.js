// COMMON Functions Module
module.exports = {
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
    }
};
