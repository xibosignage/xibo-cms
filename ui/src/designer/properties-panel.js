// PROPERTIES PANEL Module

const loadingTemplate = require('../templates/loading.hbs');
const propertiesPanel = require('../templates/properties-panel.hbs');

/**
 * Properties panel contructor
 * @param {object} container - the container to render the panel to
 */
let PropertiesPanel = function(container) {
    this.DOMObject = container;
};


/**
 * Save properties from the panel form
 */
PropertiesPanel.prototype.save = function(form) {

    console.log('HERE!!!!!!!!!');

    console.log($(form).attr('action'));
    console.log($(form).serialize());

    $.ajax({
        url: $(form).attr('action'),
        type: 'PUT',
        data: $(form).serialize(),
        success: function(data) {
            console.log('Success!!!!');
            if(data.success) {
                lD.reloadData(lD.layout);
            } else {
                alert(data.message);
            }
        },
        error: function(jXHR, textStatus, errorThrown) {
            alert(errorThrown);
        }
    });
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
            form: htmlTemplate(element),
            buttons: res.buttons
        })

        // Append layout html to the main div
        self.DOMObject.html(html);

        // Form submit handling
        self.DOMObject.find('form').submit(function(e) {
            e.preventDefault();

            self.save(this);
        });

    }).fail(function(data) {
        console.log('TODO: Handle fail');
    });

};

module.exports = PropertiesPanel;
