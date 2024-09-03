/*
 * Copyright (C) 2024 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

/* eslint-disable new-cap */
// Load templates
const ToolbarTemplate = require('../templates/toolbar.hbs');
const ToolbarCardMediaTemplate = require('../templates/toolbar-card-media.hbs');
const ToolbarCardMediaUploadTemplate =
  require('../templates/toolbar-card-media-upload.hbs');
const ToolbarCardMediaPlaceholderTemplate =
  require('../templates/toolbar-card-media-placeholder.hbs');
const ToolbarCardLayoutTemplateTemplate =
  require('../templates/toolbar-card-layout-template.hbs');
const ToolbarCardPlaylistTemplate =
  require('../templates/toolbar-card-playlist-template.hbs');
const ToolbarCardNewPlaylistTemplate =
  require('../templates/toolbar-card-playlist-new-template.hbs');
const ToolbarContentTemplate = require('../templates/toolbar-content.hbs');
const ToolbarSearchFormTemplate =
  require('../templates/toolbar-search-form.hbs');
const ToolbarContentMediaTemplate =
  require('../templates/toolbar-content-media.hbs');
const ToolbarContentMediaTableTemplate =
  require('../templates/toolbar-content-media-table.hbs');
const ToolbarContentSubmenuTemplate =
  require('../templates/toolbar-content-submenu.hbs');
const ToolbarContentSubmenuCardsTemplate =
  require('../templates/toolbar-content-submenu-elements.hbs');
const ToolbarContentGroupTemplate =
  require('../templates/toolbar-content-group.hbs');
const MediaPlayerTemplate = require('../templates/toolbar-media-preview.hbs');
const MediaInfoTemplate =
  require('../templates/toolbar-media-preview-info.hbs');

// Constants
const TABLE_VIEW_LEVEL = 4;

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

  // Flag to mark if toolbar is properly initialized
  this.initalized = false;

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

  // Toolbar zoom level (1-4 : 2 default)
  this.level = 2;

  // Level limiter ( show only 1 and 2 )
  this.levelLimiter = false;
};

/**
 * Initialize toolbar
 * @param {object} [options] - options
 * @param {boolean=} [options.isPlaylist] - is it a playlist toolbar?
 */
