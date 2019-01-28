// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarLayoutJumpList = require('../templates/toolbar-layout-jump-list.hbs');

// Add global helpers
window.formHelpers = require('../helpers/form-helpers.js');

const toolsList = [
    {
        name: toolbarTrans.tools.region.name,
        type: 'region',
        description: toolbarTrans.tools.region.description,
        dropTo: 'layout',
        hideOn: ['playlist'],
        oneClickAdd: ['layout']
    },
    {
        name: toolbarTrans.tools.audio.name,
        type: 'audio',
        description: toolbarTrans.tools.audio.name,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.expiry.name,
        type: 'expiry',
        description: toolbarTrans.tools.expiry.name,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.transitionIn.name,
        type: 'transitionIn',
        description: toolbarTrans.tools.transitionIn.name,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.transitionOut.name,
        type: 'transitionOut',
        description: toolbarTrans.tools.transitionOut.name,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.permissions.name,
        type: 'permissions',
        description: toolbarTrans.tools.permissions.name,
        dropTo: 'all'
    }
];

const defaultMenuItems = [
    {
        name: 'tools',
        title: toolbarTrans.menuItems.tools,
        tool: true,
        pagination: false,
        page: 0,
        content: [],
        state: ''
    },
    {
        name: 'widgets',
        title: toolbarTrans.menuItems.widgets,
        pagination: false,
        page: 0,
        content: [],
        state: '',
        oneClickAdd: ['playlist']
    }
];

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 * @param {object[]} [customButtons] - customized buttons
 * @param {object} [customActions] - customized actions
 */
let Toolbar = function(container, customButtons = null, customActions = {}, jumpList = {}) {
    this.DOMObject = container;
    this.openedMenu = -1;
    this.previousOpenedMenu = -1;

    this.menuItems = defaultMenuItems;
    this.menuIndex = 0;

    // Number of tabs that are fixed ( not removable and always defaulted )
    this.fixedTabs = defaultMenuItems.length;

    // Layout jumplist
    this.jumpList = jumpList;

    // Custom buttons
    this.customButtons = customButtons;

    // Custom actions
    this.customActions = customActions;

    // Lock search buttons
    this.searchButtonsLock = false;

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.cardDimensions = {
        width: 100, // In pixels
        height: 80, // In pixels
        margin: 4 // In pixels
    };

    this.selectedCard = {};

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
            self.menuItems = (jQuery.isEmptyObject(loadedData.menuItems)) ? defaultMenuItems : defaultMenuItems.concat(loadedData.menuItems);
            self.openedMenu = (loadedData.openedMenu != undefined) ? loadedData.openedMenu : -1;
            self.previousOpenedMenu = (loadedData.previousOpenedMenu != undefined) ? loadedData.openedMenu : -1;

            // Set menu index
            if(loadedData.menuIndex != undefined) {
                self.menuIndex = loadedData.menuIndex;
            } else {
                self.menuIndex = self.menuItems.length;
            }

            // Render to reflect the loaded toolbar
            self.render();

            // If there was a opened menu, load content for that one
            if(self.openedMenu != -1) {
                self.loadContent(self.openedMenu);
            }
        } else {

            // Login Form needed?
            if(res.login) {

                window.location.href = window.location.href;
                location.reload(false);
            } else {
                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }

                // Render toolbar even if the user prefs load fail
                self.render();
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.userLoadPreferencesFailed);

    });
};

/**
 * Save user preferences
 * @param {bool=} [clearPrefs = false] - Force reseting user prefs
 */
