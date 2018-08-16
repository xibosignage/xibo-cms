// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');

// Add global helpers
window.formHelpers = require('../helpers/form-helpers.js');

const defaultMenuItems = [
    {
        name: 'widgets',
        title: 'Widgets',
        pagination: false,
        page: 0,
        content: [],
        state: ''
    }
];

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 * @param {object[]} customButtons - customized buttons
 * @param {object} customActions - customized actions
 */
let Toolbar = function(container, customButtons = [], customActions = {}) {
    this.DOMObject = container;
    this.openedMenu = -1;
    this.menuItems = defaultMenuItems;
    this.menuIndex = 0;

    // Custom buttons
    this.customButtons = customButtons;

    // Custom actions
    this.customActions = customActions;

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.cardDimensions = {
        width: 100, // In pixels
        height: 70, // In pixels
        margin: 2 // In pixels
    };

    // Load user preferences
    this.loadPrefs();
};

/**
 * Load user preferences
 */
Toolbar.prototype.loadPrefs = function() {

    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=toolbar',
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {

            let loadedData = JSON.parse(res.data.value);
            
            // Populate the toolbar with the returned data
            self.menuItems = loadedData.menuItems;
            self.openedMenu = loadedData.openedMenu;

            // Set menu index
            self.menuIndex = self.menuItems.length;

            // Render to reflect the loaded toolbar
            self.render();

            // If there was a opened menu, load content for that one
            if(self.openedMenu != -1) {
                self.loadContent(self.openedMenu);
            }
        } else {
            // Render toolbar even if the user prefs load fail
            self.render();
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error('User load preferences failed!');

    });
};

/**
 * Save user preferences
 */
Toolbar.prototype.savePrefs = function() {
    
    // Save only some of the tab menu data
    let menuItemsToSave = [];

    for (let index = 0; index < this.menuItems.length; index++) {

        // Make a copy of the current element
        let elementCopy = Object.assign({}, this.menuItems[index]);

        // Remove content and set page to 0
        elementCopy.content = [];
        elementCopy.page = 0;
        
        menuItemsToSave.push(elementCopy);
    }

    let dataToSave = {
        preference: [
            {
                option: 'toolbar',
                value: JSON.stringify({
                    menuItems: menuItemsToSave,
                    openedMenu: this.openedMenu
                })
            }
        ]
    };

    // Load using the API
    const linkToAPI = urlsForApi.user.savePref;

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url,
        type: linkToAPI.type,
        data: dataToSave
    }).done(function(res) {

        if(!res.success) {
            toastr.error('User save preferences failed!');
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error('User save preferences failed!');
    });

};

/**
 * Render toolbar
 */
