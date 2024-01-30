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
const PlayerHelper = function() {
  // Check the query params to see if we're in editor mode
  const self = this;

  /**
   * Decorate slots with data
   * @param {Array} dataItems
   * @param {Object} currentWidget
   * @return {Object} currentWidget
   */
  this.decorateCollectionSlots = function(dataItems, currentWidget) {
    self.getStandaloneSlotsData(dataItems, currentWidget);
    self.getGroupSlotsData(dataItems, currentWidget);

    return currentWidget;
  };

  this.getStandaloneSlotsData = function(data, currentWidget) {
    const standalone = currentWidget.elements.standalone;
    const objKeys = Object.keys(standalone);

    if (objKeys.length > 0) {
      objKeys.forEach(function(objKey) {
        if (standalone.hasOwnProperty(objKey)) {
          const itemObj = standalone[objKey];

          if (!currentWidget.standaloneSlotsData.hasOwnProperty(objKey)) {
            currentWidget.standaloneSlotsData[objKey] = {};
          }

          if (itemObj.length > 0) {
            currentWidget.standaloneSlotsData[objKey] =
              itemObj.reduce(function(a, b, slotIndex) {
                a[(b.slot ?? slotIndex) + 1] = [];
                return {...a};
              }, {});
          }
        }

        const pinnedSlots = Object.keys(standalone[objKey])
          .reduce(function(a, b) {
            const elem = standalone[objKey][b];
            if (elem.pinSlot) return [...a, elem.slot + 1];
            return a;
          }, []);

        self.composeSlotsData(
          'standalone', data, standalone, currentWidget, {
            objKey,
            lastSlotFilled: null,
          });

        currentWidget.singlePinnedSlots = pinnedSlots;

        currentWidget.standaloneSlotsData[objKey] =
          self.composeRepeatNonRepeatData(
            currentWidget.standaloneSlotsData[objKey],
            pinnedSlots,
            currentWidget.isRepeatData,
          );
      });
    }
  };

  this.getGroupSlotsData = function(data, currentWidget) {
    const elementGroups = currentWidget.elements.groups;
    currentWidget.mappedSlotGroup = {};

    if (Object.keys(elementGroups).length > 0) {
      const {pinnedSlots} = self.getGroupData(
        elementGroups,
        'items',
        false,
      );

      currentWidget.mappedSlotGroup = self.mapSlot(
        Object.values(elementGroups), 'id',
      );
      currentWidget.groupSlotsData =
        Object.keys(currentWidget.mappedSlotGroup).reduce(function(a, b) {
          a[b] = [];
          return {...a};
        }, {});

      self.composeSlotsData('groups', data, elementGroups, currentWidget, {
        lastSlotFilled: null,
      });

      currentWidget.groupPinnedSlots = pinnedSlots;

      currentWidget.groupSlotsData = self.composeRepeatNonRepeatData(
        currentWidget.groupSlotsData,
        pinnedSlots,
        currentWidget.isRepeatData,
      );
    }
  };

  /**
   * Compose slots data
   * @param {string} type group|standalone
   * @param {array} dataItems Widget data
   * @param {object} collection {groups, standalone}
   * @param {Object} currentWidget Widget
   * @param {object?} item Optional item object
   */
  this.composeSlotsData = function(
    type,
    dataItems,
    collection,
    currentWidget,
    item,
  ) {
    const isStandalone = type === 'standalone';
    const groupData = self.getGroupData(
      isStandalone ? [collection] : collection,
      isStandalone ? item.objKey : 'items',
      isStandalone,
    );
    const maxSlot = groupData.maxSlot;
    const pinnedSlots = groupData.pinnedSlots;

    if (dataItems.length > 0) {
      const lastSlotFilled = {};

      lastSlotFilled[type] = null;

      dataLoop: for (const [dataItemKey] of Object.entries(dataItems)) {
        const hasSlotFilled = {};
        const currentKey = parseInt(dataItemKey) + 1;
        const currCollection = isStandalone ?
          collection[item.objKey] : Object.keys(collection);

        hasSlotFilled[type] = false;

        // Stop iteration through dataItems when all pinned slots are filled
        // and maxSlot = pinnedSlots.length
        if (lastSlotFilled[type] === null &&
          pinnedSlots.length === maxSlot &&
          currentKey > maxSlot
        ) {
          break;
        }

        if (currentWidget.isMixed && lastSlotFilled[type] === null &&
          currCollection.length === maxSlot &&
          currentKey > maxSlot
        ) {
          break;
        }

        if (currentWidget.isMixed && lastSlotFilled[type] === null &&
          isStandalone &&
          pinnedSlots.length === currCollection.length &&
          currentKey > maxSlot
        ) {
          break;
        }

        for (const [, itemValue] of Object.entries(currCollection)) {
          const itemObj = isStandalone ?
            itemValue : collection[itemValue];
          const isPinnedSlot = itemObj.pinSlot;
          const currentSlot = itemObj.slot + 1;

          // Skip if currentKey is less than the currentSlot
          if (currentKey < currentSlot) {
            continue dataLoop;
          }

          if (!isPinnedSlot && !pinnedSlots.includes(currentKey)) {
            // If lastSlotFilled is filled and is <= to currentSlot
            // Then, move to next slot
            if (lastSlotFilled[type] !== null &&
              currentSlot <= lastSlotFilled[type]
            ) {
              continue;
            }

            if (isStandalone) {
              const currentSlotItem =
                currentWidget.standaloneSlotsData[item.objKey];

              if (!currentSlotItem.hasOwnProperty(currentSlot)) {
                currentSlotItem[currentSlot] = [];
              }

              if (!currentSlotItem[currentSlot].includes(currentKey)) {
                currentSlotItem[currentSlot] = [
                  ...currentSlotItem[currentSlot],
                  currentKey,
                ];
              }

              currentWidget.standaloneSlotsData[item.objKey] = {
                ...currentSlotItem,
              };
            } else {
              currentWidget.groupSlotsData[currentSlot] = [
                ...currentWidget.groupSlotsData[currentSlot],
                currentKey,
              ];
            }

            hasSlotFilled[type] = true;
            lastSlotFilled[type] = currentSlot;
          } else if (!isPinnedSlot && pinnedSlots.includes(currentKey)) {
            if (lastSlotFilled[type] !== null &&
              currentSlot <= lastSlotFilled[type]
            ) {
              continue;
            }
          } else if (isPinnedSlot &&
              currentSlot === currentKey &&
              pinnedSlots.includes(currentSlot)
          ) {
            if (isStandalone) {
              currentWidget.standaloneSlotsData[item.objKey][currentSlot] =
                  [currentKey];
            } else {
              currentWidget.groupSlotsData[currentSlot] = [currentKey];
            }

            hasSlotFilled[type] = true;
            lastSlotFilled[type] = currentSlot;
          } else if (isPinnedSlot && pinnedSlots.length > 0 &&
              Math.max(...pinnedSlots) === maxSlot &&
              currentSlot !== currentKey
          ) {
            if (lastSlotFilled[type] !== null &&
              currentSlot <= lastSlotFilled[type]
            ) {
              continue;
            }

            if (isStandalone) {
              currentWidget.standaloneSlotsData[item.objKey][1] = [
                ...currentWidget.standaloneSlotsData[item.objKey][1],
                currentKey,
              ];
            } else {
              currentWidget.groupSlotsData[1] = [
                ...currentWidget.groupSlotsData[1],
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
   * @param {array} pinnedSlots
   * @param {boolean} isRepeat
   * @return {object} slotsData
   */
  this.composeRepeatNonRepeatData = function(
    slotsData,
    pinnedSlots,
    isRepeat,
  ) {
    // Copy slotsData
    const groupSlotsData = {...slotsData};
    // Remove pinnedSlot from the object
    if (pinnedSlots.length > 0) {
      pinnedSlots.forEach(function(pinnedSlot) {
        if (slotsData.hasOwnProperty(pinnedSlot)) {
          delete groupSlotsData[pinnedSlot];
        }
      });
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

  /**
   * Get items by Key
   * @param {Object} items
   * @param {String} itemsKey
   * @param {Boolean} isStandalone
   *
   * @return {Array}
   */
  this.getItemsByKey = (items, itemsKey, isStandalone) => {
    if (isStandalone && items.hasOwnProperty(itemsKey) &&
        Object.keys(items[itemsKey]).length > 0
    ) {
      return Object.keys(items[itemsKey]).reduce(function(a, itemKey) {
        return [...a, items[itemsKey][itemKey]];
      }, []);
    }

    if (items.hasOwnProperty(itemsKey)) {
      return items[itemsKey];
    }

    return [];
  };

  this.getMaxMinSlot = (objectsArray, itemsKey, isStandalone) => {
    const minValue = 1;
    const groupItems = objectsArray?.length > 0 ?
      objectsArray.reduce(
        (a, b) => {
          return [...a, ...self.getItemsByKey(b, itemsKey, isStandalone)];
        }, []) : null;
    const getSlots = (items) => items.map(function(elem) {
      return elem?.slot || 0;
    });
    const minSlot = groupItems === null ?
      minValue :
      Math.min(...getSlots(groupItems)) + 1;
    const maxSlot = groupItems === null ?
      minValue :
      Math.max(...getSlots(groupItems)) + 1;

    return {
      minSlot,
      maxSlot,
    };
  };

  this.getGroupData = function(
    groupsData,
    slotItemsKey,
    isStandalone,
  ) {
    let groupValues = Object.values(groupsData);
    const {maxSlot, minSlot} =
      self.getMaxMinSlot(groupValues, slotItemsKey, isStandalone);

    if (isStandalone) {
      groupValues =
        self.getItemsByKey(groupsData[0], slotItemsKey, isStandalone);
    }

    const pinnedSlots = groupValues.reduce(
      function(a, b) {
        if (b.pinSlot) return [...a, b.slot + 1];
        return a;
      }, []);

    return {
      groupValues,
      maxSlot,
      minSlot,
      pinnedSlots,
    };
  };

  this.mapSlot = function(items, key) {
    if (items?.length > 0) {
      const mappedSlots = {};
      items.forEach(function(item) {
        if (item.hasOwnProperty('slot') && item.slot !== undefined &&
          !mappedSlots.hasOwnProperty(item.slot + 1)
        ) {
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

  this.renderElement = function(hbs, props, isStatic) {
    const hbsTemplate = hbs(Object.assign(props, globalOptions));
    let topPos = props.top;
    let leftPos = props.left;

    if (props.group) {
      if (props.group.isMarquee) {
        topPos = (props.top - props.group.top);
        leftPos = (props.left - props.group.left);
      } else {
        if (props.top >= props.group.top) {
          topPos = (props.top - props.group.top);
        }
        if (props.left >= props.group.left) {
          leftPos = (props.left - props.group.left);
        }
      }
    }

    let cssStyles = {
      height: props.height,
      width: props.width,
      position: 'absolute',
      top: topPos,
      left: leftPos,
      zIndex: props.layer,
      transform: `rotate(${props?.rotation || 0}deg)`,
    };

    if (isStatic) {
      cssStyles = {
        ...cssStyles,
        top: props.top,
        left: props.left,
        zIndex: props.layer,
      };
    }

    if (!props.isGroup && props.dataOverride === 'text' &&
      (props.group && props.group.isMarquee) &&
      (props.effect === 'marqueeLeft' || props.effect === 'marqueeRight')
    ) {
      cssStyles = {
        ...cssStyles,
        position: 'static',
        top: 'unset',
        left: 'unset',
        width: props?.textWrap ? props.width : 'initial',
        display: 'flex',
        flexShrink: '0',
        wordWrap: 'break-word',
      };
    }

    const $renderedElem = $(hbsTemplate).first()
      .attr('id', props.elementId)
      .addClass(`${props.uniqueID}--item`)
      .css(cssStyles);

    if (!props.isGroup && props.dataOverride === 'text' &&
      (props.group && props.group.isMarquee) &&
      (props.effect === 'marqueeLeft' || props.effect === 'marqueeRight')
    ) {
      $renderedElem.get(0).style.removeProperty('white-space');
      $renderedElem.get(0).style.setProperty(
        'white-space',
        props?.textWrap ? 'unset' : 'nowrap',
        'important',
      );
    }

    return $renderedElem.prop('outerHTML');
  };

  this.renderDataItem = function(
    isGroup,
    dataItemKey,
    dataItem,
    item,
    slot,
    maxSlot,
    isPinSlot,
    pinnedSlots,
    groupId,
    $groupContent,
    groupObj,
    meta,
    $content,
  ) {
    const $groupContentItem = $(`<div class="${groupId}--item"
      data-group-key="${dataItemKey}"></div>`);
    const groupKey = '.' + groupId + '--item[data-group-key=%key%]';

    // For each data item, parse it and add it to the content;
    if (item.hasOwnProperty('hbs') &&
      typeof item.hbs === 'function' && dataItemKey !== 'empty'
    ) {
      // const extendDataWith = transformer
      //   .getExtendedDataKey(item.dataOverrideWith);
      //
      // if (extendDataWith !== null &&
      //   dataItem.hasOwnProperty(extendDataWith)
      // ) {
      //   dataItem[item.dataOverride] = dataItem[extendDataWith];
      // }
      //
      // // Handle special case for setting data for the player
      // if (item.type === 'dataset' && Object.keys(dataItem).length > 0) {
      //   if (item.dataOverride !== null &&
      //       item.templateData?.datasetField !== undefined
      //   ) {
      //     item[item.dataOverride] = dataItem[item.templateData.datasetField];
      //
      //     // Change value in templateData if exists
      //     if (item.templateData.hasOwnProperty(item.dataOverride)) {
      //       item.templateData[item.dataOverride] =
      //         dataItem[item.templateData.datasetField];
      //     }
      //   }
      // }
      //
      // if (typeof window[
      //   `onElementParseData_${item.templateData.id}`
      // ] === 'function') {
      //   dataItem[item.dataOverride] =
      //     window[`onElementParseData_${item.templateData.id}`](
      //       dataItem[extendDataWith],
      //       {...item.templateData, data: dataItem},
      //     );
      // }

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
        ).length === 0
      ) {
        $groupContent.append($groupContentItem);
      }

      let isSingleElement = false;

      if (!isGroup && item.dataOverride === 'text' && groupObj.isMarquee) {
        if (item.effect === 'marqueeLeft' || item.effect === 'marqueeRight') {
          if ($groupContent.find(
            groupKey.replace('%key%', dataItemKey)).length === 1
          ) {
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
      const props = Object.assign(
        item.templateData,
        {isGroup},
        (String(item.dataOverride).length > 0 &&
        String(item.dataOverrideWith).length > 0) ?
          dataItem : {data: dataItem},
        {group: groupObj},
      );

      $itemContainer.append(
        self.renderElement(
          item.hbs,
          props,
        ),
      );
      //
      // let onTemplateRender;
      // const itemID = item.uniqueID || item.templateData?.uniqueID;
      //
      // // Check if onTemplateRender for child template is isExtended
      // // And onTemplateRender is defined on child, then use it
      // // Else, use parent onTemplateRender
      // if (item.isExtended && typeof window[
      //   `onTemplateRender_${item.templateData.id}`
      // ] === 'function') {
      //   onTemplateRender = window[`onTemplateRender_${item.templateData.id}`];
      // } else if (item.isExtended && typeof window [
      //   `onTemplateRender_${item.dataOverride}`
      // ] === 'function') {
      //   onTemplateRender = window[`onTemplateRender_${item.dataOverride}`];
      // } else if (!item.isExtended) {
      //   onTemplateRender = window[`onTemplateRender_${item.templateData.id}`];
      // }

      const itemID = item.uniqueID || item.templateData?.uniqueID;
      // Handle the rendering of the template
      (item.onTemplateRender() !== undefined) && item.onTemplateRender()(
        item.elementId,
        $itemContainer.find(`.${itemID}--item`),
        $content.find(`.${itemID}--item`),
        {item, ...item.templateData, data: dataItem},
        meta,
      );
    } else {
      if ($groupContent &&
        $groupContent.find(
          groupKey.replace('%key%', dataItemKey)).length === 0
      ) {
        $groupContent.append($groupContentItem);
      }

      const $itemContainer = $groupContent.find(
        groupKey.replace('%key%', dataItemKey),
      );

      $itemContainer.append('');
    }
  };

  return this;
};

module.exports = new PlayerHelper();
