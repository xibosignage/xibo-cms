// NAVIGATOR Module

// Load templates
const bottomToolbarTemplate = require('../templates/toolbar.hbs');

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
 */
let BottomToolbar = function(container) {
    this.DOMObject = container;
    this.openedMenu = -1;
    this.menuItems = defaultMenuItems;
    this.menuIndex = 0;

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.cardDimensions = {
        width: 100, // In pixels
        margin: 2 // In pixels
    };
};

/**
 * Render toolbar
 */
BottomToolbar.prototype.render = function() {

    // Compile layout template with data
    const html = bottomToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        undo: ((lD.manager.changeHistory.length > 0) ? '' : 'disabled'),
        tabsCount: (lD.toolbar.menuItems.length > 1)
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Handle buttons
    for(let index = 0;index < this.menuItems.length;index++) {
        const element = this.menuItems[index];
        this.DOMObject.find('#btn-menu-' + index).click(function() {
            this.openTab(index);
        }.bind(this));

        this.DOMObject.find('#close-btn-menu-' + index).click(function() {
            this.deleteTab(index);
        }.bind(this));

        this.DOMObject.find('#content-' + index + ' #pag-btn-left').click(function() {
            this.menuItems[index].page -= 1;
            this.loadContent(index);
        }.bind(this));

        this.DOMObject.find('#content-' + index + ' #pag-btn-right').click(function() {
            this.menuItems[index].page += 1;
            this.loadContent(index);
        }.bind(this));

    }
    this.DOMObject.find('#btn-menu-toggle').click(function() {
        this.openTab();
    }.bind(this));

    this.DOMObject.find('#btn-menu-new-tab').click(function() {
        this.createNewTab();
    }.bind(this));

    this.DOMObject.find('#undoLastAction').click(function() {
        lD.manager.revertChange();
    });

    this.DOMObject.find('#deleteAllTabs').click(function() {
        lD.toolbar.deleteAllTabs();
    });

    this.DOMObject.find('.search-btn').click(function() {
        // Reset page
        lD.toolbar.menuItems[$(this).attr("data-search-id")].page = 0;

        // Load content for the search tab
        lD.toolbar.loadContent($(this).attr("data-search-id"));
    });

    // Set cards width/margin and draggable properties
    this.DOMObject.find('.toolbar-card').width(this.cardDimensions.width).css('margin', this.cardDimensions.margin).draggable({
        revert: true,
        opacity: 0.35
    });

    // Initialize tooltips
    this.DOMObject.find('[data-toggle="tooltip"]').tooltip();

};

/**
 * Load content
 * @param {number} menu - menu to laod content for
 */
BottomToolbar.prototype.loadContent = function(menu = -1) {

    // Calculate pagination
    const pagination = lD.toolbar.calculatePagination(menu);

    // Enable/Disable page down pagination button according to the page to display
    this.menuItems[menu].pagBtnLeftDisabled = (pagination.start == 0) ? 'disabled' : '';

    // Replace search button with a spinner icon
    this.DOMObject.find('.search-btn').html('<i class="fa fa-spinner fa-spin"></i>');

    if(menu == 0) {
        this.menuItems[menu].content = modulesList;

        for (let index = 0; index < this.menuItems[menu].content.length; index++) {
            const element = this.menuItems[menu].content[index];
    
            // Hide element if it's outside the "to display" region
            element.hideElement = (index < pagination.start || index >= (pagination.start + pagination.length));
        }

        // Enable/Disable page up pagination button according to the page to display and total elements
        this.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= this.menuItems[menu].content.length) ? 'disabled' : '';

        this.render();
    } else {

        // Load using the API
        const linkToAPI = urlsForApi['library']['get'];

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
        this.menuItems[menu].title = (customFilter.media != '') ? '"' + customFilter.media + '"' : 'Search ' + menu;

        if(customFilter.tags != '' && customFilter.tags != undefined) {
            this.menuItems[menu].title += ' {' + customFilter.tags + '} ';
        }

        if(customFilter.type != '') {
            this.menuItems[menu].title += ' [' + customFilter.type + '] ';
        }

        $.ajax({
            url: linkToAPI.url,
            type: linkToAPI.type,
            data: customFilter,
            success: function(res) {

                if(res.data.length == 0) {
                    toastr.info('No results for the filter!', 'Search');
                    lD.toolbar.menuItems[menu].content = null;
                } else {
                    lD.toolbar.menuItems[menu].content = res.data;
                }

                // Enable/Disable page up pagination button according to the page to display and total elements
                lD.toolbar.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= res.recordsTotal) ? 'disabled' : '';

                lD.toolbar.render();
            },
            error: function(jXHR, textStatus, errorThrown) {
                toastr.error('Library load failed!', 'Error');
                lD.toolbar.menuItems[menu].content = null;
            }
        });
    }
}

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 */
BottomToolbar.prototype.openTab = function(menu = -1) {
    
    // Close previous opened menu
    if(menu == -1 && this.openedMenu != -1) {
        this.menuItems[this.openedMenu].state = '';
        this.openedMenu = -1;
    } else if(menu != -1) {

        // Close all menus
        for(let index = this.menuItems.length - 1; index >= 0; index--) {
            this.menuItems[index].state = '';
        }

        // If menu is the default/widget, load content
        if(menu == 0) {
            this.loadContent(0);
        }

        this.menuItems[menu].state = 'active';
        this.openedMenu = menu;
    }

    this.render();
}

/**
 * Create new tab
 */
BottomToolbar.prototype.createNewTab = function() {

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
        title: 'Search ' + this.menuIndex,
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
}

/**
 * Delete tab
 * @param {number} menu
 */
BottomToolbar.prototype.deleteTab = function(menu) {
    
    this.menuItems.splice(menu, 1);
    this.openedMenu = -1;

    this.render();
}
/**
 * Delete all tabs
 */
BottomToolbar.prototype.deleteAllTabs = function() {

    for(let index = this.menuItems.length - 1; index > 0; index--) {
        this.menuItems.splice(index, 1);
    }
    this.openedMenu = -1;

    this.render();
}


/**
 * Calculate pagination
 * @param {number} menu
 */
BottomToolbar.prototype.calculatePagination = function(menu) {

    // Get page and number of elements
    const currentPage = this.menuItems[menu].page;

    // Calculate width
    const containerWidth = this.DOMObject.find('.toolbar-content').width() * (this.contentDimentions.width/100);

    // Calculate number of elements to display
    const elementsToDisplay = Math.floor(containerWidth / (this.cardDimensions.width + this.cardDimensions.margin*2));

    this.menuItems[menu].contentWidth = this.contentDimentions.width;

    return {
        start: currentPage * elementsToDisplay,
        length: elementsToDisplay
    };
}

module.exports = BottomToolbar;