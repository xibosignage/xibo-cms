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

  if (typeof elements !== 'undefined') {
    // Parse out the template with elements.
    $.each(elements, function(_key, widgetElements) {
      if (widgetElements?.length > 0) {
        const $target = $('body');
        let $content = $('#content');

        $.each(widgetElements, function(_widgetElemKey, widgetElement) {
          if (widgetElement?.elements?.length > 0) {
            let playerElements = [];
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
                    marqueeInlineSelector: `.${elementCopy.templateData.id}--item`,
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
                  )
                );
              }
            });

            if (widgetDataInfo !== null && playerElements.length > 0) {
              const elementGroups = playerElements.reduce(
                function(elemGroups, elemGroup){
                    let isGroup = elemGroup.hasOwnProperty('groupId');

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
                        duration: widgetDataInfo.totalDuration,
                        parentId: elemGroup.groupId,
                        slot: elemGroup.slot,
                        items: [],
                      };
                    }

                    // Fill in objects with items
                    if (!isGroup && Object.keys(elemGroups.standalone).length > 0) {
                      elemGroups.standalone[elemGroup.id] = [
                        ...elemGroups.standalone[elemGroup.id],
                        elemGroup,
                      ];
                    }
                    if (isGroup && Object.keys(elemGroups.groups).length > 0) {
                      elemGroups.groups[elemGroup.groupId] = {
                        ...elemGroups.groups[elemGroup.groupId],
                        items:[
                          ...elemGroups.groups[elemGroup.groupId].items,
                          elemGroup,
                        ],
                      };
                    }

                    return elemGroups;
                }, { standalone: {}, groups: {} });

              getWidgetData(widgetDataInfo)
                .then(function(data) {
                  const {
                    dataItems,
                  } = composeFinalData(widgetDataInfo, data);
                  const groupValues = Object.values(elementGroups.groups);
                  const groupItems = groupValues?.length > 0 ?
                    groupValues.reduce((a, b) => [...a, ...b.items], []) :
                    null;
                  const maxSlot = groupItems === null ?
                    1 :
                    Math.max(...groupItems.map(function(elem) {
                      return elem?.slot || 0;
                    }),
                  ) + 1;
                  const renderDataItems = function(data, item, slot, groupId, $groupContent){
                    // For each data item, parse it and add it to the content;
                    let templateAlreadyAdded = false;

                    $.each(data, function(_dataKey, dataItem) {
                      if (item.hasOwnProperty('hbs') &&
                        typeof item.hbs === 'function'
                      ) {
                        const extendDataWith = transformer
                          .getExtendedDataKey(item.dataOverrideWith);

                        if (extendDataWith !== null &&
                          dataItem.hasOwnProperty(extendDataWith)
                        ) {
                          dataItem[item.dataOverride] = dataItem[extendDataWith];
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
                              item.templateData,
                            );
                          }
                        }

                        if (_dataKey >= slot && _dataKey < data?.length) {
                          const currentSlot = slot + 1;
                          const currentKey = _dataKey + 1;
                          const moduloEq = currentSlot === maxSlot ?
                              0 : currentSlot;
                          const usedDataKey = currentKey % maxSlot === moduloEq ? currentKey : null;
                          const $groupContentItem = usedDataKey !== null ?
                              $(`<div class="${groupId}--item" data-group-key="${usedDataKey}"></div>`) :
                              null;

                          if (usedDataKey !== null && $groupContentItem !== null) {
                            if ($groupContent &&
                              $groupContent.find(
                                `.${groupId}--item[data-group-key=${currentKey}]`
                              ).length === 0
                            ) {
                              $groupContent.append($groupContentItem);
                            }

                            const $itemContainer = $groupContent.find(
                              `.${groupId}--item[data-group-key="${usedDataKey}"]`
                            );

                            $itemContainer.append(
                              renderElement(
                                item.hbs,
                                Object.assign(
                                  item.templateData,
                                (String(item.dataOverride).length > 0 &&
                                    String(item.dataOverrideWith).length > 0) ?
                                      dataItem : { data: dataItem },
                                ))
                            );
                          }
                        }
                        templateAlreadyAdded = true;
                      }
                    });

                    if (templateAlreadyAdded) {
                      // Handle the rendering of the template
                      if (item.dataOverride &&
                        typeof window[
                          `onTemplateRender_${item.dataOverride}`
                        ] === 'function'
                      ) {
                        const onTemplateRender = window[
                          `onTemplateRender_${item.dataOverride}`];

                        onTemplateRender && onTemplateRender(
                          item.elementId,
                          $target,
                          $content.find(`.${item.uniqueID}--item`),
                          item,
                          widgetDataInfo?.meta,
                        );
                      }
                    }
                  };

                  // Parse group of elements
                  $.each(Object.keys(elementGroups.groups), function(groupIndex, groupId){
                    if (elementGroups.groups.hasOwnProperty(groupId)) {
                      const elemGroup = elementGroups.groups[groupId];
                      const $groupContent = $(`<div class="${groupId}"></div>`);

                      if (elemGroup?.items.length > 0) {
                        $.each(elemGroup?.items, function(itemKey, groupItem){
                          renderDataItems(dataItems, groupItem, elemGroup.slot, groupId, $groupContent);
                        });

                        $content.append($groupContent.prop('outerHTML'));

                        $groupContent.xiboElementsRender(
                          elemGroup,
                          $groupContent.find(`.${elemGroup.id}--item`),
                        );
                      }
                    }
                  });

                  // Parse standalone elements
                  $.each(Object.keys(elementGroups.standalone), function(itemIndex, itemId) {
                    if (elementGroups.standalone.hasOwnProperty(itemId)) {
                      const itemsObj = elementGroups.standalone[itemId];
                      const $groupContent = $(`<div class="${itemId}"></div>`);

                      if (itemsObj.length > 0) {
                        const itemGroupProps = itemsObj.slice(0, 1)[0];
                        $.each(itemsObj, function(itemKey, itemObj){
                          const groupContentID = `${itemId}_page-${itemObj.slot}`;
                          const $groupContentItem = $(`<div class="${groupContentID}"></div>`);
                          renderDataItems(
                            dataItems,
                            itemObj,
                            itemObj.slot,
                            groupContentID,
                            $groupContentItem,
                          );

                          $content.append($groupContentItem.prop('outerHTML'));

                          $groupContentItem.xiboElementsRender(
                            {
                              ...itemGroupProps,
                              parentId: groupContentID,
                              id: groupContentID,
                            },
                            $groupContentItem.find(`.${groupContentID}--item`),
                          );
                        });
                      }
                    }
                  });
                });
            }
          }
        });
      }
    });
  }
});
