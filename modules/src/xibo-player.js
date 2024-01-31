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
const XiboPlayer = function() {
  this.inputWidgetData = [];
  this.inputElements = [];
  this.playerWidgets = {};
  this.countWidgetElements = 0;
  this.countWidgetStatic = 0;
  this.countGlobalElements = 0;
  this.urlParams = new URLSearchParams(window.location.search);

  /**
   * Get widget data
   * @param {Object} currentWidget Widget object
   * @return {Promise<unknown>}
   */
  this.getWidgetData = function(currentWidget) {
    return new Promise(function(resolve) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      if (String(currentWidget.url) !== 'null') {
        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax({
          method: 'GET',
          url: currentWidget.url,
        }).done(function(data) {
          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.log(jqXHR, textStatus, errorThrown);
          resolve({
            error: jqXHR.status,
            success: false,
            data: jqXHR.responseJSON,
          });
        });
      } else if (currentWidget.data?.data !== undefined) {
        resolve(currentWidget.data);
      } else {
        resolve(null);
      }
    });
  };

  /**
   * Compose Player Widget
   * @param {Object} inputWidget Widget object
   * @param {Object|null} data Widget data
   * @param {Boolean} isDataWidget
   * @return {Object} playerWidget Composed widget object
   */
  this.playerWidget = function(inputWidget, data, isDataWidget) {
    const self = this;
    let playerWidget = inputWidget;
    const isStaticWidget = this.isStaticWidget(playerWidget);
    let widgetDataItems = [];
    let shouldShowError = false;
    let withErrorMessage = null;

    if (isDataWidget) {
      const {dataItems, showError, errorMessage} =
          this.loadData(playerWidget, data);
      widgetDataItems = dataItems;
      shouldShowError = showError;
      withErrorMessage = errorMessage;
    }

    playerWidget.meta = data !== null ? data?.meta : {};
    playerWidget.items = [];

    this.loadWidgetFunctions(playerWidget, widgetDataItems);

    if (isDataWidget) {
      const templateDataState = playerWidget.onTemplateDataLoad();

      if (!templateDataState.handled) {
        const dataLoadState = playerWidget.onDataLoad();
        console.log(
          'onTemplateDataLoad::handled = ', templateDataState.handled);

        widgetDataItems = dataLoadState.dataItems;
        console.log('dataLoadState::widgetDataItems ', widgetDataItems);

        if (!dataLoadState.handled) {
          console.log('onDataLoad::handled = ', dataLoadState.handled);
          widgetDataItems = playerWidget.onParseData(widgetDataItems);
          console.log('onParseData::widgetDataItems ', widgetDataItems);
        }
      } else {
        console.log(
          'onTemplateDataLoad::handled = ', templateDataState.handled);
        widgetDataItems = playerWidget.onParseData();
      }
    }

    playerWidget.data = widgetDataItems;
    playerWidget.showError = shouldShowError;
    playerWidget.errorMessage = withErrorMessage;
    playerWidget.isPreview = this.isPreview();
    playerWidget.isEditor = this.isEditor();

    // Only add below props for widget with elements
    if (!isStaticWidget && !self.isModule(playerWidget)) {
      const tempElements = this.getElementsByWidgetId(
        playerWidget.widgetId,
        this.inputElements,
      );
      playerWidget.elements = this.composeElements(tempElements, playerWidget);
      playerWidget.isMixed = false; // Identifier for mixed widget element
      playerWidget.standaloneSlotsData = {};
      playerWidget.groupSlotsData = {};

      if (playerWidget.elements.hasOwnProperty('standalone') &&
        playerWidget.elements.hasOwnProperty('groups') &&
        (Object.keys(playerWidget.elements.standalone).length > 0 ||
        Object.keys(playerWidget.elements.groups).length > 0)
      ) {
        playerWidget =
          PlayerHelper.decorateCollectionSlots(widgetDataItems, playerWidget);
      }
    }

    // Useful when re-rendering the widget through the web console
    // parameter "shouldRefresh" defaults to =true to refresh widget data
    playerWidget.render = function(shouldRefresh = true) {
      self.renderWidget(playerWidget, shouldRefresh);
    };

    return playerWidget;
  };

  /**
   * Define widget functions used for render flow
   * @param {Object} playerWidget Widget object
   * @param {Array} dataItems Widget data
   */
  this.loadWidgetFunctions = function(playerWidget, dataItems) {
    const self = this;
    const params = this.getRenderParams(
      playerWidget,
      globalOptions,
      {target: $('body')},
    );

    playerWidget.onTemplateDataLoad = function() {
      return self.onTemplateDataLoad({
        widgetId: playerWidget.widgetId,
      });
    };
    playerWidget.onDataLoad = function() {
      return self.onDataLoad({
        widgetId: playerWidget.widgetId,
        dataItems,
        meta: playerWidget.meta,
        properties: playerWidget.properties,
      });
    };
    playerWidget.onParseData = function(widgetDataItems) {
      return self.onParseData(playerWidget, widgetDataItems ?? dataItems);
    };
    playerWidget.onTemplateRender = function(currentWidget) {
      return self.onTemplateRender(params, currentWidget);
    };
    playerWidget.onRender = function(staticWidget) {
      return self.onRender({
        ...params,
        items: staticWidget ? staticWidget.items : params.items,
      });
    };
    playerWidget.onTemplateVisible = function() {
      return self.onTemplateVisible(params);
    };
    playerWidget.onVisible = function() {
      return self.onVisible(params);
    };
  };

  /**
   * Compose widget elements
   * @param {Array} widgetElements Widget elements
   * @param {Object} currentWidget Widget object
   * @return {Object} {standalone: {}, groups: {}}
   */
  this.composeElements = function(widgetElements, currentWidget) {
    if (!widgetElements || widgetElements.length === 0) {
      return {standalone: {}, groups: {}};
    }

    const self = this;
    let elementsWithNoMetaData = 0;
    const _widgetElements = widgetElements.reduce(function(collection, item) {
      const element = self.decorateElement(item, currentWidget);
      const isGroup = self.isGroup(element);
      let standaloneKey = element.type === 'dataset' ?
        element.id + '_' + element.templateData.datasetField :
        element.id;

      // Allow multiple data source of same element type by widget
      standaloneKey += '_' + currentWidget.widgetId;

      // Initialize object values
      if (!isGroup && !collection.standalone.hasOwnProperty(standaloneKey)) {
        collection.standalone[standaloneKey] = {};
      }

      let groupKey = null;
      if (isGroup) {
        groupKey = element.groupId + '_' + currentWidget.widgetId;
        if (!collection.groups.hasOwnProperty(groupKey)) {
          collection.groups[groupKey] = {
            ...globalOptions,
            ...currentWidget.properties,
            ...element.groupProperties,
            id: groupKey,
            uniqueID: element.groupId,
            duration: currentWidget.duration,
            parentId: groupKey,
            widgetId: currentWidget.widgetId,
            slot: element.slot,
            items: [],
          };
        }
      }

      // Fill in objects with items
      if (!isGroup && Object.keys(collection.standalone).length > 0) {
        const keyIndex =
          Object.keys(collection.standalone[standaloneKey]).length + 1;
        collection.standalone[standaloneKey][keyIndex] =
            {
              ...element,
              numItems: 1,
              duration: currentWidget.duration,
            };

        if (!element.dataInMeta) {
          elementsWithNoMetaData++;
        }
      }

      if (isGroup && Object.keys(collection.groups).length > 0 &&
        groupKey !== null
      ) {
        collection.groups[groupKey] = {
          ...collection.groups[groupKey],
          items: [
            ...collection.groups[groupKey].items,
            {...element, numItems: 1},
          ],
        };
      }

      return collection;
    }, {standalone: {}, groups: {}});

    currentWidget.isMixed =
      Object.keys(_widgetElements.standalone).length > 0 &&
      elementsWithNoMetaData > 0 &&
      Object.keys(_widgetElements.groups).length > 0;

    return _widgetElements;
  };

  /**
   * Parse single element for extended properties
   * @param {Object} element Element object
   * @param {Object} currentWidget Widget object
   * @return {Object} element
   */
  this.decorateElement = function(element, currentWidget) {
    const elemCopy = JSON.parse(JSON.stringify(element));
    const elemProps = elemCopy?.properties || {};

    elemProps.circleRadius = 0;
    // Calculate circle radius based on outlineWidth
    if (element.id === 'circle') {
      elemProps.circleRadius = elemProps.outline === 1 ?
        50 - (elemProps.outlineWidth / 4) : 50;
    }

    if (Object.keys(elemCopy).length > 0 &&
        elemCopy.hasOwnProperty('properties')) {
      delete elemCopy.properties;
    }

    // Check if we have template from templateId or module
    // and set it as the template
    let $template = null;
    const templateSelector = `#hbs-${elemCopy.id}`;
    if ($(templateSelector).length > 0) {
      $template = $(templateSelector);
    }

    elemCopy.hbs = null;
    elemCopy.dataOverride = null;
    elemCopy.dataOverrideWith = null;
    elemCopy.escapeHtml = null;
    elemCopy.isExtended = false;
    elemCopy.withData = false;
    elemCopy.widgetId = currentWidget.widgetId;
    elemCopy.dataInMeta = false;

    // Compile the template if it exists
    if ($template && $template.length > 0) {
      elemCopy.dataOverride =
        $template?.data('extends-override');
      elemCopy.dataOverrideWith =
        $template?.data('extends-with');
      elemCopy.escapeHtml =
        $template?.data('escape-html');

      if (String(elemCopy.dataOverride).length > 0 &&
        String(elemCopy.dataOverrideWith).length > 0
      ) {
        elemCopy.isExtended = true;
      }

      elemCopy.hbs = Handlebars.compile($template.html());
    }

    // Special case for handling weather language
    if (elemProps.hasOwnProperty('lang') &&
      currentWidget.properties.hasOwnProperty('lang')
    ) {
      const elemLang = elemProps.lang;
      const widgetLang = currentWidget.properties.lang;

      elemProps.lang = (elemLang !== null && String(elemLang).length > 0) ?
        elemLang : widgetLang;
    }

    elemCopy.templateData = Object.assign(
      {}, elemCopy, elemProps, globalOptions,
      {uniqueID: elemCopy.elementId, prop: {...elemCopy, ...elemProps}},
    );

    // Make a copy of circleRadius to templateData if exists
    if (elemProps.hasOwnProperty('circleRadius')) {
      elemCopy.templateData.circleRadius = elemProps.circleRadius;
    }

    // Get widget info if exists.
    if (currentWidget.templateId !== null &&
      String(currentWidget.url) !== 'null'
    ) {
      elemCopy.renderData = Object.assign(
        {},
        currentWidget.properties,
        elemCopy,
        globalOptions,
        {
          duration: currentWidget.duration,
          marqueeInlineSelector: `.${elemCopy.templateData.id}--item`,
          parentId: elemCopy.elementId,
        },
      );
      elemCopy.withData = true;
    } else {
      // Elements with no data can be extended.
      // Thus, we have to decorate the element with extended params
      if (elemCopy.dataOverride !== null &&
        elemCopy.dataOverrideWith !== null
      ) {
        const extendWith =
          transformer.getExtendedDataKey(elemCopy.dataOverrideWith);

        // Check if extendWith exist in elemProps and templateData
        if (elemProps.hasOwnProperty(extendWith)) {
          elemCopy[elemCopy.dataOverride] = elemProps[extendWith];
          elemCopy.templateData[elemCopy.dataOverride] =
              elemProps[extendWith];
        }
      }
    }

    if (elemCopy?.renderData?.hasOwnProperty('durationIsPerItem')) {
      elemCopy.durationIsPerItem = elemCopy.renderData.durationIsPerItem;
    }

    // Check if element is extended and data is coming from meta
    if (elemCopy.isExtended && elemCopy.dataOverrideWith !== null &&
        elemCopy.dataOverrideWith.includes('meta')) {
      elemCopy.dataInMeta = true;
    }

    return elemCopy;
  };

  this.isGroup = function(element) {
    return Boolean(element.groupId);
  };
};

