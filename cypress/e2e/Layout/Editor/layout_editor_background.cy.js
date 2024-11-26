/* eslint-disable max-len */
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

describe('Layout Editor', function() {
  beforeEach(function() {
    cy.login();
    cy.visit('/layout/view');
    cy.get('button.layout-add-button').click();
    cy.get('#layout-viewer').should('be.visible'); // Assert that the URL has changed to the layout editor
  });

  it('should update the background according to the colour set via colour picker', function() {
    cy.get('#properties-panel').should('be.visible'); // Verify properties panel is present
    cy.get('.input-group-prepend').click(); // Open colour picker
    cy.get('.colorpicker-saturation').click(68, 28); // Select on a specific saturation
    cy.get('#properties-panel').click(30, 60); // Click outside color picker to close
    // Verify the selected color is applied to the background
    cy.get('#layout-viewer').should('have.css', 'background-color', 'rgb(243, 248, 255)');
  });

  it('should update the background according to the colour set via hex input', function() {
    cy.get('#properties-panel').should('be.visible');
    cy.get('#input_backgroundColor').clear().type('#b53939{enter}');
    // Verify the selected color is applied to the background
    cy.get('#layout-viewer')
      .should('have.css', 'background-color', 'rgb(243, 248, 255)');
  });

  it('should update the layout resolution', function() {
    cy.get('#properties-panel').should('be.visible');
    const resName='cinema';
    cy.get('#input_resolutionId')
      .parent()
      .find('.select2-selection')
      .click();
    cy.get('.select2-container--open input[type="search"]')
      .type(resName);
    cy.selectOption(resName);
    cy.get('.layout-info-dimensions span')
      .should('be.visible')
      .and('contain', '4096x2304');
  });
});
