/* eslint-disable new-cap */
// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarCardMediaTemplate = require('../templates/toolbar-card-media.hbs');
const ToolbarCardMediaUploadTemplate =
  require('../templates/toolbar-card-media-upload.hbs');
const ToolbarContentTemplate = require('../templates/toolbar-content.hbs');
const ToolbarSearchFormTemplate =
  require('../templates/toolbar-search-form.hbs');
const ToolbarContentMediaTemplate =
  require('../templates/toolbar-content-media.hbs');
const ToolbarContentSubmenuTemplate =
  require('../templates/toolbar-content-submenu.hbs');
const ToolbarContentSubmenuCardsTemplate =
  require('../templates/toolbar-content-submenu-elements.hbs');
const ToolbarContentGroupTemplate =
  require('../templates/toolbar-content-group.hbs');
const MediaPlayerTemplate = require('../templates/toolbar-media-preview.hbs');
const MediaInfoTemplate =
  require('../templates/toolbar-media-preview-info.hbs');

/**
 * Bottom toolbar contructor
 * @param {object} parent - parent container
 * @param {object} container - the container to render the navigator to
 * @param {object} [customActions] - customized actions
 * @param {boolean=} [isPlaylist] - is it a playlist toolbar?
 */
const Toolbar = function(
  parent,
  container,
  customActions = {},
  isPlaylist = false,
) {
  this.parent = parent;
  this.DOMObject = container;
  this.openedMenu = -1;
  this.openedSubMenu = -1;

  this.selectedCard = {};

  // Flag to mark if the toolbar has been rendered at least one time
  this.firstRun = true;

  // Flag to mark if the toolbar is opened
  this.opened = false;

  // Use queue to add media
  this.useQueue = true;

  // Media queue
  this.selectedQueue = [];

  // Custom actions
  this.customActions = customActions;

  // Is the toolbar a playlist toolbar?
  this.isPlaylist = isPlaylist;

  // Initialize toolbar
  this.init({
    isPlaylist: isPlaylist,
  });
};

/**
 * Initialize toolbar
 * @param {object} [options] - options
 * @param {boolean=} [options.isPlaylist] - is it a playlist toolbar?
 */
