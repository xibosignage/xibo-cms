// PROPERTIES PANEL Module

const loadingTemplate = require('../templates/loading.hbs');
const propertiesPanel = require('../templates/properties-panel.hbs');

/**
 * Properties panel contructor
 * @param {object} container - the container to render the panel to
 */
let PropertiesPanel = function(container) {

    this.DOMObject = container;

    // Initialy loaded data on the form
    this.formSerializedLoadData = '';

    this.inlineEditor = false;
};

/**
 * Call an action on the element object
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.elementAction = function(element, subAction) {
    const app = getXiboApp();
    const mainElement = app.getElementByTypeAndId(element.type, element.id, element.regionId);
    mainElement[subAction]();
};

/**
 * Save properties from the panel form
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.save = function(element) {

    const app = getXiboApp();

    // If inline editor and viewer exist
    if(this.inlineEditor && (typeof app.viewer != 'undefined')) {
        app.viewer.hideInlineEditor();
    }

    // Run form open module optional function
    if(element.type === 'widget' && typeof window[element.subType + '_form_edit_submit'] === 'function') {
        window[element.subType + '_form_edit_submit'].bind(this.DOMObject)();
    }

    const form = $(this.DOMObject).find('form');

    // If form is valid, submit it ( add change )
    if(form.valid()) {

        const formNewData = form.serialize();

        app.common.showLoadingScreen();

        // Add a save form change to the history array, with previous form state and the new state
        app.manager.addChange(
            "saveForm",
            element.type, // targetType
            element[element.type + 'Id'], // targetId
            this.formSerializedLoadData, // oldValues
            formNewData, // newValues
            {
                customRequestPath: {
                    url: form.attr('action'),
                    type: form.attr('method')
                }
            }
        ).then((res) => { // Success

            app.common.hideLoadingScreen();

            // Behavior if successful 
            if(typeof app.timeline.resetZoom === "function") {
                // safe to use the function
                app.timeline.resetZoom();
            }

            toastr.success(res.message);

            const mainObject = app.getElementByTypeAndId(app.mainObjectType, app.mainObjectId);
            app.reloadData(mainObject);
        }).catch((error) => { // Fail/error

            app.common.hideLoadingScreen();

            // Show error returned or custom message to the user
            let errorMessage = '';

            if(typeof error == 'string') {
                errorMessage += error;
            } else {
                errorMessage += error.errorThrown;
            }
            // Remove added change from the history manager
            app.manager.removeLastChange();

            // Display message in form
            formHelpers.displayErrorMessage(form, errorMessage, 'danger');

            // If Save fails and we have an inline editor opened, reshow it
            if(app.propertiesPanel.inlineEditor) {
                app.viewer.showInlineEditor();
            }

            // Show toast message
            toastr.error(errorMessage);
        });
    }
};

/**
 * Go to the previous form step
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.back = function(element) {
    
    // Get current step
    const currentStep = this.DOMObject.find('form').data('formStep');

    // Render previous form
    this.render(element, currentStep - 1);
};

/**
 * Disable all the form inputs and make it read only
 */
PropertiesPanel.prototype.makeFormReadOnly = function() {

    // Disable inputs, select, textarea and buttons
    this.DOMObject.find('input, select, textarea, button').attr('disabled', 'disabled');

    // Hide bootstrap switch
    this.DOMObject.find('.bootstrap-switch').hide();
};

/**
 * Render panel
 * @param {Object} element - the element object to be rendered
 */
