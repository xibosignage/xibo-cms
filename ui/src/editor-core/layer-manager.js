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

const managerTemplate = require('../templates/layer-manager.hbs');
const renderLayerManagerDebounce = 200;

/**
 * Layer Manager
 * @param {object} parent - Parent object
 * @param {object} container - Container to append the manager to
 * @param {object} viewerContainer - Viewer container to interact with
 */
const LayerManager = function(parent, container, viewerContainer) {
  this.parent = parent;
  this.DOMObject = container;
  this.viewerContainer = viewerContainer;

  this.layerStructure = [];

  this.firstRender = true;
  this.wasDragged = false;

  // Show/Hide ( false by default )
  this.visible = false;

  // Save scroll position
  this.scrollPosition = 0;
};

/**
 * Create structure
 * @return {Promise} - Result of create structure
 */
LayerManager.prototype.createStructure = function() {
  const self = this;
  const promiseArray = [];

  // Reset structure
  self.layerStructure = [];

  const addToLayerStructure = function(layer, object, auxArray = null) {
    const arrayToAdd = (auxArray != null) ?
      auxArray : self.layerStructure;

    if (typeof arrayToAdd[layer] === 'undefined') {
      arrayToAdd[layer] = [];
    }

    const newIdx = arrayToAdd[layer].push(object);

    // Return newly added object
    return arrayToAdd[layer][newIdx-1];
  };

  const parseDuration = function(duration) {
    return (duration == null) ? '' : lD.common.timeFormat(
      duration,
    );
  };

  // Check if object is outside view
  const isOutsideView = function(pos) {
    return (
      pos.left > lD.layout.width ||
      (pos.left + pos.width) < 0 ||
      pos.top > lD.layout.height ||
      (pos.top + pos.height) < 0
    );
  };

  // Get canvas
  const canvasObject = {};

  if (!$.isEmptyObject(self.parent.layout.canvas)) {
    const canvas = self.parent.layout.canvas;

    // Add properties to canvas object
    canvasObject.layer = canvas.zIndex;
    canvasObject.type = 'canvas';
    canvasObject.name = 'Canvas';
    canvasObject.duration = parseDuration(canvas.duration);
    canvasObject.subLayers = [];

    const getGroupInCanvasStructure = function(group, widget) {
      let groupInCanvas = null;
      canvasObject.subLayers.forEach((layer) => {
        layer.forEach((obj) => {
          if (obj.type == 'elementGroup' && obj.id == group.id) {
            groupInCanvas = obj;
          }
        });
      });

      // If there's no group, create it
      if (!groupInCanvas) {
        const module = lD.common.getModuleByType(widget.subType);
        groupInCanvas = addToLayerStructure(
          group.layer,
          {
            type: 'elementGroup',
            name: group.elementGroupName,
            templateName: module.name,
            id: group.id,
            widgetId: 'widget_' + group.regionId + '_' + group.widgetId,
            regionId: 'region_' + group.regionId,
            moduleIcon: module.icon,
            selected: group.selected,
            expanded: group.expanded,
            outsideView: isOutsideView({
              width: group.width,
              height: group.height,
              top: group.top,
              left: group.left,
            }),
            layers: [],
          },
          canvasObject.subLayers,
        );
      }

      return groupInCanvas;
    };

    // Get elements
    if (canvas.widgets) {
      Object.values(canvas.widgets).forEach((widget) => {
        const moduleIcon = lD.common.getModuleByType(widget.subType).icon;
        const elements = Object.values(widget.elements);

        elements.forEach((element) => {
          // If we don't have template yet, push the method to the promise array
          ($.isEmptyObject(element.template)) &&
            promiseArray.push(element.getTemplate());

          // Element has group
          const hasGroup = (element.groupId != undefined);

          // Add to canvas or to group
          if (hasGroup) {
            const group = widget.elementGroups[element.groupId];
            arrayToAddTo = getGroupInCanvasStructure(group, widget).layers;
          } else {
            arrayToAddTo = canvasObject.subLayers;
          }

          addToLayerStructure(
            element.layer,
            {
              type: 'element',
              name: element.elementName,
              templateName: element.template.title,
              widgetId: 'widget_' + element.regionId + '_' + element.widgetId,
              regionId: 'region_' + element.regionId,
              // Element has parent widget duration
              duration: parseDuration(widget.getDuration()),
              id: element.elementId,
              icon: element.template.icon,
              moduleIcon: moduleIcon,
              hasGroup: hasGroup,
              groupId: layerManagerTrans.inGroup
                .replace('%groupId%', element.groupId),
              selected: element.selected,
              outsideView: !hasGroup && isOutsideView({
                width: element.width,
                height: element.height,
                top: element.top,
                left: element.left,
              }),
            },
            arrayToAddTo,
          );
        });
      });
    }

    // Add canvas to structure
    addToLayerStructure(canvas.zIndex, {
      type: 'canvas',
      name: 'Canvas',
      duration: parseDuration(canvas.duration),
      layers: canvasObject.subLayers,
    });

    // If we have a background image for the layout
    // Add it to structure
    if (
      self.parent.layout.backgroundImage &&
      self.parent.layout.backgroundzIndex != null
    ) {
      addToLayerStructure(self.parent.layout.backgroundzIndex, {
        type: 'layoutBackground',
        name: 'Layout Background',
      });
    }
  }

  // Get static widgets and playlists
  Object.values(self.parent.layout.regions).forEach((region) => {
    if (region.subType === 'playlist') {
      addToLayerStructure(region.zIndex, {
        type: 'playlist',
        name: (region.name != '') ?
          region.name : `[${layerManagerTrans.playlist}]`,
        duration: parseDuration(region.duration),
        id: region.id,
        selected: region.selected,
        outsideView: isOutsideView(region.dimensions),
      });
    } else {
      // If we have an empty zone or frame, show it on the control
      if ($.isEmptyObject(region.widgets)) {
        addToLayerStructure(region.zIndex, {
          type: region.subType,
          name: (region.name != '') ?
            region.name : `[${layerManagerTrans[region.subType]}]`,
          duration: parseDuration(region.duration),
          id: region.id,
          selected: region.selected,
          outsideView: isOutsideView(region.dimensions),
        });
      }

      Object.values(region.widgets).forEach((widget) => {
        addToLayerStructure(region.zIndex, {
          type: 'staticWidget',
          name: (widget.widgetName != '') ?
            `"${widget.widgetName}"` : widget.moduleName,
          duration: parseDuration(region.duration),
          icon: widget.getIcon(),
          moduleName: widget.moduleName,
          id: widget.id,
          auxId: widget.regionId,
          selected: widget.selected,
          outsideView: isOutsideView(region.dimensions),
        });
      });
    }
  });

  // Return the promiseArray or true if we don't need to fulfill promises
  return (promiseArray.length === 0) ? Promise.resolve(true) :
    Promise.resolve(promiseArray);
};

