// COMMON Functions Module
module.exports = {

  // Tooltips flag
  displayTooltips: true,

  /**
     * Show loading screen
     * @param {string} cloneName - Screen tag
     */
  showLoadingScreen: function(cloneName = 'genericLoadingScreen') {
    let bumpVal = $('.loading-overlay.loading').data('bump') || 0;
    bumpVal++;

    if (bumpVal <= 1) {
      $('.loading-overlay').addClass('loading').show();
    }

    $('.loading-overlay').data('bump', bumpVal++);
  },

  /**
     * Hide loading screen
     * @param {string} cloneName - Screen tag
     */
  hideLoadingScreen: function(cloneName = 'genericLoadingScreen') {
    let bumpVal = $('.loading-overlay.loading').data('bump') || 1;
    bumpVal--;

    if (bumpVal <= 0) {
      $('.loading-overlay.loading').removeClass('loading');
    }

    $('.loading-overlay').data('bump', bumpVal);
  },

  /**
     * Refresh (enable/disable) Tooltips
     * @param {object} container - Container object
     * @param {boolean} forcedOption - Force option
     */
  reloadTooltips: function(container, forcedOption = null) {
    // Use global var or option
    const enableTooltips =
      (forcedOption != null) ? forcedOption : this.displayTooltips;

    container.tooltip('dispose').tooltip({
      boundary: 'window',
      trigger: 'hover',
      selector: (enableTooltips) ?
        '[data-toggle="tooltip"]:not(:disabled)' :
        '[data-toggle="tooltip"].tooltip-always-on:not(:disabled)',
    });

    // Remove rogue/detached tooltips
    this.clearTooltips();
  },

  /**
     * Clear Tooltips
     */
  clearTooltips: function() {
    // Remove rogue/detached tooltips
    $('body').find('.tooltip, .popover:not(.tour)').remove();
  },

  /**
     * Format time
     * @param {String} timeInSeconds - Time in seconds
     * @return {String} Formatted time
     */
  timeFormat: function(timeInSeconds) {
    const h = Math.floor(timeInSeconds / 3600);
    const m = Math.floor(timeInSeconds % 3600 / 60);
    const s = Math.floor(timeInSeconds % 3600 % 60);

    const zeroBefore = function(time) {
      if (time < 10) {
        time = '0' + time;
      }
      return time;
    };

    const hDisplay = h > 0 ? zeroBefore(h) + ':' : '';
    const mDisplay = (m > 0 || hDisplay != '') ? zeroBefore(m) + ':' : '';
    const sDisplay = mDisplay != '' ? zeroBefore(s) : s;

    return hDisplay + mDisplay + sDisplay;
  },

  /**
     * Format file size
     * @param {String} value - File size in bytes
     * @return {String} Formatted file size
     */
  formatFileSize: function(value) {
    return (
      b = Math, c = b.log, d = 1e3, e = c(value) / c(d) | 0, value / b.pow(d, e)
    ).toFixed(2) + ' ' + (e ? 'kMGTPEZY'[--e] + 'B' : 'Bytes');
  },

  /**
     * Get a module by type
     * @param {string} type - Type of media
     * @return {object} Module
     */
  getModuleByType: function(type) {
    return modulesList.find((module) => module.type === type);
  },
};
