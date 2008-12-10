(function($)
{
	//If the UI scope is not available, add it
	$.ui = $.ui || {};

	$.fn.dialog = function(o) {
		return this.dialogInit(o).dialogOpen();
	}
	$.fn.dialogInit = function(o) {
		return this.each(function() {
			if (!$(this).is(".ui-dialog")) {
				$.ui.dialogInit(this, o);
			}			
		});
	}
	$.fn.dialogOpen = function() {
		return this.each(function() {
			var contentEl;
			if ($(this).parents(".ui-dialog").length) contentEl = this;
			if (!contentEl && $(this).is(".ui-dialog")) contentEl = $('.ui-dialog-content', this)[0];
			$.ui.dialogOpen(contentEl)
		});
	}
	$.fn.dialogClose = function() {
		return this.each(function() {
			var contentEl;
			var closeEl = $(this);
			if (closeEl.is('.ui-dialog-content')) {
				var contentEl = closeEl;
			} else if (closeEl.hasClass('ui-dialog')) {
				contentEl = closeEl.find('.ui-dialog-content');
			} else {
				contentEl = closeEl.parents('.ui-dialog:first').find('.ui-dialog-content');
			}
			$.ui.dialogClose(contentEl[0]);
		});
	}

	$.ui.dialogInit = function(el, o) {
		
		var options = {
			width: 300,
			height: 200,
			minWidth: 150,
			minHeight: 100,
			position: 'center',
			buttons: [],
			draggable: true,
			resizable: true
		};
		var o = o || {}; $.extend(options, o); //Extend and copy options
		this.element = el; var self = this; //Do bindings
		$.data(this.element, "ui-dialog", this);

		var uiDialogContent = $(el).addClass('ui-dialog-content');

		if (!uiDialogContent.parent().length) {
			uiDialogContent.appendTo('body');
		}
		uiDialogContent
			.wrap(document.createElement('div'))
			.wrap(document.createElement('div'));
		var uiDialogContainer = uiDialogContent.parent().addClass('ui-dialog-container').css({position: 'relative'});
		var uiDialog = uiDialogContainer.parent()
			.addClass('ui-dialog')
			.css({position: 'absolute', width: options.width, height: options.height, overflow: 'hidden'});
		
		if (options.resizable) {
			uiDialog.append("<div class='ui-resizable-n ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-s ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-e ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-w ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-ne ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-se ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-sw ui-resizable-handle'></div>")
				.append("<div class='ui-resizable-nw ui-resizable-handle'></div>");
			uiDialog.resizable({ maxWidth: options.maxWidth, maxHeight: options.maxHeight, minWidth: options.minWidth, minHeight: options.minHeight });
		}

		uiDialogContainer.prepend('<div class="ui-dialog-titlebar"></div>');
		var uiDialogTitlebar = $('.ui-dialog-titlebar', uiDialogContainer);
		var title = (options.title) ? options.title : (uiDialogContent.attr('title')) ? uiDialogContent.attr('title') : '';
		uiDialogTitlebar.append('<span class="ui-dialog-title">' + title + '</span>');
		uiDialogTitlebar.append('<div class="ui-dialog-titlebar-close"></div>');
		$('.ui-dialog-titlebar-close', uiDialogTitlebar)
			.hover(function() { $(this).addClass('ui-dialog-titlebar-close-hover'); }, 
			       function() { $(this).removeClass('ui-dialog-titlebar-close-hover'); })
			.mousedown(function(ev) {
				ev.stopPropagation();
			})
			.click(function() {
				self.close();
			});

		var l = 0;
		$.each(options.buttons, function() { l = 1; return false; });
		if (l == 1) {
			uiDialog.append('<div class="ui-dialog-buttonpane"></div>');
			var uiDialogButtonPane = $('.ui-dialog-buttonpane', uiDialog);
			$.each(options.buttons, function(name, value) {
				var btn = $(document.createElement('button')).text(name).click(value);
				uiDialogButtonPane.append(btn);
			});
		}
	
		if (options.draggable) {
			uiDialog.draggable({ handle: '.ui-dialog-titlebar' });
		}
	
		this.open = function() {
			uiDialog.appendTo('body');
			var wnd = $(window), doc = $(document), top = doc.scrollTop(), left = doc.scrollLeft();
			switch (options.position) {
				case 'center':
					top += (wnd.height() / 2) - (uiDialog.height() / 2);
					left += (wnd.width() / 2) - (uiDialog.width() / 2);
					break;
				case 'top':
					top += 0;
					left += (wnd.width() / 2) - (uiDialog.width() / 2);
					break;
				case 'right':
					top += (wnd.height() / 2) - (uiDialog.height() / 2);
					left += (wnd.width()) - (uiDialog.width());
					break;
				case 'bottom':
					top += (wnd.height()) - (uiDialog.height());
					left += (wnd.width() / 2) - (uiDialog.width() / 2);
					break;
				case 'left':
					top += (wnd.height() / 2) - (uiDialog.height() / 2);
					left += 0;
					break;
			}
			top = top < doc.scrollTop() ? doc.scrollTop() : top;
			uiDialog.css({top: top, left: left});
			uiDialog.show();
		};

		this.close = function() {
			uiDialog.hide();
		};

	}

	$.ui.dialogOpen = function(el) {
		$.data(el, "ui-dialog").open();
	}

	$.ui.dialogClose = function(el) {
		$.data(el, "ui-dialog").close();
	}

})(jQuery);
