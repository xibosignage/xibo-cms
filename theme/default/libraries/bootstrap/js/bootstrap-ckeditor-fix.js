// bootstrap-ckeditor-fix.js
// hack to fix ckeditor/bootstrap compatibility bug when ckeditor appears in a bootstrap modal dialog
//
// Include this file AFTER both jQuery and bootstrap are loaded.

$.fn.modal.Constructor.prototype.enforceFocus = function() {
  modal_this = this
  $(document).on('focusin.modal', function (e) {
    if (modal_this.$element[0] !== e.target && !modal_this.$element.has(e.target).length 
    // add whatever conditions you need here:
    && !$(e.target.parentNode).hasClass('cke')
    && !$(e.target.parentNode).hasClass('cke_dialog_ui_input_text')  ) {
      modal_this.$element.focus()
    }
  })
};