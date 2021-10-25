// NAVIGATOR Module

// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarCardMediaTemplate = require('../templates/toolbar-card-media.hbs');
const ToolbarContentTemplate = require('../templates/toolbar-content.hbs');
const ToolbarContentMedia = require('../templates/toolbar-content-media.hbs');
const MediaPlayerTemplate = require('../templates/toolbar-media-preview.hbs');
const MediaInfoTemplate = require('../templates/toolbar-media-preview-info.hbs');

const moduleListFiltered = [];
const usersListFiltered = [];

// Filter module list to create the types for the filter
modulesList.forEach((element) => {
    if (element.assignable == 1 && element.regionSpecific == 0 && ['image', 'audio', 'video'].indexOf(element.type) == -1) {
        moduleListFiltered.push({
            type: element.type,
            name: element.name,
        });
    }
});

usersList.forEach((element) => {
    usersListFiltered.push({
        userId: element.userId.toString(),
        name: element.userName,
    });
});

const defaultFilters = {
    name: {
        value: '',
    },
    tag: {
        value: '',
    },
    type: {
        value: '',
    },
    owner: {
        value: '',
    },
    orientation: {
        value: '',
    },
    provider: {
        value: 'both'
    }
};

const defaultMenuItems = [
    {
        name: 'widgets',
        itemName: toolbarTrans.menuItems.widgetsName,
        itemTitle: toolbarTrans.menuItems.widgetsTitle,
        itemIcon: 'th-large',
        content: [],
        filters: {
            name: {
                value: '',
            },
        },
        state: '',
        itemCount: 0,
        favouriteModules: [],
    },
    {
        name: 'images',
        itemName: toolbarTrans.menuItems.imagesName,
        itemIcon: 'images',
        itemTitle: toolbarTrans.menuItems.imagesTitle,
        search: true,
        filters: {
            name: {
                value: '',
            },
            tag: {
                value: '',
            },
            type: {
                value: 'image',
                locked: true,
            },
            owner: {
                value: '',
                values: usersListFiltered,
            },
            orientation: {
                value: '',
            },
            provider: {
                value: 'both',
            },
        },
        state: '',
        itemCount: 0,
    },
    {
        name: 'audio',
        itemName: toolbarTrans.menuItems.audioName,
        itemIcon: 'volume-up',
        itemTitle: toolbarTrans.menuItems.audioTitle,
        search: true,
        filters: {
            name: {
                value: '',
            },
            tag: {
                value: '',
            },
            type: {
                value: 'audio',
                locked: true,
            },
            owner: {
                value: '',
                values: usersListFiltered,
            },
            provider: {
                value: 'both',
            },
        },
        state: '',
        itemCount: 0,
    },
    {
        name: 'video',
        itemName: toolbarTrans.menuItems.videoName,
        itemIcon: 'video',
        itemTitle: toolbarTrans.menuItems.videoTitle,
        search: true,
        filters: {
            name: {
                value: '',
            },
            tag: {
                value: '',
            },
            type: {
                value: 'video',
                locked: true,
            },
            owner: {
                value: '',
                values: usersListFiltered,
            },
            orientation: {
                value: '',
            },
            provider: {
                value: 'both',
            },
        },
        state: '',
        itemCount: 0,
    },
    {
        name: 'library',
        itemName: toolbarTrans.menuItems.libraryName,
        itemIcon: 'archive',
        itemTitle: toolbarTrans.menuItems.libraryTitle,
        search: true,
        filters: {
            name: {
                value: '',
            },
            tag: {
                value: '',
            },
            type: {
                value: '',
                values: moduleListFiltered,
            },
            owner: {
                value: '',
                values: usersListFiltered,
            },
            provider: {
                value: 'both',
            },
        },
        state: '',
        itemCount: 0,
    },
];

/**
 * Bottom toolbar contructor
 * @param {object} parent - parent container
 * @param {object} container - the container to render the navigator to
 * @param {object} [customActions] - customized actions
 * @param {boolean=} [showOptions] - show options menu
 */