/**
 * Set visibility
 * @param {boolean=} force Force visible to on/off
 * @param {boolean=} savePrefs Save preferences?
 */
LayerManager.prototype.setVisible = function(force, savePrefs = true) {
  // Change manager flag
  this.visible = (force != undefined) ? force : !this.visible;

  // Set button status
  lD.viewer.DOMObject.siblings('#layerManagerBtn')
    .toggleClass('active', this.visible);

  // Render manager (and reset position)
  this.render(true);

  // Save editor preferences
  (savePrefs) && lD.savePrefs();
};

/**
 * Update layer manager with debounce
 */
LayerManager.prototype.renderWithDebounce = _.debounce(function(
  reset,
) {
  lD.viewer.layerManager.render(reset);
}, renderLayerManagerDebounce);

/**
 * Render Manager
 */
/**
 * Render Manager
 * @param {boolean=} reset Reset to default state
 */
LayerManager.prototype.render = function(reset) {
  const self = this;

  // Create layers data structure
  this.createStructure().then((res) => {
    if (Array.isArray(res)) {
      Promise.all(res).then(() => {
        // Render again after all templates are loaded
        self.render(true);
      });
    } else {
      // Only render if it's visible
      if (this.visible != false) {
        // Compile layout template with data
        const html = managerTemplate({
          trans: layerManagerTrans,
          layerStructure: this.layerStructure,
        });

        // Append layout html to the main div
        this.DOMObject.html(html);

        // Check if we have multi selected elements in the viewer
        const $selectedInViewer = self.viewerContainer.find('.selected');
        if ($selectedInViewer.length > 1) {
          $selectedInViewer.each((_idx, el) => {
            // If region is frame, get widget ID instead
            const elId = ($(el).hasClass('designer-region-frame')) ?
              $(el).find('.designer-widget').attr('id') :
              $(el).attr('id');

            // Mark elements as multi selected in the manager
            self.DOMObject.find('[data-item-id="' + elId + '"]')
              .addClass('multi-selected');
          });
        }

        // Make the layer div draggable
        this.DOMObject.draggable({
          handle: '.layer-manager-header',
          cursor: 'dragging',
          drag: function() {
            self.wasDragged = true;
          },
        });

        // Select items
        this.DOMObject
          .find('.layer-manager-layer-item.selectable:not(.selected)')
          .off('click').on('click', function(ev) {
            const elementData = $(ev.currentTarget).data();
            const elementId = elementData.itemId;
            const $viewerObject = (elementData.type === 'layoutBackground') ?
              self.viewerContainer :
              self.viewerContainer.find('#' + elementId);

            if ($viewerObject.length) {
              // Select in editor
              lD.selectObject({
                target: $viewerObject,
                forceSelect: true,
              });

              // If it's a static widget, we need
              // to give the class to its region
              const $auxTarget = ($viewerObject.hasClass('designer-widget')) ?
                $viewerObject.parents('.designer-region') :
                $viewerObject;

              // Select in viewer
              lD.viewer.selectObject($auxTarget);

              // Mark object with selected from manager class
              $auxTarget.addClass('selected-from-layer-manager');

              // If it's an element, we need to set canvas zIndex
              // to auto for it to show over all other static widgets
              lD.viewer.DOMObject.find('.designer-region-canvas')
                .addClass('canvas-element-selected-from-layer-manager');
            }
          });

        // Handle sorting
        const saveSortDebounced = _.wrap(
          _.memoize(
            () => _.debounce(self.saveSort.bind(self), 500), _.property('type'),
          ),
          (getMemoizedFunc, obj) => getMemoizedFunc(obj)(obj),
        );

        // Sortable in main level
        this.DOMObject.find('.layer-manager-body').sortable({
          axis: 'y',
          items: '.sortable-main',
          containment: 'parent',
          update: function() {
            saveSortDebounced({
              type: 'main',
            });
          },
        });

        // Sortable in canvas
        this.DOMObject.find('.layer-manager-canvas-layers').sortable({
          axis: 'y',
          items: '.sortable-canvas',
          containment: 'parent',
          update: function() {
            saveSortDebounced({
              type: 'canvas',
            });
          },
        });

        // Sortable in element groups
        this.DOMObject.find('.element-group-wrapper')
          .sortable({
            axis: 'y',
            items: '.sortable-element-group',
            containment: 'parent',
            update: function(_ev, ui) {
              saveSortDebounced({
                type: 'element-group',
                auxContainer: $(ui.item[0]).parent(),
              });
            },
          });

        // Handle scroll
        this.DOMObject.find('.layer-manager-body')
          .on('scrollend', function(ev) {
            // Save scroll value
            self.scrollPosition = $(ev.currentTarget).scrollTop();
          });

        // If we have a scroll value, restore it
        if (self.scrollPosition != 0) {
          this.DOMObject.find('.layer-manager-body')
            .scrollTop(self.scrollPosition);
        }

        // Handle close button
        this.DOMObject.find('.close-layer-manager')
          .off('click').on('click', function(ev) {
            self.setVisible(false);
          });

        // Handle extend group button
        this.DOMObject.find('.expand-group-button')
          .off('click').on('click', function(ev) {
            const $elementGroup = $(ev.currentTarget).parents('.element-group');
            self.expandGroup(
              $elementGroup.data('item-id'),
              $elementGroup.data('widget-id'),
            );
          });

        // Show
        this.DOMObject.show();
      } else {
        // Empty container
        this.DOMObject.empty();

        // Hide container
        this.DOMObject.hide();
      }

      // If it's a reset or first run, show next to the button
      if (reset || this.firstRender || !self.wasDragged) {
        this.wasDragged = false;

        self.DOMObject.css('top', 'auto');
        self.DOMObject.css('left', 6);

        // Button height plus offset from bottom and top of the button: 6*2
        const viewerOffsetBottom =
          lD.viewer.DOMObject.siblings('#layerManagerBtn')
            .outerHeight() + (6 * 2);

        // Bottom is calculated by using the element height
        // and the offset from the bottom of the viewer
        self.DOMObject.css(
          'bottom',
          self.DOMObject.outerHeight() + viewerOffsetBottom,
        );
      }

      this.firstRender = false;
    }

    // Scroll to selected
    self.scrollToSelected();
  });
};

