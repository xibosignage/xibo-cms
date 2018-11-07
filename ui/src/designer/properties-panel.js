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

    const self = this;

    // Run form open module optional function
    if(element.type === 'widget' && typeof window[element.subType + '_form_edit_submit'] === 'function') {
        window[element.subType + '_form_edit_submit'].bind(this.DOMObject)();
    }

    const form = $(this.DOMObject).find('form');

    // If form is valid, submit it ( add change )
    if(form.valid()) {

        const formNewData = form.serialize();
        const app = getXiboApp();

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

            // Show toast message
            toastr.error(errorMessage);
        });
    }
};

/**
 * Render panel
 * @param {Object} element - the element object to be rendered
 */
PropertiesPanel.prototype.render = function(element) {

    // Prevent the panel to render if the layout is not editable
    if(typeof element == 'undefined' || $.isEmptyObject(element) || (element.type == 'layout' && !element.editable) ) {
        // Clean the property panel html
        this.DOMObject.html('');

        return false;
    }

    // Reset inline editor to false on each refresh
    this.inlineEditor = false;

    this.DOMObject.html(loadingTemplate());
    let requestPath = urlsForApi[element.type].getForm.url;

    requestPath = requestPath.replace(':id', element[element.type + 'Id']);

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
        if(res.html === null) {
            return;
        }

        const htmlTemplate = Handlebars.compile(res.html);

        // Create buttons object
        let buttons = {};
        
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

        // Add save button
        buttons.save = {
            name: 'Save',
                type: 'btn-info',
                    action: 'save'
        };

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

        // If fthe viewer exists, save its data  to the DOMObject
        if(viewerExists) {
            self.DOMObject.data('viewerObject', app.viewer);
        }

        // Run form open module optional function
        if(element.type === 'widget' && typeof window[element.subType + '_form_edit_open'] === 'function') {
            window[element.subType + '_form_edit_open'].bind(self.DOMObject)();
        }

        // Save form data
        self.formSerializedLoadData = self.DOMObject.find('form').serialize();

        // Handle buttons click
        self.DOMObject.find('.properties-panel-btn').click(function(el) {
            self[$(this).data('action')](element, $(this).data('subAction'));
        });

        // Handle keyboard keys
        self.DOMObject.off('keydown').keydown(function(handler) {
            if(handler.key == 'Enter') {
                self.save(element, $(this).data('subAction'));
            }
        });

        // Call Xibo Init for this form
        XiboInitialise("#" + self.DOMObject.attr("id"));

        // For the layout properties, call background Setup
        if(element.type == 'layout') {
            backGroundFormSetup(self.DOMObject);
        }

    }).fail(function(data) {

        // Clear request var after response
        self.renderRequest = undefined;

        if(data.statusText != 'requestAborted') {
            toastr.error('Get form failed!', 'Error');
        }
    });

};

module.exports = PropertiesPanel;