/**
 * Initializes player widgets, accepting inputs from HTML output
 * @param {Array} widgetData Input widgetData from HTML
 * @param {Array} elements Input elements from HTML
 */
XiboPlayer.prototype.init = function(widgetData, elements) {
  const self = this;
  let calledXiboScaler = false;

  self.inputWidgetData = widgetData;
  self.inputElements = elements;

  // Create global render array of functions
  window.renders = [];

  // Loop through each widget from widgetData
  if (self.inputWidgetData.length > 0) {
    self.inputWidgetData.forEach(function(inputWidget) {
      // Save widgetData to xic
      xiboIC.set(inputWidget.widgetId, 'widgetData', inputWidget);

      // Run the onInitialize function if it exists
      if (typeof window['onInitialize_' + inputWidget.widgetId] ===
        'function') {
        window['onInitialize_' + inputWidget.widgetId](
          inputWidget.widgetId,
          $('body'),
          inputWidget.properties,
          inputWidget.meta,
        );
        console.log('Called onInitialize for widget > ', inputWidget.widgetId);
      }

      // Set default isDataExpected value if it does not exist
      if (!inputWidget.hasOwnProperty('isDataExpected')) {
        inputWidget.isDataExpected = String(inputWidget.url) !== 'null';
      }

      // Check if inputWidget is a data widget
      if (inputWidget.isDataExpected) {
        // Load data
        self.getWidgetData(inputWidget).then(function(response) {
          if (self.isStaticWidget(inputWidget)) {
            console.log('Data Widget::Static Template');
            self.countWidgetStatic++;
          } else {
            console.log('Data Widget::Elements');
            self.countWidgetElements++;
          }

          const currentWidget = self.playerWidget(
            inputWidget,
            response,
            true,
          );
          self.playerWidgets[inputWidget.widgetId] = currentWidget;

          self.renderWidget(currentWidget);

          if (self.countWidgetElements > 0 && calledXiboScaler === false) {
            self.runLayoutScaler(currentWidget);
            calledXiboScaler = true;
          }
        });
      } else if (self.isModule(inputWidget)) { // It's a module
        console.log('Non-data Widget::Module');
        const currentWidget = self.playerWidget(
          inputWidget,
          [],
          false,
        );
        self.playerWidgets[inputWidget.widgetId] = currentWidget;

        self.renderModule(currentWidget);
      } else { // All global elements goes here
        console.log('Non-data Widget::Global Elements');
        const currentWidget = self.playerWidget(
          inputWidget,
          [],
          false,
        );
        self.playerWidgets[inputWidget.widgetId] = currentWidget;
        self.countGlobalElements++;

        self.renderGlobalElements(currentWidget);

        if (self.countGlobalElements > 0 && calledXiboScaler === false) {
          self.runLayoutScaler(currentWidget);
          calledXiboScaler = true;
        }
      }
    });
  }
};

