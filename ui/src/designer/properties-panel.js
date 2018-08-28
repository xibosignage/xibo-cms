// PROPERTIES PANEL Module

const loadingTemplate = require('../templates/loading.hbs');
const propertiesPanel = require('../templates/properties-panel.hbs');

/**
 * Properties panel contructor
 * @param {object} container - the container to render the panel to
 */
let PropertiesPanel = function(container, namespace) {
    
    this.namespace = namespace;

    this.DOMObject = container;

    // Initialy loaded data on the form
    this.formSerializedLoadData = '';
};


/**
 * Save properties from the panel form
 * @param {object} form - the form to be saved
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.save = function(form, element) {

    const self = this;

    // Run form open module optional function
    if(element.type === 'widget' && typeof window[element.subType + '_form_edit_submit'] === 'function') {
        window[element.subType + '_form_edit_submit'].bind(this.DOMObject)();
    }
    // If form is valid, submit it ( add change )
    if($(this.DOMObject).find('form').valid()) {

    const formNewData = $(form).serialize();

    // Add a save form change to the history array, with previous form state and the new state
    this.namespace.manager.addChange(
        "saveForm",
        element.type, // targetType
        element[element.type + 'Id'], // targetId
        this.formSerializedLoadData, // oldValues
            formNewData, // newValues
            {
                customRequestPath: {
                    url: $(form).attr('action'),
                    type: $(form).attr('method')
                }
            }
    ).then((res) => { // Success

        // Behavior if successful 
        if(typeof self.namespace.timeline.resetZoom === "function") {
            // safe to use the function
            self.namespace.timeline.resetZoom();
        }
        
        toastr.success(res.message);

        const mainObject = self.namespace.getElementByTypeAndId(self.namespace.mainObjectType, self.namespace.mainObjectId);
        self.namespace.reloadData(mainObject);
    }).catch((error) => { // Fail/error

        // Show error returned or custom message to the user
            let errorMessage = '';
        
        if(typeof error == 'string') {
            errorMessage += error; 
        } else {
            errorMessage += error.errorThrown; 
        }
            // Remove added change from the history manager
            self.namespace.manager.removeLastChange();

            // Display message in form
            formHelpers.displayErrorMessage($(this.DOMObject).find('form'), errorMessage, 'danger');

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

    this.DOMObject.html(loadingTemplate());
    let requestPath = urlsForApi[element.type].getForm.url;

    requestPath = requestPath.replace(':id', element[element.type + 'Id']);

    // Get form for the given element
    const self = this;
    
    $.get(requestPath).done(function(res) {

        // Prevent rendering null html
        if(res.html === null) {
            return;
        }

        const htmlTemplate = Handlebars.compile(res.html);
        
        // If there are no buttons, create a Save one without onclick event ( handled with a .click() after render )
        if((res.buttons.length === 0) || element.type == 'layout'){
            res.buttons = {
                Save: ''
            };
        } else {
            res.buttons.Save = '';
        }
        
        const html = propertiesPanel({
            header: res.dialogTitle,
            id: element.id,
            style: element.type,
            form: htmlTemplate(element),
            buttons: res.buttons
        });

        // Append layout html to the main div
        self.DOMObject.html(html);

        // Store the extra
        self.DOMObject.data("extra", res.extra);

        // Run form open module optional function
        if(element.type === 'widget' && typeof window[element.subType + '_form_edit_open'] === 'function') {
            window[element.subType + '_form_edit_open'].bind(self.DOMObject)();
        }

        // Save form data
        self.formSerializedLoadData = self.DOMObject.find('form').serialize();

        // Handle Save button click
        self.DOMObject.find('#Save').click(function() {

            self.save(
                self.DOMObject.find('form'),
                element
            );
        });

        // Form submit handling
        self.DOMObject.find('form').submit(function(e) {
            e.preventDefault();

            self.save(
                this, 
                element
            );
        });

        // Call Xibo Init for this form
        XiboInitialise("#" + self.DOMObject.attr("id"));

        // For the layout properties, call background Setup
        if(element.type == 'layout') {
            backGroundFormSetup(self.DOMObject);
        }

    }).fail(function(data) {
        toastr.error('Get form failed!', 'Error');
    });

};

module.exports = PropertiesPanel;
