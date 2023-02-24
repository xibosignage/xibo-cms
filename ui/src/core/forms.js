/* eslint-disable new-cap */
/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2022 Xibo Signage Ltd
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

// Common functions/tools
const Common = require('../editor-core/common.js');

// Check condition
const checkCondition = function(type, value, targetValue) {
  if (type === 'eq' && targetValue == value) {
    return true;
  } else if (type === 'neq' && targetValue != value) {
    return true;
  } else if (type === 'gt' && targetValue > value) {
    return true;
  } else if (type === 'lt' && targetValue < value) {
    return true;
  } else if (type === 'egt' && targetValue >= value) {
    return true;
  } else if (type === 'elt' && targetValue <= value) {
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
     */
  createFields: function(properties, targetContainer, targetId) {
    for (const key in properties) {
      if (properties.hasOwnProperty(key)) {
        const property = properties[key];

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
        }

        // dashboards available services
        if (property.type === 'dashboardSelector') {
          property.dashboardSearchUrl = urlsForApi.dashboard.search.url.replace(':id', targetId)
          // If we don't have a value, set value key pair to null
          if (property.value == '') {
            property.initialValue = null;
            property.initialKey = null;
          } else {
            property.initialValue = property.value;
            property.initialKey = 'type';
          }
        }

        // Change the name of the property to the id
        property.name = property.id;

        // Create the property id based on the targetId
        if (targetId) {
          property.id = targetId + '_' + property.id;
        }

        // Append the property to the target container
        if (templates.forms.hasOwnProperty(property.type)) {
          const $newField = $(templates.forms[property.type](property))
            .appendTo($(targetContainer));

          // Handle help text
          if (property.helpText) {
            $newField.find('.input-info-container').append(
              $(templates.forms.addOns.helpText({
                helpText: property.helpText,
              })),
            );
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

          // Handle depends on property
          if (property.dependsOn) {
            $newField.attr('data-depends-on', property.dependsOn);
          }

          // Add visibility to the field
          if (property.visibility.length) {
            $newField.attr('data-visibility', property.visibility);
          }

          // Add set default to the field
          if (property.setDefault.length) {
            $newField.attr(
              'data-set-default',
              JSON.stringify(property.setDefault),
            );
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
   */
  initFields: function(container, target, targetId) {
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

          const $orderClauseHiddenInput = $el.find('#' + $el.data('order-id'));
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
            $orderClauseHiddenInput.val(JSON.stringify(orderClauses));
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
          $el.on('change', 'select', function() {
            updateHiddenField();
          });
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

          const $selectHiddenInput = $el.find('#' + $el.data('select-id'));
          const selectedValue = $selectHiddenInput.val() ?
            JSON.parse(
              $selectHiddenInput.val(),
            ) : [];

          // Update the hidden field with a JSON string
          // of the order clauses
          const updateHiddenField = function() {
            const selectedCols = [];

            $colsInContainer.find('li').each(function(_index, el) {
              const colId = $(el).attr('id');
              selectedCols.push(colId);
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
            $selectHiddenInput.val(JSON.stringify(selectedCols));
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
            const dataSetColumnId = col.dataSetColumnId.toString();
            if (selectedValue.includes(dataSetColumnId)) {
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

          // Setup lists drag and sort ( with double click )
          $el.find('#columnsIn, #columnsOut').sortable({
            connectWith: '.connectedSortable',
            dropOnEmpty: true,
            receive: function() {
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

          // Update hidden field on start
          updateHiddenField();
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
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
            $el.find('#' + $el.data('filter-id'));
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
            $filterClauseHiddenInput.val(JSON.stringify(filterClauses));
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
          $el.on('change', 'select, input', function() {
            updateHiddenField();
          });
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error(jqXHR, textStatus, errorThrown);
        });
      }
    });

    // Code editor
    findElements(
      '.xibo-code-input',
      target,
    ).each(function(_k, el) {
      const $textArea = $(el).find('.code-input');
      const inputValue = $textArea.val();
      const codeType = $textArea.data('codeType');

      const newEditor =
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

      newEditor.onDidChangeModelContent(() => {
        $textArea.val(newEditor.getValue());
      });
    });

    // Colour picker
    findElements(
      '.colorpicker-input',
      target,
    ).each(function(_k, el) {
      // Init the colour picker
      $(el).colorpicker();

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
        {},
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
        },
      });
    });

    // Effect selector
    findElements(
      '.effect-selector',
      target,
    ).each(function(_k, el) {
      // Populate the effect list with options
      const $el = $(el).find('select');
      const effectsType = $el.data('effects-type');

      // Effects
      const effects = [
        {effect: 'none', group: 'all'},
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

      // Add option groups
      if (effectsType === 'showAll' || effectsType === 'all') {
        $el.append(
          $('<optgroup label="' + effectsTranslations.showAll + '">'),
        );
      }

      if (effectsType === 'showPaged' || effectsType === 'all') {
        $el.append(
          $('<optgroup label="' + effectsTranslations.showPaged + '">'),
        );
      }

      // Add the options to the respective groups
      $.each(effects, function(_index, element) {
        if (element.group === 'all') {
          // Add before the optgroups
          $el.prepend(
            $('<option value="' +
              element.effect +
              '">' +
              effectsTranslations[element.effect] +
              '</option>'));
        } else {
          $el.find(
            'optgroup[label="' +
            effectsTranslations[element.group] +
            '"]',
          ).append(
            $('<option value="' +
              element.effect +
              '" data-optgroup="' +
              element.group +
              '">' +
              effectsTranslations[element.effect] +
              '</option>'));
        }
      });


      // If we have a value, select it
      if ($el.data('value') !== undefined) {
        $el.val($el.data('value'));
      }
    });

    // Handle field dependencies for the container
    // only if we don't have a target
    if (!target) {
      $(container).find(
        '.xibo-form-input[data-depends-on]',
      ).each(function(_k, el) {
        const $target = $(el);
        const dependency = $target.data('depends-on');

        // If the dependency has already been added, skip
        if ($target.data('depends-on-added')) {
          return;
        }

        // Mark dependency as added to the target
        $target.data('depends-on-added', true);

        // Add event listener to the dependency
        const elementId = (targetId) ?
          '#input_' + targetId + '_' + dependency :
          '#input_' + dependency;
        $(container).find(elementId)
          .on('change', function(ev) {
            // Set dependency value to the target as a data attribute
            $target.data('depends-on-value', $(ev.currentTarget).val());

            // Reset the target form field
            forms.initFields(container, $target, targetId);
          });
      });
    }

    // Handle set default
    $(container).find(
      '.xibo-form-input[data-set-default]',
    ).each(function(_k, el) {
      const setDefaultRules = $(el).data('set-default');
      const $trigger = $(el).find('input, select');

      for (const rule of setDefaultRules) {
        const $target = $('#input_' + targetId + '_' + rule.field);
        $trigger.on('change', function(ev) {
          // Test all the conditions
          let testResult = false;

          for (let i = 0; i < rule.test.conditions.length; i++) {
            const condition = rule.test.conditions[i];
            const newTestResult =
              checkCondition(
                condition.type,
                condition.value,
                $trigger.val(),
              );

            // If we have multiple conditions, we need
            // to combine them with the test type
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

          // If the test result is true, set the value
          if (testResult) {
            // If it's a color input, update the color picker
            if ($target.parents('.colorpicker-input').length) {
              // If the value is empty, clear the color picker
              if (rule.value === '') {
                // Clear the color picker value
                $target.val('');
                // Also update the background color of the input group
                $target.parents('.colorpicker-input')
                  .find('.input-group-addon').css('background-color', '');
              } else {
                // Add the color to the color picker
                $target.colorpicker('setValue', rule.value);

                // Also update the background color of the input group
                $target.parents('.colorpicker-input').find('.input-group-addon')
                  .css('background-color', rule.value);
              }
            } else {
              // Otherwise, just set the value
              $target.val(rule.value);
            }
          }
        });
      }
    });
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
        return group.split('.').reduce((a, b) => a[b], baseObject);
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
     */
  setConditions: function(container, baseObject, targetId) {
    $(container).find('.xibo-form-input[data-visibility]')
      .each(function(_idx, el) {
        let visibility = $(el).data('visibility');

        // Handle replacements for visibilty rules
        visibility = JSON.parse(
          JSON.stringify(visibility).replace(/\%(.*?)\%/g, function(_m, group) {
            // Replace match with the value of the base object
            return group.split('.').reduce((a, b) => a[b], baseObject);
          }),
        );

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
              const fieldId = (targetId) ?
                '#input_' + targetId + '_' + condition.field :
                '#input_' + condition.field;
              const $conditionTarget =
                $(container).find(fieldId);

              // Get condition target value based on type
              const conditionTargetValue =
                ($conditionTarget.attr('type') == 'checkbox') ?
                  $conditionTarget.is(':checked') :
                  $conditionTarget.val();

              newTestResult = checkCondition(
                condition.type,
                condition.value,
                conditionTargetValue,
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
            const fieldId = (targetId) ?
              '#input_' + targetId + '_' + test.conditions[i].field :
              '#input_' + test.conditions[i].field;
            testTargets += fieldId;

            // If there are multiple conditions, add a comma
            if (i < test.conditions.length - 1) {
              testTargets += ',';
            }
          }

          // Check test when any of the targets change
          $(container).find(testTargets).on('change', checkTest);

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
          buildTest({
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
};