XiboPlayer.prototype.isPreview = function() {
  return this.urlParams.get('preview') === '1';
};

XiboPlayer.prototype.isEditor = function() {
  return this.urlParams.get('isEditor') === '1';
};

/**
 * Compose widget data
 * @param {Object} currentWidget Widget object
 * @param {Object|Array} data Widget data from data provider
 * @return {Object} widgetData
 */
XiboPlayer.prototype.loadData = function(currentWidget, data) {
  const self = this;
  const widgetData = {
    isSampleData: false,
    dataItems: [],
    isArray: Array.isArray(data?.data),
    showError: false,
    errorMessage: null,
  };
  const composeSampleData = () => {
    widgetData.isSampleData = true;

    if (currentWidget.sample === null) {
      widgetData.dataItems = [];
      return [];
    }

    // If data is empty, use sample data instead
    // Add single element or array of elements
    widgetData.dataItems = (Array.isArray(currentWidget.sample)) ?
      currentWidget.sample.slice(0) :
      [currentWidget.sample];

    return widgetData.dataItems.reduce(function(data, item) {
      Object.keys(item).forEach(function(itemKey) {
        if (String(item[itemKey]).match(DateFormatHelper.macroRegex) !== null) {
          item[itemKey] =
            DateFormatHelper.composeUTCDateFromMacro(item[itemKey]);
        }
      });

      return [...data, {...item}];
    }, []);
  };

  if (currentWidget.isDataExpected) {
    if (widgetData.isArray && data?.data?.length > 0) {
      widgetData.dataItems = data?.data;
    } else {
      widgetData.dataItems = self.isEditor() ? composeSampleData() : [];
      if (data?.success === false || !currentWidget.isValid) {
        widgetData.showError = self.isEditor();
      }
    }
  }

  if (widgetData.showError && data?.message) {
    widgetData.errorMessage = data?.message;
  }

  return widgetData;
};

