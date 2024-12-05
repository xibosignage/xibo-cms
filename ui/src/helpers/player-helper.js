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

  this.getPinnedSlots = function(dataSlots) {
    return Object.keys(dataSlots)
      .reduce(function(a, b) {
        const dataSlot = dataSlots[b];
        if (dataSlot.hasPinnedSlot) return [...a, dataSlot.slot];
        return a;
      }, []);
  };

  this.getPinnedItems = function(dataSlotItems) {
    if (Object.values(dataSlotItems).length === 0) {
      return dataSlotItems;
    }

    return Object.keys(dataSlotItems).reduce(function(items, itemKey) {
      const item = dataSlotItems[itemKey];

      if (item.pinSlot) {
        items[itemKey] = item;
      }

      return items;
    }, {});
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

  /**
   * Gets minimum and maximum slot
   * If minSlot is zero, it means it's not a data slot
   * @param {Array} collection
   * @return {{minSlot: (number|number), maxSlot: (number|number)}}
   */
  this.getMinAndMaxSlot = function(collection) {
    const minValue = 1;
    const getSlots = (items) => items.map(function(elem) {
      return elem?.slot + 1 || 0;
    });
    const minSlot = collection === null ?
      minValue :
      Math.min(...getSlots(collection));
    const maxSlot = collection === null ?
      minValue :
      Math.max(...getSlots(collection));

    return {
      minSlot,
      maxSlot,
    };
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

      // Handle special cases where data field name for override
      // that's the same as template variable
      // E.g. When a dataset column is "text" and the element is using
      // text element, extended or not
      if (props.isExtended) {
        if (props.type === 'dataset' &&
          props.hasOwnProperty('datasetField') &&
          dataItem.hasOwnProperty(props.datasetField)
        ) {
          props[props.dataOverride] = dataItem[props.datasetField];
        } else {
          const extendWith =
            transformer.getExtendedDataKey(props.dataOverrideWith);
          if (props.dataOverride === extendWith &&
            dataItem.hasOwnProperty(extendWith)
          ) {
            props[props.dataOverride] = dataItem[extendWith];
          }
        }
      }

      const $elementContent = $(self.renderElement(
        item.hbs,
        props,
      ));

      // Add style scope to container
      const $elementContentContainer = $('<div>');
      $elementContentContainer.append($elementContent).attr(
        'data-style-scope',
        'element_' +
        props.type + '__' +
        props.id,
      );

      $itemContainer.append(
        $elementContentContainer,
      );

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
