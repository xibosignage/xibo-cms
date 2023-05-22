/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
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

describe('Folders', function () {

    beforeEach(function () {
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

    // TODO Seed a folder name ChildFolder
    // TODO Seed an image child_folder_media
    it('Moving an image from Root Folder to another folder', () => {
        // Go to library
        cy.visit('/library/view');

        // Create and alias for load folders
        cy.server();
        cy.route('/library?*').as('mediaLoad');
        cy.get('#media').type('child_folder_media');

        cy.wait('@mediaLoad');
        cy.wait(1000);

        cy.get('#libraryItems tr:first-child .dropdown-toggle').click();
        cy.get('#libraryItems tr:first-child .library_button_selectfolder').click();

        cy.get('#container-folder-form-tree>ul>li>i').click();
        cy.get('#container-folder-form-tree>ul>li:not(.jstree-loading)>i').click();
        cy.contains('ChildFolder').click();
        cy.get('.save-button').click();
    });

    it('Sharing', () => {
        cy.visit('/folders/view');

        cy.server();

        // Create and alias for load folders
        cy.route('/folders').as('loadFolders');

        // Create and alias for load user permissions for folders
        cy.route('/user/permissions/Folder/*').as('permissionsFolders');

        cy.wait('@loadFolders');
        cy.wait(1000);

        cy.contains('London').rightclick();
        cy.get('ul.jstree-contextmenu >li:nth-child(6) > a').click(); // Click on Share Link
        cy.get('#name').type('xibo_user2');

        cy.wait('@permissionsFolders');

        cy.get('#permissionsTable tbody tr').should('have.length', 1);
        cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(1)').contains('xibo_user2');
        cy.get('#permissionsTable tbody tr:nth-child(1) td:nth-child(2)> input').click();
        cy.get('.save-button').click();
    });



    // TODO SEED a user folder_home_user
    // TODO SEED a folder name `FolderHome`
    it('Set Home Folders for a user', () => {

        cy.server();
        cy.visit('/user/view');
        // Create and alias for load users
        cy.route('/user*').as('loadUsers');
        cy.get('#userName').type('xibo_user2'); // TODO

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
        cy.get('#userName').type('xibo_user2');

        cy.wait('@loadUsers');
        cy.get('#users tbody tr').should('have.length', 1);
        cy.get('#users tbody tr:nth-child(1) td:nth-child(1)').contains('xibo_user2');
        cy.get('#users tbody tr:nth-child(1) td:nth-child(3)').contains('FolderHome');
    });

    // TODO Seed a folder name EmptyFolder
    it('Remove an empty folder', () => {

        cy.visit('/folders/view');
        cy.contains('EmptyFolder').rightclick();
        cy.contains('Remove').click();
        cy.visit('/folders/view');

        // Create and alias for load folders
        cy.server();
        cy.route('/folders').as('loadFolders');

        cy.wait('@loadFolders');
        cy.wait(1000);
        cy.contains('EmptyFolder').should('not.exist');
    });

    // TODO SEED a folder FolderWithContent and an image media_for_not_empty_folder
    it('Remove a folder with content', () => {
        cy.visit('/folders/view');
        cy.contains('FolderWithContent').rightclick();
        cy.contains('Remove').click();

        // Check folder still exists
        cy.visit('/folders/view');

        // Create and alias for load folders
        cy.server();
        cy.route('/folders').as('loadFolders');

        cy.wait('@loadFolders');
        cy.wait(1000);
        cy.contains('FolderWithContent').should('exist');
    });

    // Todo SEED a folder FolderWithImage and an image media_for_search_in_folder
    // Todo SEED a folder FolderWithImage and an image media_for_search_in_folder
    // TODO FolderWithImage should have an image called media_for_search_in_folder
    // Search a media in a folder
    it('Folder Search', () => {
        // Go to library
        cy.visit('/library/view');

        // Create and alias for load folders
        cy.server();
        cy.route('/folders').as('loadFolders');

        cy.wait('@loadFolders');
        cy.wait(1000);

        // Click on All folders
        cy.get('#folder-tree-clear-selection-button').click();
        cy.get('.jstree-ocl').click();
        cy.contains('FolderWithImage').click();

        cy.get('#libraryItems tbody tr').should('have.length', 1);
        cy.get('#media').type('media_for_search_in_folder');
        cy.get('#libraryItems tbody tr:nth-child(1) td:nth-child(2)').contains('media_for_search_in_folder');
    });

    // Hide Folder tree
    it('Hide Folder tree', () => {
        // Go to library
        cy.visit('/library/view');
        // The Folder tree is open by default on a grid
        cy.get('#folder-tree-select-folder-button').click();
        // clicking on the folder icon hides it
        cy.get('#grid-folder-filter').should('have.css', 'display', 'none');
    });

    // Seed a folder MoveToFolder with an image test12
    // Seed another folder MoveFromFolder with an image test34
    // Move folder MoveFromFolder to MoveToFolder
    it('Move Folders and Merge', () => {

        // Create and alias for load folders
        cy.server();
        cy.route('/library?*').as('mediaLoad');
        cy.route('/folders').as('loadFolders');

        cy.visit('/folders/view');
        cy.contains('MoveFromFolder').rightclick();
        cy.contains('Move Folder').click();

        cy.get('#container-folder-form-tree>ul>li>i').click();
        cy.get('#container-folder-form-tree>ul>li:not(.jstree-loading)>i').click();
        cy.contains('MoveToFolder').click({force: true});
        cy.get('.form-check input').click();
        cy.get('.save-button').click();

        // Check test34 exist in MoveToFolder
        cy.visit('/folders/view');

        cy.wait('@loadFolders');
        cy.wait(1000);
        cy.contains('MoveFromFolder').should('not.exist');

        // Go to library
        cy.visit('/library/view');

        cy.wait('@loadFolders');
        cy.wait(1000);

        // Click on All folders from Folder Tree
        cy.get('#folder-tree-clear-selection-button').click();
        cy.get('.jstree-ocl').click();
        cy.contains('MoveToFolder').click();
        cy.get('#libraryItems tbody tr').should('have.length', 2);
        cy.get('#media').type('test34');

        cy.wait('@mediaLoad');
        cy.wait(1000);
        cy.get('#libraryItems tbody tr:nth-child(1) td:nth-child(2)').contains('test34');
    });
});
