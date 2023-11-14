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
describe('Folders', function() {
  beforeEach(function() {
    cy.login();
  });

  it('creating a new folder and rename it', () => {
    cy.visit('/folders/view');
    cy.contains('Root Folder').rightclick();
    cy.contains('Create').should('be.visible').click();

    cy.visit('/folders/view');
    cy.contains('New Folder').should('be.visible').rightclick();
    cy.contains('Rename').type('Folder123{enter}');
  });

  it('Moving an image from Root Folder to another folder', () => {
    cy.intercept({
      url: '/library?*',
      query: {media: 'child_folder_media'},
    }).as('mediaGridLoadAfterSearch');

    // Go to library
    cy.visit('/library/view');

    cy.get('#media').type('child_folder_media');

    // Wait for the search to complete
    cy.wait('@mediaGridLoadAfterSearch');

    cy.get('#libraryItems tbody tr').should('have.length', 1);
    cy.get('#datatable-container').should('contain', 'child_folder_media');

    // Click the dropdown menu and choose a folder to move the image to
    cy.get('#libraryItems tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#libraryItems tr:first-child .library_button_selectfolder').click({force: true});

    // Expand the folder tree and select ChildFolder
    cy.get('#container-folder-form-tree>ul>li>i').click();
    cy.get('#container-folder-form-tree>ul>li:not(.jstree-loading)>i').click();
    cy.contains('ChildFolder').click();

    // Click the save button
    cy.get('.save-button').click();
  });

  it('Sharing', () => {
    // Create and alias for load user permissions for folders
    cy.intercept({
      url: '/user/permissions/Folder/*',
      query: {name: 'folder_user'},
    }).as('permissionsFoldersAfterSearch');

    cy.visit('/folders/view');

    cy.contains('ShareFolder').should('be.visible').rightclick();
    cy.get('ul.jstree-contextmenu >li:nth-child(6) > a').click(); // Click on Share Link
    cy.get('#name').type('folder_user');

    cy.wait('@permissionsFoldersAfterSearch');

    cy.get('#permissionsTable tbody tr').should('have.length', 1);
    cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(1)').contains('folder_user');
    cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(2)> input').click();
    cy.get('.save-button').click();
  });

  it('Set Home Folders for a user', () => {
    // Create and alias for load users
    cy.intercept({
      url: '/user*',
      query: {userName: 'folder_user'},
    }).as('userGridLoadAfterSearch');

    cy.visit('/user/view');
    cy.get('#userName').type('folder_user');

    cy.wait('@userGridLoadAfterSearch');
    cy.get('#users tbody tr').should('have.length', 1);
    cy.get('#users tr:first-child .dropdown-toggle').click({force: true});
    cy.get('#users tr:first-child .user_button_set_home').click({force: true});
    cy.get('#home-folder').should('be.visible');
    cy.get('.jstree-anchor:contains(\'FolderHome\')').should('be.visible').click();
    cy.get('.save-button').click();

    // Check
    cy.visit('/user/view');
    cy.get('#userName').clear();
    cy.get('#userName').type('folder_user');
    cy.wait('@userGridLoadAfterSearch');

    cy.get('#users tbody tr').should('have.length', 1);
    cy.get('#users tbody tr:nth-child(1) td:nth-child(1)').contains('folder_user');
    cy.get('#users tbody tr:nth-child(1) td:nth-child(3)').contains('FolderHome');
  });

  it('Remove an empty folder', () => {
    cy.visit('/folders/view');
    // Find the EmptyFolder element and right-click on it
    cy.get('.jstree-anchor:contains(\'EmptyFolder\')')
      .rightclick()
      .should('have.class', 'jstree-hovered'); // Ensure the right-click effect

    // Find the context menu item with "Remove" text and click on it
    cy.contains('Remove').click();

    // Validate
    cy.visit('/folders/view');
    cy.get('.jstree-anchor:contains(\'EmptyFolder\')').should('not.exist');
  });

  it('cannot remove a folder with content', () => {
    cy.visit('/folders/view');
    cy.get('.jstree-anchor:contains(\'FolderWithContent\')')
      .rightclick();

    // Find the context menu item with "Remove" text and click on it
    cy.contains('Remove').click();

    // Check folder still exists
    cy.visit('/folders/view');
    cy.get('.jstree-anchor:contains(\'FolderWithContent\')').should('exist');
  });

  it('search a media in a folder', () => {
    // Go to library
    cy.visit('/library/view');
    cy.get('.jstree-anchor:contains(\'Root Folder\')')
      .should('be.visible') // Ensure the element is visible
      .parent()
      .find('.jstree-icon.jstree-ocl')
      .click();

    cy.get('.jstree-anchor:contains(\'FolderWithImage\')').click();
    cy.get('#libraryItems tbody tr').should('have.length', 1);
    cy.get('#libraryItems tbody').contains('media_for_search_in_folder')
      .should('be.visible');
  });

  it('Hide Folder tree', () => {
    // Go to library
    cy.visit('/library/view');
    // The Folder tree is open by default on a grid
    cy.get('#folder-tree-select-folder-button').click();
    // clicking on the folder icon hides it
    cy.get('#grid-folder-filter').should('have.css', 'display', 'none');
  });

  it('Move folders and Merge', () => {
    // Go to folders
    cy.visit('/folders/view');
    cy.get('.jstree-anchor:contains(\'MoveFromFolder\')').rightclick();
    cy.contains('Move Folder').click();
    cy.get('#container-folder-form-tree').within(() => {
      // Find the "MoveToFolder" link and click it
      cy.contains('MoveToFolder').click();
    });
    cy.get('#merge').should('be.visible').check();
    cy.get('.save-button').click();

    // Validate test34 image exist in MoveToFolder
    cy.visit('/folders/view');
    cy.get('.jstree-anchor:contains(\'MoveFromFolder\')').should('not.exist');

    // Validate test34 image exist in MoveToFolder
    // Go to library
    cy.visit('/library/view');

    cy.get('.jstree-anchor:contains(\'Root Folder\')')
      .should('be.visible') // Ensure the element is visible
      .parent()
      .find('.jstree-icon.jstree-ocl')
      .click();
    cy.get('.jstree-anchor:contains(\'MoveToFolder\')').click();
    cy.get('#libraryItems tbody').contains('test34');
  });
});
