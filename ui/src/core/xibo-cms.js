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
let lastForm;
let autoSubmitTemplate = null;

// Fix startsWith string prototype for IE
if (!String.prototype.startsWith) {
  // eslint-disable-next-line no-extend-native
  String.prototype.startsWith = function(searchString, position) {
    position = position || 0;
    return this.indexOf(searchString, position) === position;
  };
}

// Fix endsWith string prototype for IE
if (!String.prototype.endsWith) {
  // eslint-disable-next-line no-extend-native
  String.prototype.endsWith = function(suffix) {
    return this.indexOf(suffix, this.length - suffix.length) !== -1;
  };
}

// Set up the light boxes
$(document).on('*[data-toggle="lightbox"]', 'click', function(event) {
  event.preventDefault();
  $(event.currentTarget).ekkoLightbox({
    onContentLoaded: function() {
      const $container = $('.ekko-lightbox-container');
      $container.css({
        'max-height': $container.height(),
        height: '',
        'max-width': $container.width(),
      });
      $container.parents('.modal-content').css({width: 'fit-content'});
    },
  });
});

$(function() {
  // Code from: http://stackoverflow.com/questions/7585351/testing-for-console-log-statements-in-ie/7585409#7585409
  // Handles console.log calls when there is no console
  if (!window.console) {
    (function() {
      const names = ['log', 'debug', 'info', 'warn', 'error',
        'assert', 'dir', 'dirxml', 'group', 'groupEnd', 'time',
        'timeEnd', 'count', 'trace', 'profile', 'profileEnd'];
      const l = names.length;
      let i;

      window.console = {};

      for (i = 0; i < l; i++) {
        window.console[names[i]] = function() { };
      }
    }());
  }

  // Highlight navigation
  const $sideBarWrapperScroll = $('#sidebar-wrapper ul.sidebar');
  const $selectedMenu = $sideBarWrapperScroll.find('li.sidebar-list a[href="' +
    window.location.pathname +
    '"]').addClass('sidebar-list-selected');

  // Scroll into view if needed
  if (
    $selectedMenu.length > 0 &&
    $sideBarWrapperScroll.length > 0 &&
    $selectedMenu.offset().top + $selectedMenu.height() >
    $sideBarWrapperScroll.height() - $sideBarWrapperScroll[0].scrollTop
  ) {
    $selectedMenu[0].scrollIntoView();
  }

  // Every minute
  setInterval('XiboPing(\'' + clockUrl + '\')', 1000 * 60);

  // Every 3 minutes
  setInterval('XiboPing(\'' + pingUrl + '\')', 1000 * 60 * 3);

  XiboInitialise('');
});

/**
 * Initialises the page/form
 * @param {Object} scope (the form or page)
 * @param {Object} [options] (options for the form)
 */