/**
 * Scroll to selected item
 */
LayerManager.prototype.scrollToSelected = function() {
  const self = this;
  const $selectedItem = self.DOMObject.find('.selected');

  // Render to selected
  if ($selectedItem.length > 0) {
    // Check if the element is outside the render view
    const $layerManagerContainer =
      self.DOMObject.find('.layer-manager-body');
    const headerHeight = $('.layer-manager-header').height();
    const viewHeight = $layerManagerContainer.height();

    const elemHeight = $selectedItem.height();
    const elemTop = $selectedItem.position().top - headerHeight;
    const elemBottom = elemTop + $selectedItem.height();

    const isVisible =
      (elemTop + elemHeight <= viewHeight) &&
      (elemBottom >= elemHeight);

    if (!isVisible) {
      const scrollAdjust =
        (elemTop + elemHeight > viewHeight) ?
          ((elemTop + elemHeight) - viewHeight) :
          (elemBottom - elemHeight);

      // Scroll to element ( using deltas )
      const viewTop = $layerManagerContainer.scrollTop();
      $layerManagerContainer.scrollTop(
        viewTop + scrollAdjust,
      );
    }
  }
};

/**
 * Expand group elements
 * @param {string} type
 * @param {string} id
 * @return {object} found object or false
 */
LayerManager.prototype.getItemFromLayerStructure = function(type, id) {
  const checkIfTarget = function(item) {
    return (
      type === item.type &&
      id === item.id
    );
  };

  let found = false;

  this.layerStructure.forEach((layer) => {
    layer.forEach((layerItem) => {
      if (layerItem.type === 'canvas') {
        if (checkIfTarget(layerItem)) {
          found = layerItem;
        } else {
          layerItem.layers.forEach((canvasLayer) => {
            canvasLayer.forEach((canvasLayerItem) => {
              if (canvasLayerItem.type === 'elementGroup') {
                if (checkIfTarget(canvasLayerItem)) {
                  found = canvasLayerItem;
                } else {
                  canvasLayerItem.layers.forEach((groupLayer) => {
                    groupLayer.forEach((groupLayerItem) => {
                      if (checkIfTarget(groupLayerItem)) {
                        found = groupLayerItem;
                      }
                    });
                  });
                }
              } else {
                if (checkIfTarget(canvasLayerItem)) {
                  found = canvasLayerItem;
                }
              }
            });
          });
        }
      } else {
        if (checkIfTarget(layerItem)) {
          found = layerItem;
        }
      }
    });
  });

  return found;
};

