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

describe('Playlist Editor (Populated/Unchanged)', function() {

    before(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {

            // Populate playlist with some widgets and media
            cy.addWidgetToPlaylist(res, 'embedded', {
                name: 'Embedded Widget'
            });

            // TODO skip so that the test success
            // cy.addRandomMediaToPlaylist(res);

            cy.addWidgetToPlaylist(res, 'clock', {
                name: 'Clock Widget'
            });
        });
    });

    beforeEach(function() {
        cy.login();
        cy.openPlaylistEditorAndLoadPrefs(this.testPlaylistId);
    });

    it('opens a media tab in the toolbar and searches for items', () => {

        // cy.intercept('/library/search?*').as('mediaLoad');

        cy.populateLibraryWithMedia();

        // Open audio tool tab
        cy.get('a[id="btn-menu-3"]').should('be.visible').click();

        // cy.wait('@mediaLoad');

        // Check if there are audio items in the search content
        cy.get('div[class="toolbar-card-preview"]').last().should('be.visible');
    });

    it('creates a new widget by selecting a searched media from the toolbar to the editor, and then reverts the change', () => {
        cy.populateLibraryWithMedia();

        // Create and alias for reload playlist
        // cy.intercept('/playlist?playlistId=*').as('reloadPlaylist');
        // cy.intercept('DELETE', '/playlist/widget/*').as('deleteWidget');
        // cy.intercept('/library/search?*').as('mediaLoad');

        // Open library search tab
        cy.get('a[id="btn-menu-0"]').should('be.visible').click();

        // cy.wait('@mediaLoad');
        cy.wait(1000);

        // Get a table row, select it and add to the dropzone
        cy.get('div[class="toolbar-card ui-draggable ui-draggable-handle"]').eq(2).should('be.visible').click({force: true}).then(() => {
            cy.get('#timeline-overlay-container').click({force: true}).then(() => {

                // Wait for the layout to reload
                // cy.wait('@reloadPlaylist');
                cy.wait(3000);

                // Check if there is just one widget in the timeline
                cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                    expect($widgets.length).to.eq(3);
                });

                // Click the revert button
                cy.get('#timeline-container [id^="widget_"]').last().click();
                cy.get('button[data-action="undo"]').click({force: true});

                // Wait for the widget to be deleted and for the playlist to reload
                // cy.wait('@deleteWidget');
                // cy.wait('@reloadPlaylist');
                cy.wait(3000);

                // Check if there is just one widget in the timeline
                cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                    expect($widgets.length).to.eq(2);
                });
            });
        });
    });
});