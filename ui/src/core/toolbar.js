// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarMediaSearchTemplate = require('../templates/toolbar-media-search.hbs');
const ToolbarMediaQueueTemplate = require('../templates/toolbar-media-queue.hbs');
const ToolbarMediaQueueElementTemplate = require('../templates/toolbar-media-queue-element.hbs');

const toolsList = [
    {
        name: toolbarTrans.tools.audio.name,
        type: 'audio',
        description: toolbarTrans.tools.audio.description,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.expiry.name,
        type: 'expiry',
        description: toolbarTrans.tools.expiry.description,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.transitionIn.name,
        type: 'transitionIn',
        description: toolbarTrans.tools.transitionIn.description,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.transitionOut.name,
        type: 'transitionOut',
        description: toolbarTrans.tools.transitionOut.description,
        dropTo: 'widget'
    },
    {
        name: toolbarTrans.tools.permissions.name,
        type: 'permissions',
        description: toolbarTrans.tools.permissions.description,
        dropTo: 'all'
    }
];

const defaultMenuItems = [
    {
        name: 'library',
        itemName: toolbarTrans.menuItems.libraryName,
        itemIcon: 'photo-video',
        itemTitle: toolbarTrans.menuItems.libraryTitle,
        selectedTab: 0,
        search: true,
        content: [], // Tabs and content
        state: ''
    },
    {
        name: 'tools',
        itemName: toolbarTrans.menuItems.toolsName,
        itemIcon: 'tools',
        itemTitle: toolbarTrans.menuItems.toolsTitle,
        tool: true,
        content: [],
        state: ''
    },
    {
        name: 'widgets',
        itemName: toolbarTrans.menuItems.widgetsName,
        itemTitle: toolbarTrans.menuItems.widgetsTitle,
        itemIcon: 'th-large',
        content: [],
        paging: true,
        state: '',
        oneClickAdd: [],
        favouriteModules: []
    }
];

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 * @param {object} [customActions] - customized actions
 * @param {boolean=} [showOptions] - show options menu
 */
let Toolbar = function(parent, container, customActions = {}, showOptions = false) {

    this.parent = parent;
    
    this.DOMObject = container;
    this.openedMenu = -1;

    // Number of tabs that are fixed ( not removable and always defaulted )
    this.fixedTabs = 3;

    this.menuItems = defaultMenuItems;

    this.libraryMenuIndex = 0;

    this.widgetMenuIndex = 2;

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.selectedCard = {};

    // Custom actions
    this.customActions = customActions;

    // Flag to mark if the toolbar has been rendered at least one time
    this.firstRun = true;

    // Use queue to add media
    this.useQueue = true;

    // Media queue
    this.selectedQueue = {};

    // Options menu
    this.showOptions = showOptions;

    // Refresh opened menu and clear selections on window resize
    const self = this;
    $(window).on('resize.toolbar-' + self.parent.mainObjectType, _.debounce(function(e) {
        if(e.target === window) {
            // Resize only if toolbar is visible
            if(self.DOMObject.is(':visible')) {
                // Deselect previous selections
                self.deselectCardsAndDropZones();

                // If there was a opened menu in the toolbar, open that tab
                if(self.openedMenu != undefined && self.openedMenu != -1) {
                    self.openMenu(self.openedMenu, true);
                }
            }
        }
    }, 250));

    this.customModuleList = modulesList;
};

/**
 * Load user preferences
 */
