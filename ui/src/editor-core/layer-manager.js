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
        groupInCanvas = addToLayerStructure(
          group.layer,
          {
            type: 'elementGroup',
            name: group.id,
            id: group.id,
            widgetId: 'widget_' + group.regionId + '_' + group.widgetId,
            regionId: 'region_' + group.regionId,
            moduleIcon: lD.common.getModuleByType(widget.subType).icon,
            selected: group.selected,
            expanded: group.expanded,
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
              name: (element.template.title) ?
                element.template.title : element.id,
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
        });
      }

      Object.values(region.widgets).forEach((widget) => {
        addToLayerStructure(region.zIndex, {
          type: 'staticWidget',
          name: (widget.widgetName != '') ?
            widget.widgetName : `[${widget.moduleName}]`,
          duration: parseDuration(region.duration),
          icon: widget.getIcon(),
          id: widget.id,
          auxId: widget.regionId,
          selected: widget.selected,
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
            const elementId = $(ev.currentTarget).data('item-id');
            const $viewerObject = self.viewerContainer.find('#' + elementId);

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
        if (targetType === 'canvas') {
          // Only save canvas if we have a new value
          if (lD.layout.canvas.zIndex != newLayer) {
            const canvas = lD.getObjectByTypeAndId('canvas');
            canvas.changeLayer(newLayer);

            // If we're selecting an element, update canvas layer
            if (lD.selectedObject.type === 'element') {
              updatePositionForm('zIndexCanvas', newLayer);
            }

            // Save id to update on viewer
            updateOnViewer = lD.layout.canvas.id;
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
            });

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
            updateOnViewer = targetId;
          }
        }

        // Update on viewer if needed
        if (updateOnViewer != '') {
          const $container = lD.viewer.DOMObject.find(`#${updateOnViewer}`);
          $container.css('z-index', newLayer);
        }
      });
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

module.exports = LayerManager;
