const PlayerHelper = function() {
  // Check the query params to see if we're in editor mode
  const _self = this;
  const urlParams = new URLSearchParams(window.location.search);
  const isPreview = urlParams.get('preview') === '1';

  this.init = (widgetData, elements) => new Promise((resolve) => {
    if (Array.isArray(widgetData)) {
      const _widgetData = [...widgetData];

      Promise.all(_widgetData.map(function(widget) {
        return _self.getWidgetData(widget);
      })).then((values) => {
        const widgets = {};
        const widgetHasNoData = values.filter((val) => {
          return val !== null &&
            (val.hasOwnProperty('success') ||
              val.hasOwnProperty('error'));
        });

        if (widgetHasNoData.length > 0) {
          _self.onDataErrorCallback(404, widgetData);
          resolve({widgets});
        }

        values.forEach((value, widgetIndex) => {
          let _elements = {standalone: {}, groups: {}};
          const _widget = _widgetData[widgetIndex];
          const {dataItems, showError} = this.composeFinalData(_widget, value);

          if (elements !== undefined && elements?.length > 0) {
            elements.forEach(function(elemVal) {
              if (elemVal?.length > 0) {
                elemVal.forEach(function(elemObj) {
                  if (elemObj.widgetId === _widget.widgetId) {
                    _elements = _self.composeElements(
                      elemObj?.elements || [], _widget);
                  }
                });
              }
            });

            if (Object.keys(_elements).length > 0) {
              _elements =
                _self.decorateCollectionSlots(_elements, dataItems, _widget);
            }
          }

          widgets[_widget.widgetId] = {
            ..._widget,
            data: dataItems,
            meta: value !== null ? value?.meta : {},
            showError,
            elements: _elements,
          };
        });

        resolve({widgets});
      });
    }
  });

  /**
   * onDataError callback
   * @param {string|number} httpStatus
   * @param {Object} response - Response body|json
   */
  this.onDataErrorCallback = (httpStatus, response) => {
    const onDataError = window[
      `onDataError_${xiboICTargetId}`
    ];

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

  /**
   * Get widget data
   * @param {object} widget
   * @return {Promise}
   */
  this.getWidgetData = (widget) => {
    return new Promise(function(resolve, reject) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      // if (!isPreview) {
      if (widget.data?.data !== undefined) {
        resolve(widget.data);
      } else if (widget.url) {
        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax({
          method: 'GET',
          url: widget.url,
        }).done(function(data) {
          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          _self.onDataErrorCallback(widget, jqXHR.status, jqXHR.responseJSON);
          console.log(jqXHR, textStatus, errorThrown);
          resolve({
            error: jqXHR.status,
            success: false,
            data: jqXHR.responseJSON,
          });
        });
      } else {
        resolve(null);
      }
    });
  };

  /**
   * Compose final data
   * @param {object} widget
   * @param {object|array} data
   * @return {object}
   */
  this.composeFinalData = (widget, data) => {
    const finalData = {
      isSampleData: false,
      dataItems: [],
      isArray: Array.isArray(data?.data),
      showError: false,
    };
    const composeSampleData = () => {
      finalData.isSampleData = true;

      if (widget.sample === null) {
        finalData.dataItems = [];
        return [];
      }

      // If data is empty, use sample data instead
      // Add single element or array of elements
      finalData.dataItems = (Array.isArray(widget.sample)) ?
        widget.sample.slice(0) : [widget.sample];

      return finalData.dataItems.reduce((data, item) => {
        Object.keys(item).forEach((itemKey) => {
          if (String(item[itemKey])
            .match(DateFormatHelper.macroRegex) !== null) {
            item[itemKey] = DateFormatHelper
              .composeUTCDateFromMacro(item[itemKey]);
          }
        });

        return [...data, {...item}];
      }, []);
    };

    if (isPreview) {
      if (finalData.isArray) {
        if (data?.data?.length > 0) {
          finalData.dataItems = data?.data;
        } else if (widget.sample && Array.isArray(widget.sample)) {
          finalData.dataItems = composeSampleData();
        }
      } else if (data?.success === false || !widget.isValid) {
        finalData.dataItems = composeSampleData();
      }
    } else {
      finalData.dataItems = data?.data || [];
    }

    if (data?.success === false || !widget.isValid) {
      finalData.showError = true;
    }

    return finalData;
  };

  /**
   * Compose widget elements
   * @param {array} elements
   * @param {object} widget
   * @return {object} {groups, standalone}
   */
  this.composeElements = (elements, widget) => {
    return elements.length > 0 && elements.reduce(function(collection, item) {
      const element = _self.decorateElement(item, widget);
      const isGroup = _self.isGroup(element);
      const standaloneKey = element.type === 'dataset' ?
        element.id + '_' + element.templateData.datasetField :
        element.id;


      // Initialize object values
      if (!isGroup &&
        !collection.standalone.hasOwnProperty(standaloneKey)
      ) {
        collection.standalone[standaloneKey] = [];
      }

      if (isGroup &&
        !collection.groups.hasOwnProperty(element.groupId)
      ) {
        collection.groups[element.groupId] = {
          ...globalOptions,
          ...widget.properties,
          ...element.groupProperties,
          id: element.groupId,
          uniqueID: element.groupId,
          duration: widget.duration,
          parentId: element.groupId,
          slot: element.slot,
          items: [],
        };
      }

      // Fill in objects with items
      if (!isGroup &&
        Object.keys(collection.standalone).length > 0
      ) {
        collection.standalone[standaloneKey] = [
          ...collection.standalone[standaloneKey],
          {
            ...element,
            numItems: 1,
            duration: widget.duration,
          },
        ];
      }

      if (isGroup && Object.keys(collection.groups).length > 0) {
        collection.groups[element.groupId] = {
          ...collection.groups[element.groupId],
          items: [
            ...collection.groups[element.groupId].items,
            {...element, numItems: 1},
          ],
        };
      }

      return collection;
    }, {standalone: {}, groups: {}});
  };

  /**
   * Decorate element
   * @param {object} element
   * @param {object} widget
   * @return {object} element
   */
  this.decorateElement = function(element, widget) {
    const elemCopy = JSON.parse(JSON.stringify(element));
    const elemProps = elemCopy?.properties || {};

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

    // Compile the template if it exists
    if ($template && $template.length > 0) {
      elemCopy.dataOverride =
          $template?.data('extends-override');
      elemCopy.dataOverrideWith =
          $template?.data('extends-with');
      elemCopy.escapeHtml =
          $template?.data('escape-html');

      if (String(elemCopy.dataOverride).length > 0 &&
          String(elemCopy.dataOverrideWith).length > 0) {
        elemCopy.isExtended = true;
      }

      elemCopy.hbs = Handlebars.compile($template.html());
    }

    // Special case for handling weather language
    if (elemProps.hasOwnProperty('lang') &&
        widget.properties.hasOwnProperty('lang')) {
      const elemLang = elemProps.lang;
      const widgetLang = widget.properties.lang;

      elemProps.lang = (elemLang !== null && String(elemLang).length > 0) ?
        elemLang : widgetLang;
    }

    elemCopy.templateData = Object.assign(
      {}, elemCopy, elemProps, globalOptions,
      {uniqueID: elemCopy.elementId, prop: {...elemCopy, ...elemProps}},
    );

    // Get widget info if exists.
    if (widget.templateId !== null && widget.url !== null) {
      elemCopy.renderData = Object.assign(
        {},
        widget.properties,
        elemCopy,
        globalOptions,
        {
          duration: widget.duration,
          marqueeInlineSelector: `.${elemCopy.templateData.id}--item`,
          parentId: elemCopy.elementId,
        },
      );
      elemCopy.withData = true;
    } else {
      // Elements with no data can be extended.
      // Thus, we have to decorate the element with extended params
      if (elemCopy.dataOverride !== null &&
        elemCopy.dataOverrideWith !== null) {
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

    return elemCopy;
  };

  /**
   * Decorate slots with data
   * @param {object} elements
   * @param {array} dataItems
   * @param {object} widget
   * @return {object} elements
   */
  this.decorateCollectionSlots = function(elements, dataItems, widget) {
    _self.getStandaloneSlotsData(elements, dataItems, widget);
    _self.getGroupSlotsData(elements, dataItems, widget);
    return elements;
  };

  this.getStandaloneSlotsData = function(elements, data, widget) {
    const standalone = elements.standalone;
    const objKeys = Object.keys(standalone);

    widget.standaloneSlotsData = {};

    if (objKeys.length > 0) {
      objKeys.forEach(function(objKey) {
        if (standalone.hasOwnProperty(objKey)) {
          const itemObj = standalone[objKey];

          if (!widget.standaloneSlotsData.hasOwnProperty(objKey)) {
            widget.standaloneSlotsData[objKey] = [];
          }

          if (itemObj.length > 0) {
            widget.standaloneSlotsData[objKey] =
              itemObj.reduce(function(a, b, slotKey) {
                a[slotKey + 1] = [];
                return {...a};
              }, {});
          }
        }

        const pinnedSlot = standalone[objKey].reduce(function(a, b) {
          if (b.pinSlot) return b.slot + 1;
          return a;
        }, null);

        _self.composeSlotsData('standalone', data, standalone, widget, {
          objKey,
          lastSlotFilled: null,
        });

        widget.standaloneSlotsData[objKey] = _self.composeRepeatNonRepeatData(
          widget.standaloneSlotsData[objKey],
          pinnedSlot,
          widget.isRepeatData,
        );
      });
    }
  };

  this.getGroupSlotsData = function(elements, data, widget) {
    widget.mappedSlotGroup = {};
    widget.groupSlotsData = {};

    if (Object.keys(elements.groups).length > 0) {
      const {pinnedSlot} = _self.getGroupData(
        elements.groups,
        'items',
      );

      widget.mappedSlotGroup = _self.mapSlot(
        Object.values(elements.groups), 'id',
      );
      widget.groupSlotsData =
        Object.keys(widget.mappedSlotGroup).reduce(function(a, b) {
          a[b] = [];
          return {...a};
        }, {});

      _self.composeSlotsData('groups', data, elements.groups, widget, {
        lastSlotFilled: null,
      });

      widget.groupSlotsData = _self.composeRepeatNonRepeatData(
        widget.groupSlotsData,
        pinnedSlot,
        widget.isRepeatData,
      );
    }
  };

  /**
   * Compose slots data
   * @param {string} type group|standalone
   * @param {array} dataItems Widget data
   * @param {object} collection {groups, standalone}
   * @param {object} widget Widget object
   * @param {object?} item Optional item object
   */
  this.composeSlotsData = function(
    type,
    dataItems,
    collection,
    widget,
    item,
  ) {
    const isStandalone = type === 'standalone';
    const groupData = _self.getGroupData(
      isStandalone ? [collection] : collection,
      isStandalone ? item.objKey : 'items',
    );
    const maxSlot = groupData.maxSlot;
    let pinnedSlot = groupData.pinnedSlot;

    if (isStandalone) {
      pinnedSlot = collection[item.objKey].reduce(function(a, b) {
        if (b.pinSlot) return b.slot + 1;
        return a;
      }, null);
    }

    if (dataItems.length > 0) {
      const lastSlotFilled = {};

      lastSlotFilled[type] = null;

      for (const [dataItemKey] of Object.entries(dataItems)) {
        const hasSlotFilled = {};
        const currentKey = parseInt(dataItemKey) + 1;
        const currCollection = isStandalone ?
          collection[item.objKey] : Object.keys(collection);

        hasSlotFilled[type] = false;

        if (isStandalone) {
          if (lastSlotFilled[type] !== null &&
              currCollection.length === maxSlot &&
              pinnedSlot === maxSlot
          ) {
            lastSlotFilled[type] = null;
            hasSlotFilled[type] = false;
            break;
          }
        }

        for (const [, itemValue] of Object.entries(currCollection)) {
          const itemObj = isStandalone ?
            itemValue : collection[itemValue];
          const isPinnedSlot = itemObj.pinSlot;
          const currentSlot = itemObj.slot + 1;

          if (!isPinnedSlot && currentKey !== pinnedSlot) {
            // If lastSlotFilled is filled and is <= to currentSlot
            // Then, move to next slot
            if (lastSlotFilled[type] !== null &&
                currentSlot <= lastSlotFilled[type]) {
              continue;
            }

            if (isStandalone) {
              const currentSlotItem =
                  widget.standaloneSlotsData[item.objKey];

              if (Object.keys(currentSlotItem).length > 0 &&
                  currentSlotItem.hasOwnProperty(currentSlot)
              ) {
                currentSlotItem[currentSlot] = [
                  ...currentSlotItem[currentSlot],
                  currentKey,
                ];
              }

              widget.standaloneSlotsData[item.objKey] = {
                ...currentSlotItem,
              };
            } else {
              widget.groupSlotsData[currentSlot] = [
                ...widget.groupSlotsData[currentSlot],
                currentKey,
              ];
            }

            hasSlotFilled[type] = true;
            lastSlotFilled[type] = currentSlot;
          } else if (isPinnedSlot &&
              currentSlot === currentKey
          ) {
            if (isStandalone) {
              widget.standaloneSlotsData[item.objKey][pinnedSlot] =
                  [pinnedSlot];
            } else {
              widget.groupSlotsData[pinnedSlot] = [pinnedSlot];
            }

            hasSlotFilled[type] = true;
            lastSlotFilled[type] = currentSlot;
          } else if (isPinnedSlot && pinnedSlot === maxSlot &&
              currentSlot !== currentKey
          ) {
            if (isStandalone) {
              widget.standaloneSlotsData[item.objKey][1] = [
                ...widget.standaloneSlotsData[item.objKey][1],
                currentKey,
              ];
            } else {
              widget.groupSlotsData[1] = [
                ...widget.groupSlotsData[1],
                currentKey,
              ];
            }

            hasSlotFilled[type] = true;
            lastSlotFilled[type] = 1;
          }

          if (hasSlotFilled[type]) {
            hasSlotFilled[type] = false;
            if (lastSlotFilled[type] % maxSlot === 0) {
              lastSlotFilled[type] = null;
            }

            break;
          }
        }
      }
    }
  };

  /**
   * Re-compose slotsData for repeat/non-repeat data widget
   * @param {object} slotsData
   * @param {number|null} pinnedSlot
   * @param {boolean} isRepeat
   * @return {object} slotsData
   */
  this.composeRepeatNonRepeatData = function(
    slotsData,
    pinnedSlot,
    isRepeat,
  ) {
    // Copy slotsData
    const groupSlotsData = {...slotsData};
    // Remove pinnedSlot from the object
    if (slotsData.hasOwnProperty(pinnedSlot)) {
      delete groupSlotsData[pinnedSlot];
    }

    const dataCounts = Object.keys(groupSlotsData).reduce((a, b) => {
      a[b] = groupSlotsData[b].length;
      return a;
    }, {});
    const maxCount = Math.max(
      ...(Object.values(dataCounts).map((count) => Number(count))));
    const minCount = Math.min(
      ...(Object.values(dataCounts).map((count) => Number(count))));

    if (minCount < maxCount) {
      const nonPinnedDataKeys =
          Object.values(groupSlotsData).reduce((a, b) => {
            return [...a, ...b];
          }, []).sort((a, b) => {
            if (a < b) return -1;
            if (a > b) return 1;
            return 0;
          });

      Object.keys(groupSlotsData).forEach(
        function(slotIndex, slotKey) {
          const dataCount = dataCounts[slotIndex];
          if (dataCount < maxCount) {
            const countDiff = maxCount - dataCount;
            if (countDiff === 1) {
              const poppedKey = nonPinnedDataKeys.shift();
              slotsData[slotIndex].push(isRepeat ? poppedKey : 'empty');
            }
          }
        });
    }

    return slotsData;
  };

  this.getMaxSlot = (objectsArray, itemsKey, minValue) => {
    const groupItems = objectsArray?.length > 0 ?
      objectsArray.reduce(
        (a, b) => [...a, ...b[itemsKey]], []) : null;

    return groupItems === null ?
      minValue :
      Math.max(...groupItems.map(function(elem) {
        return elem?.slot || 0;
      })) + 1;
  };

  this.getGroupData = function(
    groupsData,
    slotItemsKey,
    isStandalone,
  ) {
    const groupValues = Object.values(groupsData);
    const maxSlot = _self.getMaxSlot(groupValues, slotItemsKey, 1);
    let pinnedSlot = null;

    if (!isStandalone) {
      pinnedSlot = groupValues.reduce(
        function(a, b) {
          if (b.pinSlot) return b.slot + 1;
          return a;
        }, null);
    }

    return {
      groupValues,
      maxSlot,
      pinnedSlot,
    };
  };

  this.mapSlot = function(items, key) {
    if (items?.length > 0) {
      const mappedSlots = {};
      items.forEach(function(item) {
        if (!mappedSlots.hasOwnProperty(item.slot + 1)) {
          mappedSlots[item.slot + 1] = item[key];
        }
      });

      return mappedSlots;
    }
  };

  this.isGroup = function(element) {
    return element.hasOwnProperty('groupId');
  };

  this.isMarquee = function(effect) {
    return effect === 'marqueeLeft' ||
      effect === 'marqueeRight' ||
      effect === 'marqueeUp' ||
      effect === 'marqueeDown';
  };

  return this;
};

module.exports = new PlayerHelper();