/**
 * Expand group elements
 * @param {string} groupId Group id to expand
 * @param {string} widgetId Widget id containing the group
 */
LayerManager.prototype.expandGroup = function(
  groupId,
  widgetId,
) {
  const self = this;

  // Find group in the layer structure
  const groupLayerObj = self.getItemFromLayerStructure('elementGroup', groupId);

  // Get element group object
  const groupObj = lD.getObjectByTypeAndId(
    'element-group',
    groupId,
    widgetId,
  );

  // Switch extend property
  groupLayerObj.expanded = (groupLayerObj.expanded == undefined) ?
    false: !groupLayerObj.expanded;

  // Mark main object with extend option
  groupObj.expanded = groupLayerObj.expanded;

  // Render again to show the changes
  self.render();
};

/**
 * Save sorting
 * @param {string} type main, canvas or element-group
 * @param {object} auxContainer if group, get the group to save
 */
LayerManager.prototype.saveSort = function({
  type,
  auxContainer,
} = {}) {
  // Update layer in position form
  const updatePositionForm = function(index, layer) {
    const options = {};
    options[index] = layer;
    lD.propertiesPanel.updatePositionForm(options);
  };

  if (type === 'main') {
    const regionToBeSaved = [];
    let layoutSaving = null;
    lD.viewer.layerManager.DOMObject
      .find('.layer-manager-body > .sortable-main')
      .each((idx, target) => {
        const $target = $(target);
        const targetType = $target.data('type');
        const targetId = (targetType === 'staticWidget') ?
          $target.data('itemAuxId'):
          $target.data('itemId');
        const newLayer = idx;
        let updateOnViewer = '';

        if (targetType === 'layoutBackground') {
          // Only save layout if we have a new value
          if (lD.layout.backgroundzIndex != newLayer) {
            lD.layout.backgroundzIndex = newLayer;

            layoutSaving = lD.layout.saveBackgroundLayer(newLayer);

            if (lD.selectedObject.type === 'layout') {
              // Change value on form
              lD.propertiesPanel.DOMObject
                .find('#input_backgroundzIndex').val(newLayer);

              // Update properties panel serialized
              // old data to prevent another auto save
              lD.propertiesPanel.formSerializedLoadData['layout'] =
                lD.propertiesPanel.DOMObject.find('form [name]').serialize();
            }

            updateOnViewer = '.layout-background-image';
          }
        } else if (targetType === 'canvas') {
          // Only save canvas if we have a new value
          if (lD.layout.canvas.zIndex != newLayer) {
            const canvas = lD.getObjectByTypeAndId('canvas');
            canvas.changeLayer(newLayer, false);

            regionToBeSaved.push(canvas);

            // If we're selecting an element, update canvas layer
            if (lD.selectedObject.type === 'element') {
              updatePositionForm('zIndexCanvas', newLayer);
            }

            // Save id to update on viewer
            updateOnViewer = '#' + lD.layout.canvas.id;
          }
        } else {
          // Update regions
          const region = lD.getObjectByTypeAndId('region', targetId);

          if (
            region &&
            region.zIndex != newLayer
          ) {
            region.transform({
              zIndex: newLayer,
            }, false);

            regionToBeSaved.push(region);

            // If type region, check if selected is
            // region or object and if it matches the target
            if (
              (
                lD.selectedObject.type === 'region' &&
                lD.selectedObject.id == region.id
              ) ||
              (
                lD.selectedObject.type === 'widget' &&
                lD.selectedObject.regionId == region.id
              )
            ) {
              updatePositionForm('zIndex', newLayer);
            }

            // Save id to update on viewer
            updateOnViewer = '#' + targetId;
          }
        }

        // Update on viewer if needed
        if (updateOnViewer != '') {
          const $container = lD.viewer.DOMObject.find(updateOnViewer);
          $container.css('z-index', newLayer);
        }
      });

    // Save regions if needed
    if (regionToBeSaved.length > 0) {
      const saveRegions = function() {
        lD.layout.saveMultipleRegions(regionToBeSaved);
      };

      if (layoutSaving) {
        layoutSaving.then(saveRegions);
      } else {
        saveRegions();
      }
    }
  } else if (type === 'canvas') {
    const widgetsToSave = {};
    lD.viewer.layerManager.DOMObject
      .find('.layer-manager-canvas-layers > .sortable-canvas')
      .each((idx, target) => {
        const $target = $(target);
        const newLayer = idx;
        const isGroup = ($target.data('type') == 'elementGroup');
        const targetType = isGroup ? 'element-group' : 'element';
        const targetId = $target.data('itemId');
        const widgetId = $target.data('widgetId');
        const regionId = $target.data('regionId');
        const element = lD.getObjectByTypeAndId(targetType, targetId, widgetId);

        if (element.layer != newLayer) {
          // Change element layer in structure
          element.layer = newLayer;

          // Change in viewer
          lD.viewer.DOMObject.find('#' + targetId)
            .css('zIndex', newLayer);

          // If we're selecting an element or a group
          // that matches the changes target, update position form
          if (
            (
              !isGroup &&
              lD.selectedObject.type === 'element' &&
              lD.selectedObject.elementId === element.elementId
            ) ||
            (
              isGroup &&
              lD.selectedObject.type === 'element-group' &&
              lD.selectedObject.id === element.id
            )
          ) {
            updatePositionForm('zIndex', newLayer);
          }

          // Mark widget to be saved
          widgetsToSave[widgetId] =
            lD.getObjectByTypeAndId('widget', widgetId, regionId);
        }
      });

    // Save elements to target widgets
    Object.values(widgetsToSave).forEach((widget) => {
      widget.saveElements();
    });
  } else if (type === 'element-group') {
    const $groupContainer = $(auxContainer);
    const widgetId = $groupContainer.data('widgetId');
    const regionId = $groupContainer.data('regionId');
    $groupContainer.find('.sortable-element-group')
      .each((idx, target) => {
        const $target = $(target);
        const newLayer = idx;
        const targetId = $target.data('itemId');
        const element = lD.getObjectByTypeAndId('element', targetId, widgetId);

        if (element.layer != newLayer) {
          // Change element layer in structure
          element.layer = newLayer;

          // Change in viewer
          lD.viewer.DOMObject.find('#' + targetId)
            .css('zIndex', newLayer);

          // Update position form is element is selected
          if (
            lD.selectedObject.type === 'element' &&
            lD.selectedObject.elementId === element.elementId
          ) {
            updatePositionForm('zIndex', newLayer);
          }
        }
      });

    // Save widget
    const widget = lD.getObjectByTypeAndId('widget', widgetId, regionId);
    widget.saveElements();
  }

  // Reload layer manager
  this.render();
};

