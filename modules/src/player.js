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

  /**
   * Initialize the player with static templates
   * @param {object} $template
   * @param {object} $content
   * @param {boolean} moduleTemplate
   * @param {object} hbs
   * @param {object} widget
   * @param {Array} dataItems
   * @param {boolean} showError
   * @param {*} data
   */
  function initStaticTemplates(
    $template,
    $content,
    moduleTemplate,
    hbs,
    widget,
    dataItems,
    showError,
    data,
  ) {
    widget.items = [];
    const $target = $('body');

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

    // Run the onDataLoad function if it exists
    if (typeof window['onDataLoad_' + widget.widgetId] === 'function') {
      dataItems = window['onDataLoad_' + widget.widgetId](
        dataItems,
        widget.meta,
        widget.properties,
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
      if (hbs) {
        $content.append(hbs(item));

        // IF we added item template
        templateAlreadyAdded = true;
      }

      // Add item to the widget object
      (item) && widget.items.push(item);
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

  /**
   * Initialize the player with elements
   * @param {object} widget
   */
  function initPlayerElements(widget) {
    // Parse out the template with elements.
    const {
      data,
      elements,
      groupSlotsData,
      standaloneSlotsData,
      templateId,
      widgetId,
      url,
      meta,
    } = widget;
    widget.items = [];

    if (Object.keys(elements.groups).length > 0 ||
    Object.keys(elements.standalone).length > 0) {
      const $content = $('#content');
      const renderElement = (hbs, data, isStatic) => {
        const hbsTemplate = hbs(
          Object.assign(data, globalOptions),
        );
        let topPos = data.top;
        let leftPos = data.left;

        if (data.group) {
          if (data.group.isMarquee) {
            topPos = (data.top - data.group.top);
            leftPos = (data.left - data.group.left);
          } else {
            if (data.top >= data.group.top) {
              topPos = (data.top - data.group.top);
            }
            if (data.left >= data.group.left) {
              leftPos = (data.left - data.group.left);
            }
          }
        }

        let cssStyles = {
          height: data.height,
          width: data.width,
          position: 'absolute',
          top: topPos,
          left: leftPos,
          zIndex: data.layer,
          transform: `rotate(${data?.rotation || 0}deg)`,
        };

        if (isStatic) {
          cssStyles = {
            ...cssStyles,
            top: data.top,
            left: data.left,
            zIndex: data.layer,
          };
        }

        if (!data.isGroup && data.dataOverride === 'text' &&
          data.group.isMarquee &&
          (data.effect === 'marqueeLeft' || data.effect === 'marqueeRight')) {
          cssStyles = {
            ...cssStyles,
            position: 'static',
            top: 'unset',
            left: 'unset',
            width: 'auto',
            display: 'inline-block',
          };
        }

        return $(hbsTemplate).first()
          .attr('id', data.elementId)
          .addClass(`${data.uniqueID}--item`)
          .css(cssStyles)
          .prop('outerHTML');
      };

      const renderDataItem = function(
        isGroup,
        dataItemKey,
        dataItem,
        item,
        slot,
        maxSlot,
        isPinSlot,
        pinnedSlot,
        groupId,
        $groupContent,
        groupObj,
      ) {
        const $groupContentItem = $(`<div class="${groupId}--item"
            data-group-key="${dataItemKey}"></div>`);
        const groupKey = '.' + groupId +
            '--item[data-group-key=%key%]';

        // For each data item, parse it and add it to the content;
        if (item.hasOwnProperty('hbs') &&
            typeof item.hbs === 'function' && dataItemKey !== 'empty'
        ) {
          const extendDataWith = transformer
            .getExtendedDataKey(item.dataOverrideWith);

          if (extendDataWith !== null &&
            dataItem.hasOwnProperty(extendDataWith)
          ) {
            dataItem[item.dataOverride] = dataItem[extendDataWith];
          }

          // Handle special case for setting data for the player
          if (item.type === 'dataset' && Object.keys(dataItem).length > 0) {
            if (item.dataOverride !== null &&
              item.templateData?.datasetField !== undefined
            ) {
              item[item.dataOverride] =
                dataItem[item.templateData.datasetField];

              // Change value in templateData if exists
              if (item.templateData.hasOwnProperty(item.dataOverride)) {
                item.templateData[item.dataOverride] =
                  dataItem[item.templateData.datasetField];
              }
            }
          }

          if (typeof window[
            `onElementParseData_${item.templateData.id}`
          ] === 'function') {
            dataItem[item.dataOverride] =
              window[`onElementParseData_${item.templateData.id}`](
                dataItem[extendDataWith],
                {...item.templateData, data: dataItem},
              );
          }

          let groupItemStyles = {
            width: groupObj.width,
            height: groupObj.height,
          };

          if (groupObj && groupObj.isMarquee) {
            groupItemStyles = {
              ...groupItemStyles,
              position: 'relative',
              display: 'flex',
              flexShrink: '0',
            };
          }

          $groupContentItem.css(groupItemStyles);

          if ($groupContent &&
            $groupContent.find(
              groupKey.replace('%key%', dataItemKey),
            ).length === 0) {
            $groupContent.append($groupContentItem);
          }

          let isSingleElement = false;

          if (!isGroup && item.dataOverride === 'text' &&
              groupObj.isMarquee) {
            if (item.effect === 'marqueeLeft' ||
                item.effect === 'marqueeRight') {
              if ($groupContent.find(
                groupKey.replace('%key%', dataItemKey),
              ).length === 1) {
                $groupContent.find(
                  groupKey.replace('%key%', dataItemKey),
                ).remove();
              }
              isSingleElement = true;
            } else if (item.effect === 'marqueeDown' ||
              item.effect === 'marqueeUp') {
              isSingleElement = false;
            }
          }

          const $itemContainer = isSingleElement ?
            $groupContent : $groupContent.find(
              groupKey.replace('%key%', dataItemKey),
            );

          $itemContainer.append(
            renderElement(
              item.hbs,
              Object.assign(
                item.templateData,
                {isGroup},
                (String(item.dataOverride).length > 0 &&
                  String(item.dataOverrideWith).length > 0) ?
                  dataItem : {data: dataItem},
                {group: groupObj},
              ),
            ),
          );

          let onTemplateRender;
          const itemID =
            item.uniqueID || item.templateData?.uniqueID;

          // Check if onTemplateRender for child template is isExtended
          // And onTemplateRender is defined on child, then use it
          // Else, use parent onTemplateRender
          if (item.isExtended && typeof window[
            `onTemplateRender_${item.templateData.id}`
          ] === 'function') {
            onTemplateRender =
              window[`onTemplateRender_${item.templateData.id}`];
          } else if (item.isExtended && typeof window [
            `onTemplateRender_${item.dataOverride}`
          ] === 'function') {
            onTemplateRender =
              window[`onTemplateRender_${item.dataOverride}`];
          } else if (!item.isExtended) {
            onTemplateRender =
              window[`onTemplateRender_${item.templateData.id}`];
          }

          // Handle the rendering of the template
          (onTemplateRender) && onTemplateRender(
            item.elementId,
            $itemContainer.find(`.${itemID}--item`),
            $content.find(`.${itemID}--item`),
            {item, ...item.templateData, data: dataItem},
            meta,
          );
        } else {
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

          $itemContainer.append('');
        }
      };

      // Parse the data if there is a parser function
      if (typeof window['onParseData_' + widgetId] === 'function') {
        $.each(data, function(_key, item) {
          item = window['onParseData_' + widgetId](
            item,
            widget.properties,
            widget.meta,
          );
        });
      }

      if (Object.keys(groupSlotsData).length > 0 &&
        Object.values(groupSlotsData).length > 0) {
        const {
          maxSlot,
          pinnedSlot,
        } = PlayerHelper.getGroupData(elements.groups, 'items');

        $.each(Object.keys(groupSlotsData), function(slotIndex, slotKey) {
          const groupSlotId = widget.mappedSlotGroup[slotKey];
          const groupSlotObj = elements.groups[groupSlotId];
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
                    renderDataItem(
                      true,
                      dataKey,
                      dataKey === 'empty' ?
                        dataKey : {...(data[dataKey - 1] || {})},
                      groupItem,
                      slotKey,
                      maxSlot,
                      groupItem.pinSlot,
                      pinnedSlot,
                      groupSlotId,
                      $grpContent,
                      {...groupSlotObj, isMarquee},
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
                // eslint-disable-next-line max-len
                $(`<div class="${groupSlotObj.id}--marquee scroll"></div>`);

              $scroller.css({
                display: 'flex',
                height: groupSlotObj.height,
              });

              $grpContent.wrapInner($scroller.prop('outerHTML'));
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

            widget.items.push($grpContent);
          }
        });
      }

      if (Object.keys(standaloneSlotsData).length > 0 &&
        Object.values(standaloneSlotsData).length > 0) {
        const standaloneData = standaloneSlotsData;
        const standaloneElems = elements.standalone;

        $.each(Object.keys(standaloneData),
          function(keyIndx, keyValue) {
            if (standaloneData.hasOwnProperty(keyValue) &&
              Object.keys(standaloneData[keyValue]).length > 0 &&
              templateId !== null && url !== null
            ) {
              const {maxSlot} =
                PlayerHelper.getGroupData(
                  [standaloneElems],
                  keyValue,
                );
              const itemsGroup = standaloneElems[keyValue];
              const pinnedSlot = itemsGroup.reduce(function(a, b) {
                if (b.pinSlot) return b.slot + 1;
                return a;
              }, null);

              $.each(Object.keys(standaloneData[keyValue]),
                function(slotIndex, slotKey) {
                  const slotObj =
                    standaloneElems[keyValue][slotKey - 1];
                  const dataKeys =
                    standaloneData[keyValue][slotKey];
                  const grpCln = `${keyValue}_page-${slotKey}`;
                  const $grpItem =
                    $(`<div class="${grpCln}"></div>`);
                  const isMarquee =
                    PlayerHelper.isMarquee(slotObj.effect);

                  if (dataKeys.length > 0) {
                    $.each(dataKeys,
                      function(dataKeyIndx, dataKey) {
                        renderDataItem(
                          false,
                          dataKey,
                          dataKey === 'empty' ?
                            dataKey : {...(data[dataKey - 1] || {})},
                          slotObj,
                          slotKey,
                          maxSlot,
                          slotObj.pinSlot,
                          pinnedSlot,
                          grpCln,
                          $grpItem,
                          {...slotObj, isMarquee},
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

                    widget.items.push($grpItem);
                  }
                });
            } else {
              // Global elements should fall here but should validate
              if (Object.keys(standaloneElems).length > 0 &&
                standaloneElems.hasOwnProperty(keyValue) &&
                standaloneElems[keyValue].length > 0
              ) {
                $.each(standaloneElems[keyValue],
                  function(keyIndex, standaloneElem) {
                    (standaloneElem.hbs) && $content.append(
                      renderElement(
                        standaloneElem.hbs,
                        standaloneElem.templateData,
                        true,
                      ),
                    );
                  });
              }
            }
          });
      }

      // Run defaultScaler for global elements
      defaultScaler(
        widget.widgetId,
        $content,
        widget.items,
        Object.assign(
          widget.properties,
          globalOptions,
          {duration: widget.duration},
        ),
        meta,
      );
    }
  }

  PlayerHelper
    .init(widgetData, elements)
    .then(({widgets}) => {
      if (Object.keys(widgets).length > 0) {
        Object.keys(widgets).forEach(function(widgetKey) {
          const widget = widgets[widgetKey];

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

          if ((widget.templateId === 'elements' ||
              (widget.templateId === null && (
                Object.keys(widget.elements.groups).length > 0 ||
                Object.keys(widget.elements.standalone).length > 0
              ))) && typeof widget.elements !== 'undefined') {
            initPlayerElements(widget);
          } else {
            initStaticTemplates(
              $template,
              $content,
              moduleTemplate,
              hbs,
              widget,
              widget.data,
              widget.showError,
              widget.data,
            );
          }
        });
      }
    });
});
