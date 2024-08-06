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
/* eslint-disable new-cap */
// Common functions/tools
const Common = require('../editor-core/common.js');
const PlayerHelper = require('../helpers/player-helper.js');

// Check condition
const checkCondition = function(type, value, targetValue, isTopLevel = true) {
  if (type === 'ne' && value === '') {
    return true;
  } else if (type === 'eq' && targetValue == value) {
    return true;
  } else if (type === 'neq' && targetValue != value) {
    return true;
  } else if (type === 'gt' &&
    !Number.isNaN(parseInt(targetValue)) &&
    !Number.isNaN(parseInt(value)) &&
    parseInt(targetValue) > parseInt(value)) {
    return true;
  } else if (type === 'lt' &&
    !Number.isNaN(parseInt(targetValue)) &&
    !Number.isNaN(parseInt(value)) &&
    parseInt(targetValue) < parseInt(value)) {
    return true;
  } else if (type === 'egt' &&
    !Number.isNaN(parseInt(targetValue)) &&
    !Number.isNaN(parseInt(value)) &&
    parseInt(targetValue) >= parseInt(value)) {
    return true;
  } else if (type === 'elt' &&
    !Number.isNaN(parseInt(targetValue)) &&
    !Number.isNaN(parseInt(value)) &&
    parseInt(targetValue) <= parseInt(value)) {
    return true;
  } else if (type === 'isTopLevel' && value == isTopLevel) {
    return true;
  } else {
    return false;
  }
};

