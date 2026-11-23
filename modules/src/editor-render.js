/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */
$(function() {
  // Get a message from the parent window
  // RUN ON IFRAME
  window.onmessage = function(e) {
    if (
      e.data.method == 'renderContent'
    ) {
      // Update global options for the widget
      globalOptions.originalWidth = e.data.options.originalWidth;
      globalOptions.originalHeight = e.data.options.originalHeight;

      // Set the pause state for animation to false
      // To start right after the render effects are generated
      globalOptions.pauseEffectOnStart =
        e.data.options.pauseEffectOnStart ?? false;

      // Arguments for both reRender
      const args = (typeof widget != 'undefined') ? [
        e.data.options.id, // id
        $('body'), // target
        widget.items, // items
        Object.assign(widget.properties, globalOptions), // properties
        widget.meta, // meta
      ] : [];

      // Call render array of functions if exists and it's an array
      if (window.renders && Array.isArray(window.renders)) {
        window.renders.forEach((render) => {
          render(...args);
        });
      }
    }
  };

  // Post a message to say we're loaded
  window.parent.postMessage({type: 'loaded'}, '*');
});