Toolbar.prototype.init = function({isPlaylist = false} = {}) {
  const self = this;

  // Modules to be used in Widgets
  let moduleListFiltered = [];

  // Modules to be used in other option
  const moduleListOtherFiltered = [];

  // Module media types (other than the 3 main types)
  const moduleListOtherTypes = [];

  // Mark as initialized
  self.initalized = true;

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
        hasThumbnail: el.hasThumbnail,
      });

      // Add to types
      moduleListOtherTypes.push(el.type);
    }

    // Check if it's a data type module
    el.hasDataType = (el.dataType != '');

    // If we have thumbnail, add proper path
    if (el.thumbnail && !el.thumbnailLoaded) {
      el.thumbnail =
        assetDownloadUrl.replace(':assetId', el.thumbnail);
      el.thumbnailLoaded = true;
    }

    // Add card type ( to use group cards )
    el.cardType = 'module';

    // Filter out non region specific modules
    if (el.regionSpecific === 1) {
      moduleListFiltered.push(el);
    }
  });

  // Add zone to template edit mode
  if (
    !isPlaylist &&
    this.parent.templateEditMode != undefined &&
    this.parent.templateEditMode === true
  ) {
    moduleListFiltered.push({
      moduleId: 'zone',
      name: toolbarTrans.zone,
      type: 'zone',
      icon: 'fas fa-border-none',
      dataType: '',
      target: 'layout',
    });
  }

  // Group modules by group property
  const moduleGroups = {};
  for (let i = 0; i < moduleListFiltered.length; i++) {
    const module = moduleListFiltered[i];

    // Check if module.group is an object
    if (typeof module.group == 'object' && !(module.group instanceof Array)) {
      if (!moduleGroups[module.group.id]) {
        moduleGroups[module.group.id] = {
          name: module.group.name,
          type: module.group.id,
          icon: module.group.icon,
          cardType: 'moduleGroup',
          modules: [],
        };
      }

      // Add module to group
      moduleGroups[module.group.id].modules.push(module);

      // Remove module from moduleListFiltered and decrement i
      moduleListFiltered.splice(moduleListFiltered.indexOf(module), 1);
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

  // Actions
  this.interactiveList = [
    {
      name: toolbarTrans.interactive.actions.navWidget,
      icon: 'fa fa-square-o',
      type: 'navWidget',
      target: '["frame"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.navLayout,
      icon: 'fa fa-desktop',
      type: 'navLayout',
      target: '["layout"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.previousWidget,
      icon: 'fa fa-caret-square-o-left',
      type: 'previousWidget',
      target: '["playlist"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.nextWidget,
      icon: 'fa fa-caret-square-o-right',
      type: 'nextWidget',
      target: '["playlist"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.previousLayout,
      icon: 'fa fa-arrow-circle-left',
      type: 'previousLayout',
      target: '["layout"]',
      dataType: '',
    },
    {
      name: toolbarTrans.interactive.actions.nextLayout,
      icon: 'fa fa-arrow-circle-right',
      type: 'nextLayout',
      target: '["layout"]',
      dataType: '',
    },
  ];

  this.defaultFilters = {
    name: {
      value: '',
    },
    user: {
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
    folder: {
      value: '',
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
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        owner: {
          value: '',
          key: 'users',
          dataRole: 'usersList',
          searchUrl: urlsForApi.user.get.url,
        },
        orientation: {
          value: '',
        },
      },
      sort: {
        mediaId: 'numeric',
        name: 'alpha',
        orientation: 'amount',
        width: 'numeric',
        height: 'numeric',
        duration: 'numeric',
        fileSize: 'numeric',
        createdDt: 'amount',
        modifiedDt: 'amount',
      },
      sortCol: '',
      sortDir: 'asc',
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
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        owner: {
          value: '',
          key: 'users',
          dataRole: 'usersList',
          searchUrl: urlsForApi.user.get.url,
        },
      },
      sort: {
        mediaId: 'numeric',
        name: 'alpha',
        duration: 'numeric',
        fileSize: 'numeric',
        createdDt: 'amount',
        modifiedDt: 'amount',
      },
      sortCol: '',
      sortDir: 'asc',
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
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        owner: {
          value: '',
          key: 'users',
          dataRole: 'usersList',
          searchUrl: urlsForApi.user.get.url,
        },
        orientation: {
          value: '',
        },
      },
      sort: {
        mediaId: 'numeric',
        name: 'alpha',
        orientation: 'amount',
        width: 'numeric',
        height: 'numeric',
        duration: 'numeric',
        fileSize: 'numeric',
        createdDt: 'amount',
        modifiedDt: 'amount',
      },
      sortCol: '',
      sortDir: 'asc',
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
        type: {
          value: '',
          values: moduleListOtherFiltered,
        },
        name: {
          value: '',
          key: 'media',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
        },
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        owner: {
          value: '',
          key: 'users',
          dataRole: 'usersList',
          searchUrl: urlsForApi.user.get.url,
        },
      },
      sort: {
        mediaId: 'numeric',
        name: 'alpha',
        duration: 'numeric',
        fileSize: 'numeric',
        createdDt: 'amount',
        modifiedDt: 'amount',
      },
      sortCol: '',
      sortDir: 'asc',
      state: '',
      itemCount: 0,
    },
    {
      name: 'playlists',
      itemName: toolbarTrans.menuItems.playlistsName,
      itemIcon: 'list',
      itemTitle: toolbarTrans.menuItems.playlistsTitle,
      contentType: 'playlists',
      filters: {
        name: {
          value: '',
          key: 'name',
        },
        tag: {
          value: '',
          key: 'tags',
          dataRole: 'tagsinput',
        },
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        user: {
          value: '',
          key: 'users',
          dataRole: 'usersList',
          searchUrl: urlsForApi.user.get.url,
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
    {
      name: 'layout_templates',
      disabled: isPlaylist,
      itemName: toolbarTrans.menuItems.layoutTemplateName,
      itemIcon: 'object-group',
      itemTitle: toolbarTrans.menuItems.layoutTemplateTitle,
      contentType: 'layout_templates',
      filters: {
        name: {
          value: '',
          key: 'template',
        },
        provider: {
          value: 'local',
          locked: true,
        },
        folder: {
          value: '',
          key: 'folders',
          dataRole: 'foldersList',
          searchUrl: urlsForApi.folders.get.url,
        },
        orientation: {
          value: '',
        },
      },
      state: '',
      itemCount: 0,
    },
    {
      name: 'layout_exchange',
      disabled: isPlaylist,
      itemName: toolbarTrans.menuItems.layoutExchangeName,
      itemIcon: 'exchange-alt', // TODO: Change icon!
      itemTitle: toolbarTrans.menuItems.layoutExchangeTitle,
      contentType: 'layout_exchange',
      filters: {
        name: {
          value: '',
          key: 'template',
        },
        provider: {
          value: 'remote',
          locked: true,
        },
        orientation: {
          value: '',
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
  this.moduleListOtherTypes = moduleListOtherTypes;
  this.moduleGroups = moduleGroups;

  // Get providers
  $.ajax(urlsForApi.media.getProviders).done(function(res) {
    // Stop if not available
    if (Object.keys(self).length === 0) {
      return;
    }

    if (
      Array.isArray(res)
    ) {
      res.forEach((provider) => {
        // Add provider to menu items
        self.menuItems.push(
          {
            name: provider.id,
            provider: provider.id,
            itemName: provider.id,
            link: provider.link,
            customIcon: true,
            itemTitle: toolbarTrans.menuItems
              .providerTitle.replace('%obj%', provider.id),
            itemIcon: provider.iconUrl,
            contentType: 'media',
            filters: {
              name: {
                value: '',
                key: 'media',
              },
              type: {
                value: '',
                hideDefault: true,
                values: provider.mediaTypes.map((media) => {
                  return {
                    name: toolbarTrans.libraryTypes[media],
                    type: media,
                    disabled: false,
                  };
                }),
              },
              orientation: {
                value: '',
              },
            },
            state: '',
            itemCount: 0,
          });
      });
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

    // Render
    self.render();
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    console.error(errorMessagesTrans.getProvidersFailed);

    // Render
    self.render();
  });
};

/**
 * Load user preferences
 */
Toolbar.prototype.loadPrefs = function() {
  // Load using the API
  const linkToAPI = urlsForApi.user.getPref;
  const app = this.parent;
  const self = this;

  const renderBars = function() {
    // Render toolbar and topbar if exists
    self.render({
      savePrefs: false,
    });

    if (app.topbar) {
      app.topbar.render();
    }
  };

  // Request items based on filters
  $.ajax({
    url: linkToAPI.url + '?preference=toolbar',
    type: linkToAPI.type,
  }).done(function(res) {
    // Stop if not available
    if (Object.keys(self).length === 0) {
      return;
    }

    if (res.success) {
      const loadedData = JSON.parse(res.data.value ?? '{}');
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
            if (menuIdx != -1) {
              for (const filterValue in loadedData.filters[filter]) {
                if (
                  loadedData.filters[filter].hasOwnProperty(filterValue) &&
                  self.menuItems[menuIdx].filters[filterValue] != undefined
                ) {
                  self.menuItems[menuIdx].filters[filterValue].value =
                    loadedData.filters[filter][filterValue];
                }
              }
            }
          }
        }
      }

      // Load sort
      if (loadedData.sort) {
        for (const sortItem in loadedData.sort) {
          if (loadedData.sort.hasOwnProperty(sortItem)) {
            const menuIdx = findMenuIndexByName(sortItem);
            if (menuIdx != -1) {
              self.menuItems[menuIdx].sortCol =
                (loadedData.sort[sortItem].sortCol) || '';
              self.menuItems[menuIdx].sortDir =
                loadedData.sort[sortItem].sortDir || 'asc';
            }
          }
        }
      }

      // Tooltip options
      app.common.displayTooltips =
        (loadedData.displayTooltips == 1 ||
          loadedData.displayTooltips == undefined);

      // Show delete confirmation modals
      app.common.deleteConfirmation =
        (loadedData.deleteConfirmation == 1 ||
          loadedData.deleteConfirmation == undefined);

      // Toolbar level
      self.level = loadedData.level ?? 2;

      // Reload tooltips
      app.common.reloadTooltips(self.DOMObject);

      // Render bars
      renderBars();
    } else {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }

        // Still render bars
        renderBars();
      }
    }
  }).catch(function(jqXHR, textStatus, errorThrown) {
    console.error(jqXHR, textStatus, errorThrown);
    toastr.error(errorMessagesTrans.userLoadPreferencesFailed);

    // Still render bars
    renderBars();
  });
};

/**
 * Save user preferences
 * @param {bool=} [clearPrefs = false] - Force reseting user prefs
 */
Toolbar.prototype.savePrefs = _.debounce(function(clearPrefs = false) {
  // eslint-disable-next-line no-invalid-this
  const self = this;
  const app = self.parent;

  // Get opened menu name to save
  let openedMenu =
    (self.openedMenu != -1) ?
      self.menuItems[self.openedMenu].name : -1;

  // Make a copy of the opened submenu object
  let openedSubMenu =
    (self.openedSubMenu != -1) ?
      Object.assign({}, self.openedSubMenu) : -1;

  // Remove tooltip data from submenu if exists
  if (openedSubMenu.data && openedSubMenu.data['bs.tooltip'] != undefined) {
    delete openedSubMenu.data['bs.tooltip'];
  }

  let displayTooltips = (app.common.displayTooltips) ? 1 : 0;
  let deleteConfirmation = (app.common.deleteConfirmation) ? 1 : 0;
  let level = self.level;
  let favouriteModules = [];
  const filters = {};
  const sort = {};

  // If we have opened submenu, save parent with name instead of index
  if (
    openedSubMenu != -1 &&
    openedSubMenu.parent != undefined &&
    openedSubMenu.parent != -1
  ) {
    openedSubMenu.parent = self.menuItems[openedSubMenu.parent].name;
  }

  if (clearPrefs) {
    openedMenu = -1;
    openedSubMenu = -1;
    displayTooltips = 1;
    deleteConfirmation = 1;
    level = 2;
  } else {
    // Save favourite modules
    const widgetMenu = self.menuItems.find(function(el) {
      return (el.name == 'widgets');
    });
    favouriteModules = widgetMenu.favouriteModules;

    // Save filters and sort
    self.menuItems.forEach((menu) => {
      filters[menu.name] = {};

      for (const filter in menu.filters) {
        if (
          self.defaultFilters[filter].value != menu.filters[filter].value &&
          menu.filters[filter].locked != true
        ) {
          filters[menu.name][filter] = menu.filters[filter].value;
        }
      }

      sort[menu.name] = {
        sortCol: menu.sortCol,
        sortDir: menu.sortDir,
      };
    });
  }

  const dataToSave = {
    preference: [
      {
        option: 'toolbar',
        value: JSON.stringify({
          filters: filters,
          sort: sort,
          openedMenu: openedMenu,
          openedSubMenu: openedSubMenu,
          displayTooltips: displayTooltips,
          deleteConfirmation: deleteConfirmation,
          favouriteModules: favouriteModules,
          level: level,
        }),
      },
    ],
  };

  // Save using the API
  const linkToAPI = urlsForApi.user.savePref;

  // Request items based on filters
  $.ajax({
    url: linkToAPI.url,
    type: linkToAPI.type,
    data: dataToSave,
  }).done(function(res) {
    if (!res.success) {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
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
}, 200);

/**
 * Render toolbar
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.render = function({savePrefs = true} = {}) {
  const self = this;
  const app = this.parent;

  // If toolbar isn't initialized, do it
  if (this.initalized === false) {
    // Initialize toolbar
    this.init({
      isPlaylist: this.isPlaylist,
    });

    // Stop running
    return;
  }

  // Load preferences when the toolbar is rendered for the first time
  if (this.firstRun) {
    this.firstRun = false;

    // Load user preferences
    this.loadPrefs();

    // Stop running
    return;
  }

  // Stop if not available
  if (Object.keys(self).length === 0) {
    return;
  }

  const readOnlyModeOn =
    (typeof (lD) != 'undefined' && lD?.readOnlyMode === true) ||
    (app?.readOnlyMode === true);

  // Deselect selected card on render
  this.selectedCard = {};

  // Get toolbar trans
  const newToolbarTrans = Object.assign({}, toolbarTrans);

  const toolbarOpened =
    (this.openedMenu != -1) &&
    (!readOnlyModeOn);

  // If we have a level limiter and we're over level 2
  // set level at 2
  if (this.levelLimiter && this.level > 2) {
    this.level = 2;
  }

  // Compile toolbar template with data
  const html = ToolbarTemplate({
    opened: toolbarOpened,
    menuItems: this.menuItems,
    trans: newToolbarTrans,
    mainObjectType: app.mainObjectType,
    helpLink: (
      (typeof (layoutEditorHelpLink) != 'undefined') ?
        layoutEditorHelpLink : null
    ),
    toolbarLevel: this.level,
    levelLimiter: this.levelLimiter,
  });

  // Clear temp data
  app.common.clearContainer(this.DOMObject);

  // Append toolbar html to the main div
  this.DOMObject.html(html);

  // If read only mode is enabled
  if (readOnlyModeOn) {
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

    // Prepend the item to the bottom toolbar's content
    $readOnlyMessage.prependTo(
      this.DOMObject.find('.container-toolbar .navbar-collapse'),
    ).click(lD.layout.checkout);
  } else {
    // Show toolbar
    this.DOMObject.show();

    // Handle menus
    if (Array.isArray(this.menuItems)) {
      for (let i = 0; i < this.menuItems.length; i++) {
        const toolbar = self;
        const index = i;

        this.DOMObject.find('#btn-menu-' + index).click(function() {
          toolbar.openMenu(index);
        });
      }
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

      this.openMenu(this.openedMenu, true, openedSubMenu, savePrefs);
    }
  }

  // Zoom level control
  const $toolbarLevelControlMenuBtn =
    this.DOMObject.find('.toolbar-level-control-button');

  $toolbarLevelControlMenuBtn
    .on('click', (ev) => {
      $(ev.currentTarget).parent().toggleClass('opened');
    });

  this.DOMObject.find('.toolbar-level-control-select')
    .on('click', (ev) => {
      const newLevel = $(ev.target).data('level');

      // Close menu
      $(ev.target).parents('.toolbar-level-control-menu')
        .removeClass('opened');

      // Change toolbar level if changed
      if (self.level != newLevel) {
        self.level = newLevel;

        // Render toolbar
        self.render();

        // Refresh viewer
        (app.viewer) && app.viewer.update();
      }
    });
};

/**
 * Load content
 * @param {number} menu - menu to load content for
 * @param {boolean} forceReload - force content to be reloaded even if exists
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.loadContent = function(
  menu = -1,
  forceReload = false,
  savePrefs = true,
) {
  // Make menu state to be active
  this.menuItems[menu].state = 'active';

  if (this.menuItems[menu].contentType === 'modules') {
    // Sort by favourites
    const favouriteModules = [];
    const otherModules = [];
    const otherDataModules = [];

    for (let index = 0; index < this.customModuleList.length; index++) {
      const card = this.customModuleList[index];

      card.maxSize = libraryUpload.maxSize;
      card.maxSizeMessage = libraryUpload.maxSizeMessage;

      // Filter items
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
      } else if (card.hasDataType) {
        card.favourited = false;
        otherDataModules.push(card);
      } else {
        card.favourited = false;
        otherModules.push(card);
      }
    }

    // Add items to menu content
    this.menuItems[menu].content = {
      favourites: favouriteModules,
      cards: otherModules,
      alternativeCards: otherDataModules,
      contentHeader: toolbarTrans.widgets,
      contentAlternativeHeader: toolbarTrans.dataWidgets,
      noCardsToShow: toolbarTrans.noWidgetsToShow,
    };
  } else if (this.menuItems[menu].name === 'actions') {
    const actionsFiltered = [];

    for (let index = 0; index < this.interactiveList.length; index++) {
      const item = this.interactiveList[index];

      // Filter items
      if (
        this.menuItems[menu].filters.name.value &&
        !item.name.toLowerCase().includes(
          this.menuItems[menu].filters.name.value.toLowerCase(),
        )
      ) {
        continue;
      }

      actionsFiltered.push(item);
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
  (savePrefs) && this.savePrefs();
};

/**
 * Create content
 * @param {number} menu - menu to load content for
 * @param {boolean} forceReload - force content to be reloaded even if exists
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.createContent = function(
  menu = -1,
  forceReload = false,
  savePrefs = true,
) {
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
    this.menuItems[menu].contentType != 'playlists' &&
    this.menuItems[menu].contentType != 'actions' &&
    self.DOMObject
      .find(
        '#content-' +
        menu +
        ' .toolbar-pane-container .toolbar-card',
      ).length > 0
  ) {
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
    this.elementsContentCreateWindow(menu, savePrefs);
  } else if (
    content.contentType === 'layout_templates' ||
    content.contentType === 'layout_exchange'
  ) {
    this.layoutTemplatesContentCreateWindow(menu);
  } else if (content.contentType === 'playlists') {
    this.playlistsContentCreateWindow(menu);
  } else {
    this.handleCardsBehaviour();

    // Bind search action to refresh the results
    this.DOMObject.find('.module-search-form input[type="text"]')
      .on('input', _.debounce(function(e) {
        // eslint-disable-next-line no-invalid-this
        self.menuItems[menu].filters.name.value = $(this).val();
        self.menuItems[menu].focus = e.target.selectionStart;
        app.common.clearTooltips();
        self.loadContent(menu, false);
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

  // Reload tooltips
  app.common.reloadTooltips(self.DOMObject);
};

/**
 * Open menu
 * @param {number} menu - menu to open index, -1 by default and to toggle
 * @param {bool} forceOpen - force tab open ( even if opened before )
 * @param {bool} openSubMenu - open sub menu
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.openMenu = function(
  menu = -1,
  forceOpen = false,
  openSubMenu = false,
  savePrefs = true,
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
        this.loadContent(menu, false, savePrefs);
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

  // Add toolbar level to the playlist editor modal
  this.DOMObject.parents('.editor-modal')
    .attr('toolbar-level', this.level);

  // if menu was closed, save preferences and clean content
  if (!this.opened) {
    // Save user preferences
    (savePrefs) && this.savePrefs();
  }

  // If we have a sub menu, open it
  if (openSubMenu) {
    if (this.openedSubMenu.type == 'groupMenu') {
      this.openGroupMenu(null, this.openedSubMenu.data, menu, savePrefs);
    } else if (this.openedSubMenu.type == 'subMenu') {
      this.openSubMenu(null, this.openedSubMenu.data, menu, savePrefs);
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

    // Mark editor container to show overlay is on
    this.parent.editorContainer.addClass('overlay-on');

    // Show designer overlay
    $('.custom-overlay').show().off('click').on('click', () => {
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

    if (app.common.hasTarget(draggable, 'zone')) {
      // Drop to frame region
      selectorBuild.push('.designer-region[data-sub-type="zone"].droppable');
    }

    if (app.common.hasTarget(draggable, 'element')) {
      // Validate if element is of the same
      // type as existing element or group
      const elementDataType = $(draggable).data('dataType');

      if (elementDataType != 'global') {
        // Selector that handles all droppables that can accept the
        // new dragabble of type not global
        selectorBuild.push(
          '.designer-element-group[data-element-type="' +
          elementDataType + '"].editing, ' +
          '.designer-element-group[data-element-type="global"].editing',
        );
      } else {
        // Selector that handles all droppables for elements
        selectorBuild.push(
          '.designer-element-group.editing',
        );
      }
    }

    // Playlist editor droppables
    // and layout editor droppables
    if (app.common.hasTarget(draggable, 'playlist')) {
      // Drop to playlist
      selectorBuild.push('.designer-region-playlist.droppable');

      // Drop to playlist timeline
      selectorBuild.push('#playlist-timeline.ui-droppable');
    }

    // Zone droppables
    if (app.common.hasTarget(draggable, 'zone')) {
      // Drop to zone region
      selectorBuild.push('.designer-region-zone.droppable');
    }

    // If we are in action edit mode, we need
    // to only target the action edit overlay
    if (app.viewer?.actionDropMode) {
      selectorBuild =
        ['.designer-region-drawer[data-action-edit-type="create"]'];
    }

    // Image placeholder
    if (
      $(draggable).data('type') === 'media' &&
      $(draggable).data('subType') === 'image'
    ) {
      selectorBuild.push(
        '.designer-element[data-sub-type="image_placeholder"]',
      );
    }

    // Add droppable class to all selectors
    selectorBuild = selectorBuild.map((selector) => {
      return selector + selectorAppend;
    });

    // Add droppable ( or custom ) class to all selectors
    $(selectorBuild.join(', ')).addClass(
      (customClasses != '') ? customClasses : 'ui-droppable-active');
  }

  // Show layout background overlay or image replace area if exists
  app.propertiesPanel.DOMObject
    .find('.background-image-add, .image-replace-control-area').toggleClass(
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

  // Remove overlay on class from editor container
  app.editorContainer.removeClass('overlay-on');

  // Remove media queue data
  this.DOMObject.find('.toolbar-pane-content').removeData('mediaQueue');

  // Remove drop class from droppable elements
  $('.ui-droppable, .droppable')
    .removeClass('ui-droppable-active ui-droppable-actions-target');

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
  const app = this.parent;
  const hasProvider = Boolean(this.menuItems[menu].provider);

  // Deselect previous selections
  self.deselectCardsAndDropZones();

  // Table view
  const tableView = (
    self.level === TABLE_VIEW_LEVEL &&
    !hasProvider
  );

  // Render template
  const html = ToolbarContentMediaTemplate({
    menuIndex: menu,
    filters: this.menuItems[menu].filters,
    sort: this.menuItems[menu].sort,
    sortCol: this.menuItems[menu].sortCol,
    sortDir: this.menuItems[menu].sortDir,
    trans: toolbarTrans,
    formClass: 'media-search-form',
    // Hide sort for providers and table view
    showSort:
      !hasProvider &&
      !tableView,
  });

  // Clear temp data
  app.common.clearContainer(self.DOMObject.find('#media-container-' + menu));

  // Append template to the search main div
  self.DOMObject.find('#media-container-' + menu).html(html);

  // Populate selected tab with table or grid
  (tableView) ?
    self.mediaContentPopulateTable(menu) :
    self.mediaContentPopulate(menu);
};

/**
 * Media content populate table
 * @param {number} menu - menu id
 */
Toolbar.prototype.mediaContentPopulate = function(menu) {
  const self = this;
  const app = this.parent;
  const $mediaContainer = self.DOMObject.find('#media-container-' + menu);
  const $mediaContent = self.DOMObject.find('#media-content-' + menu);
  const $mediaForm = $mediaContainer.find('.media-search-form');

  // If media container isn't still loaded, skip
  if ($mediaContainer.length === 0) {
    return;
  }

  // Request elements based on filters
  const loadData = function(clear = true) {
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
    const $form =
      self.DOMObject.find(
        '#media-container-' +
        menu +
        ' .media-search-form',
      );
    const filter = $form.serializeObject();

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

    // Get sort if exists
    const $sort = $form.find('#input-sort');
    if ($sort.length > 0 && self.menuItems[menu].sortCol != '') {
      filter.sortCol = self.menuItems[menu].sortCol;

      const sortDir = ($sort.siblings('.sort-button-container')
        .data('dir')).toUpperCase();
      filter.sortDir = (sortDir) || 'DESC';
    }

    // Filter by provider if exists
    if (self.menuItems[menu].provider) {
      filter.provider = self.menuItems[menu].provider;
    }

    $.ajax({
      url: librarySearchUrl,
      type: 'GET',
      data: $.extend({
        start: start,
        length: requestLength,
      }, filter),
    }).done(function(res) {
      // Remove loading
      $mediaContent.parent().find('.loading-container-toolbar').remove();

      // Stop if not available
      if (Object.keys(self).length === 0) {
        return;
      }

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
        gutter: 6,
      }).addClass('masonry-container');

      // Show upload card
      const addUploadCard = function(module) {
        if (module) {
          module.trans = toolbarTrans;

          module.trans.upload =
            module.trans.uploadType.replace('%obj%', module.name);

          const $uploadCard = $(ToolbarCardMediaUploadTemplate(
            Object.assign(
              {},
              module,
              {
                editingPlaylist: self.isPlaylist,
              })),
          );
          $mediaContent.append($uploadCard).masonry('appended', $uploadCard);
        }
      };

      // Add upload card only if we don't have provider
      if (
        $mediaContent.find('.upload-card').length == 0 &&
        !self.menuItems[menu].provider
      ) {
        // If we have a specific module type
        if (filter.type != '') {
          addUploadCard(app.common.getModuleByType(filter.type));
        } else {
          // Show one upload card for each media type
          self.moduleListOtherTypes.forEach((moduleType) => {
            addUploadCard(app.common.getModuleByType(moduleType));
          });
        }
      }

      // Add placeholder for images if we're in template editing mode
      if (
        app.templateEditMode &&
        self.menuItems[menu].contentType === 'media' &&
        self.menuItems[menu].name === 'image'
      ) {
        const $placeholderCard = $(ToolbarCardMediaPlaceholderTemplate(
          {
            template: 'image_placeholder',
            title: toolbarTrans.placeholder.image.title,
            description: toolbarTrans.placeholder.image.description,
            startWidth: 300,
            startHeight: 150,
            extends: {
              template: 'global_image',
              override: 'url',
              with: '',
            },
          }),
        );

        $mediaContent.append($placeholderCard)
          .masonry('appended', $placeholderCard);
      }

      if (
        (!res.data || res.data.length == 0) &&
        $mediaContent.find('.toolbar-card:not(.toolbar-card-special)')
          .length == 0
      ) {
        // Handle card behaviour
        self.handleCardsBehaviour();

        // Show no results message
        $mediaForm.append(
          '<div class="no-results-message">' +
          toolbarTrans.noMediaToShow +
          '</div>');
      } else {
        let isVideo = false;
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
            isVideo = true;
            element.videoThumbnail = element.thumbnail;
          }

          // Resolve the icon
          element.icon = modulesList.filter((el) =>
            el.type === element.type)[0]?.icon || null;

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
          // Stop if not available
          if (Object.keys(self).length === 0) {
            return;
          }

          // Show content in widgets
          $mediaContent.find('.toolbar-card').removeClass('hide-content');

          // Recalculate masonry layout again
          $mediaContent.masonry('layout');

          // Show more button
          if (res.data.length > 0) {
            if ($mediaContent.parent().find('.show-more').length == 0) {
              const $showMoreBtn =
                $('<button class="btn btn-block btn-white show-more">' +
                  toolbarTrans.showMore +
                  '</button>');
              $mediaContent.after($showMoreBtn);

              $showMoreBtn.off('click').on('click', function() {
                loadData(false);
              });
            }
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
        });

        // For video, wait for 3s and call
        // masonry layout to fix layout after loading
        if (isVideo) {
          setTimeout(() => {
            $mediaContent.masonry('layout');
          }, 3000);
        }

        // Handle card behaviour
        self.handleCardsBehaviour();
      }
    }).catch(function(jqXHR, textStatus, errorThrown) {
      // Login Form needed?
      if (res.login) {
        window.location.reload();
      } else {
        // Just an error we dont know about
        if (res.message == undefined) {
          console.error(res);
        } else {
          console.error(res.message);
        }
      }
    });

    // Initialise tooltips
    app.common.reloadTooltips($mediaContent);
  };

  // Refresh the table results
  const filterRefresh = function() {
    const filters = self.menuItems[menu].filters;
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

  // Handle provider select change
  const $providerSelect =
    $mediaContainer.find('.media-search-form #input-provider');

  const providerSelectUpdate = function() {
    const providerVal = $providerSelect.val();
    const isRemote = (providerVal === 'remote');
    $mediaContainer.find('.sort-form-group')
      .toggleClass('hidden', isRemote);

    // If remote, set sort values to empty
    if (isRemote) {
      self.menuItems[menu].sortCol = '';
    }
  };

  $providerSelect.off('change.provider')
    .on('change.provider', providerSelectUpdate);
  providerSelectUpdate();

  // Handle sort controls
  const sortButtonUpdate = function($buttonContainer, sortType, sortDir) {
    const hasSort = (sortType != undefined);
    // Input group toggle class
    $buttonContainer.parents('.input-group-toggle')
      .toggleClass('input-group', hasSort);

    // If we have sortType, update button
    $buttonContainer.toggleClass('hidden', !hasSort);

    // Update button classes
    if (hasSort) {
      // Remove all previous classes
      $buttonContainer.find('i').attr('class', '');

      // Add class based on type
      const sortDirVal = (sortDir) ?
        sortDir : 'asc';
      $buttonContainer.find('i')
        .addClass('fa fa-sort-' + sortType + '-' + sortDirVal);
      $buttonContainer.data('dir', sortDirVal);
    }
  };

  // Change sort type changes button
  const $sortSelect =
    $mediaContainer.find('.media-search-form #input-sort');

  const $sortButtonContainer =
    $mediaContainer.find('.sort-button-container');

  $sortSelect.off('change.sort').on('change.sort', function(ev) {
    // Update option on menu
    self.menuItems[menu].sortCol = $sortSelect.val();

    sortButtonUpdate(
      $sortButtonContainer,
      $sortSelect.find(':selected').data('type'),
      self.menuItems[menu].sortDir,
    );
  });

  // Button click changes sort order
  $sortButtonContainer.find('.sort-button').off('click.sort')
    .on('click.sort', function() {
      self.menuItems[menu].sortDir =
        (self.menuItems[menu].sortDir == 'asc') ? 'desc' : 'asc';

      sortButtonUpdate(
        $sortButtonContainer,
        $sortSelect.find(':selected').data('type'),
        self.menuItems[menu].sortDir,
      );

      // Reload data
      loadData();
    });

  // Update button on load
  sortButtonUpdate(
    $sortButtonContainer,
    $sortSelect.find(':selected').data('type'),
    self.menuItems[menu].sortDir,
  );

  // Handle inputs
  self.mediaContentHandleInputs($mediaForm, filterRefresh, $mediaContainer);

  // Load data
  loadData();
};

/**
 * Media content populate table
 * @param {number} menu - menu id
 */
Toolbar.prototype.mediaContentPopulateTable = function(menu) {
  const self = this;
  const app = this.parent;
  const $mediaContainer = self.DOMObject.find('#media-container-' + menu);
  const $mediaContent = $mediaContainer.find('#media-content-' + menu);
  const typeColNum = 2;
  const thumbColNum = 4;

  // Destroy previous table if exists
  $mediaContent.find('#media-table-' + menu).DataTable().destroy();
  $mediaContent.empty();

  // Add table and header
  $mediaContent.html(ToolbarContentMediaTableTemplate({
    menu: menu,
    trans: toolbarTrans.mediaTable,
  }));

  // Media form
  const $mediaForm = $mediaContainer.find('.media-search-form');

  const mediaTable = $mediaContent.find('#media-table-' + menu).DataTable({
    language: dataTablesLanguage,
    lengthMenu: [5, 10, 15, 25],
    pageLength: 10,
    autoWidth: false,
    serverSide: true,
    stateSave: true,
    searchDelay: 3000,
    order: [[1, 'asc']],
    filter: false,
    ajax: {
      url: urlsForApi.library.get.url + '?assignable=1&retired=0',
      data: function(d) {
        const filter = $mediaForm.serializeObject();

        // If we have type not defined, set types to other types
        if (
          self.menuItems[menu].name == 'library' &&
          filter.type == ''
        ) {
          filter.types = self.moduleListOtherFiltered.map((el) => el.type);
        }

        $.extend(
          d,
          filter,
        );
      },
    },
    columns: [
      {
        data: 'mediaId',
        className: 'dt-col id-col',
      },
      {
        data: 'name',
        className: 'dt-col name-col',
        render: function(data) {
          return '<div title="' + data + '">' +
            data + '</div>';
        },
      },
      {
        data: 'mediaType',
        className: 'dt-col type-col',
      },
      {
        sortable: false,
        data: dataTableCreateTags,
        className: 'dt-col tags-col',
      },
      {
        sortable: false,
        data: null,
        className: 'dt-col thumb-col',
        render: function(data, type, row, _meta) {
          if (type === 'display') {
            // Return only the image part of the data
            if (!data.thumbnail) {
              return '';
            } else {
              return `<a data-toggle="lightbox"
                data-type="image"
                href="${data.thumbnail}">
                <img src="${data.thumbnail}"/></a>`;
            }
          } else {
            return row.mediaId;
          }
        },
      },
      {
        sortable: false,
        className: 'dt-col action-col',
        data: function(_data, type, _row, _meta) {
          if (type !== 'display') {
            return '';
          }

          // Create a click-able span
          return `<div class="assign-item-container">
              <a href="#" class="assignItem">
                <span class="fa fa-plus">
              </a>
            </div>`;
        },
      },
    ],
    createdRow: function(row, data) {
      // Add toolbar-card class to row
      $(row).addClass('toolbar-card');

      // Add data
      $(row).data({
        dataType: '',
        mediaId: data.mediaId,
        subType: data.mediaType,
        target: 'layout playlist drawer zone',
        type: 'media',
      });
    },
  });

  // Check if we have type and thumbnail column visible based on type
  mediaTable.column(typeColNum).visible(
    $mediaContainer.find('form').serializeObject().type === '',
  );
  mediaTable.column(thumbColNum).visible(
    ['video', 'image'].includes(
      $mediaContainer.find('form').serializeObject().type,
    ),
  );

  mediaTable.on('draw', function(e, settings) {
    dataTableDraw(e, settings);

    // Clicky on the +spans
    self.DOMObject.find('.assignItem').click(function(ev) {
      const $target = $(ev.currentTarget);
      // Get the row that this is in.
      const data = mediaTable.row($target.closest('tr')).data();

      // Add with click if playlist
      if (self.isPlaylist) {
        app.dropItemAdd({}, $target.closest('tr'));
      } else {
        self.selectCard($target.closest('tr'), data);
      }
    });

    // Handle card behaviour
    self.handleCardsBehaviour();
  });

  mediaTable.on('processing.dt', dataTableProcessing);

  // Refresh the table results
  const filterRefresh = function() {
    const filters = self.menuItems[menu].filters;
    // Save filter options
    for (const filter in filters) {
      if (filters.hasOwnProperty(filter)) {
        filters[filter].value =
        $mediaForm.find('#input-' + filter).val();
      }
    }

    // Check if we show type and thumbnail
    mediaTable.column(typeColNum).visible(filters.type.value === '');
    mediaTable.column(thumbColNum).visible(
      ['video', 'image'].includes(filters.type.value),
    );

    // Reload table
    mediaTable.ajax.reload();

    self.savePrefs();
  };

  // Handle inputs
  self.mediaContentHandleInputs($mediaForm, filterRefresh, $mediaContainer);
};

/**
 * Handle inputs for media form
 * @param {object} $mediaForm - form container
 * @param {function} filterRefresh - Filter refresh callback
 * @param {object} $mediaContainer - media container
 */
Toolbar.prototype.mediaContentHandleInputs = function(
  $mediaForm,
  filterRefresh,
  $mediaContainer,
) {
  // Prevent filter form submit and bind the change event to reload the table
  $mediaForm.on('submit', function(e) {
    e.preventDefault();
    return false;
  });

  // Bind search action to refresh the results
  $mediaForm.find(
    'select, ' +
    'input[type="text"].input-tag',
  ).change(_.debounce(function() {
    filterRefresh();
  }, 200));

  // Bind tags change to refresh the results
  $mediaForm.find('input[type="text"]')
    .on('input', _.debounce(function() {
      filterRefresh();
    }, 500));

  // Initialize tagsinput
  const $tags = $mediaForm
    .find('input[data-role="tagsinput"]');
  $tags.tagsinput();
  $mediaContainer.off('click')
    .on('click', '#tagDiv .btn-tag', function(e) {
      // Add text to form
      $tags.tagsinput('add', $(e.target).text(), {allowDuplicates: false});
    });

  // Initialize user list input
  const $userListInput = $mediaForm.find('select[name="ownerId"]');
  makePagedSelect($userListInput, $mediaContainer, null, true);

  // Initialize folder input
  const $folderInput = $mediaForm.find('select[name="folderId"]');
  makePagedSelect($folderInput, $mediaContainer, function(data) {
    // Format data
    const newData = [];

    // Get data from folder structure recursively
    const getData = function(arr) {
      arr.forEach((el) => {
        // Add to data
        newData.push({
          folderId: el.folderId,
          folderName: el.text,
        });

        // If folder has sub-folders, get data from them as well
        if (Array.isArray(el.children)) {
          getData(el.children);
        }
      });
    };

    // Get initial data
    getData(data);

    return {
      data: newData,
    };
  }, true);

  // Initialize other select inputs
  $mediaForm
    .find(
      'select:not([name="ownerId"])' +
      ':not([name="folderId"])' +
      ':not(.input-sort)',
    ).select2({
      minimumResultsForSearch: -1, // Hide search box
    });
};

/**
 * Create elements content
 * @param {number} menu - menu index
 * @param {bool=} savePrefs - Save preferences

 */
Toolbar.prototype.elementsContentCreateWindow = function(
  menu,
  savePrefs = true,
) {
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
    null,
    savePrefs,
  );
};

