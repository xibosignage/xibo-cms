/*
 * Copyright (C) 2024 Xibo Signage Ltd
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

// Import templates
const templateLayoutAddForm =
  require('../templates/campaign-builder-layout-add-form-template.hbs');

// Include public path for webpack
require('../../public_path');
require('../style/campaign-builder.scss');

// Campaign builder name space
window.cB = {
  $container: null,
  $layoutSelect: null,
  layoutAssignments: null,
  map: null,

  initialise: function($container) {
    this.$container = $container;
  },

  initialiseMap: function(containerSelector, $dialog) {
    if (this.map !== null) {
      this.map.remove();
    }

    const $containerSelector = $('#' + containerSelector);
    const $geoFenceField = $dialog.find('input[name="geoFence"]');

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

    // Add a layer for drawn items
    const drawnItems = new L.FeatureGroup();
    this.map.addLayer(drawnItems);

    // Add draw control (toolbar)
    const drawControl = new L.Control.Draw({
      position: 'topright',
      draw: {
        polyline: false,
        circle: false,
        marker: false,
        circlemarker: false,
      },
      edit: {
        featureGroup: drawnItems,
      },
    });

    this.map.addControl(drawControl);

    // add search Control - allows searching by country/city and automatically
    // moves map to that location
    const searchControl = new L.Control.Search({
      url: 'https://nominatim.openstreetmap.org/search?format=json&q={s}',
      jsonpParam: 'json_callback',
      propertyName: 'display_name',
      propertyLoc: ['lat', 'lon'],
      marker: L.circleMarker([0, 0], {radius: 30}),
      autoCollapse: true,
      autoType: false,
      minLength: 2,
      hideMarkerOnCollapse: true,
      firstTipSubmit: true,
    });

    this.map.addControl(searchControl);

    // Draw events
    this.map.on('draw:created', function(e) {
      drawnItems.addLayer(e.layer);
      $geoFenceField.val(JSON.stringify(drawnItems.toGeoJSON()));
    });
    this.map.on('draw:edited', function(e) {
      $geoFenceField.val(JSON.stringify(drawnItems.toGeoJSON()));
    });
    this.map.on('draw:deleted', function(e) {
      e.layers.eachLayer(function(layer) {
        drawnItems.removeLayer(layer);
      });
      $geoFenceField.val(JSON.stringify(drawnItems.toGeoJSON()));
    });

    // Load existing geoJSON
    if ($geoFenceField.val()) {
      L.geoJSON(JSON.parse($geoFenceField.val()), {
        onEachFeature: function(feature, layer) {
          drawnItems.addLayer(layer);
          cB.map.fitBounds(drawnItems.getBounds());
        },
      });
    }
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
        delay: 250,
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
    this.$layoutSelect = $selector;
    makePagedSelect($selector);
    $selector.on('select2:select', function(e) {
      if (!e.params.data) {
        return;
      }
      cB.openLayoutForm({
        layoutId: e.params.data.id,
        daysOfWeek: '1,2,3,4,5,6,7',
      }, campaignBuilderTrans.addLayoutFormTitle);
    });
  },

  openLayoutForm: function(layout, title) {
    // Open a modal
    // with default vars, layout info and translations
    const formHtml = templateLayoutAddForm(
      {
        ...campaignBuilderDefaultVars,
        ...layout,
        ...{
          trans: campaignBuilderTrans,
        },
      });
    const $dialog = bootbox.dialog({
      title: title,
      message: formHtml,
      size: 'large',
      buttons: {
        cancel: {
          label: campaignBuilderTrans.cancelButton,
          className: 'btn-white',
          callback: () => {
            XiboDialogClose();
          },
        },
        add: {
          label: campaignBuilderTrans.saveButton,
          className: 'btn-primary save-button',
          callback: function() {
            $dialog.find('.XiboForm').submit();
            return false;
          },
        },
      },
    }).on('shown.bs.modal', function() {
      // Modal open
      const $form = $dialog.find('.XiboForm');

      // Init fields
      const $daysOfWeek = $dialog.find('select[name="daysOfWeek[]"]');
      $.each(layout.daysOfWeek.split(','), function(index, element) {
        $daysOfWeek.find('option[value=' + element + ']')
          .attr('selected', 'selected');
      });

      $daysOfWeek.select2({width: '100%'}).val();

      const $dayPartId = $dialog.find('select[name="dayPartId"]');
      if (layout.dayPartId) {
        $dayPartId.data('initial-value', layout.dayPartId);
      }
      makePagedSelect($dayPartId);

      // Load a map
      cB.initialiseMap('campaign-builder-map', $dialog);

      // Validate form
      forms.validateForm(
        $dialog.find('.XiboForm'), // form
        $dialog, // container
        {
          submitHandler: function(form) {
            XiboFormSubmit($(form), null, () => {
              // Is this an add or an edit?
              const displayOrder = $form.data('existingDisplayOrder');
              if (displayOrder && parseInt(displayOrder) > 0) {
                // Delete the existing assignment
                $.ajax({
                  method: 'delete',
                  url: $form.data('assignmentRemoveUrl') +
                    '&displayOrder=' + displayOrder,
                  complete: () => {
                    refreshLayoutAssignmentsTable();
                  },
                });
              } else {
                refreshLayoutAssignmentsTable();
              }
            });
          },
        },
      );
    }).on('hidden.bs.modal', function() {
      // Clear the layout select
      if (cB.$layoutSelect) {
        cB.$layoutSelect.val(null).trigger('change');
      }
    });
  },

  initialiseLayoutAssignmentsTable: function($selector) {
    this.layoutAssignments = $selector.DataTable({
      language: dataTablesLanguage,
      responsive: true,
      dom: dataTablesTemplate,
      filter: false,
      searchDelay: 3000,
      order: [[0, 'asc']],
      ajax: {
        url: $selector.data('searchUrl'),
        dataSrc: function(json) {
          if (json && json.data && json.data.length > 0) {
            return json.data[0].layouts;
          } else {
            return [];
          }
        },
      },
      columns: [
        {
          data: 'layoutId',
          responsivePriority: 5,
        },
        {
          data: 'layout',
          responsivePriority: 1,
        },
        {
          data: 'duration',
          responsivePriority: 1,
        },
        {
          data: 'dayPart',
          responsivePriority: 1,
        },
        {
          data: 'daysOfWeek',
          responsivePriority: 3,
          render: function(data) {
            if (data) {
              const readable = [];
              data.split(',').forEach((e) => {
                readable.push(campaignBuilderTrans.daysOfWeek[e] || e);
              });
              return readable.join(', ');
            } else {
              return '';
            }
          },
        },
        {
          data: 'geoFence',
          responsivePriority: 10,
          render: function(data, type) {
            if (type !== 'display') {
              return !!data;
            } else {
              return '<i class="fa fa-' + (data ? 'check' : 'times') + '"></i>';
            }
          },
        },
        {
          data: function(data, type, row, meta) {
            const buttons = [
              {
                id: 'assignment_button_edit',
                text: campaignBuilderTrans.assignmentEditButton,
                url: '#',
                external: false,
                class: 'button-assignment-remove',
                dataAttributes: [
                  {
                    name: 'row-id',
                    value: meta.row,
                  },
                ],
              },
              {
                id: 'assignment_button_delete',
                text: campaignBuilderTrans.assignmentDeleteButton,
                url: $selector.data('assignmentDeleteUrl') +
                  '?displayOrder=' + data.displayOrder,
              },
            ];
            return dataTableButtonsColumn({buttons: buttons}, type, row, meta);
          },
          orderable: false,
          responsivePriority: 1,
        },
      ],
    });
    this.layoutAssignments.on('draw', function(e, settings) {
      const $target = $('#' + e.target.id);
      $target.find('.button-assignment-remove').on('click', function(e) {
        e.preventDefault();
        const $button = $(e.currentTarget);
        if ($button.hasClass('assignment_button_edit')) {
          // Open a form.
          cB.openLayoutForm(
            cB.layoutAssignments.rows($button.data('rowId')).data()[0],
            campaignBuilderTrans.editLayoutFormTitle,
          );
        }
        return false;
      });

      XiboInitialise('#' + e.target.id);
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

  cB.initialiseLayoutAssignmentsTable(
    $container.find('table#table-campaign-builder-layout-assignments'),
  );
});

window.refreshLayoutAssignmentsTable = function() {
  // Reload the data table
  if (cB.layoutAssignments) {
    cB.layoutAssignments.ajax.reload();
  }
};
