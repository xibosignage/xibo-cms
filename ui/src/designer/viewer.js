// VIEWER Module

// Load templates
const viewerTemplate = require('../templates/viewer.hbs');
const viewerNavbarTemplate = require('../templates/viewer-navbar.hbs');
const viewerLayoutPreview = require('../templates/viewer-layout-preview.hbs');
const loadingTemplate = require('../templates/loading.hbs');

/**
 * Viewer contructor
 * @param {object} container - the container to render the viewer to
 * @param {object} navbarContainer - the container to render the navbar to
 */
let Viewer = function(container, navbarContainer) {
    this.DOMObject = container;
    this.navbarContainer = navbarContainer;

    // Element dimensions inside the viewer container
    this.containerElementDimensions = null;

    // State of the inline editor (  0: off, 1: on, 2: edit )
    this.inlineEditorState = 0;
};

/**
 * Calculate element scale to fit inside the container
 * @param {object} element - original object to be rendered
 * @returns {object} Object containing dimensions for the object
 */
Viewer.prototype.scaleElement = function(element, container) {
    
    // Get container dimensions
    const containerDimensions = {
        width: container.width(),
        height: container.height()
    };

    // Get element dimensions
    let elementDimensions = {
        width: parseFloat((element.dimensions) ? element.dimensions.width : element.width),
        height: parseFloat((element.dimensions) ? element.dimensions.height : element.height),
        scale: 1,
        top: 0,
        left: 0
    };

    // Calculate ratio
    const elementRatio = elementDimensions.width / elementDimensions.height;
    const containerRatio = containerDimensions.width / containerDimensions.height;
    
    // Calculate scale factor
    if(elementRatio > containerRatio) { // element is more "landscapish" than the container
        // Scale is calculated using width
        elementDimensions.scale = containerDimensions.width / elementDimensions.width;
    } else { // Same ratio or the container is the most "landscapish"
        // Scale is calculated using height
        elementDimensions.scale = containerDimensions.height / elementDimensions.height;
    }

    // Calculate new values for the element using the scale factor
    elementDimensions.width *= elementDimensions.scale;
    elementDimensions.height *= elementDimensions.scale;

    // Calculate top and left values to centre the element in the container
    elementDimensions.top = containerDimensions.height / 2 - elementDimensions.height / 2;
    elementDimensions.left = containerDimensions.width / 2 - elementDimensions.width / 2;

    // Get scaled background
    //elementDimensions.backgroundCSS = layoutClone.backgroundCss(layoutClone.scaledDimensions.width, layoutClone.scaledDimensions.height);

    return elementDimensions;
};

/**
 * Render Viewer
 * @param {Object} element - the object to be rendered
 * @param {number=} page - page to render on the viewer, default to 1
 */
