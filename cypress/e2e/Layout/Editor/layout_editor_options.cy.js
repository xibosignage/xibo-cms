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

describe('Layout Editor', function() {
    beforeEach(function() {
        cy.login();
        cy.visit('/layout/view');
        cy.get('button.layout-add-button').click();
        cy.get('#layout-viewer').should('be.visible');  // Assert that the URL has changed to the layout editor
    });
  
    it('should be able to publish, checkout and discard layout', function() {  
        let layoutId;
        let layoutName;
        
        cy.intercept('GET', '/layout?layoutId=*').as('layoutStatus');
        cy.intercept('PUT', '/layout/discard/*').as('discardLayout');
       
        // Publish layout
        cy.openOptionsMenu();
        cy.get('#publishLayout').click();
        cy.get('button.btn-bb-Publish').click();

        cy.wait('@layoutStatus').then((interception) => {
            expect(interception.response.statusCode).to.eq(200); 
            // Check if the publishedStatus is "Published"
            const layoutData = interception.response.body.data[0]; 
            expect(layoutData).to.have.property('publishedStatus', 'Published');
        });

        // Checkout published layout
        cy.wait(1000);
        cy.openOptionsMenu();
        cy.get('#checkoutLayout').click();

        cy.wait('@layoutStatus').then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            // Check if the publishedStatus is back to "Draft"
            const layoutData = interception.response.body.data[0]; 
            expect(layoutData).to.have.property('publishedStatus', 'Draft');
        });

        // Capture layout name before discarding draft layout
        cy.get('.layout-info-name span')
            .invoke('text')
            .then((name) => {
                layoutName = name.trim().replace(/^"|"$/g, ''); // Remove double quotes
                cy.log(`Layout Name: ${layoutName}`);
                    
                cy.openOptionsMenu();
                cy.get('#discardLayout').click();
                cy.get('button.btn-bb-Discard').click();

                // Verify that the layout has been discarded
                cy.wait('@discardLayout').then((interception) => {
                    expect(interception.response.statusCode).to.equal(200);
                });

                // Check if the user is redirected to the layouts page
                cy.url().should('include', '/layout/view'); 

                // Search for the layout name
                cy.get('input[name="layout"]').clear().type(`${layoutName}{enter}`);
                
                // Check status of the layout with matching layout name
                cy.get('#layouts tbody')
                    .find('tr')
                    .should('contain', layoutName)
                    .should('contain', 'Published');
        });
    
    });

    it('should display an error when publishing an invalid layout', function() {  
        cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');
        cy.intercept('PUT', '/layout/publish/*').as('publishLayout');
        
        // Open widgets toolbox
        cy.openToolbarMenu(0, false);
        cy.get('[data-sub-type="ics-calendar"]').click();
        cy.get('[data-template-id="daily_light"]').click();
        cy.get('.viewer-object').click();

        // Wait for element to be loaded on layout
        cy.wait('@addElement').then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
        });

        // Publish layout
        cy.openOptionsMenu();
        cy.get('#publishLayout').click();
        cy.get('button.btn-bb-Publish').click();

        // Verify response
        cy.wait('@publishLayout').then((interception) => {
            expect(interception.response.statusCode).to.eq(200);
            expect(interception.response.body).to.have.property('message', 'There is an error with this Layout: Missing required property Feed URL');
        });

        // Verify that a toast message is displayed
        cy.get('.toast-message')
            .should('be.visible')
            .and('contain.text', 'There is an error with this Layout');
    });

    it('should be able to create new layout', function() {  
        cy.intercept('GET', '/layout?layoutId=*').as('newLayout');

        // Capture the layout ID of the initial layout loaded
        cy.get('#layout-editor')
            .invoke('attr', 'data-layout-id')
            .then((initialLayoutId) => {

            //Create new layout
            cy.openOptionsMenu();
            cy.get('#newLayout').click();

            cy.wait('@newLayout').then((interception) => {
                expect(interception.response.statusCode).to.eq(200); // Check if the request was successful

                // Get the new layout ID
                cy.get('#layout-editor')
                    .invoke('attr', 'data-layout-id')
                    .then((newLayoutId) => {
                        // Assert that the new layout ID is different from the initial layout ID
                        expect(newLayoutId).to.not.eq(initialLayoutId);
                    });
            });
        });
    });

    it('should be able to unlock layout', function() {  
        let layoutName;

        cy.intercept('GET', '/layout?layoutId=*').as('checkLockStatus');
        cy.intercept('GET', '/playlist/widget/form/edit/*').as('addElement');

        // Capture layout name to navigate back to it after unlocking
        cy.get('.layout-info-name span')
            .invoke('text')
            .then((name) => {
                layoutName = name.trim().replace(/^"|"$/g, ''); 
                cy.log(`Layout Name: ${layoutName}`);
                
                // Open global elements toolbox
                cy.openToolbarMenu(1, false);
                cy.get('[data-template-id="text"]').click();
                cy.get('.viewer-object').click();

                // Wait for element to be loaded on layout
                cy.wait('@addElement').then((interception) => {
                    expect(interception.response.statusCode).to.eq(200);
                });

                // Check for lock status
                cy.wait('@checkLockStatus').then((interception) => {
                    const isLocked = interception.response.body.data[0].isLocked;
                    expect(isLocked).to.not.be.empty;
                    cy.log('isLocked:', isLocked);
                });
                
                cy.intercept('PUT', '/layout/lock/release/*').as('unlock');

                // Unlock layout
                cy.openOptionsMenu();
                cy.get('#unlockLayout').should('be.visible').click();
                cy.get('button.btn-bb-unlock').click();

                // Wait for the release lock request to complete
                cy.wait('@unlock').then((interception) => {
                    expect(interception.response.statusCode).to.equal(200);
                });
                
                // Check if the user is redirected to the /layout/view page
                cy.url().should('include', '/layout/view'); 

                // Search for the layout name
                cy.get('input[name="layout"]').clear().type(`${layoutName}{enter}`);
                cy.get('#layouts tbody tr').should('contain.text', layoutName)
                cy.get('#layouts tbody tr').should('have.length', 1);
                
                cy.openRowMenu();
                cy.get('#layout_button_design').click();
                cy.get('#layout-viewer').should('be.visible');

                // Check for lock status
                cy.wait('@checkLockStatus').then((interception) => {
                    const isLocked = interception.response.body.data[0].isLocked;
                    expect(isLocked).be.empty;
                    cy.log('isLocked:', isLocked);
            });
        });
    });

    it('should enable tooltips', function() {
        cy.wait(1000);
        cy.openOptionsMenu();

        cy.intercept('POST', '/user/pref').as('updatePreferences');

        // Enable tooltips
        // Check the current state of the tooltips checkbox
        cy.get('#displayTooltips').then(($checkbox) => {
            if (!$checkbox.is(':checked')) {
                // Check the checkbox if it is currently unchecked
                cy.wrap($checkbox).click();
                cy.wait('@updatePreferences');

                // Confirm the checkbox is checked
                cy.get('#displayTooltips').should('be.checked');
            } 
        });

        // Verify that tooltips are present
        cy.get('.navbar-nav .btn-menu-option[data-toggle="tooltip"]').each(($element) => {
            // Trigger hover to show tooltip
            cy.wrap($element).trigger('mouseover');
        
            // Check that the tooltip is visible for each button
            cy.get('.tooltip').should('be.visible'); // Expect tooltip to be present
        });

    });

    it('should disable tooltips', function() {
        cy.wait(1000);
        cy.openOptionsMenu();

        cy.intercept('POST', '/user/pref').as('updatePreferences');

        // Disable tooltips
        // Check the current state of the tooltips checkbox
        cy.get('#displayTooltips').then(($checkbox) => {
            if ($checkbox.is(':checked')) {
                // Uncheck the checkbox if it is currently checked
                cy.wrap($checkbox).click();
                cy.wait('@updatePreferences');

                // Confirm the checkbox is now unchecked
                cy.get('#displayTooltips').should('not.be.checked');
            } 
        });

        // Verify that tooltips are gone
        cy.get('.navbar-nav .btn-menu-option[data-toggle="tooltip"]').each(($element) => {
            cy.wrap($element).trigger('mouseover'); // Trigger hover to show tooltip        
            cy.get('.tooltip').should('not.exist'); // Check if tooltip is gone for each button on the toolbox
        });

    });

    it('should enable delete confirmation', function() {
        cy.wait(1000);
        cy.openOptionsMenu();

        cy.intercept('POST', '/user/pref').as('updatePreferences');

        // Check the current state of the delete confirmation checkbox
        cy.get('#deleteConfirmation').then(($checkbox) => {
            if (!$checkbox.is(':checked')) {
                // Check the checkbox if it is currently unchecked
                cy.wrap($checkbox).click();
                cy.wait('@updatePreferences');

                // Confirm the checkbox is checked
                cy.get('#deleteConfirmation').should('be.checked');
            } 
        });

        // Add an element then attempt to delete
        cy.openToolbarMenu(0, false);
        cy.get('[data-sub-type="clock"]').click();
        cy.get('[data-sub-type="clock-analogue"]').click();
        cy.get('.viewer-object').click();
        cy.get('#delete-btn').click();

        // Verify that delete confirmation modal appears
        cy.get('.modal-content')
            .should('be.visible')
            .and('contain.text', 'Delete Widget');
    });

    it('should disable delete confirmation', function() {
        cy.wait(1000);
        cy.openOptionsMenu();

        cy.intercept('POST', '/user/pref').as('updatePreferences');

        // Check the current state of the delete confirmation checkbox
        cy.get('#deleteConfirmation').then(($checkbox) => {
            if ($checkbox.is(':checked')) {
                // Uncheck the checkbox if it is currently checked
                cy.wrap($checkbox).click();
                cy.wait('@updatePreferences');

                // Confirm the checkbox is now unchecked
                cy.get('#displayTooltips').should('not.be.checked');
            } 
        });

        cy.intercept('DELETE', '/region/*').as('deleteElement');

        // Add an element then attempt to delete
        cy.openToolbarMenu(0, false);
        cy.get('[data-sub-type="clock"]').click();
        cy.get('[data-sub-type="clock-analogue"]').click();
        cy.get('.viewer-object').click();
        cy.get('#delete-btn').click();

        // Verify that the widget is immediately deleted without confirmation
        cy.wait('@deleteElement').then((interception) => {
            expect(interception.response.statusCode).to.equal(200);
        });

        cy.get('.viewer-object').within(() => {
            cy.get('[data-type="region"]').should('not.exist');
        });
    });
});