window.XiboInitialise = function(scope, options) {
  // If the scope isnt defined then assume the entire page
  if (scope == undefined || scope == '') {
    scope = ' ';
  }

  // Search for any Buttons / Links on the page that are used to load forms
  $(scope + ' .XiboFormButton').on('click', function(ev) {
    const $target = $(ev.currentTarget);
    const eventStart = $target.data('eventStart');
    const eventEnd = $target.data('eventEnd');
    if (eventStart !== undefined && eventEnd !== undefined) {
      const data = {
        eventStart: eventStart,
        eventEnd: eventEnd,
      };
      XiboFormRender($target, data);
    } else {
      XiboFormRender($target);
    }

    return false;
  });

  // Search for any Buttons / Links on the page
  // that are used to load custom forms
  $(scope + ' .XiboCustomFormButton').on('click', function(ev) {
    XiboCustomFormRender($(ev.currentTarget));

    return false;
  });

  // Search for any Buttons that redirect to another page
  $(scope + ' .XiboRedirectButton').on('click', function(ev) {
    window.location = $(ev.currentTarget).attr('href');
  });

  // Search for any Buttons / Linkson the page
  // that are used to load hover tooltips
  $(scope + ' .XiboHoverButton')
    .on('mouseenter', function(ev) {
      const formUrl = $(ev.currentTarget).attr('href');

      XiboHoverRender(formUrl, e.pageX, e.pageY);

      return false;
    }).on('mouseleave', function() {
      // Dont do anything on hover off - the hover on deals with
      // destroying itself.
      return false;
    });

  // Form validation
  $(scope + ' .XiboForm').each((_idx, form) => {
    const $form = $(form);
    forms.validateForm(
      $form, // form
      $form.parent(), // container
      {
        submitHandler: XiboFormSubmit,
      },
    );
  });

  // Prevent manual numbers input outside of min/max
  $(
    scope + ' input[type="number"][min], ' +
    scope + ' input[type="number"][max]',
  ).each((_idx, input) => {
    const $input = $(input);
    const max = $input.attr('max');
    const min = $input.attr('min');

    $input.on('blur', () => {
      (max && $input.val() > max) &&
        ($input.val(max).trigger('change'));
      (min && $input.val() < min) &&
        ($input.val(min).trigger('change'));
    });
  });

  // Links that just need to be submitted as forms
  $(scope + ' .XiboAjaxSubmit').on('click', function(ev) {
    $.ajax({
      type: 'post',
      url: $(ev.currentTarget).attr('href'),
      cache: false,
      dataType: 'json',
      success: XiboSubmitResponse,
    });

    return false;
  });

  // Forms that we want to be submitted without validation.
  $(scope + ' .XiboAutoForm').on('submit', function(ev) {
    XiboFormSubmit(ev.currentTarget);

    return false;
  });

  // Text Form validation
  $(scope + ' .XiboTextForm').each((_idx, form) => {
    const $form = $(form);
    console.log('validateForm XiboTextForm');
    forms.validateForm(
      $form, // form
      $form.parent(), // container
      {
        submitHandler: XiboFormSubmit,
      },
    );
  });

  // Search for any help enabled elements
  $(scope + ' .XiboHelpButton').on('click', function(ev) {
    const formUrl = $(ev.currentTarget).attr('href');

    window.open(formUrl);

    return false;
  });

  // Special drop down forms (to act as a menu instead of a usual dropdown)
  $(scope + ' .dropdown-menu').on('click', function(ev) {
    if ($(ev.currentTarget).hasClass('dropdown-menu-form')) {
      e.stopPropagation();
    }
  });

  $(scope + ' .selectPicker select.form-control').select2({
    dropdownParent: ($(scope).hasClass('modal') ? $(scope) : $('body')),
    templateResult: function(state) {
      if (!state.id) {
        return state.text;
      }

      const $el = $(state.element);

      if ($el.data().content !== undefined) {
        return $($el.data().content);
      }

      return state.text;
    },
  });

  // make a vanilla layout, display and media selector for reuse
  $(scope + ' .pagedSelect select.form-control').each(function(_idx, el) {
    const $target = $(el);
    const anchor = $target.data('anchorElement');
    const inModal = $(scope).hasClass('modal');
    if (anchor !== undefined && anchor !== '') {
      makePagedSelect($target, $(anchor));
    } else if (inModal) {
      makePagedSelect($target, $(scope));
    } else {
      makePagedSelect($target, $('body'));
    }
  });

  // make a local select that search for text or tags
  $(scope + ' .localSelect select.form-control')
    .each(function(_idx, el) {
      makeLocalSelect(
        $(el), ($(scope).hasClass('modal') ? $(scope) : $('body')),
      );
    });

  // Notification dates
  $(scope + ' span.notification-date').each(function(_i, el) {
    $(el).html(moment($(el).html(), 'X').fromNow());
  });

  // Switch form elements
  $(scope + ' input.bootstrap-switch-target').each(function(_i, el) {
    $(el).bootstrapSwitch();
  });

  // Colour picker
  $(scope + ' .colorpicker-input:not(.colorpicker-element)')
    .each(function(_i, el) {
      $(el).colorpicker({
        container: $(el).parent(),
      });
    });

  // Initialize tags input form
  $(
    scope + ' input[data-role=tagsInputInline], ' +
    scope + ' input[data-role=tagsInputForm], ' + scope +
    ' select[multiple][data-role=tagsInputForm]',
  ).each(function(_idx, el) {
    const $self = $(el);
    const autoCompleteUrl = $self.data('autoCompleteUrl');

    if (autoCompleteUrl != undefined && autoCompleteUrl != '') {
      // Tags input with autocomplete
      const tags = new Bloodhound({
        datumTokenizer: Bloodhound.tokenizers.whitespace,
        queryTokenizer: Bloodhound.tokenizers.whitespace,
        initialize: false,
        remote: {
          url: autoCompleteUrl,
          prepare: function(query, settings) {
            settings.data = {tag: query};
            return settings;
          },
          filter: function(list) {
            return $.map(list.data, function(tagObj) {
              return {
                tag: tagObj.tag,
              };
            });
          },
        },
        sorter: function(a, b) {
          const nameA = a.tag.toUpperCase();
          const nameB = b.tag.toUpperCase();
          if (nameA < nameB) {
            return -1;
          }
          if (nameA > nameB) {
            return 1;
          }

          // Names must be the same
          return 0;
        },
      });

      const promise = tags.initialize();

      promise
        .done(function() {
          // Initialise tagsinput with autocomplete
          $self.tagsinput({
            typeaheadjs: {
              name: 'tags',
              displayKey: 'tag',
              valueKey: 'tag',
              source: tags.ttAdapter(),
            },
          });
        })
        .fail(function() {
          console.info('Auto-complete for tag failed! Using default...');
          $self.tagsinput();
        });
    } else {
      // Normal tags input
      $self.tagsinput();
    }

    // When tagsinput loses focus, add the tag,
    // do not rely solely on comma or selection from suggestions
    $('.bootstrap-tagsinput input').on('blur', function(ev) {
      if ($(ev.currentTarget).val() !== '') {
        $self.tagsinput('add', $(ev.currentTarget).val());
        $(ev.currentTarget).val('');
      }
    });
  });

  // Initialize tag with values function from xibo-forms.js
  // this needs to be initialised only once, otherwise some
  // functions in it will be executed multiple times.
  if ($(scope + ' .tags-with-value').length > 0) {
    forms.tagsWithValues($(scope).find('form').attr('id'));
  }

  $(scope + ' .XiboCommand').each(function(_idx, el) {
    // TODO: Move to forms.js eventually
    // Get main container
    const $mainContainer = $(el);

    // Get input and its value
    const $input = $mainContainer.find('input');

    // Hide main input
    $input.hide();

    const commandTypes = {
      freetext: translations.freeTextCommand,
      tpv_led: 'Philips Android',
      rs232: 'RS232',
      intent: 'Android Intent',
      http: 'HTTP',
    };

    // Load templates
    const loadTemplates = function(type) {
      const initVal = $input.val();
      const parsedVal = parseCommandValue($input.val());
      const $targetContainer = $mainContainer.find('.command-inputs');

      // Create template for the inputs
      const inputTemplate =
        templates.forms.commandInput[type];
      $targetContainer.html(
        inputTemplate({
          ...{
            value: parsedVal.value,
            initVal: initVal,
            unique: new Date().valueOf(),
          },
          ...{
            trans: translations.commandInput,
          },
        }),
      );

      // Extra templates for Android intent
      if (type == 'intent') {
        const inputExtraTemplate =
          templates.forms.commandInput['intent-extra'];
        if (parsedVal.value.extras != undefined) {
          parsedVal.value.extras.forEach(function(el) {
            $targetContainer
              .find('.intent-extra-container')
              .append(inputExtraTemplate({
                ...el,
                ...{
                  trans: translations.commandInput,
                },
              }));
          });
        }

        // Add extra element
        $targetContainer.find('.intent-add-extra')
          .on('click', function() {
            $targetContainer.find('.intent-extra-container')
              .append(inputExtraTemplate({
                trans: translations.commandInput,
              }));
            updateValue(type);
          });

        // Remove extra element
        $targetContainer
          .off('click', '.intent-remove-extra')
          .on('click', '.intent-remove-extra', function(ev) {
            $(ev.currentTarget).parents('.intent-extra-element')
              .remove();
            updateValue(type);
          });
      }

      // Header and body templates for HTTP intent
      if (type == 'http') {
        const inputKeyValueElementTemplate =
          templates.forms.commandInput['http-key-value'];
        const sectionClasses = [
          '.query-builder-container',
          '.http-headers-container',
          '.http-data-container',
        ];
        const sectionValues = [
          parsedVal.value != undefined && parsedVal.value.query != undefined ?
            parsedVal.value.query :
            null,
          parsedVal.value != undefined &&
            parsedVal.value.requestOptions != undefined &&
            parsedVal.value.requestOptions.headers != undefined ?
            parsedVal.value.requestOptions.headers :
            null,
          parsedVal.value != undefined &&
            parsedVal.value.requestOptions != undefined &&
            parsedVal.value.requestOptions.body != undefined ?
            parsedVal.value.requestOptions.body :
            null,
        ];

        // Generate key value pairs in a container
        const populateKeyValues = function($container, values) {
          // Empty container
          $container.find('.http-key-value-container').empty();

          // Populate with the new values
          for (let i = 0; i < Object.keys(values).length; i++) {
            $container.find('.http-key-value-container').append(
              inputKeyValueElementTemplate({
                ...{
                  key: Object.keys(values)[i],
                  value: Object.values(values)[i],
                },
                ...{
                  trans: translations.commandInput,
                },
              }),
            );
          }
        };

        // Update all the key-value/raw fields
        const updateKeyValueRawFields = function(forceUpdateTextArea) {
          // eslint-disable-next-line no-invalid-this
          const $target = $(this);
          // Update text area even if the checkbox for raw is off
          forceUpdateTextArea =
            forceUpdateTextArea != undefined ? forceUpdateTextArea : false;

          const $parentContainer = $target.parents('.request-section');

          // Get value from JSON string
          const parseJsonFromString = function(valueToParse) {
            let parsedValue;

            try {
              parsedValue = JSON.parse(valueToParse);
            } catch (error) {
              console.warn(error);
              console.warn('Value not a JSON!');
            }

            return parsedValue;
          };

          // Create a JSON string from a key-value pair
          const createJSONStringFromKeyValue = function($container) {
            const elementsObject = {};

            $container
              .find('.http-key-value-container .http-key-value-element')
              .each(function(_idx, el) {
                const $el = $(el);
                const elKey = $el.find('.http-key').val();
                const elValue = $el.find('.http-value').val();

                // Add to final command if all fields are correct
                if (![elKey, elValue].includes('')) {
                  elementsObject[elKey] = elValue;
                  $el.removeClass('invalid');
                } else {
                  $el.addClass('invalid');
                }
              });

            return JSON.stringify(elementsObject);
          };

          //
          const decodeQueryString = function(valueToParse) {
            let parsedValue;

            try {
              parsedValue =
                '{"' +
                decodeURI(valueToParse.replace(/&/g, '","')
                  .replace(/=/g, '":"')) +
                '"}';
            } catch (error) {
              console.warn(error);
              console.warn('Decode URI failed!');
            }

            return parsedValue;
          };

          // Create query string from a set of key values
          const createQueryStringFromKeyValues = function($container) {
            const elementsObject = {};
            $container
              .find('.http-key-value-container .http-key-value-element')
              .each(function(_idx, el) {
                const $el = $(el);
                const elKey = $el.find('.http-key').val();
                const elValue = $el.find('.http-value').val();

                // Add to final command if all fields are correct
                if (![elKey, elValue].includes('')) {
                  elementsObject[elKey] = elValue;
                }
              });

            // Build body param string
            const paramsString = Object.keys(elementsObject)
              .map((key) => key + '=' + elementsObject[key])
              .join('&');

            return paramsString;
          };

          // Get current content type
          const contentType = $targetContainer.find('.http-contenttype').val();

          if ($target.is(':checked') || forceUpdateTextArea) {
            // Create string from key value elements
            if (
              !$parentContainer.find('textarea').hasClass('http-data') ||
              contentType == 'application/json'
            ) {
              $parentContainer
                .find('textarea')
                .val(createJSONStringFromKeyValue($parentContainer));
            } else if (contentType == 'application/x-www-form-urlencoded') {
              $parentContainer
                .find('textarea')
                .val(createQueryStringFromKeyValues($parentContainer));
            }
          } else {
            // Update key value elements based on the textarea value
            let builtValue;

            if (
              !$parentContainer.find('textarea').hasClass('http-data') ||
              contentType == 'application/json'
            ) {
              // Parse JSON from textarea
              builtValue = parseJsonFromString(
                $parentContainer.find('textarea').val(),
              );
            } else if (contentType == 'application/x-www-form-urlencoded') {
              builtValue = parseJsonFromString(
                decodeQueryString($parentContainer.find('textarea').val()),
              );
            }

            // if value exists, populate key-value elements
            if (builtValue) {
              populateKeyValues($parentContainer, builtValue);
            }
          }
        };

        // Create key pair sections
        for (let i = 0; i < sectionValues.length; i++) {
          const sectionValue = sectionValues[i];
          const $sectionContainer = $targetContainer.find(sectionClasses[i]);

          if (sectionValue != null) {
            populateKeyValues($sectionContainer, sectionValue);
          }

          // Handle Add extra element
          $sectionContainer.find('.http-key-value-add')
            .on('click', function(ev) {
              $(ev.currentTarget)
                .parent()
                .find('.http-key-value-container')
                .append(inputKeyValueElementTemplate({}));
              updateValue(type);
            });

          // Handle Remove extra element
          $sectionContainer
            .off('click', '.http-key-value-remove')
            .on('click', '.http-key-value-remove', function(ev) {
              $(ev.currentTarget).parents('.http-key-value-element').remove();
              updateValue(type);
            });

          // Handle Raw checkbox input
          $sectionContainer
            .parent()
            .find('.form-check input[type="checkbox"]')
            .off('change')
            .on('change', function(ev) {
              const $target = $(ev.currentTarget);
              if (
                $target.hasClass('show-raw-headers') ||
                $target.hasClass('show-raw-data')
              ) {
                updateKeyValueRawFields.bind(ev.currentTarget)();
              } else {
                updateValue(type);
              }

              // Toggle fields visibility
              const $parentContainer = $target.parents('.request-section');
              $parentContainer
                .find($target.data('toggleElement'))
                .toggleClass(
                  $target.data('toggleClass'), $target.is(':checked'));
              $parentContainer
                .find($target.data('toggleElementReverse'))
                .toggleClass(
                  $target.data('toggleClass'), !$target.is(':checked'));
            });

          // Value change makes fields to be updated
          $sectionContainer
            .parent()
            .off(
              'change',
              '.http-key-value-container .http-key-value-element input',
            )
            .on(
              'change',
              '.http-key-value-container .http-key-value-element input',
              function(ev) {
                updateKeyValueRawFields.bind(ev.currentTarget)(true);
              },
            );

          // Call update when loading each section
          updateKeyValueRawFields.bind($sectionContainer)(true);
        }

        // Handle content type behaviour
        $targetContainer
          .find('.http-contenttype')
          .off('change')
          .on('change', function(ev) {
            const isPlainText = $(ev.currentTarget).val() == 'text/plain';

            $targetContainer
              .find('.show-raw-data')
              .parent()
              .toggleClass('d-none', isPlainText);
            $targetContainer
              .find('.show-raw-data')
              .prop('checked', isPlainText)
              .trigger('change');

            // Update data raw field based on the contenttype
            if (!isPlainText) {
              updateKeyValueRawFields.bind($('.http-data-container'))(true);
            }
          });
      }

      // Bind input changes to the old input field
      $targetContainer
        .off('change', 'input:not(.ignore-change), select, textarea')
        .on(
          'change',
          'input:not(.ignore-change), select, textarea',
          function() {
            updateValue(type);
          });

      updateValue(type);
    };

    // Parse command value and return object
    const parseCommandValue = function(value) {
      const valueObj = {};

      if (value == '' || value == undefined) {
        valueObj.type = 'freetext';
        valueObj.value = '';
      } else {
        const splitValue = value.split('|');

        if (splitValue.length == 1) {
          // free text
          valueObj.type = 'freetext';
          valueObj.value = value;
        } else {
          valueObj.type = splitValue[0];

          switch (valueObj.type) {
            case 'intent':
              // intent|<type|activity,service,broadcast>|<activity>|[<extras>]
              valueObj.value = {
                // <type|activity,service,broadcast>
                type: splitValue[1],
                name: splitValue[2],
                // [<extras>]
                // {
                //  "name": "<extra name>",
                //  "type": "<type|string,int,bool,intArray>",
                //  "value": <the value of the above type>
                // }
                extras: splitValue.length > 3 ? JSON.parse(splitValue[3]) : [],
              };
              break;
            case 'rs232':
              // rs232|<connection string>|<command>
              const connectionStringRaw = splitValue[1].split(',');
              const connectionString = {
                deviceName: connectionStringRaw[0],
                baudRate: connectionStringRaw[1],
                dataBits: connectionStringRaw[2],
                parity: connectionStringRaw[3],
                stopBits: connectionStringRaw[4],
                handshake: connectionStringRaw[5],
                hexSupport: connectionStringRaw[6],
              };

              valueObj.value = {
                // eslint-disable-next-line max-len
                // <COM#>,<Baud Rate>,<Data Bits>,<Parity|None,Odd,Even,Mark,Space>,<StopBits|None,One,Two,OnePointFive>,<Handshake|None,XOnXOff,RequestToSend,RequestToSendXOnXOff>,<HexSupport|0,1,default 0>
                // eslint-disable-next-line max-len
                // <DeviceName>,<Baud Rate>,<Data Bits>,<Parity>,<StopBits>,<FlowControl>
                cs: connectionString,
                command: splitValue[2],
              };
              break;
            case 'tpv_led':
              valueObj.type = 'tpv_led';
              valueObj.value = splitValue[1];

              break;
            case 'http':
              let requestOptions = {};
              const contentType = splitValue[2];

              // try to parse JSON
              if (splitValue[3] != undefined) {
                try {
                  requestOptions = JSON.parse(splitValue[3]);
                } catch (error) {
                  console.log(error);
                  console.warn('Skip JSON parse!');
                }
              }

              // parse headers
              if (requestOptions.headers != undefined) {
                try {
                  requestOptions.headers =
                    SON.parse(requestOptions.headers);
                } catch (error) {
                  console.warn(error);
                  console.warn('Skip headers JSON parse!');
                }
              }

              // parse body
              if (requestOptions.body != undefined) {
                try {
                  if (contentType) {
                    if (contentType == 'application/json') {
                      requestOptions.body = JSON.parse(requestOptions.body);
                    } else if (
                      contentType == 'application/x-www-form-urlencoded'
                    ) {
                      const bodyElements =
                        decodeURI(requestOptions.body).split('&');
                      const newParsedElements = {};

                      bodyElements.forEach((element) => {
                        const elementSplit = element.split('=');
                        if (elementSplit.length = 2) {
                          newParsedElements[elementSplit[0]] = elementSplit[1];
                        }
                      });

                      requestOptions.body = newParsedElements;
                    }
                  }
                } catch (error) {
                  console.warn(error);
                  console.warn('Skip body parse!');
                }
              }

              // http|url|<requestOptions>
              valueObj.type = 'http';
              valueObj.value = {
                // <url>
                url: splitValue[1],
                // eslint-disable-next-line max-len
                // <contentType|application/x-www-form-urlencoded|application/json|text/plain>
                contenttype: splitValue[2],
                // eslint-disable-next-line max-len
                // <requestOptions|{<method|GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS,TRACE,CONNECT>,<headers|{[key:value]}>,<body|based on content type>}>
                requestOptions: requestOptions,
              };
              break;
            default:
              valueObj.type = 'freetext';
              valueObj.value = value;
              break;
          }
        }
      }

      return valueObj;
    };

    // Build command value
    const updateValue = function(type) {
      let builtString = '';
      let invalidValue = false;
      const $container = $mainContainer.find('.command-inputs');

      switch (type) {
        case 'tpv_led':
          builtString = 'tpv_led|' + $container.find('.tpv-led-command').val();
          break;
        case 'http':
          // URL
          let url = $container.find('.http-url').val();
          const paramsObj = {};

          // Validate URL
          if (url == '') {
            invalidValue = true;
            $container.find('.http-url').addClass('invalid');
          } else {
            $container.find('.http-url').removeClass('invalid');
          }

          // Query builder
          if ($container.find('.show-query-builder').is(':checked')) {
            // Check if url has params
            if (url.split('?').length == 2) {
              const urlParams = url.split('?')[1];
              let params = [];

              try {
                params = decodeURI(urlParams).split('&');
              } catch (e) {
                console.warn('malformed URI:' + e);
              }

              // Update URL
              url = url.split('?')[0];

              // Add params to query builder
              for (let i = 0; i < params.length; i++) {
                const param = params[i].split('=');
                if (param.length != 2) {
                  continue;
                }

                paramsObj[param[0]] = param[1];
              }
            }

            // Grab all the key-value pairs
            $container
              .find(
                '.query-builder-container ' +
                '.http-key-value-container ' +
                '.http-key-value-element',
              )
              .each(function(ev) {
                const $el = $(ev.currentTarget);
                $el.removeClass('invalid');
                let paramName = $el.find('.http-key').val();
                let paramValue = $el.find('.http-value').val();

                // encode uri
                try {
                  paramName = encodeURI(paramName);
                  paramValue = encodeURI(paramValue);
                } catch (error) {
                  console.warn(error);
                  console.warn('malformed URI:' + e);
                  paramName = '';
                  paramValue = '';
                }

                // Add to final command if all fields are correct
                if (![paramName, paramValue].includes('')) {
                  paramsObj[paramName] = paramValue;
                  $el.removeClass('invalid');
                } else {
                  invalidValue = true;
                  $el.addClass('invalid');
                }
              });

            // Build param string
            const paramsString = Object.keys(paramsObj)
              .map((key) => key + '=' + paramsObj[key])
              .join('&');

            // Append to url
            url += paramsString != '' ? '?' + encodeURI(paramsString) : '';
          }

          // Build request options
          let requestOptions = {};

          // Method
          requestOptions.method = $container.find('.http-method').val();

          // contenttype
          const contentType = $container.find('.http-contenttype').val();

          // Custom Headers
          const headers = $container.find('.http-headers').val();

          // validate headers
          $container.find('.http-headers').parent().removeClass('invalid');
          try {
            JSON.parse(headers);
            requestOptions.headers = headers;
          } catch (e) {
            console.warn('Invalid headers: ' + e);
            invalidValue = true;
            $container.find('.http-headers').parent().addClass('invalid');
          }

          // body data
          const bodyData = $container.find('.http-data').val();

          // validate body data
          $container.find('.http-data').parent().removeClass('invalid');
          try {
            if (contentType == 'application/json') {
              JSON.parse(bodyData);
            } else if (contentType == 'application/x-www-form-urlencoded') {
              decodeURI(bodyData);
            }

            requestOptions.body = bodyData;
          } catch (e) {
            console.warn('Invalid body: ' + e);
            invalidValue = true;
            $container.find('.http-data').parent().addClass('invalid');
          }

          // Create final JSON string
          if (typeof requestOptions == 'object') {
            requestOptions = JSON.stringify(requestOptions);
          }

          // Build final string
          builtString = 'http|';
          builtString += url + '|';
          builtString += contentType + '|';
          builtString += requestOptions;

          break;
        case 'rs232':
          // Get values
          const deviceNameVal = $container.find('.rs232-device-name').val();
          const baudRateVal = $container.find('.rs232-baud-rate').val();
          const dataBitsVal = $container.find('.rs232-data-bits').val();
          const parityVal = $container.find('.rs232-parity').val();
          const stopBitsVal = $container.find('.rs232-stop-bits').val();
          const handshakeVal = $container.find('.rs232-handshake').val();
          const hexSupportVal = $container.find('.rs232-hex-support').val();
          const commandVal = $container.find('.rs232-command').val();

          $container
            .find('.rs232-device-name')
            .toggleClass('invalid', deviceNameVal == '');
          $container.find('.rs232-baud-rate')
            .toggleClass('invalid', baudRateVal == '');
          $container.find('.rs232-data-bits')
            .toggleClass('invalid', dataBitsVal == '');

          if ([deviceNameVal, baudRateVal, dataBitsVal].includes('')) {
            invalidValue = true;
          }

          builtString = 'rs232|';
          builtString += deviceNameVal != '' ? deviceNameVal + ',' : '';
          builtString += baudRateVal != '' ? baudRateVal + ',' : '';
          builtString += dataBitsVal != '' ? dataBitsVal + ',' : '';
          builtString += parityVal != '' ? parityVal + ',' : '';
          builtString += stopBitsVal != '' ? stopBitsVal + ',' : '';
          builtString += handshakeVal != '' ? handshakeVal + ',' : '';
          builtString += hexSupportVal;
          builtString += '|' + commandVal;
          break;
        case 'intent':
          builtString =
            'intent|' +
            $container.find('.intent-type').val() +
            '|' +
            $container.find('.intent-name').val();

          const nameVal = $container.find('.intent-name').val();

          if (nameVal == '') {
            $container.find('.intent-name').addClass('invalid');
            invalidValue = true;
          } else {
            $container.find('.intent-name').removeClass('invalid');
          }

          // Extra values array
          const extraValues = [];

          // Get values from input fields
          $container.find('.intent-extra-element').each(function(ev) {
            const $el = $(ev.currentTarget);
            $el.removeClass('invalid');
            const extraName = $el.find('.extra-name').val();
            const extraType = $el.find('.extra-type').val();
            let extraValue = $el.find('.extra-value').val();

            // Validate values
            if (extraType == 'intArray') {
              // Transform the value into an array
              extraValue = extraValue
                .replace(' ', '')
                .split(',')
                .map(function(x) {
                  return x != '' ? Number(x) : '';
                });

              // Check if all the array elements are numbers ( and non empty )
              for (let index = 0; index < extraValue.length; index++) {
                const element = extraValue[index];

                if (isNaN(element) || element == '') {
                  extraValue = '';
                  break;
                }
              }
            } else if (extraType == 'int' && extraValue != '') {
              extraValue = isNaN(Number(extraValue)) ? '' : Number(extraValue);
            } else if (extraType == 'bool' && extraValue != '') {
              extraValue = extraValue == 'true';
            }

            // Add to final command if all fields are correct
            if (![extraName, extraType, extraValue].includes('')) {
              extraValues.push({
                name: extraName,
                type: extraType,
                value: extraValue,
              });
            } else {
              invalidValue = true;
              $el.addClass('invalid');
            }
          });

          // Append extra values array in JSON format
          if (extraValues.length > 0) {
            builtString += '|' + JSON.stringify(extraValues);
          }

          break;
        default:
          builtString = $container.find('.free-text').val();
          break;
      }

      // Update command preview
      if (invalidValue) {
        $input.val('');
        $mainContainer
          .find('.command-preview code')
          .text($mainContainer.find('.command-preview').data('invalidMessage'));
        $mainContainer.find('.command-preview').addClass('invalid');
      } else {
        $input.val(builtString);
        $mainContainer.find('.command-preview code').text(builtString);
        $mainContainer.find('.command-preview').removeClass('invalid');
      }
    };

    // Get init command type
    const initType = parseCommandValue($input.val()).type;

    // Create basic type element
    const optionsTemplate = templates.forms.commandInput['main'];
    $input.before(
      optionsTemplate({
        ...{
          types: commandTypes,
          type: initType,
          unique: new Date().valueOf(),
        },
        ...{
          trans: translations.commandInput,
        },
      }),
    );

    // Set template on first run
    loadTemplates(initType);

    // Set template on command type change
    $(el).find('.command-type')
      .on('change', function(ev) {
        loadTemplates($(ev.currentTarget).val());
      });

    // Link checkbox to input preview
    $(el).find('.show-command-preview')
      .on('change', function(ev) {
        $mainContainer.find('.command-preview')
          .toggle($(ev.currentTarget).is(':checked'));
      });

    // Disable main input
    $input.attr('readonly', 'readonly');
  });

  // Initialize color picker
  $(scope + ' .XiboColorPicker').each(function(_idx, el) {
    // Create color picker
    createColorPicker(el);
  });

  // Handle bootstrap error when a dropdown content renders offscreen
  $(scope + ' .XiboData').on(
    'shown.bs.dropdown',
    '.dropdown-menu-container',
    function(ev) {
      const $dropdownMenuShown =
        $(ev.currentTarget).find('.dropdown-menu.show');
      setTimeout(function() {
        if ($dropdownMenuShown.offset().top < 0) {
          $dropdownMenuShown.offset({
            top: 0,
            left: $dropdownMenuShown.offset().left,
          });
        }
      }, 200);
    });

  $(scope + ' #fullScreenCampaignId').each(function(_idx, el) {
    const $form = $(el).closest('form');
    const eventTypeId = parseInt($form.find('#eventTypeId').val());
    const mediaId = $form.find('#fullScreen-mediaId').val();
    const playlistId = $form.find('#fullScreen-playlistId').val();
    let dataObj = {};
    let url;

    if (mediaId !== null && mediaId !== undefined && mediaId !== '') {
      dataObj = {
        mediaId: mediaId,
      };
      url = $form.data().libraryGetUrl;
    } else if (
      playlistId != null &&
      playlistId != undefined &&
      playlistId !== ''
    ) {
      dataObj = {
        playlistId: playlistId,
      };
      url = $form.data().playlistGetUrl;
    }

    // get selected media or playlist details
    if (url != null && [7, 8].includes(eventTypeId)) {
      $.ajax({
        type: 'GET',
        url: url,
        cache: false,
        dataType: 'json',
        data: dataObj,
      })
        .then(
          (response) => {
            if (!response.success) {
              SystemMessageInline(
                (response.message === '') ?
                  translations.failure : response.message,
                $form.closest('.modal'),
              );
            }

            // at the moment we only add media
            // or playlist name to readonly input
            // this might be extended in the future.
            if (eventTypeId == 7) {
              $form.find('#fullScreen-media').val(response.data[0].name);
            } else if (eventTypeId == 8) {
              $form.find('#fullScreen-playlist').val(response.data[0].name);
            }

            if (response.data[0]?.fullScreenCampaignId) {
              $form.find('#fullScreenCampaignId')
                .val(response.data[0].fullScreenCampaignId)
                .trigger('change');
            }

            if (!response.data[0]?.hasFullScreenLayout) {
              if (eventTypeId == 7) {
                $form.find('#fullScreenControl_media').trigger('autoOpen');
              } else if (eventTypeId == 8) {
                $form.find('#fullScreenControl_playlist').trigger('autoOpen');
              }
            }
          }, (xhr) => {
            SystemMessage(xhr.responseText, false);
          });
    }
  });

  $(scope + ' .full-screen-layout-form').on('click autoOpen', function(ev) {
    const $target = $(ev.currentTarget);

    if ($('#full-screen-schedule-modal').length != 0) {
      $('#full-screen-schedule-modal').remove();
    }

    const $mainModal = $target.parents(scope);
    const eventTypeId = $target.closest('form').find('#eventTypeId').val();
    const mediaId = $target.closest('form').find('#fullScreen-mediaId').val();
    const playlistId =
      $target.closest('form').find('#fullScreen-playlistId').val();
    const readOnlySelect = $target.data('readonly');

    if ($('#full-screen-schedule-modal').length === 0) {
      const config = {
        type: eventTypeId == 7 ? 'Media' : 'Playlist',
        eventTypeId: eventTypeId,
        readonlySelect: readOnlySelect,
      };

      $('body').append(templates.schedule.fullscreenSchedule(
        {
          ...config,
          ...{
            trans: translations.schedule.fullscreen,
          },
          ...{
            fullscreenSchedule: fullscreenSchedule,
          },
        }));
      const $modal = $('#full-screen-schedule-modal');

      // If form was opened automatically
      // close background modal if we close this one
      if (ev.type === 'autoOpen') {
        $modal.find('button.close').on('click', function() {
          $mainModal.modal('hide');
        });
      }

      $modal
        .on('show.bs.modal', function() {
          $('.no-full-screen-layout').addClass('d-none');
        })
        .on('shown.bs.modal', function() {
          const $form = $modal.find('form');
          // set initial values if we have any
          $form.find('.pagedSelect select.form-control')
            .each(function(_idx, el) {
              const $target2 = $(el);
              if (
                $target2.attr('name') == 'mediaId' && mediaId != null
              ) {
                $target2.data('initialValue', mediaId);
              } else if (
                $target2.attr('name') == 'playlistId' && playlistId != null
              ) {
                $target2.data('initialValue', playlistId);
              }

              // init select2
              makePagedSelect($target2, '#' + $form.attr('id'));
            });

          // init color picker
          $form.find('.colorpicker-input:not(.colorpicker-element)')
            .each(function(_idx, el) {
              $(el).colorpicker({
                container: $(el).parent(),
              });
            });

          // change input visibility depending on
          // what we selected for media/playlist
          $('#mediaId, #playlistId', $form)
            .on('select2:select', function(event) {
              let hasFullScreenLayout = false;
              if (event.params.data.data !== undefined) {
                hasFullScreenLayout =
                  event.params.data.data[0].hasFullScreenLayout;
              } else if (event.params.data.hasFullScreenLayout !== undefined) {
                hasFullScreenLayout = event.params.data.hasFullScreenLayout;
              }

              if (hasFullScreenLayout) {
                $('.no-full-screen-layout').addClass('d-none');
              } else {
                if ($(event.currentTarget).attr('name') === 'mediaId') {
                  $('.no-full-screen-layout').removeClass('d-none');
                } else {
                  $('.no-full-screen-layout.media-playlist-control')
                    .removeClass('d-none');
                }
              }
            });

          if (readOnlySelect) {
            $form.find('#mediaId, #playlistId').prop('disabled', true);
          }

          // resolution helpText changes depending
          // if we are on playlist or media event
          const $resolutionControl = $('.resolution-control');
          const $resolutionSelect = $form.find('#resolutionId');
          if (eventTypeId == 7) {
            $resolutionControl
              .children('div')
              .children('small.form-text.text-muted')
              .text($resolutionSelect.data('transMediaHelpText'));
          } else if (eventTypeId == 8) {
            $resolutionControl
              .children('div')
              .children('small.form-text.text-muted')
              .text($resolutionSelect.data('transPlaylistHelpText'));
          }

          // confirmation button was pressed
          // create or fetch the Layout for selected media or playllist
          // this will populate fullScreenCampaignId hidden input
          // and close this modal once everything is done
          $('#btnFullScreenLayoutConfirm').on('click', function(e) {
            e.preventDefault();
            fullscreenBeforeSubmit($form);
          });

          // Initialize fields
          XiboInitialise('#scheduleFullScreenForm');
        })
        .on('hidden.bs.modal', function(ev) {
          // Fix for 2nd/overlay modal
          $('.modal:visible').length && $(document.body).addClass('modal-open');

          $(ev.currentTarget).data('bs.modal', null);
        });

      // Open modal programmatically
      $modal.modal({
        backdrop: 'static',
        keyboard: false,
        show: true,
      });
    }
  });

  // Initalise remaining form fields
  if (forms && typeof forms.initFields === 'function') {
    // Initialise fields, with scope of body if we don't have a specific scope
    forms.initFields(
      (scope === ' ') ? 'body' : scope,
      null,
      (options && options.targetId) ? options.targetId : null,
      (options && options.readOnlyMode) ? options.readOnlyMode : false,
    );
  }
};