XiboPlayer.prototype.getElementsByWidgetId = function(widgetId, inputElements) {
  const self = this;
  let widgetElements = [];
  let _inputElements = inputElements;

  if (!_inputElements) {
    _inputElements = self.inputElements;
  }

  if (_inputElements !== undefined && _inputElements?.length > 0) {
    _inputElements.forEach(function(elemVal) {
      if (elemVal?.length > 0) {
        elemVal.forEach(function(elemObj) {
          if (elemObj.widgetId === widgetId) {
            widgetElements = elemObj?.elements ?? [];
          }
        });
      }
    });
  }

  return widgetElements;
};

XiboPlayer.prototype.getWidgetById = function(widgetId) {
  const playerWidgets = this.playerWidgets;

  if (!widgetId || Object.keys(playerWidgets).length === 0) {
    return null;
  }

  if (!playerWidgets.hasOwnProperty(widgetId)) {
    return null;
  }

  return playerWidgets[widgetId];
};

/**
 * Gets new widget data from data provider and calls callback parameter
 * to re-render the widget
 * @param {Object} currentWidget Widget object
 * @param {Function} callback Callback function to call after getting new widget
 * data
 */
XiboPlayer.prototype.getFreshWidgetData = function(currentWidget, callback) {
  if (!currentWidget) {
    return;
  }

  const self = this;
  if (typeof callback === 'function') {
    this.getWidgetData(currentWidget).then(function(response) {
      const freshWidget = self.playerWidget(currentWidget, response, true);

      if (self.playerWidgets.hasOwnProperty(freshWidget.widgetId)) {
        self.playerWidgets[freshWidget.widgetId] = freshWidget;
      }

      callback.apply(self, [freshWidget, false]);
    });
  }
};

/**
 * Renders data widgets (static template/elements)
 * @param {Object} widget
 * @param {Boolean?} shouldRefresh Optional parameter to get fresh widget data
 * @param {Number?} widgetId Optional parameter to get widget object
 */
XiboPlayer.prototype.renderWidget = function(widget, shouldRefresh, widgetId) {
  let currentWidget = widget;

  if (widgetId) {
    currentWidget = this.getWidgetById(widgetId);
  }

  // Render widgets by kind: static OR elements
  if (this.isStaticWidget(currentWidget)) {
    // Render static widget template
    if (shouldRefresh) {
      this.getFreshWidgetData(currentWidget, this.renderStaticWidget);
    } else {
      this.renderStaticWidget(currentWidget);
    }
  } else {
    // Render widget elements
    if (shouldRefresh) {
      this.getFreshWidgetData(currentWidget, this.renderWidgetElements);
    } else {
      this.renderWidgetElements(currentWidget);
    }
  }
};

/**
 * Renders widget with static templates
 * @param {Object} staticWidget Widget object
 */
XiboPlayer.prototype.renderStaticWidget = function(staticWidget) {
  const $target = $('body');
  const $content = $('#content');
  const {data, showError, errorMessage} = staticWidget;

  staticWidget.items = [];

  if (this.isEditor() && showError && errorMessage !== null) {
    const $errMsg = $('<div class="error-message" role="alert"></div>');

    $errMsg.css({
      position: 'absolute',
      bottom: 0,
      left: 0,
      textAlign: 'center',
      width: '100%',
      padding: '12px 0',
      backgroundColor: '#d05454',
      color: 'white',
      zIndex: 2,
      fontWeight: 'bold',
      fontSize: '1.1rem',
      opacity: 0.85,
    }).html(errorMessage);

    $target.append($errMsg);
  }

  // Add meta to the widget if it exists
  if (data?.meta) {
    staticWidget.meta = data.meta;
  }

  // Check if we have template from templateId or module
  // and set it as the template
  let $template = null;
  if ($('#hbs-' + staticWidget.templateId).length > 0) {
    $template = $('#hbs-' + staticWidget.templateId);
  }

  let hbs = null;
  // Compile the template if it exists
  if ($template && $template.length > 0) {
    hbs = Handlebars.compile($template.html());
  }

  // For each data item, parse it and add it to the content
  $.each(data, function(_key, item) {
    // Add the item to the content
    if (hbs) {
      $content.append(hbs(item));
    }

    // Add item to the widget object
    (item) && staticWidget.items.push(item);
  });

  // Save template height and width if exists to global options
  this.saveTemplateDimensions($template);

  // Save template properties to widget properties
  for (const key in staticWidget.templateProperties) {
    if (staticWidget.templateProperties.hasOwnProperty(key)) {
      staticWidget.properties[key] = staticWidget.templateProperties[key];
    }
  }

  // Check if we have a custom template
  let customTemplate = false;
  if (
    staticWidget.properties['customTemplate'] &&
    staticWidget.properties['customTemplate'] == 1
  ) {
    customTemplate = true;
  }

  // If we have a custom template, run the legacy template render first
  if (customTemplate) {
    const newOptions =
      $('body').xiboLegacyTemplateRender(
        Object.assign(
          staticWidget.properties,
          globalOptions,
        ),
        staticWidget,
      ).options;

    // Merge new options with globalOptions
    globalOptions = Object.assign(globalOptions, newOptions);
  }
  // Save widget as global variable
  window.widget = staticWidget;

  const templateRenderState = staticWidget.onTemplateRender(staticWidget);

  if (!templateRenderState.handled) {
    // Run module onRender function
    staticWidget.onRender(staticWidget);
  }

  const onVisibleMethods = function() {
    const templateVisibleState = staticWidget.onTemplateVisible();

    if (!templateVisibleState.handled) {
      staticWidget.onVisible();
    }
  };

  // Check for visibility
  if (xiboIC.checkVisible()) {
    onVisibleMethods();
  } else {
    xiboIC.addToQueue(onVisibleMethods);
  }

  // Lock all interactions
  xiboIC.lockAllInteractions();

  console.log(
    '<<<END>>> renderStaticWidget for widget >', staticWidget.widgetId);
};