Viewer.prototype.render = function(element, layout, page = 1) {

    // Show loading template
    this.DOMObject.html(loadingTemplate());

    // Reset container properties
    this.DOMObject.css('background', '#111');
    this.DOMObject.css('border', 'none');

    // Reset Navbar if exists
    if(this.navbarContainer != null && this.navbarContainer != undefined) {
        this.navbarContainer.removeClass();
        this.navbarContainer.html('');
    }
    
    let requestPath = urlsForApi[element.type]['preview'].url;

    // Get target element( get region if element is a Widget type )
    const targetElement = (element.type === 'widget') ? lD.layout.regions[element.regionId] : element;

    // Apply viewer scale to the layout
    this.containerElementDimensions = this.scaleElement(targetElement, this.DOMObject);

    requestPath = requestPath.replace(':id', targetElement[targetElement.type + 'Id']);
    
    // Render layout
    if(element.type === 'layout') {

        const html = viewerTemplate({
            renderLayout: true,
            containerStyle: 'layout-player',
            dimensions: this.containerElementDimensions
        });

        // Replace container html
        this.DOMObject.html(html);

        // Render background image or color to the preview
        if(layout.backgroundImage === null) {
            this.DOMObject.find('.viewer-element').css('background', targetElement.backgroundColor);
        } else {
            // Get API link
            let linkToAPI = urlsForApi['layout']['downloadBackground'].url;
            // Replace ID in the link
            linkToAPI = linkToAPI.replace(':id', element.layoutId);

            this.DOMObject.find('.viewer-element').css('background', "url('" + linkToAPI + "?preview=1&width=" + (layout.width * this.containerElementDimensions.scale) + "&height=" + (layout.height * this.containerElementDimensions.scale) + "&proportional=0&layoutBackgroundId=" + layout.backgroundImage + "') top center no-repeat");
        }

        // Handle play button
        this.DOMObject.find('#play-btn').click(function() {
            this.playPreview(requestPath, this.containerElementDimensions);
        }.bind(this));

        // Handle fullscreen button
        this.DOMObject.find('#fs-btn').click(function() {            
            this.toggleFullscreen();
        }.bind(this));

    } else { // Render Widget or Region

        // Id the element is a region or widget, increase request information
        if(element.type === 'region') {
            requestPath += '?seq=' + page;
        } else if(element.type === 'widget'){
            requestPath += '?widgetId=' + element[element.type + 'Id'];
        }

        requestPath += '&width=' + this.containerElementDimensions.width + '&height=' + this.containerElementDimensions.height;

        // If there was still a render request, abort it
        if(this.renderRequest != undefined) {
            this.renderRequest.abort('requestAborted');
        }

        // Get HTML for the given element from the API
        this.renderRequest = $.get(requestPath).done(function(res) {
            
            // Clear request var after response
            self.renderRequest = undefined;
            
            // Prevent rendering null html
            if(!res.success) {
                toastr.error(res.message);
                this.DOMObject.html(res.message);
                return;
            }

            let elementType = (element.type === 'widget') ? (element.type + '_' + element.subType) : element.type;
            
            // Replace container html
            const html = viewerTemplate({
                res: res,
                dimensions: lD.viewer.containerElementDimensions,
                type: elementType
            });

            // Append layout html to the container div
            this.DOMObject.html(html);

            // Render navbar
            this.renderNavbar(res, element);

            // Calculate and render background image or color to the preview
            this.calculateBackground(lD.viewer.containerElementDimensions, targetElement, layout);

            // If inline editor is on, show the controls for it ( fixing asyc load problem )
            if(lD.propertiesPanel.inlineEditor) {
                // Show inline editor controls
                this.showInlineEditor();
            }

            // Handle fullscreen button
            this.DOMObject.find('#fs-btn').click(function() {
                this.toggleFullscreen();
            }.bind(this));

        }.bind(this)).fail(function(res) {
            // Clear request var after response
            self.renderRequest = undefined;

            if(res.statusText != 'requestAborted') {
                toastr.error(errorMessagesTrans.previewFailed);
                this.DOMObject.html(errorMessagesTrans.previewFailed);
            }

        }.bind(this));
    }

};

/**
 * Render Navbar
 */
Viewer.prototype.renderNavbar = function(data, element) {

    // Stop if navbar container does not exist
    if(this.navbarContainer === null || this.navbarContainer === undefined || data.extra.empty) {
        return;
    }

    this.navbarContainer.html(viewerNavbarTemplate(
        {
            extra: data.extra,
            type: element.type,
            pagingEnable: (data.extra.number_items > 1),
            trans: layoutDesignerTrans
        }
    ));

    // Paging controls
    if(data.extra && data.extra.number_items > 1) {
        this.navbarContainer.find('#left-btn').prop('disabled', (data.extra.current_item <= 1)).click(function() {
            this.render(lD.selectedObject, lD.layout, data.extra.current_item - 1);
        }.bind(this));

        this.navbarContainer.find('#right-btn').prop('disabled', (data.extra.current_item >= data.extra.number_items)).click(function() {
            this.render(lD.selectedObject, lD.layout, data.extra.current_item + 1);
        }.bind(this));
    }
};

