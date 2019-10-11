// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarMediaSearchTemplate = require('../templates/toolbar-media-search.hbs');

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
        page: 0,
        content: [],
        state: ''
    },
    {
        name: 'tools',
        itemName: toolbarTrans.menuItems.toolsName,
        itemIcon: 'tools',
        itemTitle: toolbarTrans.menuItems.toolsTitle,
        page: 0,
        tool: true,
        content: [],
        state: ''
    },
    {
        name: 'widgets',
        itemName: toolbarTrans.menuItems.widgetsName,
        itemTitle: toolbarTrans.menuItems.widgetsTitle,
        itemIcon: 'th-large',
        page: 0,
        content: [],
        paging: true,
        state: '',
        oneClickAdd: ['playlist']
    }
];

/**
 * Bottom toolbar contructor
 * @param {object} container - the container to render the navigator to
 * @param {object} [customActions] - customized actions
 * @param {boolean=} [showOptions] - show options menu
 */
let Toolbar = function(container, customActions = {}, showOptions = false) {

    this.DOMObject = container;
    this.openedMenu = -1;

    // Number of tabs that are fixed ( not removable and always defaulted )
    this.fixedTabs = 3;

    this.menuItems = defaultMenuItems;

    this.libraryTabs = [];

    this.contentDimentions = {
        width: 90 // In percentage
    };

    this.selectedCard = {};

    // Custom actions
    this.customActions = customActions;

    // Flag to mark if the toolbar has been rendered at least one time
    this.firstRun = true;

    // Options menu
    this.showOptions = showOptions;

    // Refresh opened menu and clear selections on window resize
    const self = this;
    $(window).resize(_.debounce(function(e) {
        if(e.target === window) {
            // Deselect previous selections
            self.deselectCardsAndDropZones();

            // If there was a opened menu in the toolbar, open that tab
            if(self.openedMenu != -1) {
                self.openMenu(self.openedMenu, true);
            }
        }
    }, 250));
};

/**
 * Load user preferences
 */
