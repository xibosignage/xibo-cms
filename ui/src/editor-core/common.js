// COMMON Functions Module
module.exports = {

  // Tooltips flag
  displayTooltips: true,

  // Show delete confirmation modals
  deleteConfirmation: true,

  /**
     * Show loading screen
     * @param {string} cloneName - Screen tag
     */
  showLoadingScreen: function() {
    let bumpVal = $('.loading-overlay.loading').data('bump') || 0;
    bumpVal++;

    if (bumpVal <= 1) {
      $('.loading-overlay').addClass('loading').fadeIn(400);
      // TODO: Alert message disabled for now
      // it clashes with the user timeout
      // window.onbeforeunload = () => editorsTrans.onbeforeunload;
    }

    $('.loading-overlay').data('bump', bumpVal++);
  },

  /**
     * Hide loading screen
     * @param {string} cloneName - Screen tag
     */
  hideLoadingScreen: function() {
    let bumpVal = $('.loading-overlay.loading').data('bump') || 1;
    bumpVal--;

    if (bumpVal <= 0) {
      $('.loading-overlay.loading').fadeOut(400, function(el) {
        $(el).removeClass('loading');
        // TODO: Alert message disabled for now
        // it clashes with the user timeout
        // window.onbeforeunload = null;
      });
    }

    $('.loading-overlay').data('bump', bumpVal);
  },

  /**
     * Refresh (enable/disable) Tooltips
     * @param {object} container - Container object
     * @param {boolean} forcedOption - Force option
     * @param {object =} [options] - Options
     * @param {object/boolean} [options.forcedOption = null] - Force option
     * @param {object/string=} [options.placement = 'auto']
     */
  reloadTooltips: function(
    container,
    {
      forcedOption = null,
      placement = 'auto',
    } = {},
  ) {
    // Use global var or option
    const enableTooltips =
      (forcedOption != null) ? forcedOption : this.displayTooltips;

    const tooltipSelector = (enableTooltips) ?
      '[data-toggle="tooltip"]:not(:disabled)' :
      '[data-toggle="tooltip"].tooltip-always-on:not(:disabled)';

    // Disable all tooltips first
    $(container).find('[data-toggle="tooltip"]').tooltip('dispose');

    // Enable tooltips by selector
    $(container).find(tooltipSelector).tooltip({
      boundary: 'window',
      trigger: 'hover',
      placement: placement,
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
     * @param {boolean} alwaysShowMinutes - Always show 00:00 even with < 60s
     * @return {String} Formatted time
     */
  timeFormat: function(
    timeInSeconds,
    alwaysShowMinutes = true,
  ) {
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
    const mDisplay = (m > 0 || hDisplay != '' || alwaysShowMinutes) ?
      zeroBefore(m) + ':' : '';
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

  /**
     * Check if object has specific target in data
     * @param {object} object - object to check
     * @param {string[]} targetType - Target to check
     * @return {boolean}
      */
  hasTarget: function(object, targetType) {
    // Get target data
    let targetData = $(object).data('target');

    // If target data is not defined, return false
    if (targetData == undefined) {
      return false;
    }

    // If target type isn't an array, make it one
    if (!Array.isArray(targetData)) {
      targetData = targetData.split(' ');
    }

    // If target is 'all', return true
    if (targetData.indexOf('all') !== -1) {
      return true;
    } else if (
      targetData.indexOf(targetType) !== -1
    ) {
      return true;
    }
    return false;
  },

  /**
     * Clear UI elements from container
     * @param {object} $container
      */
  clearContainer: function($container) {
    // Flatpickr
    $container.find('.flatpickr-input').each((_idx, fp) => {
      if (fp._flatpickr) {
        fp._flatpickr.destroy();
      }
    });

    // Select2
    $container.find('select[data-select2-id]')
      .select2('destroy');

    // Colorpicker
    $container.find('.colorpicker-element').colorpicker('destroy');

    // JqueryUI
    $container.is('.ui-droppable') && $container.droppable('destroy');
    $container.find('.ui-droppable').droppable('destroy');
    $container.find('.ui-draggable').draggable('destroy');
    $container.find('.ui-sortable').sortable('destroy');

    // Masonry
    $container.find('.masonry-container').masonry('destroy');

    // Monaco code editor
    $container.find('.xibo-code-input .code-input').each((_idx, fp) => {
      const codeInputId = $(fp).attr('id');
      // Unset events from text area
      $(fp).off('change');

      if (window.codeEditors[codeInputId]) {
        // Dispose of model
        window.codeEditors[codeInputId].getModel().dispose();

        // Dispose of editor
        window.codeEditors[codeInputId].dispose();

        // Remove array element
        window.codeEditors[codeInputId] = null;
        delete window.codeEditors[codeInputId];
      }
    });

    // CKEditor
    $container.find('.rich-text').each((_idx, fp) => {
      const richTextId = $(fp).attr('id');
      formHelpers.destroyCKEditor(richTextId);
    });
  },

  /**
   * Handle minimum dimensions for the editor
   * @param {object} editor
   * @param {boolean} forceReload
    */
  handleEditorMinimumDimensions: function(editor, forceReload) {
    const resizeThrottle = 60;
    const minWindowWidth = 1200;
    const minWindowHeight = 600;
    const toolbarLevelLimiter = 1600;

    const updateEditor = function() {
      // Calculate dimensions
      // ( using device scale to match the real dimensions )
      const currentWidth = $(window).width() * window.devicePixelRatio;
      const currentHeight = $(window).height() * window.devicePixelRatio;
      const editorInitalState = editor.showMinDimensionsMessage;
      const toolbarInitalState = editor.toolbar.levelLimiter;

      // Check if option is disabled in local storage
      const minSizeWarningOff = localStorage.getItem('minSizeWarningOff');

      // If editor container is empty object
      // stop and detach event handler
      if ($.isEmptyObject(editor.editorContainer)) {
        $(window).off('resize.' + editor.mainObjectType);
        return;
      }

      // Show editor or message?
      editor.showMinDimensionsMessage = (
        currentWidth < minWindowWidth ||
        currentHeight < minWindowHeight
      );

      // Limit toolbar
      editor.toolbar.levelLimiter = (currentWidth < toolbarLevelLimiter);

      // If status changed, refresh editor
      if (editorInitalState != editor.showMinDimensionsMessage || forceReload) {
        // Show the minimum dimensions message instead
        if (
          editor.showMinDimensionsMessage &&
          !minSizeWarningOff
        ) {
          editor.editorContainer.append(`<div class="min-res-message">
            <div>
              <h4>${editorsTrans.minDimensionsMessageHeader}</h4>
              <div>${editorsTrans.minDimensionsMessageBody}</div>
              <button class="close-res-message-button btn btn-outline-primary">
                  ${editorsTrans.minDimensionsMessageHide}
              </button>
            </div>
          </div>`);

          // Create overlay
          const $customOverlay = $('.custom-overlay').clone();
          $customOverlay.removeClass('custom-overlay')
            .addClass('custom-overlay-clone min-res-overlay');
          $customOverlay.appendTo(editor.editorContainer);

          // Click X button to dismiss and save preference
          editor.editorContainer.find(
            '.min-res-message .close-res-message-button',
          ).on('click', function() {
            editor.minSizeWarningOff = true;
            // Save preference to local storage
            localStorage.setItem('minSizeWarningOff', true);

            // Reload message function to close it
            editor.common.handleEditorMinimumDimensions(editor, true);

            // Reload viewer if exists
            (editor.viewer) && editor.viewer.render();
          });
        } else {
          // Hide message container
          editor.editorContainer.find('.min-res-message').remove();

          // Remove overlay
          editor.editorContainer.find('.min-res-overlay').remove();
        }
      } else if (toolbarInitalState != editor.toolbar.levelLimiter) {
        // If toolbar changed, and we didn't refresh editor, reload it
        editor.toolbar.render();
      }
    };

    // Calculate on window resize
    $(window).on('resize.' + editor.mainObjectType,
      _.debounce(updateEditor, resizeThrottle));

    // Calculate on first run
    updateEditor();
  },
};