/**
 * Renders widget elements
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.renderWidgetElements = function(currentWidget) {
  const self = this;
  const {
    data,
    elements: widgetElements,
    groupSlotsData,
    standaloneSlotsData,
    templateId,
    url,
    meta,
  } = currentWidget;
  const $content = $('#content');

  // Check if data is expected, and we have elements but with no data
  // Then expire
  if (currentWidget.isDataExpected && data.length === 0) {
    xiboIC.expireNow({targetId: xiboICTargetId});
    xiboIC.reportFault({
      code: '5001',
      reason: 'No Data',
    }, {targetId: xiboICTargetId});
    return;
  }

  const standaloneElems = widgetElements.standalone;
  const standaloneData = standaloneSlotsData;

  // Groups with data
  if (Object.values(groupSlotsData).length > 0) {
    const {
      maxSlot,
      pinnedSlots,
    } = PlayerHelper.getGroupData(widgetElements.groups, 'items', false);

    $.each(Object.keys(groupSlotsData), function(slotIndex, slotKey) {
      const groupSlotId = currentWidget.mappedSlotGroup[slotKey];
      const groupSlotObj = widgetElements.groups[groupSlotId];
      const groupDataKeys = groupSlotsData[slotKey];
      const $grpContent =
          $(`<div class="${groupSlotId}"></div>`);
      const isMarquee = PlayerHelper.isMarquee(groupSlotObj.effect);

      if (groupDataKeys.length > 0 &&
          templateId !== null && url !== null
      ) {
        $.each(groupDataKeys, function(dataKeyIndx, dataKey) {
          if (groupSlotObj?.items.length > 0) {
            $.each(groupSlotObj.items,
              function(itemKey, groupItem) {
                let dataItem = dataKey === 'empty' ?
                  dataKey : {...(data[dataKey - 1] || {})};

                // Load element functions
                self.loadElementFunctions(groupItem, dataItem);

                // Run onElementParseData function
                dataItem = groupItem.onElementParseData();

                PlayerHelper.renderDataItem(
                  true,
                  dataKey,
                  dataItem,
                  groupItem,
                  slotKey,
                  maxSlot,
                  groupItem.pinSlot,
                  pinnedSlots,
                  groupSlotId,
                  $grpContent,
                  {...groupSlotObj, isMarquee},
                  meta,
                  $content,
                );
              });
          }
        });

        $grpContent.css({
          width: groupSlotObj.width,
          height: groupSlotObj.height,
          position: 'absolute',
          top: groupSlotObj.top,
          left: groupSlotObj.left,
          overflow: 'hidden',
          zIndex: groupSlotObj.layer,
        });

        if (isMarquee) {
          const $scroller =
              $(`<div class="${groupSlotObj.id}--marquee scroll"></div>`);

          $scroller.css({
            display: 'flex',
            height: groupSlotObj.height,
          });

          if (groupSlotObj?.templateData?.verticalAlign) {
            $scroller.css({
              alignItems: groupSlotObj?.templateData?.verticalAlign,
            });
          }

          $grpContent.wrapInner($scroller.prop('outerHTML'));
        }

        // Remove data group element if exists to avoid duplicate
        if ($content.find('.' +
            groupSlotId + '.cycle-slideshow').length === 1) {
          $content.find('.' +
              groupSlotId + '.cycle-slideshow').cycle('destroy');
        }
        if ($content.find('.' + groupSlotId).length === 1) {
          $content.find('.' + groupSlotId).remove();
        }

        $content.append($grpContent);

        $grpContent.promise().done(function() {
          $grpContent.xiboElementsRender(
            {
              ...groupSlotObj,
              itemsPerPage: maxSlot,
              numItems: data.length,
              selector: `.${groupSlotId}`,
            },
            $grpContent.find(`.${groupSlotObj.id}--item`),
          );
        });

        currentWidget.items.push($grpContent);
      }
    });
  }

  // Standalone elements with data
  if (Object.values(standaloneSlotsData).length > 0) {
    $.each(Object.keys(standaloneData),
      function(keyIndx, keyValue) {
        if (standaloneData.hasOwnProperty(keyValue) &&
          Object.keys(standaloneData[keyValue]).length > 0 &&
          templateId !== null && url !== null
        ) {
          const {maxSlot, pinnedSlots} =
            PlayerHelper.getGroupData(
              [standaloneElems],
              keyValue,
              true,
            );

          $.each(Object.keys(standaloneData[keyValue]),
            function(slotIndex, slotKey) {
              const slotObj = standaloneElems[keyValue][slotKey] || null;
              const dataKeys = standaloneData[keyValue][slotKey];
              const grpCln = `${keyValue}_page-${slotKey}`;
              const $grpItem = $(`<div class="${grpCln}"></div>`);
              const isMarquee =
                PlayerHelper.isMarquee(slotObj?.effect ?? 'noTransition');

              if (dataKeys.length > 0) {
                $.each(dataKeys,
                  function(dataKeyIndx, dataKey) {
                    let dataItem = dataKey === 'empty' ?
                      dataKey : {...(data[dataKey - 1] || {})};

                    // Load element functions
                    self.loadElementFunctions(slotObj, dataItem);

                    // Run onElementParseData function
                    dataItem = slotObj.onElementParseData();

                    PlayerHelper.renderDataItem(
                      false,
                      dataKey,
                      dataItem,
                      slotObj ?? {},
                      slotKey,
                      maxSlot,
                      slotObj?.pinSlot,
                      pinnedSlots,
                      grpCln,
                      $grpItem,
                      {...slotObj, isMarquee},
                      meta,
                      $content,
                    );
                  });

                if (isMarquee) {
                  $grpItem.css({
                    width: slotObj.width,
                    height: slotObj.height,
                    position: 'absolute',
                    top: slotObj.top,
                    left: slotObj.left,
                    overflow: 'hidden',
                    zIndex: slotObj.layer,
                  });

                  const $scroller =
                      $(`<div class="${slotObj.id}--marquee scroll"/>`);

                  $scroller.css({
                    display: 'flex',
                    height: slotObj.height,
                  });

                  if (slotObj?.templateData?.verticalAlign) {
                    $scroller.css({
                      alignItems: slotObj?.templateData?.verticalAlign,
                    });
                  }

                  $grpItem.wrapInner($scroller.prop('outerHTML'));
                } else {
                  $grpItem.css({
                    position: 'absolute',
                    top: slotObj.top,
                    left: slotObj.left,
                    width: slotObj.width,
                    height: slotObj.height,
                    zIndex: slotObj.layer,
                  });
                }

                // Remove data item element if it exists to avoid duplicate
                if ($content.find('.' +
                    grpCln + '.cycle-slideshow').length === 1) {
                  $content.find('.' +
                      grpCln + '.cycle-slideshow').cycle('destroy');
                }
                if ($content.find('.' + grpCln).length === 1) {
                  $content.find('.' + grpCln).remove();
                }

                $content.append($grpItem);

                $grpItem.xiboElementsRender(
                  {
                    ...slotObj,
                    parentId: grpCln,
                    itemsPerPage: maxSlot,
                    numItems: data.length,
                    id: grpCln,
                    selector: `.${grpCln}`,
                  },
                  $grpItem.find(`.${grpCln}--item`),
                );

                currentWidget.items.push($grpItem);
              }
            });
        }
      });
  }

  // Check if we are visible
  if (xiboIC.checkVisible()) {
    currentWidget.onVisible();
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.log(
    '<<<END>>> of renderWidgetElements for widget >', currentWidget.widgetId);
};

/**
 * Renders widget with global elements
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.renderGlobalElements = function(currentWidget) {
  const self = this;
  const {elements: widgetElements, meta} = currentWidget;
  const $content = $('#content');
  const groupedElements = widgetElements.groups;
  const standaloneElements = widgetElements.standalone;

  // Global grouped elements
  $.each(Object.keys(groupedElements), function(grpIndex, grpId) {
    const groupObj = groupedElements[grpId];

    if (groupObj?.items.length > 0) {
      $.each(groupObj.items,
        function(itemKey, groupItem) {
          // Load element functions
          self.loadElementFunctions(groupItem, {});

          (groupItem.hbs) && $content.append(
            PlayerHelper.renderElement(
              groupItem.hbs,
              groupItem.templateData,
              true,
            ),
          );

          const itemID =
            groupItem.uniqueID || groupItem.templateData?.uniqueID;
          const $itemContainer = $(`<div class="${grpId}"></div>`);

          // Call onTemplateRender
          // Handle the rendering of the template
          (groupItem.onTemplateRender() !== undefined) &&
          groupItem.onTemplateRender()(
            groupItem.elementId,
            $itemContainer.find(`.${itemID}--item`),
            $content.find(`.${itemID}--item`),
            {groupItem, ...groupItem.templateData, data: {}},
            meta,
          );
        });
    }
  });

  // Global standalone elements
  $.each(Object.keys(standaloneElements),
    function(keyIndx, keyValue) {
      $.each(Object.keys(standaloneElements[keyValue]),
        function(keyIndex, elemKey) {
          const standaloneElem = standaloneElements[keyValue][elemKey];

          // Load element functions
          self.loadElementFunctions(standaloneElem, {});

          (standaloneElem.hbs) && $content.append(
            PlayerHelper.renderElement(
              standaloneElem.hbs,
              standaloneElem.templateData,
              true,
            ),
          );

          const itemID =
            standaloneElem.uniqueID || standaloneElem.templateData?.uniqueID;
          const grpCln = `${keyValue}_page-${elemKey}`;
          const $itemContainer = $(`<div class="${grpCln}"></div>`);

          // Call onTemplateRender
          // Handle the rendering of the template
          (standaloneElem.onTemplateRender() !== undefined) &&
          standaloneElem.onTemplateRender()(
            standaloneElem.elementId,
            $itemContainer.find(`.${itemID}--item`),
            $content.find(`.${itemID}--item`),
            {standaloneElem, ...standaloneElem.templateData, data: {}},
            meta,
          );
        });
    });

  // Check if we are visible
  if (xiboIC.checkVisible()) {
    currentWidget.onVisible();
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.log(
    '<<<END>>> of renderGlobalElements for widget >', currentWidget.widgetId);
};

/**
 * Renders widget module
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.renderModule = function(currentWidget) {
  let $template = null;
  if ($('#hbs-module').length > 0) {
    $template = $('#hbs-module');
  }

  let hbs = null;
  // Compile the template if it exists
  if ($template && $template.length > 0) {
    hbs = Handlebars.compile($template.html());
  }

  // If we don't have dataType, or we have a module template
  // add it to the content with widget properties and global options
  if (hbs) {
    $('#content').append(hbs(
      Object.assign(currentWidget.properties, globalOptions),
    ));
  }

  // Save template height and width if exists to global options
  this.saveTemplateDimensions($template);

  // Save widget as global variable
  window.widget = currentWidget;

  // Run onRender
  currentWidget.onRender();

  if (xiboIC.checkVisible()) {
    // Run onVisible
    currentWidget.onVisible();
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.log('<<<END>>> of renderModule for widget >', currentWidget.widgetId);
};

/**
 * Define element functions
 * @param {Object} element Element
 * @param {Object} dataItem Data item
 */