/**
 * Renders the formid provided
 * @param {Object} sourceObj
 * @param {Object} data
 */
window.XiboFormRender = function(sourceObj, data = null) {
  let formUrl = '';
  if (typeof sourceObj === 'string' || sourceObj instanceof String) {
    formUrl = sourceObj;
  } else {
    formUrl = sourceObj.attr('href');
    // Remove the link from the source object if exists
    sourceObj.removeAttr('href');
  }

  // To fix the error generated by the double click on button
  if (formUrl == undefined) {
    return false;
  }

  lastForm = formUrl;

  // Call with AJAX
  $.ajax({
    type: 'get',
    url: formUrl,
    cache: false,
    dataType: 'json',
    data: data,
    success: function(response) {
      // Restore the link to the source object if exists
      if (typeof sourceObj === 'object' || sourceObj instanceof Object) {
        sourceObj.attr('href', lastForm);
      }

      // Was the Call successful
      if (response.success) {
        if (!(typeof sourceObj === 'string' || sourceObj instanceof String)) {
          const commitUrl = sourceObj.data().commitUrl;

          // Handle auto-submit
          if (response.autoSubmit && commitUrl !== undefined) {
            // grab the auto submit URL and submit it immediately
            $.ajax({
              type: sourceObj.data().commitMethod || 'POST',
              url: commitUrl,
              cache: false,
              dataType: 'json',
              success: function(autoSubmitResponse) {
                if (autoSubmitResponse.success) {
                  // Success - what do we do now?
                  if (autoSubmitResponse.message !== '') {
                    SystemMessage(autoSubmitResponse.message, true);
                  }
                  XiboRefreshAllGrids();
                } else if (autoSubmitResponse.login) {
                  // We were logged out
                  LoginBox(autoSubmitResponse.message);
                } else {
                  SystemMessageInline(autoSubmitResponse.message);
                }
              },
              error: function(xhr) {
                SystemMessageInline(xhr.responseText);
              },
            });
            return false;
          }
        }

        // Set the dialog HTML to be the response HTML
        let dialogTitle = '';

        // Is there a title for the dialog?
        if (response.dialogTitle != undefined && response.dialogTitle != '') {
          // Set the dialog title
          dialogTitle = response.dialogTitle;
        }

        const id = new Date().getTime();

        // Create the dialog with our parameters
        let size = 'large';
        if (sourceObj && typeof sourceObj === 'object') {
          size = sourceObj.data().modalSize || 'large';
        }

        // Currently only support one of these at once.
        // We have to move this here before calling bootbox.dialog
        // to avoid multiple modal being opened
        bootbox.hideAll();

        const dialog = bootbox.dialog({
          message: response.html,
          title: dialogTitle,
          animate: false,
          size: size,
        }).attr('id', id);

        // Store the extra
        dialog.data('extra', response.extra);

        // Buttons?
        if (response.buttons !== '') {
          // Append a footer to the dialog
          const footer = $('<div>').addClass('modal-footer');
          dialog.find('.modal-content').append(footer);

          let i = 0;
          const count = Object.keys(response.buttons).length;
          $.each(
            response.buttons,
            function(index, value) {
              i++;
              const extrabutton =
                $('<button id="dialog_btn_' + i + '" class="btn">').html(index);

              if (i === count) {
                extrabutton.addClass('btn-primary save-button');
              } else {
                extrabutton.addClass('btn-white');
              }

              extrabutton.on('click', function(ev) {
                ev.preventDefault();

                const $button = $(ev.currentTarget);

                if ($button.hasClass('save-button')) {
                  if ($button.hasClass('disabled')) {
                    return false;
                  } else {
                    $button.append(
                      ' <span class="saving fa fa-cog fa-spin"></span>',
                    );

                    // Disable the button
                    // https://github.com/xibosignage/xibo/issues/1467
                    $button.addClass('disabled');
                  }
                }

                eval(value);

                return false;
              });

              footer.append(extrabutton);
            });

          // Check to see if we ought to render out a checkbox for autosubmit
          if (!(typeof sourceObj === 'string' || sourceObj instanceof String)) {
            if (sourceObj.data().autoSubmit) {
              if (autoSubmitTemplate === null) {
                autoSubmitTemplate = templates['auto-submit-field'];
              }

              footer.prepend(autoSubmitTemplate({
                trans: translations.autoSubmitField,
              }));
            }
          }
        }

        // Focus in the first input
        $('input[type=text]', dialog).not('.dateControl').eq(0).focus();

        $('input[type=text]', dialog).each(function(index, el) {
          formRenderDetectSpacingIssues(el);

          $(el).on('keyup', _.debounce(function() {
            formRenderDetectSpacingIssues(el);
          }, 500));
        });

        // Set up dependencies between controls
        if (response.fieldActions != '') {
          $.each(response.fieldActions, function(index, fieldAction) {
            // console.log("Processing field action for " + fieldAction.field);

            if (fieldAction.trigger == 'init') {
              // Process the actions straight away.
              const fieldVal = $('#' + fieldAction.field).val();

              // console.log("Init action with value " + fieldVal);
              let valueMatch = false;
              if (fieldAction.operation == 'not') {
                valueMatch = (fieldVal != fieldAction.value);
              } else if (fieldAction.operation == 'is:checked') {
                valueMatch =
                  (fieldAction.value ==
                    $('#' + fieldAction.field).is(':checked'));
              } else {
                valueMatch = (fieldVal == fieldAction.value);
              }

              if (valueMatch) {
                // console.log("Value match");

                $.each(fieldAction.actions, function(index, action) {
                  // Action the field
                  const field = $(index);

                  if (!field.data('initActioned')) {
                    field.css(action).data('initActioned', true);
                  }
                });
              }
            } else {
              $('#' + fieldAction.field).on(fieldAction.trigger, function(ev) {
                // Process the actions straight away.
                const fieldVal = $(ev.currentState).val();

                // console.log("Init action with value " + fieldVal);
                let valueMatch = false;
                if (fieldAction.operation == 'not') {
                  valueMatch = (fieldVal != fieldAction.value);
                } else if (fieldAction.operation == 'is:checked') {
                  valueMatch = (fieldAction.value ==
                    $('#' + fieldAction.field).is(':checked'));
                } else {
                  valueMatch = (fieldVal == fieldAction.value);
                }

                if (valueMatch) {
                  // console.log("Value match");

                  $.each(fieldAction.actions, function(index, action) {
                    // Action the field
                    $(index).css(action);
                  });
                }
              });
            }
          });
        }

        // Check to see if there are any tab actions
        $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function(e) {
          if ($(e.target).data().enlarge === 1) {
            $(e.target).closest('.modal').addClass('modal-big');
          } else {
            $(e.target).closest('.modal').removeClass('modal-big');
          }
        });

        // Check to see if the current tab has the enlarge action
        $('a[data-toggle="tab"]', dialog).each(function(_idx, el) {
          if (
            $(el).data().enlarge === 1 &&
            $(el).closest('li').hasClass('active')
          ) {
            $(el).closest('.modal').addClass('modal-big');
          }
        });

        // make bootstrap happy.
        if ($('#folder-tree-form-modal').length != 0) {
          $('#folder-tree-form-modal').remove();
        }

        // Call Xibo Init for this form
        XiboInitialise('#' + dialog.attr('id'));

        if (dialog.find('.XiboForm').attr('id') != undefined) {
          // if this is add form and we have some folderId
          // selected in grid view, put that as the working
          // folder id for this form
          // edit forms will get the current folderId
          // assigned to the edited object.
          if (
            $('#container-folder-tree')
              .jstree('get_selected', true)[0] !== undefined &&
            $(
              '#' + dialog.find('.XiboForm').attr('id') + ' #folderId',
            ).val() == '') {
            $(
              '#' + dialog.find('.XiboForm').attr('id') + ' #folderId',
            ).val(
              $('#container-folder-tree').jstree('get_selected', true)[0].id,
            );
          }

          initJsTreeAjax(
            '#container-folder-form-tree',
            dialog.find('.XiboForm').attr('id'),
            true,
            600,
          );
        }

        // Do we have to call any functions due to this success?
        if (response.callBack !== '' && response.callBack !== undefined) {
          eval(response.callBack)(dialog);
        }
      } else {
        // Login Form needed?
        if (response.login) {
          LoginBox(response.message);

          return false;
        } else {
          // Just an error we dont know about
          if (response.message == undefined) {
            SystemMessage(response);
          } else {
            SystemMessage(response.message);
          }
        }
      }

      return false;
    },
    error: function(response) {
      SystemMessage(response.responseText);
    },
  });

  // Dont then submit the link/button
  return false;
};