Toolbar.prototype.savePrefs = function(clearPrefs = false) {
    
    // Save only some of the tab menu data
    let menuItemsToSave = [];
    let openedMenu = this.openedMenu;
    let previousOpenedMenu = this.previousOpenedMenu;

    if(clearPrefs) {
        menuItemsToSave = {};
        openedMenu = -1;
        previousOpenedMenu = -1;
    } else {
        for(let index = this.fixedTabs;index < this.menuItems.length;index++) {

        // Make a copy of the current element
        let elementCopy = Object.assign({}, this.menuItems[index]);

        // Remove content and set page to 0
        elementCopy.content = [];
        elementCopy.page = 0;
        
        menuItemsToSave.push(elementCopy);
    }
    }

    let dataToSave = {
        preference: [
            {
                option: 'toolbar',
                value: JSON.stringify({
                    menuItems: menuItemsToSave,
                    openedMenu: openedMenu,
                    previousOpenedMenu: previousOpenedMenu,
                    menuIndex: this.menuIndex
                })
            }
        ]
    };

    // Save using the API
    const linkToAPI = urlsForApi.user.savePref;

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url,
        type: linkToAPI.type,
        data: dataToSave
    }).done(function(res) {

        if(!res.success) {
            // Login Form needed?
            if(res.login) {

                window.location.href = window.location.href;
                location.reload(false);
            } else {

                toastr.error(errorMessagesTrans.userSavePreferencesFailed);

                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }

                // Render toolbar even if the user prefs load fail
                self.render();
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(errorMessagesTrans.userSavePreferencesFailed);
    });

};

/**
 * Render toolbar
 */
