/* eslint-disable no-extend-native */
/* eslint-disable new-cap */
/* eslint-disable no-invalid-this */
// Include templates
const templates = {
  dataSetOrderClauseTemplate:
    require('../templates/form-helpers-data-set-order-clause.hbs'),
  dataSetFilterClauseTemplate:
    require('../templates/form-helpers-data-set-filter-clause.hbs'),
  subPlaylistFormTemplate:
    require('../templates/form-helpers-sub-playlist-form.hbs'),
  subPlaylistContainerTemplate:
    require('../templates/form-helpers-sub-playlist-container.hbs'),
  twittermetroColorsTemplate:
    require('../templates/form-helpers-twitter-metro-colors.hbs'),
  chartColorsTemplate:
    require('../templates/form-helpers-chart-colors.hbs'),
  chartGraphConfigTemplate:
    require('../templates/form-helpers-chart-graph-config.hbs'),
  worldClockTemplate:
    require('../templates/form-helpers-world-clock.hbs'),
  menuProductOptions:
    require('../templates/form-helpers-menuboard-product.hbs'),
  editorRegionControls:
    require('../templates/forms/inputs/add-ons/richTextRegionControls.hbs'),
};

const CKEDITOR_MIN_HEIGHT = 120;
const CKEDITOR_MAX_HEIGHT = 200;
const CKEDITOR_OVERLAY_WIDTH = 2;
const CKEDITOR_MARGIN = 8;
const CKEDITOR_SCROLLBAR_MARGIN = 8;