Toolbar.prototype.loadPrefs = function() {
    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    const app = this.parent;

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=toolbar',
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {

            let loadedData = JSON.parse(res.data.value);

            // Populate the toolbar with the returned data
            self.menuItems[self.libraryMenuIndex].content = (loadedData.libraryContent != undefined) ? loadedData.libraryContent : [];
            self.menuItems[self.libraryMenuIndex].searchPosition = loadedData.searchPosition;

            self.openedMenu = (loadedData.openedMenu != undefined) ? loadedData.openedMenu : -1;

            // Load favourites
            self.menuItems[self.widgetMenuIndex].favouriteModules = (loadedData.favouriteModules != undefined) ? loadedData.favouriteModules : [];

                // Tooltip options
            app.common.displayTooltips = (loadedData.displayTooltips == 1 || loadedData.displayTooltips == undefined);

            // Reload tooltips
            app.common.reloadTooltips(app.editorContainer);

            // If there was a opened menu, load content for that one
            if(self.openedMenu != -1) {
                self.openMenu(self.openedMenu, true);
            } else {
                // Render to reflect the loaded toolbar
                self.render();
            }

            // Reload topbar if exists
            if(app.topbar != undefined) {
                app.topbar.render();
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
    const app = this.parent;

    // Save only some of the tab menu data
    let libraryContent = [];
    let searchPosition;
    let openedMenu = this.openedMenu;
    let displayTooltips = (app.common.displayTooltips) ? 1 : 0;
    let favouriteModules = [];

    if(clearPrefs) {
        libraryContent = [];
        openedMenu = -1;
        displayTooltips = 1;
    } else {
        // Get library content
        libraryContent = this.menuItems[this.libraryMenuIndex].content;

        // Get library window position
        searchPosition = this.menuItems[this.libraryMenuIndex].searchPosition;

        // Save favourite
        favouriteModules = this.menuItems[this.widgetMenuIndex].favouriteModules;
    }

    let dataToSave = {
        preference: [
            {
                option: 'toolbar',
                value: JSON.stringify({
                    libraryContent: libraryContent,
                    searchPosition: searchPosition,
                    openedMenu: openedMenu,
                    displayTooltips: displayTooltips,
                    favouriteModules: favouriteModules
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

    // Load preferences when the toolbar is rendered for the first time
    if(this.firstRun) {
        // Mark toolbar as loaded
        this.firstRun = false;

        // Load user preferences
        this.loadPrefs();
    }

    let self = this;
    const app = this.parent;

    // Deselect selected card on render
    this.selectedCard = {};

    // Get toolbar trans
    let newToolbarTrans = Object.assign({}, toolbarTrans);

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
        if(typeof historyManagerTrans != "undefined" && historyManagerTrans.revert[lastAction.type] != undefined) {
            newToolbarTrans.undoActiveTitle = historyManagerTrans.revert[lastAction.type].replace('%target%', lastAction.target.type);
        } else {
            newToolbarTrans.undoActiveTitle = '[' + lastAction.target.type + '] ' + lastAction.type;
        }
    }

    // Compile toolbar template with data
    const html = ToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        displayTooltips: app.common.displayTooltips,
        trashActive: trashBinActive,
        undoActive: undoActive,
        trans: newToolbarTrans,
        showOptions: self.showOptions,
        mainObjectType: app.mainObjectType
    });

    // Append toolbar html to the main div
    this.DOMObject.html(html);

    // If read only mode is enabled
    if(app.readOnlyMode != undefined && app.readOnlyMode === true) {
        // Hide edit mode fields
        this.DOMObject.find('.hide-on-read-only').hide();
        
        // Create the read only alert message
        let $readOnlyMessage = $('<div id="read-only-message" class="alert alert-info btn text-center navbar-nav" data-toggle="tooltip" data-placement="bottom" data-title="' + layoutDesignerTrans.readOnlyModeMessage + '" role="alert"><strong>' + layoutDesignerTrans.readOnlyModeTitle + '</strong>&nbsp;' + layoutDesignerTrans.readOnlyModeMessage + '</div>');

        // Prepend the element to the bottom toolbar's content
        $readOnlyMessage.prependTo(this.DOMObject.find('.container-toolbar .navbar-collapse')).click(lD.checkoutLayout);
    } else {

        // Handle menus
        for(let i = 0;i < this.menuItems.length;i++) {

            const toolbar = self;
            const index = i;
            const $scrollArea = toolbar.DOMObject.find('#content-' + index + ' .toolbar-pane-content-container');
            const scrollStepSpeed = 200;

            this.DOMObject.find('#btn-menu-' + index).click(function() {
                toolbar.openMenu(index);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-left-' + index).click(function() {
                $scrollArea.scrollLeft($scrollArea.scrollLeft() - scrollStepSpeed);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-right-' + index).click(function() {
                $scrollArea.scrollLeft($scrollArea.scrollLeft() + scrollStepSpeed);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-top-left-' + index).click(function() {
                $scrollArea.scrollLeft(0);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-top-right-' + index).click(function() {
                $scrollArea.scrollLeft(9999);
            });
        }

        // Delete object
        this.DOMObject.find('#trashContainer').click(function() {
            if($(this).hasClass('active')) {
                self.customActions.deleteSelectedObjectAction();
            }
        });

        // Revert last action
        this.DOMObject.find('#undoContainer').click(function() {
            if($(this).hasClass('active')) {
                app.undoLastAction();
            }
        });

        // Enable multi select mode
        this.DOMObject.find('#multiSelectContainer').click(function() {
            self.toggleMultiselectMode();
        });
    }

    const setButtonActionAndState = function(button) {
        let buttonInactive = false;

        // Bind action to button
        self.DOMObject.find('#' + button.id).click(
            button.action
        );

        // If there is a inactiveCheck, use that function to switch button state
        if(button.inactiveCheck != undefined) {
            const inactiveClass = (button.inactiveCheckClass != undefined) ? button.inactiveCheckClass : 'disabled';
            const toggleValue = button.inactiveCheck();
            self.DOMObject.find('#' + button.id).toggleClass(inactiveClass, toggleValue);
            buttonInactive = toggleValue;
        }

        return buttonInactive;
    };

    // If in edit mode
    if(app.readOnlyMode === undefined || app.readOnlyMode === false) {
        // Set cards width/margin and draggable properties
        this.DOMObject.find('.toolbar-card').each(function() {
            
            $(this).draggable({
                cursor: 'crosshair',
                appendTo: $(this).parents('.toolbar-pane:first'),
                handle: '.drag-area',
                cursorAt: {
                    top: ($(this).height() + ($(this).outerWidth(true) - $(this).outerWidth()) / 2) / 2,
                    left: ($(this).width() + ($(this).outerWidth(true) - $(this).outerWidth()) / 2) / 2
                },
                opacity: 0.3,
                helper: 'clone',
                start: function() {
                    // Deselect previous selections
                    self.deselectCardsAndDropZones();

                    // Show overlay
                    $('.custom-overlay').show();

                    // Mark card as being dragged
                    $(this).addClass('card-dragged');

                    // Mark content as selected
                    $(this).parent('.toolbar-pane-content').addClass('selected');
                },
                stop: function() {

                    // Hide overlay
                    $('.custom-overlay').hide();

                    // Remove card class as being dragged
                    $(this).removeClass('card-dragged');

                    // Mark content as unselected
                    $(this).parent('.toolbar-pane-content').removeClass('selected');
                }
            });
        });

        // Select card clicking in the Add button
        this.DOMObject.find('.toolbar-card:not(.card-selected) .add-area').click((e) => {
            self.selectCard($(e.currentTarget).parent());
        });

        // Select card clicking in the Add button
        this.DOMObject.find('.toolbar-card:not(.card-selected) .btn-favourite').click((e) => {
            this.toggleFavourite(e.currentTarget);
        });

        // If library meny is selected, open media content window
        if(this.openedMenu != -1 && this.menuItems[this.openedMenu].name === 'library') {
            this.mediaContentCreateWindow(this.openedMenu);
        }
    }

    // Options menu
    if(self.showOptions) {
        self.DOMObject.find('.navbar-submenu-options-container').off().click(function(e) {
            e.stopPropagation();
        });

        // Toggle tooltips
        self.DOMObject.find('#displayTooltips').off().click(function() {

            app.common.displayTooltips = $('#displayTooltips').prop('checked');

            if(app.common.displayTooltips) {
                toastr.success(editorsTrans.tooltipsEnabled);
            } else {
                toastr.error(editorsTrans.tooltipsDisabled);
            }

            self.savePrefs();

            app.common.reloadTooltips(app.editorContainer);
        });

        // Reset tour
        if(typeof app.resetTour === 'function') {
            self.DOMObject.find('#resetTour').removeClass('hidden').off().click(function() {
                app.resetTour();
            });
        }
    }

    // Save default tolbar nav z-index
    this.defaultZIndex = this.DOMObject.find('nav').css('z-index');
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 */
Toolbar.prototype.loadContent = function(menu = -1) {
    const app = this.parent;

    // Make menu state to be active
    this.menuItems[menu].state = 'active';

    if(this.menuItems[menu].name === 'tools') {
        this.menuItems[menu].content = toolsList;
    } else if(this.menuItems[menu].name === 'widgets') {
        // Calculate scroll region width
        var totalWidth = this.DOMObject.find('.navbar-collapse').outerWidth();
        var widthMenuLeft = this.DOMObject.find('.toolbar-menu-left').width();
        var widthMenuRight = this.DOMObject.find('.toolbar-menu-right').width();

        // Content width minus the left and right button areas, and the paging buttons width (20px * 4)
        this.menuItems[menu].containerWidth = totalWidth - widthMenuLeft - widthMenuRight - 80;

        // Sort by favourites
        var favouriteModules = [];
        var otherModules = [];

        for(let index = 0;index < this.customModuleList.length;index++) {
            const element = this.customModuleList[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            if($.inArray(element.type, this.menuItems[menu].favouriteModules) > -1) {
                element.favourited = true;
                favouriteModules.push(element);
            } else {
                element.favourited = false;
                otherModules.push(element);
            }

            element.oneClickAdd = this.menuItems[menu].oneClickAdd;
        }

        // Add elements to menu content
        this.menuItems[menu].content = favouriteModules.concat(otherModules);
    }

    // Check if content needs to be hidden on this editor
    for(let index = 0;index < this.menuItems[menu].content.length;index++) {
        const element = this.menuItems[menu].content[index];
        element.hideElement = element.hideOn != undefined && element.hideOn.indexOf(app.mainObjectType) != -1;
    }

    // Save user preferences and render
    this.savePrefs();
    this.render();
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 * @param {bool} forceOpen - force tab open ( even if opened before )
 */
Toolbar.prototype.openMenu = function(menu = -1, forceOpen = false) {
    // Deselect previous selections
    this.deselectCardsAndDropZones();

    // Open specific menu
    if(menu != -1) {
        let active = (forceOpen) ? false : (this.menuItems[menu].state == 'active');

        // Close all menus
        for(let index = this.menuItems.length - 1;index >= 0;index--) {
            this.menuItems[index].state = '';
        }

        if(active){
            this.openedMenu = -1;
        } else {
            this.openedMenu = menu;

            // If menu is the default/widget/tools, load content
            if(menu > -1) {
                this.loadContent(menu);
                return; // To avoid double save and render
            }
        }
    }

    // Save user preferences
    this.savePrefs();

    // Render toolbar
    this.render();
};

/**
 * Select toolbar card so it can be used
 * @param {object} card - DOM card to select/activate
 */
Toolbar.prototype.selectCard = function(card) {

    const app = this.parent;

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
            if($('[data-type="' + dropTo + '"]').length > 0) {
                app.dropItemAdd($('[data-type="' + dropTo + '"]'), card);
            } else if(dropTo == 'layout') {
                // Create temporary object simulating the layout object
                let $tempLayoutObj = $('<div>').data({
                    'id': app.mainObjectId,
                    'type': 'layout'
                });

                app.dropItemAdd($tempLayoutObj, card);
            }
        } else {
            // Select new card
            $(card).addClass('card-selected');
            $(card).parent('.toolbar-pane-content').addClass('selected');

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
 * Select media so it can be used
 * @param {object} media - DOM card to select/activate
 */
Toolbar.prototype.selectMedia = function(media, data) {

    const alreadySelected = $(media).hasClass('media-selected');

    // Deselect previous selections
    this.deselectCardsAndDropZones();

    if(!alreadySelected) {

        // Select row in the table
        $(media).addClass('media-selected');
        $(media).parent('.toolbar-pane-content').addClass('selected');

        // Create temp object to simulate a card and use it as the selected card
        this.selectedCard = $('<div drop-to="region">').data({
            type: 'media',
            mediaId: data.mediaId,
            subType: data.mediaType
        });

        // Show designer overlay
        $('.custom-overlay').show().unbind().click(() => {
            this.deselectCardsAndDropZones();
        });

        // Set droppable regions as active
        $('[data-type="region"].ui-droppable.editable').addClass('ui-droppable-active');
    }
};

/**
 * Deselect all the cards and remove the overlay on the drop zones
 */
Toolbar.prototype.deselectCardsAndDropZones = function() {
    // Deselect other cards
    this.DOMObject.find('.toolbar-card.card-selected').removeClass('card-selected');

    // Deselect other media
    this.DOMObject.find('.media-table tr.media-selected').removeClass('media-selected');

    // Remove content selected class
    this.DOMObject.find('.toolbar-pane-content.selected').removeClass('selected');

    // Remove drop class from droppable elements
    $('.ui-droppable').removeClass('ui-droppable-active');

    // Disable multi-select mode
    if(this.parent.editorContainer.hasClass('multi-select')) {
        this.toggleMultiselectMode(false);
    }

    // Hide designer overlay
    $('.custom-overlay').hide().unbind();

    // Deselect card
    this.selectedCard = {};
};

/**
 * Create new tab
 */
Toolbar.prototype.createNewTab = function(menu) {

    let moduleListFiltered = [];
    let usersListFiltered = [];

    const self = this;

    // Filter module list to create the types for the filter
    modulesList.forEach(element => {
        if(element.assignable == 1 && element.regionSpecific == 0) {
            moduleListFiltered.push({
                type: element.type,
                name: element.name
            });
        }
    });

    usersList.forEach(element => {
        usersListFiltered.push({
            userId: element.userId.toString(),
            name: element.userName
        });
    });

    this.menuItems[menu].content.push({
        name: toolbarTrans.tabName.replace('%tagId%', self.menuItems[menu].content.length),
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
            owner: {
                name: toolbarTrans.searchFilters.owner,
                values: usersListFiltered
            },
        }
    });

    this.menuItems[menu].selectedTab = this.menuItems[menu].content.length - 1;

    self.savePrefs();
};

/**
 * Delete tab
 * @param {number} menu
 */
Toolbar.prototype.deleteTab = function(menu) {

    // Remove menu option from the array
    this.menuItems.splice(menu, 1);

    if(this.openedMenu == menu) {
        // Deselect menu if we're closing the selected one
        this.openedMenu = -1;
    } else if(this.openedMenu < menu) {
        // If the deleted menu is lower than the selected, update the selected index
        this.openedMenu -= 1;
    }

    // Deselect previous selections
    this.deselectCardsAndDropZones();

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
    }

    // Deselect previous selections
    this.deselectCardsAndDropZones();

    // Save user preferences
    this.savePrefs();
    this.render();
};

/**
 * Opens a new tab and search for the given media type
 * @param {string} type - Type of media
 */
Toolbar.prototype.openNewTabAndSearch = function(type) {
    // Open library library window
    this.openMenu(this.libraryMenuIndex);
    
    // Create a new tab
    this.createNewTab(this.libraryMenuIndex);

    // Switch the selected tab's Type select box to the type we want
    this.menuItems[this.libraryMenuIndex].content[this.menuItems[this.libraryMenuIndex].selectedTab].filters.type.value = type;
    //this.DOMObject.find('#media-search-form-' + this.openedMenu + ' #input-type-' + this.openedMenu).val(type);

    // Search/Load Content
    this.loadContent(this.openedMenu);
};

/**
 * Media form callback
 */
Toolbar.prototype.mediaContentCreateWindow = function(menu) {
    const self = this;
    const app = this.parent;

    // Deselect previous selections
    self.deselectCardsAndDropZones();

    // Get search window Jquery object
    const $libraryWindowContent = self.DOMObject.find('#content-' + menu + '.library-content');

    if(this.menuItems[menu].content.length === 0) {
        this.createNewTab(menu);
    }

    // Render template
    const html = ToolbarMediaSearchTemplate({
        menuIndex: menu,
        menuObj: this.menuItems[menu],
        trans: toolbarTrans,
        closeTabs: this.menuItems[menu].content.length > 1
    });

    // Append template to the search main div
    self.DOMObject.find('#media-search-container-' + menu).html(html);

    // Set paging max to 5
    $.fn.DataTable.ext.pager.numbers_length = 5;
    
    // Make search window to be draggable and resizable
    $libraryWindowContent
        .appendTo('.editor-bottom-bar > nav')
        .draggable({
            containment: 'window',
            scroll: false,
            handle: '.drag-handle'
        })
        .resizable({
            minWidth: 640
        }).off('dragstart dragstart resizestart resizestop dragstop').on('dragstart',
            function() {
                // Remove right sticky css
                $(this).css('right', 'auto');
        }).on('resizestart',
            function() {
                self.tablePositionUpdate($libraryWindowContent);
            }
        ).on('resizestop dragstop',
            function(event, ui) {
                // Save only if we're not in hide mode
                if(!$(this).hasClass('hide-mode')) {
                    // Save window positioning
                    self.menuItems[menu].searchPosition = {
                        width: $(this).width(),
                        height: $(this).height(),
                        top: $(this).position().top,
                        left: $(this).position().left
                    };

                    self.savePrefs();

                    self.tablePositionUpdate($libraryWindowContent);
                }
            }
        );

    // If we have set positions, set them on load
    if(self.menuItems[menu].searchPosition != undefined) {
        const position = self.menuItems[menu].searchPosition;

        if(position.left + position.width > $(window).width()){
            position.left = $(window).width() - position.width;
        }

        if(position.top + position.height > $(window).height()) {
            position.top = $(window).height() - position.height;
        }

        if(position.left < 0) {
            position.left = 0;
        }

        if(position.top < 0) {
            position.top = 0;
        }

        $libraryWindowContent.width(position.width);
        $libraryWindowContent.height(position.height);
        $libraryWindowContent.css({top: position.top});
        $libraryWindowContent.css({left: position.left});
    }

    // Search content window buttons handling
    $libraryWindowContent.find('.btn-window-close').off().click(function() {
        self.openMenu($(this).data('menu'));
    });

    $libraryWindowContent.find('.btn-window-new-tab').off().click(function() {
        // Get menu index
        const menu = $(this).data('menu');

        // Create new tab
        self.createNewTab(menu);

        // Populate selected tab
        self.mediaContentCreateWindow(menu);
    });

    $libraryWindowContent.find('.media-search-tab:not(.active):not(.media-tab-close)').off().click(function(ev) {
        ev.stopPropagation();

        // Get tab index
        const tab = $(this).data('tab');

        // Change selected tav
        self.menuItems[menu].selectedTab = tab;

        // Populate selected tab
        self.mediaContentCreateWindow(menu);
    });

    $libraryWindowContent.find('.media-tab-close').off().click(function(ev) {
        ev.stopPropagation();

        // Get tab index
        const tabToDelete = $(this).parent().data('tab');
        let selectedTab = self.menuItems[menu].selectedTab;

        // Select another tab
        if(selectedTab >= tabToDelete && selectedTab > 0) {
            selectedTab--;
        }

        // Delete tab and content
        self.menuItems[menu].content.splice(tabToDelete, 1);
        
        // Select new tab
        self.menuItems[menu].selectedTab = selectedTab;

        // Update tab names
        self.updateTabNames(menu);

         // Populate selected tab
        self.mediaContentCreateWindow(menu);
    });

    // Populate selected tab
    self.mediaContentPopulateTable(menu);

    // Create media queue if in backup
    if(!$.isEmptyObject(self.selectedQueue)){
        self.createQueue(menu);
    }
};

/**
 * Media content populate table
 */
Toolbar.prototype.mediaContentPopulateTable = function(menu) {
    const self = this;
    const tabIndex = self.menuItems[0].selectedTab;
    const tabObj = self.menuItems[menu].content[self.menuItems[0].selectedTab];

    // Destroy previous table
    self.DOMObject.find('#media-table-' + menu).DataTable().destroy();
    self.DOMObject.find('#media-table-' + menu).empty();

    var mediaTable = self.DOMObject.find('#media-table-' + menu).DataTable({
        "language": dataTablesLanguage,
        "lengthMenu": [5, 10],
        "pageLength": 5,
        "autoWidth": false,
        serverSide: true, stateSave: true,
        searchDelay: 3000,
        "order": [[1, "asc"]],
        "filter": false,
        ajax: {
            url: librarySearchUrl + '?assignable=1&retired=0',
            "data": function(d) {
                $.extend(d, self.DOMObject.find('#media-search-container-' + menu + ' #media-search-form-' + tabIndex).serializeObject());
            }
        },
        "columns": [
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if(type !== "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"glyphicon glyphicon-plus-sign\"></a>";
                }
            },
            {"data": "mediaId"},
            {"data": "name"},
            {"data": "mediaType"},
            {
                "sortable": false,
                "data": dataTableCreateTags
            },
            {
                "name": "mediaId",
                "data": null,
                "render": function(data, type, row, meta) {
                    if(type === "display") {
                        // Return only the image part of the data
                        if(data.thumbnailUrl === '')
                            return '';
                        else
                            return '<img src="' + data.thumbnailUrl + '"/>';
                        return data;
                    } else {
                        return row.mediaId;
                    }
                }
            }
        ]
    });

    mediaTable.on('draw', function(e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        self.DOMObject.find(".assignItem").click(function() {
            // Get the row that this is in.
            var data = mediaTable.row($(this).closest("tr")).data();

            if(self.useQueue) {
                self.addToQueue(menu, data);
            } else {
                self.selectMedia($(this).closest('tr'), data);
            }
        });

        self.updateTabNames(menu);

        self.tablePositionUpdate(self.DOMObject.find('#content-' + menu + '.library-content'));
    });

    mediaTable.on('processing.dt', dataTableProcessing);

    // Refresh the table results
    var filterRefresh = function(mediaTable, tabObj) {

        // Save filter options
        tabObj.filters.name.value = self.DOMObject.find('#media-search-form-' + tabIndex + ' #input-name-' + tabIndex).val();
        tabObj.filters.tag.value = self.DOMObject.find('#media-search-form-' + tabIndex + ' #input-tag-' + tabIndex).val();
        tabObj.filters.type.value = self.DOMObject.find('#media-search-form-' + tabIndex + ' #input-type-' + tabIndex).val();
        tabObj.filters.owner.value = self.DOMObject.find('#media-search-form-' + tabIndex + ' #input-owner-' + tabIndex).val();

        self.savePrefs();

        self.updateTabNames(menu);

        // Reload table
        mediaTable.ajax.reload();
    };

    // Prevent filter form submit and bind the change event to reload the table
    self.DOMObject.find('#media-search-form-' + tabIndex).on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // Bind seach action to refresh the results
    self.DOMObject.find('#media-search-form-' + tabIndex + ' select, input[type="text"]').change(_.debounce(function() {
        filterRefresh(mediaTable, tabObj);
    }, 500));

    self.DOMObject.find('#media-search-form-' + tabIndex + ' input[type="text"]').on('input', _.debounce(function() {
        filterRefresh(mediaTable, tabObj);
    }, 500));

    // Initialize tagsinput
    self.DOMObject.find('#media-search-form-' + tabIndex + ' input[data-role="tagsinput"]').tagsinput();

    self.DOMObject.find('#media-table-' + menu).off('click').on('click', '#tagDiv .btn-tag', function() {

        // See if its the first element, if not add comma
        var tagText = $(this).text();

        // Add text to form
        self.DOMObject.find('#media-search-form-' + tabIndex + ' input[data-role="tagsinput"]').tagsinput('add', tagText, {allowDuplicates: false});
    });
};


/**
 * Update tab height
 */
Toolbar.prototype.tablePositionUpdate = function(container) {
    // Calculate table container height
    const tableContainerHeight = container.find('.media-search-controls').height() + container.find('.media-search-form:not(.hidden)').height() + container.find('.dataTables_wrapper').height();

    // Set resizable min height
    if(container.resizable('instance') != undefined) {
        container.resizable('option', 'minHeight', tableContainerHeight);
    }

    // Fix height if bigger than the min
    if(container.height() < tableContainerHeight) {
        container.height(tableContainerHeight);
    }
};

/**
 * Update tab name
 */
Toolbar.prototype.updateTabNames = function(menu) {
    for(let tab = 0;tab < this.menuItems[menu].content.length;tab++) {

        const tabContent = this.menuItems[menu].content[tab];
        const customFilter = tabContent.filters;

        // Change tab name to reflect the search query
        if(customFilter.name.value != '' && customFilter.name.value != undefined) {
            tabContent.name = '"' + customFilter.name.value + '"';
        } else {
            tabContent.name = toolbarTrans.tabName.replace('%tagId%', tab);
        }

        if(customFilter.tag.value != '' && customFilter.tag.value != undefined) {
            tabContent.name += ' {' + customFilter.tag.value + '} ';
        }

        if(customFilter.type.value != '' && customFilter.type.value != undefined) {
            tabContent.name += ' [' + customFilter.type.value + '] ';
        }

        // Change 
        this.DOMObject.find('#content-' + menu + ' #tab-name-' + tab).html(tabContent.name);
    }
};

/**
 * Mark/Unmark as favourite
 */
Toolbar.prototype.toggleFavourite = function(target) {

    let favouriteModulesArray = this.menuItems[this.widgetMenuIndex].favouriteModules;
    let markAsFav = false;

    const $card = $(target).parent('.toolbar-card[data-type="module"]');
    const cardType = $card.data().subType;
    const positionInArray = $.inArray(cardType, favouriteModulesArray);

    // Add/remove from the fav array
    if(positionInArray > -1) {
        // Remove from favourites
        favouriteModulesArray.splice(positionInArray, 1);
    } else {
        // Add to favourites
        markAsFav = true;
        favouriteModulesArray.push(cardType);
    }

    // Show notification
    toastr.success((markAsFav) ? toolbarTrans.addedToFavourites : toolbarTrans.removedFromFavourites, '', { positionClass: 'toast-bottom-right' });

    // Save user preferences
    this.savePrefs();

    // Reload toolbar widget content
    this.loadContent(2);
};

/**
 * Queue
 */
Toolbar.prototype.createQueue = function(menu, target = null) {
    const self = this;

    let html = '';

    if(this.selectedQueue != undefined && !$.isEmptyObject(this.selectedQueue)) {
        // Get html from backup
        html = $(this.selectedQueue)[0].outerHTML;

        // Remove previous queue
        this.destroyQueue(menu);
    } else {
        html = ToolbarMediaQueueTemplate({
            trans: toolbarTrans
        });
    }
    // Append the queue to the library search window
    this.DOMObject.find('#content-' + menu).append(html);

    // Handle destroy queue button
    this.DOMObject.find('#content-' + menu + ' .btn-queue-close').click(function() {
        self.destroyQueue(menu);
    });
    
    // Update queue position
    this.updateQueue(menu);

    // Add element to queue
    if(target){
        this.addToQueue(menu, target);
    }
    
    // Make queue sortable
    this.DOMObject.find('#content-' + menu + ' .media-add-queue-list').sortable();

    // Handle buttons
    const $mediaQueue = this.DOMObject.find('#content-' + menu + ' .media-add-queue');
    $mediaQueue.find('.media-add-queue-buttons').on('click', '.btn.active', function() {

        const buttonType = $(this).data('type');

        if(buttonType == 'toRegion' || buttonType == 'toPlaylist') {
            self.queueAddToRegionPlaylist(menu);
        } else if(buttonType == 'selectRegion') {
            self.queueToggleToAddMode(menu);
        } else if(buttonType == 'cancel') {
            self.queueToggleToAddMode(menu, false);
        }
    });

    // Handle remove queue button
    $mediaQueue.on('click', '.queue-element-remove', function() {
        self.removeFromQueue(menu, $(this));
    });
};

Toolbar.prototype.updateQueue = function(menu) {
    const $mediaQueue = this.DOMObject.find('#content-' + menu + ' .media-add-queue');
    const app = this.parent;

    // Position queue
    $mediaQueue.css('left', - $mediaQueue.outerWidth()).show();

    // Update queue button status
    $mediaQueue.find('.media-add-queue-buttons .btn').removeClass('active'); // Remove active from all buttons
    
    if($mediaQueue.parent().hasClass('hide-mode')) {
        $mediaQueue.find('.btn-queue-close').hide();
        $mediaQueue.find('.media-add-queue-buttons button[data-type="cancel"]').addClass('active'); 
    } else {
        $mediaQueue.find('.btn-queue-close').show();

        if(app.mainObjectType == 'playlist') {
            $mediaQueue.find('.media-add-queue-buttons button[data-type="toPlaylist"]').addClass('active');  
        } else if(app.selectedObject.type === 'region') {
            $mediaQueue.find('.media-add-queue-buttons button[data-type="toRegion"]').addClass('active');  
        } else {
            $mediaQueue.find('.media-add-queue-buttons button[data-type="selectRegion"]').addClass('active'); 
        }
    }

    // Show drop overlay if queue has elements
    if($mediaQueue.find('.queue-element').length > 0) {
        this.queueToggleOverlays(menu);
    } else {
        this.queueToggleOverlays(menu, false);
    }

    // Save backup
    this.selectedQueue = $mediaQueue;
};

Toolbar.prototype.destroyQueue = function(menu) {
    // Destroy queue element
    $(this.DOMObject.find('.media-add-queue')).remove();

    // Hide drop overlay
    this.queueToggleOverlays(menu, false);

    // Clear media backup
    this.selectedQueue = {};
};

Toolbar.prototype.addToQueue = function(menu, target) {
    const self = this;

    if(this.DOMObject.find('.media-add-queue').length == 0) {
        this.createQueue(menu, target);
    } else {
        // Create a new element with a template
        const newElementHTML = ToolbarMediaQueueElementTemplate({
            target: target,
            trans: toolbarTrans
        });

        // Add data to the new element and create a jquery object
        const $newElement = $(newElementHTML).data(target);

        // Add new element to the list
        this.DOMObject.find('#content-' + menu + ' .media-add-queue-list').append(
            $newElement
        );

        // Update queue position
        this.updateQueue(menu);
    }
};

Toolbar.prototype.removeFromQueue = function(menu, target) {
    const $mediaList = $(target).parents('.media-add-queue-list');
    
    // Remove element
    $(target).parent().remove();

    // If the list is empty, remove it
    if($mediaList.find('div').length == 0) {
        this.destroyQueue(menu);
    } else {
        // Update queue position
        this.updateQueue(menu);
    }
};

Toolbar.prototype.queueToggleOverlays = function(menu, enable = true) {
    const $mediaQueue = this.DOMObject.find('#content-' + menu + ' .media-add-queue');

    // Mark queue as add enabled/disabled
    $mediaQueue.data('toAdd', enable);

    if(enable) {
        // Show designer overlay
        $('.custom-overlay').show();

        // Set droppable areas as active
        $('[data-type="region"].ui-droppable.editable').addClass('ui-droppable-active');
    } else {
        this.deselectCardsAndDropZones();
    }
};

Toolbar.prototype.queueToggleToAddMode = function(menu, enable = true) {
    const $mediaQueue = this.DOMObject.find('#content-' + menu + ' .media-add-queue');
    const self = this;

    // Show/hide table container
    $mediaQueue.parent().toggleClass('hide-mode', enable);

    // Enable/disable drag
    $mediaQueue.parent().resizable(enable ? 'disable' : 'enable');

    if(enable) {

        // Click on overlay do toggle add mode
        $('.custom-overlay').unbind().click(() => {
            self.queueToggleToAddMode(menu, false);
        });
    }

    // Update queue position
    this.updateQueue(menu);
};

Toolbar.prototype.queueAddToRegionPlaylist = function(menu) {
    const app = this.parent;

    let mediaQueueArray = [];

    this.selectedQueue.find('.queue-element').each(function() {
        mediaQueueArray.push($(this).attr('id'));
    });

    let playlistId = null;

    // Get playlist id
    if(app.selectedObject.type == 'region') {
        playlistId = app.getElementByTypeAndId('region', app.selectedObject.id).playlists.playlistId;

        // Add media queue to playlist
        app.addMediaToPlaylist(playlistId, mediaQueueArray);
    } else if(app.mainObjectType == 'playlist' && app.playlist != undefined) {
        app.playlist.addMedia(mediaQueueArray);
    }

    // Destroy queue
    this.destroyQueue(menu);
};

/**
 * Revert last action
 */
Toolbar.prototype.toggleMultiselectMode = function(forceSelect = null) {
    const self = this;
    const app = this.parent;
    const timeline = app.timeline;
    const editorContainer = app.editorContainer;

    const updateTrashContainer = function() {
        // Upate trash container status
        self.DOMObject.find('#trashContainer').toggleClass('active', (timeline.DOMObject.find('.playlist-widget.multi-selected').length > 0));
    };

    // Check if needs to be selected or unselected
    let multiSelectFlag = (forceSelect != null) ? forceSelect : !editorContainer.hasClass('multi-select');

    // Toggle multi select class on container
    editorContainer.toggleClass('multi-select', multiSelectFlag);

    // Toggle class on button
    this.DOMObject.find('#multiSelectContainer').toggleClass('multiselect-active', multiSelectFlag);

    if(multiSelectFlag) {
        // Show overlay
        $('.custom-overlay').show().unbind().click(() => {
            self.deselectCardsAndDropZones();
        });

        // Disable timeline sort
        timeline.DOMObject.find('#timeline-container').sortable('disable');
        
        // Enable select for each widget
        timeline.DOMObject.find('.playlist-widget.deletable').removeClass('selected').unbind().click(function(e) {
            e.stopPropagation();
            $(this).toggleClass('multi-selected');
            
            updateTrashContainer();
        });

        updateTrashContainer();
    } else {
        // Hide designer overlay
        $('.custom-overlay').hide().unbind();
        
        // Re-render timeline
        app.renderContainer(timeline);

        // Re-render toolbar
        app.renderContainer(this);
    }
};

module.exports = Toolbar;