/**
 * Play preview
 */
Viewer.prototype.playPreview = function(url, dimensions) {
    // Compile layout template with data
    const html = viewerLayoutPreview({
        url: url,
        width: dimensions.width,
        height: dimensions.height
    });

    // Append layout html to the main div
    this.DOMObject.find('.layout-player').html(html);
};

/**
 * Toggle fullscreen
 */
Viewer.prototype.toggleFullscreen = function() {
    this.DOMObject.parent().toggleClass('fullscreen');
    this.render(lD.selectedObject, lD.layout);
};

/**
 * Setup Inline Editor ( show or hide )
 */
Viewer.prototype.setupInlineEditor = function(textAreaId, show = true, customNoDataMessage = null) {

    // Prevent setup if user is in read only mode
    const app = getXiboApp();
    if(app.readOnlyMode != undefined && app.readOnlyMode === true) {
        return;
    }
    
    // Change inline editor to enable it on viewer render/refresh
    lD.propertiesPanel.inlineEditor = show;
    lD.propertiesPanel.inlineEditorId = textAreaId;
    lD.propertiesPanel.customNoDataMessage = customNoDataMessage;

    // Show or hide inline editor
    if(show) {
        this.showInlineEditor();
    } else {
        this.hideInlineEditor();
    }
};

/**
 * Show Inline Editor
 */
Viewer.prototype.showInlineEditor = function() {

    // Show closed editor controls
    this.DOMObject.parent().find('.inline-editor-closed').show();

    // Show form elements
    lD.propertiesPanel.DOMObject.find('.inline-editor-show').show();

    // Hide form editor
    lD.propertiesPanel.DOMObject.find('.inline-editor-hide').hide();

    // Handle click to open editor
    this.DOMObject.find('#inline-editor-overlay').off().click(function() {
        this.editInlineEditorToggle(true);
    }.bind(this));

    // Update state
    this.inlineEditorState = 1;
};

/**
 * Hide Inline Editor
 */
Viewer.prototype.hideInlineEditor = function() {
    // Hide inline editor controls
    this.DOMObject.parent().find(
        '.inline-editor-opened, ' +
        '.inline-editor-closed').hide();
    
    // Show widget info
    this.DOMObject.parent().find('.inline-editor-hide').show();

    // Hide form elements
    lD.propertiesPanel.DOMObject.find('.inline-editor-show').hide();

    // Show inline editor message
    lD.propertiesPanel.DOMObject.find('.inline-editor-hide').show();

    // If opened, needs to be saved/closed
    if(this.inlineEditorState == 2) {
        // Close editor content
        this.closeInlineEditorContent();

        // Show rendered preview
        this.DOMObject.find('#viewer-preview').show();
    }

    // Update state
    this.inlineEditorState = 0;
};

/**
 * Edit Inline Editor editor ( open or close edit )
 */
Viewer.prototype.editInlineEditorToggle = function(show = true) {
    // Toggle open/close inline editor classes
    this.DOMObject.parent().find('.inline-editor-opened').toggle(show);
    this.DOMObject.parent().find('.inline-editor-hide').toggle(!show);
    this.navbarContainer.parent().find('.inline-editor-closed').toggle(!show);

    // Load content if editor is toggled on
    if(show) {
        this.openInlineEditorContent();
    } else {
        this.closeInlineEditorContent();
    }
    
    // Toggle rendered preview
    this.DOMObject.find('#viewer-preview').toggle(!show);
};

/**
 * Load Inline Editor content
 */