Toolbar.prototype.render = function() {

    let self = this;

    // Compile layout template with data
    const html = ToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        tabsCount: (this.menuItems.length > 1),
        customButtons: this.customButtons
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Handle tabs
    for(let i = 0;i < this.menuItems.length;i++) {

        const toolbar = self;
        const index = i;

        this.DOMObject.find('#btn-menu-' + index).click(function() {
            toolbar.openTab(index);
        });

        this.DOMObject.find('#close-btn-menu-' + index).click(function() {
            toolbar.deleteTab(index);
        });

        this.DOMObject.find('#content-' + index + ' #pag-btn-left').click(function() {
            toolbar.menuItems[index].page -= 1;
            toolbar.loadContent(index);
        });

        this.DOMObject.find('#content-' + index + ' #pag-btn-right').click(function() {
            toolbar.menuItems[index].page += 1;
            toolbar.loadContent(index);
        });

    }

    // Toggle button
    this.DOMObject.find('#btn-menu-toggle').click(function() {
        self.openTab();
    });

    // Create new tab
    this.DOMObject.find('#btn-menu-new-tab').click(function(){
        self.createNewTab();
    });

    // Close all tabs
    this.DOMObject.find('#deleteAllTabs').click(function() {
        self.deleteAllTabs();
    });

    // Search button
    this.DOMObject.find('.search-btn').click(function() {
        
        // Reset page
        self.menuItems[$(this).attr("data-search-id")].page = 0;

        // Load content for the search tab
        self.loadContent($(this).attr("data-search-id"));
    });

    // Delete object
    this.DOMObject.find('#trashContainer').click(
        this.customActions.deleteSelectedObjectAction
    ).droppable({
        drop: function(event, ui) {
            self.customActions.deleteDraggedObjectAction(ui.draggable);
        }
    });

    // Handle custom buttons
    for(let index = 0;index < this.customButtons.length;index++) {

        // Bind action to button
        this.DOMObject.find('#' + this.customButtons[index].id).click(
            this.customButtons[index].action
        );

        // If there is a activeCheck, use that function to switch button state
        if(this.customButtons[index].activeCheck != undefined && this.customButtons[index].activeCheck()) {
            this.DOMObject.find('#' + this.customButtons[index].id).prop('disabled', false);
        } else if(this.customButtons[index].activeCheck != undefined){
            this.DOMObject.find('#' + this.customButtons[index].id).prop('disabled', true);
        }
        
    }

    // Set cards width/margin and draggable properties
    this.DOMObject.find('.toolbar-card').width(
        this.cardDimensions.width
    ).height(
        this.cardDimensions.height
    ).css(
        'margin', this.cardDimensions.margin
    ).draggable({
        cursor: "crosshair",
        cursorAt: {
            top: (this.cardDimensions.height + this.cardDimensions.margin) / 2,
            left: (this.cardDimensions.width + this.cardDimensions.margin) / 2
        },
        opacity: 0.3,
        helper: 'clone'
    });

    // Initialize tooltips
    this.DOMObject.find('[data-toggle="tooltip"]').tooltip();
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 */
Toolbar.prototype.loadContent = function(menu = -1) {

    // Calculate pagination
    const pagination = this.calculatePagination(menu);

    // Enable/Disable page down pagination button according to the page to display
    this.menuItems[menu].pagBtnLeftDisabled = (pagination.start == 0) ? 'disabled' : '';

    // Replace search button with a spinner icon
    this.DOMObject.find('.search-btn').html('<i class="fa fa-spinner fa-spin"></i>');

    if(menu == 0) {
        
        this.menuItems[menu].content = modulesList;

        for(let index = 0;index < this.menuItems[menu].content.length;index++) {
            const element = this.menuItems[menu].content[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            // Hide element if it's outside the "to display" region
            element.hideElement = (index < pagination.start || index >= (pagination.start + pagination.length));
        }

        // Enable/Disable page up pagination button according to the page to display and total elements
        this.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= this.menuItems[menu].content.length) ? 'disabled' : '';
        
        // Save user preferences
        this.savePrefs();

        this.render();
    } else {

        // Load using the API
        const linkToAPI = urlsForApi.library.get;

        // Save filters
        this.menuItems[menu].filters.name.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-name').val();
        this.menuItems[menu].filters.tag.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-tag').val();
        this.menuItems[menu].filters.type.value = this.DOMObject.find('#media-search-form-' + menu + ' #input-type').val();

        // Create filter
        let customFilter = {
            retired: 0,
            assignable: 1,
            start: pagination.start,
            length: pagination.length,
            media: this.menuItems[menu].filters.name.value,
            tags: this.menuItems[menu].filters.tag.value,
            type: this.menuItems[menu].filters.type.value
        };

        // Change tab name to reflect the search query
        if(customFilter.media != '' && customFilter.media != undefined) {
            this.menuItems[menu].title = '"' + customFilter.media + '"';
        } else {
            this.menuIndex += 1;
            this.menuItems[menu].title = 'Tab ' + this.menuIndex;
        }

        if(customFilter.tags != '' && customFilter.tags != undefined) {
            this.menuItems[menu].title += ' {' + customFilter.tags + '} ';
        }

        if(customFilter.type != '' && customFilter.type != undefined) {
            this.menuItems[menu].title += ' [' + customFilter.type + '] ';
        }

        // Request elements based on filters
        let self = this;
        $.ajax({
            url: linkToAPI.url,
            type: linkToAPI.type,
            data: customFilter
        }).done(function(res) {

            if(res.data.length == 0) {
                toastr.info('No results for the filter!', 'Search');
                self.menuItems[menu].content = null;
            } else {
                self.menuItems[menu].content = res.data;
            }

            // Enable/Disable page up pagination button according to the page to display and total elements
            self.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= res.recordsTotal) ? 'disabled' : '';

            // Save user preferences
            self.savePrefs();

            self.render();
        }).catch(function(jqXHR, textStatus, errorThrown) {

            console.error(jqXHR, textStatus, errorThrown);
            toastr.error('Library load failed!');

            self.menuItems[menu].content = null;
        });
    }
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 */
Toolbar.prototype.openTab = function(menu = -1) {

    // Close previous opened menu
    if(menu == -1 && this.openedMenu != -1) {
        this.menuItems[this.openedMenu].state = '';
        this.openedMenu = -1;
    } else if(menu != -1) {

        // Close all menus
        for(let index = this.menuItems.length - 1;index >= 0;index--) {
            this.menuItems[index].state = '';
        }

        // If menu is the default/widget, load content
        if(menu == 0) {
            this.loadContent(0);
        }

        this.menuItems[menu].state = 'active';
        this.openedMenu = menu;
    }

    // Save user preferences
    this.savePrefs();

    this.render();
};

/**
 * Create new tab
 */
Toolbar.prototype.createNewTab = function() {

    let moduleListFiltered = [];

    // Filter module list to create the types for the filter
    modulesList.forEach(element => {
        if(element.assignable == 1 && element.regionSpecific == 0) {
            moduleListFiltered.push(element);
        }
    });

    this.menuIndex += 1;

    this.menuItems.push({
        name: 'search',
        title: 'Tab ' + this.menuIndex,
        search: true,
        page: 0,
        query: '',
        filters: {
            name: {
                name: 'Name',
                value: ''
            },
            tag: {
                name: 'Tag',
                value: ''
            },
            type: {
                name: 'Type',
                values: moduleListFiltered
            },
        },
        content: []
    });

    this.openTab(this.menuItems.length - 1);

    this.render();
};

/**
 * Delete tab
 * @param {number} menu
 */
Toolbar.prototype.deleteTab = function(menu) {

    this.menuItems.splice(menu, 1);
    this.openedMenu = -1;

    // Save user preferences
    this.savePrefs();

    this.render();
};

/**
 * Delete all tabs
 */
Toolbar.prototype.deleteAllTabs = function() {

    for(let index = this.menuItems.length - 1;index > 0;index--) {
        this.menuItems.splice(index, 1);
    }
    this.openedMenu = -1;

    // Save user preferences
    this.savePrefs();
    
    this.render();
};

/**
 * Calculate pagination
 * @param {number} menu
 */
Toolbar.prototype.calculatePagination = function(menu) {
    
    // Get page and number of elements
    const currentPage = this.menuItems[menu].page;

    // Calculate width
    const containerWidth = this.DOMObject.find('.toolbar-content').width() * (this.contentDimentions.width / 100);

    // Calculate number of elements to display
    const elementsToDisplay = Math.floor(containerWidth / (this.cardDimensions.width + this.cardDimensions.margin * 2));

    this.menuItems[menu].contentWidth = this.contentDimentions.width;

    return {
        start: currentPage * elementsToDisplay,
        length: elementsToDisplay
    };
};

module.exports = Toolbar;