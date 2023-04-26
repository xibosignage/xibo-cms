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
$(function() {
  // Check the query params to see if we're in editor mode
  const urlParams = new URLSearchParams(window.location.search);
  const isPreview = urlParams.get('preview') === '1';

  // Defaut scaler function
  const defaultScaler = function(
    _id,
    target,
    _items,
    properties,
  ) {
    // If target is not defined, use the body
    target = (target) ? target : $('body');

    // If properties is empty
    // use the global options with widget properties
    properties = (properties) ?
      properties : Object.assign(widget.properties, globalOptions);

    // Scale the content if there's no scaleContent property
    // or if it's set to true
    if (
      !properties.hasOwnProperty('scaleContent') ||
      properties.scaleContent
    ) {
      // Scale the content
      $(target).xiboLayoutScaler(properties);
    }
  };

  const macroRegex = /^%(\+|\-)[0-9]([0-9])?(d|h|m|s)%$/gi;

  const composeUTCDateFromMacro = (macroStr) => {
    const utcFormat = 'YYYY-MM-DDTHH:mm:ssZ';
    const dateNow = moment().utc();
    // Check if input has the correct format
    const dateStr = String(macroStr);

    if (dateStr.length === 0 ||
        dateStr.match(macroRegex) === null
    ) {
      return dateNow.format(utcFormat);
    }

    // Trim the macro date string
    const dateOffsetStr = dateStr.replaceAll('%', '');
    const params = (op) => dateOffsetStr.replace(op, '')
      .split(/(\d+)/).filter(Boolean);
    const addRegex = /^\+/g;
    const subtractRegex = /^\-/g;

    // Check if it's add or subtract offset and return composed date
    if (dateOffsetStr.match(addRegex) !== null) {
      return dateNow.add(...params(addRegex)).format(utcFormat);
    } else if (dateOffsetStr.match(subtractRegex) !== null) {
      return dateNow.subtract(...params(subtractRegex)).format(utcFormat);
    }
  };
  const elementsWidget = {};

  /**
   * Compose final data
   * @param {object} widget
   * @param {object|array} data
   * @return {object}
   */
  function composeFinalData(widget, data) {
    let dataItems = [];
    let isSampleData = false;
    const isArray = Array.isArray(data);

    // If the request failed, and we're in preview, show the error message
    if ((!widget.isValid ||
        (!isArray && data.success === false)
    ) && isPreview) {
      if (widget.sample) {
        isSampleData = true;
        // If data is empty, use sample data instead
        // Add single element or array of elements
        dataItems = (Array.isArray(widget.sample)) ?
          widget.sample.slice(0) : [widget.sample];

        dataItems = dataItems.reduce((data, item) => {
          Object.keys(item).forEach((itemKey) => {
            if (String(item[itemKey]).match(macroRegex) !== null) {
              item[itemKey] = composeUTCDateFromMacro(item[itemKey]);
            }
          });

          return [...data, {...item}];
        }, []);
      }
    } else {
      dataItems = data?.data || [];
    }

    return {
      isSampleData,
      dataItems,
      isArray,
    };
  }

  /**
   * Get widget data
   * @param {object} widget
   * @return {Promise}
   */
  function getWidgetData(widget) {
    return new Promise(function(resolve) {
      // if we have data on the widget (for older players),
      // or if we are not in preview and have empty data on Widget (like text)
      // do not run ajax use that data instead
      if (
        (widget.data.data !== undefined && widget.data.data.length > 0) ||
        (widget.data.length == 0 && !isPreview)
      ) {
        resolve(widget.data);
      } else {
        // else get data from widget.url,
        // this will be either getData for preview
        // or new json file for v4 players
        $.ajax({
          method: 'GET',
          url: widget.url,
        }).done(function(data) {
          resolve(data);
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.log(jqXHR, textStatus, errorThrown);
        });
      }
    });
  }

  /**
   * Initialize the player
   * @param {object} $template
   * @param {object} $content
   * @param {boolean} moduleTemplate
   * @param {object} hbs
   * @param {object} widget
   * @param {*} data
   */
  function initPlayer(
    $template,
    $content,
    moduleTemplate,
    hbs,
    widget,
    data,
  ) {
    widget.items = [];
    const $target = $('body');
    const {
      isArray,
      isSampleData,
      dataItems,
    } = composeFinalData(widget, data);

    if (isSampleData) {
      $target.append(
        '<div class="error-message" role="alert">' +
        data.message +
        '</div>');
    }

    // Add meta to the widget if it exists
    if (!isArray && data.meta) {
      widget.meta = data.meta;
    }

    // Run the onInitialize function if it exists
    if (typeof window['onInitialize_' + widget.widgetId] === 'function') {
      window['onInitialize_' + widget.widgetId](
        widget.widgetId,
        $target,
        widget.properties,
        widget.meta,
      );
    }

    // For each data item, parse it and add it to the content
    let templateAlreadyAdded = false;
    $.each(dataItems, function(_key, item) {
      // Parse the data if there is a parser function
      if (typeof window['onParseData_' + widget.widgetId] === 'function') {
        item = window[
          'onParseData_' + widget.widgetId
        ](item, widget.properties, widget.meta);
      }

      // Add the item to the content
      (hbs) && $content.append(hbs(item));

      // Add items to the widget object
      (item) && widget.items.push(item);

      // IF we added item template
      templateAlreadyAdded = true;
    });

    // If we don't have dataType, or we have a module template
    // add it to the content with widget properties and global options
    if (moduleTemplate && hbs && !templateAlreadyAdded) {
      $content.append(hbs(
        Object.assign(widget.properties, globalOptions),
      ));
    }

    // Save template height and width if exists to global options
    if ($template && $template.length > 0) {
      globalOptions.widgetDesignWidth = $template.data('width');
      globalOptions.widgetDesignHeight = $template.data('height');
      globalOptions.widgetDesignGap = $template.data('gap');
    }

    // Save template properties to widget properties
    for (const key in widget.templateProperties) {
      if (widget.templateProperties.hasOwnProperty(key)) {
        widget.properties[key] = widget.templateProperties[key];
      }
    }

    // Run the onRender function if it exists
    if (typeof window['onRender_' + widget.widgetId] === 'function') {
      window.onRender =
        window['onRender_' + widget.widgetId];
    }

    // Handle the rendering of the template
    if (
      typeof window['onTemplateRender_' + widget.templateId] === 'function'
    ) { // Custom scaler
      window.onTemplateRender =
        window['onTemplateRender_' + widget.templateId];
    }

    // Create global render array of functions
    window.renders = [];
    // Template render function
    if (window.onTemplateRender) {
      // Save the render method in renders
      window.renders.push(window.onTemplateRender);
    }

    // Module render function
    if (window.onRender) {
      // Save the render method in renders
      window.renders.push(window.onRender);
    }

    // If there's no elements in renders, use the default scaler
    if (window.renders.length === 0) {
      window.renders.push(defaultScaler);
    }

    // Run all render functions
    const optionsForRendering = Object.assign(
      widget.properties,
      globalOptions,
      {
        duration: widget.duration,
      },
    );
    $.each(window.renders, function(_key, render) {
      render(
        widget.widgetId,
        $target,
        widget.items,
        optionsForRendering,
        widget.meta,
      );
    });

    // Save widget as global variable
    window.widget = widget;

    // Call the run on visible function if it exists
    if (
      typeof window['onVisible_' + widget.widgetId] === 'function'
    ) {
      window.runOnVisible = function() {
        window['onVisible_' + widget.widgetId](
          widget.widgetId,
          $target,
          widget.items,
          widget.properties,
          widget.meta,
        );
      };
      if (xiboIC.checkVisible()) {
        window.runOnVisible();
      } else {
        xiboIC.addToQueue(window.runOnVisible);
      }
    }

    // Lock all interactions
    xiboIC.lockAllInteractions();
  }

  // Call the data url and parse out the template.
  $.each(widgetData, function(_key, widget) {
    // Check if we have template from templateId or module
    // and set it as the template
    let $template = null;
    let moduleTemplate = false;
    if ($('#hbs-' + widget.templateId).length > 0) {
      $template = $('#hbs-' + widget.templateId);
    } else if ($('#hbs-module').length > 0) {
      $template = $('#hbs-module');
      moduleTemplate = true;
    }

    // Save widgetData to xic
    xiboIC.set(widget.widgetId, 'widgetData', widget);

    let hbs = null;
    // Compile the template if it exists
    if ($template && $template.length > 0) {
      hbs = Handlebars.compile($template.html());
    }

    const $content = $('#content');

    // Get widget with templateId = elements
    if (widget.templateId === 'elements') {
      elementsWidget[widget.widgetId] = widget;
    }

    getWidgetData(widget).then(function(data) {
      initPlayer(
        $template,
        $content,
        moduleTemplate,
        hbs,
        widget,
        data,
      );
    });
  });

  // Parse out the template with elements.
  $.each(elements, function(_key, widgetElements) {
    if (widgetElements?.length > 0) {
      const $target = $('body');
      const $content = $('#content');

      $.each(widgetElements, function(_widgetElemKey, widgetElement) {
        if (widgetElement?.elements?.length > 0) {
          $.each(widgetElement.elements, function(_elemKey, element) {
            const elementCopy = JSON.parse(JSON.stringify(element));

            if (Object.keys(elementCopy).length > 0 &&
              elementCopy.hasOwnProperty('properties')
            ) {
              delete elementCopy.properties;
            }

            // Check if we have template from templateId or module
            // and set it as the template
            let $template = null;
            const templateSelector = `#hbs-${elementCopy.id}`;
            if ($(templateSelector).length > 0) {
              $template = $(templateSelector);
            }

            let hbs = null;

            // Compile the template if it exists
            if ($template && $template.length > 0) {
              hbs = Handlebars.compile($template.html());
            }

            const elementProperties = element?.properties || {};
            const renderElement = (data) => {
              const hbsTemplate = hbs(
                Object.assign(data, globalOptions),
              );

              $content.append($(hbsTemplate).first()
                .attr('id', data.elementId)
                .addClass(`${data.id}--item`)
                .css({
                  height: data.height,
                  width: data.width,
                  position: 'absolute',
                  top: data.top,
                  left: data.left,
                  'z-index': data.layer,
                }).prop('outerHTML'));
            };
            const templateData = Object.assign(
              {}, elementProperties, elementCopy, globalOptions,
            );

            // Get widget info if exists.
            if (Object.keys(elementsWidget).length > 0 &&
              elementsWidget.hasOwnProperty(widgetElement.widgetId)
            ) {
              const widgetInfo = elementsWidget[widgetElement.widgetId];
              const renderData = Object.assign(
                widgetInfo.properties,
                globalOptions,
                {
                  duration: widgetInfo.duration,
                  marqueeInlineSelector: `.${templateData.id}--item`,
                },
              );
              getWidgetData(widgetInfo)
                .then(function(data) {
                  const {
                    dataItems,
                  } = composeFinalData(widgetInfo, data);

                  // For each data item, parse it and add it to the content;
                  let templateAlreadyAdded = false;
                  $.each(dataItems, function(_key, item) {
                    (hbs) && renderElement(Object.assign(templateData, item));

                    templateAlreadyAdded = true;
                  });

                  if (templateAlreadyAdded) {
                    $target.xiboElementsRender(
                      renderData,
                      $content.find(`.${templateData.id}--item`),
                    );
                  }
                });
            } else {
              (hbs) && renderElement(templateData);
            }
          });
        }
      });
    }
  });
});