Viewer.prototype.openInlineEditorContent = function() {
    // Move text area from form to viewer
    const taText = lD.propertiesPanel.DOMObject.find('#' + lD.propertiesPanel.inlineEditorId).clone();
    taText.attr('id', 'viewer_' + taText.attr('id'));
    this.DOMObject.find('#inline-editor').empty().append(taText);

    // Move editor controls from the form to the viewer navbar
    const controls = lD.propertiesPanel.DOMObject.find('.ckeditor_controls[data-linked-to="' + lD.propertiesPanel.inlineEditorId + '"]');

    // Destroy select2 controls before cloning
    controls.find('select').each(function() {
        if($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
        }
    });

    // Clone controls to move the copy to the viewer's navbar
    const controlClones = controls.clone();
    
    // Change the new controls data link to the new text area
    controlClones.find('select').attr('data-linked-to', 'viewer_' + controls.find('select').attr('data-linked-to'));

    // Show controls
    controlClones.show();

    // Show fullscreen editor save button
    const $saveEditorButton = this.DOMObject.parents('#layout-viewer-container.fullscreen').find('#inline-editor-save').show();
    this.DOMObject.parents('#layout-viewer-container.fullscreen').find('#fs-btn').removeClass('fa-arrows-alt').addClass('fa-close');
    this.navbarContainer.addClass('fs-edit');

    $saveEditorButton.off().click(function() {
        lD.propertiesPanel.DOMObject.find('button[data-action="save"]').trigger('click');
    });

    // Append controls to navbar
    this.navbarContainer.find('.inline-editor-templates').empty().append(controlClones);

    // Setup inline CKEditor
    formHelpers.setupCKEditor(this.DOMObject.parent(), null, 'viewer_' + lD.propertiesPanel.inlineEditorId, true, lD.propertiesPanel.customNoDataMessage, true);

    // Update state
    this.inlineEditorState = 2;
};


/**
 * Unload Inline Editor content ( reenable on properties panel )
 */
Viewer.prototype.closeInlineEditorContent = function() {
    if(CKEDITOR.instances['viewer_' + lD.propertiesPanel.inlineEditorId] == undefined) {
        return;
    }

    // Update inline editor text area
    let data = CKEDITOR.instances['viewer_' + lD.propertiesPanel.inlineEditorId].getData();

    // Copy data back to properties panel text area
    lD.propertiesPanel.DOMObject.find('#' + lD.propertiesPanel.inlineEditorId).val(data);

    this.navbarContainer.removeClass('fs-edit');

    // Update state
    this.inlineEditorState = 1;

    formHelpers.destroyCKEditor('viewer_' + lD.propertiesPanel.inlineEditorId);
};

/**
 * Calculate background CSS
 */