XiboPlayer.prototype.loadElementFunctions = function(element, dataItem) {
  element.onElementParseData = function() {
    const extendDataWith = transformer
      .getExtendedDataKey(element.dataOverrideWith);

    if (extendDataWith !== null &&
      dataItem.hasOwnProperty(extendDataWith)
    ) {
      dataItem[element.dataOverride] = dataItem[extendDataWith];
    }

    // Handle special case for setting data for the player
    if (element.type === 'dataset' && Object.keys(dataItem).length > 0) {
      if (element.dataOverride !== null &&
        element.templateData?.datasetField !== undefined
      ) {
        element[element.dataOverride] =
          dataItem[element.templateData.datasetField];

        // Change value in templateData if exists
        if (element.templateData.hasOwnProperty(element.dataOverride)) {
          element.templateData[element.dataOverride] =
            dataItem[element.templateData.datasetField];
        }
      }
    }

    if (typeof window[
      `onElementParseData_${element.templateData.id}`
    ] === 'function') {
      dataItem[element.dataOverride] =
        window[`onElementParseData_${element.templateData.id}`](
          dataItem[extendDataWith],
          {...element.templateData, data: dataItem},
        );
    }

    console.log('Called onElementParseData for element >', element.elementId);
    return dataItem;
  };
  element.onTemplateRender = function() {
    let onTemplateRender;

    // Check if onTemplateRender for child template is isExtended
    // And onTemplateRender is defined on child, then use it
    // Else, use parent onTemplateRender
    if (element.isExtended && typeof window[
      `onTemplateRender_${element.templateData.id}`
    ] === 'function') {
      onTemplateRender = window[`onTemplateRender_${element.templateData.id}`];
    } else if (element.isExtended && typeof window [
      `onTemplateRender_${element.dataOverride}`
    ] === 'function') {
      onTemplateRender = window[`onTemplateRender_${element.dataOverride}`];
    } else if (!element.isExtended) {
      onTemplateRender = window[`onTemplateRender_${element.templateData.id}`];
    }

    console.log('Called onTemplateRender for element >', element.elementId);

    return onTemplateRender;
  };
};

