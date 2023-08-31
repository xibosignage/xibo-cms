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

    arrayToAdd[layer].push(object);
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

    // Get elements
    if (canvas.widgets) {
      Object.values(canvas.widgets).forEach((widget) => {
        const moduleIcon = lD.common.getModuleByType(widget.subType).icon;
        const elements = Object.values(widget.elements);

        elements.forEach((element) => {
          // If we don't have template yet, push the method to the promise array
          ($.isEmptyObject(element.template)) &&
            promiseArray.push(element.getTemplate());

          addToLayerStructure(element.layer, {
            type: 'element',
            name: (element.template.title) ?
              element.template.title : element.id,
            // Element has parent widget duration
            duration: parseDuration(widget.getDuration()),
            id: element.elementId,
            icon: element.template.icon,
            moduleIcon: moduleIcon,
            hasGroup: (element.groupId != undefined),
            groupId: layerManagerTrans.inGroup
              .replace('%groupId%', element.groupId),
            selected: element.selected,
          },
          canvasObject.subLayers,
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
        name: region.name,
        duration: parseDuration(region.duration),
        id: region.id,
        selected: region.selected,
      });
    } else {
      Object.values(region.widgets).forEach((widget) => {
        addToLayerStructure(region.zIndex, {
          type: 'staticWidget',
          name: (widget.widgetName != '') ?
            widget.widgetName : `[${widget.moduleName}]`,
          duration: parseDuration(region.duration),
          icon: widget.getIcon(),
          id: widget.id,
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
        this.DOMObject.find('.layer-manager-layer-item.selectable')
          .off().on('click', function(ev) {
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
              lD.viewer.selectElement($auxTarget);

              // Mark object with selected from manager class
              $auxTarget.addClass('selected-from-layer-manager');
            }
          });

        // Handle close button
        this.DOMObject.find('.close-layer-manager')
          .off().on('click', function(ev) {
            self.setVisible(false);
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
  });
};

module.exports = LayerManager;
