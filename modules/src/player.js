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

    // if we have data on the widget (for older players),
    // or if we are not in preview and have empty data on Widget (like text)
    // do not run ajax use that data instead
    if ((widget.data.data !== undefined && widget.data.data.length > 0)
      || (widget.data.length == 0 && !isPreview)
    ) {
      initPlayer(widget.data)
    } else {
      // else get data from widget.url,
      // this will be either getData for preview
      // or new json file for v4 players
      $.ajax({
        method: 'GET',
        url: widget.url,
      }).done(function(data) {
        initPlayer(data)
      }).fail(function(jqXHR, textStatus, errorThrown) {
        console.log(jqXHR, textStatus, errorThrown);
      });
    }

    function initPlayer(data) {
      const $target = $('body');
      let dataItems = [];
      widget.items = [];
      const isArray = Array.isArray(data);

      // If the request failed, and we're in preview, show the error message
      if (!isArray && data.success === false && isPreview) {
        $target.append(
          '<div class="error-message" role="alert">' +
          data.message +
          '</div>');
      } else if (
        !isArray &&
        data.data !== undefined &&
        data.data.length === 0 &&
        widget.sample &&
        isPreview
      ) {
        // If data is empty, use sample data instead
        // Add single element or array of elements
        dataItems = (Array.isArray(widget.sample)) ?
          widget.sample.slice(0) : [widget.sample];
      } else if (
        !isArray &&
        data.data !== undefined &&
        data.data.length > 0
      ) {
        // Add items to the widget
        dataItems = data.data;
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
      $.each(window.renders, function(_key, render) {
        render(
          widget.widgetId,
          $target,
          widget.items,
          Object.assign(widget.properties, globalOptions),
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
  });
});
