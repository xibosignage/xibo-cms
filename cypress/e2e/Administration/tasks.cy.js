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
describe('Tasks', function() {
  beforeEach(function() {
    cy.login();
  });

  it('should edit a task', function() {
    // Intercept the PUT request
    cy.intercept({
      method: 'PUT',
      url: '/task/*',
    }).as('putRequest');

    cy.visit('/task/view');

    // Click on the first row element to open the delete modal
    cy.get('#tasks tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#tasks tr:first-child .task_button_edit').click({force: true});

    // Assuming you have an input field with the id 'myInputField'
    cy.get('.modal input#name').invoke('val').then((value) => {
      return Cypress.Promise.resolve(value);
    }).then((value) => {
      if (value) {
        cy.get('.modal input#name').clear()
          .type(value + ' Edited');

        // edit test tag
        cy.get('.bootbox .save-button').click();

        // Wait for the intercepted PUT request and check the form data
        cy.wait('@putRequest').then((interception) => {
          // Get the request body (form data)
          const response = interception.response;
          const responseData = response.body.data;

          // assertion on the "task" value
          expect(responseData.name).to.eq(value + ' Edited');
        });
      }
    });
  });
});
