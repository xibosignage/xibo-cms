/*
 * Copyright (C) 2022 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

// Include public path for webpack
require('../../public_path');
require('../style/campaign-builder.scss');

// Campaign builder name space
window.cB = {
  $container: null,
  templateLayoutAddForm: null,
  map: null,

  initialise: function($container) {
    this.$container = $container;
  },

  initialiseMap: function(containerSelector) {
    if (this.map !== null) {
      this.map.remove();
    }

    const $containerSelector = $('#' + containerSelector);

    this.map = L.map(containerSelector).setView(
      [
        this.getDataProperty($containerSelector, 'mapLat', '51'),
        this.getDataProperty($containerSelector, 'mapLong', '0.4'),
      ],
      this.getDataProperty($containerSelector, 'mapZoom', 13),
    );

    L.tileLayer(this.getDataProperty($containerSelector, 'mapTileServer'), {
      attribution: this.getDataProperty(
        $containerSelector,
        'mapAttribution',
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ),
      subdomains: ['a', 'b', 'c'],
    }).addTo(this.map);
  },

  getDataProperty: function($element, property, defaultValue = null) {
    const value = $element.data(property);
    if (value) {
      return value;
    } else {
      return defaultValue;
    }
  },

  initaliseDisplaySelect: function($selector) {
    $selector.select2({
      ajax: {
        url: $selector.data('searchUrl'),
        dataType: 'json',
        data: function(params) {
          const query = {
            isDisplaySpecific: -1,
            forSchedule: 1,
            displayGroup: params.term,
            start: 0,
            length: 10,
            columns: [
              {
                data: 'isDisplaySpecific',
              },
              {
                data: 'displayGroup',
              },
            ],
            order: [
              {
                column: 0,
                dir: 'asc',
              },
              {
                column: 1,
                dir: 'asc',
              },
            ],
          };

          // Set the start parameter based on the page number
          if (params.page != null) {
            query.start = (params.page - 1) * 10;
          }

          return query;
        },
        processResults: function(data, params) {
          const groups = [];
          const displays = [];

          $.each(data.data, function(index, element) {
            if (element.isDisplaySpecific === 1) {
              displays.push({
                id: element.displayGroupId,
                text: element.displayGroup,
              });
            } else {
              groups.push({
                id: element.displayGroupId,
                text: element.displayGroup,
              });
            }
          });

          let page = params.page || 1;
          page = (page > 1) ? page - 1 : page;

          return {
            results: [
              {
                text: $selector.data('transGroups'),
                children: groups,
              }, {
                text: $selector.data('transDisplay'),
                children: displays,
              },
            ],
            pagination: {
              more: (page * 10 < data.recordsTotal),
            },
          };
        },
      },
    });
  },

  initialiseLayoutSelect: function($selector) {
    makePagedSelect($selector);
    const cb = this;
    $selector.on('select2:select', function(e) {
      if (cb.templateLayoutAddForm === null) {
        cb.templateLayoutAddForm =
          Handlebars.compile(
            $('#campaign-builder-layout-add-form-template').html(),
          );
      }

      // Open a modal
      bootbox.dialog({
        message: cb.templateLayoutAddForm({
          layoutId: e.params.data.id,
        }),
        size: 'large',
        buttons: {
          fee: {
            label: campaignBuilderTrans.addLayoutButton,
            className: 'btn-primary',
            callback: function() {
              // TODO: make a call to assign layout.
            },
          },
        },
      }).on('shown.bs.modal', function() {
        // Init
        const $dialog = $(this);
        $dialog.find('select[name="daysOfWeek[]"]').select2({
          width: '100%',
        });

        makePagedSelect($dialog.find('select[name="dayPartId[]"]'));

        // Load a map
        cB.initialiseMap('campaign-builder-map');
      }).on('hidden.bs.modal', function() {
        // Clear the layout select
        $selector.trigger('select2:clear');
      });
    });
  },
};

$(function() {
  // Get our container
  const $container = $('#campaign-builder');
  cB.initialise($container);

  // Initialise some form controls.
  cB.initaliseDisplaySelect(
    $container.find('select[name="displayGroupIds[]"]'),
  );

  cB.initialiseLayoutSelect(
    $container.find('select[name="layoutId"]'),
  );
});