XiboPlayer.prototype.isStaticWidget = function(playerWidget) {
  return playerWidget !== undefined && playerWidget !== null &&
    playerWidget.templateId !== 'elements' &&
    this.inputElements.length === 0;
};

XiboPlayer.prototype.isModule = function(currentWidget) {
  return !currentWidget.isDataExpected && $('#hbs-module').length > 0;
};

/**
 * Caller function for onTemplateDataLoad
 * @param {Object} params
 * @return {Object} State to determine next step. E.g. {handled: false}
 */
XiboPlayer.prototype.onTemplateDataLoad = function(params) {
  let onTemplateDataLoad = null;
  // onTemplateDataLoad function should be checked and run first before
  if (typeof window['onTemplateDataLoad_' + params.widgetId] ===
    'function') {
    onTemplateDataLoad =
      window['onTemplateDataLoad_' + params.widgetId];
  }

  let onTemplateDataLoadRes = {handled: false};

  if (onTemplateDataLoad) {
    const onTemplateDataLoadResult = onTemplateDataLoad(params.widgetId);

    if (onTemplateDataLoadResult !== undefined &&
      Object.keys(onTemplateDataLoadResult).length > 0
    ) {
      if ((onTemplateDataLoadResult ?? {}).hasOwnProperty('handled')) {
        onTemplateDataLoadRes = {
          ...onTemplateDataLoadRes,
          handled: onTemplateDataLoadResult.handled,
        };
      } else {
        onTemplateDataLoadRes = {
          ...onTemplateDataLoadResult,
          ...onTemplateDataLoadRes,
        };
      }
    }
  }

  return onTemplateDataLoadRes;
};

/**
 * Caller function for onDataLoad
 * @param {Object} params
 * @return {Object} State to determine next step.
 * E.g. {handled: false, dataItems: []}
 */
XiboPlayer.prototype.onDataLoad = function(params) {
  let onDataLoad = null;
  if (typeof window['onDataLoad_' + params.widgetId] === 'function') {
    // onDataLoad callback function is currently not returning any state
    // that can be used to identify what to do next
    onDataLoad = window['onDataLoad_' + params.widgetId];
  }

  let onDataLoadResponse = {handled: false, dataItems: params.dataItems ?? []};

  if (onDataLoad) {
    const onDataLoadResult = onDataLoad(
      params.dataItems,
      params.meta,
      params.properties,
    );

    if (onDataLoadResult !== undefined &&
        Object.keys(onDataLoadResult).length > 0
    ) {
      if ((onDataLoadResult ?? {}).hasOwnProperty('handled')) {
        onDataLoadResponse = {
          ...onDataLoadResponse,
          handled: onDataLoadResult.handled,
        };
      }

      if ((onDataLoadResult ?? {}).hasOwnProperty('dataItems')) {
        onDataLoadResponse = {
          ...onDataLoadResponse,
          dataItems: onDataLoadResult.dataItems,
        };
      }
    }
  }

  return onDataLoadResponse;
};

/**
 * Caller function for onParseData
 * @param {Object} currentWidget Widget object
 * @param {Array} widgetDataItems Widget data items
 * @return {Array} Widget data items
 */
XiboPlayer.prototype.onParseData = function(
  currentWidget,
  widgetDataItems,
) {
  const dataItems = widgetDataItems ?? [];
  // Parse the widgetDataItems if there is a parser function for the module
  if (typeof window['onParseData_' + currentWidget.widgetId] === 'function') {
    widgetDataItems.forEach(function(dataItem, _dataKey) {
      dataItems[_dataKey] =
        window['onParseData_' + currentWidget.widgetId](
          dataItem,
          currentWidget.properties,
          currentWidget.meta,
        );
    });
  }

  return dataItems;
};

