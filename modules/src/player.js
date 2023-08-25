/*
 * Copyright (C) 2023 Xibo Signage Ltd
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
$(function() {
  // Check the query params to see if we're in editor mode
  const urlParams = new URLSearchParams(window.location.search);
  const isPreview = urlParams.get('preview') === '1';

  // Defaut scaler function
  const defaultScaler = function(
    _id,
    target,
    _items,
    properties,
  ) {
    // If target is not defined, use the body
    target = (target) ? target : $('body');

    // If properties is empty
    // use the global options with widget properties
    properties = (properties) ?
      properties : Object.assign(widget.properties, globalOptions);

    // Scale the content if there's no scaleContent property
    // or if it's set to true
    if (
      !properties.hasOwnProperty('scaleContent') ||
      properties.scaleContent
    ) {
      // Scale the content
      $(target).xiboLayoutScaler(properties);
    }
  };

  const macroRegex = /^%(\+|\-)[0-9]([0-9])?(d|h|m|s)%$/gi;

  const composeUTCDateFromMacro = (macroStr) => {
    const utcFormat = 'YYYY-MM-DDTHH:mm:ssZ';
    const dateNow = moment().utc();
    // Check if input has the correct format
    const dateStr = String(macroStr);

    if (dateStr.length === 0 ||
        dateStr.match(macroRegex) === null
    ) {
      return dateNow.format(utcFormat);
    }

    // Trim the macro date string
    const dateOffsetStr = dateStr.replaceAll('%', '');
    const params = (op) => dateOffsetStr.replace(op, '')
      .split(/(\d+)/).filter(Boolean);
    const addRegex = /^\+/g;
    const subtractRegex = /^\-/g;

    // Check if it's add or subtract offset and return composed date
    if (dateOffsetStr.match(addRegex) !== null) {
      return dateNow.add(...params(addRegex)).format(utcFormat);
    } else if (dateOffsetStr.match(subtractRegex) !== null) {
      return dateNow.subtract(...params(subtractRegex)).format(utcFormat);
    }
  };
  const elementsWidget = {};

  /**
   * Compose final data
   * @param {object} widget
   * @param {object|array} data
   * @return {object}
   */
  function composeFinalData(widget, data) {
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
          if (String(item[itemKey]).match(macroRegex) !== null) {
            item[itemKey] = composeUTCDateFromMacro(item[itemKey]);
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
  }

  /**
   * onDataError callback
   * @param {Object} widget - Widget
   * @param {string|number} httpStatus
   * @param {Object} response - Response body|json
   */
  function onDataErrorCallback(widget, httpStatus, response) {
    if (
      typeof window[`onDataError_${widget.widgetId}`] === 'function'
    ) {
      const onDataError = window[
        `onDataError_${widget.widgetId}`
      ](httpStatus, response);

      if (typeof onDataError === 'undefined' || onDataError == false) {
        xiboIC.reportFault({
          code: '5001',
          reason: 'No Data',
        });
      }
    }
  }

  /**
   * Get widget data
   * @param {object} widget
   * @return {Promise}
   */
  function getWidgetData(widget) {
    return new Promise(function(resolve) {
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
          if (data && data.hasOwnProperty('success') &&
            data.success === false && data.error
          ) {
            onDataErrorCallback(widget, data.error, data);
          }

          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          onDataErrorCallback(widget, jqXHR.status, jqXHR.responseJSON);
          console.log(jqXHR, textStatus, errorThrown);
        });
      } else {
        resolve(null);
      }
    });
  }

  /**
   * Initialize the player
   * @param {object} $template
   * @param {object} $content
   * @param {boolean} moduleTemplate
   * @param {object} hbs
   * @param {object} widget
   * @param {*} data
   */
  function initPlayer(
    $template,
    $content,
    moduleTemplate,
    hbs,
    widget,
    data,
  ) {
    widget.items = [];
    const $target = $('body');
    const {
      dataItems,
      showError,
    } = composeFinalData(widget, data);

    if (dataItems.length === 0 && showError) {
      xiboIC.expireNow({targetId: widget.widgetId});
    }

    if (showError && data?.message) {
      $target.append(
        '<div class="error-message" role="alert">' +
        data.message +
        '</div>');
    }

    // Add meta to the widget if it exists
    if (data?.meta) {
      widget.meta = data.meta;
    }

    // Run the onInitialize function if it exists
    if (typeof window['onInitialize_' + widget.widgetId] === 'function') {
      window['onInitialize_' + widget.widgetId](
        widget.widgetId,
        $target,
        widget.properties,
        widget.meta,
      );
    }

    // For each data item, parse it and add it to the content
    let templateAlreadyAdded = false;
    $.each(dataItems, function(_key, item) {
      // Parse the data if there is a parser function
      if (typeof window['onParseData_' + widget.widgetId] === 'function') {
        item = window[
          'onParseData_' + widget.widgetId
        ](item, widget.properties, widget.meta);
      }

      // Add the item to the content
      (hbs) && $content.append(hbs(item));

      // Add items to the widget object
      (item) && widget.items.push(item);

      // IF we added item template
      templateAlreadyAdded = true;
    });

    // If we don't have dataType, or we have a module template
    // add it to the content with widget properties and global options
    if (moduleTemplate && hbs && !templateAlreadyAdded) {
      $content.append(hbs(
        Object.assign(widget.properties, globalOptions),
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
    for (const key in widget.templateProperties) {
      if (widget.templateProperties.hasOwnProperty(key)) {
        widget.properties[key] = widget.templateProperties[key];
      }
    }

    // Check if we have a custom template
    let customTemplate = false;
    if (
      widget.properties['customTemplate'] &&
      widget.properties['customTemplate'] == 1
    ) {
      customTemplate = true;
    }

    // Run the onRender function if it exists
    if (typeof window['onRender_' + widget.widgetId] === 'function') {
      window.onRender =
        window['onRender_' + widget.widgetId];
    }

    // Handle the rendering of the template
    if (
      typeof window['onTemplateRender_' + widget.templateId] === 'function'
    ) { // Custom scaler
      window.onTemplateRender =
        window['onTemplateRender_' + widget.templateId];
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

    // If there's no elements in renders, use the default scaler
    if (window.renders.length === 0) {
      window.renders.push(defaultScaler);
    }

    // If we have a custom template, run the legacy template render first
    if (customTemplate) {
      const newOptions =
        $('body').xiboLegacyTemplateRender(
          Object.assign(
            widget.properties,
            globalOptions,
          ),
          widget,
        ).options;

      // Merge new options with globalOptions
      globalOptions = Object.assign(
        globalOptions,
        newOptions,
      );
    }

    // Options for the render functions
    const optionsForRendering = Object.assign(
      widget.properties,
      globalOptions,
      {
        duration: widget.duration,
      },
    );

    // Run the render functions
    $.each(window.renders, function(_key, render) {
      render(
        widget.widgetId,
        $target,
        widget.items,
        optionsForRendering,
        widget.meta,
      );
    });

    // Save widget as global variable
    window.widget = widget;

    // Call the run on visible function if it exists
    if (
      typeof window['onVisible_' + widget.widgetId] === 'function'
    ) {
      window.runOnVisible = function() {
        window['onVisible_' + widget.widgetId](
          widget.widgetId,
          $target,
          widget.items,
          widget.properties,
          widget.meta,
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
  }

  // Call the data url and parse out the template.
  $.each(widgetData, function(_key, widget) {
    // Check if we have template from templateId or module
    // and set it as the template
    let $template = null;
    let moduleTemplate = false;
    if ($('#hbs-' + widget.templateId).length > 0) {
      $template = $('#hbs-' + widget.templateId);
    } else if ($('#hbs-module').length > 0) {
      $template = $('#hbs-module');
      moduleTemplate = true;
    }

    // Save widgetData to xic
    xiboIC.set(widget.widgetId, 'widgetData', widget);

    let hbs = null;
    // Compile the template if it exists
    if ($template && $template.length > 0) {
      hbs = Handlebars.compile($template.html());
    }

    const $content = $('#content');

    // Get widget with templateId = elements
    if (widget.templateId === 'elements') {
      elementsWidget[widget.widgetId] = widget;
    }

    getWidgetData(widget).then(function(data) {
      initPlayer(
        $template,
        $content,
        moduleTemplate,
        hbs,
        widget,
        data,
      );
    });
  });

  // TODO: this can't be separate from the main player initialisation.
  if (typeof elements !== 'undefined') {
    // Parse out the template with elements.
    $.each(elements, function(_key, widgetElements) {
      if (widgetElements?.length > 0) {
        const $content = $('#content');

        $.each(widgetElements, function(_widgetElemKey, widgetElement) {
          if (widgetElement?.elements?.length > 0) {
            const playerElements = [];
            let widgetDataInfo = null;

            if (Object.keys(elementsWidget).length > 0 &&
                elementsWidget.hasOwnProperty(widgetElement.widgetId)
            ) {
              widgetDataInfo = elementsWidget[widgetElement.widgetId];
            }
            const renderElement = (hbs, data, isStatic) => {
              const hbsTemplate = hbs(
                Object.assign(data, globalOptions),
              );
              let cssStyles = {
                height: data.height,
                width: data.width,
                position: 'absolute',
                top: data.top,
                left: data.left,
                'z-index': data.layer,
                transform: `rotate(${data?.rotation || 0}deg)`,
              };

              if (isStatic) {
                cssStyles = {
                  ...cssStyles,
                  position: 'absolute',
                  top: data.top,
                  left: data.left,
                  'z-index': data.layer,
                };
              }

              return $(hbsTemplate).first()
                .attr('id', data.elementId)
                .addClass(`${data.uniqueID}--item`)
                .css(cssStyles)
                .prop('outerHTML');
            };

            $.each(widgetElement.elements, function(_elemKey, element) {
              const elementCopy = JSON.parse(JSON.stringify(element));
              const elementProperties = elementCopy?.properties || {};

              if (Object.keys(elementCopy).length > 0 &&
                elementCopy.hasOwnProperty('properties')
              ) {
                delete elementCopy.properties;
              }

              // Check if we have template from templateId or module
              // and set it as the template
              let $template = null;
              const templateSelector = `#hbs-${elementCopy.id}`;
              if ($(templateSelector).length > 0) {
                $template = $(templateSelector);
              }

              elementCopy.hbs = null;
              elementCopy.dataOverride = null;
              elementCopy.dataOverrideWith = null;

              // Compile the template if it exists
              if ($template && $template.length > 0) {
                elementCopy.dataOverride = $template?.data('extends-override');
                elementCopy.dataOverrideWith = $template?.data('extends-with');

                elementCopy.hbs = Handlebars.compile($template.html());
              }

              elementCopy.templateData = Object.assign(
                {}, elementProperties, elementCopy, globalOptions,
                {uniqueID: elementCopy.elementId, prop: elementCopy},
              );

              // Get widget info if exists.
              if (widgetDataInfo !== null) {
                elementCopy.renderData = Object.assign(
                  {},
                  widgetDataInfo.properties,
                  elementCopy,
                  globalOptions,
                  {
                    duration: widgetDataInfo.duration,
                    marqueeInlineSelector: `
                      .${elementCopy.templateData.id}--item`,
                    parentId: elementCopy.elementId,
                  },
                );

                playerElements.push(elementCopy);
              } else {
                (elementCopy.hbs) && $content.append(
                  renderElement(
                    elementCopy.hbs,
                    elementCopy.templateData,
                    true,
                  ),
                );
              }
            });

            if (widgetDataInfo !== null && playerElements.length > 0) {
              const mapSlot = function(items, key) {
                if (items?.length > 0) {
                  const mappedSlots = {};
                  $.each(items, function(itemIndx, item) {
                    if (!mappedSlots.hasOwnProperty(item.slot + 1)) {
                      mappedSlots[item.slot + 1] = item[key];
                    }
                  });

                  return mappedSlots;
                }
              };
              const elementGroups = playerElements.reduce(
                function(elemGroups, elemGroup) {
                  const isGroup = elemGroup.hasOwnProperty('groupId');

                  // Initialize object values
                  if (!isGroup &&
                      !elemGroups.standalone.hasOwnProperty(elemGroup.id)
                  ) {
                    elemGroups.standalone[elemGroup.id] = [];
                  }
                  if (isGroup &&
                      !elemGroups.groups.hasOwnProperty(elemGroup.groupId)
                  ) {
                    elemGroups.groups[elemGroup.groupId] = {
                      ...globalOptions,
                      ...widgetDataInfo.properties,
                      ...elemGroup.groupProperties,
                      id: elemGroup.groupId,
                      uniqueID: elemGroup.groupId,
                      duration: widgetDataInfo.duration,
                      parentId: elemGroup.groupId,
                      slot: elemGroup.slot,
                      items: [],
                    };
                  }

                  // Fill in objects with items
                  if (!isGroup &&
                    Object.keys(elemGroups.standalone).length > 0
                  ) {
                    elemGroups.standalone[elemGroup.id] = [
                      ...elemGroups.standalone[elemGroup.id],
                      {
                        ...elemGroup,
                        numItems: 1,
                        duration: widgetDataInfo.duration,
                      },
                    ];
                  }
                  if (isGroup && Object.keys(elemGroups.groups).length > 0) {
                    elemGroups.groups[elemGroup.groupId] = {
                      ...elemGroups.groups[elemGroup.groupId],
                      items: [
                        ...elemGroups.groups[elemGroup.groupId].items,
                        {...elemGroup, numItems: 1},
                      ],
                    };
                  }

                  return elemGroups;
                }, {standalone: {}, groups: {}});

              getWidgetData(widgetDataInfo)
                .then(function(data) {
                  const {
                    dataItems,
                  } = composeFinalData(widgetDataInfo, data);

                  // Parse the data if there is a parser function
                  if (typeof window['onParseData_' +
                    widgetDataInfo.widgetId] === 'function'
                  ) {
                    $.each(dataItems, function(_key, item) {
                      item = window['onParseData_' + widgetDataInfo.widgetId](
                        item,
                        widgetDataInfo.properties,
                        widgetDataInfo.meta,
                      );
                    });
                  }

                  const getMaxSlot = (objectsArray, itemsKey, minValue) => {
                    const groupItems = objectsArray?.length > 0 ?
                      objectsArray.reduce(
                        (a, b) => [...a, ...b[itemsKey]], []) :
                      null;

                    return groupItems === null ?
                      minValue :
                      Math.max(...groupItems.map(function(elem) {
                        return elem?.slot || 0;
                      }),
                      ) + 1;
                  };
                  const renderDataItem = function(
                    dataItemKey,
                    dataItem,
                    item,
                    slot,
                    maxSlot,
                    isPinSlot,
                    pinnedSlot,
                    groupId,
                    $groupContent,
                  ) {
                    // For each data item, parse it and add it to the content;
                    if (item.hasOwnProperty('hbs') &&
                      typeof item.hbs === 'function'
                    ) {
                      const extendDataWith = transformer
                        .getExtendedDataKey(item.dataOverrideWith);

                      if (extendDataWith !== null &&
                        dataItem.hasOwnProperty(extendDataWith)
                      ) {
                        dataItem[item.dataOverride] =
                          dataItem[extendDataWith];
                      }

                      // Handle special case for setting data for the player
                      if (item.type === 'dataset' &&
                        Object.keys(dataItem).length > 0
                      ) {
                        if (item.dataOverride !== null &&
                          item.templateData?.datasetField !== undefined
                        ) {
                          item[item.dataOverride] =
                            dataItem[item.templateData.datasetField];

                          // Change value in templateData if exists
                          if (item.templateData.hasOwnProperty(
                            item.dataOverride)) {
                            item.templateData[item.dataOverride] =
                              dataItem[item.templateData.datasetField];
                          }
                        }
                      }

                      if (typeof window[
                        `onElementParseData_${item.templateData.id}`
                      ] === 'function') {
                        const onElementParseData = window[
                          `onElementParseData_${item.templateData.id}`
                        ];

                        if (onElementParseData) {
                          dataItem[item.dataOverride] = onElementParseData(
                            dataItem[extendDataWith],
                            {...item.templateData, data: dataItem},
                          );
                        }
                      }

                      const $groupContentItem =
                        $(`<div class="${groupId}--item"
                        data-group-key="${dataItemKey}"></div>`);
                      const groupKey = '.' + groupId +
                        '--item[data-group-key=%key%]';

                      if ($groupContent &&
                        $groupContent.find(
                          groupKey.replace('%key%', dataItemKey),
                        ).length === 0
                      ) {
                        $groupContent.append($groupContentItem);
                      }

                      const $itemContainer = $groupContent.find(
                        groupKey.replace('%key%', dataItemKey),
                      );

                      $itemContainer.append(
                        renderElement(
                          item.hbs,
                          Object.assign(
                            item.templateData,
                            (String(item.dataOverride).length > 0 &&
                              String(item.dataOverrideWith).length > 0) ?
                              dataItem : {data: dataItem},
                          )),
                      );

                      // Handle the rendering of the template
                      if (typeof window[
                        `onTemplateRender_${item.templateData.id}`
                      ] === 'function') {
                        const onTemplateRender = window[
                          `onTemplateRender_${item.templateData.id}`];

                        onTemplateRender && onTemplateRender(
                          item.elementId,
                          $itemContainer,
                          $content.find(`.${item.uniqueID}--item`),
                          {item, ...item.templateData, data: dataItem},
                          widgetDataInfo?.meta,
                        );
                      }
                    }
                  };

                  let mappedSlotsGroupData = {};
                  let mappedSlotGroup = {};
                  const mappedSlotsStandaloneData = {};
                  const getGroupData = function(
                    groupsData,
                    slotItemsKey,
                    isStandalone,
                  ) {
                    const groupValues = Object.values(groupsData);
                    const maxSlot = getMaxSlot(groupValues, slotItemsKey, 1);
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

                  if (Object.keys(elementGroups.groups).length > 0) {
                    mappedSlotGroup =
                      mapSlot(Object.values(elementGroups.groups), 'id');
                    mappedSlotsGroupData =
                      Object.keys(mappedSlotGroup).reduce(function(a, b) {
                        a[b] = [];
                        return {...a};
                      }, {});
                  }
                  if (Object.keys(elementGroups.standalone).length > 0) {
                    $.each(Object.keys(elementGroups.standalone),
                      function(indx, itemKey) {
                        if (elementGroups.standalone.hasOwnProperty(itemKey)) {
                          const itemObj = elementGroups.standalone[itemKey];

                          if (!mappedSlotsStandaloneData
                            .hasOwnProperty(itemKey)) {
                            mappedSlotsStandaloneData[itemKey] = [];
                          }

                          if (itemObj.length > 0) {
                            mappedSlotsStandaloneData[itemKey] =
                              itemObj.reduce(function(a, b, slotKey) {
                                a[slotKey + 1] = [];
                                return {...a};
                              }, {});
                          }
                        }
                      });
                  }

                  let lastGroupSlotFilled = null;
                  $.each(dataItems, function(dataItemKey, dataItem) {
                    let hasGroupSlotFilled = false;
                    const currentKey = dataItemKey + 1;
                    // Parse group of elements
                    $.each(Object.keys(elementGroups.groups),
                      function(groupIndex, groupId) {
                        if (elementGroups.groups.hasOwnProperty(groupId)) {
                          const elemGroup = elementGroups.groups[groupId];
                          const {
                            maxSlot,
                            pinnedSlot,
                          } = getGroupData(elementGroups.groups, 'items');
                          const isPinnedSlot = elemGroup.pinSlot;
                          const currentSlot = elemGroup.slot + 1;

                          if (!isPinnedSlot && currentKey !== pinnedSlot) {
                            // If lastSlot is filled and is <= to currentSlot
                            // Then, move to next slot
                            if (lastGroupSlotFilled !== null &&
                              currentSlot <= lastGroupSlotFilled) {
                              return true;
                            }

                            mappedSlotsGroupData[currentSlot] = [
                              ...mappedSlotsGroupData[currentSlot],
                              currentKey,
                            ];
                            hasGroupSlotFilled = true;
                            lastGroupSlotFilled = currentSlot;
                          } else if (isPinnedSlot &&
                            currentSlot === currentKey) {
                            mappedSlotsGroupData[pinnedSlot] = [pinnedSlot];
                            hasGroupSlotFilled = true;
                            lastGroupSlotFilled = currentSlot;
                          } else if (isPinnedSlot && pinnedSlot === maxSlot &&
                            currentSlot !== currentKey) {
                            mappedSlotsGroupData[1] = [
                              ...mappedSlotsGroupData[1],
                              currentKey,
                            ];
                            hasGroupSlotFilled = true;
                            lastGroupSlotFilled = 1;
                          }

                          if (hasGroupSlotFilled) {
                            hasGroupSlotFilled = false;
                            if (lastGroupSlotFilled % maxSlot === 0) {
                              lastGroupSlotFilled = null;
                            }
                            return false;
                          }
                        }
                      });
                  });

                  // Parse standalone elements
                  $.each(Object.keys(elementGroups.standalone),
                    function(itemIndex, itemId) {
                      if (elementGroups.standalone.hasOwnProperty(itemId)) {
                        const itemsObj = elementGroups.standalone[itemId];
                        const {maxSlot} =
                          getGroupData([elementGroups.standalone], itemId);
                        const pinnedSlot = itemsObj.reduce(function(a, b) {
                          if (b.pinSlot) return b.slot + 1;
                          return a;
                        }, null);
                        let lastSingleSlotFilled = null;

                        $.each(dataItems, function(dataItemKey, dataItem) {
                          let hasStandaloneSlotFilled = false;
                          const currentKey = dataItemKey + 1;

                          if (itemsObj.length > 0) {
                            // If pinnedSlot === maxSlot === items length
                            // Then, move to next item
                            if (lastSingleSlotFilled !== null &&
                              itemsObj.length === maxSlot &&
                              pinnedSlot === maxSlot) {
                              lastSingleSlotFilled = null;
                              hasStandaloneSlotFilled = false;
                              return false;
                            }

                            $.each(itemsObj, function(itemKey, itemObj) {
                              const isPinnedSlot = itemObj.pinSlot;
                              const currentSlot = itemObj.slot + 1;

                              if (!isPinnedSlot && currentKey !== pinnedSlot) {
                                // If lastSingleSlot is filled
                                // and is <= to currentSlot
                                // Then, move to next slot
                                if (lastSingleSlotFilled !== null &&
                                    currentSlot <= lastSingleSlotFilled) {
                                  return true;
                                }

                                mappedSlotsStandaloneData[itemId][currentSlot] =
                                    [
                                      // eslint-disable-next-line max-len
                                      ...mappedSlotsStandaloneData[itemId][currentSlot],
                                      currentKey,
                                    ];
                                hasStandaloneSlotFilled = true;
                                lastSingleSlotFilled = currentSlot;
                              } else if (isPinnedSlot &&
                                  currentSlot === currentKey) {
                                mappedSlotsStandaloneData[itemId][pinnedSlot] =
                                    [pinnedSlot];
                                hasStandaloneSlotFilled = true;
                                lastSingleSlotFilled = currentSlot;
                              } else if (isPinnedSlot &&
                                  pinnedSlot === maxSlot &&
                                  currentSlot !== currentKey) {
                                mappedSlotsStandaloneData[itemId][1] = [
                                  ...mappedSlotsStandaloneData[itemId][1],
                                  currentKey,
                                ];
                                hasStandaloneSlotFilled = true;
                                lastSingleSlotFilled = 1;
                              }

                              if (hasStandaloneSlotFilled) {
                                hasStandaloneSlotFilled = false;
                                if (lastSingleSlotFilled % maxSlot === 0 &&
                                  itemsObj.length > 1) {
                                  lastSingleSlotFilled = null;
                                }
                                return false;
                              }
                            });
                          }
                        });
                      }
                    });

                  if (Object.keys(mappedSlotsGroupData).length > 0 &&
                    Object.values(mappedSlotsGroupData).length > 0) {
                    $.each(Object.keys(mappedSlotsGroupData),
                      function(slotIndex, slotKey) {
                        const groupSlotId = mappedSlotGroup[slotKey];
                        const groupSlotObj = elementGroups.groups[groupSlotId];
                        const groupDataKeys = mappedSlotsGroupData[slotKey];
                        const {
                          maxSlot,
                          pinnedSlot,
                        } = getGroupData(elementGroups.groups, 'items');
                        const $grpContent =
                          $(`<div class="${groupSlotId}"></div>`);

                        if (groupDataKeys.length > 0) {
                          $.each(groupDataKeys, function(dataKeyIndx, dataKey) {
                            if (groupSlotObj?.items.length > 0) {
                              $.each(groupSlotObj?.items,
                                function(itemKey, groupItem) {
                                  renderDataItem(
                                    dataKey,
                                    dataItems[dataKey - 1],
                                    groupItem,
                                    slotKey,
                                    maxSlot,
                                    groupItem.pinSlot,
                                    pinnedSlot,
                                    groupSlotId,
                                    $grpContent,
                                  );
                                });
                            }
                          });

                          $content.append($grpContent.prop('outerHTML'));

                          $grpContent.xiboElementsRender(
                            {
                              ...groupSlotObj,
                              itemsPerPage: maxSlot,
                              numItems: dataItems.length,
                            },
                            $grpContent.find(`.${groupSlotObj.id}--item`),
                          );
                        }
                      });
                  }

                  if (Object.keys(mappedSlotsStandaloneData).length > 0 &&
                    Object.values(mappedSlotsStandaloneData).length > 0) {
                    const standaloneData = mappedSlotsStandaloneData;
                    $.each(Object.keys(standaloneData),
                      function(keyIndx, keyValue) {
                        if (standaloneData
                          .hasOwnProperty(keyValue) &&
                          Object.keys(standaloneData[keyValue]).length > 0) {
                          const {maxSlot} =
                            getGroupData([elementGroups.standalone], keyValue);
                          const itemsGroup = elementGroups.standalone[keyValue];
                          const pinnedSlot = itemsGroup.reduce(function(a, b) {
                            if (b.pinSlot) return b.slot + 1;
                            return a;
                          }, null);

                          const itemGroupProps = itemsGroup.slice(0, 1)[0];
                          $.each(Object.keys(standaloneData[keyValue]),
                            function(slotIndex, slotKey) {
                              const slotObj =
                                elementGroups.standalone[keyValue][slotKey - 1];
                              const dataKeys =
                                standaloneData[keyValue][slotKey];
                              const grpCln = `${keyValue}_page-${slotKey}`;
                              const $grpItem =
                                $(`<div class="${grpCln}"></div>`);

                              if (dataKeys.length > 0) {
                                $.each(dataKeys,
                                  function(dataKeyIndx, dataKey) {
                                    renderDataItem(
                                      dataKey,
                                      dataItems[dataKey - 1],
                                      slotObj,
                                      slotKey,
                                      maxSlot,
                                      slotObj.pinSlot,
                                      pinnedSlot,
                                      grpCln,
                                      $grpItem,
                                    );
                                  });

                                $content.append($grpItem.prop('outerHTML'));

                                $grpItem.xiboElementsRender(
                                  {
                                    ...itemGroupProps,
                                    parentId: grpCln,
                                    itemsPerPage: maxSlot,
                                    numItems: dataItems.length,
                                    id: grpCln,
                                  },
                                  $grpItem.find(`.${grpCln}--item`),
                                );
                              }
                            });
                        }
                      });
                  }
                });
            }
          }
        });
      }
    });
  }
});
