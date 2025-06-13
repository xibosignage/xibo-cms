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

describe('Playlist Editor (Populated)', function() {

    beforeEach(function() {
        cy.login();

        // Create random name
        let uuid = Cypress._.random(0, 1e9);

        // Create a new layout and go to the layout's designer page
        cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {

            // Populate playlist with some widgets and media
            cy.addWidgetToPlaylist(res, 'embedded', {
                name: 'Embedded Widget'
            });

            cy.addMediaToLibrary("file/example.zip");

            cy.addWidgetToPlaylist(res, 'clock', {
                name: 'Clock Widget'
            });

            cy.openPlaylistEditorAndLoadPrefs(res);
        });
    });

    it('changes and saves widget properties', () => {
        // Create and alias for reload widget
        // cy.intercept('GET','/playlist/widget/form/edit/*').as('reloadWidget');

        // Select the first widget on timeline ( image )
        cy.get('#timeline-container [data-type="widget"]').first().click();

        // Wait for the widget to load
        // cy.wait('@reloadWidget');

        // Type the new name in the input
        cy.get('a[href="#advancedTab"]').click();
        cy.get('#properties-panel-form-container input[name="name"]').clear().type('newName');

        // Set a duration
        cy.get('#properties-panel-form-container input[name="useDuration"]').check();
        cy.get('#properties-panel-form-container input[name="duration"]').clear().type(12);

        // Save form
        cy.get('#properties-panel-form-container button[data-action="save"]').click();

        // Should show a notification for the name change
        // cy.get('.toast-success');

        // Wait for the widget to reload
        // cy.wait('@reloadWidget');

        // Check if the values are the same entered after reload
        cy.get('#properties-panel-form-container input[name="name"]').should('have.prop', 'value').and('equal', 'newName');
        cy.get('#properties-panel-form-container input[name="duration"]').should('have.prop', 'value').and('equal', '12');

    });

    it.skip('should revert a saved form to a previous state', () => {

        let oldName;

        // Create and alias for reload widget
        // cy.intercept('GET', '/playlist/widget/form/edit/*').as('reloadWidget');
        // cy.intercept('PUT', '/playlist/widget/*').as('saveWidget');

        // Select the first widget on timeline ( image )
        cy.get('#timeline-container [data-type="widget"]').first().click();

        // Wait for the widget to load
        // cy.wait('@reloadWidget');

        // Get the input field
        cy.get('a[href="#advancedTab"]').click();
        cy.get('#properties-panel-form-container input[name="name"]').then(($input) => {

            // Save old name
            oldName = $input.val();

            //Type the new name in the input
            cy.get('#properties-panel-form-container input[name="name"]').clear().type('newName');

            // Save form
            cy.get('#properties-panel-form-container button[data-action="save"]').click();

            // Should show a notification for the name change
            // cy.get('.toast-success');

            // Wait for the widget to save
            // cy.wait('@reloadWidget');

            // Click the revert button
            cy.get('#playlist-editor-toolbar #undoContainer').click();

            // Wait for the widget to save
            // cy.wait('@saveWidget');

            // Test if the revert made the name go back to the old name
            cy.get('#properties-panel-form-container input[name="name"]').should('have.prop', 'value').and('equal', oldName);
        });
    });

    it.skip('should delete a widget using the toolbar bin', () => {
        // cy.intercept('/playlist?playlistId=*').as('reloadPlaylist');

        // Select a widget from the navigator
        cy.get('#playlist-timeline [data-type="widget"]').first().click().then(($el) => {

            const widgetId = $el.attr('id');

            // Click trash container
            cy.get('div[class="widgetDelete"]').first().click();

            // Confirm delete on modal
            cy.get('button[class*="btn-bb-confirm"]').click();

            // Check toast message
            // cy.get('.toast-success').contains('Deleted');

            // Wait for the layout to reload
            // cy.wait('@reloadPlaylist');

            // Check that widget is not on timeline
            cy.get('#playlist-timeline [data-type="widget"]#' + widgetId).should('not.exist');
        });
    });

    it('should add an audio clip to a widget by the context menu, and adds a link to open the form in the timeline', () => {
        
        cy.populateLibraryWithMedia();

        // Create and alias for reload playlist
        cy.intercept('/playlist?playlistId=*').as('reloadPlaylist');

        // Right click to open the context menu and select add audio
        cy.get('#timeline-container [data-type="widget"]').first().should('be.visible').rightclick();
        cy.get('.context-menu-btn[data-property="Audio"]').should('be.visible').click();

        // Select the 1st option
        cy.get('[data-test="widgetPropertiesForm"] #mediaId > option').eq(1).then(($el) => {
            cy.get('[data-test="widgetPropertiesForm"] #mediaId').select($el.val());
        });

        // Save and close the form
        cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

        // Check if the widget has the audio icon
        // cy.wait('@reloadPlaylist');
        cy.get('#timeline-container [data-type="widget"]:first-child')
            .find('i[data-property="Audio"]').click();

        cy.get('[data-test="widgetPropertiesForm"]').contains('Audio for');
    });

    // Skip test for now ( it's failing in the test suite and being tested already in layout designer spec ) 
    it('attaches expiry dates to a widget by the context menu, and adds a link to open the form in the timeline', () => {
        // Create and alias for reload playlist
        // cy.intercept('/playlist?playlistId=*').as('reloadPlaylist');
        
        // Right click to open the context menu and select add audio
        cy.get('#timeline-container [data-type="widget"]').first().should('be.visible').rightclick();
        cy.get('.context-menu-btn[data-property="Expiry"]').should('be.visible').click();

        // Add dates
        cy.get('[data-test="widgetPropertiesForm"] .starttime-control .date-clear-button').click();
        // cy.get('[data-test="widgetPropertiesForm"] #fromDt').find('input[class="datePickerHelper form-control dateControl dateTime active"]').click();
        cy.get('div[class="flatpickr-wrapper"]').first().click();
        cy.get('.flatpickr-calendar.open .dayContainer .flatpickr-day:first').click();

        cy.get('[data-test="widgetPropertiesForm"] .endtime-control .date-clear-button').click();
        // cy.get('[data-test="widgetPropertiesForm"] #toDt').find('input[class="datePickerHelper form-control dateControl dateTime active"]').click();
        cy.get('div[class="flatpickr-wrapper"]').last().click();
        cy.get('.flatpickr-calendar.open .dayContainer .flatpickr-day:first').click();


        // Save and close the form
        cy.get('[data-test="widgetPropertiesForm"] .btn-bb-done').click();

        // Check if the widget has the expiry dates icon
        // cy.wait('@reloadPlaylist');
        cy.get('#timeline-container [data-type="widget"]:first-child')
            .find('i[data-property="Expiry"]').click();

        cy.get('[data-test="widgetPropertiesForm"]').contains('Expiry for');
    });
});