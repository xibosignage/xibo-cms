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

/* eslint-disable max-len */
describe('Layout Designer', function() {
  beforeEach(function() {
    cy.login();
  });

  it('should create a new layout and be redirected to the layout designer, add/delete dataset widget', function() {
    // Create and alias for load dataset
    cy.intercept('/dataset?start=*').as('loadDatasets');

    cy.intercept({
      method: 'DELETE',
      url: '/region/*',
    }).as('deleteWidget');

    cy.visit('/layout/view');

    cy.get('button[href="/layout"]').click();

    // Open widget menu
    cy.openToolbarMenu(0);

    cy.get('[data-sub-type="dataset"]').click();
    cy.get('[data-template-id="dataset_table_1"]').click();
    cy.get('.viewer-object.layout.ui-droppable-active').click();

    // // Check if the widget is in the viewer
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_dataset"]').should('exist');

    // Select the dataset
    cy.get('#configureTab > .dropdown-input-group > .select2 > .selection > .select2-selection').click();

    // Wait for datasets to load
    cy.wait('@loadDatasets');

    // Type the dataset name
    cy.get('.select2-container--open input[type="search"]').type('8 items');

    // Wait for datasets to load
    cy.wait('@loadDatasets');
    cy.get('.select2-container--open').contains('8 items');
    cy.get('.select2-container--open .select2-results > ul > li:first').contains('8 items').click();

    cy.get('[name="lowerLimit"]').clear().type('1');
    cy.get('[name="upperLimit"]').clear().type('10');
    cy.get('.order-clause-row > :nth-child(2) > .form-control').select('Col1', {force: true});
    cy.get('.order-clause-row > .btn').click();
    cy.get(':nth-child(2) > :nth-child(2) > .form-control').select('Col2', {force: true});

    // -------------
    // -------------Appearance Tab
    cy.get('.nav-link[href="#appearanceTab"]').click();

    // Check if dataset exists exactly two columns
    cy.get('#columnsOut')
        .find('li')
        .should('have.length', 2)

    // Select columns available/ move them to columns selected
    cy.get('#columnsOut>li:first')
      .trigger('mousedown', {
        which: 1,
      })
      .trigger('mousemove', {
        which: 1,
        pageX: 583,
        pageY: 440,
      });
    cy.get('#columnsIn').click();

    cy.get('#columnsOut>li:first')
      .trigger('mousedown', {
        which: 1,
      })
      .trigger('mousemove', {
        which: 1,
        pageX: 583,
        pageY: 440,
      });
    cy.get('#columnsIn').click();

    cy.get('[name="showHeadings"]').check();
    cy.get('[name="rowsPerPage"]').clear().type('5');
    cy.get('[name="fontSize"]').clear().type('48');
    cy.get('[name="backgroundColor"]').clear().type('#333333');

    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_dataset"]').parents('.designer-region').rightclick();
    cy.get('[data-title="Delete"]').click();
    cy.contains('Yes').click();

    // Wait until the widget has been deleted
    cy.wait('@deleteWidget');
    cy.get('#layout-viewer .designer-region .widget-preview[data-type="widget_dataset"]').should('not.exist');
  });
});
