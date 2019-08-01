describe('Playlist Editor', function() {

    /**
     * Open playlist editor modal and wait for toolbar user prefs to load
     * @param {String} playlistName
     */
    function openPlaylistEditorAndLoadPrefs(playlistName, playlistId) {

        cy.server();
        cy.route('/user/pref?preference=toolbar').as('userPrefsLoad');
        cy.route('/playlist?draw=2&*').as('playlistGridSearch');

        // Reload playlist table page
        cy.visit('/playlist/view');

        // Clear toolbar preferences
        cy.clearToolbarPrefs();

        // Filter for the created playlist
        cy.get('#Filter input[name="name"]').type(playlistName);

        // Wait for the filter to make effect ( debounce + grid reload )
        cy.wait('@playlistGridSearch');

        cy.get('[href="/playlist/form/timeline/' + playlistId + '"]').click({force: true});

        // Wait for user prefs to load
        cy.wait('@userPrefsLoad');

    }

    /**
     * Add media items to library
     */
    function populateLibraryWithMedia() {
        // Add audio media to library
        cy.addMediaToLibrary('../assets/audioSample.mp3');
    }

    /**
     * Drag one element to another one
     * @param {string} draggableSelector 
     * @param {string} dropableSelector 
     */
    function dragToElement(draggableSelector, dropableSelector) {

        return cy.get(dropableSelector).then(($el) => {
            let position = {
                x: $el.offset().left + $el.width() / 2 + window.scrollX,
                y: $el.offset().top + $el.height() / 2 + window.scrollY
            };

            cy.get(draggableSelector).invoke('show');

            cy.get(draggableSelector)
                .trigger('mousedown', {
                    which: 1
                })
                .trigger('mousemove', {
                    which: 1,
                    pageX: position.x,
                    pageY: position.y
                })
                .trigger('mouseup');
        });
    }

    beforeEach(function() {
        cy.login();
    });

    context('Empty Playlist', function() {

        beforeEach(function() {
            // Create random name
            const uuid = Cypress._.random(0, 1e9);

            // Create a new layout and go to the layout's designer page
            cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {
                openPlaylistEditorAndLoadPrefs(uuid, res);
            });
        });

        afterEach(function() {
            // Remove the created layout
            cy.deletePlaylist(this.testPlaylistId);
        });

        it('should show the droppable zone and toolbar', function() {

            cy.get('#dropzone-container').should('be.visible');
            cy.get('#playlist-editor-toolbar nav').should('be.visible');
        });

        it('creates a new widget by dragging a widget from the toolbar to the editor', () => {

            // Create and alias for reload playlist
            cy.server();
            cy.route('POST', '**/playlist/widget/embedded/*').as('createWidget');

            // Open toolbar Widgets tab
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Widgets').should('be.visible').click();

            cy.get('#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"]').should('be.visible').then(() => {
                dragToElement(
                    '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="embedded"] .drag-area',
                    '#dropzone-container'
                ).then(() => {
                    // Wait for the widget to be added
                    cy.wait('@createWidget');

                    // Check if there is just one widget in the timeline
                    cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                        expect($widgets.length).to.eq(1);
                    });
                });
            });

        });

        it('creates a new widget by selecting a searched media from the toolbar to the editor', () => {

            populateLibraryWithMedia();

            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');
            cy.route('/library?assignable=*').as('mediaLoad');

            // Open a new tab
            cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();

            cy.wait('@mediaLoad');

            // Select and search image items
            cy.get('.toolbar-pane.active .input-type').select('image');

            cy.wait('@mediaLoad');

            // Get a table row, select it and add to the dropzone
            cy.get('#playlist-editor-toolbar .media-table .assignItem:first').click().then(() => {
                cy.get('#dropzone-container').click({force: true}).then(() => {

                    // Wait for the layout to reload
                    cy.wait('@reloadPlaylist');

                    // Check if there is just one widget in the timeline
                    cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                        expect($widgets.length).to.eq(1);
                    });
                });
            });
        });
        
        it('reverts a create widget change', () => {

            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');
            cy.route('DELETE', '/playlist/widget/*').as('deleteWidget');

            // Open a new tab
            cy.get('#playlist-editor-toolbar #btn-menu-new-tab').click();

            // Select and search image items
            cy.get('.toolbar-pane.active .input-type').select('image');

            // Get a table row, select it and add to the dropzone
            cy.get('#playlist-editor-toolbar .media-table .assignItem:first').click().then(() => {
                cy.get('#dropzone-container').click({force: true}).then(() => {

                    // Wait for the layout to reload
                    cy.wait('@reloadPlaylist');

                    // Check if there is just one widget in the timeline
                    cy.get('#timeline-container [data-type="widget"]').then(($widgets) => {
                        expect($widgets.length).to.eq(1);
                    });

                    // Click the revert button
                    cy.get('#playlist-editor-toolbar #undoContainer').click();

                    // Wait for the widget to be deleted and for the playlist to reload
                    cy.wait('@deleteWidget');
                    cy.wait('@reloadPlaylist');

                    cy.get('#dropzone-container').should('be.visible');
                   
                });
            });
        });
    });

    context('Populated Playlist', function() {

        beforeEach(function() {
            // Create random name
            const uuid = Cypress._.random(0, 1e9);

            // Create a new layout and go to the layout's designer page
            cy.createNonDynamicPlaylist(uuid).as('testPlaylistId').then((res) => {

                // Populate playlist with some widgets and media
                cy.addWidgetToPlaylist(res, 'embedded', {
                    name: 'Embedded Widget'
                });

                cy.addRandomMediaToPlaylist(res);

                cy.addWidgetToPlaylist(res, 'clock', {
                    name: 'Clock Widget'
                });

                openPlaylistEditorAndLoadPrefs(uuid, res);
            });
        });

        afterEach(function() {
            // Remove the created layout
            cy.deletePlaylist(this.testPlaylistId);
        });

        it('changes and saves widget properties', () => {
            // Create and alias for reload widget
            cy.server();
            cy.route('/playlist/widget/form/edit/*').as('reloadWidget');

            // Select the first widget on timeline ( image )
            cy.get('#timeline-container [data-type="widget"]:first-child').click();

            // Wait for the widget to load
            cy.wait('@reloadWidget');

            // Type the new name in the input
            cy.get('#properties-panel-container input[name="name"]').clear().type('newName');

            // Set a duration
            cy.get('#properties-panel-container #useDuration').check();
            cy.get('#properties-panel-container input[name="duration"]').clear().type(12);

            // Save form
            cy.get('#properties-panel-container button[data-action="save"]').click();

            // Should show a notification for the name change
            cy.get('.toast-success');

            // Wait for the widget to reload
            cy.wait('@reloadWidget');

            // Check if the values are the same entered after reload
            cy.get('#properties-panel-container input[name="name"]').should('have.attr', 'value').and('equal', 'newName');
            cy.get('#properties-panel-container input[name="duration"]').should('have.attr', 'value').and('equal', '12');

        });

        it('should revert a saved form to a previous state', () => {

            let oldName;

            // Create and alias for reload widget
            cy.server();
            cy.route('/playlist/widget/form/edit/*').as('reloadWidget');
            cy.route('PUT', '/playlist/widget/*').as('saveWidget');

            // Select the first widget on timeline ( image )
            cy.get('#timeline-container [data-type="widget"]:first-child').click();

            // Wait for the widget to load
            cy.wait('@reloadWidget');

            // Get the input field
            cy.get('#properties-panel-container input[name="name"]').then(($input) => {

                // Save old name
                oldName = $input.val();

                //Type the new name in the input
                cy.get('#properties-panel-container input[name="name"]').clear().type('newName');

                // Save form
                cy.get('#properties-panel-container button[data-action="save"]').click();

                // Should show a notification for the name change
                cy.get('.toast-success');

                // Wait for the widget to save
                cy.wait('@reloadWidget');

                // Click the revert button
                cy.get('#playlist-editor-toolbar #undoContainer').click();

                // Wait for the widget to save
                cy.wait('@saveWidget');

                // Test if the revert made the name go back to the old name
                cy.get('#properties-panel-container input[name="name"]').should('have.attr', 'value').and('equal', oldName);
            });
        });

        // Test skipped due to Cypress not being able to handle vertical drag and drop without scrolling, which leads to unexpected behaviours ( https://github.com/cypress-io/cypress/issues/2418 )
        it.skip('saves the widgets order when sorting by dragging', () => {
            cy.server();
            cy.route('POST', '**/playlist/order/*').as('saveOrder');
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            cy.get('#timeline-container [data-type="widget"]:first-child').then(($oldWidget) => {

                const offsetY = 40;

                // Move to the second widget position ( plus offset )
                cy.wrap($oldWidget)
                    .trigger('mousedown', {
                        which: 1
                    })
                    .trigger('mousemove', {
                        which: 1,
                        pageY: $oldWidget.position().top + offsetY
                    })
                    .trigger('mouseup');

                cy.wait('@saveOrder');

                // Should show a notification for the order change
                cy.get('.toast-success').contains('Order Changed');

                // Reload playlist and check if the new first widget has a different Id
                cy.wait('@reloadPlaylist');

                cy.get('#timeline-container [data-type="widget"]:first-child').then(($newWidget) => {
                    expect($oldWidget.attr('id')).not.to.eq($newWidget.attr('id'));
                });
            });
        });

        // Test skipped due to Cypress not being able to handle vertical drag and drop without scrolling, which leads to unexpected behaviours ( https://github.com/cypress-io/cypress/issues/2418 )
        it.skip('should revert the widgets order when using the undo feature', () => {
            cy.server();
            cy.route('POST', '**/playlist/order/*').as('saveOrder');
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            cy.get('#timeline-container [data-type="widget"]:first-child').then(($oldWidget) => {

                const offsetY = 40;

                // Move to the second widget position ( plus offset )
                cy.wrap($oldWidget)
                    .trigger('mousedown', {
                        which: 1
                    })
                    .trigger('mousemove', {
                        which: 1,
                        pageY: $oldWidget.position().top + offsetY
                    })
                    .trigger('mouseup');

                cy.wait('@saveOrder');

                // Should show a notification for the order change
                cy.get('.toast-success').contains('Order Changed');

                // Reload playlist and check if the new first widget has a different Id
                cy.wait('@reloadPlaylist');

                cy.get('#timeline-container [data-type="widget"]:first-child').then(($newWidget) => {
                    expect($oldWidget.attr('id')).not.to.eq($newWidget.attr('id'));
                });

                // Click the revert button
                cy.get('#playlist-editor-toolbar #undoLastAction').click({force: true});

                // Wait for the order to save
                cy.wait('@saveOrder');
                cy.wait('@reloadPlaylist');

                // Test if the revert made the name go back to the first widget
                cy.get('#timeline-container [data-type="widget"]:first-child').then(($newWidget) => {
                    expect($oldWidget.attr('id')).to.eq($newWidget.attr('id'));
                });

            });
        });

        it('should delete a widget using the toolbar bin', () => {
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            // Select a widget from the navigator
            cy.get('#playlist-timeline [data-type="widget"]:first-child').click().then(($el) => {

                const widgetId = $el.attr('id');

                // Click trash container
                cy.get('#playlist-editor-toolbar a#trashContainer').click();

                // Confirm delete on modal
                cy.get('[data-test="deleteObjectModal"] button[data-bb-handler="confirm"]').click();

                // Check toast message
                cy.get('.toast-success').contains('Deleted');

                // Wait for the layout to reload
                cy.wait('@reloadPlaylist');

                // Check that widget is not on timeline
                cy.get('#playlist-timeline [data-type="widget"]#' + widgetId).should('not.exist');
            });
        });

        it('should add a audio clip to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
            
            populateLibraryWithMedia();

            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            // Open toolbar Tools tab
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();

            // Open the audio form
            dragToElement(
                '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="audio"] .drag-area',
                '#timeline-container [data-type="widget"]:first-child'
            ).then(() => {

                // Select the 1st option
                cy.get('[data-test="widgetPropertiesForm"] #mediaId > option').eq(1).then(($el) => {
                    cy.get('[data-test="widgetPropertiesForm"] #mediaId').select($el.val());
                });

                // Save and close the form
                cy.get('[data-test="widgetPropertiesForm"] [data-bb-handler="done"]').click();

                // Check if the widget has the audio icon
                cy.wait('@reloadPlaylist');
                cy.get('#timeline-container [data-type="widget"]:first-child')
                    .find('i[data-property="Audio"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Audio for');
            });
        });

        it('attaches expiry dates to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');
            
            // Open toolbar Tools tab
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();

            // Open the expiry form
            dragToElement(
                '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="expiry"] .drag-area',
                '#timeline-container [data-type="widget"]:first-child'
            ).then(() => {

                // Add dates
                cy.get('[data-test="widgetPropertiesForm"] #fromDt_Link1').type('2018-01-01');
                cy.get('[data-test="widgetPropertiesForm"] #fromDt_Link2').type('00:00');

                cy.get('[data-test="widgetPropertiesForm"] #toDt_Link1').type('2018-01-01');
                cy.get('[data-test="widgetPropertiesForm"] #toDt_Link2').type('23:45');

                // Save and close the form
                cy.get('[data-test="widgetPropertiesForm"] [data-bb-handler="done"]').click();

                // Check if the widget has the expiry dates icon
                cy.wait('@reloadPlaylist');
                cy.get('#timeline-container [data-type="widget"]:first-child')
                    .find('i[data-property="Expiry"]').click();

                cy.get('[data-test="widgetPropertiesForm"]').contains('Expiry for');
            });
        });

        // NOTE: Test skipped for now until transitions are enabled by default
        it.skip('adds a transition to a widget by drag and drop, and adds a link to open the form in the timeline', () => {
            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            // Open toolbar Tools tab
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();

            // Open the transition form
            dragToElement(
                '#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="transitionIn"] .drag-area',
                '#timeline-container [data-type="widget"]:nth-child(2)'
            ).then(() => {

                // Select the 1st option
                cy.get('[data-test="widgetPropertiesForm"] #transitionType > option').eq(1).then(($el) => {
                    cy.get('[data-test="widgetPropertiesForm"] #transitionType').select($el.val());
                });

                // Save and close the form
                cy.get('[data-test="widgetPropertiesForm"] [data-bb-handler="done"]').click();

                // Check if the widget has the transition icon
                cy.wait('@reloadPlaylist').then(() => {
                    cy.get('#timeline-container [data-type="widget"]:nth-child(2)')
                        .find('i[data-property="Transition"]').click();

                    cy.get('[data-test="widgetPropertiesForm"]').contains('Edit in Transition for');
                });
            });
        });

        it('check if the form to attach a transition to a widget by click to add appears', () => {
            // Create and alias for reload playlist
            cy.server();
            cy.route('/playlist?playlistId=*').as('reloadPlaylist');

            // Open toolbar Tools tab
            cy.get('#playlist-editor-toolbar .btn-menu-tab').contains('Tools').should('be.visible').click();


            // Activate the Add button
            cy.get('#playlist-editor-toolbar .toolbar-pane-content [data-sub-type="transitionIn"] .add-area').invoke('show').click();

                // Click on the widget to add
            cy.get('#timeline-container [data-type="widget"]:nth-child(2)').click();

            // Check if the right form appears
            cy.get('[data-test="widgetPropertiesForm"]').contains('Edit in Transition for');

        });
    });
});