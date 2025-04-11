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
describe('Menuboards', function() {
  let testRun = '';

  beforeEach(function() {
    cy.login();

    testRun = Cypress._.random(0, 1e9);
  });

  it('should add a menuboard', function() {
    cy.visit('/menuboard/view');

    // Click on the Add Menuboard button
    cy.contains('Add Menu Board').click();

    cy.get('.modal input#name')
      .type('Cypress Test Menuboard ' + testRun + '_1');
    cy.get('.modal input#code')
      .type('MENUBOARD');
    cy.get('.modal textarea#description')
      .type('Menuboard Description');

    // Add first by clicking next
    cy.get('.modal .save-button').click();

    // Check if menuboard is added in toast message
    cy.contains('Added Menu Board');
  });

  it('should cancel adding a menuboard', function() {
    cy.visit('/menuboard/view');

    // Click on the Add Menuboard button
    cy.contains('Add Menu Board').click();

    cy.get('.modal input#name')
      .type('Cypress Test Menuboard ' + testRun + '_1');
    cy.get('.modal input#code')
      .type('MENUBOARD');
    cy.get('.modal textarea#description')
      .type('Menuboard Description');

    // Click cancel
    cy.get('.modal #dialog_btn_1').click();

    // Check if you are back to the view page
    cy.url().should('include', '/menuboard/view');
  });

  it('searches and edit existing menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((res) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      // Intercept the PUT request
      cy.intercept({
        method: 'PUT',
        url: '/menuboard/*',
      }).as('putRequest');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#menuBoards tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#menuBoards tr:first-child .menuBoard_edit_button').click({force: true});

      cy.get('.modal input#name').clear()
        .type('Cypress Test Menuboard Edited ' + testRun);

      // edit test menuboard
      cy.get('.bootbox .save-button').click();

      // Wait for the intercepted PUT request and check the form data
      cy.wait('@putRequest').then((interception) => {
        // Get the request body (form data)
        const response = interception.response;
        const responseData = response.body.data;

        // assertion on the "menuboard" value
        expect(responseData.name).to.eq('Cypress Test Menuboard Edited ' + testRun);
      });
    });
  });

  it('searches and delete existing menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((res) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#menuBoards tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#menuBoards tr:first-child .menuBoard_delete_button').click({force: true});

      // Delete test menuboard
      cy.get('.bootbox .save-button').click();

      // Check if menuboard is deleted in toast message
      cy.get('.toast').contains('Deleted Cypress Test Menuboard');
    });
  });

  // -------------------
  it('should add categories and products to a menuboard', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.intercept({
        url: '/menuboard?*',
        query: {name: 'Cypress Test Menuboard ' + testRun},
      }).as('loadGridAfterSearch');

      cy.visit('/menuboard/view');

      // Filter for the created menuboard
      cy.get('#Filter input[name="name"]')
        .type('Cypress Test Menuboard ' + testRun);

      // Wait for the grid reload
      cy.wait('@loadGridAfterSearch');
      cy.get('#menuBoards tbody tr').should('have.length', 1);

      // Click on the first row element to open the delete modal
      cy.get('#menuBoards tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#menuBoards tr:first-child .menuBoard_button_viewcategories').click({force: true});

      // Click on the Add Category button
      cy.contains('Add Category').click();

      cy.get('.modal input#name')
        .type('Cypress Test Category ' + testRun + '_1');
      cy.get('.modal input#code')
        .type('MENUBOARDCAT');

      // Add first by clicking next
      cy.get('.modal .save-button').click();

      // Check if menuboard is added in toast message
      cy.contains('Added Menu Board Category');

      // Wait for the grid reload
      // cy.wait('@loadCategoryGridAfterSearch');

      // Click on the first row element to open the delete modal
      cy.get('#menuBoardCategories tr:first-child .dropdown-toggle').click({force: true});
      cy.get('#menuBoardCategories tr:first-child .menuBoardCategory_button_viewproducts').click({force: true});

      // Click on the Add Product button
      cy.contains('Add Product').click();

      cy.get('.modal input#name')
        .type('Cypress Test Product ' + testRun + '_1');
      cy.get('.modal input#code')
        .type('MENUBOARDPROD');

      // Add first by clicking next
      cy.get('.modal .save-button').click();

      // Check if menuboard is added in toast message
      cy.contains('Added Menu Board Product');
    });
  });

  // -------------------
  // Categories
  it('should add a category', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      // GO to products page
      cy.visit('/menuboard/' + menuId + '/categories/view');
      // Click on the Add Category button
      cy.contains('Add Category').click();

      cy.get('.modal input#name')
        .type('Cypress Test Category ' + testRun + '_1');
      cy.get('.modal input#code')
        .type('MENUBOARDCAT');

      // Add first by clicking next
      cy.get('.modal .save-button').click();

      // Check toast message
      cy.contains('Added Menu Board Category');

      // Delete the menuboard and assert success
      cy.deleteMenuboard(menuId).then((response) => {
        expect(response.status).to.equal(204);
      });
    });
  });

  it('searches and edit existing category', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.createMenuboardCat('Cypress Test Category ' + testRun, menuId).then((menuCatId) => {
        cy.intercept({
          url: '/menuboard/' + menuId + '/categories?*',
          query: {name: 'Cypress Test Category ' + testRun},
        }).as('loadGridAfterSearch');

        // Intercept the PUT request
        cy.intercept({
          method: 'PUT',
          url: '/menuboard/' + menuCatId + '/category',
        }).as('putRequest');

        // GO to products page
        cy.visit('/menuboard/' + menuId + '/categories/view');
        // Filter for the created menuboard
        cy.get('#Filter input[name="name"]')
          .type('Cypress Test Category ' + testRun);

        // Wait for the grid reload
        cy.wait('@loadGridAfterSearch');
        cy.get('#menuBoardCategories tbody tr').should('have.length', 1);

        // Click on the first row element to open the delete modal
        cy.get('#menuBoardCategories tr:first-child .dropdown-toggle').click({force: true});
        cy.get('#menuBoardCategories tr:first-child .menuBoardCategory_edit_button').click({force: true});

        // EDIT
        cy.get('.modal input#name').clear()
          .type('Cypress Test Category Edited ' + testRun);

        cy.get('.bootbox .save-button').click();

        // Wait for the intercepted PUT request and check the form data
        cy.wait('@putRequest').then((interception) => {
          // Get the request body (form data)
          const response = interception.response;
          const responseData = response.body.data;

          // assertion on the "menuboard" value
          expect(responseData.name).to.eq('Cypress Test Category Edited ' + testRun);
        });

        // Delete the menuboard and assert success
        cy.deleteMenuboard(menuId).then((response) => {
          expect(response.status).to.equal(204);
        });
      });
    });
  });

  it('searches and delete existing category', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.createMenuboardCat('Cypress Test Category ' + testRun, menuId).then((menuCatId) => {
        cy.intercept({
          url: '/menuboard/' + menuId + '/categories?*',
          query: {name: 'Cypress Test Category ' + testRun},
        }).as('loadGridAfterSearch');

        // Intercept the PUT request
        cy.intercept({
          method: 'PUT',
          url: '/menuboard/' + menuCatId + '/category',
        }).as('putRequest');

        // GO to products page
        cy.visit('/menuboard/' + menuId + '/categories/view');
        // Filter for the created menuboard
        cy.get('#Filter input[name="name"]')
          .type('Cypress Test Category ' + testRun);

        // Wait for the grid reload
        cy.wait('@loadGridAfterSearch');
        cy.get('#menuBoardCategories tbody tr').should('have.length', 1);

        // Click on the first row element to open the delete modal
        cy.get('#menuBoardCategories tr:first-child .dropdown-toggle').click({force: true});
        cy.get('#menuBoardCategories tr:first-child .menuBoardCategory_delete_button').click({force: true});

        // Delete test category
        cy.get('.bootbox .save-button').click();

        // Check toast message
        cy.get('.toast').contains('Deleted Cypress Test Category');

        // Delete the menuboard and assert success
        cy.deleteMenuboard(menuId).then((response) => {
          expect(response.status).to.equal(204);
        });
      });
    });
  });

  // -------------------
  // Products
  it('should add a product', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.createMenuboardCat('Cypress Test Category ' + testRun, menuId).then((menuCatId) => {
        // GO to products page
        cy.visit('/menuboard/' + menuCatId + '/products/view');
        // Click on the Add Product button
        cy.contains('Add Product').click();

        cy.get('.modal input#name')
          .type('Cypress Test Product ' + testRun);
        cy.get('.modal input#code')
          .type('MENUBOARDPROD');

        // Add first by clicking next
        cy.get('.modal .save-button').click();

        // Check if menuboard is added in toast message
        cy.contains('Added Menu Board Product');
      });
    });
  });

  it('searches and edit existing product', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.log(menuId);
      cy.createMenuboardCat('Cypress Test Category ' + testRun, menuId).then((menuCatId) => {
        cy.log(menuCatId);
        cy.createMenuboardCatProd('Cypress Test Product ' + testRun, menuCatId).then((menuProdId) => {
          cy.log(menuProdId);
          cy.intercept({
            url: '/menuboard/' + menuCatId + '/products?*',
            query: {name: 'Cypress Test Product ' + testRun},
          }).as('loadGridAfterSearch');

          // Intercept the PUT request
          cy.intercept({
            method: 'PUT',
            url: '/menuboard/' + menuProdId + '/product',
          }).as('putRequest');

          // GO to products page
          cy.visit('/menuboard/' + menuCatId + '/products/view');
          // Filter for the created menuboard
          cy.get('#Filter input[name="name"]')
            .type('Cypress Test Product ' + testRun);

          // Wait for the grid reload
          cy.wait('@loadGridAfterSearch');
          cy.get('#menuBoardProducts tbody tr').should('have.length', 1);

          // Click on the first row element to open the delete modal
          cy.get('#menuBoardProducts tr:first-child .dropdown-toggle').click({force: true});
          cy.get('#menuBoardProducts tr:first-child .menuBoardProduct_edit_button').click({force: true});

          // EDIT
          cy.get('.modal input#name').clear()
            .type('Cypress Test Product Edited ' + testRun);

          cy.get('.bootbox .save-button').click();

          // Wait for the intercepted PUT request and check the form data
          cy.wait('@putRequest').then((interception) => {
            // Get the request body (form data)
            const response = interception.response;
            const responseData = response.body.data;

            // assertion on the "menuboard" value
            expect(responseData.name).to.eq('Cypress Test Product Edited ' + testRun);
          });

          // Delete the menuboard and assert success
          cy.deleteMenuboard(menuId).then((response) => {
            expect(response.status).to.equal(204);
          });
        });
      });
    });
  });

  it('searches and delete existing product', function() {
    // Create a new menuboard and then search for it and delete it
    cy.createMenuboard('Cypress Test Menuboard ' + testRun).then((menuId) => {
      cy.createMenuboardCat('Cypress Test Category ' + testRun, menuId).then((menuCatId) => {
        cy.createMenuboardCatProd('Cypress Test Product ' + testRun, menuCatId).then((menuProdId) => {
          cy.intercept({
            url: '/menuboard/' + menuCatId + '/products?*',
            query: {name: 'Cypress Test Product ' + testRun},
          }).as('loadGridAfterSearch');

          // Intercept the PUT request
          cy.intercept({
            method: 'PUT',
            url: '/menuboard/' + menuProdId + '/product',
          }).as('putRequest');

          // GO to products page
          cy.visit('/menuboard/' + menuCatId + '/products/view');
          // Filter for the created menuboard
          cy.get('#Filter input[name="name"]')
            .type('Cypress Test Product ' + testRun);

          // Wait for the grid reload
          cy.wait('@loadGridAfterSearch');
          cy.get('#menuBoardProducts tbody tr').should('have.length', 1);

          // Click on the first row element to open the delete modal
          cy.get('#menuBoardProducts tr:first-child .dropdown-toggle').click({force: true});
          cy.get('#menuBoardProducts tr:first-child .menuBoardProduct_delete_button').click({force: true});

          // Delete test menuboard
          cy.get('.bootbox .save-button').click();

          // Check toast message
          cy.get('.toast').contains('Deleted Cypress Test Product');

          // Delete the menuboard and assert success
          cy.deleteMenuboard(menuId).then((response) => {
            expect(response.status).to.equal(204);
          });
        });
      });
    });
  });
});
