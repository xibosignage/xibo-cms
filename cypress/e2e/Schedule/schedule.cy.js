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
describe('Schedule Events', function() {
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

  // TC-01
  it('should schedule an event layout that has no priority, no recurrence', function() {
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
      query: {name: layoutSchedule1},
    }).as('loadListCampaignsAfterSearch');

    // Click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();
    cy.contains('Add Event').click();

    cy.get('.bootbox.modal')
      .should('be.visible') // essential: Ensure the modal is visible
      .then(() => {
        cy.get('.modal-content #eventTypeId').select('Layout');
        // Select layout
        cy.selectFromDropdown('.layout-control .select2-selection', layoutSchedule1, layoutSchedule1, '@loadListCampaignsAfterSearch');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select display
        cy.selectFromDropdown('.display-group-control .select2-selection', display1, display1, '@loadDisplaygroupAfterSearch', 1);

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select day part and set name
        cy.get('.modal-content [name="dayPartId"]').select('Always');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();
        cy.get('.modal-content [name="name"]').type('Always - Layout Event');

        cy.get('.modal .modal-footer').contains('Finish').click();

        cy.contains('Added Event');
      });

    // Validate - schedule creation should be successful
    cy.visit('/schedule/view');
    cy.contains('Clear Filters').should('be.visible').click();

    cy.get('#DisplayList + span .select2-selection').click();

    // Type the display name
    cy.get('.select2-container--open textarea[type="search"]').type(display1);

    // Wait for Display to load
    cy.wait('@loadDisplayAfterSearch');
    cy.get('.select2-container--open').contains(display1);
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);

    // Select the display from the dropdown
    cy.get('.select2-container--open .select2-results > ul > li:first').contains(display1).click();

    // Verify that the schedule is successfully created and listed in the grid
    cy.get('#schedule-grid').contains(layoutSchedule1);

    // Should have 1
    cy.get('#schedule-grid tbody tr').should('have.length', 1);
  });

  // relies on TC-01
  it('should edit a scheduled event', function() {
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');

    // Set up intercepts with aliases
    cy.intercept({
      url: '/display?start=*',
      query: {display: display1},
    }).as('loadDisplayAfterSearch');

    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display2},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/campaign?type=list*',
      query: {name: layoutSchedule1},
    }).as('loadListCampaignsAfterSearch');

    // Click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();

    cy.get('#DisplayList + span .select2-selection').click();

    // Type the display name
    cy.get('.select2-container--open textarea[type="search"]').type(display1);

    // Wait for Display to load
    cy.wait('@loadDisplayAfterSearch');
    cy.get('.select2-container--open').contains(display1);
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);

    // Select the display from the dropdown
    cy.get('.select2-container--open .select2-results > ul > li:first').contains(display1).click();

    // Verify that the schedule is successfully created and listed in the grid
    cy.get('#schedule-grid').contains(layoutSchedule1);

    // Should have 1
    cy.get('#schedule-grid tbody tr').should('have.length', 1);
    cy.get('#schedule-grid tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#schedule-grid tr:first-child .schedule_button_edit').click({force: true});

    cy.contains('.stepwizard-step', 'Displays')
      .find('a')
      .click();

    // Select display
    cy.get('.display-group-control > .col-sm-10 > .select2 > .selection > .select2-selection').type(display2);
    // Wait for the display group to load after search
    cy.wait('@loadDisplaygroupAfterSearch');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul')
      .should('contain', display2);
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li')
      .should('have.length', 2)
      .last()
      .click();

    cy.contains('.stepwizard-step', 'Optional')
      .find('a')
      .click();

    cy.get('.modal-content [name="name"]').clear().type('Always - Layout Event Edited');

    // Click Next and check toast message
    cy.get('.modal .modal-footer').contains('Save').click();
    cy.contains('Edited Event');
  });

  it('should schedule an event campaign that has no priority, no recurrence', function() {
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
        cy.get('.modal-content #eventTypeId').select('Campaign');
        // Select campaign
        cy.selectFromDropdown('.layout-control .select2-selection', campaignSchedule1, campaignSchedule1, '@loadListCampaignsAfterSearch');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select display
        cy.selectFromDropdown('.display-group-control .select2-selection', display1, display1, '@loadDisplaygroupAfterSearch', 1);

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select day part and campaign
        cy.get('.modal-content [name="dayPartId"]').select('Always');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();
        cy.get('.modal-content [name="name"]').type('Always - Campaign Event');

        cy.get('.modal .modal-footer').contains('Finish').click();

        cy.contains('Added Event');
      });

    // Validate - schedule creation should be successful
    cy.visit('/schedule/view');
    cy.contains('Clear Filters').should('be.visible').click();

    cy.get('#DisplayList + span .select2-selection').click();

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

  it('should schedule an event command layout that has no priority, no recurrence', function() {
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');
    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display1},
    }).as('loadDisplaygroupAfterSearch');

    cy.intercept({
      url: '/command?*',
      query: {command: command1},
    }).as('loadCommandAfterSearch');

    // Click on the Add Event button
    cy.visit('/schedule/view');

    cy.contains('Clear Filters').should('be.visible').click();
    cy.contains('Add Event').click();

    cy.get('.bootbox.modal')
      .should('be.visible') // essential: Ensure the modal is visible
      .then(() => {
        cy.get('.modal-content #eventTypeId').select('Command');
        // Select command
        cy.selectFromDropdown('.command-control .select2-selection', command1, command1, '@loadCommandAfterSearch');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select display
        cy.selectFromDropdown('.display-group-control .select2-selection', display1, display1, '@loadDisplaygroupAfterSearch', 1);

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        cy.get('.starttime-control > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper').click();
        cy.get('.open > .flatpickr-innerContainer > .flatpickr-rContainer > .flatpickr-days > .dayContainer > .today').click();
        cy.get('.open > .flatpickr-time > :nth-child(3) > .arrowUp').click();

        cy.get('.modal .modal-footer').contains('Next').click();
        cy.get('.modal-content [name="name"]').type('Custom - Command Event');

        cy.get('.modal .modal-footer').contains('Finish').click();
      });
  });

  it('should schedule an event overlay layout that has no priority, no recurrence', function() {
    cy.intercept('GET', '/schedule/form/add?*').as('scheduleAddForm');
    cy.intercept({
      url: '/displaygroup?*',
      query: {displayGroup: display1},
    }).as('loadDisplaygroupAfterSearch');

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
        cy.get('.modal-content #eventTypeId').select('Overlay Layout');
        // Select layout
        cy.selectFromDropdown('.layout-control .select2-selection', layoutSchedule1, layoutSchedule1, '@loadListCampaignsAfterSearch');

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select display
        cy.selectFromDropdown('.display-group-control .select2-selection', display1, display1, '@loadDisplaygroupAfterSearch', 1);

        // Click Next and check toast message
        cy.get('.modal .modal-footer').contains('Next').click();

        // Select daypart - custom
        cy.get('#dayPartId').select('Custom');


        cy.get('.starttime-control > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper')
          .click() // Open the picker
          .then(() => {
            // Select today's date
            cy.get('.flatpickr-calendar.open .flatpickr-days .dayContainer .today')
              .click();

            // Increment the hour (adjust time)
            cy.get('.flatpickr-calendar.open .flatpickr-time :nth-child(3) .arrowUp')
              .click();

            // Close the picker by clicking outside
            cy.get('body').click(0, 0);
          });

        cy.get('.endtime-control > .col-sm-10 > .input-group > .flatpickr-wrapper > .datePickerHelper')
          .click() // Open the picker
          .then(() => {
            // Select today's date
            cy.get('.flatpickr-calendar.open .flatpickr-days .dayContainer .today')
              .click();

            // Increment the hour (adjust time)
            cy.get('.flatpickr-calendar.open .flatpickr-time :nth-child(3) .arrowUp')
              .click()
              .click();

            // Close the picker by clicking outside
            cy.get('body').click(0, 0);
          });

        cy.get('.modal .modal-footer').contains('Next').click();
        cy.get('.modal-content [name="name"]').type('Custom - Overlay Event');

        cy.get('.modal .modal-footer').contains('Finish').click();
      });
  });
});