Toolbar.prototype.loadPrefs = function() {
    // Load using the API
    const linkToAPI = urlsForApi.user.getPref;

    const app = getXiboApp();

    // Request elements based on filters
    let self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=toolbar',
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {

            let loadedData = JSON.parse(res.data.value);

            // Populate the toolbar with the returned data
            self.libraryTabs = (loadedData.libraryTabs != undefined) ? loadedData.libraryTabs : [];
            self.openedMenu = (loadedData.openedMenu != undefined) ? loadedData.openedMenu : -1;

            // Tooltip options
            app.common.displayTooltips = (loadedData.displayTooltips == 1);

            // If there was a opened menu, load content for that one
            if(self.openedMenu != -1) {
                self.openMenu(self.openedMenu, true);
            } else {
                // Render to reflect the loaded toolbar
                self.render();
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
    const app = getXiboApp();

    // Save only some of the tab menu data
    let libraryTabsToSave = [];
    let openedMenu = this.openedMenu;
    let displayTooltips = (app.common.displayTooltips) ? 1 : 0;

    if(clearPrefs) {
        libraryTabsToSave = {};
        openedMenu = -1;
        displayTooltips = 1;
    } else {
        for(let index = 0;index < this.libraryTabs.length;index++) {

            // Make a copy of the current element
            let elementCopy = Object.assign({}, this.libraryTabs[index]);

            // Remove content and set page to 0
            elementCopy.content = [];
            elementCopy.page = 0;

            libraryTabsToSave.push(elementCopy);
        }
    }

    let dataToSave = {
        preference: [
            {
                option: 'toolbar',
                value: JSON.stringify({
                    libraryTabs: libraryTabsToSave,
                    openedMenu: openedMenu,
                    displayTooltips: displayTooltips
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
    const app = getXiboApp();

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

    // Compile layout template with data
    const html = ToolbarTemplate({
        opened: (this.openedMenu != -1),
        menuItems: this.menuItems,
        libraryTabs: this.libraryTabs,
        tabsCount: (this.libraryTabs.length > 0),
        displayTooltips: app.common.displayTooltips,
        trashActive: trashBinActive,
        undoActive: undoActive,
        trans: newToolbarTrans,
        showOptions: self.showOptions
    });

    // Append layout html to the main div
    this.DOMObject.html(html);
    // If read only mode is enabled
    if(app.readOnlyMode != undefined && app.readOnlyMode === true) {
        // Hide edit mode fields
        this.DOMObject.find('.hide-on-read-only').hide();
        
        // Create the read only alert message
        let $readOnlyMessage = $('<div id="read-only-message" class="alert alert-info btn text-center navbar-nav" data-toggle="tooltip" data-placement="bottom" title="' + layoutDesignerTrans.readOnlyModeMessage + '" role="alert"><strong>' + layoutDesignerTrans.readOnlyModeTitle + '</strong>&nbsp;' + layoutDesignerTrans.readOnlyModeMessage + '</div>');

        // Prepend the element to the bottom toolbar's content
        $readOnlyMessage.prependTo(this.DOMObject.find('.container-toolbar .navbar-collapse')).click(lD.showCheckoutScreen);
    } else {

        // Handle menus
        for(let i = 0;i < this.menuItems.length;i++) {

            const toolbar = self;
            const index = i;

            this.DOMObject.find('#btn-menu-' + index).click(function() {
                toolbar.openMenu(index);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-left-' + index).click(function() {
                toolbar.menuItems[index].page -= 1;
                toolbar.loadContent(index);
            });

            this.DOMObject.find('#content-' + index + ' #pag-btn-right-' + index).click(function() {
                toolbar.menuItems[index].page += 1;
                toolbar.loadContent(index);
            });

        }

        // Handle tabs
        for(let i = 0;i < this.libraryTabs.length;i++) {
            console.log('TODO: tab system');
        }

        // Create new tab
        this.DOMObject.find('#btn-menu-new-tab').click(function() {
            self.createNewTab();
        });

        // Close all tabs
        this.DOMObject.find('#deleteAllTabs').click(function() {
            self.deleteAllTabs();
        });

        // Delete object
        this.DOMObject.find('#trashContainer.active').click(
            this.customActions.deleteSelectedObjectAction
        );

        // Delete object
        this.DOMObject.find('#undoContainer.active').click(
            app.undoLastAction
        );
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

        // Initialize tooltips
        app.common.reloadTooltips(this.DOMObject);

        // Load media content
        if(this.openedMenu >= this.fixedTabs) {
            // Load tab search media content
            this.mediaContentCreate(this.openedMenu);
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

            app.refreshDesigner();
        });

        // Reset tour
        if(typeof app.resetTour === 'function') {
            self.DOMObject.find('#resetTour').removeClass('hidden').off().click(function() {
                app.resetTour();
            });
        }
    }
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 */
Toolbar.prototype.loadContent = function(menu = -1) {
    const app = getXiboApp();

    // Make menu state to be active
    this.menuItems[menu].state = 'active';

    if(this.menuItems[menu].name === 'tools') {
        this.menuItems[menu].content = toolsList;
    } else if(this.menuItems[menu].name === 'library') {
        console.log('Open library window');
    } else if(this.menuItems[menu].name === 'widgets') {

        console.log('Widgets Render!!!');

        // Calculate pagination
        //const pagination = this.calculatePagination(menu);

        // Enable/Disable page down pagination button according to the page to display
        //this.menuItems[menu].pagBtnLeftDisabled = (pagination.start == 0) ? 'disabled' : '';

        this.menuItems[menu].content = modulesList;


        for(let index = 0;index < this.menuItems[menu].content.length;index++) {
            const element = this.menuItems[menu].content[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            // Hide element if it's outside the "to display" region or is a hideOn this app
            //element.hideElement = (index < pagination.start || index >= (pagination.start + pagination.length)) || (element.hideOn != undefined && element.hideOn.indexOf(app.mainObjectType) != -1);
        }

        // Enable/Disable page up pagination button according to the page to display and total elements
        //this.menuItems[menu].pagBtnRightDisabled = ((pagination.start + pagination.length) >= this.menuItems[menu].content.length) ? 'disabled' : '';
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

    // Hide designer overlay
    $('.custom-overlay').hide().unbind();

    // Deselect card
    this.selectedCard = {};
};

//TODO: vvvv Library window!!! vvvv

/**
 * Open tab
 * @param {number} menu - menu to open index, -1 by default and to toggle
 * @param {bool} forceOpen - force tab open ( even if opened before )
 */
Toolbar.prototype.openTab = function(menu = -1, forceOpen = false) {
    // Deselect previous selections
    this.deselectCardsAndDropZones();

    // Open specific menu
    if(menu != -1) {
        let active = (forceOpen) ? false : (this.menuItems[menu].state == 'active');

        // Close all menus
        for(let index = this.menuItems.length - 1;index >= 0;index--) {
            this.menuItems[index].state = '';
        }

        if(active) {
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
 * Create new tab
 */
Toolbar.prototype.createNewTab = function() {

    let moduleListFiltered = [];
    let usersListFiltered = [];

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

    this.menuItems.push({
        name: 'search',
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
            owner: {
                name: toolbarTrans.searchFilters.owner,
                values: usersListFiltered
        },
        },
        content: []
    });

    this.openMenu(this.menuItems.length - 1);
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
    // Create a new tab
    this.createNewTab();

    // Switch the selected tab's Type select box to the type we want
    this.menuItems[this.openedMenu].filters.type.value = type;
    this.DOMObject.find('#media-search-form-' + this.openedMenu + ' #input-type-' + this.openedMenu).val(type);

    // Search/Load Content
    this.loadContent(this.openedMenu);
};

/**
 * Media form callback
 */
Toolbar.prototype.mediaContentCreate = function(menu) {

    const self = this;
    const app = getXiboApp();

    // Get search window Jquery object
    const $searchContent = self.DOMObject.find('#content-' + menu + '.search-content');

    // Render template
    const html = ToolbarMediaSearchTemplate({
        menuIndex: menu,
        menuObj: this.menuItems[menu],
        trans: toolbarTrans
    });

    // Append template to the search main div
    self.DOMObject.find('#media-search-container-' + menu).html(html);

    // Set paging max to 5
    $.fn.DataTable.ext.pager.numbers_length = 5;

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
                $.extend(d, self.DOMObject.find('#media-search-container-' + menu).find("form").serializeObject());
            }
        },
        "columns": [
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
            },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if(type !== "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"glyphicon glyphicon-plus-sign\"></a>";
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
            self.selectMedia($(this).closest('tr'), data);
        });

        self.tablePositionUpdate($searchContent);
    });
    mediaTable.on('processing.dt', dataTableProcessing);

    // Refresh the table results
    var filterRefresh = _.debounce(function() {
        // Save filter options
        self.menuItems[menu].filters.name.value = self.DOMObject.find('#media-search-form-' + menu + ' #input-name-' + menu).val();
        self.menuItems[menu].filters.tag.value = self.DOMObject.find('#media-search-form-' + menu + ' #input-tag-' + menu).val();
        self.menuItems[menu].filters.type.value = self.DOMObject.find('#media-search-form-' + menu + ' #input-type-' + menu).val();
        self.menuItems[menu].filters.owner.value = self.DOMObject.find('#media-search-form-' + menu + ' #input-owner-' + menu).val();

        self.savePrefs();

        // Deselect previous selections
        self.deselectCardsAndDropZones();

        // Reload table
        mediaTable.ajax.reload();
    }, 500);

    // Prevent filter form submit and bind the change event to reload the table
    self.DOMObject.find('#media-search-form-' + menu).on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // Bind seach action to refresh the results
    self.DOMObject.find('#media-search-form-' + menu + ' select, input[type="text"]').change(filterRefresh);
    self.DOMObject.find('#media-search-form-' + menu + ' input[type="text"]').on('input', filterRefresh);

    // Make search window to be draggable and resizable
    $searchContent
        .appendTo('.editor-toolbar > nav')
        .draggable({
            containment: 'window',
            scroll: false,
            handle: '.drag-handle'
        })
        .resizable({
            minWidth: 640
        }).on('resizestart',
            function() {
                self.tablePositionUpdate($searchContent);
            }
        ).on('resizestop dragstop',
            function(event, ui) {

                // Save window positioning
                self.menuItems[menu].searchPosition = {
                    width: $(this).width(),
                    height: $(this).height(),
                    top: $(this).position().top,
                    left: $(this).position().left
                };
                self.savePrefs();

                self.tablePositionUpdate($searchContent);
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

        $searchContent.width(position.width);
        $searchContent.height(position.height);
        $searchContent.css({top: position.top});
        $searchContent.css({left: position.left});
    }

    // Search content window buttons handling
    $searchContent.find('.btn-window-close').click(function() {
        self.deleteTab($(this).data('menu'));
    });

    $searchContent.find('.btn-window-minimize').click(function() {
        self.openMenu($(this).data('menu'));
    });

    // Initialize tagsinput
    self.DOMObject.find('#media-search-form-' + menu + ' input[data-role="tagsinput"]').tagsinput();

    self.DOMObject.find('#media-table-' + menu).off('click').on('click', '#tagDiv .btn-tag', function() {

        // See if its the first element, if not add comma
        var tagText = $(this).text();

        // Add text to form
        self.DOMObject.find('#media-search-form-' + menu + ' input[data-role="tagsinput"]').tagsinput('add', tagText, {allowDuplicates: false});
    });

    // Initialize tooltips
    app.common.reloadTooltips(self.DOMObject);

    // Deselect previous selections
    self.deselectCardsAndDropZones();
};

/**
 * Update tab height
 */
Toolbar.prototype.tablePositionUpdate = function(container) {
    
    // Calculate table container height
    const tableContainerHeight = container.find('.form-inline').height() + container.find('.dataTables_wrapper').height();

    // Set resizable min height
    container.resizable('option', 'minHeight', tableContainerHeight);

    // Fix height if bigger than the min
    if(container.height() < tableContainerHeight) {
        container.height(tableContainerHeight);
    }
};

/**
 * Update tab name
 */
Toolbar.prototype.updateTabNames = function() {

    for(let menu = this.fixedTabs;menu < this.menuItems.length;menu++) {

        const customFilter = this.menuItems[menu].filters;

        // Change tab name to reflect the search query
        if(customFilter.name.value != '' && customFilter.name.value != undefined) {
            this.menuItems[menu].itemName = '"' + customFilter.name.value + '"';
        } else {
            this.menuItems[menu].itemName = toolbarTrans.tabName.replace('%tagId%', menu);
        }

        if(customFilter.tag.value != '' && customFilter.tag.value != undefined) {
            this.menuItems[menu].itemName += ' {' + customFilter.tag.value + '} ';
        }

        if(customFilter.type.value != '' && customFilter.type.value != undefined) {
            this.menuItems[menu].itemName += ' [' + customFilter.type.value + '] ';
        }

        // Change 
        this.DOMObject.find('span.tab-name-' + menu).html(this.menuItems[menu].itemName);
    }
};

module.exports = Toolbar;