Viewer.prototype.calculateBackground = function(dimensions, element, layout) {

    // Calculate element and layout dimensions scaled to the container
    const elementScaledDimensions = {
        top: (parseFloat(element.dimensions.top) * dimensions.scale),
        left: (parseFloat(element.dimensions.left) * dimensions.scale),
        width: (parseFloat(element.dimensions.width) * dimensions.scale),
        height: (parseFloat(element.dimensions.height) * dimensions.scale)
    };

    const layoutScaledDimensions = {
        width: layout.width * dimensions.scale,
        height: layout.height * dimensions.scale
    };

    // Add background ( or color ) to the viewer
    if(layout.backgroundImage === null) {
        this.DOMObject.css('background-color', layout.backgroundColor);
    } else {
        // Get API link
        let linkToAPI = urlsForApi['layout']['downloadBackground'].url;
        // Replace ID in the link
        linkToAPI = linkToAPI.replace(':id', layout.layoutId);

        this.DOMObject.css('background', "url('" + linkToAPI + "?preview=1&width=" + (layout.width * dimensions.scale) + "&height=" + (layout.height * dimensions.scale) + "&proportional=0&layoutBackgroundId=" + layout.backgroundImage + "') top center no-repeat");
        this.DOMObject.css('background-color', '#111');

        // Adjust background position
        this.DOMObject.css('background-position-x', -elementScaledDimensions.left + 'px');
        this.DOMObject.css('background-position-y', -elementScaledDimensions.top + 'px');
    }

    // Reset element positions (to use viewer border to position it)
    this.DOMObject.find('.viewer-element').css('top', 0);
    this.DOMObject.find('.viewer-element').css('left', 0);

    // Draw focus area borders
    this.DOMObject.css('border-color', '#000');
    this.DOMObject.css('border-color', '#000a');
    this.DOMObject.css('border-style', 'solid');
    this.DOMObject.css('border-top-width', dimensions.top + 'px');
    this.DOMObject.css('border-left-width', dimensions.left + 'px');

    this.DOMObject.css('border-bottom-width', dimensions.top + 'px');
    this.DOMObject.css('border-right-width', dimensions.left + 'px');

    // Calculate and draw layout borders ( showing layout's limits)
    // Left border
    if(Math.abs(elementScaledDimensions.left) < dimensions.left) {

        this.DOMObject.find('#border-before').css({
            'width': (dimensions.left - elementScaledDimensions.left),
            'height': dimensions.height,
            '-webkit-transform': 'translate(' + (-dimensions.left) + 'px, 0px)',
            '-moz-transform': 'translate(' + (-dimensions.left) + 'px, 0px)',
            '-ms-transform': 'translate(' + (-dimensions.left) + 'px, 0px)',
            '-o-transform': 'translate(' + (-dimensions.left) + 'px, 0px)',
            'transform': 'translate(' + (-dimensions.left) + 'px, 0px)'
        });
    }

    // Top border
    if(Math.abs(elementScaledDimensions.top) < dimensions.top) {

        this.DOMObject.find('#border-before').css({
            'width': dimensions.width,
            'height': (dimensions.top - elementScaledDimensions.top),
            '-webkit-transform': 'translate(0px, ' + (-dimensions.top) + 'px)',
            '-moz-transform': 'translate(0px, ' + (-dimensions.top) + 'px)',
            '-ms-transform': 'translate(0px, ' + (-dimensions.top) + 'px)',
            '-o-transform': 'translate(0px, ' + (-dimensions.top) + 'px)',
            'transform': 'translate(0px, ' + (-dimensions.top) + 'px)'
        });
    }

    // Calculate bottom and right borders dor the element and layout
    const elementRightBorder = elementScaledDimensions.width + elementScaledDimensions.left;
    const elementBottomBorder = elementScaledDimensions.height + elementScaledDimensions.top;
    const layoutRightBorder = layoutScaledDimensions.width;
    const layoutBottomBorder = layoutScaledDimensions.height;

    // Right border ( using left padding since the image is aligned )
    if(Math.abs(layoutRightBorder - elementRightBorder) < dimensions.left) {

        const calculatedRight = (elementScaledDimensions.width + (layoutRightBorder - elementRightBorder));

        this.DOMObject.find('#border-after').css({
            'width': (dimensions.left - (layoutRightBorder - elementRightBorder)),
            'height': dimensions.height,
            '-webkit-transform': 'translate(' + calculatedRight + 'px, 0px)',
            '-moz-transform': 'translate(' + calculatedRight + 'px, 0px)',
            '-ms-transform': 'translate(' + calculatedRight + 'px, 0px)',
            '-o-transform': 'translate(' + calculatedRight + 'px, 0px)',
            'transform': 'translate(' + calculatedRight + 'px, 0px)'
        });
    }

    // Bottom border ( using top padding since the image is aligned )
    if(Math.abs(layoutBottomBorder - elementBottomBorder) < dimensions.top) {

        const calculatedBottom = (elementScaledDimensions.height + (layoutBottomBorder - elementBottomBorder));


        this.DOMObject.find('#border-after').css({
            'width': dimensions.width,
            'height': (dimensions.top - (layoutBottomBorder - elementBottomBorder)),
            '-webkit-transform': 'translate(0px, ' + calculatedBottom + 'px)',
            '-moz-transform': 'translate(0px, ' + calculatedBottom + 'px)',
            '-ms-transform': 'translate(0px, ' + calculatedBottom + 'px)',
            '-o-transform': 'translate(0px, ' + calculatedBottom + 'px)',
            'transform': 'translate(0px, ' + calculatedBottom + 'px)'
        });
    }

};

module.exports = Viewer;
