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
  this.getWidgetData = async function(currentWidget) {
    // if we are a dataset type, then first check to see if there
    // is realtime data.
    console.debug('getWidgetData: ' + currentWidget.widgetId);
    console.debug('getWidgetData: windowLocation:', window.location);

    let localData;
    if (currentWidget.properties?.dataSetId) {
      await xiboIC.getData(currentWidget.properties?.dataSetId, {
        done: (status, data) => {
          localData = JSON.parse(data);
        },
      });
    }

    return new Promise(function(resolve) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      if (String(currentWidget.url) !== 'null') {
        let ajaxOptions = {
          method: 'GET',
          url: currentWidget.url,
        };

        // We include dataType for ChromeOS player consumer
        if (window.location && window.location.pathname === '/pwa/') {
          ajaxOptions.dataType = 'json';
        }

        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax(ajaxOptions).done(function(data) {
          // The contents of the JSON file will be an object with data and meta
          // add in local data.
          if (localData) {
            data.data = localData;
          }

          let widgetData = data;

          if (typeof widgetData === 'string') {
            widgetData = JSON.parse(data);
          }

          resolve({
            ...widgetData,
            isDataReady: true,
          });
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
          resolve({
            isDataReady: false,
            error: jqXHR.status,
            success: false,
            data: jqXHR.responseJSON,
          });
        });
      } else if (currentWidget.data?.data !== undefined) {
        // This happens for v3 players where the data is already
        // added to the HTML
        if (localData) {
          currentWidget.data.data = localData;
        }
        resolve({
          ...currentWidget.data,
          isDataReady: true,
        });
      } else {
        // This should be impossible.
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
    const playerWidget = inputWidget;
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

    playerWidget.isDataReady = data?.isDataReady || false;
    playerWidget.meta = data !== null ? data?.meta : {};
    playerWidget.items = [];

    // Decorate this widget with all applicable functions
    this.loadWidgetFunctions(playerWidget);

    if (isDataWidget) {
      const dataLoadState = playerWidget.onDataLoad(widgetDataItems);
      console.debug('onDataLoad::handled = ', dataLoadState.handled);

      widgetDataItems = dataLoadState.dataItems;

      if (!dataLoadState.handled) {
        widgetDataItems = playerWidget.onParseData(widgetDataItems);
        console.debug('onParseData::widgetDataItems ', widgetDataItems);
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
      );

      this.prepareWidgetElements(tempElements, playerWidget);
    }

    // Useful when re-rendering the widget through the web console
    // parameter "shouldRefresh" defaults to =true to refresh widget data
    playerWidget.render = function(shouldRefresh = true) {
      if (playerWidget.isDataExpected) {
        self.renderWidget(playerWidget, shouldRefresh);
      } else if (self.isModule(playerWidget)) {
        self.renderModule(playerWidget);
      } else {
        self.renderGlobalElements(playerWidget);
      }
    };

    return playerWidget;
  };

  /**
   * Prepare widget elements (data and global)
   * @param {Array} widgetElements
   * @param {Object} currentWidget
   * @return {Object} currentWidget
   */
  this.prepareWidgetElements = function(widgetElements, currentWidget) {
    const transformedElems =
        this.composeElements(widgetElements, currentWidget);

    if (currentWidget.isDataExpected && widgetElements.length > 0) {
      const {minSlot, maxSlot} =
          PlayerHelper.getMinAndMaxSlot(Object.values(transformedElems));
      // Compose data elements slots
      currentWidget.maxSlot = maxSlot;
      currentWidget.elements = transformedElems;
      currentWidget.metaElements = this.composeMetaElements(transformedElems);
      currentWidget.dataElements =
          this.initSlots(transformedElems, minSlot, maxSlot);
      currentWidget.pinnedSlots =
        PlayerHelper.getPinnedSlots(currentWidget.dataElements);

      this.composeDataSlots(currentWidget);
      this.composeRNRData(currentWidget);
    } else {
      // These are global elements
      currentWidget.globalElements = transformedElems;
    }

    return currentWidget;
  };

  /**
   * Define widget functions used for render flow
   * @param {Object} playerWidget Widget object
   */
  this.loadWidgetFunctions = function(playerWidget) {
    const self = this;
    const params = this.getRenderParams(
      playerWidget,
      {target: $('body')},
      globalOptions,
    );

    playerWidget.onDataLoad = function(widgetDataItems) {
      return self.onDataLoad({
        widgetId: playerWidget.widgetId,
        dataItems: widgetDataItems,
        meta: playerWidget.meta,
        properties: playerWidget.properties,
        isDataReady: playerWidget.isDataReady,
      });
    };
    playerWidget.onParseData = function(widgetDataItems) {
      return self.onParseData(playerWidget, widgetDataItems);
    };
    playerWidget.onTemplateRender = function(currentWidget, options) {
      return self.onTemplateRender(
        options ? {...params, ...options} : params,
        currentWidget,
      );
    };
    playerWidget.onRender = function(staticWidget, options) {
      // We use staticWidget and options parameter to get updated parameters
      // after loading these functions
      const onRenderParams = options ? {...params, ...options} : params;

      return self.onRender({
        ...onRenderParams,
        items: staticWidget ? staticWidget.items : params.items,
      });
    };
    playerWidget.onTemplateVisible = function(options) {
      return self.onTemplateVisible(options ? {...params, ...options} : params);
    };
    playerWidget.onVisible = function(options) {
      return self.onVisible(options ? {...params, ...options} : params);
    };
  };

  /**
   * Compose widget elements
   * @param {Array} widgetElements Widget elements
   * @param {Object} currentWidget Widget object
   * @return {Object}
   */
  this.composeElements = function(widgetElements, currentWidget) {
    const self = this;
    return widgetElements.reduce(function(collection, widgetElement) {
      const grpId = widgetElement.groupId;
      const hasGroup = Boolean(grpId);

      // If element isn't visible, skip
      if (widgetElement.isVisible === false) {
        return collection;
      }

      // Check for group
      if (hasGroup) {
        const grpWidgetId = grpId + '_' + currentWidget.widgetId;
        if (!Boolean(collection[grpWidgetId])) {
          const groupProps = {
            ...widgetElement.groupProperties,
            groupId: widgetElement.groupId,
            groupScale: widgetElement.groupScale,
            slot: widgetElement.slot ?? undefined,
            dataKeys: [],
            items: [],
            duration: currentWidget.duration,
            durationIsPerItem:
              Boolean(currentWidget.properties.durationIsPerItem),
          };
          collection[grpWidgetId] = groupProps;
          collection[grpWidgetId].onTemplateVisible = function($target) {
            self.runLayoutAnimate($target, groupProps);
            console.debug('Called onTemplateVisible for group > ', grpWidgetId);
          };
        }

        if (Boolean(collection[grpWidgetId])) {
          collection[grpWidgetId].items.push(
            self.decorateElement(widgetElement, currentWidget),
          );
        }
      } else {
        const elemWidgetId =
          widgetElement.elementId + '_' + currentWidget.widgetId;

        if (!Boolean(collection[elemWidgetId])) {
          collection[elemWidgetId] =
            self.decorateElement({...widgetElement}, currentWidget);
        }
      }

      return collection;
    }, {});
  };

  /**
   * Compose elements that has data from meta
   * @param {Object} transformedElems Elements collection
   * @return {Object} metaElements
   * */
  this.composeMetaElements = function(transformedElems) {
    let metaElements = {};

    if (Object.entries(transformedElems).length > 0) {
      metaElements = Object.keys(transformedElems).reduce((a, b) => {
        const metaItem = transformedElems[b];
        if (metaItem.dataInMeta) {
          a[b] = metaItem;
        }

        return a;
      }, {});
    }

    return metaElements;
  };

  /**
   * Initialize slots
   * @param {Object} collection Data elements
   * @param {Number} minSlot
   * @param {Number} maxSlot
   * @return {*}
   */
  this.initSlots = function(collection, minSlot, maxSlot) {
    if (minSlot === 0) {
      return minSlot;
    }

    const dataSlots =
      [...Array(maxSlot).keys()].reduce(function(slots, slot) {
        slots[slot + 1] = {
          items: {},
          hasPinnedSlot: false,
          dataKeys: [],
          slot: slot + 1,
        };

        return slots;
      }, {});

    if (Object.values(dataSlots).length > 0 &&
      Object.values(collection).length > 0
    ) {
      for (const [itemKey, currentItem] of Object.entries(collection)) {
        // Skip item if dataInMeta = true
        if (currentItem.dataInMeta) {
          continue;
        }

        const currentSlot = currentItem.slot + 1;
        if (Boolean(dataSlots[currentSlot])) {
          dataSlots[currentSlot].items[itemKey] = currentItem;
          dataSlots[currentSlot].hasGroup = Boolean(currentItem.groupId);
          dataSlots[currentSlot].hasPinnedSlot =
            Object.keys(dataSlots[currentSlot].items).filter(function(k) {
              return dataSlots[currentSlot].items[k].pinSlot === true;
            }).length > 0;
          dataSlots[currentSlot].pinnedItems =
            PlayerHelper.getPinnedItems(dataSlots[currentSlot].items);
        }
      }
    }

    return dataSlots;
  };

  /**
   * Compose widget data slots
   * @param {Object} currentWidget
   */
  this.composeDataSlots = function(currentWidget) {
    const {
      data,
      maxSlot,
      dataElements,
      pinnedSlots,
    } = currentWidget;

    if (data.length > 0) {
      let lastSlotFilled = null;
      const filledPinnedSlot = [];

      dataLoop: for (const [dataItemKey] of Object.entries(data)) {
        let hasSlotFilled = false;
        const currentKey = parseInt(dataItemKey) + 1;
        const currCollection = Object.keys(dataElements);

        // Stop iteration through data when all pinned slots are filled
        // and maxSlot = pinnedSlots.length
        if (lastSlotFilled === null &&
          pinnedSlots.length === maxSlot &&
          currentKey > maxSlot
        ) {
          break;
        }

        // Slots loop
        for (const [, itemValue] of Object.entries(currCollection)) {
          const itemObj = dataElements[itemValue];
          const slotItems = itemObj.items;
          const pinnedItems = itemObj.pinnedItems;
          const currentSlot = itemObj.slot;
          let nextSlot = currentSlot + 1;

          if (nextSlot > maxSlot) {
            nextSlot = currentSlot;
          }

          // Skip if currentKey is less than the currentSlot
          // This occurs when a data slot has been skipped
          // E.g. dataSlots = [2, 3]
          if (currentKey < currentSlot) {
            continue dataLoop;
          }

          // If lastSlotFilled is filled and is <= to currentSlot
          // Then, move to next slot
          if (lastSlotFilled !== null &&
            currentSlot <= lastSlotFilled
          ) {
            continue;
          }

          // Skip slot if all slot items are pinned and
          // currentKey is more than the maxSlot and
          // currentSlot is a pinned slot
          if (lastSlotFilled === null &&
            currentKey > maxSlot && itemObj.hasPinnedSlot &&
            Object.keys(pinnedItems).length === Object.keys(slotItems).length
          ) {
            continue;
          }

          // Loop through data slot items (elements or groups)
          for (const [dataSlotItemKey] of Object.entries(slotItems)) {
            const dataSlotItem = itemObj.items[dataSlotItemKey];
            const isPinnedSlot = dataSlotItem.pinSlot;

            if (isPinnedSlot) {
              if (currentKey !== currentSlot) {
                hasSlotFilled = true;
                lastSlotFilled = currentSlot;
                continue;
              }

              if (!dataSlotItem.dataKeys.includes(currentKey)) {
                dataSlotItem.dataKeys = [
                  ...dataSlotItem.dataKeys,
                  currentKey,
                ];
              }
            } else {
              dataSlotItem.dataKeys = [
                ...dataSlotItem.dataKeys,
                currentKey,
              ];
            }

            hasSlotFilled = true;
            lastSlotFilled = currentSlot;
          }

          if (pinnedSlots.includes(currentSlot) &&
            lastSlotFilled === currentSlot &&
            !filledPinnedSlot.includes(currentSlot)
          ) {
            filledPinnedSlot.push(currentSlot);
          }

          itemObj.dataKeys = [
            ...itemObj.dataKeys,
            currentKey,
          ];

          if (hasSlotFilled) {
            hasSlotFilled = false;
            if (lastSlotFilled % maxSlot === 0) {
              lastSlotFilled = null;
            } else if (currentKey > maxSlot &&
                nextSlot !== currentSlot &&
                pinnedSlots.includes(nextSlot) &&
                filledPinnedSlot.includes(nextSlot)
            ) {
              // Next slot is a pinned slot and has been filled
              // So, current item must be passed to next non-pinned slot
              if (nextSlot === maxSlot) {
                lastSlotFilled = null;
              } else {
                lastSlotFilled = nextSlot;
              }
            }

            break;
          }
        }
      }
    }
  };

  /**
   * Compose repeat and non-repeat data
   * @param {Object} currentWidget
   */
  this.composeRNRData = function(currentWidget) {
    const {dataElements, pinnedSlots, isRepeatData} = currentWidget;
    // Copy data elements slots
    const groupSlotsData = {...dataElements};

    const dataCounts = Object.keys(groupSlotsData).reduce((a, b) => {
      a[b] = groupSlotsData[b].dataKeys.length;
      return a;
    }, {});
    const maxCount = Math.max(
      ...(Object.values(dataCounts).map((count) => Number(count))));
    const minCount = Math.min(
      ...(Object.values(dataCounts).map((count) => Number(count))));

    if (minCount < maxCount) {
      const nonPinnedDataKeys =
          Object.values(groupSlotsData).reduce((a, b) => {
            if (!b.hasPinnedSlot) {
              a = [...a, ...(b.dataKeys)];
            } else {
              if (b.dataKeys.length > 1) {
                b.dataKeys.forEach(function(dataKey) {
                  if (!pinnedSlots.includes(dataKey)) {
                    a = [...a, dataKey];
                  }
                });
              }
            }

            return a;
          }, []).sort((a, b) => {
            if (a < b) return -1;
            if (a > b) return 1;
            return 0;
          });

      Object.keys(groupSlotsData).forEach(function(slotIndex, slotKey) {
        const dataCount = dataCounts[slotIndex];
        if (dataCount < maxCount) {
          const countDiff = maxCount - dataCount;
          if (countDiff === 1) {
            const poppedKey = nonPinnedDataKeys.shift();
            dataElements[slotIndex].dataKeys.push(
              isRepeatData ? poppedKey : 'empty');

            // Update data keys of each data slot items
            if (Object.keys(dataElements[slotIndex].items).length > 0) {
              Object.keys(dataElements[slotIndex].items).forEach(function(k) {
                if (!dataElements[slotIndex].items[k].pinSlot) {
                  dataElements[slotIndex].items[k].dataKeys.push(
                    isRepeatData ? poppedKey : 'empty');
                }
              });
            }
          }
        }
      });
    }

    currentWidget.dataElements = dataElements;
  };

  /**
   * Parse single element for extended properties
   * @param {Object} element Element object
   * @param {Object} currentWidget Widget object
   * @return {Object} element
   */
  this.decorateElement = function(element, currentWidget) {
    const self = this;
    const elemCopy = JSON.parse(JSON.stringify(element));
    const elemProps = elemCopy?.properties || {};

    // Initialize element data keys
    elemCopy.dataKeys = [];

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

    elemCopy.templateData = Object.assign(
      {}, elemCopy, elemProps, globalOptions,
      {uniqueID: elemCopy.elementId, prop: {...elemCopy, ...elemProps}},
    );

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

    // Duration
    elemCopy.duration = currentWidget.duration;
    elemCopy.durationIsPerItem =
      Boolean(currentWidget.properties.durationIsPerItem);

    // Check if element is extended and data is coming from meta
    if (elemCopy.isExtended && elemCopy.dataOverrideWith !== null &&
        elemCopy.dataOverrideWith.includes('meta')) {
      elemCopy.dataInMeta = true;
    }

    // Add onTemplateVisible if element does not belong to a group
    if (!self.isGroup(elemCopy)) {
      elemCopy.onTemplateVisible = function($target) {
        self.runLayoutAnimate($target, elemCopy);
        console.debug('Called onTemplateVisible for element > ',
          elemCopy.elementId);
      };
    }

    return elemCopy;
  };

  this.isGroup = function(element) {
    return Boolean(element.groupId);
  };
};

/**
 * Initializes player widgets, accepting inputs from HTML output
 */
XiboPlayer.prototype.init = function() {
  const self = this;
  let calledXiboScaler = false;

  // Create global render array of functions
  window.renders = [];

  // If we have scoped styles for elements
  // convert the CSS rules to use it
  $(
    'style[data-style-scope][data-style-target="element"]',
  ).each((_idx, styleEl) => {
    const scopeName = $(styleEl).data('style-scope');
    const styleContent = $(styleEl).html();

    function scopeCSS(css, scope) {
      return css
        .split('}')
        .map((rule) => rule.trim() ? `${scope} ${rule.trim()}}` : '')
        .join('\n')
        .trim();
    }

    $(styleEl).html(
      scopeCSS(
        styleContent,
        '[data-style-scope="' + scopeName + '"]',
      ),
    );
  });

  // Loop through each widget from widgetData
  if (widgetData.length > 0) {
    widgetData.forEach(function(inputWidget, widgetIndex) {
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
        console.debug(
          'Called onInitialize for widget > ',
          inputWidget.widgetId,
        );
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
            console.debug('Data Widget::Static Template');
            self.countWidgetStatic++;
          } else {
            console.debug('Data Widget::Elements');
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

        // Handle real-time data/dataset
        if (inputWidget.properties?.dataSetId) {
          xiboIC.registerNotifyDataListener((dataKey) => {
            // Loose match.
            if (dataKey == inputWidget.properties?.dataSetId) {
              inputWidget.render();
            }
          });
        }
      } else if (self.isModule(inputWidget)) { // It's a module
        console.debug('Non-data Widget::Module');
        const currentWidget = self.playerWidget(
          inputWidget,
          [],
          false,
        );
        self.playerWidgets[inputWidget.widgetId] = currentWidget;

        self.renderModule(currentWidget);
      } else { // All global elements goes here
        console.debug('Non-data Widget::Global Elements');
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

    // Lock all interactions
    xiboIC.lockAllInteractions();
  }
};

XiboPlayer.prototype.isPreview = function() {
  return this.urlParams.get('preview') === '1';
};

XiboPlayer.prototype.isEditor = function() {
  return this.urlParams.get('isEditor') === '1';
};

/**
 * Show sample data or an error if in the editor.
 * @param {Object} currentWidget Widget object
 * @param {Object|Array} data Widget data from data provider
 * @return {Object} widgetLoadedData
 */
XiboPlayer.prototype.loadData = function(currentWidget, data) {
  const self = this;
  const widgetLoadedData = {
    isSampleData: false,
    dataItems: [],
    isArray: Array.isArray(data?.data),
    showError: false,
    errorMessage: null,
  };
  const composeSampleData = () => {
    widgetLoadedData.isSampleData = true;

    if (currentWidget.sample === null) {
      widgetLoadedData.dataItems = [];
      return [];
    }

    // If data is empty, use sample data instead
    // Add single element or array of elements
    widgetLoadedData.dataItems = (Array.isArray(currentWidget.sample)) ?
      currentWidget.sample.slice(0) :
      [currentWidget.sample];

    return widgetLoadedData.dataItems.reduce(function(data, item) {
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
    if (widgetLoadedData.isArray && data?.data?.length > 0) {
      widgetLoadedData.dataItems = data?.data;
    } else {
      widgetLoadedData.dataItems = self.isEditor() ? composeSampleData() : [];
      if (data?.success === false || !currentWidget.isValid) {
        widgetLoadedData.showError = self.isEditor();
      }
    }
  }

  if (widgetLoadedData.showError && data?.message) {
    widgetLoadedData.errorMessage = data?.message;
  }

  return widgetLoadedData;
};

XiboPlayer.prototype.getElementsByWidgetId = function(widgetId) {
  let widgetElements = [];
  const _inputElements = elements;

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
      this.getFreshWidgetData(currentWidget, this.renderDataElements);
    } else {
      this.renderDataElements(currentWidget);
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

  // Expire if the data is not ready
  // TODO: once we have a mechanism to refresh widget data in 4.1,
  //  we won't need this anymore
  if (!staticWidget.isDataReady) {
    // eslint-disable-next-line max-len
    console.error('renderStaticWidget: static widget where isDataReady:false, expiring in 1500ms');
    setTimeout(() => xiboIC.expireNow({targetId: xiboICTargetId}), 1500);
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
  } else if ($('#hbs-module').length > 0) {
    // Dashboard module is using this template
    $template = $('#hbs-module');
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

  // Updated params for rendering
  const optionsForRendering = {
    rendering: this.renderOptions(staticWidget, globalOptions),
  };

  const templateRenderState = staticWidget.onTemplateRender(
    staticWidget,
    optionsForRendering,
  );

  if (!templateRenderState.handled) {
    // Run module onRender function
    staticWidget.onRender(staticWidget, optionsForRendering);
  }

  const onVisibleMethods = function() {
    const templateVisibleState =
      staticWidget.onTemplateVisible(optionsForRendering);

    if (!templateVisibleState.handled) {
      staticWidget.onVisible(optionsForRendering);
    }
  };

  // Check for visibility
  if (xiboIC.checkVisible()) {
    onVisibleMethods();
  } else {
    xiboIC.addToQueue(onVisibleMethods);
  }

  console.debug(
    '<<<END>>> renderStaticWidget for widget >', staticWidget.widgetId);
};

/**
 * Renders widget elements
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.renderDataElements = function(currentWidget) {
  const self = this;
  const {
    data,
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

  // New implementation of widget elements rendering
  if (currentWidget.dataElements && Object.values(currentWidget.dataElements)) {
    // Loop through data slot of elements
    Object.keys(currentWidget.dataElements).forEach(function(slotKey) {
      const slotObj = currentWidget.dataElements[slotKey];
      const dataKeys = slotObj.dataKeys;

      if (Object.keys(slotObj.items).length > 0) {
        Object.keys(slotObj.items).forEach(function(itemKey) {
          const slotObjItem = slotObj.items[itemKey];
          const isGroup = Boolean(slotObjItem.groupId);
          const $slotItemContent = $(`<div class="${itemKey}"></div>`);
          const isMarquee = PlayerHelper.isMarquee(slotObjItem?.efffect);

          for (const [, dataKey] of Object.entries(dataKeys)) {
            // If currentKey(dataKey) does not belong to current item
            // Then, skip it
            if (!slotObjItem.dataKeys.includes(dataKey)) {
              continue;
            }

            if (isGroup) {
              // Check group items
              if (slotObjItem.items.length > 0) {
                // Loop through group items
                slotObjItem.items.forEach(function(groupItem) {
                  // Load element functions
                  self.loadElementFunctions(groupItem, dataKey === 'empty' ?
                    dataKey : {...(data[dataKey - 1] || {})});

                  PlayerHelper.renderDataItem(
                    isGroup,
                    dataKey,
                    groupItem.onElementParseData(dataKey === 'empty' ?
                      dataKey : {...(data[dataKey - 1] || {})},
                    ),
                    groupItem,
                    slotKey,
                    currentWidget.maxSlot,
                    groupItem.pinSlot,
                    currentWidget.pinnedSlots,
                    itemKey,
                    $slotItemContent,
                    {...slotObjItem, isMarquee},
                    meta,
                    $content,
                  );
                });
              }
            } else {
              // Load element functions
              self.loadElementFunctions(slotObjItem, dataKey === 'empty' ?
                dataKey : {...(data[dataKey - 1] || {})});

              PlayerHelper.renderDataItem(
                isGroup,
                dataKey,
                slotObjItem.onElementParseData(dataKey === 'empty' ?
                  dataKey : {...(data[dataKey - 1] || {})},
                ),
                slotObjItem,
                slotKey,
                currentWidget.maxSlot,
                slotObjItem.pinSlot,
                currentWidget.pinnedSlots,
                itemKey,
                $slotItemContent,
                {...slotObjItem, isMarquee},
                meta,
                $content,
              );
            }
          }

          self.postRenderDataElements(
            $slotItemContent,
            slotObjItem,
            isMarquee,
            itemKey,
            isGroup,
            $content,
            currentWidget,
            data,
          );
        });
      }
    });
  }

  // Render data elements from meta data
  if (currentWidget.metaElements &&
    Object.entries(currentWidget.metaElements).length > 0
  ) {
    Object.keys(currentWidget.metaElements).forEach(function(itemKey, slotKey) {
      const slotObjItem = currentWidget.metaElements[itemKey];
      const $slotItemContent = $(`<div class="${itemKey}"></div>`);
      const isMarquee = PlayerHelper.isMarquee(slotObjItem?.efffect);

      // Load element functions
      self.loadElementFunctions(slotObjItem, meta);

      PlayerHelper.renderDataItem(
        false,
        slotObjItem.id,
        slotObjItem.onElementParseData(meta),
        slotObjItem,
        slotKey,
        currentWidget.maxSlot,
        slotObjItem.pinSlot,
        currentWidget.pinnedSlots,
        itemKey,
        $slotItemContent,
        {...slotObjItem, isMarquee},
        meta,
        $content,
      );

      self.postRenderDataElements(
        $slotItemContent,
        slotObjItem,
        isMarquee,
        itemKey,
        false,
        $content,
        currentWidget,
        data,
      );
    });
  }
  // Find and handle any images
  $content.find('img').xiboImageRender();

  // Check if we are visible
  if (xiboIC.checkVisible()) {
    currentWidget.onVisible();
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.debug(
    '<<<END>>> of renderDataElements for widget >', currentWidget.widgetId);
};

XiboPlayer.prototype.postRenderDataElements = function(
  $slotItemContent,
  slotObjItem,
  isMarquee,
  itemKey,
  isGroup,
  $content,
  currentWidget,
  data,
) {
  $slotItemContent.css({
    width: slotObjItem.width,
    height: slotObjItem.height,
    position: 'absolute',
    top: slotObjItem.top,
    left: slotObjItem.left,
    zIndex: slotObjItem.layer,
  });

  if (isMarquee) {
    const $scroller =
      $(`<div class="${itemKey}--marquee scroll"></div>`);

    $scroller.css({
      display: 'flex',
      height: slotObjItem.height,
    });

    if (slotObjItem?.templateData?.verticalAlign) {
      $scroller.css({
        alignItems: slotObjItem?.templateData?.verticalAlign,
      });
    }

    $slotItemContent.wrapInner($scroller.prop('outerHTML'));
  } else {
    if (!isGroup) {
      $slotItemContent.css({
        position: 'absolute',
        top: slotObjItem.top,
        left: slotObjItem.left,
        width: slotObjItem.width,
        height: slotObjItem.height,
        zIndex: slotObjItem.layer,
      });
    }
  }

  // Remove data group element if exists to avoid duplicate
  if ($content.find('.' +
    itemKey + '.cycle-slideshow').length === 1) {
    $content.find('.' +
      itemKey + '.cycle-slideshow').cycle('destroy');
  }
  if ($content.find('.' + itemKey).length === 1) {
    $content.find('.' + itemKey).remove();
  }

  $content.append($slotItemContent);

  $slotItemContent.promise().done(function() {
    $slotItemContent.xiboElementsRender(
      {
        ...slotObjItem,
        itemsPerPage: currentWidget?.maxSlot,
        numItems: data?.length || 0,
        id: itemKey,
        selector: `.${itemKey}`,
      },
      $slotItemContent.find(`.${itemKey}--item`),
    );

    const runOnTemplateVisible = function() {
      slotObjItem.onTemplateVisible($slotItemContent);
    };

    // Run onTemplateVisible by default if visible
    if (xiboIC.checkVisible()) {
      runOnTemplateVisible();
    } else {
      xiboIC.addToQueue(runOnTemplateVisible);
    }

    currentWidget.items.push($slotItemContent);
  });
};

/**
 * Renders widget with global elements
 * @param {Object} currentWidget Widget object
 */
XiboPlayer.prototype.renderGlobalElements = function(currentWidget) {
  const self = this;
  const {globalElements, meta} = currentWidget;
  const $content = $('#content');

  // New implementation for global elements
  if (globalElements && Object.values(globalElements).length > 0) {
    Object.keys(globalElements).forEach(function(itemKey) {
      const elemObj = globalElements[itemKey];
      const isGroup = Boolean(elemObj.groupId);

      if (isGroup) {
        // Grouped elements
        if (elemObj.items.length > 0) {
          // Check if group element exists
          // If not, then create
          let $groupContent;
          if ($content.find(`.${itemKey}`).length === 0) {
            $groupContent = $(`<div class="${itemKey}"></div>`);

            $groupContent.css({
              width: elemObj.width,
              height: elemObj.height,
              position: 'absolute',
              top: elemObj.top,
              left: elemObj.left,
              zIndex: elemObj.layer,
            });
          }

          // Loop through group items
          elemObj.items.forEach(function(groupItem) {
            // Load element functions
            self.loadElementFunctions(groupItem, {});

            if (groupItem.hbs && $groupContent) {
              const $elementContent = $(PlayerHelper.renderElement(
                groupItem.hbs,
                groupItem.templateData,
                true,
              ));

              // Add style scope to container
              const $elementContentContainer = $('<div>');
              $elementContentContainer.append($elementContent).attr(
                'data-style-scope',
                'element_' +
                groupItem.templateData.type + '__' +
                groupItem.templateData.id,
              );

              // Append to main container
              $groupContent.append(
                $elementContentContainer,
              );
            }
          });

          // If there's a group content element
          // Append it to the page
          if ($groupContent) {
            $content.append($groupContent);
          }

          // Invoke groupItem onTemplateRender if present
          Promise.all(Array.from(elemObj.items).map(function(groupItem) {
            const itemID =
            groupItem.uniqueID || groupItem.templateData?.uniqueID;

            // Call onTemplateRender
            // Handle the rendering of the template
            (groupItem.onTemplateRender() !== undefined) &&
            groupItem.onTemplateRender()(
              groupItem.elementId,
              $content.find(`#${itemID}`),
              $content.find(`.${itemID}--item`),
              {groupItem, ...groupItem.templateData, data: {}},
              meta,
            );
          }));
        }
      } else {
        // Single elements
        // Load element functions
        self.loadElementFunctions(elemObj, {});

        if (elemObj.hbs) {
          const $elementContent = $(PlayerHelper.renderElement(
            elemObj.hbs,
            elemObj.templateData,
            true,
          ));

          // Add style scope to container
          const $elementContentContainer = $('<div>');
          $elementContentContainer.append($elementContent).attr(
            'data-style-scope',
            `element_${elemObj.templateData.type}__${elemObj.templateData.id}`,
          );

          // Append to main container
          $content.append(
            $elementContentContainer,
          );
        }

        const itemID =
          elemObj.uniqueID || elemObj.templateData?.uniqueID;

        // Call onTemplateRender
        // Handle the rendering of the template
        (elemObj.onTemplateRender() !== undefined) &&
          elemObj.onTemplateRender()(
            elemObj.elementId,
            $content.find(`#${itemID}`),
            $content.find(`.${itemID}--item`),
            {elemObj, ...elemObj.templateData, data: {}},
            meta,
          );
      }
    });
  }

  // Find and handle any images
  $content.find('img').xiboImageRender();

  // Check if we are visible
  if (xiboIC.checkVisible()) {
    currentWidget.onVisible();
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.debug(
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

  // Updated params for rendering
  const optionsForRendering = {
    rendering: this.renderOptions(currentWidget, globalOptions),
  };

  // Run onRender
  currentWidget.onRender(currentWidget, optionsForRendering);

  if (xiboIC.checkVisible()) {
    // Run onVisible
    currentWidget.onVisible(optionsForRendering);
  } else {
    xiboIC.addToQueue(currentWidget.onVisible);
  }

  console.debug(
    '<<<END>>> of renderModule for widget >', currentWidget.widgetId);
};

/**
 * Define element functions
 * @param {Object} element Element
 * @param {Object} dataItem Data item
 */
XiboPlayer.prototype.loadElementFunctions = function(element, dataItem) {
  element.onElementParseData = function(elemData) {
    const newDataItem = elemData ?? dataItem;
    const extendDataWith = transformer
      .getExtendedDataKey(element.dataOverrideWith);

    if (extendDataWith !== null &&
      newDataItem.hasOwnProperty(extendDataWith)
    ) {
      newDataItem[element.dataOverride] = newDataItem[extendDataWith];
    }

    // Handle special case for setting data for the player
    if (element.type === 'dataset' && Object.keys(newDataItem).length > 0) {
      if (element.dataOverride !== null &&
        element.templateData?.datasetField !== undefined
      ) {
        const datasetField = element.templateData.datasetField;
        // Check if there are dates that needs formatting
        // before assigning value
        let tempVal = newDataItem[datasetField];

        if (element.dataOverride === 'date') {
          const dateFormat = element.templateData.dateFormat;
          tempVal = DateFormatHelper.formatDate(tempVal, dateFormat);
        }

        element[element.dataOverride] = tempVal;

        // Change value in templateData if exists
        if (element.templateData.hasOwnProperty(element.dataOverride)) {
          element.templateData[element.dataOverride] = tempVal;
        }
      }
    }

    if (typeof window[
      `onElementParseData_${element.templateData.id}`
    ] === 'function') {
      newDataItem[element.dataOverride] =
        window[`onElementParseData_${element.templateData.id}`](
          newDataItem[extendDataWith],
          {...element.templateData, data: newDataItem},
        );
    }

    console.debug('Called onElementParseData for element >', element.elementId);
    return newDataItem;
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

    console.debug('Called onTemplateRender for element >', element.elementId);

    return onTemplateRender;
  };
};

XiboPlayer.prototype.isStaticWidget = function(playerWidget) {
  return playerWidget !== undefined && playerWidget !== null &&
    playerWidget.templateId !== 'elements' &&
    elements.length === 0;
};

XiboPlayer.prototype.isModule = function(currentWidget) {
  return (!currentWidget.isDataExpected && $('#hbs-module').length > 0) ||
    (!currentWidget.isDataExpected && elements.length === 0);
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
      params.isDataReady,
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
    console.debug('Called onTemplateRender for widget > ', params.widgetId);

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
    console.debug('Called onRender for widget > ', params.widgetId);
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
      console.debug('Called onTemplateVisible for widget > ', params.widgetId);

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
      console.debug('Called onVisible for widget > ', params.widgetId);
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

XiboPlayer.prototype.renderOptions = function(currentWidget, globalOptions) {
  // Options for the render functions
  return Object.assign(
    currentWidget.properties,
    globalOptions,
    {
      duration: currentWidget.duration,
      pauseEffectOnStart:
        globalOptions.pauseEffectOnStart ?? true,
      isPreview: currentWidget.isPreview,
      isEditor: currentWidget.isEditor,
    },
  );
};

XiboPlayer.prototype.getRenderParams = function(
  currentWidget,
  options,
  globalOptions,
) {
  return {
    templateId: currentWidget.templateId,
    widgetId: currentWidget.widgetId,
    target: options.target,
    items: currentWidget.items,
    rendering: this.renderOptions(currentWidget, globalOptions),
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

/**
 * Run xiboLayoutAnimate to start animations
 * @param {Object} $target HTML target element
 * @param {Object} properties Widget or Group or Element properties
 */
XiboPlayer.prototype.runLayoutAnimate = function($target, properties) {
  $target.xiboLayoutAnimate(properties);
};

const xiboPlayer = new XiboPlayer();

module.exports = xiboPlayer;

$(function() {
  xiboPlayer.init();
});