/**
 * Renders the form provided using the form own javascript
 * @param {Object} sourceObj
 */
window.XiboCustomFormRender = function(sourceObj) {
  let formUrl = '';

  formUrl = sourceObj.attr('href');

  // Remove the link from the source object if exists
  sourceObj.removeAttr('href');

  // To fix the error generated by the double click on button
  if (formUrl == undefined) {
    return false;
  }

  lastForm = formUrl;

  // Call with AJAX
  $.ajax({
    type: 'get',
    url: formUrl,
    cache: false,
    dataType: 'json',
    success: function(response) {
      // Restore the link to the source object if exists
      if (typeof sourceObj === 'object' || sourceObj instanceof Object) {
        sourceObj.attr('href', lastForm);
      }

      // Was the Call successful
      if (response.success) {
        // Create new id using the current time
        const id = new Date().getTime();

        const formToRender = {
          id: id,
          buttons: response.buttons,
          data: response.data,
          title: response.dialogTitle,
          message: response.html,
          extra: response.extra,
        };

        // Do we have to call any functions due to this success?
        if (response.callBack !== '' && response.callBack !== undefined) {
          window[response.callBack](formToRender);
        }
      } else {
        // Login Form needed?
        if (response.login) {
          LoginBox(response.message);

          return false;
        } else {
          // Just an error we dont know about
          if (response.message == undefined) {
            SystemMessage(response);
          } else {
            SystemMessage(response.message);
          }
        }
      }

      return false;
    },
    error: function(response) {
      SystemMessage(response.responseText);
    },
  });

  // Dont then submit the link/button
  return false;
};

