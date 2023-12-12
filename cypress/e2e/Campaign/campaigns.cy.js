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
describe('Campaigns', function() {
  const testRun = Cypress._.random(0, 1e9);

  beforeEach(function() {
    cy.login();
  });

  // Create a list campaign
  // Assign layout to it
  // and add the id to the session
  it('should add a campaign and assign a layout', function() {
    cy.intercept('/campaign?draw=4&*').as('campaignGridLoad');

    cy.intercept({
      url: '/campaign?*',
      query: {name: 'Cypress Test Campaign ' + testRun},
    }).as('campaignGridLoadAfterSearch');

    cy.intercept({
      url: '/layout?*',
      query: {layout: 'List Campaign Layout'},
    }).as('layoutLoadAfterSearch');

    // Intercept the POST request to get the campaign Id
    cy.intercept('/campaign').as('postCampaign');
    cy.intercept('/campaign/form/add?*').as('campaignFormAdd');

    cy.visit('/campaign/view');

    // Click on the Add Campaign button
    cy.contains('Add Campaign').click();

    cy.get('.modal input#name')
      .type('Cypress Test Campaign ' + testRun);

    cy.get('.modal .save-button').click();

    // Wait for the edit form to pop open
    cy.contains('.modal .modal-title', testRun);

    // Wait for the intercepted POST request to complete and the response to be received
    cy.wait('@postCampaign').then((interception) => {
      // Access the response body and extract the ID
      const id = interception.response.body.id;
      // Save the ID to the Cypress.env object
      Cypress.env('sessionCampaignId', id);
    });

    // Switch to the layouts tab.
    cy.contains('.modal .nav-tabs .nav-link', 'Layouts').click();

    // Should have no layouts assigned
    cy.get('.modal #LayoutAssignSortable').children()
      .should('have.length', 0);

    // Search for 2 layouts names 'List Campaign Layout 1' and 'List Campaign Layout 2'
    cy.get('.form-inline input[name="layout"]')
      .type('List Campaign Layout').blur();

    // Wait for the intercepted request and check the URL for the desired query parameter value
    cy.wait('@layoutLoadAfterSearch').then((interception) => {
      // Perform your desired actions or assertions here
      cy.log('Layout Loading');

      cy.get('#layoutAssignments tbody tr').should('have.length', 2);

      // Assign a layout
      cy.get('#layoutAssignments tr:nth-child(1) a.assignItem').click();
      cy.get('#layoutAssignments tr:nth-child(2) a.assignItem').click();

      // Save
      cy.get('.bootbox .save-button').click();

      // Wait for 4th campaign grid reload
      cy.wait('@campaignGridLoad');

      // Filter for the created campaign
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Campaign ' + testRun);

      cy.wait('@campaignGridLoadAfterSearch');

      // Should have 2 layouts assigned
      cy.get('#campaigns tbody tr').should('have.length', 1);
      cy.get('#campaigns tbody tr:nth-child(1) td:nth-child(5)').contains('2');
    });
  });

  it('should schedule a campaign and should set display status to green', function() {
    // At this point we know the campaignId
    const displayName = 'List Campaign Display 1';
    const sessionCampaignId = Cypress.env('sessionCampaignId');

    // Schedule the campaign
    cy.scheduleCampaign(sessionCampaignId, displayName).then((res) => {
      cy.displaySetStatus(displayName, 1);

      // Go to display grid
      cy.intercept('/display?draw=3&*').as('displayGridLoad');

      cy.visit('/display/view');

      // Filter for the created campaign
      cy.get('.FilterDiv input[name="display"]')
        .type(displayName);

      // Should have the display
      cy.get('#displays tbody tr').should('have.length', 1);

      // Check the display status is green
      cy.get('#displays tbody tr:nth-child(1)').should('have.class', 'table-success'); // For class "table-success"
      cy.get('#displays tbody tr:nth-child(1)').should('have.class', 'odd'); // For class "odd"
    });
  });

  it('delete a campaign and check if the display status is pending', function() {
    cy.intercept('/campaign?draw=2&*').as('campaignGridLoad');
    cy.intercept('DELETE', '/campaign/*', (req) => {
    }).as('deleteCampaign');
    cy.visit('/campaign/view');

    // Filter for the created campaign
    cy.get('#Filter input[name="name"]')
      .type('Cypress Test Campaign ' + testRun);

    // Wait for 2nd campaign grid reload
    cy.wait('@campaignGridLoad');

    cy.get('#campaigns tbody tr').should('have.length', 1);

    cy.get('#campaigns tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#campaigns tr:first-child .campaign_button_delete').click({force: true});

    // Delete the campaign
    cy.get('.bootbox .save-button').click();

    // Wait for the intercepted DELETE request to complete with status 200
    cy.wait('@deleteCampaign').its('response.statusCode').should('eq', 200);

    // check the display status
    cy.displayStatusEquals('List Campaign Display 1', 3).then((res) => {
      expect(res.body).to.be.true;
    });
  });
});