const Toolbar = function(parent, container, customActions = {}, showOptions = false) {
    this.parent = parent;

    this.DOMObject = container;
    this.openedMenu = -1;

    this.menuItems = defaultMenuItems;

    this.widgetMenuIndex = 0;
    this.libraryMenuIndex = 4;

    this.selectedCard = {};

    // Custom actions
    this.customActions = customActions;

    // Flag to mark if the toolbar has been rendered at least one time
    this.firstRun = true;

    // Flag to mark if the toolbar is opened
    this.opened = false;

    // Use queue to add media
    this.useQueue = true;

    // Media queue
    this.selectedQueue = [];

    // Options menu
    this.showOptions = showOptions;

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
    const self = this;
    $.ajax({
        url: linkToAPI.url + '?preference=toolbar',
        type: linkToAPI.type,
    }).done(function(res) {
        if (res.success) {
            const loadedData = JSON.parse(res.data.value);

            self.openedMenu = (loadedData.openedMenu != undefined) ? loadedData.openedMenu : -1;

            // Load favourites
            self.menuItems[self.widgetMenuIndex].favouriteModules = (loadedData.favouriteModules != undefined) ? loadedData.favouriteModules : [];

            // Load filters
            if (loadedData.filters) {
                loadedData.filters.forEach((menu, menuIdx) => {
                    for (let filter in menu) {
                        self.menuItems[menuIdx].filters[filter].value = menu[filter];
                    }
                });
            }

            // Tooltip options
            app.common.displayTooltips = (loadedData.displayTooltips == 1 || loadedData.displayTooltips == undefined);

            // Reload tooltips
            app.common.reloadTooltips(app.editorContainer);

            // Refresh designer to reflect the changes
            app.refreshDesigner(true);
        } else {
            // Login Form needed?
            if (res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                // Just an error we dont know about
                if (res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
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
    let openedMenu = this.openedMenu;
    let displayTooltips = (app.common.displayTooltips) ? 1 : 0;
    let favouriteModules = [];
    const filters = [];

    if (clearPrefs) {
        openedMenu = -1;
        displayTooltips = 1;
    } else {
        // Save favourite
        favouriteModules = this.menuItems[this.widgetMenuIndex].favouriteModules;

        // Save filters
        this.menuItems.forEach((menu, menuIdx) => {
            filters[menuIdx] = {};
            for (let filter in menu.filters) {
                if(defaultFilters[filter].value != menu.filters[filter].value && menu.filters[filter].locked != true) {
                    filters[menuIdx][filter] = menu.filters[filter].value;
                }
            }
        });
    }

    const dataToSave = {
        preference: [
            {
                option: 'toolbar',
                value: JSON.stringify({
                    filters: filters,
                    openedMenu: openedMenu,
                    displayTooltips: displayTooltips,
                    favouriteModules: favouriteModules,
                }),
            },
        ],
    };

    // Save using the API
    const linkToAPI = urlsForApi.user.savePref;

    // Request elements based on filters
    $.ajax({
        url: linkToAPI.url,
        type: linkToAPI.type,
        data: dataToSave,
    }).done(function(res) {
        if (!res.success) {
            // Login Form needed?
            if (res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                toastr.error(errorMessagesTrans.userSavePreferencesFailed);

                // Just an error we dont know about
                if (res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
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
    if (this.firstRun) {
        this.firstRun = false;

        // Load user preferences
        this.loadPrefs();
    }

    const self = this;
    const app = this.parent;

    // Deselect selected card on render
    this.selectedCard = {};

    // Get toolbar trans
    const newToolbarTrans = Object.assign({}, toolbarTrans);

    // Check if trash bin is active
    const trashBinActive = app.selectedObject.isDeletable && (app.readOnlyMode === undefined || app.readOnlyMode === false);

    // Get text for bin tooltip
    if (trashBinActive) {
        newToolbarTrans.trashBinActiveTitle = toolbarTrans.deleteObject.replace('%object%', app.selectedObject.type);
    }

    const checkHistory = app.checkHistory();

    if (checkHistory) {
        newToolbarTrans.undoActiveTitle = checkHistory.undoActiveTitle;
    }

    const toolbarOpened = (this.openedMenu != -1) && (app.readOnlyMode === undefined || app.readOnlyMode === false);

    // Compile toolbar template with data
    const html = ToolbarTemplate({
        opened: toolbarOpened,
        menuItems: this.menuItems,
        displayTooltips: app.common.displayTooltips,
        trashActive: trashBinActive,
        undoActive: checkHistory.undoActive,
        trans: newToolbarTrans,
        showOptions: self.showOptions,
        mainObjectType: app.mainObjectType,
    });

    // Append toolbar html to the main div
    this.DOMObject.html(html);

    // If read only mode is enabled
    if (app.readOnlyMode != undefined && app.readOnlyMode === true) {
        // Hide edit mode fields
        this.DOMObject.find('.hide-on-read-only').hide();

        // Hide toolbar
        this.DOMObject.hide();

        // Create the read only alert message
        const $readOnlyMessage = $('<div id="read-only-message" class="alert alert-info btn text-center navbar-nav" data-container=".editor-bottom-bar" data-toggle="tooltip" data-placement="bottom" data-title="' + layoutEditorTrans.readOnlyModeMessage + '" role="alert"><strong>' + layoutEditorTrans.readOnlyModeTitle + '</strong>&nbsp;' + layoutEditorTrans.readOnlyModeMessage + '</div>');

        // Prepend the element to the bottom toolbar's content
        $readOnlyMessage.prependTo(this.DOMObject.find('.container-toolbar .navbar-collapse')).click(lD.checkoutLayout);
    } else {
        // Show toolbar
        this.DOMObject.show();

        // Handle menus
        for (let i = 0; i < this.menuItems.length; i++) {
            const toolbar = self;
            const index = i;

            this.DOMObject.find('#btn-menu-' + index).click(function() {
                toolbar.openMenu(index);
            });
        }

        // Delete object
        this.DOMObject.find('.trash-container').click(function() {
            if ($(this).hasClass('active')) {
                app.deleteSelectedObject();
            }
        });

        // Revert last action
        this.DOMObject.find('.undo-container').click(function() {
            if ($(this).hasClass('active')) {
                app.undoLastAction();
            }
        });

        // Enable multi select mode
        this.DOMObject.find('#multiSelectContainer').click(function() {
            self.toggleMultiselectMode();
        });
    }

    // Options menu
    if (self.showOptions) {
        self.DOMObject.find('.navbar-submenu-options-container').off().click(function(e) {
            e.stopPropagation();
        });

        // Toggle tooltips
        self.DOMObject.find('#displayTooltips').off().click(function() {
            app.common.displayTooltips = self.DOMObject.find('#displayTooltips').prop('checked');

            if (app.common.displayTooltips) {
                toastr.success(editorsTrans.tooltipsEnabled);
            } else {
                toastr.error(editorsTrans.tooltipsDisabled);
            }

            self.savePrefs();

            app.common.reloadTooltips(app.editorContainer);
        });

        // Reset tour
        if (typeof app.resetTour === 'function') {
            self.DOMObject.find('#resetTour').removeClass('d-none').off().click(function() {
                app.resetTour();
            });
        }
    }

    // Save default tolbar nav z-index
    this.defaultZIndex = this.DOMObject.find('nav').css('z-index');

    // If there was a opened menu in the toolbar, open that tab
    if (this.openedMenu != undefined && this.openedMenu != -1) {
        this.openMenu(this.openedMenu, true);
    }
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 * @param {boolean} forceReload - force content to be reloaded even if exists
 */
Toolbar.prototype.loadContent = function(menu = -1, forceReload = false) {
    // Make menu state to be active
    this.menuItems[menu].state = 'active';

    if (this.menuItems[menu].name === 'widgets') {
        // Sort by favourites
        const favouriteModules = [];
        const otherModules = [];

        for (let index = 0; index < this.customModuleList.length; index++) {
            const element = this.customModuleList[index];

            element.maxSize = libraryUpload.maxSize;
            element.maxSizeMessage = libraryUpload.maxSizeMessage;

            // Filter elements
            if (this.menuItems[menu].filters.name.value && !element.name.toLowerCase().includes(this.menuItems[menu].filters.name.value.toLowerCase())) {
                continue;
            }

            if ($.inArray(element.type, this.menuItems[menu].favouriteModules) > -1) {
                element.favourited = true;
                favouriteModules.push(element);
            } else {
                element.favourited = false;
                otherModules.push(element);
            }
        }

        // Add elements to menu content
        this.menuItems[menu].content = {
            modulesFav: favouriteModules,
            modules: otherModules,
        };
    }

    this.DOMObject.find('#content-' + menu + ', #btn-menu-' + menu).addClass('active');

    // Create content
    this.createContent(menu, forceReload);

    // Save user preferences
    this.savePrefs();
};

/**
 * Create content
 * @param {number} menu - menu to load content for
 * @param {boolean} forceReload - force content to be reloaded even if exists
 */
Toolbar.prototype.createContent = function(menu = -1, forceReload = false) {
    const content = $.extend({}, this.menuItems[menu], {menuIndex: menu, trans: toolbarTrans, filters: this.menuItems[menu].filters});
    const self = this;

    // Create content only if it's not rendered yet ( if force reload is true, skip this step)
    if(!forceReload && menu > self.widgetMenuIndex && self.DOMObject.find('#content-' + menu + ' .toolbar-pane-container .toolbar-card').length > 0) {
        // Recalculate masonry layout to refresh the elements positions
        self.DOMObject.find('#media-content-' + menu).masonry('layout');
        
        return;
    }

    // Render template
    const html = ToolbarContentTemplate(content);

    // Append template to the search main div
    this.DOMObject.find('#content-' + menu).replaceWith(html);

    if (content.search) {
        this.mediaContentCreateWindow(menu);
    } else {
        this.handleCardsBehaviour();

        // Bind search action to refresh the results
        this.DOMObject.find('#module-search-form input[type="text"]').on('input', _.debounce(function(e) {
            self.menuItems[menu].filters.name.value = $(this).val();
            self.menuItems[menu].focus = e.target.selectionStart;
            self.loadContent(menu);
        }, 500));

        // Focus with cursor position
        const focusPosition = self.menuItems[menu].focus;
        if (focusPosition != undefined) {
            $('#module-search-form input[type="text"]').focus();
            $('#module-search-form input[type="text"]')[0].setSelectionRange(focusPosition, focusPosition);
        }
    }
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 * @param {bool} forceOpen - force tab open ( even if opened before )
 */
Toolbar.prototype.openMenu = function(menu = -1, forceOpen = false) {
    let active = false;
    const oldStatusOpened = this.opened;
    const app = this.parent;

    // Deselect previous selections
    this.deselectCardsAndDropZones();

    // Open specific menu
    if (menu > -1 && menu < this.menuItems.length) {
        active = (forceOpen) ? false : (this.menuItems[menu].state == 'active');

        // Close all menus
        for (let index = this.menuItems.length - 1; index >= 0; index--) {
            this.menuItems[index].state = '';
            this.DOMObject.find('#content-' + index + ', #btn-menu-' + index).removeClass('active');
        }

        if (active) {
            this.openedMenu = -1;
            this.opened = false;
        } else {
            this.openedMenu = menu;

            // If menu is the default/widget/tools, load content
            if (menu > -1) {
                this.loadContent(menu);
            }

            this.opened = true;
        }
    } else {
        active = true;
        this.openedMenu = -1;
        this.opened = false;
    }

    // If toolbar changes width, refresh containers
    if (this.opened != oldStatusOpened) {
        // Update navbar and editor modal
        this.DOMObject.find('nav.navbar').toggleClass('opened', this.opened);
        this.DOMObject.parents('.editor-modal').toggleClass('toolbar-opened', this.opened);
        
        if (app.mainObjectType != 'playlist') {
            // Refresh main containers
            if (app.navigatorMode) {
                app.renderContainer(app.navigator, app.selectedObject);
            } else {
                app.renderContainer(app.viewer, app.selectedObject);
            }
        }
    }

    // if menu was closed, save preferences and clean content
    if(!this.opened) {
        // Save user preferences
        this.savePrefs();
    }
};

/**
 * Select toolbar card so it can be used
 * @param {object} card - DOM card to select/activate
 */
Toolbar.prototype.selectCard = function(card) {
    // Deselect previous selections
    this.deselectCardsAndDropZones();

    const previouslySelected = this.selectedCard;

    if (previouslySelected[0] != card[0]) {
        // Get card info
        const dropTo = $(card).attr('drop-to');
        const subType = $(card).attr('data-sub-type');

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
        if (dropTo === 'all' && subType === 'permissions') {
            $('.ui-droppable.permissionsModifiable').addClass('ui-droppable-active');
        } else {
            // Prevent adding audio to subplaylist
            let selectorAppend = '';
            if (subType == 'audio') {
                selectorAppend = ':not([data-widget-type="subplaylist"])';
            }

            $('[data-type="' + dropTo + '"].ui-droppable.editable' + selectorAppend).addClass('ui-droppable-active');
        }
    }
};

/**
 * Deselect all the cards and remove the overlay on the drop zones
 */
Toolbar.prototype.deselectCardsAndDropZones = function() {
    // Deselect other cards
    this.DOMObject.find('.toolbar-card.card-selected').removeClass('card-selected');

    // Deselect other media
    this.DOMObject.find('.media-content .media-selected').removeClass('media-selected');

    // Remove content selected class
    this.DOMObject.find('.toolbar-pane-content.selected').removeClass('selected');

    // Remove media queue data
    this.DOMObject.find('.toolbar-pane-content').removeData('mediaQueue');

    // Remove drop class from droppable elements
    $('.ui-droppable').removeClass('ui-droppable-active');

    // Disable multi-select mode
    if (this.parent.editorContainer.hasClass('multi-select')) {
        this.toggleMultiselectMode(false);
    }

    // Hide designer overlay
    $('.custom-overlay').hide().unbind();

    // Deselect card
    this.selectedCard = {};

    // Empty queue
    this.selectedQueue = [];
};

/**
 * Media form callback
 */
Toolbar.prototype.mediaContentCreateWindow = function(menu) {
    const self = this;

    // Deselect previous selections
    self.deselectCardsAndDropZones();

    // Render template
    const html = ToolbarContentMedia({
        menuIndex: menu,
        filters: this.menuItems[menu].filters,
        trans: toolbarTrans,
    });

    // Append template to the search main div
    self.DOMObject.find('#media-container-' + menu).html(html);

    // Populate selected tab
    self.mediaContentPopulate(menu);
};

/**
 * Media content populate table
 * @param {number} menu - menu id
 */
Toolbar.prototype.mediaContentPopulate = function(menu) {
    const self = this;
    const filters = self.menuItems[menu].filters;
    const requestURL = librarySearchUrl + '?assignable=1&retired=0';
    const $mediaContainer = self.DOMObject.find('#media-container-' + menu);

    // Request elements based on filters
    const loadData = function(clear = true) {
        const $mediaContent = self.DOMObject.find('#media-content-' + menu);
        
        // Remove show more button
        $mediaContainer.find('.show-more').remove();

        // Empty content and reset item count
        if(clear) {
            // Clear selection
            self.deselectCardsAndDropZones();

            // Empty content
            $mediaContent.empty();
            self.menuItems[menu].itemCount = 0;
        }

        // Show loading
        $mediaContent.after('<div class="loading-container w-100 text-center"><span class="loading fa fa-cog fa-spin"></span></div>');

        // Get filter data
        const filter = self.DOMObject.find('#media-container-' + menu + ' #media-search-form').serializeObject();

        if(menu == self.libraryMenuIndex && filter.type == '') {
            filter.types = moduleListFiltered.map(el => el.type);
        }

        // Manage request length
        const requestLength = 15;

        // Filter start
        const start = self.menuItems[menu].itemCount;

        $.ajax({
            url: requestURL,
            type: 'GET',
            data: $.extend({
                start: start,
                length: requestLength,
                provider: 'both'
            }, filter),
        }).done(function(res) {
            // Remove loading
            $mediaContent.parent().find('.loading-container').remove();
            if((!res.data || res.data.length == 0) && $mediaContent.find('.toolbar-card').length == 0) {
                // Show no results message
                $mediaContent.append('<div class="no-results-message">' + toolbarTrans.noMediaToShow + '</div>');
            } else {
                // Init masonry
                $mediaContent.masonry({
                    itemSelector: '.toolbar-card',
                    columnWidth: 96,
                    gutter: 11,
                });

                for (let index = 0; index < res.data.length; index++) {
                    const element = Object.assign({}, res.data[index]);
                    element.trans = toolbarTrans;

                    // Create download link for images
                    if(element.type == 'image' && !element.download) {
                        element.download = imageDownloadUrl.replace(":id", element.id);
                    }

                    // Use template
                    const $card = $(ToolbarCardMediaTemplate(element));

                    // Add data object to card
                    if($card.hasClass('from-provider')) {
                        $card.data('providerData', res.data[index]);
                    }

                    
                    // Append to container
                    $mediaContent.append($card).masonry('appended', $card);;

                    self.menuItems[menu].itemCount++;
                }

                // Layout masonry after images are loaded
                $mediaContent.imagesLoaded(function() {
                    // Recalculate masonry layout
                    $mediaContent.masonry('layout');

                    // Show content in widgets
                    $mediaContent.find('.toolbar-card').removeClass('hide-content');

                    // Show more button
                    if(res.data.length > 0) {
                        const $showMoreBtn = $('<button class="btn btn-block btn-white show-more">' + toolbarTrans.showMore + '</button>');
                        $mediaContent.after($showMoreBtn);

                        $showMoreBtn.off('click').on('click', function() {
                            loadData(false);
                        });
                    } else {
                        toastr.info(toolbarTrans.noShowMore, null, {"positionClass": "toast-bottom-center"});
                    }

                    // Fix for scrollbar
                    const $parent = $mediaContent.parent();
                    $parent.toggleClass('scroll', ($parent.width() < $parent[0].scrollHeight));

                    // Handle card behaviour
                    self.handleCardsBehaviour();
                });
            }
        }).catch(function(jqXHR, textStatus, errorThrown) {
            // Login Form needed?
            if (res.login) {
                window.location.href = window.location.href;
                location.reload();
            } else {
                // Just an error we dont know about
                if (res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }
            }
        });
    };

    // Refresh the table results
    const filterRefresh = function(filters) {
        // Save filter options
        for (let filter in filters) {
            filters[filter].value = self.DOMObject.find('#content-' + menu + ' #media-search-form #input-' + filter).val();
        }

        // Reload data
        loadData();

        self.savePrefs();
    };

    // Prevent filter form submit and bind the change event to reload the table
    $mediaContainer.find('#media-search-form').on('submit', function(e) {
        e.preventDefault();
        return false;
    });

    // Bind seach action to refresh the results
    $mediaContainer.find('#media-search-form select, #media-search-form input[type="text"].input-tag').change(_.debounce(function() {
        filterRefresh(filters);
    }, 200));

    $mediaContainer.find('#media-search-form input[type="text"]').on('input', _.debounce(function() {
        filterRefresh(filters);
    }, 500));

    // Initialize tagsinput
    const $tags = $mediaContainer.find('#media-search-form input[data-role="tagsinput"]');
    $tags.tagsinput();

    $mediaContainer.find('#media-' + menu).off('click').on('click', '#tagDiv .btn-tag', function() {
        // Add text to form
        $tags.tagsinput('add', $(this).text(), {allowDuplicates: false});
    });

    // Load data
    loadData();
};

/**
 * Mark/Unmark as favourite
 */
Toolbar.prototype.toggleFavourite = function(target) {
    const favouriteModulesArray = this.menuItems[this.widgetMenuIndex].favouriteModules;
    let markAsFav = false;

    const $card = $(target).parent('.toolbar-card[data-type="module"]');
    const cardType = $card.data().subType;
    const positionInArray = $.inArray(cardType, favouriteModulesArray);

    // Add/remove from the fav array
    if (positionInArray > -1) {
        // Remove from favourites
        favouriteModulesArray.splice(positionInArray, 1);
    } else {
        // Add to favourites
        markAsFav = true;
        favouriteModulesArray.push(cardType);
    }

    // Show notification
    toastr.success((markAsFav) ? toolbarTrans.addedToFavourites : toolbarTrans.removedFromFavourites, '', {positionClass: 'toast-bottom-right'});

    // Reload toolbar widget content with reload
    this.loadContent(0, true);
};

Toolbar.prototype.updateQueue = function(menu, mediaQueue) {
    const $mediaPane = this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');

    // Show drop overlay if queue has elements
    if(mediaQueue.length > 0) {
        this.queueToggleOverlays(menu);
    } else {
        this.queueToggleOverlays(menu, false);
    }

    $mediaPane.data('mediaQueue', mediaQueue)

    // Save backup
    this.selectedQueue = mediaQueue;
};

Toolbar.prototype.addToQueue = function(menu, target) {
    const $mediaPane = this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');
    let mediaQueue = $mediaPane.data('mediaQueue') ?? [];

    // Add to queue
    const toAdd = (target.data('providerData')) ||  target.data('mediaId');
    mediaQueue.push(toAdd);
    target.addClass('card-selected');
    
    // Update queue positions
    this.updateQueue(menu, mediaQueue);
};

Toolbar.prototype.removeFromQueue = function(menu, target) {
    const $mediaPane = this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');
    let mediaQueue = $mediaPane.data('mediaQueue');

    // Remove element
    mediaQueue.splice(mediaQueue.indexOf(target.data('mediaId')), 1);
    target.removeClass('card-selected');

    // Update queue position
    this.updateQueue(menu, mediaQueue);
};

Toolbar.prototype.queueToggleOverlays = function(menu, enable = true) {
    const self = this;
    const $mediaQueue = this.DOMObject.find('#content-' + menu + ' .media-add-queue');

    // Mark queue as add enabled/disabled
    $mediaQueue.data('toAdd', enable);

    if(enable) {
        // Show designer overlay
        $('.custom-overlay').show().unbind().click(() => {
            self.deselectCardsAndDropZones();
        });

        // Set droppable areas as active
        $('[data-type="region"].ui-droppable.editable').addClass('ui-droppable-active');
    } else {
        self.deselectCardsAndDropZones();
    }
};

/**
 * Toggle multiple element select mode
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
    const multiSelectFlag = (forceSelect != null) ? forceSelect : !editorContainer.hasClass('multi-select');

    // Toggle multi select class on container
    editorContainer.toggleClass('multi-select', multiSelectFlag);

    // Toggle class on button
    this.DOMObject.find('#multiSelectContainer').toggleClass('multiselect-active', multiSelectFlag);

    if (multiSelectFlag) {
        // Show overlay
        $('.custom-overlay').show().unbind().click(() => {
            self.deselectCardsAndDropZones();
        });

        // Disable timeline sort
        timeline.DOMObject.find('#timeline-container').sortable('disable');

        // Enable select for each widget
        timeline.DOMObject.find('.playlist-widget.deletable')
            .removeClass('selected').unbind().click(function(e) {
                e.stopPropagation();
                $(this).toggleClass('multi-selected');

                updateTrashContainer();
            }
            );

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

/**
 * Handle toolbar cards behaviour
 */
Toolbar.prototype.handleCardsBehaviour = function() {
    const app = this.parent;
    const self = this;

    // If in edit mode
    if (app.readOnlyMode === undefined || app.readOnlyMode === false) {
        this.DOMObject.find('.toolbar-card').each(function() {
            $(this).draggable({
                cursor: 'crosshair',
                appendTo: $(this).parents('.toolbar-pane:first'),
                handle: (self.openedMenu == self.widgetMenuIndex) ? '.drag-area' : false,
                cursorAt: {
                    top: ($(this).height() + ($(this).outerWidth(true) - $(this).outerWidth()) / 2) / 2,
                    left: ($(this).width() + ($(this).outerWidth(true) - $(this).outerWidth()) / 2) / 2,
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

                    // Reload tooltips to avoid floating detached elements
                    app.common.reloadTooltips(app.editorContainer);
                },
                stop: function() {
                    // Hide overlay
                    $('.custom-overlay').hide();

                    // Remove card class as being dragged
                    $(this).removeClass('card-dragged');

                    // Mark content as unselected
                    $(this).parent('.toolbar-pane-content').removeClass('selected');

                    // Reload tooltips to avoid floating detached elements
                    app.common.reloadTooltips(app.editorContainer);
                },
            });
        });

        // Select card clicking in the Add button
        this.DOMObject.find('.toolbar-card:not(.card-selected) .add-area').click((e) => {
            self.selectCard($(e.currentTarget).parent());
        });

        // Select card clicking in the Add button
        this.DOMObject.find('.toolbar-card:not(.card-selected) .btn-favourite').click((e) => {
            self.toggleFavourite(e.currentTarget);
        });

        // Card select button
        this.DOMObject.find('#media-content-' + this.openedMenu + ' .select-button').off('click').click(function() {
            const $card = $(this).parent();

            if($card.hasClass('card-selected')) {
                self.removeFromQueue(self.openedMenu, $card);
            } else {
                self.addToQueue(self.openedMenu, $card);
            }
        });

        // Media preview button
        this.DOMObject.find('#media-content-' + this.openedMenu + ' .preview-button').off('click').click(function() {
            self.createMediaPreview($(this).parent());
        });

        // Play video on hover
        this.DOMObject.find('#media-content-' + this.openedMenu + ' .toolbar-card[data-sub-type="video"]').off('mouseenter mouseleave').hover(
            function() { // mouseenter
                const vid = $(this).find('video')[0];
                if(vid && vid.readyState > 1 && vid.paused) {
                    vid.play();

                    // Stop playing after X seconds
                    _.debounce(function() {
                        if(vid && vid.readyState > 1 && !vid.paused && vid.currentTime > 0) {
                            vid.currentTime = 0;
                            vid.pause();
                        }
                    }, 5000)();
                }
            },
            function() { // mouseleave
                const vid = $(this).find('video')[0];
                if(vid && vid.readyState > 1 && !vid.paused && vid.currentTime > 0) {
                    vid.currentTime = 0;
                    vid.pause();
                }
            })
    }
};

/**
 * Create media preview
 * @param  {object} media
 */
Toolbar.prototype.createMediaPreview = function(media) {
    const self = this;
    const app = this.parent;
    
    if (self.DOMObject.find('.media-preview').length == 0) {
        self.DOMObject.append(MediaPlayerTemplate({
            trans: toolbarTrans,
        }));
    }

    const $mediaPreview = self.DOMObject.find('.media-preview');
    const $mediaPreviewContent = $mediaPreview.find('#content');
    const $mediaPreviewInfo = $mediaPreview.find('#info');

    // Create base template for preview content
    const mediaTemplates = {
        video: Handlebars.compile('<video src="{{url}}" controls></video>'),
        image: Handlebars.compile('<img src="{{url}}">'),
    }

    // Clean all selected elements
    $mediaPreviewContent.html('').removeData('mediaId');
    $mediaPreviewInfo.html('');

    const mediaData = media.data();

    // Format file size
    if(mediaData.providerData?.fileSize) {
        mediaData.providerData.fileSizeFormatted = app.common.formatFileSize(mediaData.providerData.fileSize);
    }

    // Load and start preview
    $mediaPreviewContent.append(mediaTemplates[mediaData.subType]({
        url: (mediaData.providerData) ? mediaData.providerData.download : mediaData.download
    })).data('mediaId', mediaData['mediaId']);

    $mediaPreviewInfo.append(MediaInfoTemplate({
        data: (mediaData.providerData) ? mediaData.providerData : mediaData,
        trans: toolbarTrans,
    }));

    $mediaPreview.find('#closeBtn').off().on('click', function() {
        // Close preview and empty content
        $mediaPreview.find('#content').html('');
        $mediaPreview.removeClass('show');
        $mediaPreview.remove();
    });

    $mediaPreview.find('#sizeBtn').off().on('click', function() {
        // Toggle size class
        $mediaPreview.toggleClass('large');

        // Change icon based on size state
        $(this).toggleClass('fa-arrow-circle-down', $mediaPreview.hasClass('large'));
        $(this).toggleClass('fa-arrow-circle-up', !$mediaPreview.hasClass('large'));
    });

    $mediaPreview.find('#selectBtn').off().on('click', function() {
        // Select Media on toolbar
        const $card = self.DOMObject.find('.toolbar-menu-content #content-' + self.openedMenu + ' .toolbar-card[data-media-id="' + $mediaPreviewContent.data('mediaId') + '"]');

        if(!$card.hasClass('card-selected')) {
            self.addToQueue(self.openedMenu, $card);
        }
    });

    // Show layout preview element
    $mediaPreview.addClass('show');
};


module.exports = Toolbar;