/**
 * Makes a remote call to XIBO and passes
 * the result in the given onSuccess method
 * In case of an Error it shows an ErrorMessageBox
 * @param {String} fromUrl
 * @param {Object} data
 * @param {Function} onSuccess
 */
window.XiboRemoteRequest = function(formUrl, data, onSuccess) {
  $.ajax({
    type: 'post',
    url: formUrl,
    cache: false,
    dataType: 'json',
    data: data,
    success: onSuccess,
    error: function(response) {
      SystemMessage(response.responseText);
    },
  });
};

window.formRenderDetectSpacingIssues = function(element) {
  const $el = $(element);
  const value = $el.val();

  if (
    value !== '' &&
    (
      value.startsWith(' ') ||
      value.endsWith(' ') ||
      value.indexOf('  ') > -1
    )
  ) {
    // Add a little icon to the fields parent to inform of this issue
    console.debug('Field with strange spacing: ' + $el.attr('name'));

    const warning =
      $('<span></span>')
        .addClass('fa fa-exclamation-circle spacing-warning-icon')
        .attr('title', translations.spacesWarning);

    $el.parent().append(warning);
  } else {
    $el.parent().find('.spacing-warning-icon').remove();
  }
};

window.XiboMultiSelectFormRender = function(button) {
  // The button ID
  const buttonId = $(button).data().buttonId;

  // Get a list of buttons that match the ID
  const matches = [];
  let formOpenCallback = null;

  $('.' + buttonId).each(function(_idx, el) {
    const $button = $(el);

    if ($button.closest('tr').hasClass('selected')) {
      // This particular button should be included.
      matches.push($button);

      if (matches.length === 1) {
        // this is the first button which matches
        // so use the form open hook if one has been provided.
        formOpenCallback = $button.data().formCallback;

        // If form needs confirmation
        formConfirm = $button.data().formConfirm;
      }
    }
  });

  let message;

  if (matches.length > 0) {
    message = translations.multiselectMessage.replace('%1', '' + matches.length)
      .replace('%2', $(button).html());
  } else {
    message = translations.multiselectNoItemsMessage;
  }

  // Open a Dialog containing all the items we have identified.
  const dialog = bootbox.dialog({
    message: message,
    title: translations.multiselect,
    animate: false,
    size: 'large',
  });

  // Append a footer to the dialog
  const dialogContent = dialog.find('.modal-body');
  const footer = $('<div>').addClass('modal-footer');
  dialog.find('.modal-content').append(footer);

  // Call our open function if we have one
  if (formOpenCallback !== undefined && formOpenCallback !== null) {
    eval(formOpenCallback)(dialog);
  }

  // Add some buttons
  let extrabutton;

  if (matches.length > 0) {
    extrabutton = $('<button class="btn">').html(translations.save)
      .addClass('btn-primary save-button');

    // If form needs confirmation, disable save button
    if (formConfirm) {
      extrabutton.prop('disabled', true);
    }

    extrabutton.on('click', function(ev) {
      $(ev.currentTarget)
        .append(' <span class="saving fa fa-cog fa-spin"></span>');

      // Create a new queue.
      window.queue = $.jqmq({

        // Next item will be processed only when
        // queue.next() is called in callback.
        delay: -1,

        // Process queue items one-at-a-time.
        batch: 1,

        // For each queue item, execute this function
        // making an AJAX request. Only continue processing
        // the queue once the AJAX request's callback executes.
        callback: function(item) {
          let data = $(item).data();

          if (dialog.data().commitData !== undefined) {
            data = $.extend({}, data, dialog.data().commitData);
          }

          // Make an AJAX call
          $.ajax({
            type: data.commitMethod,
            url: data.commitUrl,
            cache: false,
            dataType: 'json',
            data: data,
            success: function(response, textStatus, error) {
              if (response.success) {
                dialogContent.append($('<div>')
                  .html(data.rowtitle + ': ' + translations.success));

                // Process the next item
                queue.next();
              } else {
                // Why did we fail?
                if (response.login) {
                  // We were logged out
                  LoginBox(response.message);
                } else {
                  dialogContent.append($('<div>')
                    .html(data.rowtitle + ': ' + translations.failure));

                  // Likely just an error that we want to report on
                  footer.find('.saving').remove();
                  SystemMessageInline(
                    response.message,
                    footer.closest('.modal'),
                  );
                }
              }
            },
            error: function(responseText) {
              SystemMessage(responseText, false);
            },
          });
        },
        // When the queue completes naturally, execute this function.
        complete: function() {
          // Remove the save button
          footer.find('.saving').parent().remove();

          // Refresh the grids
          // (this is a global refresh)
          XiboRefreshAllGrids();
        },
      });

      // Add our selected items to the queue
      $(matches).each(function(_idx, el) {
        queue.add(el);
      });

      queue.start();

      // Keep the modal window open!
      return false;
    });

    footer.append(extrabutton);
  }

  // Close button
  extrabutton = $('<button class="btn">').html(translations.close)
    .addClass('btn-white');
  extrabutton.on('click', function(ev) {
    $(ev.currentTarget)
      .append(' <span class="saving fa fa-cog fa-spin"></span>');

    // Do our thing
    dialog.modal('hide');

    // Bring other modals back to focus
    if ($('.modal').hasClass('in')) {
      $('body').addClass('modal-open');
    }

    // Keep the modal window open!
    return false;
  });

  footer.append(extrabutton);
};

