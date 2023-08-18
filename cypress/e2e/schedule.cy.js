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
    // Set up intercepts with aliases
    cy.intercept('/displaygroup?*').as('loadDisplaygroups');
    cy.intercept('/campaign?type=list*').as('loadListCampaigns');
    cy.intercept('/campaign?isLayoutSpecific=-1*').as('loadLayoutSpecificCampaign');
    cy.intercept('/display?start=*').as('loadDisplays');
    cy.intercept('/schedule?draw=4&*').as('scheduleGridLoad');
    cy.intercept('/layout?*').as('layoutLoad');
    cy.intercept('/user/pref').as('userPref');
    cy.intercept('POST', '/schedule').as('postCampaign'); // Intercept POST request

    // Create a campaign
    cy.createCampaign('Campaign for Schedule 1');

    // Visit the page and click on the Add Event button
    cy.visit('/schedule/view');
    cy.contains('Add Event').click();

    // Fill event details
    cy.get('.col-sm-10 > #eventTypeId').select('Campaign', {force: true});
    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection > .select2-selection > .select2-selection__rendered')
      .type('List Campaign Display 1');

    // Wait for Display to load and select the display
    cy.wait('@loadDisplaygroups');
    cy.get('.select2-container--open').contains('List Campaign Display 1');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
    cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains('List Campaign Display 1').click();

    // Select day part and campaign
    cy.get('[name="dayPartId"]').select('Always', {force: true});
    cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection').type('Campaign for Schedule 1');
    cy.wait('@loadListCampaigns');
    cy.get('.select2-container--open').contains('Campaign for Schedule 1');
    cy.get('.select2-container--open .select2-results > ul > li:first').click();

    // Click Next and check toast message
    cy.get('.modal .modal-footer').contains('Next').click();
    cy.contains('Added Event');
  });

  it('should schedule an event layout that has no priority, no recurrence', function() {
    cy.intercept('/displaygroup?*').as('loadDisplaygroups');
    cy.intercept('/campaign?type=list*').as('loadListCampaigns');
    cy.intercept('/campaign?isLayoutSpecific=-1*').as('loadLayoutSpecificCampaign');
    cy.intercept('/display?start=*').as('loadDisplays');
    cy.intercept('/schedule?draw=4&*').as('scheduleGridLoad');
    cy.intercept('/layout?*').as('layoutLoad');
    cy.intercept('/user/pref').as('userPref');

    // Intercept the POST request to get the schedule Id
    cy.intercept('/schedule').as('postCampaign');

    // Click on the Add Event button
    cy.visit('/schedule/view');
    cy.contains('Add Event').click();

    // Layout
    cy.get('.col-sm-10 > #eventTypeId').select('Layout', {force: true});
    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection > .select2-selection > .select2-selection__rendered')
      .type('List Campaign Display 1');
    // Wait for Display to load and select the display
    cy.wait('@loadDisplaygroups');
    cy.get('.select2-container--open').contains('List Campaign Display 1');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
    cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains('List Campaign Display 1').click();
    cy.get('[name="dayPartId"]').select('Always', {force: true});

    // Select Layout
    cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection')
      .type('Layout for Schedule 1');
    // Wait for Campaign to load
    cy.wait('@loadListCampaigns');
    cy.get('.select2-container--open').contains('Layout for Schedule 1');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li:first').contains('Layout for Schedule 1').click();
    cy.get('.modal .modal-footer').contains('Next').click();
    cy.contains('Added Event');
  });

  it('should schedule an event command/overlay layout that has no priority, no recurrence', function() {
    cy.intercept('/displaygroup?*').as('loadDisplaygroups');
    cy.intercept('/campaign?type=list*').as('loadListCampaigns');
    cy.intercept('/campaign?isLayoutSpecific=-1*').as('loadLayoutSpecificCampaign');
    cy.intercept('/display?start=*').as('loadDisplays');
    cy.intercept('/schedule?draw=4&*').as('scheduleGridLoad');
    cy.intercept('/layout?*').as('layoutLoad');
    cy.intercept('/user/pref').as('userPref');

    // Intercept the POST request to get the schedule Id
    cy.intercept('/schedule').as('postCampaign');

    cy.createCommand('Set Timezone', 'Set timezone', 'TIMEZONE');

    // Click on the Add Event button
    cy.visit('/schedule/view');
    cy.contains('Add Event').click();

    // Create Command Schedule
    cy.get('.col-sm-10 > #eventTypeId').select('Command', {force: true});
    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection > .select2-selection > .select2-selection__rendered')
      .type('List Campaign Display 1');
    // Wait for Display to load and select the display
    cy.wait('@loadDisplaygroups');
    cy.get('.select2-container--open').contains('List Campaign Display 1');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
    cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains('List Campaign Display 1').click();

    cy.get('.starttime-control > .col-sm-10 > .input-group > .datePickerHelper').click();
    cy.get('.open > .flatpickr-innerContainer > .flatpickr-rContainer > .flatpickr-days > .dayContainer > .today').click();
    cy.get('.open > .flatpickr-time > :nth-child(3) > .arrowUp').click();
    cy.get('[name="commandId"]').select('Set Timezone', {force: true});
    cy.get('.modal .modal-footer').contains('Next').click();

    // ---------
    // Create Overlay Layout Schedule
    cy.get('.col-sm-10 > #eventTypeId').select('Overlay Layout', {force: true});
    cy.get('[name="dayPartId"]').select('Always', {force: true});

    // Select Layout
    cy.get('.layout-control > .col-sm-10 > .select2 > .selection > .select2-selection').type('Layout for Schedule 1');
    // Wait for Display to load
    cy.wait('@loadListCampaigns');
    cy.get('.select2-container--open').contains('Layout for Schedule 1');
    // cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li:first').contains('Layout for Schedule 1').click();

    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection > .select2-selection > .select2-selection__rendered')
      .type('List Campaign Display 1');
    // Wait for Display to load
    cy.wait('@loadDisplaygroups');
    cy.get('.select2-container--open').contains('List Campaign Display 1');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
    cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains('List Campaign Display 1').click();

    cy.get('.modal .modal-footer').contains('Save').click();
  });

  it('schedule creation should be successful', function() {
    cy.intercept('/displaygroup?*').as('loadDisplaygroups');
    cy.intercept('/campaign?type=list*').as('loadListCampaigns');
    cy.intercept('/campaign?isLayoutSpecific=-1*').as('loadLayoutSpecificCampaign');
    cy.intercept('/display?start=*').as('loadDisplays');
    cy.intercept('/schedule?draw=4&*').as('scheduleGridLoad');
    cy.intercept('/layout?*').as('layoutLoad');
    cy.intercept('/user/pref').as('userPref');

    // Intercept the POST request to get the schedule Id
    cy.intercept('/schedule').as('postCampaign');

    // ------
    // Check if schedule creation was successful
    cy.visit('/schedule/view');

    cy.get('#DisplayList + span .select2-selection').click();
    cy.wait('@loadDisplays');
    // Type the display name
    cy.get('.select2-container--open input[type="search"]').type('List Campaign Display 1');

    // Wait for Display to load
    cy.wait('@loadDisplays');
    cy.get('.select2-container--open').contains('List Campaign Display 1');
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li:first').contains('List Campaign Display 1').click();

    cy.get('#schedule-grid').contains('Campaign for Schedule 1');
    cy.get('#schedule-grid').contains('Layout for Schedule 1');
  });

  it('should edit a scheduled event', function() {
    cy.intercept('/user/pref').as('userPref');
    cy.intercept('/schedule?draw=*').as('scheduleGridLoad');

    cy.intercept('/displaygroup?*').as('loadDisplaygroups');
    cy.intercept('/campaign?isLayoutSpecific=-1*').as('loadLayoutSpecificCampaign');

    cy.visit('/schedule/view');

    // ---------
    // Edit a schedule - add another display
    cy.get('#campaignIdFilter + span .select2-selection').click();
    cy.wait('@loadLayoutSpecificCampaign');
    cy.get('.select2-container--open input[type="search"]').type('Layout for Schedule 1'); // Type the layout name
    cy.wait('@loadLayoutSpecificCampaign');
    cy.get('.select2-container--open').contains('Layout for Schedule 1');
    cy.get('.select2-container--open .select2-results > ul > li').should('have.length', 1);
    cy.get('.select2-container--open .select2-results > ul > li:first').contains('Layout for Schedule 1').click();

    // Should have 1
    cy.get('#schedule-grid tbody tr').should('have.length', 2);
    cy.get('#schedule-grid tr:first-child .dropdown-toggle').click();
    cy.get('#schedule-grid tr:first-child .schedule_button_edit').click();

    cy.get(':nth-child(3) > .col-sm-10 > .select2 > .selection > .select2-selection > .select2-selection__rendered')
      .type('List Campaign Display 2');
    // Wait for Display to load
    cy.wait('@loadDisplaygroups');
    cy.get('.select2-container--open').contains('List Campaign Display 2');
    cy.get('.select2-container--open .select2-dropdown .select2-results > ul > li').should('have.length', 2);
    cy.get('#select2-displayGroupIds-results > li > ul > li:first').contains('List Campaign Display 2').click();
    cy.get('.modal .modal-footer').contains('Save').click();
    cy.get('#schedule-grid tbody').contains('2');

    // ---------
    // Delete the schedule
    // cy.get('#schedule-grid tbody tr').should('have.length', 2);
    // cy.wait('@scheduleGridLoad');
    // cy.wait('@userPref');
    // cy.wait('@scheduleGridLoad');
    // cy.get('#schedule-grid tr:first-child .dropdown-toggle').click();
    // cy.get('#schedule-grid tr:first-child .schedule_button_delete').click();
    // cy.get('.bootbox .save-button').click();
    //
    // // Validate the schedule no longer exist
    // cy.get('#schedule-grid tbody tr').should('have.length', 1);
  });
});