/**
 * Update object layer
 * @param {object} target region, element/element-group
 * @param {number} targetLayer new layer
 * @param {object} options
 * @param {boolean} [options.widgetId] widget id for elements
 * @param {boolean} [options.updateObjectsInFront]
 *  increase layer for all the elements in the same layer as target or above
 * @param {boolean} [options.updateObjectsInFrontTargetLayer]
 *  even if we don't update the original object layer
 *  we need to update others with a target layer
 */
LayerManager.prototype.updateObjectLayer = function(
  target,
  targetLayer,
  {
    widgetId = null,
    updateObjectsInFront = false,
    updateObjectsInFrontTargetLayer = 0,
  } = {}) {
  // Check if layer to upgrade other object has any object in it
  if (target.type === 'region') {
    const layerStructureTarget =
      this.layerStructure[updateObjectsInFrontTargetLayer];

    // If we have no objects at target layer, or it's the target
    // don't update others
    if (
      layerStructureTarget.length === 0 ||
      (
        layerStructureTarget.length === 1 &&
        target.zIndex === updateObjectsInFrontTargetLayer
      )
    ) {
      // Don't update other objects
      updateObjectsInFront = false;
    }
  } else if (
    target.type === 'element' ||
    target.type === 'element-group'
  ) {
    // Find canvas layers in structure
    const canvasContainingLayer =
      this.layerStructure[lD.layout.canvas.zIndex];
    let canvasLayersInStructure;
    canvasContainingLayer.every((obj) => {
      if (obj.type === 'canvas') {
        canvasLayersInStructure = obj.layers;
        return false;
      }

      return true;
    });

    // If we have no objects at target layer, or it's the target
    // don't update others
    const canvasLayersInStructureTargetLayer =
      canvasLayersInStructure[updateObjectsInFrontTargetLayer];
    if (
      canvasLayersInStructureTargetLayer === undefined ||
      canvasLayersInStructureTargetLayer.length === 0 ||
      (
        canvasLayersInStructureTargetLayer.length === 1 &&
        target.layer === updateObjectsInFrontTargetLayer
      )
    ) {
      // Don't update other objects
      updateObjectsInFront = false;
    }
  }

  // Only update if we have a new layer
  // or we want to update other layers
  if (targetLayer != null || updateObjectsInFront) {
    if (target.type === 'region') {
      const regionToBeSaved = [];

      // Transform target region if needed
      if (targetLayer != null) {
        target.transform(
          {
            zIndex: targetLayer,
          }, false);

        regionToBeSaved.push(target);

        // Update on viewer
        lD.viewer.updateRegion(target);
      }

      // Update other objects if they are in front of the new layer
      if (updateObjectsInFront) {
        // Check if canvas needs to be moved
        if (
          lD.layout.canvas &&
          lD.layout.canvas.zIndex >= updateObjectsInFrontTargetLayer
        ) {
          const newLayer = lD.layout.canvas.zIndex + 1;
          lD.layout.canvas.changeLayer(
            newLayer,
            false, // saveToHistory
          );

          regionToBeSaved.push(lD.layout.canvas);

          // Update canvas zIndex on viewer
          const $container =
            lD.viewer.DOMObject.find(`#${lD.layout.canvas.id}`);
          $container.css('z-index', newLayer);
        }

        // Check other regions on the layout
        for (id in lD.layout.regions) {
          if (
            Object.prototype.hasOwnProperty
              .call(lD.layout.regions, id)
          ) {
            const targetRegion = lD.layout.regions[id];

            // If region layer needs to be moved
            // and region isn't the target
            if (
              targetRegion.zIndex >= updateObjectsInFrontTargetLayer &&
              targetRegion.id != target.id
            ) {
              targetRegion.transform(
                {
                  zIndex: targetRegion.zIndex + 1,
                },
                false);

              regionToBeSaved.push(targetRegion);

              // Update on viewer
              lD.viewer.updateRegion(targetRegion);
            }
          }
        }
      }

      // Save regions if needed
      if (regionToBeSaved.length > 0) {
        lD.layout.saveMultipleRegions(regionToBeSaved);
      }
    } else if (
      target.type === 'element' ||
      target.type === 'element-group'
    ) {
      const originalWidget =
        lD.getObjectByTypeAndId('widget', widgetId, 'canvas');
      const widgetsToBeSaved = [];

      if (targetLayer != null) {
        target.layer = targetLayer;

        // Update element or element group in the viewer
        if (target.type === 'element') {
          lD.viewer.updateElement(target, true);
        } else {
          lD.viewer.updateElementGroupLayer(target);
        }

        // Add to widgets to be saved
        widgetsToBeSaved.push(originalWidget);
      }

      // If we want to update other elements and groups
      if (updateObjectsInFront) {
        if (
          target.type === 'element' &&
          target.group
        ) {
          // If target is element and in a group, update elements in its group
          for (elId in target.group.elements) {
            if (
              Object.prototype.hasOwnProperty
                .call(target.group.elements, elId)
            ) {
              const targetElement = target.group.elements[elId];

              // If element layer needs to be moved
              // and element isn't the target
              if (
                targetElement.layer >= updateObjectsInFrontTargetLayer &&
                targetElement.elementId != target.elementId
              ) {
                targetElement.layer = targetElement.layer + 1;
                lD.viewer.updateElement(targetElement, true);
              }
            }
          }
        } else {
          // If target isn't in a group, update elements and groups in canvas
          for (widgetId in lD.layout.canvas.widgets) {
            if (
              Object.prototype.hasOwnProperty
                .call(lD.layout.canvas.widgets, widgetId)
            ) {
              const auxWidget = lD.layout.canvas.widgets[widgetId];

              // Elements
              for (elId in auxWidget.elements) {
                if (
                  Object.prototype.hasOwnProperty
                    .call(auxWidget.elements, elId)
                ) {
                  const targetWidgetElement = auxWidget.elements[elId];
                  let updateElement = false;
                  const hasGroup = (!targetWidgetElement.group);
                  const updateLayerNeeded =
                    (
                      targetWidgetElement.layer >=
                      updateObjectsInFrontTargetLayer
                    );

                  // If original target is a group
                  if (
                    target.type === 'element-group' &&
                    hasGroup &&
                    updateLayerNeeded
                  ) {
                    updateElement = true;
                  } else if (
                    hasGroup &&
                    targetWidgetElement.elementId != target.elementId &&
                    updateLayerNeeded
                  ) {
                    // If element isn't in a group
                    updateElement = true;
                  }

                  // If element layer needs to be moved
                  if (updateElement) {
                    targetWidgetElement.layer = targetWidgetElement.layer + 1;
                    lD.viewer.updateElement(targetWidgetElement, true);
                  }
                }
              }

              // Element groups
              for (elGrId in auxWidget.elementGroups) {
                if (
                  Object.prototype.hasOwnProperty
                    .call(auxWidget.elementGroups, elGrId)
                ) {
                  const targetWidgetElementGroup =
                    auxWidget.elementGroups[elGrId];
                  let updateElement = false;
                  const updateLayerNeeded =
                    (
                      targetWidgetElementGroup.layer >=
                      updateObjectsInFrontTargetLayer
                    );

                  // If original target is a group, check if it's the same
                  if (
                    target.type === 'element-group' &&
                    targetWidgetElementGroup.id != target.id &&
                    updateLayerNeeded
                  ) {
                    updateElement = true;
                  } else if (
                    target.type === 'element' &&
                    updateLayerNeeded
                  ) {
                    // If original target is an element
                    // update group if layer needs it
                    updateElement = true;
                  }

                  // If element layer needs to be moved
                  if (updateElement) {
                    targetWidgetElementGroup.layer =
                      targetWidgetElementGroup.layer + 1;
                    lD.viewer.updateElementGroupLayer(targetWidgetElementGroup);
                  }
                }
              }

              // Add to widgets to be saved
              // if it's not the same as target widget
              if (auxWidget != originalWidget) {
                widgetsToBeSaved.push(auxWidget);
              }
            }
          }
        }
      }

      // If we have widgets to save, save all
      widgetsToBeSaved.forEach((widgetToSave) => {
        // Save all elements for widget
        widgetToSave.saveElements({
          forceRequest: true,
        });
      });
    }

    // If object is selected, update position form with new layer
    if (
      (
        target.type === 'region' &&
        target.id === target.widget.regionId
      ) ||
      (
        target.type != 'region' &&
        target.selected
      )
    ) {
      lD.propertiesPanel.updatePositionForm({
        zIndex: targetLayer,
      });
    }

    // Update layer manager
    lD.viewer.layerManager.render();
  }
};

module.exports = LayerManager;