/**
 * Xibo Ping
 * @param {String} url
 * @param {String} updateDiv
 */
window.XiboPing = function(url, updateDiv) {
  // Call with AJAX
  $.ajax({
    type: 'get',
    url: url,
    cache: false,
    dataType: 'json',
    success: function(response) {
      // Was the Call successful
      if (response.success) {
        if (updateDiv != undefined) {
          $(updateDiv).html(response.html);
        }

        if (response.clockUpdate) {
          XiboClockUpdate(response.html);
        }
      } else {
        // Login Form needed?
        if (response.login) {
          LoginBox(response.message);

          return false;
        }
      }

      return false;
    },
  });
};

/**
 * Updates the Clock with the latest time
 * @param {Object} time
 */
function XiboClockUpdate(time) {
  $('#XiboClock').html(time);

  return;
}

/**
 * Submits the Form
 * @param {Object} form
 * @param e
 * @param callBack
 */
window.XiboFormSubmit = function(form, e, callBack) {
  // Get the URL from the action part of the form)
  const $form = $(form);
  const url = $form.attr('action');

  // Update any text editor instances we have
  formHelpers.updateCKEditor();

  $.ajax({
    type: $form.attr('method'),
    url: url,
    cache: false,
    dataType: 'json',
    data: $form.serialize(),
    success: function(xhr, textStatus, error) {
      XiboSubmitResponse(xhr, form);

      if (callBack != null && callBack != undefined) {
        callBack(xhr, form);
      } else {
        const callBackFromForm = $form.data('submitCallBack');
        if (
          callBackFromForm &&
          typeof window[callBackFromForm] === 'function'
        ) {
          window[callBackFromForm](xhr, form);
        }
      }
    },
    error: function(xhr, textStatus, errorThrown) {
      SystemMessage(xhr.responseText, false);
    },
  });

  // Check to see if we need to call any auto-submit preferences
  // get the formid
  if (
    $form.closest('.modal-dialog').find('input[name=autoSubmit]').is(':checked')
  ) {
    updateUserPref([{
      option: 'autoSubmit.' + $form.attr('id'),
      value: true,
    }]);
  }

  return false;
};

/**
 * Handles the submit response from an AJAX call
 * @param {Object} response
 * @param
 */
window.XiboSubmitResponse = function(response, form) {
  // Remove the spinner
  $(form).closest('.modal-dialog').find('.saving').remove();

  // Check the apply flag
  const apply = $(form).data('apply');

  // Remove the apply flag
  $(form).data('apply', false);

  // Did we actually succeed
  if (response.success) {
    // Success - what do we do now?
    if (response.message != '') {
      SystemMessage(response.message, true);
    }

    // We might need to keep the form open
    if (apply == undefined || !apply) {
      bootbox.hideAll();
    } else {
      // If we have reset on apply
      if ($(form).data('applyCallback')) {
        eval($(form).data('applyCallback'))(form);
      }

      // Remove form errors
      $(form).closest('.modal-dialog').find('.form-error').remove();

      // Focus in the first input
      $('input[type=text]', form).eq(0).focus();
    }

    // Should we refresh the window or refresh the Grids?
    XiboRefreshAllGrids();

    if (!apply) {
      // Next form URL is provided
      if ($(form).data('nextFormUrl') !== undefined) {
        const responseId = ($(form).data('nextFormIdProperty') === undefined) ?
          response.id :
          response.data[$(form).data('nextFormIdProperty')];
        XiboFormRender($(form).data().nextFormUrl.replace(':id', responseId));
      }
    }
  } else {
    // Why did we fail?
    if (response.login) {
      // We were logged out
      LoginBox(response.message);
    } else {
      // Likely just an error that we want to report on
      SystemMessageInline(response.message, $(form).closest('.modal'));
    }
  }

  return false;
};

/**
 * Renders a Hover window and sets up events to destroy the window.
 */
function XiboHoverRender(url, x, y) {
  // Call some AJAX
  // TODO: Change this to be hover code
  $.ajax({
    type: 'get',
    url: url,
    cache: false,
    dataType: 'json',
    success: function(response) {
      // Was the Call successful
      if (response.success) {
        let dialogWidth = '500';
        let dialogHeight = '500';

        // Do we need to alter the dialog size?
        if (response.dialogSize) {
          dialogWidth = response.dialogWidth;
          dialogHeight = response.dialogHeight;
        }

        // Create the the popup bubble with our parameters
        $('body').append('<div class="XiboHover"></div>');

        $('.XiboHover').css('position', 'absolute').css(
          {
            display: 'none',
            width: dialogWidth,
            height: dialogHeight,
            top: y,
            left: x,
          },
        ).fadeIn('slow').hover(
          function() {
            return false;
          },
          function() {
            $('.XiboHover').hide().remove();
            return false;
          },
        );

        // Set the dialog HTML to be the response HTML
        $('.XiboHover').html(response.html);

        // Do we have to call any functions due to this success?
        if (response.callBack != '' && response.callBack != undefined) {
          eval(response.callBack)(name);
        }

        // Call Xibo Init for this form
        XiboInitialise('.XiboHover');
      } else {
        // Login Form needed?
        if (response.login) {
          LoginBox(response.message);
          return false;
        } else {
          // Just an error we dont know about
          if (response.message == undefined) {
            SystemMessage(response);
          } else {
            SystemMessage(response.message);
          }
        }
      }

      return false;
    },
  });

  // Dont then submit the link/button
  return false;
}

/**
 * Closes the dialog window
 */
window.XiboDialogClose = function(refreshTable) {
  refreshTable = refreshTable !== undefined;

  bootbox.hideAll();

  if (refreshTable) {
    XiboRefreshAllGrids();
  }
};

/**
 * Apply a form instead of saving and closing
 * @constructor
 */
window.XiboDialogApply = function(formId) {
  const form = $(formId);

  form.data('apply', true);

  form.submit();
};

window.XiboSwapDialog = function(formUrl, data) {
  bootbox.hideAll();
  XiboFormRender(formUrl, data);
};

window.XiboRedirect = function(url) {
  window.location.href = url;
};

/**
 * Display a login box
 * @param {String} message
 */
window.LoginBox = function(message) {
  // Reload the page (appending the message)
  window.location.reload();
};

/**
 * Update User preferences
 * @param prefs
 * @param success
 */
window.updateUserPref = function(prefs, success) {
  // If we do not have a success function provided, then set one.
  if (success === undefined || success === null) {
    success = function(response) {
      if (response.success) {
        SystemMessage(response.message, true);
      } else if (response.login) {
        LoginBox(response.message);
      } else {
        SystemMessage(response.message, response.success);
      }
      return false;
    };
  }

  $.ajax({
    type: 'post',
    url: userPreferencesUrl,
    cache: false,
    dataType: 'json',
    data: {
      preference: prefs,
    },
    success: success,
  });
};

/**
 * Displays the system message
 * @param {String} messageText
 * @param {boolean} success
 */
window.SystemMessage = function(messageText, success) {
  if (messageText == '' || messageText == null) {
    return;
  }

  if (success) {
    toastr.success(messageText);
  } else {
    const dialog = bootbox.dialog({
      message: messageText,
      title: 'Application Message',
      size: 'large',
      buttons: [{
        label: 'Close',
        className: 'btn-bb-close',
        callback: function() {
          dialog.modal('hide');
        },
      }],
      animate: false,
    });
  }
};

/**
 * Displays the system message
 * @param {String} messageText
 * @param {Bool} success
 */
window.SystemMessageInline = function(messageText, modal) {
  if (messageText == '' || messageText == null) {
    return;
  }

  // if modal is null (or not a form)
  // then pick the nearest .text error instead.
  if (modal == undefined || modal == null || modal.length == 0) {
    modal = $('.modal');
  }

  // popup if no form
  if (modal.length <= 0) {
    toastr.error(messageText);
    return;
  }

  // Remove existing errors
  $('.form-error', modal).remove();

  // Re-enabled any disabled buttons
  $(modal).find('.btn').removeClass('disabled');

  $('<div/>', {
    class: 'card bg-light p-3 text-danger col-sm-12 text-center form-error',
    html: messageText,
  }).appendTo(modal.find('.modal-footer'));
};