/**
 * Create layout templates window
 * @param {number} menu - menu index
 */
Toolbar.prototype.layoutTemplatesContentCreateWindow = function(menu) {
  const self = this;
  const app = this.parent;
  const tabContentName = this.menuItems[menu].name;

  // Deselect previous selections
  self.deselectCardsAndDropZones();

  // Render template
  const html = ToolbarContentMediaTemplate({
    menuIndex: menu,
    filters: this.menuItems[menu].filters,
    trans: toolbarTrans,
    formClass: 'layout_tempates-search-form',
    headerMessage: (tabContentName == 'layout_exchange') ?
      toolbarTrans.layoutExchangeTemplatesMessage :
      toolbarTrans.layoutTemplatesMessage,
  });

  // Clear temp data
  app.common.clearContainer(
    self.DOMObject.find('#' + tabContentName + '-container-' + menu),
  );

  // Append template to the search main div
  self.DOMObject.find('#' + tabContentName + '-container-' + menu).html(html);

  // Load content
  this.layoutTemplatesContentPopulate(menu);
};

/**
 * Create playlists window
 * @param {number} menu - menu index
 */
Toolbar.prototype.playlistsContentCreateWindow = function(menu) {
  const self = this;

  // Deselect previous selections
  self.deselectCardsAndDropZones();

  // Render template
  const html = ToolbarContentMediaTemplate({
    menuIndex: menu,
    filters: this.menuItems[menu].filters,
    trans: toolbarTrans,
    formClass: 'playlists-search-form media-search-form',
  });

  // Append template to the search main div
  self.DOMObject.find('#playlists-container-' + menu).html(html);

  // Load content
  this.playlistsContentPopulate(menu);
};