Toolbar.prototype.render = function() {

    let self = this;
    const app = getXiboApp();

    // Deselect selected card on render
    this.selectedCard = {};

    // Get toolbar trans
    let newToolbarTrans = toolbarTrans;

    // Check if trash bin is active
    let trashBinActive = app.selectedObject.isDeletable && (app.readOnlyMode === undefined || app.readOnlyMode === false);
    
    // Get text for bin tooltip
    if(trashBinActive) {
        newToolbarTrans.trashBinActiveTitle = toolbarTrans.deleteObject.replace('%object%', app.selectedObject.type);
    }

    // Check if there are some changes
    let undoActive = app.manager.changeHistory.length > 0;

    // Get last action text for popup
    if(undoActive) { 
        let lastAction = app.manager.changeHistory[app.manager.changeHistory.length - 1];
        if(typeof historyManagerTrans != "undefined" && historyManagerTrans.revert[lastAction.type] != undefined ) {
            newToolbarTrans.undoActiveTitle = historyManagerTrans.revert[lastAction.type].replace('%target%', lastAction.target.type);
        } else {
            newToolbarTrans.undoActiveTitle = '[' + lastAction.target.type + '] ' + lastAction.type;
        }
    }

    // Compile layout template with data
    const html = ToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        tabsCount: (this.menuItems.length > this.fixedTabs),
        customButtons: this.customButtons,
        trashActive: trashBinActive,
        undoActive: undoActive,
        trans: newToolbarTrans
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // If read only mode is enabled
    if(app.readOnlyMode != undefined && app.readOnlyMode === true) {
        this.DOMObject.find('.hide-on-read-only').hide();
    } else {

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
                if(!toolbar.searchButtonsLock) {
                    toolbar.menuItems[index].page -= 1;
                    toolbar.loadContent(index); 
                }
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-right').click(function() {
                if(!toolbar.searchButtonsLock) {
                    toolbar.menuItems[index].page += 1;
                    toolbar.loadContent(index);
                }
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
            if(!self.searchButtonsLock) {
                // Reset page
                self.menuItems[$(this).attr("data-search-id")].page = 0;

                // Load content for the search tab
                self.loadContent($(this).attr("data-search-id"));
            }
        });

        // Delete object
        this.DOMObject.find('#trashContainer').click(
            this.customActions.deleteSelectedObjectAction
        );

        // Delete object
        this.DOMObject.find('#undoContainer').click(
            app.undoLastAction
        );
    }

    // Handle custom buttons
    if(this.customButtons != null) {
        for(let index = 0;index < this.customButtons.length;index++) {

            // Bind action to button
            this.DOMObject.find('#' + this.customButtons[index].id).click(
                this.customButtons[index].action
            );

            // If there is a inactiveCheck, use that function to switch button state
            if(this.customButtons[index].inactiveCheck != undefined) {
                const inactiveClass = (this.customButtons[index].inactiveCheckClass != undefined) ? this.customButtons[index].inactiveCheckClass : 'disabled';
                const toggleValue = this.customButtons[index].inactiveCheck();
                this.DOMObject.find('#' + this.customButtons[index].id).toggleClass(inactiveClass, toggleValue);
            }
        }
    }

    // Set layout jumpList if exists
    if(!$.isEmptyObject(this.jumpList) && $('#layoutJumpList').length == 0) {
        this.setupJumpList($("#layoutJumpListContainer"));
    }

    // If in edit mode
    if(app.readOnlyMode === undefined || app.readOnlyMode === false) {
        // Set cards width/margin and draggable properties
        this.DOMObject.find('.toolbar-card').width(
            this.cardCalculatedWidth
        ).height(
            this.cardDimensions.height
        ).css(
            'margin-left', this.cardDimensions.margin
        ).draggable({
            cursor: 'crosshair',
            handle: '.drag-area',
            cursorAt: {
                top: (this.cardDimensions.height + this.cardDimensions.margin) / 2,
                left: (this.cardDimensions.width + this.cardDimensions.margin) / 2
            },
            opacity: 0.3,
            helper: 'clone',
            start: function() {
                // Deselect previous selections
                self.deselectCardsAndDropZones();

                $('.custom-overlay').show();
            }, 
            stop: function() {
                // Hide designer overlay
                $('.custom-overlay').hide();
            }
        });

        // Select card clicking in the Add button
        this.DOMObject.find('.toolbar-card:not(.card-selected) .add-area').click((e) => {
            self.selectCard($(e.currentTarget).parent()); 
        });

        // Initialize tooltips
        this.DOMObject.find('[data-toggle="tooltip"]').tooltip();

        // Initialize tagsinput
        this.DOMObject.find('input[data-role="tagsinput"]').tagsinput();

        this.DOMObject.find('.media-tags').off('click').on('click', '.media-tags-label', function(e) {

            // See if its the first element, if not add comma
            var tagText = $(this).text();

            // Add text to form
            self.DOMObject.find("#input-tag").tagsinput('add', tagText, {allowDuplicates: false});
        });
    }
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 */
Toolbar.prototype.loadContent = function(menu = -1) {

    // Calculate pagination
    const pagination = this.calculatePagination(menu);

    const app = getXiboApp();

    // Enable/Disable page down pagination button according to the page to display
    this.menuItems[menu].pagBtnLeftDisabled = (pagination.start == 0) ? 'disabled' : '';

    // Replace search button with a spinner icon
    this.DOMObject.find('.search-btn').html('<i class="fa fa-spinner fa-spin"></i>');

    if(menu < this.fixedTabs) { // Fixed Tabs

        switch(menu) {
            // Tools
            case 0:
                this.menuItems[menu].content = toolsList;
                break;

            // Widgets
            case 1:
                this.menuItems[menu].content = modulesList;
                break;

            default:
                this.menuItems[menu].content = [];
                break;
        }

        for(let index = 0;index < this.menuItems[menu].content.length;index++) {
            const element = this.menuItems[menu].content[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            // Hide element if it's outside the "to display" region or is a hideOn this app
            element.hideElement = (index < pagination.start || index >= (pagination.start + pagination.length)) || (element.hideOn != undefined && element.hideOn.indexOf(app.mainObjectType) != -1);
        }

        // Enable/Disable page up pagination button according to the page to display and total elements
        this.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= this.menuItems[menu].content.length) ? 'disabled' : '';
        
        this.menuItems[menu].state = 'active';

        // Save user preferences
        this.savePrefs();

        this.render();

    } else { // Generated tabs ( search )

        // Load using the API
        const linkToAPI = urlsForApi.library.get;

        // Lock buttons
        this.searchButtonsLock = true;

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
            this.menuItems[menu].title = toolbarTrans.tabName.replace('%tagId%', this.menuIndex);
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
                toastr.info(toolbarTrans.noResults, toolbarTrans.search);
                self.menuItems[menu].content = null;
            } else {
                //Convert tags into an array
                res.data.forEach((el) => {
                    if(typeof el.tags != undefined && el.tags != null) {
                        el.tags = el.tags.split(',');
                        el.tagsCount = el.tags.length;
                        el.tagsShow = (el.tagsCount === 1) ? true : false;
                        el.tagsMessage = toolbarTrans.toolbarTagsMessage.replace('%tagCount%', el.tagsCount);

                    } else {
                        el.tagsCount = 0;
                        el.tagsShow = false;
                        el.tagsMessage = '';
                    }
                });

                self.menuItems[menu].content = res.data;
            }

            // Unlock buttons
            self.searchButtonsLock = false;

            // Enable/Disable page up pagination button according to the page to display and total elements
            self.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= res.recordsTotal) ? 'disabled' : '';

            // Save user preferences
            self.savePrefs();

            self.render();
        }).catch(function(jqXHR, textStatus, errorThrown) {

            console.error(jqXHR, textStatus, errorThrown);
            toastr.error(errorMessagesTrans.libraryLoadFailed);

            // Unlock buttons
            self.searchButtonsLock = false;

            self.menuItems[menu].content = null;
        });
    }
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 */
Toolbar.prototype.openTab = function(menu = -1) {

    // Toggle previous opened menu
    if(menu == -1) {

        if(this.openedMenu != -1) { // Close opened tab
            this.previousOpenedMenu = this.openedMenu;
            this.menuItems[this.openedMenu].state = '';
            this.openedMenu = -1;
        } else if(this.previousOpenedMenu != -1 && this.menuItems[this.previousOpenedMenu] != undefined) { // Reopen previously opened tab
            this.menuItems[this.previousOpenedMenu].state = 'active';
            this.openedMenu = this.previousOpenedMenu;
            this.previousOpenedMenu = -1;

            // If menu is the default/widget, load content
            if(this.openedMenu < this.fixedTabs && this.openedMenu > -1) {
                this.loadContent(this.openedMenu);
                return; // To avoid double save and render
            }
        }
    } else { // Open specific menu

        // Close all menus
        for(let index = this.menuItems.length - 1;index >= 0;index--) {
            this.menuItems[index].state = '';
        }

        this.menuItems[menu].state = 'active';
        this.openedMenu = menu;
        this.previousOpenedMenu = -1;

        // If menu is the default/widget/tools, load content
        if(menu < this.fixedTabs && menu > -1) {
            this.loadContent(menu);
            return; // To avoid double save and render
        }
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
        title: toolbarTrans.tabName.replace('%tagId%', this.menuIndex),
        search: true,
        page: 0,
        query: '',
        filters: {
            name: {
                name: toolbarTrans.searchFilters.name,
                value: ''
            },
            tag: {
                name: toolbarTrans.searchFilters.tag,
                value: ''
            },
            type: {
                name: toolbarTrans.searchFilters.type,
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

    if(this.openedMenu >= this.fixedTabs) {
        this.openedMenu = -1;
        this.previousOpenedMenu = -1;
    }

    // Reset menu index
    if(this.menuItems.length === this.fixedTabs) {
        this.menuIndex = this.menuItems.length;
    }

    // Save user preferences
    this.savePrefs();

    this.render();
};

/**
 * Delete all tabs
 */
Toolbar.prototype.deleteAllTabs = function() {

    for(let index = this.menuItems.length - 1;index >= this.fixedTabs;index--) {
        this.menuItems.splice(index, 1);
    }
    
    if(this.openedMenu >= this.fixedTabs) {
        this.openedMenu = -1;
        this.previousOpenedMenu = -1;
    }

    // Reset menu index
    this.menuIndex = this.menuItems.length;

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
    const elementsToDisplay = Math.floor(containerWidth / (this.cardDimensions.width + this.cardDimensions.margin));

    // Space used
    const usedSpace = elementsToDisplay * (this.cardDimensions.width + this.cardDimensions.margin);
    
    // Remaining space to be filled ( without the right margin )
    const remainingSpace = containerWidth - usedSpace - this.cardDimensions.margin;

    // New calculated width
    if(remainingSpace < this.cardDimensions.width) {
        this.cardCalculatedWidth = this.cardDimensions.width + (remainingSpace / elementsToDisplay);
    } else {
        this.cardCalculatedWidth = this.cardDimensions.width;
    }
    
    this.menuItems[menu].contentWidth = this.contentDimentions.width;

    return {
        start: currentPage * elementsToDisplay,
        length: elementsToDisplay
    };
};

/**
* Setup layout jumplist
* @param {object} jumpListContainer
*/
Toolbar.prototype.setupJumpList = function(jumpListContainer) {

    const html = ToolbarLayoutJumpList(this.jumpList);
    const self = this;

    // Append layout html to the main div
    jumpListContainer.html(html);

    jumpListContainer.show();

    const jumpList = jumpListContainer.find('#layoutJumpList');

    jumpList.select2({
        ajax: {
            url: jumpList.data().url,
            dataType: "json",
            data: function(params) {

                var query = {
                    layout: params.term,
                    start: 0,
                    length: 10
                };

                // Set the start parameter based on the page number
                if(params.page != null) {
                    query.start = (params.page - 1) * 10;
                }

                // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                // is opened again)
                if(params.term !== undefined) {
                    localStorage.liveSearchPlaceholder = params.term;
                }

                return query;
            },
            processResults: function(data, params) {
                var results = [];

                $.each(data.data, function(index, element) {
                    results.push({
                        "id": element.layoutId,
                        "text": element.layout
                    });
                });

                var page = params.page || 1;
                page = (page > 1) ? page - 1 : page;

                return {
                    results: results,
                    pagination: {
                        more: (page * 10 < data.recordsTotal)
                    }
                };
            },
            delay: 250
        }
    });

    jumpList.on("select2:select", function(e) {
        // OPTIMIZE: Maybe use the layout load without reloading page
        //self.jumpList.callback(e.params.data.id);

        // Go to the Layout we've selected.
        window.location = jumpList.data().designerUrl.replace(":id", e.params.data.id);
    }).on("select2:opening", function(e) {
        // Set the search box according to the saved value (if we have one)
        
        if(localStorage.liveSearchPlaceholder != null && localStorage.liveSearchPlaceholder !== "") {
            var $search = jumpList.data("select2").dropdown.$search;
            $search.val(localStorage.liveSearchPlaceholder);

            setTimeout(function() {
                $search.trigger("input");
            }, 100);
        }
    });
};

/**
 * Select toolbar card so it can be used
 * @param {object} card - DOM card to select/activate
 */
Toolbar.prototype.selectCard = function(card) {

    const app = getXiboApp();

    // Deselect previous selections
    this.deselectCardsAndDropZones();

    const previouslySelected = this.selectedCard;

    if(previouslySelected[0] != card[0]) {
        
        // Get card info
        const dropTo = $(card).attr('drop-to');
        const subType = $(card).attr('data-sub-type');
        const oneClickAdd = $(card).attr('data-one-click-add');

        if(oneClickAdd != undefined && oneClickAdd.split(',').indexOf(app.mainObjectType) != -1) {

            // Simulate drop item add
            app.dropItemAdd($('[data-type="' + dropTo + '"]'), card);

        } else {

            // Select new card
            $(card).addClass('card-selected');

            // Save selected card data
            this.selectedCard = card;

            // Show designer overlay
            $('.custom-overlay').show().unbind().click(() => {
                this.deselectCardsAndDropZones();
            });

            // Set droppable areas as active
            if(dropTo === 'all' && subType === 'permissions') {
                $('.ui-droppable.permissionsModifiable').addClass('ui-droppable-active');
            } else {
                $('[data-type="' + dropTo + '"].ui-droppable.editable').addClass('ui-droppable-active');
            }
        }
    }
};

/**
 * Deselect all the cards and remove the overlay on the drop zones
 */
Toolbar.prototype.deselectCardsAndDropZones = function() {
    // Deselect other cards
    this.DOMObject.find('.toolbar-card.card-selected').removeClass('card-selected');

    // Remove drop class from droppable elements
    $('.ui-droppable').removeClass('ui-droppable-active');

    // Hide designer overlay
    $('.custom-overlay').hide().unbind();

    // Deselect card
    this.selectedCard = {};
};

module.exports = Toolbar;