/**
 * Make a Paged Layout Selector from a
 * Select Element and its parent (which can be null)
 * @param element
 * @param parent
 * @param dataFormatter
 * @param addRandomId
 */
window.makePagedSelect = function(
  element,
  parent,
  dataFormatter,
  addRandomId = false,
) {
  // If we need to append random id
  if (addRandomId === true) {
    const randomNum = Math.floor(1000000000 + Math.random() * 9000000000);
    const previousId = $(element).attr('id');
    const newId = previousId ? previousId + '_' + randomNum : randomNum;
    $(element).attr('data-select2-id', newId);
  }

  element.select2({
    dropdownParent: ((parent == null) ? $('body') : $(parent)),
    minimumResultsForSearch: (element.data('hideSearch')) ? Infinity : 1,
    ajax: {
      url: element.data('searchUrl'),
      dataType: 'json',
      delay: 250,
      data: function(params) {
        let query = {
          start: 0,
          length: 10,
        };

        // Term to use for search
        let searchTerm = params.term;

        // If we search by tags
        if (
          searchTerm != undefined &&
          element.data('searchTermTags') != undefined
        ) {
          // Get string
          const tags = searchTerm.match(/\[([^}]+)\]/);
          let searchTags = '';

          // If we have match for tag search
          if (tags != null) {
            // Add tags to search
            searchTags = tags[1];

            // Remove tags in the query text
            searchTerm = searchTerm.replace(tags[0], '');

            // Add search by tags to the query
            query[element.data('searchTermTags')] = searchTags;
          }
        }

        // Search by searchTerm
        query[element.data('searchTerm')] = searchTerm;

        // Check to see if we've been given additional filter options
        if (element.data('filterOptions') !== undefined) {
          query = $.extend({}, query, element.data('filterOptions'));
        }

        // Set the start parameter based on the page number
        if (params.page != null) {
          query.start = (params.page - 1) * 10;
        }

        return query;
      },
      processResults: function(data, params) {
        const results = [];
        const $element = element;

        // If we have a custom data formatter
        if (
          dataFormatter &&
          typeof dataFormatter === 'function'
        ) {
          data = dataFormatter(data);
        }

        // Check if we have a display all option
        const displayAll = $element.data('displayAll') ?? false;

        $.each(data.data, function(index, el) {
          const result = {
            id: el[$element.data('idProperty')],
            text: el[$element.data('textProperty')],
          };

          if ($element.data('thumbnail') !== undefined) {
            result.thumbnail = el[$element.data('thumbnail')];
          }

          if ($element.data('additionalProperty') !== undefined) {
            const additionalProperties =
              $element.data('additionalProperty').split(',');
            $.each(additionalProperties, function(index, property) {
              result[property] = el[property];
            });
          }

          results.push(result);
        });

        let page = params.page || 1;
        page = (page > 1) ? page - 1 : page;

        return {
          results: results,
          pagination: {
            more: !displayAll && (page * 10 < data.recordsTotal),
          },
        };
      },
    },
    templateResult: function(state) {
      let stateText = '';

      // Add thumbnail if available
      if (state.thumbnail) {
        stateText += '<span class=\'option-thumbnail mr-3\'>' +
          '<img style=\'width: 100px; height: 60px; ' +
          'object-fit: cover;\' src=\'' + state.thumbnail + '\' /></span>';
      }

      // Add option text
      stateText +=
        '<span class=\'option-text\'>' + state.text + '</span></span>';
      return $(stateText);
    },
  });

  element.on('select2:open', function(event) {
    setTimeout(function() {
      $(event.target).data('select2').dropdown?.$search?.get(0).focus();
    }, 10);
  });

  // Set initial value if exists
  if (
    [undefined, ''].indexOf(element.data('initialValue')) == -1 &&
    [undefined, ''].indexOf(element.data('initialKey')) == -1
  ) {
    const initialValue = element.data('initialValue');
    const initialKey = element.data('initialKey');
    const textProperty = element.data('textProperty');
    const idProperty = element.data('idProperty');
    let dataObj = {};
    dataObj[initialKey] = initialValue;

    // if we have any filter options, add them here as well
    // for example isDisplaySpecific filter is important for displayGroup.search
    if (element.data('filterOptions') !== undefined) {
      dataObj = $.extend({}, dataObj, element.data('filterOptions'));
    }

    $.ajax({
      url: element.data('searchUrl'),
      type: 'GET',
      data: dataObj,
    }).then(function(data) {
      // Do we need to check if it's selected
      let checkSelected = false;

      // If we have a custom data formatter
      if (
        dataFormatter &&
        typeof dataFormatter === 'function'
      ) {
        data = dataFormatter(data);
        checkSelected = true;
      }

      // create the option and append to Select2
      data.data.forEach((object) => {
        let isSelected = true;

        // Check if it's selected if needed
        if (checkSelected) {
          isSelected = (initialValue == object[idProperty]);
        }

        // Only had if the option is selected
        if (isSelected) {
          const option = new Option(
            object[textProperty],
            object[idProperty],
            isSelected,
            isSelected,
          );
          element.append(option);
        }
      });

      // Trigger change but skip auto save
      element.trigger(
        'change',
        [{
          skipSave: true,
        }],
      );

      // manually trigger the `select2:select` event
      element.trigger({
        type: 'select2:select',
        params: {
          data: data,
        },
      });
    });
  }
};

/**
 * Make a dropwdown with a search field for
 * option's text and tag datafield (data-tags)
 * @param element
 * @param parent
 * @param addRandomId
 */
window.makeLocalSelect = function(element, parent, addRandomId = false) {
  // If we need to append random id
  if (addRandomId === true) {
    const randomNum = Math.floor(1000000000 + Math.random() * 9000000000);
    const previousId = $(element).attr('id');
    const newId = previousId ? previousId + '_' + randomNum : randomNum;
    $(element).attr('data-select2-id', newId);
  }

  element.select2({
    dropdownParent: ((parent == null) ? $('body') : $(parent)),
    matcher: function(params, data) {
      const testElementFilter = function(filter, elementFilterClassName) {
        const elementFilterClass =
          $(data.element).data()[elementFilterClassName];

        // Get element class array ( one or more elements split by comma)
        const elementClassArray =
          (elementFilterClass != undefined) ?
            elementFilterClass.replace(' ', '').split(',') : [];

        // If filter exists and it's not in one
        // of the element filters, return empty data
        return (
          filter != undefined &&
          filter != '' &&
          !elementClassArray.includes(filter)
        );
      };

      // If filterClass is defined, try to filter the elements by it
      const mainFilterClass = $(data.element.parentElement).data().filterClass;

      if (Array.isArray(mainFilterClass)) {
        for (let index = 0; index < mainFilterClass.length; index++) {
          if (
            testElementFilter(
              mainFilterClass[index],
              'filter' + index + 'Class',
            )
          ) {
            return null;
          }
        }
      } else {
        if (testElementFilter(mainFilterClass, 'filterClass')) {
          return null;
        }
      }

      // If there are no search terms, return all of the data
      if ($.trim(params.term) === '') {
        return data;
      }

      // Tags
      const tags = params.term.match(/\[([^}]+)\]/);
      let queryText = params.term;
      let queryTags = '';

      if (tags != null) {
        // Add tags to search
        queryTags = tags[1];

        // Replace tags in the query text
        queryText = params.term.replace(tags[0], '');
      }

      // Remove whitespaces and split by comma
      queryText = queryText.replace(' ', '').split(',');
      queryTags = queryTags.replace(' ', '').split(',');

      // Find by text
      for (let index = 0; index < queryText.length; index++) {
        const text = queryText[index];
        if (
          text != '' &&
          data.text.toUpperCase().indexOf(text.toUpperCase()) > -1
        ) {
          return data;
        }
      }

      // Find by tag ( data-tag )
      for (let index = 0; index < queryTags.length; index++) {
        const tag = queryTags[index];
        if (
          tag != '' &&
          $(data.element).data('tags') != undefined &&
          $(data.element).data('tags').toUpperCase()
            .indexOf(tag.toUpperCase()) > -1
        ) {
          return data;
        }
      }

      // Return `null` if the term should not be displayed
      return null;
    },
    templateResult: function(state) {
      if (!state.id) {
        return state.text;
      }

      const $el = $(state.element);

      if ($el.data().content !== undefined) {
        return $($el.data().content);
      }

      return state.text;
    },
  });

  element.on('select2:open', function(event) {
    setTimeout(function() {
      $(event.target).data('select2').dropdown?.$search?.get(0).focus();
    }, 10);
  });
};

// Custom submit for user preferences
window.userPreferencesFormSubmit = function() {
  const $form = $('#userPreferences');
  // Replace all checkboxes with hidden input fields
  $form.find('input[type="checkbox"]').each(function(_idx, el) {
    // Get checkbox values
    const value = $(el).is(':checked') ? 'on' : 'off';
    const id = $(el).attr('id');

    // Create hidden input
    $('<input type="hidden">')
      .attr('id', id)
      .attr('name', id)
      .val(value)
      .appendTo($(el).parent());

    // Disable checkbox so it won't be submitted
    $(el).attr('disabled', true);
  });
  $form.submit();
};