/**
 * Create content for layout templates
 * @param {number} menu - menu index
 */
Toolbar.prototype.layoutTemplatesContentPopulate = function(menu) {
  const app = this.parent;
  const self = this;
  const tabContentName = this.menuItems[menu].name;
  const $container = self.DOMObject.find(
    '#' + tabContentName + '-container-' + menu,
  );
  const $content = self.DOMObject.find('#media-content-' + menu);
  const $searchForm = $container.parent().find('.toolbar-search-form');

  // Load template data
  const loadData = function(clear = true) {
    // Remove show more button
    $container.find('.show-more').remove();

    // Empty content and reset item count
    if (clear) {
      // Clear selection
      self.deselectCardsAndDropZones();

      // Empty content
      $content.empty();
      self.menuItems[menu].itemCount = 0;
    }

    // Show loading
    $content.before(
      `<div class="loading-container-toolbar w-100 text-center">
        <span class="loading fa fa-cog fa-spin"></span>
      </div>`);

    // Remove no media message if exists
    $searchForm.find('.no-results-message').remove();

    // If there's no masonry sizer, add it
    if ($content.find('.toolbar-card-sizer').length === 0) {
      $content.append('<div class="toolbar-card-sizer"></div>');
    }

    // Init masonry
    $content.masonry({
      itemSelector: '.toolbar-card',
      columnWidth: '.toolbar-card-sizer',
      gutter: 6,
    }).addClass('masonry-container');

    // Manage request length
    const requestLength = 15;

    // Filter start
    const start = self.menuItems[menu].itemCount;

    $.ajax({
      method: 'GET',
      url: templateSearchUrl,
      data: $.extend({
        start: start,
        length: requestLength,
        provider: 'both',
      }, $searchForm.serializeObject()),
      success: function(response) {
        // Stop if not available
        if (Object.keys(self).length === 0) {
          return;
        }

        $container.parent().find('.loading-container-toolbar').remove();

        if (response && response.data && response.data.length > 0) {
          // Process each row returned
          $.each(response.data, function(index, el) {
            // Enhance with some properties
            el.trans = toolbarTrans;
            el.showFooter = el.orientation ||
              (el.provider && el.provider.logoUrl) ||
              (el.tags && el.tags.length > 0);
            el.thumbnail = el.thumbnail || defaultThumbnailUrl;

            // Provider logos do not have the root uri
            if (el.provider && el.provider.logoUrl) {
              el.provider.logoUrl = $('meta[name=public-path]')
                .attr('content') + el.provider.logoUrl;
            }

            if (el.provider && !el.provider.link) {
              el.provider.link = '#';
            }

            // Get template and add
            const $card = $(ToolbarCardLayoutTemplateTemplate(el));

            // Add data object to card
            if ($card.hasClass('from-provider')) {
              $card.data('providerData', response.data[index]);
            }

            // Append to container
            $content.append($card).masonry('appended', $card);

            self.menuItems[menu].itemCount++;
          });

          // Layout masonry after images are loaded
          $content.imagesLoaded(function() {
            // Show content in widgets
            $content.find('.toolbar-card').removeClass('hide-content');

            // Recalculate masonry layout
            $content.masonry('layout');

            // Show more button
            if (response.data.length > 0) {
              if ($content.parent().find('.show-more').length == 0) {
                const $showMoreBtn =
                  $('<button class="btn btn-block btn-white show-more">' +
                    toolbarTrans.showMore +
                    '</button>');
                $content.after($showMoreBtn);

                $showMoreBtn.off('click').on('click', function() {
                  loadData(false);
                });
              }
            } else {
              toastr.info(
                toolbarTrans.noShowMore,
                null,
                {positionClass: 'toast-bottom-center'},
              );
            }

            // Fix for scrollbar
            const $parent = $content.parent();
            $parent.toggleClass(
              'scroll',
              ($parent.width() < $parent[0].scrollHeight),
            );
          });

          // Handle card behaviour
          self.handleCardsBehaviour();
        } else if (response.login) {
          window.location.reload();
        } else if (
          $content.find('.toolbar-card:not(.toolbar-card-special)').length === 0
        ) {
          $searchForm.append(
            '<div class="no-results-message">' +
            toolbarTrans.noMediaToShow +
            '</div>');
        }

        // Initialise tooltips
        app.common.reloadTooltips($container);
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $container.parent().find('.loading-container-toolbar').remove();
        console.error(jqXHR);
      },
    });
  };

  // Refresh the results
  const filterRefresh = function() {
    const filters = self.menuItems[menu].filters;
    // Save filter options
    for (const filter in filters) {
      if (filters.hasOwnProperty(filter)) {
        filters[filter].value =
          self.DOMObject.find(
            '#content-' +
            menu +
            ' .layout_tempates-search-form #input-' + filter,
          ).val();
      }
    }

    // Reload data
    loadData();

    self.savePrefs();
  };

  // Prevent filter form submit and bind the change event to reload the table
  $searchForm.on('submit', function(e) {
    e.preventDefault();
    return false;
  });

  // Bind search action to refresh the results
  $searchForm.find('select, input[type="text"]').change(_.debounce(function() {
    filterRefresh();
  }, 200));

  // Initialize folder input
  const $folderInput = $searchForm.find('select[name="folderId"]');
  makePagedSelect($folderInput, $searchForm, function(data) {
    // Format data
    const newData = [];

    // Get data from folder structure recursively
    const getData = function(arr) {
      arr.forEach((el) => {
        // Add to data
        newData.push({
          folderId: el.folderId,
          folderName: el.text,
        });

        // If folder has sub-folders, get data from them as well
        if (Array.isArray(el.children)) {
          getData(el.children);
        }
      });
    };

    // Get initial data
    getData(data);

    return {
      data: newData,
    };
  }, true);

  // Initialize other select inputs
  $container
    .find('.media-search-form select:not([name="ownerId"]):not(.input-sort)')
    .select2({
      minimumResultsForSearch: -1, // Hide search box
    });

  loadData();
};