Toolbar.prototype.init = function({isPlaylist = false} = {}) {
  // Modules to be used in Widgets
  let moduleListFiltered = [];

  // Create user list
  const usersListFiltered = [];

  // Modules to be used in other option
  const moduleListOtherFiltered = [];

  // Filter module list to create the types for the filter
  modulesList.forEach((el) => {
    // Show/hide modules based on showIn property
    if (
      el.showIn == 'playlist' && !isPlaylist ||
      el.showIn == 'layout' && isPlaylist ||
      el.showIn == 'none'
    ) {
      return;
    }

    // Check if we have valid extension on settings
    for (let index = 0; index < el.settings.length; index++) {
      const setting = el.settings[index];

      if (setting.id == 'validExtensions') {
        el.validExtensions =
            (setting.value) ? setting.value : setting.default;
      }
    }

    // Create new list with "other" modules
    if (
      el.assignable == 1 &&
    el.regionSpecific == 0 &&
    ['image', 'audio', 'video'].indexOf(el.type) == -1
    ) {
      moduleListOtherFiltered.push({
        type: el.type,
        name: el.name,
      });
    }

    // If we have thumbnail, add proper path
    if (el.thumbnail && !el.thumbnailLoaded) {
      el.thumbnail =
        assetDownloadUrl.replace(':assetId', el.thumbnail);
      el.thumbnailLoaded = true;
    }

    // Add card type ( to use group cards )
    el.cardType = 'module';

    // Filter out image/audio/video
    if (['image', 'audio', 'video'].indexOf(el.type) == -1) {
      moduleListFiltered.push(el);
    }
  });

  // Add playlist to modules
  if (!isPlaylist) {
    moduleListFiltered.push({
      moduleId: 'playlist',
      name: toolbarTrans.playlist,
      type: 'playlist',
      dataType: '',
      regionSpecific: 1,
      group: [],
    });
  }

  // Group modules by group property
  const moduleGroups = {};
  for (let i = 0; i < moduleListFiltered.length; i++) {
    const element = moduleListFiltered[i];

    // Check if element.group is an object
    if (typeof element.group == 'object' && !(element.group instanceof Array)) {
      if (!moduleGroups[element.group.id]) {
        moduleGroups[element.group.id] = {
          name: element.group.name,
          type: element.group.id,
          icon: element.group.icon,
          cardType: 'moduleGroup',
          modules: [],
        };
      }

      // Add module to group
      moduleGroups[element.group.id].modules.push(element);

      // Remove module from moduleListFiltered and decrement i
      moduleListFiltered.splice(moduleListFiltered.indexOf(element), 1);
      i--;
    }
  }

  // Add module groups to module list
  moduleListFiltered = moduleListFiltered.concat(
    Object.values(moduleGroups),
  );

  // Sort modules by name
  moduleListFiltered.sort(function(a, b) {
    return (a.name < b.name) ? -1 : 1;
  });

  usersList.forEach((element) => {
    usersListFiltered.push({
      userId: element.userId.toString(),
      name: element.userName,
    });
  });

  // Actions
  this.interactiveList = [
    {
      name: toolbarTrans.interactive.actions.navWidget,
      icon: 'fa fa-arrows-alt',
      type: 'navWidget',
      target: '["frame"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.navLayout,
      icon: 'fa fa-arrows-alt',
      type: 'navLayout',
      target: '["layout"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.nextWidget,
      icon: 'fa-arrow-right-alt',
      type: 'nextWidget',
      target: '["playlist"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.previousWidget,
      icon: 'fa-arrow-left-alt',
      type: 'previousWidget',
      target: '["playlist"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.nextLayout,
      icon: 'fa-arrow-right',
      type: 'nextLayout',
      target: '["layout"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.previousLayout,
      icon: 'fa-arrow-left',
      type: 'previousLayout',
      target: '["layout"]',
      dataType: '',
    },
  ];

  this.defaultFilters = {
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
      value: 'both',
    },
  };

  const defaultMenuItems = [
    {
      name: 'widgets',
      iconType: 'module',
      itemName: toolbarTrans.menuItems.widgetsName,
      itemTitle: toolbarTrans.menuItems.widgetsTitle,
      itemIcon: 'th-large',
      content: [],
      contentType: 'modules',
      filters: {
        name: {
          value: '',
          title: toolbarTrans.searchFilters.search,
        },
      },
      state: '',
      itemCount: 0,
      favouriteModules: [],
    },
    {
      name: 'global',
      disabled: isPlaylist ? true : false,
      itemName: toolbarTrans.menuItems.globalElementsName,
      itemIcon: 'font',
      itemTitle: toolbarTrans.menuItems.globalElementsTitle,
      contentType: 'elements',
      filters: {
        name: {
          value: '',
          title: toolbarTrans.searchFilters.search,
        },
      },
      state: '',
      itemCount: 0,
    },
    {
      name: 'image',
      itemName: toolbarTrans.menuItems.imageName,
      itemIcon: 'image',
      itemTitle: toolbarTrans.menuItems.imageTitle,
      contentType: 'media',
      filters: {
        name: {
          value: '',
          key: 'media',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
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
      contentType: 'media',
      filters: {
        name: {
          value: '',
          key: 'media',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
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
      contentType: 'media',
      filters: {
        name: {
          value: '',
          key: 'media',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
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
      contentType: 'media',
      filters: {
        name: {
          value: '',
          key: 'media',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
        },
        type: {
          value: '',
          values: moduleListOtherFiltered,
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
      name: 'actions',
      disabled: isPlaylist ? true : false,
      iconType: 'actions',
      itemName: toolbarTrans.menuItems.actionsName,
      itemIcon: 'paper-plane',
      itemTitle: toolbarTrans.menuItems.actionsTitle,
      contentType: 'actions',
      content: [],
      filters: {
        name: {
          value: '',
          title: toolbarTrans.searchFilters.search,
        },
      },
      state: '',
      itemCount: 0,
    },
  ];

  // Menu items
  this.menuItems = defaultMenuItems;

  // Check if menu items based on modules are disabled
  const getModuleByTypeFunc = this.parent.common.getModuleByType;
  this.menuItems.forEach(function(el) {
    if (el.contentType == 'media') { // validate only media menu options
      if (el.name == 'library') { // library needs its filters to be validated
        el.disabled = true;
        el.filters.type.values.forEach(function(el2) {
          el2.disabled = $.isEmptyObject(getModuleByTypeFunc(el2.type));
          if (el.disabled && !el2.disabled) {
            el.disabled = false;
          }
        });
      } else {
        // other basic media (audio, video,...)
        // only need to check the upper level
        el.disabled = $.isEmptyObject(getModuleByTypeFunc(el.name));
      }
    }
  });

  // Filtered module list
  this.customModuleList = moduleListFiltered;
  this.moduleListOtherFiltered = moduleListOtherFiltered;
  this.moduleGroups = moduleGroups;
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

      const findMenuIndexByName = function(name) {
        let foundMenu = -1;

        for (let i = 0; i < self.menuItems.length; i++) {
          if (self.menuItems[i].name == name) {
            foundMenu = i;
            break;
          }
        }

        return foundMenu;
      };

      // Load opened menu
      if (loadedData.openedMenu != undefined) {
        self.openedMenu = findMenuIndexByName(loadedData.openedMenu);
      } else {
        self.openedMenu = -1;
      }

      // Load opened submenu
      self.openedSubMenu =
        (loadedData.openedSubMenu != undefined) ?
          loadedData.openedSubMenu : -1;

      // If we have opened submenu, replace parent with menu index
      if (self.openedSubMenu != null) {
        self.openedSubMenu.parent =
          findMenuIndexByName(self.openedSubMenu.parent);
      }

      // Load favourites
      const widgetMenuIndex = findMenuIndexByName('widgets');
      self.menuItems[widgetMenuIndex].favouriteModules =
        (loadedData.favouriteModules != undefined) ?
          loadedData.favouriteModules :
          [];

      // Load filters
      if (loadedData.filters) {
        for (const filter in loadedData.filters) {
          if (loadedData.filters.hasOwnProperty(filter)) {
            const menuIdx = findMenuIndexByName(filter);
            for (const filterValue in loadedData.filters[filter]) {
              if (loadedData.filters[filter].hasOwnProperty(filterValue)) {
                self.menuItems[menuIdx].filters[filterValue].value =
                  loadedData.filters[filter][filterValue];
              }
            }
          }
        }
      }

      // Tooltip options
      app.common.displayTooltips =
        (loadedData.displayTooltips == 1 ||
          loadedData.displayTooltips == undefined);

      // Reload tooltips
      app.common.reloadTooltips(app.editorContainer);

      // Render toolbar and topbar if exists
      self.render();
      if (app.topbar) {
        app.topbar.render();
      }
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
  const self = this;

  // Get opened menu name to save
  let openedMenu =
    (this.openedMenu != -1) ?
      this.menuItems[this.openedMenu].name : -1;

  // Make a copy of the opened submenu object
  let openedSubMenu =
  (this.openedSubMenu != -1) ?
    Object.assign({}, this.openedSubMenu) : -1;
  let displayTooltips = (app.common.displayTooltips) ? 1 : 0;
  let favouriteModules = [];
  const filters = {};

  // If we have opened submenu, save parent with name instead of index
  if (
    openedSubMenu != -1 &&
    openedSubMenu.parent != undefined &&
    openedSubMenu.parent != -1
  ) {
    openedSubMenu.parent = this.menuItems[openedSubMenu.parent].name;
  }

  if (clearPrefs) {
    openedMenu = -1;
    openedSubMenu = -1;
    displayTooltips = 1;
  } else {
    // Save favourite modules
    const widgetMenu = self.menuItems.find(function(el) {
      return (el.name == 'widgets');
    });
    favouriteModules = widgetMenu.favouriteModules;

    // Save filters
    this.menuItems.forEach((menu) => {
      filters[menu.name] = {};
      for (const filter in menu.filters) {
        if (
          this.defaultFilters[filter].value != menu.filters[filter].value &&
          menu.filters[filter].locked != true
        ) {
          filters[menu.name][filter] = menu.filters[filter].value;
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
          openedSubMenu: openedSubMenu,
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

  const toolbarOpened =
    (this.openedMenu != -1) &&
    (app.readOnlyMode === undefined ||
      app.readOnlyMode === false);

  // Compile toolbar template with data
  const html = ToolbarTemplate({
    opened: toolbarOpened,
    menuItems: this.menuItems,
    trans: newToolbarTrans,
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
    const $readOnlyMessage =
      $(`<div id="read-only-message"
        class="alert alert-info btn text-center navbar-nav"
        data-container=".editor-bottom-bar" data-toggle="tooltip"
        data-placement="bottom" data-title="` +
        layoutEditorTrans.readOnlyModeMessage +
        `" role="alert"><strong>` + layoutEditorTrans.readOnlyModeTitle +
        `</strong>&nbsp;` + layoutEditorTrans.readOnlyModeMessage +
        `</div>`);

    // Prepend the element to the bottom toolbar's content
    $readOnlyMessage.prependTo(
      this.DOMObject.find('.container-toolbar .navbar-collapse'),
    ).click(lD.layout.checkout);
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
  }

  // Save default tolbar nav z-index
  this.defaultZIndex = this.DOMObject.find('nav').css('z-index');

  // If there was a opened menu in the toolbar, open that tab
  if (this.openedMenu != undefined && this.openedMenu != -1) {
    // If menu is disabled, mark toolbar as closed
    // and do not open the menu
    if (this.menuItems[this.openedMenu].disabled === true) {
      this.openedMenu = -1;
      this.openedSubMenu = -1;

      // Close toolbar
      this.DOMObject.find('nav').removeClass('opened');

      return;
    } else {
      // Do we have opened sub menu?
      const openedSubMenu =
        (this.openedSubMenu && this.openedSubMenu != -1) &&
        this.openedSubMenu.parent == this.openedMenu;

      this.openMenu(this.openedMenu, true, openedSubMenu);
    }
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

  if (this.menuItems[menu].contentType === 'modules') {
    // Sort by favourites
    const favouriteModules = [];
    const otherModules = [];

    for (let index = 0; index < this.customModuleList.length; index++) {
      const card = this.customModuleList[index];

      card.maxSize = libraryUpload.maxSize;
      card.maxSizeMessage = libraryUpload.maxSizeMessage;

      // Filter elements
      if (
        this.menuItems[menu].filters.name.value &&
        !card.name.toLowerCase().includes(
          this.menuItems[menu].filters.name.value.toLowerCase(),
        )
      ) {
        continue;
      }

      if ($.inArray(card.type, this.menuItems[menu].favouriteModules) > -1) {
        card.favourited = true;
        favouriteModules.push(card);
      } else {
        card.favourited = false;
        otherModules.push(card);
      }
    }

    // Add elements to menu content
    this.menuItems[menu].content = {
      favourites: favouriteModules,
      cards: otherModules,
      contentHeader: toolbarTrans.widgets,
      noCardsToShow: toolbarTrans.noWidgetsToShow,
    };
  } else if (this.menuItems[menu].name === 'actions') {
    const actionsFiltered = [];

    for (let index = 0; index < this.interactiveList.length; index++) {
      const element = this.interactiveList[index];

      // Filter elements
      if (
        this.menuItems[menu].filters.name.value &&
        !element.name.toLowerCase().includes(
          this.menuItems[menu].filters.name.value.toLowerCase(),
        )
      ) {
        continue;
      }

      actionsFiltered.push(element);
    }

    // Add card to menu content
    this.menuItems[menu].content = {
      cards: actionsFiltered,
      contentHeader: toolbarTrans.actions,
      noCardsToShow: toolbarTrans.noActionsToShow,
    };
  }


  this.DOMObject.find('#content-' + menu + ', #btn-menu-' + menu)
    .addClass('active');

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
  const content = $.extend(
    {},
    this.menuItems[menu],
    {
      menuIndex: menu,
      trans: toolbarTrans,
      filters: this.menuItems[menu].filters,
    });
  const self = this;
  const app = this.parent;

  // Create content only if it's not rendered yet
  // ( if force reload is true, skip this step)
  if (
    !forceReload &&
    this.menuItems[menu].contentType != 'modules' &&
    this.menuItems[menu].contentType != 'actions' &&
    self.DOMObject
      .find(
        '#content-' +
        menu +
        ' .toolbar-pane-container .toolbar-card',
      ).length > 0
  ) {
    // Recalculate masonry layout to refresh the card positions
    self.DOMObject.find('#media-content-' + menu).masonry('layout');

    // Adapt card behaviour to current tab
    self.handleCardsBehaviour();

    return;
  }

  // Render template
  const html = ToolbarContentTemplate(content);

  // Append template to the search main div
  this.DOMObject.find('#content-' + menu).replaceWith(html);

  if (content.contentType == 'media') {
    this.mediaContentCreateWindow(menu);
  } else if (content.contentType == 'elements') {
    this.elementsContentCreateWindow(menu);
  } else {
    this.handleCardsBehaviour();

    // Bind search action to refresh the results
    this.DOMObject.find('.module-search-form input[type="text"]')
      .on('input', _.debounce(function(e) {
        // eslint-disable-next-line no-invalid-this
        self.menuItems[menu].filters.name.value = $(this).val();
        self.menuItems[menu].focus = e.target.selectionStart;
        app.common.clearTooltips();
        self.loadContent(menu);
      }, 500));

    // Focus with cursor position
    const focusPosition = self.menuItems[menu].focus;
    if (focusPosition != undefined) {
      $('.module-search-form input[type="text"]').focus();
      $('.module-search-form input[type="text"]')[0]
        .setSelectionRange(focusPosition, focusPosition);
    }
  }

  // Handle content close button
  this.DOMObject.find('.close-content').on('click', function() {
    self.openMenu(self.openedMenu);
  });
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 * @param {bool} forceOpen - force tab open ( even if opened before )
 * @param {bool} openSubMenu - open sub menu
 */
Toolbar.prototype.openMenu = function(
  menu = -1,
  forceOpen = false,
  openSubMenu = false,
) {
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
      this.DOMObject.find('#content-' + index + ', #btn-menu-' + index)
        .removeClass('active');
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
    this.DOMObject.parents('.editor-modal')
      .toggleClass('toolbar-opened', this.opened);

    if (app.mainObjectType != 'playlist') {
      // Refresh main containers
      app.viewer.update();
    }
  }

  // if menu was closed, save preferences and clean content
  if (!this.opened) {
    // Save user preferences
    this.savePrefs();
  }

  // If we have a sub menu, open it
  if (openSubMenu) {
    if (this.openedSubMenu.type == 'groupMenu') {
      this.openGroupMenu(null, this.openedSubMenu.data, menu);
    } else if (this.openedSubMenu.type == 'subMenu') {
      this.openSubMenu(null, this.openedSubMenu.data, menu);
    }
  } else {
    this.openedSubMenu = null;
  }

  // Clear rogue tooltips
  app.common.clearTooltips();
};

/**
 * Select toolbar card so it can be used
 * @param {object} card - DOM card to select/activate
 */
Toolbar.prototype.selectCard = function(card) {
  // Deselect previous selections
  this.deselectCardsAndDropZones();

  const previouslySelected = this.selectedCard.mainObjectType;

  if (!previouslySelected || previouslySelected[0] != card[0]) {
    // Select new card
    $(card).addClass('card-selected');
    $(card).parents('.toolbar-pane-content').addClass('selected');
    $(card).parents('nav.navbar').addClass('card-selected');

    // Save selected card data
    this.selectedCard = card;

    // Show designer overlay
    $('.custom-overlay').show().off().on('click', () => {
      this.deselectCardsAndDropZones();
    });

    // Handle droppables
    // For actions, add an extra class
    this.handleDroppables(
      $(card),
      $(card).data('type') == 'actions' ?
        'ui-droppable-active ui-droppable-actions-target' : '',
    );
  }
};

/**
 * Handle droppables
 * @param {object} draggable - draggable object
 * @param {string} customClasses - custom classes to add to droppable
 */
Toolbar.prototype.handleDroppables = function(draggable, customClasses = '') {
  // Get draggable info
  const draggableType = $(draggable).data('subType');
  const app = this.parent;

  // Set droppable areas as active
  if (
    app.common.hasTarget(draggable, 'all') &&
    draggableType === 'permissions'
  ) {
    $('.droppable.permissionsModifiable').addClass('ui-droppable-active');
  } else {
    let selectorAppend = '';
    let selectorBuild = [];

    // Prevent adding audio to subplaylist
    if (draggableType == 'audio') {
      selectorAppend += ':not([data-widget-type="subplaylist"])';
    }

    // Layout editor droppables
    if (app.common.hasTarget(draggable, 'layout')) {
      // Drop to layout wrapper and layout
      selectorBuild.push('.layout-wrapper.droppable');
      selectorBuild.push('.layout.droppable');
    }

    if (app.common.hasTarget(draggable, 'widget')) {
      // Drop to widget
      selectorBuild.push('.designer-widget.droppable');
    }

    if (app.common.hasTarget(draggable, 'frame')) {
      // Drop to frame region
      selectorBuild.push('.designer-region[data-sub-type="frame"].droppable');
    }

    if (app.common.hasTarget(draggable, 'frame')) {
      // Drop to frame region
      selectorBuild.push('.designer-region[data-sub-type="frame"].droppable');
    }

    if (app.common.hasTarget(draggable, 'zone')) {
      // Drop to frame region
      selectorBuild.push('.designer-region[data-sub-type="zone"].droppable');
    }

    // Playlist editor droppables
    // and layout editor droppables
    if (app.common.hasTarget(draggable, 'playlist')) {
      // Drop to playlist
      selectorBuild.push('.designer-region-playlist.droppable');

      // Drop to zone region
      selectorBuild.push('.designer-region-zone.droppable');

      // Drop to playlist timeline
      selectorBuild.push('#playlist-timeline.ui-droppable');
    }

    // Add droppable class to all selectors
    selectorBuild = selectorBuild.map((selector) => {
      return selector + selectorAppend;
    });

    // Add droppable ( or custom ) class to all selectors
    $(selectorBuild.join(', ')).addClass(
      (customClasses != '') ? customClasses : 'ui-droppable-active');
  }

  // Show layout background overlay if exists
  app.propertiesPanel.DOMObject.find('.background-image-add').toggleClass(
    'ui-droppable-active',
    (draggableType == 'image' && !draggable.hasClass('upload-card')));
};

/**
 * Deselect all the cards and remove the overlay on the drop zones
 */
Toolbar.prototype.deselectCardsAndDropZones = function() {
  const app = this.parent;
  // Deselect other cards
  this.DOMObject.find('.toolbar-card.card-selected')
    .removeClass('card-selected');

  // Deselect other media
  this.DOMObject.find('.media-content .media-selected')
    .removeClass('media-selected');

  // Remove content selected class
  this.DOMObject.find('.toolbar-pane-content.selected')
    .removeClass('selected');
  this.DOMObject.find('nav.navbar').removeClass('card-selected');

  // Remove media queue data
  this.DOMObject.find('.toolbar-pane-content').removeData('mediaQueue');

  // Remove drop class from droppable elements
  $('.ui-droppable, .droppable').removeClass('ui-droppable-active');

  // Disable multi-select mode
  if (app.editorContainer.hasClass('multi-select')) {
    app.toggleMultiselectMode(false);
  }

  // Hide designer overlay
  $('.custom-overlay').hide().off();

  // Deselect card
  this.selectedCard = {};

  // Empty queue
  this.selectedQueue = [];
};

/**
 * Create media content
 * @param {number} menu - menu index
 */
Toolbar.prototype.mediaContentCreateWindow = function(menu) {
  const self = this;

  // Deselect previous selections
  self.deselectCardsAndDropZones();

  // Render template
  const html = ToolbarContentMediaTemplate({
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
  const app = this.parent;
  const filters = self.menuItems[menu].filters;
  const $mediaContainer = self.DOMObject.find('#media-container-' + menu);

  // Request elements based on filters
  const loadData = function(clear = true) {
    const $mediaContent = self.DOMObject.find('#media-content-' + menu);
    const $mediaForm = $mediaContent.parent().find('.media-search-form');

    // Remove show more button
    $mediaContainer.find('.show-more').remove();

    // Empty content and reset item count
    if (clear) {
      // Clear selection
      self.deselectCardsAndDropZones();

      // Empty content
      $mediaContent.empty();
      self.menuItems[menu].itemCount = 0;
    }

    // Show loading
    $mediaContent.before(
      `<div class="loading-container-toolbar w-100 text-center">
        <span class="loading fa fa-cog fa-spin"></span>
      </div>`);

    // Remove no media message if exists
    $mediaForm.find('.no-results-message').remove();

    // Get filter data
    const filter =
      self.DOMObject.find(
        '#media-container-' +
        menu +
        ' .media-search-form',
      ).serializeObject();

    if (
      self.menuItems[menu].name == 'library' &&
      filter.type == ''
    ) {
      filter.types = self.moduleListOtherFiltered.map((el) => el.type);
    }

    // Manage request length
    const requestLength = 15;

    // Filter start
    const start = self.menuItems[menu].itemCount;

    $.ajax({
      url: librarySearchUrl,
      type: 'GET',
      data: $.extend({
        start: start,
        length: requestLength,
        provider: 'both',
      }, filter),
    }).done(function(res) {
      // Add upload card
      const showUploadCard = function() {
        if ($mediaContent.find('.upload-card').length == 0) {
          // Find specific module
          const module = app.common.getModuleByType(filter.type);
          if (module) {
            module.trans = toolbarTrans;

            const $uploadCard = $(ToolbarCardMediaUploadTemplate(module));
            $mediaContent.append($uploadCard).masonry('appended', $uploadCard);
          }
        }
      };

      // Remove loading
      $mediaContent.parent().find('.loading-container-toolbar').remove();

      // If there's no masonry sizer, add it
      if ($mediaContent.find('.toolbar-card-sizer').length == 0) {
        $mediaContent.append(
          '<div class="toolbar-card-sizer"></div>',
        );
      }

      // Init masonry
      $mediaContent.masonry({
        itemSelector: '.toolbar-card',
        columnWidth: '.toolbar-card-sizer',
        percentPosition: true,
        gutter: 8,
      });

      if (
        (!res.data || res.data.length == 0) &&
        $mediaContent.find('.toolbar-card').length == 0
      ) {
        showUploadCard();

        // Handle card behaviour
        self.handleCardsBehaviour();

        // Show no results message
        $mediaForm.append(
          '<div class="no-results-message">' +
          toolbarTrans.noMediaToShow +
          '</div>');
      } else {
        showUploadCard();

        for (let index = 0; index < res.data.length; index++) {
          const element = Object.assign({}, res.data[index]);
          element.trans = toolbarTrans;

          // Create download link for images
          if (element.type == 'image' && !element.download) {
            element.download = imageDownloadUrl.replace(':id', element.id);
          }

          // Format duration
          if (['audio', 'video'].includes(element.type)) {
            element.mediaDuration = app.common.timeFormat(element.duration);
          }

          // Get video thumbnail for videos with provider
          // Local videos will have an image thumbnail
          if (element.type == 'video' && element.provider) {
            element.videoThumbnail = element.thumbnail;
          }

          // Use template
          const $card = $(ToolbarCardMediaTemplate(element));

          // Add data object to card
          if ($card.hasClass('from-provider')) {
            $card.data('providerData', res.data[index]);
          }

          // Append to container
          $mediaContent.append($card).masonry('appended', $card);

          self.menuItems[menu].itemCount++;
        }

        // Layout masonry after images are loaded
        $mediaContent.imagesLoaded(function() {
          // Recalculate masonry layout
          $mediaContent.masonry('layout');

          // Show content in widgets
          $mediaContent.find('.toolbar-card').removeClass('hide-content');

          // Show more button
          if (res.data.length > 0) {
            const $showMoreBtn =
              $('<button class="btn btn-block btn-white show-more">' +
                toolbarTrans.showMore +
                '</button>');
            $mediaContent.after($showMoreBtn);

            $showMoreBtn.off('click').on('click', function() {
              loadData(false);
            });
          } else {
            toastr.info(
              toolbarTrans.noShowMore,
              null,
              {positionClass: 'toast-bottom-center'},
            );
          }

          // Fix for scrollbar
          const $parent = $mediaContent.parent();
          $parent.toggleClass(
            'scroll',
            ($parent.width() < $parent[0].scrollHeight),
          );

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
    for (const filter in filters) {
      if (filters.hasOwnProperty(filter)) {
        filters[filter].value =
          self.DOMObject.find(
            '#content-' +
            menu +
            ' .media-search-form #input-' + filter,
          ).val();
      }
    }

    // Reload data
    loadData();

    self.savePrefs();
  };

  // Prevent filter form submit and bind the change event to reload the table
  $mediaContainer.find('.media-search-form').on('submit', function(e) {
    e.preventDefault();
    return false;
  });

  // Bind search action to refresh the results
  $mediaContainer.find(
    '.media-search-form select, ' +
    '.media-search-form input[type="text"].input-tag',
  ).change(_.debounce(function() {
    filterRefresh(filters);
  }, 200));

  // Bind tags change to refresh the results
  $mediaContainer.find('.media-search-form input[type="text"]')
    .on('input', _.debounce(function() {
      filterRefresh(filters);
    }, 500));

  // Initialize tagsinput
  const $tags = $mediaContainer
    .find('.media-search-form input[data-role="tagsinput"]');
  $tags.tagsinput();

  $mediaContainer.find('#media-' + menu).off('click')
    .on('click', '#tagDiv .btn-tag', function(e) {
      // Add text to form
      $tags.tagsinput('add', $(e.target).text(), {allowDuplicates: false});
    });

  // Load data
  loadData();
};

/**
 * Create elements content
 * @param {number} menu - menu index
 */
Toolbar.prototype.elementsContentCreateWindow = function(menu) {
  const self = this;
  const $elementsContainer =
    self.DOMObject.find('#elements-container-' + menu);

  // Deselect previous selections
  self.deselectCardsAndDropZones();

  // Add search form before the elements container
  const $searchForm = $(ToolbarSearchFormTemplate({
    trans: toolbarTrans,
    filters: this.menuItems[menu].filters,
  }));
  $elementsContainer.before($searchForm);

  // Load content
  self.loadTemplates(
    $elementsContainer.parent(),
    'global',
  );
};


/**
 * Mark/Unmark as favourite
 * @param {object} target - The target element
 */
Toolbar.prototype.toggleFavourite = function(target) {
  const self = this;
  const widgetMenu = self.menuItems.find(function(el) {
    return (el.name == 'widgets');
  });
  const favouriteModulesArray = widgetMenu.favouriteModules;

  const $card = $(target).parent('.toolbar-card');
  const cardType = $card.data().subType;
  const positionInArray = $.inArray(cardType, favouriteModulesArray);

  // Add/remove from the fav array
  if (positionInArray > -1) {
    // Remove from favourites
    favouriteModulesArray.splice(positionInArray, 1);
  } else {
    // Add to favourites
    favouriteModulesArray.push(cardType);
  }

  // Reload toolbar widget content with reload
  this.loadContent(0, true);
};

Toolbar.prototype.updateQueue = function(menu, mediaQueue) {
  const $mediaPane =
    this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');

  // Show drop overlay if queue has elements
  if (mediaQueue.length > 0) {
    this.queueToggleOverlays(menu);
  } else {
    this.queueToggleOverlays(menu, false);
  }

  $mediaPane.data('mediaQueue', mediaQueue);

  // Save backup
  this.selectedQueue = mediaQueue;
};

Toolbar.prototype.addToQueue = function(menu, target) {
  const $mediaPane =
    this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');
  const mediaQueue = $mediaPane.data('mediaQueue') ?? [];

  // Add to queue
  const toAdd = (target.data('providerData')) || target.data('mediaId');
  mediaQueue.push(toAdd);
  target.addClass('card-selected');

  // Update queue positions
  this.updateQueue(menu, mediaQueue);
};

Toolbar.prototype.removeFromQueue = function(menu, target) {
  const $mediaPane =
    this.DOMObject.find('#content-' + menu + ' .toolbar-pane-content');
  const mediaQueue = $mediaPane.data('mediaQueue');

  // Remove element
  mediaQueue.splice(mediaQueue.indexOf(target.data('mediaId')), 1);
  target.removeClass('card-selected');

  // Update queue position
  this.updateQueue(menu, mediaQueue);
};

Toolbar.prototype.queueToggleOverlays = function(menu, enable = true) {
  const self = this;
  const $mediaQueue =
    this.DOMObject.find('#content-' + menu + ' .media-add-queue');

  // Mark queue as add enabled/disabled
  $mediaQueue.data('toAdd', enable);

  if (enable) {
    // Show designer overlay
    $('.custom-overlay').show().unbind().click(() => {
      self.deselectCardsAndDropZones();
    });

    // Handle droppables
    this.handleDroppables('all', 'media');
  } else {
    self.deselectCardsAndDropZones();
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
    this.DOMObject.find('.toolbar-card:not(.toolbar-card-menu)')
      .each(function(idx, el) {
        $(el).draggable({
          appendTo: $(el).parents('.toolbar-pane:first'),
          cursorAt: {
            top:
            (
              $(el).height() + ($(el).outerWidth(true) - $(el).outerWidth()) / 2
            ) / 2,
            left:
            (
              $(el).width() + ($(el).outerWidth(true) - $(el).outerWidth()) / 2
            ) / 2,
          },
          opacity: 0.7,
          helper: 'clone',
          start: function() {
          // Deselect previous selections
            self.deselectCardsAndDropZones();

            // Show overlay
            $('.custom-overlay').show();

            // Mark card as being dragged
            $(this).addClass('card-dragged');

            // Mark content as selected
            $(this).parents('.toolbar-pane-content').addClass('selected');
            $(this).parents('nav.navbar').addClass('card-selected');

            // Reload tooltips to avoid floating detached elements
            app.common.reloadTooltips(app.editorContainer);
          },
          stop: function() {
          // Hide overlay
            $('.custom-overlay').hide();

            // Remove card class as being dragged
            $(this).removeClass('card-dragged');

            // Mark content as unselected
            $(this).parents('.toolbar-pane-content').removeClass('selected');
            $(this).parents('nav.navbar').removeClass('card-selected');

            // Reload tooltips to avoid floating detached elements
            app.common.reloadTooltips(app.editorContainer);
          },
        });
      });

    // Select normal card
    this.DOMObject.find(
      '.toolbar-card:not(.toolbar-card-menu):not(.card-selected)',
    ).click((e) => {
      self.selectCard($(e.currentTarget));
    });

    // Select upload card
    this.DOMObject.find('.toolbar-card.upload-card').off('click').click((e) => {
      const $card = $(this);
      if (!$card.hasClass('card-selected')) {
        self.selectCard($(e.currentTarget));
      } else {
        self.deselectCardsAndDropZones();
      }
    });

    // Open card template menu
    this.DOMObject.find(
      '.toolbar-card.toolbar-card-menu:not(.toolbar-card-group)',
    ).off('click').click((e) => {
      self.openSubMenu($(e.currentTarget));
    });

    // Open card group menu
    this.DOMObject.find(
      '.toolbar-card.toolbar-card-group',
    ).off('click').click((e) => {
      self.openGroupMenu($(e.currentTarget));
    });

    // Toggle favourite card
    this.DOMObject.find(
      '.toolbar-card:not(.card-selected) '+
      '.btn-favourite',
    ).click((e) => {
      self.toggleFavourite(e.currentTarget);
    });

    // Card select button
    this.DOMObject.find(
      '#media-content-' +
      this.openedMenu +
      ' .select-button:not(.select-upload)').off('click')
      .click(function(e) {
        // Stop propagation
        e.stopPropagation();

        // If upload card is selected, remove all selected
        if (
          !$.isEmptyObject(self.selectedCard) &&
          self.selectedCard.hasClass('card-selected')
        ) {
          {
            self.deselectCardsAndDropZones();
          }
        }

        const $card = $(e.target).parents('.toolbar-card');
        if ($card.hasClass('card-selected')) {
          self.removeFromQueue(self.openedMenu, $card);
        } else {
          self.addToQueue(self.openedMenu, $card);
        }
      });

    // Media preview button
    this.DOMObject.find(
      '#media-content-' +
      this.openedMenu +
      ' .preview-button',
    ).off('click').click(function(e) {
      // Stop propagation
      e.stopPropagation();

      self.createMediaPreview($(e.currentTarget).parents('.toolbar-card'));
    });

    // Play video on hover
    this.DOMObject.find(
      '#media-content-' +
      this.openedMenu +
      ' .toolbar-card[data-sub-type="video"]',
    ).off('mouseenter mouseleave').hover(
      function(e) { // mouseenter
        const vid = $(e.currentTarget).find('video')[0];
        if (vid && vid.readyState > 1 && vid.paused) {
          vid.play();

          // Stop playing after X seconds
          _.debounce(function() {
            if (
              vid && vid.readyState > 1 &&
              !vid.paused &&
              vid.currentTime > 0
            ) {
              vid.currentTime = 0;
              vid.pause();
            }
          }, 5000)();
        }
      },
      function(e) { // mouseleave
        const vid = $(e.currentTarget).find('video')[0];
        if (vid && vid.readyState > 1 && !vid.paused && vid.currentTime > 0) {
          vid.currentTime = 0;
          vid.pause();
        }
      });
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
  };

  // Clean all selected elements
  $mediaPreviewContent.html('').removeData('mediaId');
  $mediaPreviewInfo.html('');

  const mediaData = media.data();

  // Format file size
  if (mediaData.providerData?.fileSize) {
    mediaData.providerData.fileSizeFormatted =
      app.common.formatFileSize(mediaData.providerData.fileSize);
  }

  // Load and start preview
  $mediaPreviewContent.append(mediaTemplates[mediaData.subType]({
    url: (mediaData.providerData) ?
      mediaData.providerData.download :
      mediaData.download,
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

  $mediaPreview.find('#sizeBtn').off().on('click', function(e) {
    // Toggle size class
    $mediaPreview.toggleClass('large');

    // Change icon based on size state
    $(e.target).toggleClass(
      'fa-arrow-circle-down',
      $mediaPreview.hasClass('large'),
    );
    $(e.target).toggleClass(
      'fa-arrow-circle-up',
      !$mediaPreview.hasClass('large'),
    );
  });

  $mediaPreview.find('#selectBtn').off().on('click', function() {
    // Select Media on toolbar
    const $card = self.DOMObject.find(
      '.toolbar-menu-content #content-' +
      self.openedMenu +
      ' .toolbar-card[data-media-id="' +
      $mediaPreviewContent.data('mediaId') +
      '"]');

    if (!$card.hasClass('card-selected')) {
      self.selectCard($card);
    }
  });

  // Show layout preview element
  $mediaPreview.addClass('show');
};

/**
 * Opens a new tab and search for the given media type
 * @param {string} type - Type of media
 */
Toolbar.prototype.openNewTabAndSearch = function(type) {
  if (['audio', 'video', 'image'].includes(type)) {
    this.openedMenu = ['image', 'audio', 'video'].indexOf(type) + 1;
  } else {
    // Open library tab
    this.openedMenu = 4;
    this.menuItems[this.openedMenu].filters.type.value = type;
  }

  // Re-render toolbar
  this.render();
};

/**
 * Open sub menu
 * @param  {string} $card - Module card
 * @param  {object} data  - Module data
 * @param {number} parentMenu - Parent menu
 */
Toolbar.prototype.openSubMenu = function(
  $card, data = null,
  parentMenu = null,
) {
  const self = this;
  const openedMenu = self.openedMenu;
  const $submenuContainer = self.DOMObject.find('#content-' + openedMenu);
  const cardData = data ? data : $card.data();
  const parent = parentMenu !== null ?
    parentMenu :
    $card.parents('.toolbar-pane').data('menu-index');

  // Save card data
  self.openedSubMenu = {
    parent: parent,
    type: 'subMenu',
    data: cardData,
  };

  // Append HTML
  $submenuContainer.addClass('toolbar-cards-pane');
  $submenuContainer.html(
    ToolbarContentSubmenuTemplate({
      data: cardData,
      trans: toolbarTrans,
      filters: {
        name: {
          value: '',
          title: toolbarTrans.searchFilters.search,
        },
      },
    }),
  );

  // Handle back button
  $submenuContainer.find('.close-submenu').off().on('click', function() {
    $submenuContainer.removeClass('toolbar-cards-pane');

    // Clear submenu
    self.openedSubMenu = -1;

    // Open menu
    self.openMenu(openedMenu, true);

    // Save user preferences
    self.savePrefs();
  });

  // Clear tooltips
  this.parent.common.clearTooltips();

  // Load content
  self.loadTemplates($submenuContainer, cardData.dataType, cardData.subType);

  // Save user preferences
  self.savePrefs();
};

/**
 * Load module group submenu
 * @param {string} $card - Module card
 * @param {object} data  - Module data
 * @param {number} parentMenu - Parent menu
 */
Toolbar.prototype.openGroupMenu = function(
  $card,
  data = null,
  parentMenu = null,
) {
  const self = this;
  const openedMenu = self.openedMenu;
  const $submenuContainer = self.DOMObject.find('#content-' + openedMenu);
  const cardData = data ? data : $card.data();
  const parent = parentMenu !== null ?
    parentMenu :
    $card.parents('.toolbar-pane').data('menu-index');

  // Deselect previous selections
  this.deselectCardsAndDropZones();

  // Save card data
  self.openedSubMenu = {
    parent: parent,
    type: 'groupMenu',
    data: cardData,
  };

  // Load module cards from this group
  const content = this.moduleGroups[cardData.subType].modules;

  // Append HTML
  $submenuContainer.addClass('toolbar-group-pane');
  $submenuContainer.html(
    ToolbarContentGroupTemplate({
      data: cardData,
      content: content,
      trans: toolbarTrans,
    }),
  );

  // Handle back button
  $submenuContainer.find('.close-submenu').off().on('click', function() {
    $submenuContainer.removeClass('toolbar-group-pane');

    // Clear submenu
    self.openedSubMenu = -1;

    // Open menu
    self.openMenu(openedMenu, true);

    // Save user preferences
    self.savePrefs();
  });

  // Handle cards behaviour
  self.handleCardsBehaviour();

  // Clear tooltips
  this.parent.common.clearTooltips();

  // Save user preferences
  self.savePrefs();
};

/**
 * Load content for templates
 * @param {object} $container - Templates container
 * @param  {object} contentType - Content type
 * @param  {object} moduleType - Module type
 */
Toolbar.prototype.loadTemplates = function(
  $container,
  contentType,
  moduleType,
) {
  const self = this;
  const app = this.parent;

  // Show loading
  $container.find('.toolbar-pane-container').before(
    `<div class="loading-container-toolbar w-100 text-center">
      <span class="loading fa fa-cog fa-spin"></span>
    </div>`);

  // Get templates data
  app.templateManager.getTemplateByDataType(contentType)
    .then(function(templatesData) {
      const populateContent = function() {
        const elements = [];
        const stencils = [];
        const templates = [];

        // Get filter value
        const filterValue = $container.find('#input-name').val();

        // Save filter value to the menu item
        self.menuItems[self.openedMenu].filters.name.value = filterValue;

        // Loop through templates data object
        for (const key in templatesData) {
          if (!templatesData.hasOwnProperty(key)) {
            continue;
          }

          const el = templatesData[key];

          // Add module type to the elements
          el.subType = moduleType;

          // Filter elements
          if (filterValue && filterValue.length > 0) {
            if (
              el.title.toLowerCase().indexOf(filterValue.toLowerCase()) == -1
            ) {
              continue;
            }
          }

          // Add thumbnail url if thumbail property is present
          if (el.thumbnail && !el.thumbnailLoaded) {
            el.thumbnail =
              assetDownloadUrl.replace(':assetId', el.thumbnail);
            el.thumbnailLoaded = true;
          }

          // Save templates to respective groups
          // don't show items based on showIn property
          // or elements and stencils if we are in playlist editor
          if (
            el.showIn == 'playlist' && !isPlaylist ||
            el.showIn == 'layout' && isPlaylist ||
            el.showIn == 'none' ||
            el.type === 'element' && self.isPlaylist ||
            el.type === 'element-group' && self.isPlaylist
          ) {
            continue;
          } else {
            if (el.type === 'element') {
              elements.push(el);
            } else if (el.type === 'element-group') {
              stencils.push(el);
            } else if (el.type === 'static') {
              templates.push(el);
            }
          }
        }

        // Remove loading
        $container.find('.loading-container-toolbar')
          .remove();

        $container.find('.toolbar-pane-container').html(
          ToolbarContentSubmenuCardsTemplate({
            elements: elements,
            stencils: stencils,
            templates: templates,
            trans: toolbarTrans,
          }),
        );

        // Initialise tooltips
        self.parent.common.reloadTooltips(
          $container,
        );

        // Handle cards behaviour
        self.handleCardsBehaviour();

        // Save user preferences
        self.savePrefs();
      };

      // Handle name filter change
      $container.find('#input-name').off()
        .on('input', _.debounce(populateContent, 500));

      // Call populate content on load
      populateContent();
    });
};

module.exports = Toolbar;
