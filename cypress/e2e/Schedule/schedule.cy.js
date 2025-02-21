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


/* eslint-disable max-len */
describe('Campaigns', function() {
  // Seeded Data
  const campaignSchedule1 = 'Campaign for Schedule 1';
  const layoutSchedule1 = 'Layout for Schedule 1';

  const display1 = 'List Campaign Display 1';
  const display2 = 'List Campaign Display 2';
  const command1 = 'Set Timezone';

  beforeEach(function() {
    cy.login();
  });

  it('should list all scheduled events', function() {
    // Make a GET request to the API endpoint '/schedule/data/events'??
    cy.request({
      method: 'GET',
      url: '/schedule/data/events',
    }).then((response) => {
      // Assertions on the response
      expect(response.status).to.equal(200);
      expect(response.body).to.have.property('result');
    });
  });

  it('should schedule an event campaign that has no priority, no recurrence', function() {
    cy.intercept('GET', '/schedule?draw=2*').as('scheduleLoad');
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');

    // Set up intercepts with aliases
    cy.intercept({
      url: '/display?start=*',
      query: {display: display1},
    }).as('loadDisplayAfterSearch');

    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display1},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/campaign?type=list*',
      query: {name: campaignSchedule1},
    }).as('loadListCampaignsAfterSearch');

    // Visit the page and click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();
    cy.contains('Add Event').click();

    cy.get('.bootbox.modal')
      .should('be.visible') // essential: Ensure the modal is visible
      .then(() => {
        // Clear the display filter and select a specific display (display1)
        cy.get('.select2-selection__clear > span').click();
        cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection .select2-search__field')
          .type(display1)
          .should('have.value', display1);

        // Wait for the display group to load after search
        cy.wait('@loadDisplaygroupAfterSearch');

        // Verify the display appears in the dropdown and select it
        cy.get('.select2-container--open').contains(display1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
        cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains(display1).click();

        // Select day part and campaign
        cy.get('.modal-content [name="dayPartId"]').select('Always');

        cy.get('.modal-content #eventTypeId').select('Campaign');
        cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection').type(campaignSchedule1);
        // Wait for campaigns to load
        cy.wait('@loadListCampaignsAfterSearch');
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul')
          .should('contain', campaignSchedule1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li')
          .should('have.length', 1)
          .first()
          .click();

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();
        cy.contains('Added Event');
      });

    // Validate - schedule creation should be successful
    cy.visit('/schedule/view');
    cy.contains('Clear Filters').should('be.visible').click();


    cy.get('#DisplayList + span .select2-selection').click();
    cy.wait('@scheduleLoad');

    // Type the display name
    cy.get('.select2-container--open textarea[type="search"]').type(display1);

    // Wait for Display to load
    cy.wait('@loadDisplayAfterSearch');
    cy.get('.select2-container--open').contains(display1);
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);

    // Select the display from the dropdown
    cy.get('.select2-container--open .select2-results > ul > li:first').contains(display1).click();

    // Verify that the schedule is successfully created and listed in the grid
    cy.get('#schedule-grid').contains(campaignSchedule1);
  });

  it('should schedule an event layout that has no priority, no recurrence', function() {
    cy.intercept('GET', '/schedule?draw=2*').as('scheduleLoad');
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');
    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display1},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/displaygroup?*',
    }).as('loadDisplaygroupAfterSearchCommon');

    cy.intercept({
      url: '/campaign?type=list*',
      query: {name: layoutSchedule1},
    }).as('loadListCampaignsAfterSearch');

    // Click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();
    cy.contains('Add Event').click();
    cy.get('.bootbox.modal')
      .should('be.visible') // essential: Ensure the modal is visible
      .then(() => {
        // Clear the display filter and select a specific display (display1)
        cy.get('.select2-selection__clear > span').click();
        cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection .select2-search__field')
          .type(display1)
          .should('have.value', display1);
        // Wait for the display group to load after search
        cy.wait('@loadDisplaygroupAfterSearch');
        cy.get('.select2-container--open').contains(display1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
        cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains(display1).click();

        // Select day part
        cy.get('.modal-content [name="dayPartId"]').select('Always');

        cy.get('.modal-content #eventTypeId').select('Layout');
        // Select Layout
        cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection')
          .type(layoutSchedule1);
        // Wait for Campaign to load
        cy.wait('@loadListCampaignsAfterSearch');
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul')
          .should('contain', layoutSchedule1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li')
          .should('have.length', 1)
          .first()
          .click();

        // Save
        cy.get('.modal .modal-footer').contains('Next').click();
        cy.contains('Added Event');
      });
  });

  it('should schedule an event command/overlay layout that has no priority, no recurrence', function() {
    cy.intercept('GET', '/schedule?draw=2*').as('scheduleLoad');
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');
    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display1},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/command?*',
      query: {command: command1},
    }).as('loadCommandAfterSearch');

    cy.intercept({
      url: '/campaign?type=list*',
      query: {name: layoutSchedule1},
    }).as('loadListCampaignsAfterSearch');

    // Click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();
    cy.contains('Add Event').click();

    cy.get('.bootbox.modal')
      .should('be.visible') // essential: Ensure the modal is visible
      .then(() => {
        // Clear the display filter and select a specific display (display1)
        cy.get('.select2-selection__clear > span').click();
        cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection .select2-search__field')
          .type(display1)
          .should('have.value', display1);

        // Wait for the display group to load after search
        cy.wait('@loadDisplaygroupAfterSearch');

        // Verify the display appears in the dropdown and select it
        cy.get('.select2-container--open').contains(display1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
        cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains(display1).click();

        // command
        cy.get('.modal-content #eventTypeId').select('Command');
        cy.get('.command-control > .col-sm-10 > .select2 > .selection > .select2-selection')
          .type(command1);
        cy.wait('@loadCommandAfterSearch');
        cy.get('.select2-container--open').contains(command1);
        cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
        cy.get('.select2-container--open .select2-results > ul > li:first').contains(command1).click();

        cy.get('.starttime-control > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
        cy.get('.open > .flatpickr-innerContainer > .flatpickr-rContainer > .flatpickr-days > .dayContainer > .today').click();
        cy.get('.open > .flatpickr-time > :nth-child(3) > .arrowUp').click();
        cy.get('.modal .modal-footer').contains('Next').click();

        // ---------
        // Create Overlay Layout Schedule
        cy.get('.modal-content #eventTypeId').select('Overlay Layout');
        cy.get('.modal-content [name="dayPartId"]').select('Always');

        // Select Layout
        cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection').type(layoutSchedule1);
        // Wait for Display to load
        cy.wait('@loadListCampaignsAfterSearch');
        cy.get('.select2-container--open').contains(layoutSchedule1);
        cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
        cy.get('.select2-container--open .select2-results > ul > li:first').contains(layoutSchedule1).click();

        // display
        cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection .select2-search__field')
          .type(display1);
        cy.wait('@loadDisplaygroupAfterSearch');
        cy.get('.select2-container--open').contains(display1);
        cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
        cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains(display1).click();

        cy.get('.modal .modal-footer').contains('Save').click();
      });
  });

  it('should edit a scheduled event', function() {
    cy.intercept('GET', '/schedule/data/events?*').as('scheduleDataEvent');
    cy.intercept('GET', '/schedule?draw=3*').as('scheduleLoad3');
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');
    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display2},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/campaign?isLayoutSpecific=-1*',
      query: {name: layoutSchedule1},
    }).as('loadLayoutSpecificCampaign');

    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();

    // ---------
    // Edit a schedule - add another display
    cy.get('#campaignIdFilter + span .select2-selection').click();
    cy.get('.select2-container--open .select2-search__field').type(layoutSchedule1); // Type the layout name
    cy.wait('@loadLayoutSpecificCampaign');
    cy.selectOption(layoutSchedule1);

    // Should have 1
    cy.get('#schedule-grid tbody tr').should('have.length', 2);
    cy.get('#schedule-grid tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#schedule-grid tr:first-child .schedule_button_edit').click({force: true});

    // display
    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection .select2-search__field')
      .type(display2);
    cy.wait('@loadDisplaygroupAfterSearch');
    cy.get('.select2-container--open .select2-results > ul > li')
      .should('have.length', 2)
      .last()
      .contains(display2)
      .click();

    cy.get('.modal .modal-footer').contains('Save').click();

    cy.get('#schedule-grid tbody').contains('2');

    cy.get('#schedule-grid tbody tr').should('have.length', 2);
    cy.wait('@scheduleLoad3');
    cy.wait('@scheduleDataEvent');
    cy.get('#schedule-grid tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#schedule-grid tr:first-child .schedule_button_delete').click({force: true});
    cy.get('.bootbox .save-button').click();
  });
});
