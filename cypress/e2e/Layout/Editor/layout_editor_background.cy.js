/*
 * Copyright (C) 2025 Xibo Signage Ltd
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

/* eslint-disable max-len */
describe('Layout Editor Background', function() {
  const SELECTORS = {
    layoutAddButton: 'button.layout-add-button',
    layoutViewer: '#layout-viewer',
    propertiesPanel: '#properties-panel',
    colorPickerTrigger: '.input-group-prepend',
    colorPickerSaturation: '.colorpicker-saturation',
    backgroundColorInput: '#input_backgroundColor',
    backgroundzIndex: '#input_backgroundzIndex',
    resolutionDropdown: '#input_resolutionId',
    select2Selection: '.select2-selection',
    select2SearchInput: '.select2-container--open input[type="search"]',
    layoutInfoDimensions: '.layout-info-dimensions span',
  };

  beforeEach(function() {
    cy.login();
    cy.visit('/layout/view');
    cy.get(SELECTORS.layoutAddButton).click();
    cy.get(SELECTORS.layoutViewer).should('be.visible'); // Assert that the URL has changed to the layout editor
  });

  it('should update the background according to the colour set via colour picker', function() {
    cy.get(SELECTORS.propertiesPanel).should('be.visible'); // Verify properties panel is present
    cy.get(SELECTORS.colorPickerTrigger).click(); // Open colour picker
    cy.get(SELECTORS.colorPickerSaturation).click(68, 28); // Select on a specific saturation
    cy.get(SELECTORS.propertiesPanel).click(30, 60); // Click outside color picker to close

    // Verify the selected color is applied to the background
    cy.get(SELECTORS.layoutViewer).should('have.css', 'background-color', 'rgb(243, 248, 255)');
  });

  it('should update the background according to the colour set via hex input', function() {
    cy.get(SELECTORS.propertiesPanel).should('be.visible');
    cy.get(SELECTORS.backgroundColorInput).clear().type('#b53939{enter}');

    // Verify the selected color is applied to the background
    cy.get(SELECTORS.layoutViewer).should('have.css', 'background-color', 'rgb(243, 248, 255)');
  });

  it('should update the layer according to the input', function() {
    cy.get(SELECTORS.propertiesPanel).should('be.visible');
    cy.get(SELECTORS.backgroundzIndex).clear().type('1{enter}');

    // Verify the selected number is applied to the layer
    cy.get(SELECTORS.backgroundzIndex).should('have.value', '1');
  });

  // This is failing and a bug reported
  it('should update the layout resolution', function() {
    cy.get(SELECTORS.propertiesPanel).should('be.visible');
    const resName = 'cinema';

    cy.get(SELECTORS.resolutionDropdown).parent().find(SELECTORS.select2Selection).click();
    cy.get(SELECTORS.select2SearchInput).type(resName);
    cy.selectOption(resName);

    cy.get(SELECTORS.layoutInfoDimensions)
      .should('be.visible')
      .and('contain', '4096x2304');
  });
});