window.forms = {
  /**
     * Create form inputs from an array of elements
     * @param {object} properties - The properties to set on the form
     * @param {object} targetContainer - The container to add the properties to
     * @param {string} [targetId] - Target Id ( widget, element, etc.)
     * @param {boolean} [playlistId] - If widget, the playlistId
     * @param {object[]} [propertyGroups] - Groups to add the properties to
     * @param {string} [customClass] - Custom class to add to the form fields
     * @param {boolean} [prepend] - Prepend fields instead?
     *  - If the properties are for an element
     */
  createFields: function(
    properties,
    targetContainer,
    targetId,
    playlistId,
    propertyGroups = [],
    customClass,
    prepend = false,
  ) {
    for (const key in properties) {
      if (properties.hasOwnProperty(key)) {
        const property = properties[key];

        // If element is marked as skip
        if (property.skip === true) {
          continue;
        }

        // Handle default value
        if (property.value === null && property.default !== undefined) {
          property.value = property.default;
        }

        // Handle visibility
        if (
          property.visibility.length
        ) {
          const rules = [];
          // Add all conditions to an array
          for (let i = 0; i < property.visibility.length; i++) {
            const test = property.visibility[i];
            const testObject = {
              type: test.type,
              conditions: [],
            };

            for (let j = 0; j < test.conditions.length; j++) {
              const condition = test.conditions[j];
              testObject.conditions.push({
                field: condition.field,
                type: condition.type,
                value: condition.value,
              });
            }

            rules.push(testObject);
          }

          property.visibility = JSON.stringify(rules);
        }

        // Special properties
        // Dataset selector
        if (property.type === 'datasetSelector') {
          property.datasetSearchUrl = urlsForApi.dataset.search.url;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'dataSetId';
          }
        }

        // Dataset Field selector
        if (property.type === 'datasetField') {
          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'datasetField';
          }
        }

        // Menu boards selector
        if (property.type === 'menuBoardSelector') {
          property.menuBoardSearchUrl = urlsForApi.menuBoard.search.url;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'menuId';
          }
        }

        // Menu category selector
        if (property.type === 'menuBoardCategorySelector') {
          property.menuBoardCategorySearchUrl =
            urlsForApi.menuBoard.categorySearch.url;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'menuCategoryId';
          }
        }

        // Media selector
        if (property.type === 'mediaSelector') {
          property.mediaSearchUrl = urlsForApi.library.get.url;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'mediaId';
          }
        }

        // Fonts selector
        if (property.type === 'fontSelector') {
          property.fontsSearchUrl = getFontsUrl + '?length=10000';
        }

        // Stored command selector
        if (property.type === 'commandSelector') {
          property.commandSearchUrl = urlsForApi.command.search.url;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'code';
          }
        }

        // Playlist Mixer
        if (property.type === 'playlistMixer') {
          property.playlistId = playlistId;
        }

        // dashboards available services
        if (property.type === 'connectorProperties') {
          property.connectorPropertiesUrl =
            urlsForApi.connectorProperties.search.url.replace(':id', targetId) +
              '?propertyId=' + property.id;

          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = property.id;
          }
        }

        // Change the name of the property to the id
        property.name = property.id;

        // Create the property id based on the targetId
        if (targetId) {
          property.id = targetId + '_' + property.id;
        }

        // Check if variant="autocomplete" exists and create isAutocomplete prop
        if (property.variant && property.variant === 'autocomplete') {
          property.isAutoComplete = true;
        }

        // Append the property to the target container
        if (templates.forms.hasOwnProperty(property.type)) {
          // New field
          const $newField = $(templates.forms[property.type](
            Object.assign(
              {},
              property,
              {
                trans: (typeof propertiesPanelTrans === 'undefined') ?
                  {} :
                  propertiesPanelTrans,
              },
            ),
          ));

          // Target to append to
          let $targetContainer = $(targetContainer);

          // Check if the property has a group
          if (property.propertyGroupId) {
            // Get group object from propertyGroups
            const group = propertyGroups.find(
              (group) => group.id === property.propertyGroupId,
            );

            // Only add to group if it exists
            if (group) {
              // Check if the group already exists in the DOM, if not create it
              if (
                $(targetContainer).find('#' + property.propertyGroupId).length
              ) {
                // Set target container to be the group
                $targetContainer = $(targetContainer)
                  .find('#' + property.propertyGroupId + ' .field-container');
              } else {
                // Create the group and add it to the target container
                $targetContainer.append(
                  $(templates.forms.group({
                    id: group.id,
                    title: group.title,
                    helpText: group.helpText,
                    expanded: group.expanded,
                  })),
                );

                // Set target container to be the group field container
                $targetContainer = $(
                  $(targetContainer).find('#' + property.propertyGroupId),
                ).find('.field-container');
              }
            }
          }

          // Append the new field to the target container
          if (prepend) {
            $targetContainer.prepend($newField);
          } else {
            $targetContainer.append($newField);
          }

          // Handle help text
          if (property.helpText) {
            // Only add if not added yet
            if (
              $newField.find('.input-info-container .xibo-help-text')
                .length === 0
            ) {
              $newField.find('.input-info-container').append(
                $(templates.forms.addOns.helpText({
                  helpText: property.helpText,
                })),
              );
            }
          }

          // Handle custom popover
          if (property.customPopOver) {
            $newField.find('.input-info-container').append(
              $(templates.forms.addOns.customPopOver({
                content: property.customPopOver,
              })),
            );
          }

          // Handle player compatibility
          if (property.playerCompatibility) {
            $newField.find('.input-info-container').append(
              $(templates.forms.addOns.playerCompatibility(
                property.playerCompatibility,
              )),
            );
          }

          // Add date format helper popup when variant = dateFormat
          if (property.variant && property.variant === 'dateFormat') {
            $newField.find('label.control-label').append(
              $(templates.forms.addOns.dateFormatHelperPopup({
                content: Handlebars.compile($('#php-date-format-table').html()),
              })),
            );

            // Initialize popover
            $newField.find('[data-toggle="popover"]').popover({
              sanitize: false,
              trigger: 'manual',
            }).on('mouseenter', function(ev) {
              $(ev.currentTarget).popover('show');
            });
          }

          // Handle setting value to datasetField if value is defined
          if (property.type === 'datasetField') {
            if (property.value !== undefined &&
              String(property.value).length > 0
            ) {
              $newField.find('select').attr('data-value', property.value);
            }
          }

          // Handle depends on property if not already set
          if (
            property.dependsOn &&
            !$newField.attr('data-depends-on')
          ) {
            $newField.attr('data-depends-on', property.dependsOn);
          }

          // Add visibility to the field if not already set
          if (
            property.visibility.length &&
            !$newField.attr('data-visibility')
          ) {
            $newField.attr('data-visibility', property.visibility);
          }

          // Add required attribute to the field if not already set
          if (
            property?.validation?.tests?.length &&
            !$newField.attr('data-is-required')
          ) {
            let added = false;
            $.each(property.validation.tests, function(index, el) {
              $.each(el.conditions, function(condIndex, condition) {
                if (condition?.type === 'required' && !added) {
                  $newField.attr('data-is-required', true);
                  property.isRequired = true;
                  added = true;
                }
              });
            });
          }

          // Add custom class to the field if not already set
          if (customClass) {
            $newField.find('[name]').addClass(customClass);
          }
        } else {
          console.error('Form type not found: ' + property.type);
        }
      }
    }

    // Initialise tooltips
    Common.reloadTooltips(
      $(targetContainer),
      {
        position: 'left',
      },
    );
  },
  /**
   * Initialise the form fields
   * @param {string} container - Main container Jquery selector
   * @param {object} target - Target Jquery selector or object
   * @param {string} [targetId] - Target Id ( widget, element, etc.)
   * @param {boolean} [readOnlyMode=false]
   * - If the properties are element properties
   */
  initFields: function(container, target, targetId, readOnlyMode = false) {
    const forms = this;

    // Find elements, either they match
    // the children of the container or they are the target
    const findElements = function(selector, target) {
      if (target) {
        if ($(target).is(selector)) {
          return $(target);
        } else {
          // Return empty object
          return $();
        }
      }

      return $(container).find(selector);
    };

    // Dropdowns
    findElements(
      '.dropdown-input-group',
      target,
    ).each(function(_k, el) {
      const $dropdown = $(el).find('select');

      // Check if options have a value with data-content and an image
      // If so, add the image to the dropdown
      $dropdown.find('option[data-content]').each(function(_k, option) {
        const $option = $(option);
        const $dataContent = $($option.data('content'));

        // Get the image
        const $image = $dataContent.find('img');

        // Replace src with data-src
        $image.attr('src',
          assetDownloadUrl.replace(':assetId', $image.attr('src')),
        );

        // Add html back to the option
        $option.data('content', $dataContent.html());
      });
    });

    // Dataset order clause
    findElements(
      '.dataset-order-clause',
      target,
    ).each(function(_k, el) {
      const $el = $(el);
      const datasetId = $el.data('depends-on-value');

      // Initialise the dataset order clause
      // if the dataset id is not empty
      if (datasetId) {
        // Get the dataset columns
        $.ajax({
          url: urlsForApi.dataset.search.url,
          type: 'GET',
          data: {
            dataSetId: datasetId,
          },
        }).done(function(data) {
          // Get the columns
          const datasetCols = data.data[0].columns;

          // Order Clause
          const $orderClauseFields = $el.find('.order-clause-container');
          if ($orderClauseFields.length == 0) {
            return;
          }

          const $orderClauseHiddenInput =
            $el.find('#input_' + $el.data('order-id'));
          const orderClauseValues = $orderClauseHiddenInput.val() ?
            JSON.parse(
              $orderClauseHiddenInput.val(),
            ) : [];

          // Update the hidden field with a JSON string
          // of the order clauses
          const updateHiddenField = function() {
            const orderClauses = [];
            $orderClauseFields.find('.order-clause-row').each(function(
              _index,
              el,
            ) {
              const $el = $(el);
              const orderClause = $el.find('.order-clause').val();
              const orderClauseDirection =
                $el.find('.order-clause-direction').val();

              if (orderClause) {
                orderClauses.push({
                  orderClause: orderClause,
                  orderClauseDirection: orderClauseDirection,
                });
              }
            });

            // Update the hidden field with a JSON string
            $orderClauseHiddenInput.val(JSON.stringify(orderClauses))
              .trigger('change');
          };

          // Clear existing fields
          $orderClauseFields.empty();

          // Get template
          const orderClauseTemplate =
            formHelpers.getTemplate('dataSetOrderClauseTemplate');

          const ascTitle = datasetQueryBuilderTranslations.ascTitle;
          const descTitle = datasetQueryBuilderTranslations.descTitle;

          if (orderClauseValues.length == 0) {
            // Add a template row
            const context = {
              columns: datasetCols,
              title: '1',
              orderClause: '',
              orderClauseAsc: '',
              orderClauseDesc: '',
              buttonGlyph: 'fa-plus',
              ascTitle: ascTitle,
              descTitle: descTitle,
            };
            $orderClauseFields.append(orderClauseTemplate(context));
          } else {
            // For each of the existing codes, create form components
            let i = 0;
            $.each(orderClauseValues, function(_index, field) {
              i++;

              const direction = (field.orderClauseDirection == 'ASC');

              const context = {
                columns: datasetCols,
                title: i,
                orderClause: field.orderClause,
                orderClauseAsc: direction,
                orderClauseDesc: !direction,
                buttonGlyph: ((i == 1) ? 'fa-plus' : 'fa-minus'),
                ascTitle: ascTitle,
                descTitle: descTitle,
              };

              $orderClauseFields.append(orderClauseTemplate(context));
            });
          }

          // Show only on non read only mode
          if (!readOnlyMode) {
            // Nabble the resulting buttons
            $orderClauseFields.on('click', 'button', function(e) {
              e.preventDefault();

              // find the gylph
              if ($(e.currentTarget).find('i').hasClass('fa-plus')) {
                const context = {
                  columns: datasetCols,
                  title: $orderClauseFields.find('.form-inline').length + 1,
                  orderClause: '',
                  orderClauseAsc: '',
                  orderClauseDesc: '',
                  buttonGlyph: 'fa-minus',
                  ascTitle: ascTitle,
                  descTitle: descTitle,
                };
                $orderClauseFields.append(orderClauseTemplate(context));
              } else {
                // Remove this row
                $(e.currentTarget).closest('.form-inline').remove();
              }

              updateHiddenField();
            });

            // Update the hidden field when the order clause changes
            $el.on('change', 'select:not([type="hidden"])', function() {
              updateHiddenField();
            });
          } else {
            forms.makeFormReadOnly($el);
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
        });
      }
    });

    // Dataset column selector
    findElements(
      '.dataset-column-selector',
      target,
    ).each(function(_k, el) {
      const $el = $(el);
      const datasetId = $el.data('depends-on-value');

      // Initialise the dataset column selector
      // if the dataset id is not empty
      if (datasetId) {
        // Get the dataset columns
        $.ajax({
          url: urlsForApi.dataset.search.url,
          type: 'GET',
          data: {
            dataSetId: datasetId,
          },
        }).done(function(data) {
          // Get the columns
          const datasetCols = data.data[0].columns;

          // Order Clause
          const $colsOutContainer = $el.find('#columnsOut');
          const $colsInContainer = $el.find('#columnsIn');

          if ($colsOutContainer.length == 0 ||
            $colsInContainer.length == 0) {
            return;
          }

          const $selectHiddenInput =
            $el.find('#input_' + $el.data('select-id'));
          const selectedValue = $selectHiddenInput.val() ?
            JSON.parse(
              $selectHiddenInput.val(),
            ) : [];

          // Update the hidden field with a JSON string
          // of the order clauses
          const updateHiddenField = function(skipSave = false) {
            const selectedCols = [];

            $colsInContainer.find('li').each(function(_index, el) {
              const colId = $(el).attr('id');
              selectedCols.push(Number(colId));
            });

            // Delete all temporary fields
            $el.find('.temp').remove();

            // Create a hidden field for each of the selected columns
            $.each(selectedCols, function(_index, col) {
              $el.append(
                '<input type="hidden" class="temp" ' +
                'name="dataSetColumnId[]" value="' +
                col + '" />',
              );
            });

            // Update the hidden field with a JSON string
            $selectHiddenInput.val(JSON.stringify(selectedCols))
              .trigger(
                'change',
                [{
                  skipSave: skipSave,
                }]);
          };

          // Clear existing fields
          $colsOutContainer.empty();
          $colsInContainer.empty();

          const colAvailableTitle =
            datasetColumnSelectorTranslations.colAvailable;
          const colSelectedTitle =
            datasetColumnSelectorTranslations.colSelected;

          // Set titles
          $el.find('.col-out-title').text(colAvailableTitle);
          $el.find('.col-in-title').text(colSelectedTitle);

          // Get the selected columns
          const datasetColsOut = [];
          const datasetColsIn = [];

          // If the column is in the dataset
          // add it to the selected columns
          // if not add it to the remaining columns
          $.each(datasetCols, function(_index, col) {
            if (selectedValue.includes(col.dataSetColumnId)) {
              datasetColsIn.push(col);
            } else {
              datasetColsOut.push(col);
            }
          });

          // Populate the available columns
          const $columnsOut = $el.find('#columnsOut');
          $.each(datasetColsOut, function(_index, col) {
            $columnsOut.append(
              '<li class="li-sortable" id="' + col.dataSetColumnId + '">' +
              col.heading +
              '</li>',
            );
          });

          // Populate the selected columns
          const $columnsIn = $el.find('#columnsIn');
          $.each(datasetColsIn, function(_index, col) {
            $columnsIn.append(
              '<li class="li-sortable" id="' + col.dataSetColumnId + '">' +
              col.heading +
              '</li>',
            );
          });

          if (!readOnlyMode) {
            // Setup lists drag and sort ( with double click )
            $el.find('#columnsIn, #columnsOut').sortable({
              connectWith: '.connectedSortable',
              dropOnEmpty: true,
              update: function() {
                updateHiddenField();
              },
            }).disableSelection();

            // Double click to switch lists
            $el.find('.li-sortable').on('dblclick', function(ev) {
              const $this = $(ev.currentTarget);
              $this.appendTo($this.parent().is('#columnsIn') ?
                $columnsOut : $columnsIn);
              updateHiddenField();
            });
          }

          // Update hidden field on start
          updateHiddenField(true);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
        });
      }
    });

    // Dataset field selector
    findElements(
      '.dataset-field-selector',
      target,
    ).each(function(_k, el) {
      const $el = $(el);
      const $select = $el.find('select');
      const $dataTypeId = $(container).find('input[name="dataTypeId"]');
      const datasetId = $el.data('depends-on-value');
      const getFieldItems = function(fields, search) {
        return fields.reduce(function(cols, col) {
          const searchValue = String(search);
          let searchValues = [];

          if (searchValue.indexOf(',') !== -1) {
            searchValues = searchValue.split(',');
          } else {
            searchValues = [searchValue];
          }

          if (searchValues.indexOf(String(col.dataTypeId)) !== -1) {
            return [...cols, col];
          }
          return cols;
        }, []);
      };

      // Initialise the dataset fields
      // if the dataset id is not empty
      if (datasetId) {
        $el.show();
        // Get the dataset columns
        $.ajax({
          url: urlsForApi.dataset.search.url,
          type: 'GET',
          data: {
            dataSetId: datasetId,
          },
        }).done(function(data) {
          // Get the columns
          const datasetCols = data.data[0].columns || [];

          if ($dataTypeId.length > 0 && datasetCols.length > 0) {
            // Find columns with given dataTypeId
            const fieldItems =
              getFieldItems(datasetCols, $dataTypeId.val()) || [];

            if (fieldItems.length > 0) {
              $select.find('option[value]').remove();

              $.each(fieldItems, function(_k, field) {
                $select.append(
                  $('<option value="' +
                    field.heading +
                    '">' +
                    field.heading +
                    '</option>',
                  ),
                );
              });

              if ($select.data('value') !== undefined) {
                // Trigger change event
                $select.val($select.data('value'))
                  .trigger('change', [{
                    skipSave: true,
                  }]);
              } else if (fieldItems.length === 1) {
                // Set default value when 1 option exists
                $select.val(fieldItems[0].heading).trigger('change');
              } else if (fieldItems.length > 1) {
                // Set default value to first option
                $select.val(fieldItems[0].heading).trigger('change');
              }
            } else {
              $el.hide();
            }
          }
        });
      }
    });

    // Dataset filter clause
    findElements(
      '.dataset-filter-clause',
      target,
    ).each(function(_k, el) {
      const $el = $(el);
      const datasetId = $el.data('depends-on-value');

      // Initialise the dataset filter clause
      // if the dataset id is not empty
      if (datasetId) {
        // Get the dataset columns
        $.ajax({
          url: urlsForApi.dataset.search.url,
          type: 'GET',
          data: {
            dataSetId: datasetId,
          },
        }).done(function(data) {
          // Get the columns
          const datasetCols = data.data[0].columns;

          // Filter Clause
          const $filterClauseFields = $el.find('.filter-clause-container');
          if ($filterClauseFields.length == 0) {
            return;
          }

          const $filterClauseHiddenInput =
            $el.find('#input_' + $el.data('filter-id'));
          const filterClauseValues = $filterClauseHiddenInput.val() ?
            JSON.parse(
              $filterClauseHiddenInput.val(),
            ) : [];

          // Update the hidden field with a JSON string
          // of the filter clauses
          const updateHiddenField = function() {
            const filterClauses = [];
            $filterClauseFields.find('.filter-clause-row').each(function(
              _index,
              el,
            ) {
              const $el = $(el);
              const filterClause = $el.find('.filter-clause').val();
              const filterClauseOperator =
                $el.find('.filter-clause-operator').val();
              const filterClauseCriteria =
                $el.find('.filter-clause-criteria').val();
              const filterClauseValue =
                $el.find('.filter-clause-value').val();

              if (filterClause) {
                filterClauses.push({
                  filterClause: filterClause,
                  filterClauseOperator: filterClauseOperator,
                  filterClauseCriteria: filterClauseCriteria,
                  filterClauseValue: filterClauseValue,
                });
              }
            });

            // Update the hidden field with a JSON string
            $filterClauseHiddenInput.val(JSON.stringify(filterClauses))
              .trigger('change');
          };

          // Clear existing fields
          $filterClauseFields.empty();

          // Get template
          const filterClauseTemplate =
            formHelpers.getTemplate('dataSetFilterClauseTemplate');

          const filterOptions =
            datasetQueryBuilderTranslations.filterOptions;
          const filterOperatorOptions =
            datasetQueryBuilderTranslations.filterOperatorOptions;

          if (filterClauseValues.length == 0) {
            // Add a template row
            const context = {
              columns: datasetCols,
              filterOptions: filterOptions,
              filterOperatorOptions: filterOperatorOptions,
              title: '1',
              filterClause: '',
              filterClauseOperator: 'AND',
              filterClauseCriteria: '',
              filterClauseValue: '',
              buttonGlyph: 'fa-plus',
            };
            $filterClauseFields.append(filterClauseTemplate(context));
          } else {
            // For each of the existing codes, create form components
            let j = 0;
            $.each(filterClauseValues, function(_index, field) {
              j++;

              const context = {
                columns: datasetCols,
                filterOptions: filterOptions,
                filterOperatorOptions: filterOperatorOptions,
                title: j,
                filterClause: field.filterClause,
                filterClauseOperator: field.filterClauseOperator,
                filterClauseCriteria: field.filterClauseCriteria,
                filterClauseValue: field.filterClauseValue,
                buttonGlyph: ((j == 1) ? 'fa-plus' : 'fa-minus'),
              };

              $filterClauseFields.append(filterClauseTemplate(context));
            });
          }

          // Show only on non read only mode
          if (!readOnlyMode) {
            // Nabble the resulting buttons
            $filterClauseFields.on('click', 'button', function(e) {
              e.preventDefault();

              // find the gylph
              if ($(e.currentTarget).find('i').hasClass('fa-plus')) {
                const context = {
                  columns: datasetCols,
                  filterOptions: filterOptions,
                  filterOperatorOptions: filterOperatorOptions,
                  title: $filterClauseFields.find('.form-inline').length + 1,
                  filterClause: '',
                  filterClauseOperator: 'AND',
                  filterClauseCriteria: '',
                  filterClauseValue: '',
                  buttonGlyph: 'fa-minus',
                };
                $filterClauseFields.append(filterClauseTemplate(context));
              } else {
                // Remove this row
                $(e.currentTarget).closest('.form-inline').remove();
              }

              updateHiddenField();
            });

            // Update the hidden field when the filter clause changes
            $el.on('change',
              'select:not([type="hidden"]), input:not([type="hidden"])',
              function(ev) {
                updateHiddenField();
              });
          } else {
            forms.makeFormReadOnly($el);
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
        });
      }
    });

    // Article tag selector
    findElements(
      '.ticker-tag-selector',
      target,
    ).each(function(_k, el) {
      const $el = $(el);

      // Get the columns
      const tickerTagsMap = {
        title: 'text',
        summary: 'text',
        content: 'text',
        author: 'text',
        permalink: 'text',
        link: 'text',
        date: 'date',
        publishedDate: 'date',
        image: 'image',
      };

      // Tag containers
      const $tagsOutContainer = $el.find('#tagsOut');
      const $tagsInContainer = $el.find('#tagsIn');

      if ($tagsOutContainer.length == 0 ||
        $tagsInContainer.length == 0) {
        return;
      }

      const $selectHiddenInput =
        $el.find('#input_' + $el.data('select-id'));
      const tagsValue = $selectHiddenInput.val() ?
        JSON.parse(
          $selectHiddenInput.val(),
        ) : [];

      const selectedTags = Object.keys(tagsValue);

      // Update the hidden field with a JSON string
      // of the tags
      const updateHiddenField = _.debounce(function(skipSave = false) {
        const selectedTags = [];
        const tagsValue = $selectHiddenInput.val() ?
          JSON.parse(
            $selectHiddenInput.val(),
          ) : [];

        $tagsInContainer.find('li').each(function(_index, tagEl) {
          const tagId = $(tagEl).attr('id');
          selectedTags.push(tagId);
        });

        // Clear all previous style controls
        $el.find('.ticker-tag-styles .ticker-tag-style').remove();

        // Add styles for each selected tag
        selectedTags.forEach((tag) => {
          const $newStyle = $(templates.forms.tickerTagStyle({
            id: tag,
            type: tickerTagsMap[tag],
            value: tagsValue[tag],
            trans: tickerTagSelectorTranslations,
            fontsSearchUrl: getFontsUrl + '?length=10000',
          }));

          $newStyle.appendTo($el.find('.ticker-tag-styles'));

          // Update hidden field on input change
          $newStyle.off().on('change inputChange', updateTagsStylesHiddenField);
        });

        // Init fields
        forms.initFields($el.find('.ticker-tag-styles'));

        updateTagsStylesHiddenField();
      }, 100);

      const updateTagsStylesHiddenField = _.debounce(function() {
        const selectedTagStylesValue = {};

        $el.find('.ticker-tag-style').each(function(_index, tagContainer) {
          const $container = $(tagContainer);
          const tagId = $container.data('component-id');
          selectedTagStylesValue[tagId] = {};

          const $tagProperties =
            $(tagContainer).find('.ticker-tag-style-property');

          // save tag type
          selectedTagStylesValue[tagId]['tagType'] = tickerTagsMap[tagId];

          $tagProperties.find('select, input').each(function(_index, tagInput) {
            const inputType = $(tagInput).data('tag-style-input');
            const value = ($(tagInput).attr('type') === 'checkbox') ?
              $(tagInput).is(':checked') :
              $(tagInput).val();
            selectedTagStylesValue[tagId][inputType] = value;
          });
        });

        // Update the hidden field with a JSON string
        $selectHiddenInput.val(JSON.stringify(selectedTagStylesValue))
          .trigger('change');
      }, 100);

      // Clear existing fields
      $tagsOutContainer.empty();
      $tagsInContainer.empty();

      const tagAvailableTitle =
        tickerTagSelectorTranslations.tagAvailable;
      const tagSelectedTitle =
        tickerTagSelectorTranslations.tagSelected;

      // Set titles
      $el.find('.col-out-title').text(tagAvailableTitle);
      $el.find('.col-in-title').text(tagSelectedTitle);

      // Get the selected tags
      const tickerTagsOut = [];
      const tickerTagsIn = [];

      // If the column is in the dataset
      // add it to the selected tags
      // if not add it to the remaining tags
      $.each(Object.keys(tickerTagsMap), function(_index, tag) {
        if (selectedTags.includes(tag)) {
          tickerTagsIn.push(tag);
        } else {
          tickerTagsOut.push(tag);
        }
      });

      // Populate the available tags
      const $tagsOut = $el.find('#tagsOut');
      $.each(tickerTagsOut, function(_index, tag) {
        const $tag = $(
          '<li class="li-sortable" id="' + tag +
          '" data-tag-type="' + tickerTagsMap[tag] + '">' +
          tickerTagSelectorTranslations[tag] +
          '</li>',
        ).data('tag-type', tickerTagsMap[tag]);

        $tagsOut.append($tag);
      });

      // Populate the selected tags
      const $tagsIn = $el.find('#tagsIn');
      $.each(tickerTagsIn, function(_index, tag) {
        const $tag = $(
          '<li class="li-sortable" id="' + tag +
          '" data-tag-type="' + tickerTagsMap[tag] + '">' +
          tickerTagSelectorTranslations[tag] +
          '</li>',
        );

        $tagsIn.append($tag);
      });

      if (!readOnlyMode) {
        // Setup lists drag and sort ( with double click )
        $el.find('#tagsIn, #tagsOut').sortable({
          connectWith: '.connectedSortable',
          dropOnEmpty: true,
          receive: updateHiddenField,
          update: updateHiddenField,
        }).disableSelection();

        // Double click to switch lists
        $el.find('.li-sortable').on('dblclick', function(ev) {
          const $this = $(ev.currentTarget);
          $this.appendTo($this.parent().is('#tagsIn') ?
            $tagsOut : $tagsIn);
          updateHiddenField();
        });
      }

      // Update hidden field on start
      updateHiddenField(true);
    });

    // Dataset col selector
    findElements(
      '.dataset-column-style-selector',
      target,
    ).each(function(_k, el) {
      const $el = $(el);
      const datasetId = $el.data('depends-on-value');

      // Initialise the dataset column style selector
      // if the dataset id is not empty
      if (datasetId) {
        // Get the dataset columns
        $.ajax({
          url: urlsForApi.dataset.search.url,
          type: 'GET',
          data: {
            dataSetId: datasetId,
          },
        }).done(function(data) {
          // Get the columns
          const datasetCols = data.data[0].columns;

          const datasetTypeMap = {
            3: 'date',
            5: 'image',
          };

          const getColStyle = function(col) {
            return (datasetTypeMap[col]) ? datasetTypeMap[col] : 'text';
          };

          const getColById = function(colId) {
            return datasetCols.find((col) => col.dataSetColumnId == colId);
          };

          // Column containers
          const $colsOutContainer = $el.find('#colsOut');
          const $colsInContainer = $el.find('#colsIn');

          if ($colsOutContainer.length == 0 ||
            $colsInContainer.length == 0) {
            return;
          }

          const $selectHiddenInput =
            $el.find('#input_' + $el.data('select-id'));
          const colsValue = $selectHiddenInput.val() ?
            JSON.parse(
              $selectHiddenInput.val(),
            ) : [];

          const selectedCols = Object.keys(colsValue);

          // Update the hidden field with a JSON string
          // of the columns
          const updateHiddenField = _.debounce(function(skipSave = false) {
            const selectedCols = [];
            const colsValue = $selectHiddenInput.val() ?
              JSON.parse(
                $selectHiddenInput.val(),
              ) : [];

            $colsInContainer.find('li').each(function(_index, colEl) {
              const colId = $(colEl).attr('id');
              const colType = getColStyle($(colEl).data('colType'));
              const colObj = getColById(colId);
              selectedCols.push({
                id: colId,
                type: colType,
                name: colObj.heading,
              });
            });

            // Clear all previous style controls
            $el.find('.dataset-column-styles .dataset-col-style').remove();

            // Add styles for each selected col
            selectedCols.forEach((col) => {
              const $newStyle = $(templates.forms.datasetColStyle({
                id: col.id,
                type: col.type,
                name: col.name,
                value: colsValue[col.id],
                trans: datasetColStyleSelectorTranslations,
                fontsSearchUrl: getFontsUrl + '?length=10000',
              }));

              $newStyle.appendTo($el.find('.dataset-column-styles'));

              // Update hidden field on input change
              $newStyle.off().on(
                'change inputChange',
                function() {
                  updateColStylesHiddenField();
                },
              );
            });

            // Init fields
            forms.initFields($el.find('.dataset-column-styles'));

            updateColStylesHiddenField();
          }, 100);

          const updateColStylesHiddenField = _.debounce(function() {
            const selectedColStylesValue = {};

            let playOrder = 0;
            $el.find('.dataset-col-style').each(function(_index, colContainer) {
              const $container = $(colContainer);
              const colId = $container.data('component-id');
              selectedColStylesValue[colId] = {};

              const $colProperties =
                $(colContainer).find('.dataset-col-style-property');

              // set play order
              selectedColStylesValue[colId].playOrder = playOrder;
              playOrder++;

              // save colId
              selectedColStylesValue[colId].colId = colId;

              $colProperties.find('select, input')
                .each(function(_index, colsInput) {
                  const inputType =
                    $(colsInput).data('dataset-col-style-input');
                  const value = ($(colsInput).attr('type') === 'checkbox') ?
                    $(colsInput).is(':checked') :
                    $(colsInput).val();
                  selectedColStylesValue[colId][inputType] = value;
                });
            });

            // Update the hidden field with a JSON string
            $selectHiddenInput.val(JSON.stringify(selectedColStylesValue))
              .trigger('change');
          }, 100);

          // Clear existing fields
          $colsOutContainer.empty();
          $colsInContainer.empty();

          const colsAvailableTitle =
            datasetColStyleSelectorTranslations.colAvailable;
          const colsSelectedTitle =
            datasetColStyleSelectorTranslations.colSelected;

          // Set titles
          $el.find('.col-out-title').text(colsAvailableTitle);
          $el.find('.col-in-title').text(colsSelectedTitle);

          // Get the selected cols
          const colsOut = [];
          const colsIn = [];

          // If the column is in the dataset
          // add it to the selected cols
          // if not add it to the remaining cols
          $.each(Object.keys(datasetCols), function(_index, col) {
            const colId = datasetCols[col].dataSetColumnId;
            if (selectedCols.find((column) => colId == column)) {
              colsIn.push(colId);
            } else {
              colsOut.push(colId);
            }
          });

          // Populate the available columns
          const $colsOut = $el.find('#colsOut');
          $.each(colsOut, function(_index, col) {
            const columnObj = getColById(col);
            const $col = $(
              '<li class="li-sortable" id="' +
              columnObj.dataSetColumnId +
              '" data-col-type="' + columnObj.dataTypeId + '">' +
              columnObj.heading +
              '</li>',
            ).data('col-type', columnObj.dataTypeId);

            $colsOut.append($col);
          });

          // Populate the selected columns
          const $colsIn = $el.find('#colsIn');
          $.each(colsIn, function(_index, col) {
            const columnObj = getColById(col);
            const $col = $(
              '<li class="li-sortable" id="' +
              columnObj.dataSetColumnId +
              '" data-col-type="' + columnObj.dataTypeId + '">' +
              columnObj.heading +
              '</li>',
            );

            $colsIn.append($col);
          });

          if (!readOnlyMode) {
            // Setup lists drag and sort ( with double click )
            $el.find('#colsIn, #colsOut').sortable({
              connectWith: '.connectedSortable',
              dropOnEmpty: true,
              receive: updateHiddenField,
              update: updateHiddenField,
            }).disableSelection();

            // Double click to switch lists
            $el.find('.li-sortable').on('dblclick', function(ev) {
              const $this = $(ev.currentTarget);
              $this.appendTo($this.parent().is('#colsIn') ?
                $colsOut : $colsIn);
              updateHiddenField();
            });
          }

          // Update hidden field on start
          updateHiddenField(true);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
        });
      }
    });

    findElements(
      '.languageSelect',
      target,
    ).each(function(_k, el) {
      const $select = $(el).find('select.form-control');
      const isMomentLocaleSelect = $(el).hasClass('momentLocales');

      if (isMomentLocaleSelect) {
        const momentLocales = moment.locales().sort();
        const selectValue = $select.val();

        // Remove all previous options
        $select.find('option').remove();

        // Add empty option to the start of the array
        momentLocales.unshift('');

        // Add options from moment
        for (let index = 0; index < momentLocales.length; index++) {
          const locale = momentLocales[index];

          // Get translation if exists
          const name = momentLocalesTrans[locale] || locale;

          // Create new option
          const $newOption =
            $(`<option value="${locale}">${locale} - ${name}</option>`);

          // Check if it's selected
          if (selectValue == locale) {
            $newOption.prop('selected', true);
          }

          $newOption.appendTo($select);
        }
      }

      makeLocalSelect($select, container);
    });

    // Playlist mixer
    findElements(
      '.playlist-mixer',
      target,
    ).each(function(_k, el) {
      const $el = $(el);

      /**
       * Initialise a new row
       * @param {object} $form
       * @param {object} $row
       */
      function subplaylistInitRow($form, $row) {
        const $select = $row.find('.subplaylist-id');

        // Get the initial value.
        if ($select.data('fieldId')) {
          $.ajax({
            method: 'GET',
            url: urlsForApi.playlist.get.url +
              '?playlistId=' + $select.data('fieldId'),
            success: function(response) {
              if (response.data && response.data.length > 0) {
                // Append our initial option
                $select.append('<option value="' + response.data[0].playlistId +
                  '" data-tags="' + response.data[0].tags +
                  '" selected>' + response.data[0].name + '</option>');

                subplaylistInitSelect2($form, $select);
              } else {
                // No permissions.
                $select.parent().append(
                  '<input type="hidden" value="' +
                  $select.data('fieldId') +
                  '" name="subPlaylistId[]">' +
                  '<span title="' +
                    playlistMixerTranslations.noPermission +
                  '">' +
                  '<i class="fa fa-lock"></i>&nbsp;' +
                    playlistMixerTranslations.playlistId + ' ' +
                  $select.data('fieldId') + '</span>');
                $select.remove();
              }

              // Disable fields on non read only mode
              if (readOnlyMode) {
                forms.makeFormReadOnly($form);
              }
            },
            error: function() {
              $select.parent().append(
                '{% trans "An unknown error has occurred. Please refresh" %}',
              );
            },
          });
        } else {
          subplaylistInitSelect2($form, $select);
        }

        $row.find('select[name="spotFill[]"]').select2({
          templateResult: function(state) {
            if (!state.id) {
              return state.text;
            }
            return $(state.element).data().templateResult;
          },
          dropdownAutoWidth: true,
          minimumResultsForSearch: -1,
        });
      }

      /**
       * Initialise the select2
       * @param {object} $form
       * @param {object} $el
       */
      function subplaylistInitSelect2($form, $el) {
        $el.select2({
          dropdownAutoWidth: true,
          ajax: {
            url: urlsForApi.playlist.get.url +
              '?notPlaylistId=' + $form.data('playlistId'),
            dataType: 'json',
            delay: 250,
            data: function(params) {
              const query = {
                start: 0,
                length: 10,
                name: params.term,
              };
              if (params.page != null) {
                query.start = (params.page - 1) * 10;
              }
              return query;
            },
            processResults: function(data, params) {
              const results = [];

              $.each(data.data, function(index, el) {
                results.push({
                  id: el.playlistId,
                  text: el.name,
                });
              });

              let page = params.page || 1;
              page = (page > 1) ? page - 1 : page;

              if (page === 1 && results.length <= 0) {
                $form.find('.sub-playlist-no-playlists-message')
                  .removeClass('d-none');
              }

              return {
                results: results,
                pagination: {
                  more: (page * 10 < data.recordsTotal),
                },
              };
            },
          },
        });
      }

      const $mixerHiddenInput =
        $el.find('#input_' + $el.data('mixer-id'));

      const mixerValues = $mixerHiddenInput.val() ?
        JSON.parse(
          $mixerHiddenInput.val(),
        ) : [];

      // Filter Clause
      const $playlistItemsContainer = $el.find('.mixer-playlist-container');
      if ($playlistItemsContainer.length == 0) {
        return;
      }

      // Update the hidden field with a JSON string
      // of the filter clauses
      const updateHiddenField = function() {
        const mixerItems = [];
        $playlistItemsContainer.find('.subplaylist-item-row').each(function(
          _index,
          el,
        ) {
          const $el = $(el);
          const playlistId = $el.find('.subplaylist-id').val();
          const spots =
            $el.find('.subplaylist-spots').val();
          const spotLength =
            $el.find('.subplaylist-spots-length').val();
          const spotFill =
            $el.find('.subplaylist-spots-fill').val();

          if (playlistId) {
            mixerItems.push({
              rowNo: _index + 1,
              playlistId: playlistId,
              spots: spots,
              spotLength: spotLength,
              spotFill: spotFill,
            });
          }
        });

        // Update the hidden field with a JSON string
        $mixerHiddenInput.val(JSON.stringify(mixerItems))
          .trigger('xiboInputChange');
      };

      // Clear existing fields
      $playlistItemsContainer.empty();

      // Add header template
      const containerTemplate =
        formHelpers.getTemplate('subPlaylistContainerTemplate');
      $playlistItemsContainer.append(containerTemplate({
        trans: playlistMixerTranslations,
      }));

      // Get template
      const subPlaylistFormTemplate =
        formHelpers.getTemplate('subPlaylistFormTemplate');

      if (mixerValues.length == 0) {
        // Add a template row
        const context = {
          playlistId: '',
          spots: '',
          spotLength: '',
          spotFill: '',
          fillTitle: playlistMixerTranslations.fillTitle,
          padTitle: playlistMixerTranslations.padTitle,
          repeatTitle: playlistMixerTranslations.repeatTitle,
          fillHelpText: playlistMixerTranslations.fillHelpText,
          padHelpText: playlistMixerTranslations.padHelpText,
          repeatHelpText: playlistMixerTranslations.repeatHelpText,
        };
        $playlistItemsContainer.find('.subplaylist-items-content')
          .append(subPlaylistFormTemplate(context));
      } else {
        // For each of the existing codes, create form components
        $.each(mixerValues, function(_index, field) {
          const context = {
            playlistId: field.playlistId,
            spots: field.spots,
            spotLength: field.spotLength,
            spotFill: field.spotFill,
            fillTitle: playlistMixerTranslations.fillTitle,
            padTitle: playlistMixerTranslations.padTitle,
            repeatTitle: playlistMixerTranslations.repeatTitle,
            fillHelpText: playlistMixerTranslations.fillHelpText,
            padHelpText: playlistMixerTranslations.padHelpText,
            repeatHelpText: playlistMixerTranslations.repeatHelpText,
          };

          $playlistItemsContainer.find('.subplaylist-items-content')
            .append(subPlaylistFormTemplate(context));
        });
      }

      // Add or remove playlist item
      $playlistItemsContainer.on('click', '.subplaylist-item-btn', function(e) {
        e.preventDefault();

        // find the gylph
        if ($(e.currentTarget).find('i').hasClass('fa-plus')) {
          const context = {
            playlistId: '',
            spots: '',
            spotLength: '',
            subPlaylistIdSpotFill: '',
            fillTitle: playlistMixerTranslations.fillTitle,
            padTitle: playlistMixerTranslations.padTitle,
            repeatTitle: playlistMixerTranslations.repeatTitle,
            fillHelpText: playlistMixerTranslations.fillHelpText,
            padHelpText: playlistMixerTranslations.padHelpText,
            repeatHelpText: playlistMixerTranslations.repeatHelpText,
          };

          const $newRow = $(subPlaylistFormTemplate(context))
            .appendTo(
              $playlistItemsContainer.find('.subplaylist-items-content'),
            );

          // Initialise row
          subplaylistInitRow($el, $newRow);
        } else {
          // Remove this row
          $(e.currentTarget).closest('.subplaylist-item-row').remove();
        }

        updateHiddenField();
      });

      // Initialise all rows
      $playlistItemsContainer.find('.subplaylist-item-row')
        .each(function(_index, element) {
          subplaylistInitRow($el, $(element));
        });

      // Update the hidden field when the item changes
      $el.on('change', 'select, input', function() {
        updateHiddenField();
      });

      // Make the playlist items sortable
      $playlistItemsContainer.sortable({
        axis: 'y',
        items: '.subplaylist-item-row',
        handle: '.subplaylist-item-sort',
        containment: 'parent',
        update: function() {
          updateHiddenField();
        },
      });
    });

    // Code editor
    findElements(
      '.xibo-code-input',
      target,
    ).each(function(_k, el) {
      const $textArea = $(el).find('.code-input');
      const inputValue = $textArea.val();
      const codeType = $textArea.data('codeType');

      // Create code editor object if it doesn't exist
      if (window.codeEditors === undefined) {
        window.codeEditors = {};
      }

      const newEditor =
      window.codeEditors[$textArea.attr('id')] =
        monaco.editor.create($(el).find('.code-input-editor')[0], {
          value: inputValue,
          fontSize: 12,
          theme: 'vs-dark',
          language: codeType,
          lineNumbers: 'off',
          glyphMargin: false,
          folding: false,
          lineDecorationsWidth: 0,
          lineNumbersMinChars: 0,
          automaticLayout: true,
          minimap: {
            enabled: false,
          },
        });

      // Update the textarea when the editor changes
      newEditor.onDidChangeModelContent(() => {
        $textArea.val(newEditor.getValue()).trigger('inputChange');
      });

      // Update the editor when the textarea changes
      $textArea.on('change', function() {
        newEditor.setValue($textArea.val());
      });
    });

    // Colour picker
    findElements(
      '.colorpicker-input.colorpicker-element',
      target,
    ).each(function(_k, el) {
      // Init the colour picker
      $(el).colorpicker({
        container: $(el).find('.picker-container'),
        align: 'left',
        format: ($(el).data('colorFormat') !== undefined) ?
          $(el).data('colorFormat') :
          false,
      });

      const $inputElement = $(el).find('input');
      $inputElement.on('focusout', function() {
        // If the input is empty, set the default value
        // or clear the color preview
        if ($inputElement.val() == '') {
          // If we have a default value
          if ($(el).data('default') !== undefined) {
            const defaultValue = $(el).data('default');
            $(el).colorpicker('setValue', defaultValue);
          } else {
            // Clear the color preview
            $(el).find('.input-group-addon').css('background-color', '');
          }
        }
      });
    });

    // Colour gradient
    findElements(
      '.color-gradient',
      target,
    ).each(function(_k, el) {
      // Init the colour pickers
      $(el).find('.colorpicker-input.colorpicker-element').colorpicker({
        container: $(el).find('.picker-container'),
        align: 'left',
        format: ($(el).data('colorFormat') !== undefined) ?
          $(el).data('colorFormat') :
          false,
      });
      // Defaults
      const defaults = {
        color1: '#222',
        color2: '#eee',
        angle: 0,
      };

      const $inputElements =
        $(el).find('[name]:not(.color-gradient-hidden).element-property');
      const $hiddenInput =
        $(el).find('.color-gradient-hidden.element-property');
      const $gradientType =
          $inputElements.filter('[name="gradientType"]');
      const $gradientAngle =
          $inputElements.filter('[name="gradientAngle"]');
      const $gradientColor1 =
          $inputElements.filter('[name="gradientColor1"]');
      const $gradientColor2 =
          $inputElements.filter('[name="gradientColor2"]');

      // Load values into inputs
      if ($hiddenInput.val() != '') {
        const initialValue =
          JSON.parse($hiddenInput.val());

        $gradientType.val(initialValue.type).trigger('change');
        $gradientColor1.parents('.colorpicker-input')
          .colorpicker('setValue', initialValue.color1);
        $gradientColor2.parents('.colorpicker-input')
          .colorpicker('setValue', initialValue.color2);
        $gradientAngle.val(initialValue.angle);
      }

      // Update fields visibility and default values
      const updateFields = function() {
        if ($gradientColor1.val() == '') {
          $gradientColor1.parents('.colorpicker-input')
            .colorpicker('setValue', defaults.color1);
        }
        if ($gradientColor2.val() == '') {
          $gradientColor2.parents('.colorpicker-input')
            .colorpicker('setValue', defaults.color2);
        }
        if ($gradientAngle.val() == '') {
          $gradientAngle.val(defaults.angle);
        }
        $gradientAngle.parent().toggle($gradientType.val() === 'linear');
      };

      // Mark inputs to skip saving in properties panel
      $inputElements.addClass('skip-save');

      // When changing the inputs, save to hidden input
      $inputElements.on('change', function() {
        const gradientType = $gradientType.val();
        const color1Val = $gradientColor1.val();
        const color2Val = $gradientColor2.val();
        const angleVal = $gradientAngle.val();

        // Gradient object to be saved
        const gradient = {
          type: gradientType,
          color1: (color1Val != '') ? color1Val : defaults.color1,
          color2: (color2Val != '') ? color2Val : defaults.color2,
        };

        // If gradient type is linear, save angle
        if (gradientType === 'linear') {
          gradient.angle = (angleVal != '') ? angleVal : defaults.angle;
        }

        updateFields();

        // Save value to hidden input
        $hiddenInput.val(JSON.stringify(gradient)).trigger('change');
      });

      updateFields();
    });

    // Date picker - date only
    findElements(
      '.dateControl.date:not(.datePickerHelper)',
      target,
    ).each(function(_k, el) {
      if (calendarType == 'Jalali') {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateOnlyFormat,
          {
            altFieldFormatter: function(unixTime) {
              const newDate = moment.unix(unixTime / 1000);
              newDate.set('hour', 0);
              newDate.set('minute', 0);
              newDate.set('second', 0);
              return newDate.format(systemDateFormat);
            },
          },
        );
      } else {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateOnlyFormat,
        );
      }
    });

    // Date picker - date and time
    findElements(
      '.dateControl.dateTime:not(.datePickerHelper)',
      target,
    ).each(function(_k, el) {
      const enableSeconds = dateFormat.includes('s');
      const enable24 = !dateFormat.includes('A');

      if (calendarType == 'Jalali') {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateFormat,
          {
            timePicker: {
              enabled: true,
              second: {
                enabled: enableSeconds,
              },
            },
          },
        );
      } else {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateFormat,
          {
            enableTime: true,
            time_24hr: enable24,
            enableSeconds: enableSeconds,
            altFormat: $(el).data('customFormat') ?
              $(el).data('customFormat') : jsDateFormat,
          },
        );
      }
    });

    // Date picker - month only
    findElements(
      '.dateControl.month:not(.datePickerHelper)',
      target,
    ).each(function(_k, el) {
      if (calendarType == 'Jalali') {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateFormat,
          {
            format: $(el).data('customFormat') ?
              $(el).data('customFormat') : 'MMMM YYYY',
            viewMode: 'month',
            dayPicker: {
              enabled: false,
            },
            altFieldFormatter: function(unixTime) {
              const newDate = moment.unix(unixTime / 1000);
              newDate.set('date', 1);
              newDate.set('hour', 0);
              newDate.set('minute', 0);
              newDate.set('second', 0);

              return newDate.format(systemDateFormat);
            },
          },
        );
      } else {
        initDatePicker(
          $(el),
          systemDateFormat,
          jsDateFormat,
          {
            plugins: [new flatpickrMonthSelectPlugin({
              shorthand: false,
              dateFormat: systemDateFormat,
              altFormat: $(el).data('customFormat') ?
                $(el).data('customFormat') : 'MMMM Y',
              parseDate: function(datestr, format) {
                return moment(datestr, format, true).toDate();
              },
              formatDate: function(date, format, locale) {
                return moment(date).format(format);
              },
            })],
          },
        );
      }
    });

    // Date picker - time only
    findElements(
      '.dateControl.time:not(.datePickerHelper)',
      target,
    ).each(function(_k, el) {
      const enableSeconds = dateFormat.includes('s');

      if (calendarType == 'Jalali') {
        initDatePicker(
          $(el),
          systemTimeFormat,
          jsTimeFormat,
          {
            onlyTimePicker: true,
            format: jsTimeFormat,
            timePicker: {
              second: {
                enabled: enableSeconds,
              },
            },
            altFieldFormatter: function(unixTime) {
              const newDate = moment.unix(unixTime / 1000);
              newDate.set('second', 0);

              return newDate.format(systemTimeFormat);
            },
          },
        );
      } else {
        initDatePicker(
          $(el),
          systemTimeFormat,
          jsTimeFormat,
          {
            enableTime: true,
            noCalendar: true,
            enableSeconds: enableSeconds,
            time_24hr: true,
            altFormat: $(el).data('customFormat') ?
              $(el).data('customFormat') : jsTimeFormat,
          },
        );
      }
    });

    // Rich text input
    findElements(
      '.rich-text',
      target,
    ).each(function(_k, el) {
      formHelpers.setupCKEditor(
        container,
        $(el).attr('id'),
        true,
        null,
        false,
        true);
    });

    // World clock control
    findElements(
      '.world-clock-control',
      target,
    ).each(function(_k, el) {
      // Get clocks container
      const $clocksContainer = $(el).find('.clocksContainer');

      // Get hidden input
      const $hiddenInput = $(el).find('.world-clock-value');

      /**
       * Configure the multiple world clock form
       * @param {*} container
       * @return {void}
       */
      function configureMultipleWorldClocks(container) {
        if (container.length == 0) {
          return;
        }

        const worldClockTemplate =
          formHelpers.getTemplate('worldClockTemplate');
        const worldClocks = $hiddenInput.attr('value') ?
          JSON.parse($hiddenInput.attr('value')) : [];

        if (worldClocks.length == 0) {
          // Add a template row
          const context = {
            title: '1',
            clockTimezone: '',
            timezones: timezones,
            buttonGlyph: 'fa-plus',
          };
          $(worldClockTemplate(context)).appendTo($clocksContainer);
          initClockRows(el);
        } else {
          // For each of the existing codes, create form components
          let i = 0;
          $.each(worldClocks, function(_index, field) {
            i++;

            const context = {
              title: i,
              clockTimezone: field.clockTimezone,
              clockHighlight: field.clockHighlight,
              clockLabel: field.clockLabel,
              timezones: timezones,
              buttonGlyph: ((i == 1) ? 'fa-plus' : 'fa-minus'),
            };
            $clocksContainer.append(worldClockTemplate(context));
          });
          initClockRows(el);
        }

        // Nabble the resulting buttons
        $clocksContainer.on('click', 'button', function(e) {
          e.preventDefault();

          // find the gylph
          if ($(e.currentTarget).find('i').hasClass('fa-plus')) {
            const context = {
              title: $clocksContainer.find('.form-clock').length + 1,
              clockTimezone: '',
              timezones: timezones,
              buttonGlyph: 'fa-minus',
            };
            $clocksContainer.append(worldClockTemplate(context));
            initClockRows(el);
          } else {
            // Remove this row
            $(e.currentTarget).closest('.form-clock').remove();
          }
        });
      }

      /**
       * Update the hidden input with the current clock values
       * @param {object} container
       */
      function updateClocksHiddenInput(container) {
        const worldClocks = [];
        $(container).find('.form-clock').each(function(_k, el2) {
          // Only add if the timezone is set
          if ($(el2).find('.localSelect select').val() != '') {
            worldClocks.push({
              clockTimezone: $(el2).find('.localSelect select').val(),
              clockHighlight: $(el2).find('.clockHighlight').is(':checked'),
              clockLabel: $(el2).find('.clockLabel').val(),
            });
          }
        });

        // Update the hidden input
        $hiddenInput.attr('value', JSON.stringify(worldClocks));
      }

      /**
       * Initialise the select2 elements
       * @param {object} container
       */
      function initClockRows(container) {
        // Initialise select2 elements
        $(container).find('.localSelect select.form-control')
          .each(function(_k, el2) {
            makeLocalSelect(
              $(el2),
              ($(container).hasClass('modal') ? $(container) : $('body')),
            );
          });

        // Update the hidden input when the clock values change
        $(container).find('input[type="checkbox"]').on('click', function() {
          updateClocksHiddenInput(container);
        });
        $(container).find('input[type="text"], select')
          .on('change', function() {
            updateClocksHiddenInput(container);
          });
      }

      // Setup multiple clocks
      configureMultipleWorldClocks($(el));
      initClockRows(el);
    });

    // Font selector
    findElements(
      '.font-selector',
      target,
    ).each(function(_k, el) {
      // Populate the font list with options
      const $el = $(el).find('select');
      $.ajax({
        method: 'GET',
        url: $el.data('searchUrl'),
        success: function(res) {
          if (res.data !== undefined && res.data.length > 0) {
            $.each(res.data, function(_index, element) {
              if ($el.data('value') === element.familyName) {
                $el.append(
                  $('<option value="' +
                    element.familyName +
                    '" selected>' +
                    element.name +
                    '</option>'));

                // Trigger change event
                $el.trigger(
                  'change',
                  [{
                    skipSave: true,
                  }]);
              } else {
                $el.append(
                  $('<option value="' +
                    element.familyName +
                    '">' +
                    element.name +
                    '</option>'));
              }
            });
          }

          // Disable fields on non read only mode
          if (readOnlyMode) {
            forms.makeFormReadOnly($(el));
          }
        },
      });
    });

    // Snippet selector
    findElements(
      '.snippet-selector',
      target,
    ).each(function(_k, el) {
      // Get select element
      const $select = $(el).find('select');

      // Get target field
      const targetFieldId = $select.data('target');
      const $targetField = $('[name=' + targetFieldId + ']');

      // Snippet mode
      const snippetMode = $select.data('mode');

      // Set normal snippet
      const setupSnippets = function($select) {
        formHelpers.setupSnippetsSelector(
          $select,
          function(e) {
            const value = $(e.currentTarget).val();

            // If there is no value, or target field is not found, do nothing
            if (value == undefined || value == '' || $targetField.length == 0) {
              return;
            }

            // Text to be inserted
            const text = '[' + value + ']';

            // Check if there is a CKEditor instance
            const ckeditorInstance = formHelpers
              .getCKEditorInstance('input_' + targetId + '_' + targetFieldId);

            if (ckeditorInstance) {
              // CKEditor
              formHelpers.insertToCKEditor(
                'input_' + targetId + '_' + targetFieldId,
                text,
              );
            } else if ($targetField.hasClass('code-input')) {
              // Monaco editor
              const editor =
                window.codeEditors['input_' + targetId + '_' + targetFieldId];

              const selection = editor.getSelection();
              const id = {
                major: 1,
                minor: 1,
              };
              const op = {
                identifier: id,
                range: selection,
                text: text,
                forceMoveMarkers: true,
              };
              editor.executeEdits('custom-code', [op]);

              // Trigger change event
              $targetField.trigger('change');
            } else {
              // Text area
              const cursorPosition = $targetField[0].selectionStart;
              const previousText = $targetField.val();

              // Insert text to the cursor position
              $targetField.val(
                previousText.substring(0, cursorPosition) +
                text +
                previousText.substring(cursorPosition));

              // Trigger change event
              $targetField.trigger('change');
            }
          },
        );
      };

      // Setup media snippet selector
      const setupMediaSnippets = function($select) {
        // Add URL to the select element
        $select.data('searchUrl', urlsForApi.library.get.url);

        // Add library download URL to the select element
        $select.data('imageUrl', urlsForApi.library.download.url);

        formHelpers.setupMediaSelector(
          $select,
          function(e) {
            const value = $(e.currentTarget).val();

            // If there is no value, do nothing
            if (value == undefined || value == '') {
              return;
            }

            // Text to be inserted
            const textURL = urlsForApi.library.download.url.replace(
              ':id',
              value,
            );
            const text = '<img alt="" src="' + textURL + '?preview=1" />';

            // Check if there is a CKEditor instance
            const ckeditorInstance = formHelpers
              .getCKEditorInstance('input_' + targetId + '_' + targetFieldId);

            if (ckeditorInstance) {
              // CKEditor
              formHelpers.insertToCKEditor(
                'input_' + targetId + '_' + targetFieldId,
                text,
              );
            } else if ($targetField.length > 0) {
              // Text area
              const cursorPosition = $targetField[0].selectionStart;
              const previousText = $targetField.val();

              $targetField.val(
                previousText.substring(0, cursorPosition) +
                text +
                previousText.substring(cursorPosition));
            }
          },
        );
      };

      if (snippetMode == 'dataSet') {
        const dataSetFied = $(el).data('dependsOn');
        const datasetId = $('[name=' + dataSetFied + ']').val();

        // Initialise the dataset order clause
        // if the dataset id is not empty
        if (datasetId) {
          // Get the dataset columns
          $.ajax({
            url: urlsForApi.dataset.search.url,
            type: 'GET',
            data: {
              dataSetId: datasetId,
            },
            success: function(response) {
              const data = response.data[0];
              if (
                data &&
                data.columns &&
                data.columns.length > 0) {
                // Clear select options
                $select.find('option[value]').remove();

                // Add data to the select options
                $.each(data.columns, function(_index, col) {
                  $select.append(
                    $('<option data-snippet-type="' +
                      col.dataType +
                      '" value="' +
                      col.heading + '|' + col.dataSetColumnId +
                      '">' +
                      col.heading +
                      '</option>'));
                });

                // If there are no options, hide
                if (data.columns.length == 0) {
                  $select.parent().hide();
                } else {
                  // Setup the snippet selector
                  setupSnippets($select);
                }

                // Disable fields on non read only mode
                if (readOnlyMode) {
                  forms.makeFormReadOnly($select.parent());
                }
              } else {
                $select.parent().hide();
              }
            },
            error: function() {
              $select.parent().append(
                '{% trans "An unknown error has occurred. Please refresh" %}',
              );
            },
          });
        }
      } else if (snippetMode == 'dataType') {
        // Get request path
        const requestPath =
          urlsForApi.widget.getDataType.url.replace(':id', targetId);

        // Get the data type snippets
        $.ajax({
          method: 'GET',
          url: requestPath,
          success: function(response) {
            if (response && response.fields && response.fields.length > 0) {
              // Clear select options
              $select.find('option[value]').remove();

              // Add data to the select options
              $.each(response.fields, function(_index, element) {
                $select.append(
                  $('<option data-snippet-type="' +
                    element.type +
                    '" value="' +
                    element.id +
                    '">' +
                    element.title +
                    '</option>'));
              });

              // If there are no options, hide
              if (response.fields.length == 0) {
                $select.parent().hide();
              } else {
                // Setup the snippet selector
                setupSnippets($select);
              }

              // Disable fields on non read only mode
              if (readOnlyMode) {
                forms.makeFormReadOnly($select.parent());
              }
            } else {
              $select.parent().hide();
            }
          },
          error: function() {
            $select.parent().append(
              '{% trans "An unknown error has occurred. Please refresh" %}',
            );
          },
        });
      } else if (snippetMode == 'options') {
        // Setup the snippet selector
        setupSnippets($select);
      } else if (snippetMode == 'media') {
        // Setup the media snippet selector
        setupMediaSnippets($select);
      }
    });

    // Effect selector
    findElements(
      '.effect-selector',
      target,
    ).each(function(_k, el) {
      // Populate the effect list with options
      const $el = $(el).find('select');
      const effectsType = $el.data('effects-type').split(' ');

      // Show option groups if we show all
      const showOptionGroups = (effectsType.indexOf('all') != -1);

      // Effects
      const effects = [
        {effect: 'none', group: 'showAll'},
        {effect: 'marqueeLeft', group: 'showAll'},
        {effect: 'marqueeRight', group: 'showAll'},
        {effect: 'marqueeUp', group: 'showAll'},
        {effect: 'marqueeDown', group: 'showAll'},
        {effect: 'noTransition', group: 'showPaged'},
        {effect: 'fade', group: 'showPaged'},
        {effect: 'fadeout', group: 'showPaged'},
        {effect: 'scrollHorz', group: 'showPaged'},
        {effect: 'scrollVert', group: 'showPaged'},
        {effect: 'flipHorz', group: 'showPaged'},
        {effect: 'flipVert', group: 'showPaged'},
        {effect: 'shuffle', group: 'showPaged'},
        {effect: 'tileSlide', group: 'showPaged'},
        {effect: 'tileBlind', group: 'showPaged'},
      ];

      // Add the options
      $.each(effects, function(_index, element) {
        // Don't add effect if it's none and
        // the target is a element or element-group
        if (
          effectsType.indexOf('noNone') != -1 &&
          element.effect === 'none'
        ) {
          return;
        }

        // Don't add effect if the target effect type isn't all or a valid type
        if (
          effectsType.indexOf('all') === -1 &&
          effectsType.indexOf(element.group) === -1
        ) {
          return;
        }

        // Show option group if it doesn't exist
        let $optionGroup = $el.find(`optgroup[data-type=${element.group}]`);
        if (
          showOptionGroups && $optionGroup.length == 0
        ) {
          $optionGroup =
            $(`<optgroup
              label="${effectsTranslations[element.group]}"
              data-type="${element.group}">
            </optgroup>`);
          $el.append($optionGroup);
        }

        // Check if we add to option group or main select
        const $appendTo = showOptionGroups ? $optionGroup : $el;

        $appendTo.append(
          $('<option value="' +
            element.effect +
            '" data-optgroup="' +
            element.group +
            '">' +
            effectsTranslations[element.effect] +
            '</option>'));
      });


      // If we have a value, select it
      if ($el.data('value') !== undefined) {
        $el.val($el.data('value'));
        // Trigger change
        $el.trigger(
          'change',
          [{
            skipSave: true,
          }]);
      }

      if ($(el).next().find('input[name="speed"]').length === 1) {
        let currentSelected = null;
        const speedInput = $(el).next().find('input[name="speed"]');
        const setEffectSpeed = function(prevEffect, newEffect, currSpeed) {
          const marqueeDefaultSpeed = 1;
          const noneMarqueeDefaultSpeed = 1000;
          const currIsMarquee = PlayerHelper.isMarquee(prevEffect);
          const isMarquee = PlayerHelper.isMarquee(newEffect);

          if (currIsMarquee && !isMarquee) {
            return noneMarqueeDefaultSpeed;
          } else if (!currIsMarquee && isMarquee) {
            return marqueeDefaultSpeed;
          } else {
            if (String(currSpeed).length === 0) {
              if (isMarquee) {
                return marqueeDefaultSpeed;
              } else {
                return noneMarqueeDefaultSpeed;
              }
            } else {
              return currSpeed;
            }
          }
        };

        $el.on('select2:open', function(e) {
          currentSelected = e.currentTarget.value;
        });

        speedInput.val(setEffectSpeed(
          currentSelected,
          currentSelected,
          speedInput.val(),
        ));

        $el.on('select2:select', function(e) {
          const data = e.params.data;
          const effect = data.id;

          speedInput.val(setEffectSpeed(
            currentSelected,
            effect,
            speedInput.val(),
          ));
        });
      }
    });

    // Font selector
    findElements(
      '.menu-board-category-selector',
      target,
    ).each(function(_k, el) {
      // Replace search url with the menuId
      const $el = $(el);
      const $elSelect = $(el).find('select');
      const dependsOnName = $el.data('depends-on');
      const $menu =
        $(container).find('[name=' + dependsOnName + ']');

      // Get menu value
      const menuId = $menu.val();

      if (menuId != '') {
        // Replace URL
        $elSelect.data(
          'search-url',
          $elSelect.data('search-url-base').replace(':id', menuId),
        );

        // Reset the select2 to update ajax url
        // $elSelect.select2('destroy');
        makePagedSelect($elSelect);
      }
    });

    // Select2 dropdown
    findElements(
      '.select2-hidden-accessible',
      target,
    ).each(function(_k, el) {
      const $el = $(el);

      $el.on('select2:open', function(event) {
        const $search = $(event.target).data('select2').dropdown?.$search;
        setTimeout(function() {
          if ($search) {
            $search.get(0).focus();
          }
        }, 10);
      });
    });

    let countExec = 0;
    // Stocks symbol search
    // Initialize tags input for properties panel with connectorProperties field
    findElements(
      'input[data-role=panelTagsInput]',
      target,
    ).each(function(_k, el) {
      const self = $(el);
      const autoCompleteUrl = self.data('autoCompleteUrl');
      const termKey = self.data('searchTermKey');

      if (autoCompleteUrl !== undefined && autoCompleteUrl !== '') {
        countExec++;

        // Tags input with autocomplete
        const tags = new Bloodhound({
          datumTokenizer: Bloodhound.tokenizers.whitespace,
          queryTokenizer: Bloodhound.tokenizers.whitespace,
          initialize: false,
          remote: {
            url: autoCompleteUrl,
            prepare: function(query, settings) {
              settings.data = {tag: query};

              if (termKey !== undefined && termKey !== '') {
                settings.data[termKey] = query;
              }

              return settings;
            },
            transform: function(list) {
              return $.map(list.data, function(tagObj) {
                if (countExec === 1) {
                  return {tag: tagObj.type};
                }

                return tagObj.type;
              });
            },
          },
        });

        const promise = tags.initialize();

        promise
          .done(function() {
            if (countExec > 1 || self.prev().is('.bootstrap-tagsinput')) {
              // Destroy tagsinput instance
              if (typeof self.tagsinput === 'function') {
                self.tagsinput('destroy');
              }

              countExec = 0;
            }

            const tagsInputOptions = {
              name: 'tags',
              source: tags.ttAdapter(),
            };

            if (countExec === 1) {
              tagsInputOptions.displayKey = 'tag';
              tagsInputOptions.valueKey = 'tag';
            }

            // Initialise tagsinput with autocomplete
            self.tagsinput({
              typeaheadjs: [{
                hint: true,
                highlight: true,
              }, tagsInputOptions],
            });
          })
          .fail(function() {
            console.info('Auto-complete for tag failed! Using default...');
            self.tagsinput();
          });
      } else {
        self.tagsinput();
      }
    });

    // Handle field dependencies for the container
    // only if we don't have a target
    if (!target) {
      $(container).find(
        '.xibo-form-input[data-depends-on]',
      ).each(function(_k, el) {
        const $target = $(el);
        let dependency = $target.data('depends-on');

        // If the dependency has already been added, skip
        if ($target.data('depends-on-added')) {
          return;
        }

        // Mark dependency as added to the target
        $target.attr('data-depends-on-added', true);

        // Check if the dependency value comes as an array value ( value[1])
        let dependencyArrayIndex = null;
        if (dependency.indexOf('[') !== -1 && dependency.indexOf(']') !== -1) {
          const dependencyArray = dependency.split('[');
          dependency = dependencyArray[0];
          dependencyArrayIndex = dependencyArray[1].replace(']', '');
        }

        // Add event listener to the dependency
        const base = '[name="' + dependency + '"]';

        // Add event listener to the $base element
        $(container).find(base).on('change', function(ev) {
          let valueToSet = null;
          const $base = $(ev.currentTarget);

          // If $base is a dropdown
          if ($base.is('select')) {
            // Get selected option
            const $selectedOption = $base.find(
              'option:selected',
            );

            // Check if the selected option has a data-set value
            // if not, use the value of the dropdown
            if ($selectedOption.data('set')) {
              valueToSet = $selectedOption.data('set');
            } else {
              valueToSet = $selectedOption.val();
            }
          } else if ($base.is('input[type="checkbox"]')) {
            // If $base is a checkbox
            valueToSet = $base.is(':checked');
          } else {
            valueToSet = $base.val();
          }

          // Check if value to set is a string or an array of values
          if (dependencyArrayIndex !== null && valueToSet !== null) {
            valueToSet = valueToSet.split(',')[dependencyArrayIndex];
          }

          // If the target is a checkbox, set the checked property
          if ($target.children('input[type="checkbox"]').length > 0) {
            // Set checked property value
            $target.children('input[type="checkbox"]')
              .prop('checked', valueToSet);
          } else if ($target.hasClass('colorpicker-input')) {
            // If the value is empty, clear the color picker
            if (valueToSet === '') {
              // Clear the color picker value
              $target.find('input').val('');

              // Also update the background color of the input group
              $target.find('.input-group-addon').css('background-color', '');
            } else if (
              valueToSet !== null &&
              valueToSet !== undefined &&
              Color(valueToSet).valid
            ) {
              // Add the color to the color picker
              $target.find('input')
                .colorpicker('setValue', valueToSet);

              // Also update the background color of the input group
              $target.find('.input-group-addon')
                .css('background-color', valueToSet);
            }
          } else if ($target.children('input[type="text"]').length > 0) {
            // If the target is a text input, set the value
            $target.children('input[type="text"]').val(valueToSet);
          } else {
            // For the remaining cases, set the
            // value of the dependency to the target data attribute
            $target.data('dependsOnValue', valueToSet);

            // Reset the target form field
            forms.initFields(container, $target, targetId, readOnlyMode);
          }
        });
      });
    }
  },
  /**
     * Handle form field replacements
     * @param {*} container - The form container
     * @param {*} baseObject - The base object to replace
     */
  handleFormReplacements: function(container, baseObject) {
    const replaceHTML = function(htmlString) {
      htmlString = htmlString.replace(/\%(.*?)\%/g, function(_m, group) {
        // Replace trimmed match with the value of the base object
        return group.split('.').reduce((a, b) => {
          return (a[b]) || `%${b}%`;
        }, baseObject);
      });

      return htmlString;
    };

    // Replace title and alternative title for the elements that have them
    $(container).find('.xibo-form-input > [title], .xibo-form-btn[title]')
      .each(function(_idx, el) {
        const $element = $(el);
        const elementTitle = $element.attr('title');
        const elementAlternativeTitle = $element.attr('data-original-title');

        // If theres title and it contains a replacement special character
        if (elementTitle && elementTitle.indexOf('%') > -1) {
          $element.attr('title', replaceHTML(elementTitle));
        }

        // If theres an aletrnative title and it
        // contains a replacement special character
        if (
          elementAlternativeTitle &&
          elementAlternativeTitle.indexOf('%') > -1
        ) {
          $element.attr(
            'data-original-title',
            replaceHTML(elementAlternativeTitle));
        }
      });

    // Replace inner html for input direct children
    $(container).find('.xibo-form-input > *, .xibo-form-btn')
      .each(function(_idx, el) {
        const $element = $(el);
        const elementInnerHTML = $element.html();

        // If theres inner html and it contains a replacement special character
        if (elementInnerHTML && elementInnerHTML.indexOf('%') > -1) {
          $element.html(replaceHTML(elementInnerHTML));
        }
      });
  },
  /**
     * Set the form conditions
     * @param {object} container - The form container
     * @param {object} baseObject - The base object
     * @param {string} targetId - The target id
     * @param {boolean} isTopLevel - Is the target parent top level
     */
  setConditions: function(
    container,
    baseObject,
    targetId,
    isTopLevel = true,
  ) {
    $(container).find('.xibo-form-input[data-visibility]')
      .each(function(_idx, el) {
        let visibility = $(el).data('visibility');

        // Handle replacements for visibilty rules
        visibility = JSON.parse(
          JSON.stringify(visibility).replace(/\$(.*?)\$/g, function(_m, group) {
            // Replace match with the value of the base object
            return group.split('.').reduce((a, b) => a[b], baseObject);
          }),
        );

        // Check if target element for condition exists
        const isConditionTargetExists = function(test) {
          if (test?.field && targetId) {
            const $conditionTargetElem = $(container).find(
              `[name="${test.field}"]`);

            return $conditionTargetElem.length !== 0;
          }

          if (test.conditions.length > 0 && targetId) {
            for (let i = 0; i < test.conditions.length; i++) {
              const conditionField = test.conditions[i].field;
              const $conditionTargetElem = $(container)
                .find(`[name="${conditionField}"]`);

              if ($conditionTargetElem.length === 0) {
                return false;
              }
            }

            return true;
          }
        };

        // Handle a single condition
        const buildTest = function(test, $testContainer) {
          let testTargets = '';
          const testType = test.type;
          const testConditions = test.conditions;

          // Check test
          const checkTest = function() {
            let testResult;

            for (let i = 0; i < testConditions.length; i++) {
              const condition = testConditions[i];
              const fieldId = `[name="${condition.field}"]`;
              const $conditionTarget =
                $(container).find(fieldId);

              // Get condition target value based on type
              const conditionTargetValue =
                ($conditionTarget.length !== 0 &&
                  $conditionTarget.attr('type') === 'checkbox') ?
                  $conditionTarget.is(':checked') :
                  $conditionTarget.val();

              const newTestResult = checkCondition(
                condition.type,
                condition.value,
                conditionTargetValue,
                isTopLevel,
              );

              // If there are multiple conditions
              // we need to add the joining logic to them
              if (i > 0) {
                if (testType === 'and') {
                  testResult = testResult && newTestResult;
                } else if (testType === 'or') {
                  testResult = testResult || newTestResult;
                }
              } else {
                testResult = newTestResult;
              }
            }

            // If the test is true, show the element
            if (testResult) {
              $testContainer.show();
            } else {
              $testContainer.hide();
            }
          };

          // Get all the targets for the test
          for (let i = 0; i < test.conditions.length; i++) {
            // Add the target to the list
            const fieldId = `[name="${test.conditions[i].field}"]`;
            testTargets += fieldId;

            // If there are multiple conditions, add a comma
            if (i < test.conditions.length - 1) {
              testTargets += ',';
            }
          }

          if (String(testTargets).length > 0) {
            // Check test when any of the targets change
            $(container).find(testTargets).on('change', checkTest);
          }

          // Run on first load
          checkTest();
        };

        // If visibility tests are an array, process each one of the options
        if (Array.isArray(visibility)) {
          for (let i = 0; i < visibility.length; i++) {
            const test = visibility[i];
            buildTest(test, $(el));
          }
        } else {
          // Otherwise, process the single condition
          isConditionTargetExists(visibility) && buildTest({
            conditions: [visibility],
            test: '',
          }, $(el));
        }
      });
  },
  /**
     * Check for spacing issues on the form inputs
     * @param {object} $container - The form container
     */
  checkForSpacingIssues: function($container) {
    $container.find('input[type=text]').each(function(_idx, el) {
      formRenderDetectSpacingIssues(el);

      $(el).on('keyup', _.debounce(function() {
        formRenderDetectSpacingIssues(el);
      }, 500));
    });
  },
  /**
    * Disable all the form inputs and make it read only
    * @param {object} $formContainer - The form container
    */
  makeFormReadOnly: function($formContainer) {
    // Disable inputs, select, textarea and buttons
    $formContainer
      .find(
        'input, select, ' +
        'textarea, button:not(.copyTextAreaButton)',
      ).attr('disabled', 'disabled');

    // Disable color picker plugin
    $formContainer
      .find('.colorpicker-input i.input-group-addon')
      .off('click');

    // Hide buttons
    $formContainer.find(
      'button:not(.copyTextAreaButton), ' +
      '.date-clear-button',
    ).hide();

    // Hide bootstrap switch
    $formContainer.find('.bootstrap-switch').hide();
  },

  /**
   * Reload Rich text fields
   * @param {object} $formContainer - The form container
   */
  reloadRichTextFields: function($formContainer) {
    $formContainer.find('.rich-text').each(function(_k, el) {
      const elId = $(el).attr('id');
      const $parent = $(el).parent();

      // Hide elements in editor to avoid glitch
      $parent.addClass('loading');

      // Destroy CKEditor
      formHelpers.destroyCKEditor(elId);

      // Reload
      formHelpers.setupCKEditor(
        $formContainer,
        elId,
        true,
        null,
        false,
        true);

      // Hide text area to prevent flicker
      $parent.removeClass('loading');
    });
  },

  /**
   * Create form fields for fallback data
  * @param {object} dataType
  * @param {object} container
  * @param {object} fallbackData
  * @param {object} widget
  * @param {callback} reloadWidgetCallback
   */
  createFallbackDataForm: function(
    dataType,
    container,
    fallbackData,
    widget,
    reloadWidgetCallback,
  ) {
    const dataFields = dataType.fields;

    const createField = function(field, data) {
      const fieldClone = {...field};
      const auxId = Math.floor(Math.random() * 1000000);

      if (data != undefined) {
        fieldClone.value = data;
      }

      // Set date variant
      if (
        ['datetime', 'date', 'time', 'month'].indexOf(fieldClone.type) != -1
      ) {
        fieldClone.variant = (fieldClone.type === 'datetime')?
          'dateTime' :
          fieldClone.type;
        fieldClone.type = 'date';
      }

      // Set image variant
      if (fieldClone.type === 'image') {
        fieldClone.type = 'mediaSelector';
        fieldClone.mediaSearchUrl = urlsForApi.library.get.url;
        fieldClone.initialValue = fieldClone.value;
        fieldClone.initialKey = 'mediaId';
      }

      // If field is type string, change it to text
      if (fieldClone.type === 'string') {
        fieldClone.type = 'text';
      }

      // Set name as id and give a unique id
      fieldClone.name = fieldClone.id;
      fieldClone.id = fieldClone.id + '_' + auxId;

      // Add custom class to prevent auto save
      fieldClone.customClass = 'fallback-property';

      let $newField;
      if (templates.forms.hasOwnProperty(fieldClone.type)) {
        $newField = $(templates.forms[fieldClone.type](fieldClone));
      }

      // Add helper to required fields
      if ($newField && $newField.is('[data-is-required="true"]')) {
        $newField.find('label')
          .append(`<span class="ml-1"
            title=${fallbackDataTrans.requiredField}>*</span>`);
      }

      return $newField;
    };

    const updateRecordPreview = function($record, recordData) {
      const $previewsContainer =
        $record.find('.fallback-data-record-previews');

      // Empty container
      $previewsContainer.empty();

      dataFields.forEach((field) => {
        if (recordData && recordData[field.id]) {
          const fieldData = recordData[field.id];

          // New field preview
          const $recordPreview = $(templates.forms.fallbackDataRecordPreview({
            trans: fallbackDataTrans,
            field: field,
            data: fieldData,
          }));

          $record.find('.fallback-data-record-previews')
            .append($recordPreview);
        }
      });
    };

    const saveRecord = function($record) {
      const recordData = {};
      let invalidRequired = false;

      // Get input fields and build data to be saved
      dataFields.forEach((field) => {
        const $field = $record.find('[name="' + field.id + '"]');
        const value = $field.val();
        const required =
          $field.parents('.fallback-property').is('[data-is-required="true"]');

        if (value == '' && required) {
          invalidRequired = true;
        } else if (value != '') {
          recordData[field.id] = value;
        }
      });

      // If record data is empty or invalid, thow error and don't save
      if ($.isEmptyObject(recordData)) {
        toastr.error(fallbackDataTrans.invalidRecordEmpty);
        return;
      } else if (invalidRequired) {
        toastr.error(fallbackDataTrans.invalidRecordRequired);
        return;
      }

      const updateRecord = function() {
        // Update preview
        updateRecordPreview($record, recordData);

        reloadWidgetCallback();

        $record.removeClass('editing');
      };

      // Save or add new record
      const recordId = $record.data('recordId');
      const displayOrder = $record.index();
      if (recordId) {
        widget.editFallbackDataRecord(recordId, recordData, displayOrder)
          .then((_res) => {
            if (_res.success) {
              updateRecord();
            }
          });
      } else {
        widget.addFallbackData(recordData, displayOrder)
          .then((_res) => {
            if (_res.success) {
              // Add id to record object
              $record.data('recordId', _res.id);
              updateRecord();
            }
          });
      }
    };

    const editRecord = function($record) {
      $record.addClass('editing');
    };

    const deleteRecord = function($record) {
      const recordId = $record.data('recordId');

      if (recordId) {
        widget.deleteFallbackDataRecord(recordId)
          .then((_res) => {
            if (_res.success) {
              // Remove object
              $record.remove();

              reloadWidgetCallback();
            }
          });
      } else {
        // Remove object
        $record.remove();
      }
    };

    const createRecord = function(data = {}) {
      const recordData = data.data;
      const displayOrder = data.displayOrder;
      const recordId = data.id;

      const $recordContainer = $(templates.forms.fallbackDataRecord({
        trans: fallbackDataTrans,
      }));

      // Add record id to data if exists
      if (recordId != undefined) {
        $recordContainer.data('recordId', recordId);
      }

      // Add display order to data if exists
      if (displayOrder != undefined) {
        $recordContainer.data('displayOrder', displayOrder);
      }

      // Add properties from data
      dataFields.forEach((field) => {
        const fieldData = (recordData) && recordData[field.id];
        const $newField = createField(field, fieldData);

        // New field input
        $recordContainer.find('.fallback-data-record-fields')
          .append($newField);
      });

      // Initialize fields
      forms.initFields($recordContainer);

      // Record preview
      updateRecordPreview($recordContainer, recordData);

      // Mark new field as editing if it's a new record
      if ($.isEmptyObject(data)) {
        $recordContainer.addClass('editing');
      }

      // Handle buttons
      $recordContainer.find('button').on('click', function(ev) {
        const actionType =
          $(ev.currentTarget).data('action');

        if (actionType == 'save-record') {
          saveRecord($recordContainer);
        } else if (actionType == 'delete-record') {
          deleteRecord($recordContainer);
        } else if (actionType == 'edit-record') {
          editRecord($recordContainer);
        }
      });

      return $recordContainer;
    };

    // Create main content
    $(container).append(templates.forms.fallbackDataContent({
      data: dataType,
      trans: fallbackDataTrans,
      showFallback: widget.getOptions().showFallback,
    }));

    const $recordsContainer =
      $(container).find('.fallback-data-records');

    // If we have existing data, add it to the control
    if (fallbackData) {
      // Sort array by display order
      fallbackData.sort((a, b) => {
        return a.displayOrder - b.displayOrder;
      });

      fallbackData.forEach((data) => {
        $recordsContainer.append(
          createRecord(data),
        );
      });

      // Call Xibo Init for the records container
      XiboInitialise('.fallback-data-records');
    }

    // Handle create record button
    $(container).find('[data-action="add-new-record"]').on('click', function() {
      $recordsContainer.append(
        createRecord(),
      );

      // Call Xibo Init for the records container
      XiboInitialise('.fallback-data-records');
    });

    // Init sortable
    $recordsContainer.sortable({
      axis: 'y',
      items: '.fallback-data-record',
      containment: 'parent',
      update: function() {
        // Create records structure
        let idxAux = 0;
        const records = [];

        $recordsContainer.find('.fallback-data-record').each((_idx, record) => {
          const recordData = $(record).data();

          if (recordData.recordId) {
            records.push({
              dataId: recordData.recordId,
              displayOrder: idxAux,
            });

            // Update data on record
            $(record).data('displayOrder', idxAux);

            idxAux++;
          }
        });

        widget.saveFallbackDataOrder(records)
          .then((_res) => {
            if (_res.success) {
              reloadWidgetCallback();
            }
          });
      },
    });
  },
};