PropertiesPanel.prototype.render = function(element, step) {

    // Prevent the panel to render if there's no selected object
    if(typeof element == 'undefined' || $.isEmptyObject(element) ) {
        // Clean the property panel html
        this.DOMObject.html('');

        return false;
    }

    // Reset inline editor to false on each refresh
    this.inlineEditor = false;

    this.DOMObject.html(loadingTemplate());
    let requestPath = urlsForApi[element.type].getForm.url;

    requestPath = requestPath.replace(':id', element[element.type + 'Id']);

    if(step !== undefined && typeof step == 'number') {
        requestPath += '?step=' + step;
    } 

    // Get form for the given element
    const self = this;

    // If there was still a render request, abort it
    if(this.renderRequest != undefined) {
        this.renderRequest.abort('requestAborted');
    }
    
    // Create a new request
    this.renderRequest = $.get(requestPath).done(function(res) {

        const app = getXiboApp();

        // Clear request var after response
        self.renderRequest = undefined;

        // Prevent rendering null html
        if(res.html === null || res.success === false) {
            self.DOMObject.html('<div class="unsuccessMessage form-title">' + res.message + '</div>');
            return;
        }

        const htmlTemplate = Handlebars.compile(res.html);

        // Create buttons object
        let buttons = {};
        
        if(app.readOnlyMode === undefined || app.readOnlyMode === false) {
            // Process buttons from result
            for(let button in res.buttons) {
                
                // If button is not a cancel or save button, add it to the button object
                if(!['Save', 'Cancel'].includes(button)) {
                    buttons[button] = {
                        name: button,
                        type: 'btn-default',
                        click: res.buttons[button]
                    };
                }
            }

            // Add back button
            buttons.back = {
                name: editorsTrans.back,
                type: 'btn-default',
                action: 'back'
            };

            // Add save button
            buttons.save = {
                name: translations.save,
                type: 'btn-info',
                action: 'save'
            };
        }
        
        const html = propertiesPanel({
            header: res.dialogTitle,
            style: element.type,
            form: htmlTemplate(element),
            buttons: buttons
        });

        // Append layout html to the main div
        self.DOMObject.html(html);

        // Store the extra
        self.DOMObject.data("extra", res.extra);

        // Check if there's a viewer element
        const viewerExists = (typeof app.viewer != 'undefined');
        self.DOMObject.data('formEditorOnly', !viewerExists);

        // If the viewer exists, save its data  to the DOMObject
        if(viewerExists) {
            self.DOMObject.data('viewerObject', app.viewer);
        }
        
        // Run form open module optional function
        if(element.type === 'widget' && typeof window[element.subType + '_form_edit_open'] === 'function') {
            // Pass widget options to the form as data
            self.DOMObject.data('elementOptions', element.getOptions());

            window[element.subType + '_form_edit_open'].bind(self.DOMObject)();
        } else if(element.type === 'region' && typeof window.region_form_edit_open === 'function') {
            window.region_form_edit_open.bind(self.DOMObject)();
        }

        // Save form data
        self.formSerializedLoadData = self.DOMObject.find('form').serialize();

        // Handle buttons click
        if(app.readOnlyMode === undefined || app.readOnlyMode === false) {
            self.DOMObject.find('.properties-panel-btn').click(function(el) {
                if($(this).data('action')) {
                    self[$(this).data('action')](element, $(this).data('subAction'));
                }  
            });

            // Handle back button based on form page
            if(self.DOMObject.find('form').data('formStep') != undefined && self.DOMObject.find('form').data('formStep') > 1) {
                self.DOMObject.find('button#back').show();
            } else {
                self.DOMObject.find('button#back').hide();
            }

            // Handle keyboard keys
            self.DOMObject.off('keydown').keydown(function(handler) {
                if(handler.key == 'Enter' && !$(handler.target).is('textarea')) {
                    self.save(element, $(this).data('subAction'));
                }
            });
        }

        // Call Xibo Init for this form
        XiboInitialise("#" + self.DOMObject.attr("id"));

        // For the layout properties, call background Setup
        if(element.type == 'layout') {
            backGroundFormSetup(self.DOMObject);
        }

        if(app.readOnlyMode != undefined && app.readOnlyMode === true) {
            self.makeFormReadOnly();
        }

    }).fail(function(data) {

        // Clear request var after response
        self.renderRequest = undefined;

        if(data.statusText != 'requestAborted') {
            toastr.error(errorMessagesTrans.getFormFailed, errorMessagesTrans.error);
        }
    });

};

module.exports = PropertiesPanel;