/**
 * Create content for playlists
 * @param {number} menu - menu index
 */
Toolbar.prototype.playlistsContentPopulate = function(menu) {
  const self = this;
  const app = this.parent;
  const $container = self.DOMObject.find('#playlists-container-' + menu);
  const $content = self.DOMObject.find('#media-content-' + menu);
  const $searchForm = $container.parent().find('.toolbar-search-form');

  // Load playlists
  const loadData = function(clear = true) {
    // Remove show more button
    $container.find('.show-more').remove();

    // Empty content and reset item count
    if (clear) {
      // Clear selection
      self.deselectCardsAndDropZones();

      // Empty content
      $content.empty();
      self.menuItems[menu].itemCount = 0;
    }

    // Show loading
    $content.before(
      `<div class="loading-container-toolbar w-100 text-center">
        <span class="loading fa fa-cog fa-spin"></span>
      </div>`);

    // Remove no media message if exists
    $searchForm.find('.no-results-message').remove();

    // Manage request length
    const requestLength = 15;

    // Filter start
    const start = self.menuItems[menu].itemCount;

    $.ajax({
      method: 'GET',
      url: urlsForApi.playlist.get.url,
      data: $.extend({
        start: start,
        length: requestLength,
        provider: 'both',
      }, $searchForm.serializeObject()),
      success: function(response) {
        // Stop if not available
        if (Object.keys(self).length === 0) {
          return;
        }

        $container.parent().find('.loading-container-toolbar').remove();

        // Send translation with module object
        module.trans = toolbarTrans;

        // Show new playlist card
        if ($container.find('.new-playlist-card').length == 0) {
          const $playlistCard = $(ToolbarCardNewPlaylistTemplate(
            Object.assign(
              {},
              module,
              {
                editingPlaylist: self.isPlaylist,
              })),
          );
          $content.append($playlistCard);
        }

        if (response && response.data && response.data.length > 0) {
          // Process each row returned
          $.each(response.data, function(index, el) {
            // Enhance with some properties
            el.trans = toolbarTrans;
            el.showFooter = el.orientation ||
              (el.provider && el.provider.logoUrl) ||
              (el.tags && el.tags.length > 0);
            el.thumbnail = el.thumbnail || defaultThumbnailUrl;

            // Provider logos do not have the root uri
            if (el.provider && el.provider.logoUrl) {
              el.provider.logoUrl = $('meta[name=public-path]')
                .attr('content') + el.provider.logoUrl;
            }

            if (el.provider && !el.provider.link) {
              el.provider.link = '#';
            }

            // Editing playlist?
            el.editingPlaylist = self.isPlaylist;

            // Format duration
            if (typeof el.duration === 'number') {
              el.playlistDuration =
                app.common.timeFormat(el.duration);
            }

            // Get template and add
            const $card = $(ToolbarCardPlaylistTemplate(el));

            // Add data object to card
            if ($card.hasClass('from-provider')) {
              $card.data('providerData', response.data[index]);
            }

            // Append to container
            $content.append($card);

            self.menuItems[menu].itemCount++;
          });

          // Show content in widgets
          $content.find('.toolbar-card').removeClass('hide-content');

          // Show more button
          if (response.data.length > 0) {
            if ($content.parent().find('.show-more').length == 0) {
              const $showMoreBtn =
                $('<button class="btn btn-block btn-white show-more">' +
                  toolbarTrans.showMore +
                  '</button>');
              $content.after($showMoreBtn);

              $showMoreBtn.off('click').on('click', function() {
                loadData(false);
              });
            }
          } else {
            toastr.info(
              toolbarTrans.noShowMore,
              null,
              {positionClass: 'toast-bottom-center'},
            );
          }

          // Fix for scrollbar
          const $parent = $content.parent();
          $parent.toggleClass(
            'scroll',
            ($parent.width() < $parent[0].scrollHeight),
          );

          self.handleCardsBehaviour();
        } else if (response.login) {
          window.location.href = window.location.href;
          location.reload();
        } else if (
          $content.find('.toolbar-card:not(.toolbar-card-special)').length === 0
        ) {
          self.handleCardsBehaviour();

          $searchForm.append(
            '<div class="no-results-message">' +
            toolbarTrans.noPlaylistsToShow +
            '</div>');
        }

        // Initialise tooltips
        app.common.reloadTooltips($container);
      },
      error: function(jqXHR, textStatus, errorThrown) {
        $container.parent().find('.loading-container-toolbar').remove();
        console.error(jqXHR);
      },
    });
  };

  // Refresh the results
  const filterRefresh = function() {
    const filters = self.menuItems[menu].filters;
    // Save filter options
    for (const filter in filters) {
      if (filters.hasOwnProperty(filter)) {
        filters[filter].value =
          self.DOMObject.find(
            '#content-' +
            menu +
            ' .playlists-search-form #input-' + filter,
          ).val();
      }
    }

    // Reload data
    loadData();

    self.savePrefs();
  };

  // Prevent filter form submit and bind the change event to reload the table
  $searchForm.on('submit', function(e) {
    e.preventDefault();
    return false;
  });

  // Bind search action to refresh the results
  $searchForm.find('select, input[type="text"]').change(_.debounce(function() {
    filterRefresh();
  }, 200));

  // Initialize tagsinput
  const $tags = $container
    .find('.media-search-form input[data-role="tagsinput"]');
  $tags.tagsinput();
  $container.find('#media-' + menu).off('click')
    .on('click', '#tagDiv .btn-tag', function(e) {
      // Add text to form
      $tags.tagsinput('add', $(e.target).text(), {allowDuplicates: false});
    });

  // Initialize user list input
  const $userListInput = $container
    .find('.media-search-form select[name="userId"]');
  makePagedSelect($userListInput, $container, null, true);

  // Initialize folder input
  const $folderInput = $container.find('select[name="folderId"]');
  makePagedSelect($folderInput, $container, function(data) {
    // Format data
    const newData = [];

    // Get data from folder structure recursively
    const getData = function(arr) {
      arr.forEach((el) => {
        // Add to data
        newData.push({
          folderId: el.folderId,
          folderName: el.text,
        });

        // If folder has sub-folders, get data from them as well
        if (Array.isArray(el.children)) {
          getData(el.children);
        }
      });
    };

    // Get initial data
    getData(data);

    return {
      data: newData,
    };
  }, true);

  // Initialize other select inputs
  $container
    .find('.media-search-form select:not([name="userId"])' +
      ':not([name="folderId"])' +
      ':not(.input-sort)')
    .select2({
      minimumResultsForSearch: -1, // Hide search box
    });

  loadData();
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
  const readOnlyModeOn =
    (typeof (lD) != 'undefined' && lD?.readOnlyMode === true) ||
    (app?.readOnlyMode === true);

  // If in edit mode
  if (!readOnlyModeOn) {
    this.DOMObject.find('.toolbar-card:not(.toolbar-card-menu)')
      .each(function(_idx, el) {
        const $card = $(el);
        $card.draggable({
          appendTo: $card.parents('.toolbar-pane:first'),
          cursorAt: {
            top:
              (
                $card.height() +
                ($card.outerWidth(true) - $card.outerWidth()) / 2
              ) / 2,
            left:
              (
                $card.width() +
                ($card.outerWidth(true) - $card.outerWidth()) / 2
              ) / 2,
          },
          opacity: 0.7,
          helper: function(ev) {
            const $clone = $(ev.currentTarget).clone();

            // Remove events
            $clone.tooltip('dispose');
            $clone.find('[data-toggle="tooltip"]').tooltip('dispose');

            return $clone;
          },
          start: function() {
            // Deselect previous selections
            self.deselectCardsAndDropZones();

            // Show overlay
            $('.custom-overlay').show();

            // Mark editor container to show overlay is on
            self.parent.editorContainer.addClass('overlay-on');

            // Mark card as being dragged
            $card.addClass('card-dragged');

            // Mark content as selected
            $card.parents('.toolbar-pane-content').addClass('selected');
            $card.parents('nav.navbar').addClass('card-selected');
          },
          stop: function() {
            // Hide overlay
            $('.custom-overlay').hide();

            // Remove overlay on class from editor container
            self.parent.editorContainer.removeClass('overlay-on');

            // Remove card class as being dragged
            $card.removeClass('card-dragged');

            // Mark content as unselected
            $card.parents('.toolbar-pane-content').removeClass('selected');
            $card.parents('nav.navbar').removeClass('card-selected');
          },
        });
      });

    // Select normal card
    this.DOMObject.find(
      '.toolbar-card:not(.toolbar-card-menu):not(.card-selected):not(tr)',
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
      '.toolbar-card:not(.card-selected) ' +
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
  app.common.clearContainer($mediaPreviewContent);
  $mediaPreviewContent.html('').removeData('mediaId');
  app.common.clearContainer($mediaPreviewInfo);
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

  $mediaPreview.find('#closeBtn').off('click').on('click', function() {
    // Close preview and empty content
    app.common.clearContainer($mediaPreview.find('#content'));
    $mediaPreview.find('#content').html('');
    $mediaPreview.removeClass('show');
    $mediaPreview.remove();
  });

  $mediaPreview.find('#sizeBtn').off('click').on('click', function(e) {
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

  $mediaPreview.find('#selectBtn').off('click').on('click', function() {
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
  const self = this;
  const isLibraryMenu = !['audio', 'video', 'image'].includes(type);
  const getTabId = function(name) {
    if (isLibraryMenu) {
      name = 'library';
    }

    for (let index = 0; index < self.menuItems.length; index++) {
      const menu = self.menuItems[index];
      if (menu.name == name) {
        return index;
      }
    }

    return 0;
  };

  // Get tab id and set it as the opened menu
  this.openedMenu = getTabId(type);

  if (isLibraryMenu) {
    // Add filter to library tab
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
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.openSubMenu = function(
  $card, data = null,
  parentMenu = null,
  savePrefs = true,
) {
  const self = this;
  const app = this.parent;
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
  app.common.clearContainer($submenuContainer);
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
  $submenuContainer.find('.close-submenu').off('click').on('click', function() {
    $submenuContainer.removeClass('toolbar-cards-pane');

    // Clear submenu
    self.openedSubMenu = -1;

    // Open menu
    self.openMenu(openedMenu, true);

    // Save user preferences
    (savePrefs) && self.savePrefs();
  });

  // Clear tooltips
  this.parent.common.clearTooltips();

  // Load content
  self.loadTemplates(
    $submenuContainer,
    cardData.dataType,
    cardData.subType,
    savePrefs,
  );

  // Save user preferences
  (savePrefs) && self.savePrefs();
};

/**
 * Load module group submenu
 * @param {string} $card - Module card
 * @param {object} data  - Module data
 * @param {number} parentMenu - Parent menu
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.openGroupMenu = function(
  $card,
  data = null,
  parentMenu = null,
  savePrefs = true,
) {
  const self = this;
  const app = this.parent;
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
  app.common.clearContainer($submenuContainer);
  $submenuContainer.html(
    ToolbarContentGroupTemplate({
      data: cardData,
      content: content,
      trans: toolbarTrans,
    }),
  );

  // Handle back button
  $submenuContainer.find('.close-submenu').off('click').on('click', function() {
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
  (savePrefs) && self.savePrefs();
};

/**
 * Load content for templates
 * @param {object} $container - Templates container
 * @param  {object} contentType - Content type
 * @param  {object} moduleType - Module type
 * @param {bool=} savePrefs - Save preferences
 */
Toolbar.prototype.loadTemplates = function(
  $container,
  contentType,
  moduleType,
  savePrefs = false,
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
        // Stop if not available
        if (Object.keys(self).length === 0) {
          return;
        }

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
            el.showIn == 'playlist' && !self.isPlaylist ||
            el.showIn == 'layout' && self.isPlaylist ||
            el.showIn == 'templateEditor' && !app.templateEditMode ||
            el.showIn == 'none' ||
            el.type === 'element' && self.isPlaylist ||
            el.type === 'element-group' && self.isPlaylist
          ) {
            continue;
          } else {
            if (el.type === 'element') {
              // Check if element is required
              const elementModule = lD.common.getModuleByType(el.subType);

              if (
                elementModule &&
                Array.isArray(elementModule.requiredElements) &&
                elementModule.requiredElements.indexOf(el.templateId) != -1
              ) {
                el.isRequired = true;
              }

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

        // Clear temp data
        app.common.clearContainer(
          $container.find('.toolbar-pane-container'),
        );

        $container.find('.toolbar-pane-container').html(
          ToolbarContentSubmenuCardsTemplate({
            elements: elements,
            stencils: stencils,
            templates: templates,
            trans: toolbarTrans,
          }),
        );

        // Initialise tooltips
        app.common.reloadTooltips(self.DOMObject);

        // Handle cards behaviour
        self.handleCardsBehaviour();

        // Save user preferences
        (savePrefs) && self.savePrefs();
      };

      // Handle name filter change
      $container.find('#input-name').off()
        .on('input', _.debounce(populateContent, 500));

      // Call populate content on load
      populateContent();
    });
};

/**
 * Get menu item id from type
 * @param {string} type - Menu type
 * @return {number} - Menu id
 */
Toolbar.prototype.getMenuIdFromType = function(
  type,
) {
  return this.menuItems.findIndex((item) => item.name === type);
};

module.exports = Toolbar;