// Initialise date time picker
window.initDatePicker = function(
  $element,
  baseFormat,
  displayFormat,
  options,
  onChangeCallback,
  clearButtonActive,
  onClearCallback,
) {
  // Default values
  options = (typeof options == 'undefined') ? {} : options;
  onChangeCallback =
    (typeof onChangeCallback == 'undefined') ? null : onChangeCallback;
  clearButtonActive =
    (typeof clearButtonActive == 'undefined') ? true : clearButtonActive;
  onClearCallback =
    (typeof onClearCallback == 'undefined') ? null : onClearCallback;

  // Check for date format
  if (baseFormat == undefined || displayFormat == undefined) {
    console.error('baseFormat and displayFormat needs to be defined!');
    return false;
  }

  if ($element.data('customFormat')) {
    displayFormat = $element.data('customFormat');
  }

  let $inputElement = $element;
  const initialValue = $element.val();

  if (calendarType == 'Jalali') {
    if (options.altField != undefined) {
      $inputElement = $(options.altField);
    } else {
      $inputElement = $(
        '<input type="text" class="form-control" id="' +
        $element.attr('id') +
        'Link">');
      $element.after($inputElement).hide();
    }

    $inputElement.persianDatepicker(Object.assign({
      initialValue: ((initialValue != undefined) ? initialValue : false),
      altField: '#' + $element.attr('id'),
      altFieldFormatter: function(unixTime) {
        return (moment.unix(unixTime / 1000).format(baseFormat));
      },
      onSelect: function() {
        // Trigger change after close
        $element.trigger('change');
        $inputElement.trigger('change');
      },
    }, options));

    // Add the readonly property
    $inputElement.attr('readonly', 'readonly');
  } else if (calendarType == 'Gregorian') {
    // Remove tabindex from modal to fix flatpickr bug
    $element.parents('.bootbox.modal').removeAttr('tabindex');

    flatpickr.l10ns.default.firstDayOfWeek =
      parseInt(moment().startOf('week').format('d'));

    // Create flatpickr
    flatpickr($element, Object.assign({
      altInput: true,
      allowInput: false,
      defaultDate: ((initialValue != undefined) ? initialValue : null),
      altInputClass: 'datePickerHelper ' + $element.attr('class'),
      disableMobile: true,
      altFormat: displayFormat,
      dateFormat: baseFormat,
      locale: (language != 'en-GB') ? language : 'default',
      defaultHour: '00',
      getWeek: function(dateObj) {
        return moment(dateObj).week();
      },
      parseDate: function(datestr, format) {
        return moment(datestr, format, true).toDate();
      },
      formatDate: function(date, format, locale) {
        return moment(date).format(format);
      },
    }, options));
  }

  // Callback for on change event
  $inputElement.on('change', function() {
    // Callback if exists
    if (onChangeCallback != null && typeof onChangeCallback == 'function') {
      onChangeCallback();
    }
  });

  // Clear button
  if (clearButtonActive) {
    $inputElement.parent().find('.date-clear-button').removeClass('d-none')
      .on('click', function() {
        updateDatePicker($inputElement, '');

        // Clear callback if defined
        if (onClearCallback != null && typeof onClearCallback == 'function') {
          onClearCallback();
        }
      });
  }

  // Toggle button
  $inputElement.parent().find('.date-open-button').on('click', function() {
    if (calendarType == 'Gregorian') {
      if ($inputElement[0]._flatpickr != undefined) {
        $inputElement[0]._flatpickr.open();
      }
    } else if (calendarType == 'Jalali') {
      $inputElement.data().datepicker.show();
    }
  });
};

// Update date picker/pickers
window.updateDatePicker = function($element, date, format, triggerChange) {
  // Default values
  triggerChange = (typeof triggerChange == 'undefined') ? false : triggerChange;

  if (calendarType == 'Gregorian') {
    // Update gregorian calendar
    if ($element[0]._flatpickr != undefined) {
      if (date == '') {
        $element.val('').trigger('change');
        $element[0]._flatpickr.setDate('');
      } else if (format != undefined) {
        $element[0]._flatpickr.setDate(date, triggerChange, format);
      } else {
        $element[0]._flatpickr.setDate(date);
      }
    }
  } else if (calendarType == 'Jalali') {
    if (date == '') {
      $element.val('').trigger('change');
      $('#' + $element.attr('id') + 'Link').val('').trigger('change');
    } else {
      // Update jalali calendar
      $('#' + $element.attr('id') + 'Link').data().datepicker
        .setDate(moment(date, format).unix() * 1000);
    }
  }
};

// Destroy date picker
window.destroyDatePicker = function($element) {
  if (calendarType == 'Gregorian') {
    // Destroy gregorian calendar
    if ($element[0]._flatpickr != undefined) {
      $element[0]._flatpickr.destroy();
    }

    // Set value to text field if exists
    if ($element.attr('value') != undefined) {
      $element.val($element.attr('value'));
    }
  } else if (calendarType == 'Jalali') {
    // Destroy jalali calendar
    $('#' + $element.attr('id') + 'Link').data().datepicker.destroy();
  }

  // Unbind toggle button click
  $element.parent().find('.date-open-button').off('click');
};

window.updateRangeFilter = function($element, $from, $to, callBack) {
  let value = $element.val();
  let from;
  let to;
  const isCustom = value === 'custom';
  const isPast = value.includes('last');

  if (value === 'agenda') {
    value = 'day';
  }

  if (isCustom) {
    $('.custom-date-range').removeClass('d-none');
  } else {
    $('.custom-date-range').addClass('d-none');

    if (!isPast) {
      from = moment().startOf(value).format(jsDateFormat);
      to = moment().endOf(value).format(jsDateFormat);
    } else {
      const pastValue = value.replace('last', '');
      from = moment().startOf(pastValue)
        .subtract(1, pastValue + 's').format(jsDateFormat);
      to = moment().endOf(pastValue)
        .subtract(1, pastValue + 's').format(jsDateFormat);
    }

    updateDatePicker($from, from, jsDateFormat, true);
    updateDatePicker($to, to, jsDateFormat, true);
  }

  (typeof callBack === 'function') && callBack();
};

/**
 * Create a mini layout preview
 * @param  {string} previewUrl
 */
window.createMiniLayoutPreview = function(previewUrl) {
  // Add element to page if it's not already
  if ($('.page-content').find('.mini-layout-preview').length == 0) {
    const miniPlayerTemplate = templates['mini-player'];
    $('.page-content').append(miniPlayerTemplate({
      trans: translations.miniPlayer,
    }));
  }

  const $layoutPreview = $('.mini-layout-preview');
  const $layoutPreviewContent = $layoutPreview.find('#content');

  // Create base template for preview content
  const previewTemplate =
    Handlebars.compile(
      '<iframe scrolling="no" src="{{url}}" width="{{width}}px" ' +
      'height="{{height}}px" style="border:0;"></iframe>');

  // Clean all selected elements
  $layoutPreviewContent.html('');

  // Handle buttons
  $layoutPreview.find('#playBtn').show().off().on('click', function(ev) {
    // Hide button
    $(ev.currentTarget).hide();

    // Load and start preview
    $layoutPreview.find('#content').append(previewTemplate({
      url: previewUrl,
      width: $layoutPreview.hasClass('large') ? '760' : '440',
      height: $layoutPreview.hasClass('large') ? '420' : '240',
    }));
  });

  $layoutPreview.find('#closeBtn').off().on('click', function() {
    // Close preview and empty content
    $layoutPreview.find('#content').html('');
    $layoutPreview.removeClass('show');
    $layoutPreview.remove();
  });

  $layoutPreview.find('#newTabBtn').off().on('click', function() {
    // Open preview in new tab
    window.open(previewUrl, '_blank');
  });

  $layoutPreview.find('#sizeBtn').off().on('click', function(ev) {
    // Empty content
    $layoutPreview.find('#content').html('');

    // Toggle size class
    $layoutPreview.toggleClass('large');

    // Change icon based on size state
    $(ev.currentTarget).toggleClass(
      'fa-arrow-circle-down', $layoutPreview.hasClass('large'),
    );
    $(ev.currentTarget).toggleClass(
      'fa-arrow-circle-up', !$layoutPreview.hasClass('large'),
    );
    // Re-show play button
    $layoutPreview.find('#playBtn').show();
  });

  // Show layout preview element
  $layoutPreview.addClass('show');
};

/**
 * https://stackoverflow.com/questions/15900485/correct-way-to-convert-size-in-bytes-to-kb-mb-gb-in-javascript
 * @param {number} size
 * @param {number} precision
 * @returns {string}
 */
window.formatBytes = function(size, precision) {
  if (size === 0) {
    return '0 Bytes';
  }

  const c = 0 > precision ?
    0 : precision;
  const d = Math.floor(Math.log(size) / Math.log(1024));
  return parseFloat((size / Math.pow(1024, d)).toFixed(c)) +
    ' ' + ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'][d];
};

/**
 * Create bootstrap colorpicker
 * @param {object} element jquery object or CSS selector
 * @param {object} options bootstrap-colorpicker options (https://itsjavi.com/bootstrap-colorpicker/v2/)
 */
function createColorPicker(element, options) {
  const $self = $(element);

  // Disable autocomplete
  $self.attr('autocomplete', 'off');

  $self.colorpicker(Object.assign({
    format: 'hex',
  }, options));
}

window.moveFolderMultiSelectFormOpen = function(dialog) {
  // make bootstrap happy.
  if ($('#folder-tree-form-modal').length != 0) {
    $('#folder-tree-form-modal').remove();
  }

  const folderContainer =
    '<div class="card p-3 mb-3 bg-light" ' +
    'id="container-folder-form-tree"></div>';
  const $hiddenInput =
    $('<input name="folderId" type="hidden" id="formFolderId">');

  $hiddenInput.on('change', function(ev) {
    dialog.data().commitData = {folderId: $(ev.currentTarget).val()};
  });

  $(dialog).find('.modal-body').append(folderContainer);
  $(dialog).find('.modal-body').append($hiddenInput);
  initJsTreeAjax(
    '#container-folder-form-tree',
    'multi-select-folder',
    true,
    600000,
  );
};

window.triggerWebhookMultiSelectFormOpen = function(dialog) {
  const $triggerCode = $(
    '<div class="form-group row">' +
    '<label class="col-sm-2 control-label" for="triggerCode" ' +
    'accesskey="">' + translations.triggerCodeLabel + '</label>' +
    '<div class="col-sm-10">' +
    '<input class="form-control" name="triggerCode" type="text" ' +
    'id="triggerCode" value="">' +
    '<span class="help-block">' +
    translations.triggerCodeHelpText +
    '</span>' +
    '</div>' +
    '</div>',
  );

  $(dialog).find('.modal-body').append($triggerCode);

  $('#triggerCode').on('change', function() {
    dialog.data().commitData = {
      triggerCode: $('#triggerCode').val(),
    };
  });
};

window.sendCommandMultiSelectFormOpen = function(dialog) {
  // Inject a list of commands into the form, in a drop down.
  const $commandSelect = $(
    '<div class="form-group form-horizontal row mt-4">' +
    '<label class="col-sm-2 control-label" for="commandId" accesskey="">' +
    translations.sendCommandLabel + '</label>' +
    '<div class="col-sm-10">' +
    '<select name="commandId" class="form-control" ' +
    'data-search-url="' + commandSearchUrl + '" data-search-term="command" ' +
    'data-id-property="commandId" data-text-property="command">' +
    '<span class="help-block">' + translations.sendCommandHelpText + '</span>' +
    '</div>' +
    '</div>',
  );

  // Add the list to the body.
  $(dialog).find('.modal-body').append($commandSelect);

  makePagedSelect(dialog.find('select[name="commandId"]'), dialog);

  dialog.find('select[name="commandId"]').on('select2:select', function(ev) {
    dialog.data().commitData = {
      commandId: $(ev.currentTarget).select2('data')[0].id,
    };
  });
};
