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
    // Create and alias for load folders
    cy.intercept('/library?*').as('mediaLoad');
    cy.intercept('/user/pref').as('userPref');

    // Go to library
    cy.visit('/library/view');
    cy.get('#media').type('child_folder_media');

    cy.wait('@mediaLoad');
    cy.get('#libraryItems tbody tr').should('have.length', 1);
    cy.wait('@mediaLoad');
    cy.wait('@userPref');
    cy.get('#datatable-container').should('contain', 'child_folder_media');

    cy.get('#libraryItems tr:first-child .dropdown-toggle').click();
    cy.get('#libraryItems tr:first-child .library_button_selectfolder').click();

    cy.get('#container-folder-form-tree>ul>li>i').click();
    cy.get('#container-folder-form-tree>ul>li:not(.jstree-loading)>i').click();
    cy.contains('ChildFolder').click();
    cy.get('.save-button').click();
  });

  it('Sharing', () => {
    // Create and alias for load folders
    cy.intercept('/folders').as('loadFolders');

    // Create and alias for load user permissions for folders
    cy.intercept('/user/permissions/Folder/*').as('permissionsFolders');

    cy.visit('/folders/view');

    cy.wait('@loadFolders');

    cy.contains('ShareFolder').rightclick();
    cy.get('ul.jstree-contextmenu >li:nth-child(6) > a').click(); // Click on Share Link
    cy.get('#name').type('folder_user');

    cy.wait('@permissionsFolders');

    cy.get('#permissionsTable tbody tr').should('have.length', 1);
    cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(1)').contains('folder_user');
    cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(2)> input').click();
    cy.get('.save-button').click();
  });

  it('Set Home Folders for a user', () => {
    // Create and alias for load users
    cy.intercept('/user*').as('loadUsers');

    cy.visit('/user/view');
    cy.get('#userName').type('folder_user');

    cy.wait('@loadUsers');
    cy.get('#users tbody tr').should('have.length', 1);
    cy.get('#users tr:first-child .dropdown-toggle').click();
    cy.get('#users tr:first-child .user_button_set_home').click();
    cy.get('#home-folder').should('be.visible');
    cy.contains('FolderHome').click({force: true} );
    cy.get('.save-button').click();

    // Check
    cy.visit('/user/view');
    cy.get('#userName').clear();
    cy.get('#userName').type('folder_user');

    cy.wait('@loadUsers');
    cy.get('#users tbody tr').should('have.length', 1);
    cy.get('#users tbody tr:nth-child(1) td:nth-child(1)').contains('folder_user');
    cy.get('#users tbody tr:nth-child(1) td:nth-child(3)').contains('FolderHome');
  });

  it('Remove an empty folder', () => {
    // Create and alias for load folders
    cy.intercept('/folders').as('loadFolders');

    cy.visit('/folders/view');
    cy.contains('EmptyFolder').rightclick();
    cy.contains('Remove').click();

    cy.visit('/folders/view');
    cy.wait('@loadFolders');
    cy.contains('EmptyFolder').should('not.exist');
  });

  it('cannot remove a folder with content', () => {
    // Create and alias for load folders
    cy.intercept('/folders').as('loadFolders');

    cy.visit('/folders/view');
    cy.contains('FolderWithContent').rightclick();
    cy.contains('Remove').click();

    // Check folder still exists
    cy.visit('/folders/view');

    cy.wait('@loadFolders');
    cy.contains('FolderWithContent').should('exist');
  });

  it('search a media in a folder', () => {
    // Create and alias for load folders
    cy.intercept('/folders').as('loadFolders');
    cy.intercept('/library?*').as('mediaLoad');
    cy.intercept('/user/pref').as('userPref');

    // Go to library
    cy.visit('/library/view');

    cy.wait('@loadFolders');
    cy.wait('@mediaLoad');
    cy.wait('@userPref');

    // Click on All folders
    cy.get('#folder-tree-clear-selection-button').click();
    cy.get('.jstree-ocl').click();
    cy.contains('FolderWithImage').click();

    cy.get('#libraryItems tbody tr').should('have.length', 1);
    cy.get('#libraryItems tbody').contains('media_for_search_in_folder');
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
    // Move a folder (MoveFromFolder) to another (MoveToFolder) and merge
    cy.intercept('/library?*').as('mediaLoad');
    // Create and alias for load folders
    cy.intercept('/folders').as('loadFolders');

    // Go to folders
    cy.visit('/folders/view');
    cy.contains('MoveFromFolder').rightclick();
    cy.contains('Move Folder').click();

    cy.get('#container-folder-form-tree>ul>li>i').click();
    cy.get('#container-folder-form-tree>ul>li:not(.jstree-loading)>i').click();
    cy.contains('MoveToFolder').click({force: true});
    cy.get('.form-check input').click();
    cy.get('.save-button').click();

    // Validate test34 image exist in MoveToFolder
    cy.visit('/folders/view');

    cy.wait('@loadFolders');
    cy.contains('MoveFromFolder').should('not.exist');

    // Validate test34 image exist in MoveToFolder
    // Go to library
    cy.visit('/library/view');
    cy.wait('@mediaLoad');
    cy.wait('@loadFolders');

    // Click on All folders from Folder Tree
    cy.get('#folder-tree-clear-selection-button').click();
    cy.get('.jstree-ocl').click();
    cy.contains('MoveToFolder').click();

    cy.wait('@mediaLoad');
    cy.get('#libraryItems tbody').contains('test34');
  });
});
