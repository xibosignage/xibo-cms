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
  this.urlParams = new URLSearchParams(window.location.search);

  /**
   * Get widget data
   * @param {Object} currentWidget
   * @return {Promise<unknown>}
   */
  this.getWidgetData = function(currentWidget) {
    const self = this;
    return new Promise(function(resolve, reject) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      if (currentWidget.url !== null) {
        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax({
          method: 'GET',
          url: currentWidget.url,
        }).done(function(data) {
          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          self.onDataErrorCallback(
            jqXHR.status,
            jqXHR.responseJSON,
          );
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
   * Get all widgets data
   * @param {Array} widgetList
   * @return {Promise<unknown>}
   */
  this.getAllWidgetsData = function(widgetList) {
    const self = this;
    return new Promise(function(resolve) {
      Promise.all(widgetList.map(function(widgetItem) {
        return self.getWidgetData(widgetItem);
      })).then(function(values) {
        resolve(values);
      });
    });
  };

  /**
   * Compose Player Widget
   * @param {Object} inputWidget
   * @param {Object|Array} data
   * @return {Object} playerWidget
   */
  this.playerWidget = function(inputWidget, data) {
    const self = this;
    let playerWidget = inputWidget;
    // Initialize widget properties
    if (!playerWidget.hasOwnProperty('isDataExpected')) {
      playerWidget.isDataExpected = playerWidget.url !== null;
    }

    const {dataItems, showError, errorMessage} =
      this.composeWidgetData(playerWidget, data);

    // Parse the dataItems if there is a parser function for the module
    if (typeof window['onParseData_' + playerWidget.widgetId] === 'function') {
      dataItems.forEach(function(dataItem, _dataKey) {
        dataItems[_dataKey] = window['onParseData_' + playerWidget.widgetId](
          dataItem,
          playerWidget.properties,
          playerWidget.meta,
        );
      });
    }

    playerWidget.data = dataItems;
    playerWidget.meta = data !== null ? data?.meta : {};
    playerWidget.showError = showError;
    playerWidget.errorMessage = errorMessage;
    playerWidget.items = [];

    // Only add below props for widget with elements
    if (this.isStaticWidget(playerWidget) === false) {
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
          PlayerHelper.decorateCollectionSlots(dataItems, playerWidget);
      }
    }

    playerWidget.render = function(shouldRefresh = false) {
      self.renderWidget(playerWidget, shouldRefresh);
    };

    return playerWidget;
  };

  /**
   * Compose widget elements
   * @param {Array} widgetElements
   * @param {Object} currentWidget
   * @return {Object} {standalone: {}, groups: {}}
   */
  this.composeElements = function(widgetElements, currentWidget) {
    if (!widgetElements || widgetElements.length === 0) {
      return widgetElements;
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
    if (currentWidget.templateId !== null && currentWidget.url !== null) {
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
    return element.hasOwnProperty('groupId');
  };
};

XiboPlayer.prototype.init = function(widgetData, elements) {
  const self = this;

  self.inputWidgetData = widgetData;
  self.inputElements = elements;

  self.getAllWidgetsData(self.inputWidgetData).then(function(values) {
    if (self.noDataWidgets(values).length > 0) {
      self.onDataErrorCallback(404, self.inputWidgetData);
    }

    values.forEach(function(value, widgetIndex) {
      const currentWidget =
        self.playerWidget(self.inputWidgetData[widgetIndex], value);
      self.playerWidgets[currentWidget.widgetId] = currentWidget;
    });

    self.renderWidgets();
  });
};

XiboPlayer.prototype.isPreview = function() {
  return this.urlParams.get('preview') === '1';
};

XiboPlayer.prototype.isEditor = function() {
  return this.urlParams.get('isEditor') === '1';
};

XiboPlayer.prototype.noDataWidgets = function(dataCollection) {
  return dataCollection.filter((val) => {
    return val !== null &&
      (val.hasOwnProperty('success') ||
        val.hasOwnProperty('error') ||
        (val.hasOwnProperty('data') && val?.data?.length === 0)
      );
  });
};

/**
 * Compose widget data
 * @param {Object} currentWidget
 * @param {Object|Array} data
 * @return {Object} widgetData
 */
XiboPlayer.prototype.composeWidgetData = function(currentWidget, data) {
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

XiboPlayer.prototype.onDataErrorCallback = function(httpStatus, response) {
  const onDataError = window[`onDataError_${xiboICTargetId}`];

  if (typeof onDataError === 'function') {
    if (onDataError(httpStatus, response) == false) {
      xiboIC.reportFault({
        code: '5001',
        reason: 'No Data',
      }, {targetId: xiboICTargetId});
      xiboIC.expireNow({targetId: xiboICTargetId});
    }

    onDataError(httpStatus, response);
  } else {
    xiboIC.reportFault({
      code: '5001',
      reason: 'No Data',
    }, {targetId: xiboICTargetId});
    xiboIC.expireNow({targetId: xiboICTargetId});
  }
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

XiboPlayer.prototype.getFreshWidgetData = function(currentWidget, callback) {
  if (!currentWidget) {
    return false;
  }

  const self = this;
  if (typeof callback === 'function') {
    this.getWidgetData(currentWidget).then(function(response) {
      const freshWidget = self.playerWidget(currentWidget, response);

      if (self.playerWidgets.hasOwnProperty(freshWidget.widgetId)) {
        self.playerWidgets[freshWidget.widgetId] = freshWidget;
      }

      callback.apply(self, [freshWidget, true]);
    });
  }
};

XiboPlayer.prototype.renderWidget = function(widgetId, shouldRefresh) {
  let currentWidget = widgetId;

  if (typeof currentWidget === 'number') {
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

XiboPlayer.prototype.renderWidgets = function() {
  const self = this;
  const playerWidgets = this.getPlayerWidgets();

  if (playerWidgets.length > 0) {
    const $content = $('#content');

    playerWidgets.forEach(function(playerWidget) {
      if (self.isStaticWidget(playerWidget)) {
        self.countWidgetStatic++;
      } else {
        self.countWidgetElements++;
      }

      self.renderWidget(playerWidget);

      if (self.countWidgetElements === self.inputWidgetData.length) {
        // Run xiboLayoutScaler to scale the content
        $content.xiboLayoutScaler(Object.assign(
          playerWidget.properties,
          globalOptions,
          {duration: playerWidget.duration},
        ));
      }
    });
  }
};

XiboPlayer.prototype.renderStaticWidget = function(
  staticWidget,
  shouldRefresh,
) {
  const $target = $('body');
  const $content = $('#content');
  const {data, showError, errorMessage} = staticWidget;
  let dataItems = data;

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

  // Run the onInitialize function if it exists
  if (typeof window['onInitialize_' + staticWidget.widgetId] === 'function') {
    window['onInitialize_' + staticWidget.widgetId](
      staticWidget.widgetId,
      $target,
      staticWidget.properties,
      staticWidget.meta,
    );
  }

  // Run the onDataLoad function if it exists
  if (typeof window['onDataLoad_' + staticWidget.widgetId] === 'function') {
    dataItems = window['onDataLoad_' + staticWidget.widgetId](
      dataItems,
      staticWidget.meta,
      staticWidget.properties,
    );
  }

  // Check if we have template from templateId or module
  // and set it as the template
  let $template = null;
  let moduleTemplate = false;
  if ($('#hbs-' + staticWidget.templateId).length > 0) {
    $template = $('#hbs-' + staticWidget.templateId);
  } else if ($('#hbs-module').length > 0) {
    $template = $('#hbs-module');
    moduleTemplate = true;
  }

  // Save widgetData to xic
  xiboIC.set(staticWidget.widgetId, 'widgetData', staticWidget);

  let hbs = null;
  // Compile the template if it exists
  if ($template && $template.length > 0) {
    hbs = Handlebars.compile($template.html());
  }

  // For each data item, parse it and add it to the content
  let templateAlreadyAdded = false;
  $.each(dataItems, function(_key, item) {
    // Parse the data if there is a parser function
    if (typeof window['onParseData_' + staticWidget.widgetId] === 'function') {
      item = window[
        'onParseData_' + staticWidget.widgetId
      ](item, staticWidget.properties, staticWidget.meta);
    }

    // Add the item to the content
    if (hbs) {
      $content.append(hbs(item));

      // IF we added item template
      templateAlreadyAdded = true;
    }

    // Add item to the widget object
    (item) && staticWidget.items.push(item);
  });

  // If we don't have dataType, or we have a module template
  // add it to the content with widget properties and global options
  if (moduleTemplate && hbs && !templateAlreadyAdded) {
    $content.append(hbs(
      Object.assign(staticWidget.properties, globalOptions),
    ));
  }

  // Save template height and width if exists to global options
  if ($template && $template.length > 0) {
    $template.data('width') &&
    (globalOptions.widgetDesignWidth = $template.data('width'));
    $template.data('height') &&
    (globalOptions.widgetDesignHeight = $template.data('height'));
    $template.data('gap') &&
    (globalOptions.widgetDesignGap = $template.data('gap'));
  }

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

  // Run the onRender function if it exists
  if (typeof window['onRender_' + staticWidget.widgetId] === 'function') {
    window.onRender = window['onRender_' + staticWidget.widgetId];
  }

  // Handle the rendering of the template
  if (
    typeof window['onTemplateRender_' + staticWidget.templateId] === 'function'
  ) { // Custom scaler
    window.onTemplateRender =
      window['onTemplateRender_' + staticWidget.templateId];
  }

  // Create global render array of functions
  window.renders = [];
  // Template render function
  if (window.onTemplateRender) {
    // Save the render method in renders
    window.renders.push(window.onTemplateRender);
  }

  // Module render function
  if (window.onRender) {
    // Save the render method in renders
    window.renders.push(window.onRender);
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

  // Options for the render functions
  const optionsForRendering = Object.assign(
    staticWidget.properties,
    globalOptions,
    {
      duration: staticWidget.duration,
      pauseEffectOnStart: globalOptions.pauseEffectOnStart ?? false,
    },
  );

  // Run the render functions
  $.each(window.renders, function(_key, render) {
    render(
      staticWidget.widgetId,
      $target,
      staticWidget.items,
      optionsForRendering,
      staticWidget.meta,
    );
  });

  // Save widget as global variable
  window.widget = staticWidget;

  // Call the run on visible function if it exists
  if (
    typeof window['onVisible_' + staticWidget.widgetId] === 'function'
  ) {
    window.runOnVisible = function() {
      window['onVisible_' + staticWidget.widgetId](
        staticWidget.widgetId,
        $target,
        staticWidget.items,
        staticWidget.properties,
        staticWidget.meta,
      );
    };
    if (xiboIC.checkVisible()) {
      window.runOnVisible();
    } else {
      xiboIC.addToQueue(window.runOnVisible);
    }
  }

  // Lock all interactions
  xiboIC.lockAllInteractions();
};

XiboPlayer.prototype.renderWidgetElements = function(
  currentWidget,
  shouldRefresh,
) {
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

  if (Object.keys(widgetElements?.standalone ?? {}).length > 0 ||
    Object.keys(widgetElements?.groups ?? {}).length > 0
  ) {
    if (Object.keys(groupSlotsData).length > 0 &&
      Object.values(groupSlotsData).length > 0
    ) {
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
                  PlayerHelper.renderDataItem(
                    true,
                    dataKey,
                    dataKey === 'empty' ?
                      dataKey : {...(data[dataKey - 1] || {})},
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

    if (Object.keys(standaloneSlotsData).length > 0 &&
      Object.values(standaloneSlotsData).length > 0
    ) {
      const standaloneData = standaloneSlotsData;
      const standaloneElems = widgetElements.standalone;

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
                      PlayerHelper.renderDataItem(
                        false,
                        dataKey,
                        dataKey === 'empty' ?
                          dataKey : {...(data[dataKey - 1] || {})},
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
          } else {
            // Global elements should fall here but should validate
            if (Object.keys(standaloneElems).length > 0 &&
              standaloneElems.hasOwnProperty(keyValue) &&
              Object.keys(standaloneElems[keyValue]).length > 0
            ) {
              $.each(Object.keys(standaloneElems[keyValue]),
                function(keyIndex, elemKey) {
                  (standaloneElems[keyValue][elemKey].hbs) && $content.append(
                    PlayerHelper.renderElement(
                      standaloneElems[keyValue][elemKey].hbs,
                      standaloneElems[keyValue][elemKey].templateData,
                      true,
                    ),
                  );
                });
            }
          }
        });
    }
  }
};

XiboPlayer.prototype.getPlayerWidgets = function() {
  const playerWidgets = this.playerWidgets;

  if (Object.keys(playerWidgets).length === 0) {
    return [];
  }

  return Object.keys(playerWidgets).reduce(function(a, b) {
    return [...a, playerWidgets[b]];
  }, []);
};

XiboPlayer.prototype.isStaticWidget = function(playerWidget) {
  return playerWidget !== undefined && playerWidget !== null &&
    playerWidget.templateId !== 'elements' &&
    this.inputElements.length === 0;
};

const xiboPlayer = new XiboPlayer();

module.exports = xiboPlayer;

$(function() {
  xiboPlayer.init(widgetData, elements);
});