const formHelpers = function() {
  // Default params ( might change )
  this.defaultBackgroundColor = '#eee';

  this.defaultRegionDimensions = {
    width: 800,
    height: 600,
  };

  /**
   * Set helpers to work with the tool that is using it
   * @param {object} namespace - Helper namespace
   * @param {string} mainObject - Helper main object
   */
  this.setup = function(namespace, mainObject) {
    this.namespace = namespace;
    this.mainObject = mainObject;
  };

  /**
   * Get CKEditor config
   * @return {Promise} - Promise
   */
  this.getCKEditorConfig = function() {
    let fontNames = CKEDITOR.config.font_names;

    // Base editor config
    const editorConfig = {
      contentsCss: [CKEDITOR.getUrl('contents.css'), libraryFontCSS],
      imageDownloadUrl: imageDownloadUrl,
    };

    return new Promise((resolve, reject) => {
      $.get(getFontsUrl + '?length=10000')
        .done(function(res) {
          // Get res.data fonts into the fontNames string
          res.data.forEach(function(font) {
            fontNames += `;${font.name}/${font.familyName}`;
          });

          // Sort the fontNames string
          fontNames = fontNames.split(';').sort().join(';');

          // Set fontNames to the editorConfig
          editorConfig.font_names = fontNames;

          // Resolve the promise and return the editorConfig
          resolve(editorConfig);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          // Output error to console
          console.error(jqXHR, textStatus, errorThrown);

          // Reject the promise
          reject(jqXHR, textStatus, errorThrown);
        });
    });
  };

  /**
   * Use passed main checkbox object's value (checkBoxSelector)
   * to toggle the secondary passed fields (inputFieldsSelector
   * OR inputFieldsSelectorOpposite) inside the form
   * @param {object} form - Form object
   * @param {string} checkBoxSelector - CSS selector for the checkbox object
   * @param {string} inputFieldsSelector
   *  - CSS selector for the input fields to toggle
   *  ( show on checked, hide on unchecked)
   * @param {string=} inputFieldsSelectorOpposite
   *  - CSS selector for the input fields that behave diferently
   *  from the select fields on previous param
   *  ( hide on checked, show on unchecked)
   * @param {string=} customVisibleDisplayProperty
   *  - CSS display property to use for the object visibility
   */
  this.setupCheckboxInputFields = function(
    form,
    checkBoxSelector,
    inputFieldsSelector,
    inputFieldsSelectorOpposite,
    customVisibleDisplayProperty,
  ) {
    const checkboxObj = $(form).find(checkBoxSelector);
    const inputFieldsObj = $(form).find(inputFieldsSelector);
    const inputFieldsObjOpposite = $(form).find(inputFieldsSelectorOpposite);
    const displayVisibleProperty =
      (customVisibleDisplayProperty) ? customVisibleDisplayProperty : '';

    const displayInputFields = function() {
      // Init
      if (checkboxObj.is(':checked') == false) {
        inputFieldsObj.css('display', 'none');
        inputFieldsObjOpposite.css('display', displayVisibleProperty);
      } else if (checkboxObj.is(':checked') == true) {
        inputFieldsObj.css('display', displayVisibleProperty);
        inputFieldsObjOpposite.css('display', 'none');
      }
    };

    // Init
    displayInputFields();

    // Change
    checkboxObj.on('change', displayInputFields);
  };

  /**
   * Use passed main input object's value (inputValueSelector)
   * to toggle the secondary passed fields (inputFieldsArray) inside the form
   * @param {object} form - Form object
   * @param {string} inputValueSelector
   *   - CSS selector for the input field that triggers the
   *  "change" and "input" events
   * @param {Array.<string>} inputFieldsArray
   *   - Array of CSS selector for the input fields to be
   *  compared with the values to be toggled
   * @param {Array.<number>} customIndexValues
   *   - Array of values to compare to the inputFieldsArray,
   *  if it matches, the field will be shown/hidden
   *  according to the inverted flag state
   * @param {bool=} inverted
   *   - Use hide element instead of show just element ( default )
   * @param {string} customTarget - CSS selector for the target element
   * @param {string} customVisibleDisplayProperty
   *   - CSS display property to use for the object visibility
   */
  this.setupObjectValueInputFields = function(
    form,
    inputValueSelector,
    inputFieldsArray,
    customIndexValues = null,
    inverted = false,
    customTarget = null,
    customVisibleDisplayProperty,
  ) {
    const displayVisibleProperty =
      (customVisibleDisplayProperty) ? customVisibleDisplayProperty : '';
    const elementClass = (!inverted) ? displayVisibleProperty : 'none';
    const inverseClass = (!inverted) ? 'none' : displayVisibleProperty;

    const inputValueField = $(form).find(inputValueSelector);

    const displayInputFields = function() {
      const inputValue = inputValueField.val();

      // Hide/show all fields first
      for (let index = 0; index < inputFieldsArray.length; index++) {
        const element = $(form).find(inputFieldsArray[index]);

        $(element).css('display', inverseClass);
      }

      // If there is a custom target for the marked fields
      if (customTarget != null) {
        form = customTarget;
      }

      // Hide/Show only the marked ones
      for (let index = 0; index < inputFieldsArray.length; index++) {
        const element = $(form).find(inputFieldsArray[index]);

        let currentIndex = index;

        if (customIndexValues != null) {
          currentIndex = customIndexValues[index];
        }

        if (currentIndex == inputValue) {
          $(element).css('display', elementClass);
        }
      }
    };

    // Init
    displayInputFields();

    // Change
    inputValueField.on('change input', displayInputFields);
  };

  /**
   * Use a callback to toggle a selector visibility
   * @param {jQuery} triggerFields
   *   - jQuery element for the input field that
   *  triggers the "change" and "input" events
   * @param {jQuery} targetFields
   *   - jQuery element(s) for the input fields
   *  to be compared with the values to be toggled
   * @param {*} compareValue
   *   - value to be used to compare with the trigger input
   * @param {function} test
   *   - Function to test the condition (a,b)
   */
  this.setupConditionalInputFields = function(
    triggerFields,
    targetFields,
    compareValue,
    test,
  ) {
    /**
     * Check test and toggle visibility
     */
    const checkTestAndApply = function() {
      targetFields.toggle(test(compareValue));
    };

    // Init
    checkTestAndApply();

    // Change
    triggerFields.on('change input', checkTestAndApply);
  };

  /**
   * Append an error message on form
   * ( create or update a previously created one )
   * @param {object} form
   *   - Form object that contains one object with id = "errorMessage"
   * @param {string} message - Message to be displayed
   * @param {string} type
   *   - Type of message (Bootstrap Alert: success, danger, info, warning)
   */
  this.displayErrorMessage = function(form, message, type) {
    if ($(form).find('#errorMessage').length) {
      // Replace message in form error
      $(form).find('#errorMessage p').html(message);
    } else {
      // Build message html and append to form
      let html = '';
      html += '<div id="errorMessage" class="alert alert-' +
        type +
        '"><div class="row"><div class="col-sm-12 ">';
      html += '<p>' + message + '</p>';
      html += '</div></div></div>';

      // Append message to the form
      $(form).append(html);
    }
  };

  /**
   * Clear all error messages from form
   * @param {object} form
   */
  this.clearErrorMessage = function(form) {
    $(form).find('#errorMessage').remove();
  };

  /**
   * Fill a tab with the ajax request information and then switch to that tab
   * @param {string} tabName - Tab name
   * @param {string} url - Request url
   */
  this.requestTab = function(tabName, url) {
    $.ajax({
      type: 'get',
      url: url,
      cache: false,
      data: 'tab=' + tabName,
      success: function(response, status, xhr) {
        $('.tab-content #' + tabName).html(response.html);

        $('.nav-tabs a[href="#' + tabName + '"]').tab('show');
      },
    });
  };

  /**
   * Setup the snippets' selector
   * @param {object} selector - DOM select object
   * @param {function} callback
   *   - A function to run after setting the select2 instance
   */
  this.setupSnippetsSelector = function(selector, callback) {
    selector.select2().off().on('select2:select', function(e) {
      // Call callback
      callback(e);

      // Reset selector
      $(this).val('').trigger('change');
    });
  };

  /**
   * Setup the library/media selector
   * @param {object} selector - DOM select object
   * @param {function} callback
   *  - A function to run after setting the select2 instance
   */
  this.setupMediaSelector = function(selector, callback) {
    selector.select2({
      ajax: {
        url: selector.data().searchUrl,
        dataType: 'json',
        delay: 250,
        data: function(params) {
          const queryText = params.term;
          const queryTags = '';

          // Tags
          if (params.term != undefined) {
            const tags = params.term.match(/\[([^}]+)\]/);
            if (tags != null) {
              // Add tags to search
              queryTags = tags[1];

              // Replace tags in the query text
              queryText = params.term.replace(tags[0], '');
            }

            // Remove whitespaces and split by comma
            queryText = queryText.replace(' ', '');
            queryTags = queryTags.replace(' ', '');
          }

          const query = {
            media: queryText,
            tags: queryTags,
            type: 'image',
            retired: 0,
            assignable: 1,
            start: 0,
            length: 10,
          };

          // Set the start parameter based on the page number
          if (params.page != null) {
            query.start = (params.page - 1) * 10;
          }

          // Find out what is inside the search box for this list,
          // and save it (so we can replay it when the list
          // is opened again)
          if (params.term !== undefined) {
            localStorage.liveSearchPlaceholder = params.term;
          }

          return query;
        },
        processResults: function(data, params) {
          const results = [];

          $.each(data.data, function(index, element) {
            results.push({
              id: element.mediaId,
              text: element.name,
              imageUrl:
                selector.data().imageUrl.replace(':id', element.mediaId),
              disabled: false,
            });
          });

          let page = params.page || 1;
          page = (page > 1) ? page - 1 : page;

          return {
            results: results,
            pagination: {
              more: (page * 10 < data.recordsTotal),
            },
          };
        },
      },
      templateResult: function(state) {
        if (!state.id) {
          return state.text;
        }
        const template = window.templates.forms.addOns.dropdownOptionImage({
          title: state.text,
          image: state.imageUrl,
        });

        return $(template);
      },
    }).off().on('select2:select', function(e) {
      callback(e);

      // Reset selector
      $(this).val('').trigger('change');
    });
  };

  /**
* Setup the library/media selector
* @param {object} dialog
   - Dialog object ( the object that contains the overwrittable fields )
* @param {string} triggerSelector - Overwrite Trigger object jquery selector
* @param {string} templateFieldSelector
  - Selected template object jquery selector
* @param {object} targetsObject
  - Object containining pairs of selctors for form
  fields and respective template replacements
*/
  this.setupTemplateOverriding = function(
    dialog,
    triggerSelector,
    templateFieldSelector,
    targetsObject,
  ) {
    // Get extra data
    const data = $(dialog).data().extra;

    const $trigger = $(triggerSelector, dialog);

    // Function to apply template contents to form
    const applyTemplateContentIfNecessary = function(data) {
      // Apply content only if override template is on
      if ($trigger.is(':checked')) {
        // Get the currently selected templateId
        const templateId = $(templateFieldSelector, dialog).val();

        // Get available templates
        let templates = data;

        if (data.templates !== undefined) {
          // Fix for modules with templates as a param of data
          templates = data.templates;
        }

        // Find selected template
        $.each(templates, function(templateIndex, template) {
          if (template.id == templateId) {
            $.each(
              targetsObject,
              function(targetSelector, targetTemplateField) {
                const $target = $(targetSelector, dialog);
                const targetType = $target.attr('type');

                // Process types and assign values
                if (targetType === 'checkbox') { // Checkbox
                  // If the checkbox is a bootstrap switch
                  if ($target.hasClass('bootstrap-switch-target')) {
                    $target.bootstrapSwitch(
                      'state',
                      template[targetTemplateField],
                    );
                  } else {
                    $target.prop('checked', template[targetTemplateField]);
                  }
                } else { // All the other input types
                  $target.val(template[targetTemplateField]);
                }
              });
          }
        });
      } else {
        // If one of the targets is a boostrap switch, switch it off
        forceBootstrapSwitchesOff();
      }
    };


    // Function to switch off all the bootstrapSwitch
    const forceBootstrapSwitchesOff = function() {
      // If one of the targets is a boostrap switch, switch it off
      $.each(targetsObject, function(targetSelector, targetTemplateField) {
        const $target = $(targetSelector, dialog);

        // Turn off the bootstrapSwitch
        if (
          $target.attr('type') === 'checkbox' &&
          $target.hasClass('bootstrap-switch-target')
        ) { // bootstrap switch
          $target.bootstrapSwitch('state', false);
        }
      });
    };

    // Register an onchange listener to manipulate
    // the template content if the selector is changed.
    $trigger.on('change', function() {
      applyTemplateContentIfNecessary(data);
    });

    // On load, if the trigger is uncheckedand a
    // target is a boostrap switch, switch it off
    if (!$trigger.is(':checked')) {
      forceBootstrapSwitchesOff();
    }
  };

  /**
   * Create a CKEDITOR instance to conjure a text editor
   * @param {object} dialog
   *   - Dialog object ( the object that contains the replaceable fields )
   * @param {string} textAreaId - Id of the text area to use for the editor
   * @param {bool=} inline - Inline editor option
   * @param {string=} customNoDataMessage
   *   - Custom message to appear when the field is empty
   * @param {boolean} focusOnBuild - Focus on the editor after building
   * @param {boolean} updateOnBlur - Update the field on blur
   * @return {Promise} - Promise
   */
  this.setupCKEditor = function(
    dialog,
    textAreaId,
    inline = false,
    customNoDataMessage = null,
    focusOnBuild = false,
    updateOnBlur = false,
  ) {
    const self = this;

    // Return promise
    return new Promise((resolve, reject) => {
      // Check if text area is visible
      const visibleOnLoad = $(dialog).find('#' + textAreaId).is(':visible');

      // COLORS
      // Background color for the editor
      const backgroundColor =
        (
          this.mainObject != undefined &&
          typeof this.mainObject.backgroundColor != 'undefined' &&
          this.mainObject.backgroundColor != null
        ) ?
          this.mainObject.backgroundColor :
          this.defaultBackgroundColor;

      // Choose a complementary color
      const color = $c.complement(backgroundColor);

      // Calculate if inline BG colour should be shown
      const inlineHideBGColour = (
        inline && this.mainObject.backgroundImage != undefined &&
        this.mainObject.backgroundImage != null
      );

      const scaleToContainer = (regionDimensions, $scaleTo, inline) => {
        let width;

        // If element isn't visible, set default dimensions
        if (visibleOnLoad === false) {
          width = $(dialog).find('form .tab-content').width();
        } else {
          if (inline) {
            // Outer width for the inline element
            width =
              $scaleTo.outerWidth() -
              (CKEDITOR_MARGIN + CKEDITOR_SCROLLBAR_MARGIN);
          } else {
            // Inner width and a padding for the scrollbar
            width =
              $scaleTo.innerWidth() -
              32 -
              ((iframeBorderWidth + iframeMargin) * 2);
          }
        }

        // Element side plus margin
        const elementWidth = regionDimensions.width;
        const elementHeight = regionDimensions.height;

        let scale = width / elementWidth;

        // Scale within limit values for inline
        if (inline) {
          if (elementHeight * scale < CKEDITOR_MIN_HEIGHT) {
            scale = (CKEDITOR_MIN_HEIGHT - CKEDITOR_MARGIN) / elementHeight;
          } else if (elementHeight * scale > CKEDITOR_MAX_HEIGHT) {
            scale = (CKEDITOR_MAX_HEIGHT - CKEDITOR_MARGIN) / elementHeight;
          }
        }

        return scale;
      };

      const iframeMargin = 10;
      const iframeBorderWidth = 2;

      // DIMENSIONS
      let region = {};

      // Get region dimensions
      if (this.namespace == undefined) {
        if (
          dialog.find('form').data('regionWidth') != undefined &&
          dialog.find('form').data('regionHeight') != undefined
        ) {
          // Get region dimension from form data
          region.dimensions = {
            width: dialog.find('form').data('regionWidth'),
            height: dialog.find('form').data('regionHeight'),
          };
        } else {
          // Empty region ( no dimensions set )
          region = {};
        }
      } else if (this.namespace.mainRegion != undefined) {
        region = this.namespace.mainRegion;
      } else if (this.namespace.selectedObject.type == 'widget') {
        const widget = this.namespace.selectedObject;
        region = this.namespace.getObjectByTypeAndId('region', widget.regionId);
      } else if (this.namespace.selectedObject.type == 'region') {
        region =
          this.namespace.getObjectByTypeAndId(
            'region',
            this.namespace.selectedObject.id,
          );
      }

      let regionDimensions = null;
      let scale = 1;

      const $richTextInput =
        $(dialog).find('#' + textAreaId).parents('.rich-text-input');

      // Calculate dimensions
      if (region.dimensions === undefined) {
        // Without region
        regionDimensions = this.defaultRegionDimensions;

        // Calculate scale based on defaults
        scale = scaleToContainer(
          this.defaultRegionDimensions,
          $richTextInput,
          true);
      } else {
        // If region dimensions are defined, use them as base for the editor
        regionDimensions = region.dimensions;

        if (inline) {
          scale = scaleToContainer(
            regionDimensions,
            $richTextInput,
            true);
        } else {
          // Calculate scale based on the region previewed in the viewer
          scale =
            this.namespace.viewer.DOMObject.find('.viewer-object').width() /
            regionDimensions.width;
        }
      }

      const applyContentsToIframe = function(field) {
        const $container = $(CKEDITOR.instances[textAreaId].container.$);
        const $inputContainer =
          $container.parents('.rich-text-input');

        if (inline) {
          // Inline editor div tweaks to make them
          // behave like the iframe rendered content
          $('.cke_textarea_inline').css('width', regionDimensions.width);
          $('.cke_textarea_inline').css('height', regionDimensions.height);

          // Show background colour if there's no background image on the layout
          if (!inlineHideBGColour) {
            $('.cke_textarea_inline').css('background', backgroundColor);
          }

          $('.cke_textarea_inline').css('transform', 'scale(' + scale + ')');
          $('.cke_textarea_inline').data('originaScale', scale);
          $('.cke_textarea_inline').data('regionWidth', regionDimensions.width);
          $('.cke_textarea_inline').data(
            'regionHeight',
            regionDimensions.height,
          );
          $('.cke_textarea_inline').data('currentScale', scale);
          $('.cke_textarea_inline').css('transform-origin', '0 0');
          $('.cke_textarea_inline').css('word-wrap', 'inherit');
          $('.cke_textarea_inline').css('line-height', 'normal');
          $('.cke_textarea_inline')
            .css('outline-width', (CKEDITOR_OVERLAY_WIDTH / scale));

          // Save new dimensions to data
          $('.cke_textarea_inline').data({
            width: regionDimensions.width * scale,
            height: regionDimensions.height * scale,
            scale: scale,
          });

          $('.cke_textarea_inline p').css('margin', '0 0 16px');
          $('.cke_textarea_inline').show();
        } else {
          $('#cke_' + field + ' iframe').contents().find('head').append(
            '' +
            '<style>' +
            'html { height: 100%; ' +
            '}' +
            'body {' +
            'width: ' + regionDimensions.width + 'px; ' +
            'height: ' + regionDimensions.height + 'px; ' +
            'border: ' + iframeBorderWidth + 'px solid red; ' +
            'background: ' + backgroundColor + '; ' +
            'transform: scale(' + scale + '); ' +
            'margin: ' + iframeMargin + 'px; ' +
            'word-wrap: inherit; ' +
            'transform-origin: 0 0; }' +
            'h1, h2, h3, h4, p { margin-top: 0;}' +
            '</style>');
        }

        // Set parent container height if data exists
        const containerData = $container.data();
        if (containerData !== undefined) {
          const bottomMargin = 2;
          // Set width and height to container
          $container.parents('.rich-text-container')
            .css('height', containerData.height + bottomMargin);
        }

        // If the field with changed width, apply the new scale
        if (
          regionDimensions.width * scale != $inputContainer.width() &&
          visibleOnLoad === true &&
          !inline
        ) {
          scale = scaleToContainer(
            regionDimensions,
            $(dialog).find('#' + textAreaId).parents('.rich-text-input'),
            true);

          applyContentsToIframe(field);
        } else {
          resolve(true);
        }
      };

      // Hide element to avoid glitch
      $(dialog).find('#' + textAreaId).css('opacity', 0);

      // CKEditor default config and init after config is loaded
      return this.getCKEditorConfig().then(function(config) {
        CKEDITOR_DEFAULT_CONFIG = config;

        // Set CKEDITOR viewer height based on
        // region height ( plus content default margin + border*2: 40px )
        const newHeight =
          (regionDimensions.height * scale) + (iframeMargin * 2);
        CKEDITOR.config.height = (newHeight > 500) ? 500 : newHeight;

        // Conjure up a text editor
        if (inline) {
          CKEDITOR.inline(textAreaId, CKEDITOR_DEFAULT_CONFIG);
          (self.namespace.enableInlineModeEditing) &&
            self.namespace.enableInlineModeEditing();
        } else {
          CKEDITOR.replace(textAreaId, CKEDITOR_DEFAULT_CONFIG);
        }

        // Bind to instance ready so that we
        // can adjust some things about the editor.
        CKEDITOR.instances[textAreaId].on('instanceReady', function(ev) {
          // If not defined, cancel instance setup
          if (CKEDITOR.instances[textAreaId] === undefined) {
            return;
          }

          // Fix paste not enabled
          ev.editor.on('beforeCommandExec', function(event) {
            // Show the paste dialog for the paste buttons and right-click paste
            if (event.data.name == 'paste') {
              event.editor._.forcePasteDialog = true;
            }
            // Don't show the paste dialog for Ctrl+Shift+V
            if (
              event.data.name == 'pastetext' &&
              event.data.commandData.from == 'keystrokeHandler'
            ) {
              event.cancel();
            }
          });

          // Trigger focus on textarea on editor focus
          ev.editor.on('focus', function(evt) {
            // Trigger focus event on text area
            $(evt.editor.element.$).trigger('editorFocus');
          });

          // Apply scaling to this editor instance
          applyContentsToIframe(textAreaId);

          // Reapply the background style after switching
          // to source view and back to the normal editing view
          CKEDITOR.instances[textAreaId].on('contentDom', function() {
            applyContentsToIframe(textAreaId);
          });

          // Get the template data from the text area field
          let data = $('#' + textAreaId).val();

          // Replace color if exists
          if (data != undefined) {
            data = data.replace(/#Color#/g, color);
          }

          // Handle no message data
          if (data == '') {
            let dataMessage = '';

            if (textAreaId === 'noDataMessage') {
              dataMessage = translations.noDataMessage;
            } else if (customNoDataMessage !== null) {
              dataMessage = customNoDataMessage;
            } else {
              dataMessage = translations.enterText;
            }

            data = '<span style="font-size: 48px;"><span style="color: ' +
              color +
              ';">' +
              dataMessage +
              '</span></span>';
          }

          // Handle initial template set up
          data = self.convertLibraryReferences(data);

          CKEDITOR.instances[textAreaId].setData(data);

          if (focusOnBuild) {
            CKEDITOR.instances[textAreaId].focus();
          }
        });

        // Do we have any snippets selector?
        const $selectPickerSnippets =
          $(
            '.ckeditor_snippets_select[data-linked-to="' + textAreaId + '"]',
            dialog);
        // Select2 has been initialized
        if ($selectPickerSnippets.length > 0) {
          this.setupSnippetsSelector($selectPickerSnippets, function(e) {
            const linkedTo = $selectPickerSnippets.data().linkedTo;
            const value = e.params.data.element.value;

            if (CKEDITOR.instances[linkedTo] != undefined &&
              value !== undefined) {
              const text = '[' + value + ']';

              CKEDITOR.instances[linkedTo].insertText(text);
            }
          });
        }

        // Do we have a media selector?
        const $selectPicker =
          $(
            '.ckeditor_library_select[data-linked-to="' + textAreaId + '"]',
            dialog);
        if ($selectPicker.length > 0) {
          this.setupMediaSelector($selectPicker, function(e) {
            const linkedTo = $selectPicker.data().linkedTo;
            const value = e.params.data.imageUrl;

            if (value !== undefined && value !== '' && linkedTo != null) {
              if (CKEDITOR.instances[linkedTo] != undefined) {
                CKEDITOR.instances[linkedTo]
                  .insertHtml('<img src="' + value + '" />');
              }
            }
          });
        }

        // Update on blur
        if (updateOnBlur) {
          CKEDITOR.instances[textAreaId].on('blur', function() {
            // Update CKEditor, but don't parse data (do that only on save)
            self.updateCKEditor(textAreaId, false);
          });
        }

        return false;
      });
    });
  };

  /**
   * Restart all CKEDITOR instances
   * @param {Object} options - The ckeditor options
   */
  this.restartCKEditors = function(options) {
    const self = this;

    $.each(CKEDITOR.instances, function(index, value) {
      CKEDITOR.instances[index].destroy();

      self.setupCKEditor(options);
    });
  };

  /**
   * Update text callback CKEDITOR instance
   * @param {Object=} instance - The instance object
   * @param {boolean=} updateParsedData - Update parsed data on CKEditor
   */
  this.updateCKEditor = function(instance = null, updateParsedData = true) {
    const self = this;

    try {
      // Update specific instance
      if (
        instance != undefined &&
        instance != null &&
        CKEDITOR.instances[instance] != undefined
      ) {
        // Parse editor data and update it
        self.parseCKEditorData(instance, null, updateParsedData);
      } else {
        $.each(CKEDITOR.instances, function(index, _value) {
          // Parse editor data and update it
          self.parseCKEditorData(index, null, updateParsedData);
        });
      }
    } catch (e) {
      console.warn('Unable to update CKEditor instances. ' + e);
    }
  };

  /**
   * Destroy text callback CKEDITOR instance
   * @param {Object} instance - The instance object
   */
  this.destroyCKEditor = function(instance) {
    const self = this;

    // Make sure when we close the dialog we also destroy the editor
    try {
      if (instance === undefined) {
        // Destroy all instances
        $.each(CKEDITOR.instances, function(index, value) {
          CKEDITOR.instances[index].destroy();
        });
      } else {
        // Destroy specific instance
        if (CKEDITOR.instances[instance] != undefined) {
          // Parse instance data before destroying
          self.parseCKEditorData(
            instance,
            CKEDITOR.instances[instance].destroy,
            false,
          );
        } else {
          console.warn('CKEditor instance does not exist.');
        }
      }
    } catch (e) {
      console.warn('Unable to remove CKEditor instance. ' + e);
      CKEDITOR.instances = {};
    }
  };

  /**
   * Parse Editor data to turn media path into library tags
   * @param {string} instance - CKEditor instance name to update
   * @param {function=} callback - A function to run after data update
   * @param {boolean=} updateDataAfterParse - Update data after parse
   */
  this.parseCKEditorData = function(
    instance,
    callback = null,
    updateDataAfterParse = true,
  ) {
    // If instance is not set, stop right here
    if (CKEDITOR.instances[instance] === undefined) {
      return;
    }

    const regex =
      new RegExp(CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl
        .replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&')
        .replace(':id', '([0-9]+)'), 'g',
      );

    let data = CKEDITOR.instances[instance].getData();

    data = data.replace(regex, function(match, group1) {
      return '[' + group1 + ']';
    });

    // Update text field with the new data
    // ( to avoid the setData delay on save )
    $('textarea#' + instance).val(data);

    // If we're not saving, trigger change for saving
    if (!updateDataAfterParse) {
      $('textarea#' + instance).trigger('inputChange');
    }

    // Set the appropriate text editor field with this data
    if (updateDataAfterParse) {
      if (callback !== null) {
        CKEDITOR.instances[instance].setData(data, callback);
      } else {
        CKEDITOR.instances[instance].setData(data);
      }
    } else {
      // Still call callback
      (typeof callback === 'function') && callback();
    }
  };

  /**
   * Create and attach a Replace button
   * and open a upload form on click to replace media
   * @param {object} dialog - Dialog object
   */
  this.mediaEditFormOpen = function(dialog) {
    const self = this;

    if (dialog.find('form').data().mediaEditable != 1) {
      return;
    }

    // Create a new button
    const footer = dialog.find('.button-container');
    const mediaId = dialog.find('form').data().mediaId;
    const widgetId = dialog.find('form').data().widgetId;
    const validExtensions = dialog.find('form').data().validExtensions;

    // Append
    const replaceButton = $('<button type="button" class="btn btn-warning">')
      .html(playlistAddFilesTrans.uploadMessage);
    replaceButton.on('click', function(e) {
      e.preventDefault();

      // Open the upload dialog with our options.
      openUploadForm(
        {
          url: libraryAddUrl,
          title: uploadTrans.uploadMessage,
          animateDialog: false,
          initialisedBy: 'library-upload',
          className: self.namespace.getUploadDialogClassName(),
          templateOptions: {
            multi: false,
            oldMediaId: mediaId,
            widgetId: widgetId,
            updateInAllChecked: uploadFormUpdateAllDefault,
            trans: playlistAddFilesTrans,
            upload: {
              maxSize: $(this).data().maxSize,
              maxSizeMessage: $(this).data().maxSizeMessage,
              validExtensionsMessage:
                translations.validExtensions.replace('%s', validExtensions)
                  .replace(/\|/g, ', '),
              validExt: validExtensions,
            },
            showWidgetDates: false,
            folderSelector: true,
          },
          buttons: {
            main: {
              label: translations.done,
              className: 'btn-primary btn-bb-main',
              callback: function() {
                self.namespace.reloadData(self.mainObject, {
                  refreshEditor: true,
                });
              },
            },
          },
        },
      );
    });

    // Add to the second to last position ( if we have that button )
    if (
      footer.find('button:last').length > 0
    ) {
      footer.find('button:last').before(replaceButton);
    } else {
      // Just add to the footer
      footer.append(replaceButton);
    }
  };

  /**
   * Configure the query builder ( order and filter )
   * @param {object} dialog - Dialog object
   * @param {object} translations - Object with all the translations
   */
  this.configureQueryBuilder = function(dialog, translations) {
    // Order Clause
    const orderClauseFields = $('#orderClause');

    if (orderClauseFields.length == 0) {
      return;
    }

    const orderClauseTemplate = templates.dataSetOrderClauseTemplate;

    const ascTitle = translations.ascTitle;
    const descTitle = translations.descTitle;

    if (dialog.data().extra.orderClause.length == 0) {
      // Add a template row
      const context = {
        columns: dialog.data().extra.columns,
        title: '1',
        orderClause: '',
        orderClauseAsc: '',
        orderClauseDesc: '',
        buttonGlyph: 'fa-plus',
        ascTitle: ascTitle,
        descTitle: descTitle,
      };
      orderClauseFields.append(orderClauseTemplate(context));
    } else {
      // For each of the existing codes, create form components
      let i = 0;
      $.each(dialog.data().extra.orderClause, function(index, field) {
        i++;

        const direction = (field.orderClauseDirection == 'ASC');

        const context = {
          columns: dialog.data().extra.columns,
          title: i,
          orderClause: field.orderClause,
          orderClauseAsc: direction,
          orderClauseDesc: !direction,
          buttonGlyph: ((i == 1) ? 'fa-plus' : 'fa-minus'),
          ascTitle: ascTitle,
          descTitle: descTitle,
        };

        orderClauseFields.append(orderClauseTemplate(context));
      });
    }

    // Nabble the resulting buttons
    orderClauseFields.on('click', 'button', function(e) {
      e.preventDefault();

      // find the gylph
      if ($(this).find('i').hasClass('fa-plus')) {
        const context = {
          columns: dialog.data().extra.columns,
          title: orderClauseFields.find('.form-inline').length + 1,
          orderClause: '',
          orderClauseAsc: '',
          orderClauseDesc: '',
          buttonGlyph: 'fa-minus',
          ascTitle: ascTitle,
          descTitle: descTitle,
        };
        orderClauseFields.append(orderClauseTemplate(context));
      } else {
        // Remove this row
        $(this).closest('.form-inline').remove();
      }
    });

    //
    // Filter Clause
    //
    const filterClauseFields = $('#filterClause');
    const filterClauseTemplate = templates.dataSetFilterClauseTemplate;
    const filterOptions = translations.filterOptions;
    const filterOperatorOptions = translations.filterOperatorOptions;

    if (dialog.data().extra.filterClause.length == 0) {
      // Add a template row
      const context2 = {
        columns: dialog.data().extra.columns,
        filterOptions: filterOptions,
        filterOperatorOptions: filterOperatorOptions,
        title: '1',
        filterClause: '',
        filterClauseOperator: 'AND',
        filterClauseCriteria: '',
        filterClauseValue: '',
        buttonGlyph: 'fa-plus',
      };
      filterClauseFields.append(filterClauseTemplate(context2));
    } else {
      // For each of the existing codes, create form components
      let j = 0;
      $.each(dialog.data().extra.filterClause, function(index, field) {
        j++;

        const context2 = {
          columns: dialog.data().extra.columns,
          filterOptions: filterOptions,
          filterOperatorOptions: filterOperatorOptions,
          title: j,
          filterClause: field.filterClause,
          filterClauseOperator: field.filterClauseOperator,
          filterClauseCriteria: field.filterClauseCriteria,
          filterClauseValue: field.filterClauseValue,
          buttonGlyph: ((j == 1) ? 'fa-plus' : 'fa-minus'),
        };

        filterClauseFields.append(filterClauseTemplate(context2));
      });
    }

    // Nabble the resulting buttons
    filterClauseFields.on('click', 'button', function(e) {
      e.preventDefault();

      // find the gylph
      if ($(this).find('i').hasClass('fa-plus')) {
        const context = {
          columns: dialog.data().extra.columns,
          filterOptions: filterOptions,
          filterOperatorOptions: filterOperatorOptions,
          title: filterClauseFields.find('.form-inline').length + 1,
          filterClause: '',
          filterClauseOperator: 'AND',
          filterClauseCriteria: '',
          filterClauseValue: '',
          buttonGlyph: 'fa-minus',
        };
        filterClauseFields.append(filterClauseTemplate(context));
      } else {
        // Remove this row
        $(this).closest('.form-inline').remove();
      }
    });
  };

  /**
   * Get pre-built template
   * @param {object} templateName - Template name
   * @return {object} Template object
   */
  this.getTemplate = function(templateName) {
    if (templates[templateName] === undefined) {
      console.error(
        'Template ' +
        templateName +
        ' does not exist on formHelpers file!');
    }

    return templates[templateName];
  };

  /**
   * Run after opening the permission form to set up the fields
   * @param {object} dialog - Dialog object
   */
  this.permissionsFormAfterOpen = function(dialog) {
    const grid = $('#permissionsTable', dialog).closest('.XiboGrid');

    const table = $('#permissionsTable', dialog).DataTable({
      language: dataTablesLanguage,
      serverSide: true,
      stateSave: true,
      filter: false,
      searchDelay: 3000,
      order: [[0, 'asc']],
      ajax: {
        url: grid.data().url,
        data: function(d) {
          $.extend(d, grid.find('.permissionsTableFilter form')
            .serializeObject());
        },
      },
      columns: [
        {
          data: 'group',
          render: function(data, type, row, meta) {
            if (type != 'display') {
              return data;
            }
            if (row.isUser == 1) {
              return data;
            } else {
              return '<strong>' + data + '</strong>';
            }
          },
        },
        {
          data: 'view', render: function(data, type, row, meta) {
            if (type != 'display') {
              return data;
            }

            return `<input type="checkbox"
              data-permission="view" data-group-id="` +
              row.groupId + '" ' + ((data == 1) ? 'checked' : '') + ' />';
          },
        },
        {
          data: 'edit', render: function(data, type, row, meta) {
            if (type != 'display') {
              return data;
            }

            return `<input type="checkbox"
              data-permission="edit" data-group-id="` +
              row.groupId + '" ' + ((data == 1) ? 'checked' : '') + ' />';
          },
        },
        {
          data: 'delete', render: function(data, type, row, meta) {
            if (type != 'display') {
              return data;
            }

            return `<input type="checkbox"
              data-permission="delete" data-group-id="` +
              row.groupId + '" ' + ((data == 1) ? 'checked' : '') + ' />';
          },
        },
      ],
    });

    table.on('draw', function(e, settings) {
      dataTableDraw(e, settings);

      // permissions should be an object not an array
      if (grid.data().permissions.length <= 0) {
        grid.data().permissions = {};
      }

      // Bind to the checkboxes change event
      const target = $('#' + e.target.id);
      target.find('input[type=checkbox]').change(function() {
        // Update our global permissions data with this
        const groupId = $(this).data().groupId;
        const permission = $(this).data().permission;
        const value = $(this).is(':checked');
        if (grid.data().permissions[groupId] === undefined) {
          grid.data().permissions[groupId] = {};
        }
        grid.data().permissions[groupId][permission] = (value) ? 1 : 0;
      });
    });
    table.on('processing.dt', dataTableProcessing);

    // Bind our filter
    grid.find(
      '.permissionsTableFilter form input, .permissionsTableFilter form select',
    ).on('change', function() {
      table.ajax.reload();
    });
  };

  /**
  * Run before submitting the permission form to process data
  * @param {object} dialog - Dialog object
  * @return {object} Processed data
  */
  this.permissionsFormBeforeSubmit = function(dialog) {
    const $formContainer = $('.permissions-form', dialog);

    const permissions = {
      groupIds: $('.permissionsGrid', dialog).data().permissions,
      ownerId: $formContainer.find('select[name=ownerId]').val(),
      cascade: $formContainer.find('#cascade').is(':checked'),
    };

    return $.param(permissions);
  };

  /**
   * Renders the formid provided
   * @param {Object} sourceObj
   * @param {Object} data
   * @param {number=} step
   * @return {Object}
   */
  this.widgetFormRender = function(sourceObj, data, step) {
    const self = this;

    let formUrl = '';
    if (typeof sourceObj === 'string' || sourceObj instanceof String) {
      formUrl = sourceObj;
    } else {
      formUrl = sourceObj.attr('href');
    }

    // To fix the error generated by the double click on button
    if (formUrl == undefined) {
      return false;
    }

    // Currently only support one of these at once.
    bootbox.hideAll();

    // Add step to the form url if it exists
    if (step != undefined) {
      formUrl = formUrl.split('?')[0] + '?step=' + step;
    }

    // Call with AJAX
    $.ajax({
      type: 'get',
      url: formUrl,
      cache: false,
      dataType: 'json',
      success: function(response) {
        // Was the Call successful
        if (response.success) {
          // Set the dialog HTML to be the response HTML
          let dialogTitle = '';

          // Is there a title for the dialog?
          if (response.dialogTitle != undefined && response.dialogTitle != '') {
            // Set the dialog title
            dialogTitle = response.dialogTitle;
          }

          const id = new Date().getTime();

          // Create the dialog with our parameters
          const dialog = bootbox.dialog({
            message: response.html,
            title: dialogTitle,
            size: 'large',
            animate: false,
          }).attr('id', id);

          // Store the extra
          dialog.data('extra', response.extra);

          // Buttons
          const buttons = self.widgetFormRenderButtons(response.buttons);

          if (buttons !== '') {
            // Append a footer to the dialog
            const footer = $('<div>').addClass('modal-footer');
            dialog.find('.modal-content').append(footer);

            let i = 0;
            $.each(
              buttons,
              function(index, value) {
                i++;
                const extrabutton =
                  $('<button id="dialog_btn_' + i + '" class="btn">')
                    .html(value.name);

                extrabutton.addClass(value.type);

                extrabutton.attr('id', index);

                extrabutton.click(function(e) {
                  e.preventDefault();

                  self.widgetFormEditAction(dialog,
                    value.action,
                    response.data.module.widget.type,
                    {
                      sourceObj,
                      data,
                      step,
                    });

                  return false;
                });

                footer.append(extrabutton);
              });
          }

          // Focus in the first input
          $('input[type=text]', dialog).eq(0).focus();

          $('input[type=text]', dialog).each(function(index, el) {
            formRenderDetectSpacingIssues(el);

            $(el).on('keyup', _.debounce(function() {
              formRenderDetectSpacingIssues(el);
            }, 500));
          });

          // Check to see if there are any tab actions
          $('a[data-toggle="tab"]', dialog).on('shown.bs.tab', function(e) {
            if ($(e.target).data().enlarge === 1) {
              $(e.target).closest('.modal').addClass('modal-big');
            } else {
              $(e.target).closest('.modal').removeClass('modal-big');
            }
          });

          // Check to see if the current tab has the enlarge action
          $('a[data-toggle="tab"]', dialog).each(function() {
            if (
              $(this).data().enlarge === 1 &&
              $(this).closest('li').hasClass('active')
            ) {
              $(this).closest('.modal').addClass('modal-big');
            }
          });

          // Call Xibo Init for this form
          XiboInitialise('#' + dialog.attr('id'));

          // Do we have to call any functions due to this success?
          if (response.callBack !== '' && response.callBack !== undefined) {
            eval(response.callBack)(dialog);
          }

          // Pass widget options to the form as data
          const widgetOptions = {};
          for (const option in response.data.module.widget.widgetOptions) {
            if (
              response.data.module.widget.widgetOptions.hasOwnProperty(option)
            ) {
              const currOption =
                response.data.module.widget.widgetOptions[option];

              if (currOption.type === 'attrib') {
                widgetOptions[currOption.option] = currOption.value;
              } else if (currOption.type === 'raw') {
                widgetOptions[currOption.option] = JSON.parse(currOption.value);
              }
            }
          }
          dialog.find('form').data('elementOptions', widgetOptions);

          // Store region dimentions to the form
          if (data.regionWidth != undefined && data.regionHeight != undefined) {
            dialog.find('form').data('regionWidth', data.regionWidth);
            dialog.find('form').data('regionHeight', data.regionHeight);
          }

          dialog.data('formEditorOnly', true);

          // Widget after form open specific functions
          self.widgetFormEditAfterOpen(
            dialog,
            response.data.module.widget.type,
          );
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
   * Run before submitting the permission form to process data
   * @param {object} container - Container object containing form
   * @param {object} widgetType - Widget/module type
   */
  this.widgetFormEditAfterOpen = function(container, widgetType) {
    const self = this;

    // Check if form edit open function exists
    if (typeof window[widgetType + '_form_edit_open'] === 'function') {
      window[widgetType + '_form_edit_open'].bind(container)();
    }

    // Handle any popovers.
    container.find('[data-toggle="popover"]').popover();

    // Create copy buttons for text areas
    container.find('textarea').each((key, el) => {
      const $newButton = $('<button/>', {
        html: '<i class="fas fa-copy"></i>',
        type: 'button',
        title: editorsTrans.copyToClipboard,
        'data-container': '.properties-panel',
        class: 'btn btn-sm copyTextAreaButton',
        click: function() {
          const $input = $(el);
          let disabled = false;

          if ($input.attr('disabled') == 'disabled') {
            $input.attr('disabled', false);
            disabled = true;
          }

          // Select the input to copy
          let hasNoneClass = false;
          const wasHidden = !$input.is(':visible');
          if ($input.hasClass('d-none')) {
            $input.removeClass('d-none');
            hasNoneClass = true;
          } else if (wasHidden) {
            $input.show();
          }
          $input.trigger('focus');
          $input.trigger('select');

          // Try to copy to clipboard and give feedback
          try {
            const success = document.execCommand('copy');
            if (success) {
              $newButton.trigger('copied', [editorsTrans.copied]);
            } else {
              $newButton.trigger('copied', [editorsTrans.couldNotCopy]);
            }
          } catch (err) {
            $newButton.trigger('copied', [editorsTrans.couldNotCopy]);
          }

          // Unselect the input
          $input.trigger('focus');
          $input.trigger('blur');
          if (hasNoneClass) {
            $input.addClass('d-none');
          } else if (wasHidden) {
            $input.hide();
          }

          // Restore disabled if existed
          if (disabled) {
            $input.attr('disabled', true);
          }
        },
      }).tooltip();

      // Handler for updating the tooltip message.
      $newButton.bind('copied', function(event, message) {
        const $self = $(this);
        $self.tooltip('hide')
          .attr('data-original-title', message)
          .tooltip('show');

        setTimeout(function() {
          $self.tooltip('hide')
            .attr('data-original-title', editorsTrans.copyToClipboard);
        }, 1000);
      });

      // Get button container
      $buttonContainer =
        $(el).parents('.xibo-form-input').find('.text-area-buttons');

      // Add button to the button container for the text area
      $buttonContainer.append($newButton);
    });

    // Create buttons for rich text areas
    container.find('textarea.rich-text').each((key, el) => {
      const $input = $(el);
      const $container = $input.closest('.form-group');
      const $editorMainContainer = $(el).parents('.rich-text-main-container');
      const $editorContainer = $(el).parents('.rich-text-container');
      const $propertiesPanelContainer =
        $container.parents('.properties-panel-container');

      const scaleEditorToContainer = function(
        $editor, option = '',
      ) {
        const $containerWrapper =
          $editor.parents('.rich-text-container-wrapper');
        const regionWidth = Number($editor.data('regionWidth'));
        const regionHeight = Number($editor.data('regionHeight'));
        const containerWidth = $containerWrapper[0].clientWidth;
        const containerHeight = $containerWrapper[0].clientHeight;

        // Scale to container
        let newScale;
        if (option === 'width') {
          // Extra margin to compensate for the scroll bar width
          const extraMargin = CKEDITOR_MARGIN + CKEDITOR_SCROLLBAR_MARGIN;
          newScale = (containerWidth - extraMargin) / regionWidth;
        } else if (option === 'height') {
          newScale = (containerHeight - CKEDITOR_MARGIN) / regionHeight;
        } else {
          newScale = Math.min(
            (containerWidth - CKEDITOR_MARGIN) / regionWidth,
            (containerHeight - CKEDITOR_MARGIN) / regionHeight,
          );
        }

        $editor.css('transform', 'scale(' + newScale + ')');
        $editor.data('currentScale', newScale);

        // Adjust overlay
        $('.cke_textarea_inline')
          .css(
            'outline-width',
            (CKEDITOR_OVERLAY_WIDTH / newScale),
          );
      };

      // Button to view source code
      const $viewSourceButton = $('<button/>', {
        html: '<i class="fas fa-code"></i>',
        type: 'button',
        title: editorsTrans.viewSource,
        placement: 'right',
        'data-container': '.properties-panel',
        class: 'btn btn-sm mr-auto viewSourceButton',
        click: function() {
          const textAreaId = $editorMainContainer.find('textarea').attr('id');

          // Toggle source class
          $editorMainContainer.toggleClass('source');

          // If we turn off source, set value to ckeditor
          if (!$editorMainContainer.hasClass('source')) {
            // Restore text editor
            self.setupCKEditor(
              container,
              textAreaId,
              true,
              null,
              false,
              true,
            ).then(function(res) {
              const $editor = $editorMainContainer.find('.cke_textarea_inline');
              scaleEditorToContainer($editor);
            });
          } else {
            // Destroy CKEditor
            CKEDITOR.instances[textAreaId].destroy();
          }
        },
      }).tooltip({
        trigger: 'hover',
      });

      // Button to detach editor
      const $detachButton = $('<button/>', {
        html: '<i class="fas fa-expand-arrows-alt"></i>',
        type: 'button',
        title: editorsTrans.detachEditor,
        'data-container': '.properties-panel',
        class: 'btn btn-sm detachEditorButton',
        click: function() {
          const $detachButton = $container.find('.detachEditorButton');
          const $attachButton = $container.find('.attachEditorButton');
          const $editor = $editorMainContainer.find('.cke_textarea_inline');

          // Save properties panel original Z-index to data
          if ($propertiesPanelContainer.length > 0) {
            $propertiesPanelContainer.data(
              'originalZindex',
              $propertiesPanelContainer.css('z-index'),
            );

            // Set properties panel z-index to auto
            $propertiesPanelContainer.css('z-index', 'auto');
          }

          // Create overlay
          const $customOverlay = $('.custom-overlay:first').clone();
          $customOverlay
            .attr('id', 'richTextDetachedOverlay')
            .appendTo($propertiesPanelContainer);
          $customOverlay
            .show();

          // Add class to playlist modal if exists
          $container.parents('.editor-modal')
            .addClass('source-editor-opened', true);

          // Create temporary container with editor dimensions
          $('<div/>', {
            class: 'rich-text-temp-container',
            css: {
              width: $editorMainContainer.width(),
              height: $editorMainContainer.height(),
            },
          }).appendTo($container);

          // if click on overlay, reattach editor
          $customOverlay.on('click', function() {
            $attachButton.trigger('click');
          });

          // Detach editor
          $editorMainContainer.addClass('detached');
          $detachButton.addClass('d-none');
          $attachButton.removeClass('d-none');

          // Recalculate scale
          if ($editor.length > 0) {
            scaleEditorToContainer($editor);
          }
        },
      }).tooltip({
        trigger: 'hover',
      });

      // Button to attach editor
      const $attachButton = $('<button/>', {
        html: '<i class="fas fa-compress-arrows-alt"></i>',
        type: 'button',
        title: editorsTrans.attachEditor,
        'data-container': '.properties-panel',
        class: 'btn btn-sm attachEditorButton d-none',
        click: function() {
          const $detachButton = $container.find('.detachEditorButton');
          const $attachButton = $container.find('.attachEditorButton');
          const $editor = $editorMainContainer.find('.cke_textarea_inline');

          // Restore properties panel original Z-index from data
          if ($propertiesPanelContainer.length > 0) {
            const originalZindex =
              $propertiesPanelContainer.data('originalZindex');

            // Set properties panel z-index to auto
            $propertiesPanelContainer.css('z-index', originalZindex);
          }

          // Remove temporary container
          $container.find('.rich-text-temp-container').remove();

          // Remove overlay
          $('#richTextDetachedOverlay').remove();

          // Remove class from playlist modal if exists
          $container.parents('.editor-modal')
            .removeClass('source-editor-opened');

          // Attach editor
          $editorMainContainer.removeClass('detached');
          $detachButton.removeClass('d-none');
          $attachButton.addClass('d-none');

          // Recalculate scale
          if ($editor.length > 0) {
            scaleEditorToContainer($editor);
          }
        },
      }).tooltip({
        trigger: 'hover',
      });

      // Zoom buttons
      const $zoomInButton = $('<button/>', {
        html: '<i class="fa fa-search-plus"></i>',
        type: 'button',
        title: editorsTrans.zoomInEditor,
        'data-container': '.properties-panel',
        class: 'btn btn-sm zoomButton zoomInEditorButton',
        click: function() {
          const $editor = $editorMainContainer.find('.cke_textarea_inline');
          const editorScale = Number($editor.data('currentScale'));
          const newScale = (editorScale * 1.2);
          $editor.css('transform', 'scale(' + newScale + ')');
          $editor.data('currentScale', newScale);
        },
      }).tooltip({
        trigger: 'hover',
      });

      const $zoomOutButton = $('<button/>', {
        html: '<i class="fa fa-search-minus"></i>',
        type: 'button',
        title: editorsTrans.zoomOutEditor,
        'data-container': '.properties-panel',
        class: 'btn btn-sm zoomButton zoomOutEditorButton',
        click: function() {
          const $editor = $editorMainContainer.find('.cke_textarea_inline');
          const editorScale = Number($editor.data('currentScale'));
          const newScale = (editorScale / 1.2);
          $editor.css('transform', 'scale(' + newScale + ')');
          $editor.data('currentScale', newScale);
        },
      }).tooltip({
        trigger: 'hover',
      });

      const $resetZoomButton = $('<button/>', {
        html: '<i class="fas fa-ruler-combined"></i>',
        type: 'button',
        title: editorsTrans.scaleToContainer,
        'data-container': '.properties-panel',
        class: 'btn btn-sm zoomButton scaleToContainer',
        click: function() {
          const $editor = $editorMainContainer.find('.cke_textarea_inline');
          scaleEditorToContainer($editor);
        },
      }).tooltip({
        trigger: 'hover',
      });

      const $scaleToWidth = $('<button/>', {
        html: '<i class="fas fa-ruler-horizontal"></i>',
        type: 'button',
        title: editorsTrans.scaleToWidth,
        'data-container': '.properties-panel',
        class: 'btn btn-sm zoomButton scaleToWidth',
        click: function() {
          const $editor = $editorMainContainer.find('.cke_textarea_inline');
          scaleEditorToContainer($editor, 'width');
        },
      }).tooltip({
        trigger: 'hover',
      });

      const $scaleToHeight = $('<button/>', {
        html: '<i class="fas fa-ruler-vertical"></i>',
        type: 'button',
        title: editorsTrans.scaleToHeight,
        'data-container': '.properties-panel',
        class: 'btn btn-sm zoomButton scaleToHeight',
        click: function() {
          const $editor = $editorMainContainer.find('.cke_textarea_inline');
          scaleEditorToContainer($editor, 'height');
        },
      }).tooltip({
        trigger: 'hover',
      });

      // Add same background colour to editor container as the layout's
      const backgroundColor =
      (
        self.mainObject != undefined &&
        typeof self.mainObject.backgroundColor != 'undefined' &&
        self.mainObject.backgroundColor != null
      ) ?
        self.mainObject.backgroundColor :
        self.defaultBackgroundColor;
      $editorContainer.parent().css('background-color', backgroundColor);

      // Get button container
      $buttonContainer =
        $(el).parents('.xibo-form-input').find('.text-area-buttons');

      // View source button
      $buttonContainer.prepend($viewSourceButton);

      // Add zoom buttons
      $buttonContainer.append($resetZoomButton);
      $buttonContainer.append($scaleToWidth);
      $buttonContainer.append($scaleToHeight);
      $buttonContainer.append($zoomInButton);
      $buttonContainer.append($zoomOutButton);

      // Add detach and attach buttons to container for the text area
      $buttonContainer.append($detachButton);
      $buttonContainer.append($attachButton);

      // Handle region controls
      if (
        self.mainObject.type != 'layout' &&
        !self.namespace.inline &&
        $editorMainContainer.find('.rich-text-dimensions-control').length === 0
      ) {
        // Add to container
        $buttonContainer
          .after(templates.editorRegionControls({
            trans: propertiesPanelTrans,
            dimensions: self.defaultRegionDimensions,
          }));

        ['width', 'height'].forEach((dimension) => {
          const $dimensionControl =
            $editorMainContainer.find('.text-editor-' + dimension);
          const targetName = (dimension == 'width') ?
            'regionWidth' : 'regionHeight';

          // Handle input change
          $dimensionControl.on('focusout', () => {
            const $editor = $editorMainContainer.find('.cke_textarea_inline');
            const dataValue = $editor.data(targetName);
            // If the value was updated
            if (
              $dimensionControl.val() !=
              dataValue
            ) {
              // Update data
              $editor.data(targetName, $dimensionControl.val());

              // Update dimension
              $editor.css(dimension, $dimensionControl.val());

              scaleEditorToContainer($editor, '');
            }
          });
        });
      }
    });
  };

  /**
   * Run before submitting the permission form to process data
   */
  this.widgetFormEditBeforeSubmit = function() {
    // Update CKEditor instances
    this.updateCKEditor();
  };

  /**
   * Run before submitting the permission form to process data
   * @param {object} container - Container object containing form
   * @param {string} actionType
   *   - Type of action ( default type or a function call )
   * @param {string} widgetType - Widget type
   * @param {object=} options
   */
  this.widgetFormEditAction = function(
    container,
    actionType,
    widgetType,
    options = {},
  ) {
    switch (actionType) {
      case 'save':
        this.widgetFormEditSubmit(container, widgetType);
        break;

      case 'close':
        container.modal('hide');
        break;

      default:
        if (typeof window[actionType] === 'function') {
          window[actionType](container);
        }
        break;
    }
  };

  /**
   * Submit form
   * @param {object} container - Container object containing form
   * @param {string} widgetType - Widget type
   * @return {boolean} - True if form was submitted
   */
  this.widgetFormEditSubmit = function(container, widgetType) {
    const self = this;

    const changeSaveButtonState = function(disable = true) {
      if (disable) {
        // Disable the button ( Fix https://github.com/xibosignage/xibo/issues/1467)
        container.find('#save')
          .append('<span class="saving fa fa-cog fa-spin"></span>');
        container.find('#save').attr('disabled', 'disabled');
      } else {
        // Re-enable the button
        container.find('#save .saving').remove();
        container.find('#save').attr('disabled', null);
      }
    };

    changeSaveButtonState();

    this.widgetFormEditBeforeSubmit(container, widgetType);

    const $form = container.find('form');

    if ($form.valid()) {
      // Get the URL from the action part of the form)
      const url = $form.attr('action');

      $.ajax({
        type: $form.attr('method'),
        url: url,
        cache: false,
        dataType: 'json',
        data: $form.serialize(),
        success: function(response) {
          changeSaveButtonState(false);

          // success
          if (response.success) {
            if (response.message != '') {
              SystemMessage(response.message, true);
            }

            bootbox.hideAll();

            XiboRefreshAllGrids();

            if ($form.data('nextFormUrl') != undefined) {
              self.widgetFormRender($form.data().nextFormUrl
                .replace(':id', response.id));
            }
          } else {
            // Why did we fail?
            if (response.login) {
              // We were logged out
              LoginBox(response.message);
            } else {
              // Likely just an error that we want to report on
              SystemMessageInline(response.message, $form.closest('.modal'));
            }
          }
        },
        error: function(xhr, textStatus, errorThrown) {
          SystemMessage(xhr.responseText, false);
        },
      });

      return false;
    } else {
      changeSaveButtonState(false);
    }
  };

  /**
   * Get buttons from twig and generate a buttons object
   * @param {object} inputButtons - Buttons from twig file
   * @return {object} - Buttons object
   */
  this.widgetFormRenderButtons = function(inputButtons) {
    const buttons = {};

    // Process buttons from result
    for (const button in inputButtons) {
      // If button is not a cancel or save button, add it to the button object
      if (
        !(inputButtons[button].includes('XiboDialogClose') ||
          inputButtons[button].includes('.submit()'))
      ) {
        buttons[button] = {
          name: button,
          type: 'btn-white',
          click: inputButtons[button],
        };
      }
    }

    // Add delete button
    buttons.delete = {
      name: editorsTrans.delete,
      type: 'btn-danger',
      action: 'delete',
    };

    // Add back button
    buttons.back = {
      name: editorsTrans.back,
      type: 'btn-white',
      action: 'back',
    };

    // Add save button
    buttons.save = {
      name: translations.save,
      type: 'btn-info',
      action: 'save',
    };

    return buttons;
  };

  /**
   *  We need to convert any library references [123] to their
   * full URL counterparts we leave well alone non-library references.
   * @param {string} data - Data string to be processed
   * @return {string} - Processed data string
   */
  this.convertLibraryReferences = function(data) {
    const regex = /\[[0-9]+]/gi;

    data = data.replace(regex, function(match) {
      const inner = match.replace(']', '').replace('[', '');
      return CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(':id', inner);
    });

    return data;
  };

  /**
  *  We need to revert all library links to references [123]
  * @param {string} data - Data string to be processed
  * @return {string} - Processed data string
  */
  this.revertLibraryReferences = function(data) {
    String.prototype.replaceAll = function(search, replacement) {
      const target = this;
      return target.split(search).join(replacement);
    };

    const urlSplit = CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.split(':id');

    data = data.replaceAll(urlSplit[0], '[');
    data = data.replaceAll(urlSplit[1], ']');

    return data;
  };

  this.setupPhpDateFormatPopover = function($dialog) {
    const phpDateFormatTable =
      Handlebars.compile($('#php-date-format-table').html());
    $dialog.find('form .date-format-table').popover({
      content: phpDateFormatTable,
      html: true,
      placement: 'bottom',
      sanitize: false,
      trigger: 'manual',
      container: $dialog.find('form'),
    }).on('mouseenter', function() {
      $(this).popover('show');
      $('.popover').on('mouseleave', function() {
        $(this).popover('hide');
      });
    }).on('mouseleave', function() {
      setTimeout(function() {
        if (!$('.popover:hover').length) {
          $(this).popover('hide');
        }
      }, 300);
    });
  };

  /**
   * Validate required form input fields
   * @param {object} container - Form object
   * @return {object|null} errors
   */
  this.validateFormBeforeSubmit = function(container) {
    const errors = {};
    $(container).find('.xibo-form-input[data-is-required]')
      .each(function(_idx, el) {
        let inputField = null;
        let fieldType = null;

        if ($(el).find('input').length) {
          inputField = $(el).find('> input');
          fieldType = inputField.attr('type');
        } else if ($(el).find('> select').length) {
          inputField = $(el).find('> select');
          fieldType = 'select';
        }

        const errorMessage = errorMessagesTrans.requiredField.replace(
          '%property%',
          inputField.siblings('label').html(),
        );

        if (fieldType === 'text' || fieldType === 'number') {
          if (inputField.val().length === 0) {
            errors[inputField.attr('name')] = errorMessage;
          }
        } else if (fieldType === 'checkbox') {
          if (!inputField.is(':checked')) {
            errors[inputField.attr('name')] = errorMessage;
          }
        } else if (fieldType === 'select') {
          if (inputField.val() === null ||
            inputField.val()?.length == undefined ||
            inputField.val()?.length === 0
          ) {
            errors[inputField.attr('name')] = errorMessage;
          }
        }
      });

    if (Object.keys(errors).length > 0) {
      return errors;
    }

    return null;
  };
};


module.exports = new formHelpers();