/**
 * Caller function for onTemplateRender method
 * @param {Object} params onTemplateRender parameters
 * @param {Object?} currentWidget Optional widget object parameter
 * to get updated widget
 * @return {Object} State to determine next step. E.g. {handled: false}
 */
XiboPlayer.prototype.onTemplateRender = function(params, currentWidget) {
  // Handle the rendering of the template
  if (
    typeof window['onTemplateRender_' + params.templateId] === 'function'
  ) { // Custom scaler
    window.onTemplateRender =
      window['onTemplateRender_' + params.templateId];
  }

  let onTemplateRender = null;
  // Template render function
  if (window.onTemplateRender) {
    onTemplateRender = window.onTemplateRender;
    // Save the render method in renders
    window.renders.push(window.onTemplateRender);
  }

  let onTemplateRenderResponse = {handled: false};
  if (onTemplateRender) {
    const onTemplateRenderResult = onTemplateRender(
      params.widgetId,
      params.target,
      currentWidget ? currentWidget.items : params.items,
      params.rendering,
      params.meta,
    );
    console.log('Called onTemplateRender for widget > ', params.widgetId);

    if (onTemplateRenderResult !== undefined &&
      Object.keys(onTemplateRenderResult).length > 0
    ) {
      if ((onTemplateRenderResult ?? {}).hasOwnProperty('handled')) {
        onTemplateRenderResponse = {
          ...onTemplateRenderResponse,
          handled: onTemplateRenderResult.handled,
        };
      }
    }
  }

  return onTemplateRenderResponse;
};

/**
 * Caller function for onRender method
 * @param {Object} params
 */
XiboPlayer.prototype.onRender = function(params) {
  // Run the onRender function if it exists
  if (typeof window['onRender_' + params.widgetId] === 'function') {
    window.onRender = window['onRender_' + params.widgetId];
  }

  if (window.onRender) {
    // Save the render method in renders
    window.renders.push(window.onRender);

    // Run render function
    window.onRender(
      params.widgetId,
      params.target,
      params.items,
      params.rendering,
      params.meta,
    );
    console.log('Called onRender for widget > ', params.widgetId);
  }
};

/**
 * Caller function for onTemplateVisible
 * @param {Object} params
 * @return {Object} State to determine next step. E.g. {handled: false}
 */
XiboPlayer.prototype.onTemplateVisible = function(params) {
  let templateVisibleResponse = {handled: false};
  // Call the run on template visible function if it exists
  if (
    typeof window['onTemplateVisible_' + params.templateId] === 'function'
  ) {
    const onTemplateVisible = window['onTemplateVisible_' + params.templateId];
    window.runOnTemplateVisible = function() {
      const onTemplateVisibleResult = onTemplateVisible(
        params.widgetId,
        params.target,
        params.items,
        params.rendering,
        params.meta,
      );
      console.log('Called onTemplateVisible for widget > ', params.widgetId);

      if (onTemplateVisibleResult !== undefined &&
        Object.keys(onTemplateVisibleResult).length > 0
      ) {
        if ((onTemplateVisibleResult ?? {}).hasOwnProperty('handled')) {
          templateVisibleResponse = {
            ...templateVisibleResponse,
            handled: onTemplateVisibleResult.handled,
          };
        }
      }

      return templateVisibleResponse;
    };

    return window.runOnTemplateVisible();
  }

  return templateVisibleResponse;
};

/**
 * Caller function for onVisible
 * @param {Object} params
 */
XiboPlayer.prototype.onVisible = function(params) {
  // Call the run on visible function if it exists
  if (
    typeof window['onVisible_' + params.widgetId] === 'function'
  ) {
    window.runOnVisible = function() {
      window['onVisible_' + params.widgetId](
        params.widgetId,
        params.target,
        params.items,
        params.rendering,
        params.meta,
      );
      console.log('Called onVisible for widget > ', params.widgetId);
    };

    window.runOnVisible();
  }
};

XiboPlayer.prototype.saveTemplateDimensions = function($template) {
  if ($template && $template.length > 0) {
    $template.data('width') &&
    (globalOptions.widgetDesignWidth = $template.data('width'));
    $template.data('height') &&
    (globalOptions.widgetDesignHeight = $template.data('height'));
    $template.data('gap') &&
    (globalOptions.widgetDesignGap = $template.data('gap'));
  }
};

XiboPlayer.prototype.getRenderParams = function(
  currentWidget,
  globalOptions,
  options,
) {
  // Options for the render functions
  const optionsForRendering = Object.assign(
    currentWidget.properties,
    globalOptions,
    {
      duration: currentWidget.duration,
      pauseEffectOnStart: globalOptions.pauseEffectOnStart ?? false,
      isPreview: currentWidget.isPreview,
      isEditor: currentWidget.isEditor,
    },
  );

  return {
    templateId: currentWidget.templateId,
    widgetId: currentWidget.widgetId,
    target: options.target,
    items: currentWidget.items,
    rendering: optionsForRendering,
    properties: currentWidget.properties,
    meta: currentWidget.meta,
  };
};

/**
 * Runs xiboLayoutScaler
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.runLayoutScaler = function(currentWidget) {
  // Run xiboLayoutScaler once to scale the content
  $('#content').xiboLayoutScaler(Object.assign(
    currentWidget.properties,
    globalOptions,
    {duration: currentWidget.duration},
  ));
};

const xiboPlayer = new XiboPlayer();

module.exports = xiboPlayer;

$(function() {
  xiboPlayer.init(widgetData, elements);
});
