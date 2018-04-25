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
};


/**
 * Save properties from the panel form
 * @param {object} form - the form to be saved
 * @param {object} element - the element that the form relates to
 */
PropertiesPanel.prototype.save = function(form, element) {

    const formNewData = $(form).serialize();

    lD.manager.addChange(
        "saveForm",
        element.type,
        element.id,
        {
            url: $(form).attr('action'),
            data: this.formSerializedLoadData
        },
        {
            url: $(form).attr('action'),
            data: formNewData
        }
    );
};

/**
 * Render panel
 * @param {Object} element - the element object to be rendered
 */
PropertiesPanel.prototype.render = function(element) {

    this.DOMObject.html(loadingTemplate());

    const self = this;
    let requestPath = '';

    switch(element.type) {
        case 'layout':
            requestPath = '/layout/form/edit/' + element.layoutId
            break;

        case 'region':
            requestPath = '/region/form/edit/' + element.regionId + '?layoutid=' + lD.layout.layoutId + '&regionid=' + element.regionId
            break;

        case 'widget':
            requestPath = '/playlist/widget/form/edit/' + element.widgetId
            break;

        default:
            break;
    }

    // Get form for the given element
    $.get(requestPath).done(function(res) {

        const htmlTemplate = Handlebars.compile(res.html);
        
        const html = propertiesPanel({
            header: res.dialogTitle,
            style: element.type,
            form: htmlTemplate(element),
            buttons: res.buttons
        })

        // Append layout html to the main div
        self.DOMObject.html(html);

        // Save form data
        self.formSerializedLoadData = self.DOMObject.find('form').serialize();

        // Form submit handling
        self.DOMObject.find('form').submit(function(e) {
            e.preventDefault();

            self.save(this, element);
        });

    }).fail(function(data) {
        toastr.error('Get form failed!', 'Error');
    });

};

module.exports = PropertiesPanel;
