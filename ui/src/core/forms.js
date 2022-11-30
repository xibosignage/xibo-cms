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

// Common funtions/tools
const Common = require('../editor-core/common.js');

window.forms = {
  /**
     * Create form inputs from an array of elements
     * @param {object} properties - The properties to set on the form
     * @param {object} targetContainer - The container to add the properties to
     */
  createFields: function(properties, targetContainer) {
    for (const key in properties) {
      if (properties.hasOwnProperty(key)) {
        const property = properties[key];

        // Handle default value
        if (property.value === null && property.default !== undefined) {
          property.value = property.default;
        }

        // Handle render condition
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

        // Append the property to the target container
        if (templates.forms.hasOwnProperty(property.type)) {
          $(templates.forms[property.type](property))
            .appendTo($(targetContainer));
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
   * @param {object} container - Main container
   */
  initFields: function(container) {
    // Code editor
    $(container).find('.xibo-code-input').each(function(_k, el) {
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
    $(container).find(
      '.colorpicker-input',
    ).each(function(_k, el) {
      // Init the colour picker
      $(el).colorpicker();

      // If we have a default value, set it on unfocus
      if ($(el).data('default') !== undefined) {
        const defaultValue = $(el).data('default');
        const $inputElement = $(el).find('input');
        $inputElement.on('focusout', function() {
          if ($inputElement.val() == '') {
            $(el).colorpicker('setValue', defaultValue);
          }
        });
      }
    });

    // Date picker - date only
    $(container).find(
      '.dateControl.date:not(.datePickerHelper)',
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
    $(container).find(
      '.dateControl.dateTime:not(.datePickerHelper)',
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
    $(container).find(
      '.dateControl.month:not(.datePickerHelper)',
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
    $(container).find(
      '.dateControl.time:not(.datePickerHelper)',
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
     */
  setConditions: function(container, baseObject) {
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

          // Check test
          const checkTest = function() {
            let testResult;

            for (let i = 0; i < testConditions.length; i++) {
              const condition = testConditions[i];
              const $conditionTarget = $(container).find(condition.field);

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
            testTargets += test.conditions[i].field;

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
