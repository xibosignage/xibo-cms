// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
Cypress.Commands.add('login', function() {
    cy.request({
        method: 'POST',
        url: '/login',
        form: true,
        body: {
            'username': 'xibo_admin',
            'password': 'password'
        }
    }).then((res) => {
        // Get access token and save it as a environment variable
        cy.getAccessToken();
    });

    cy.getCookie('PHPSESSID').should('exist');
});

Cypress.Commands.add('getAccessToken', function() {
    cy.request({
        method: 'POST',
        url: '/api/authorize/access_token',
        form: true,
        body: {
            'client_id': Cypress.env('client_id'),
            'client_secret': Cypress.env('client_secret'),
            'grant_type': 'client_credentials'
        }
    }).then((res) => {
        Cypress.env('accessToken', res.body.access_token);
    });
});

Cypress.Commands.add('formRequest', (method, url, formData) => {

    return new Promise(function(resolve, reject) {

        const xhr = new XMLHttpRequest();

        xhr.open(method, url);
        xhr.setRequestHeader('Authorization', 'Bearer ' + Cypress.env('accessToken'));

        xhr.onload = function() {
            if(this.status >= 200 && this.status < 300) {
                resolve(xhr.response);
            } else {
                reject({
                    status: this.status,
                    statusText: xhr.statusText
                });
            }
        };
        xhr.onerror = function() {
            reject({
                status: this.status,
                statusText: xhr.statusText
            });
        };

        xhr.send(formData);
    });
});

Cypress.Commands.add('clearToolbarPrefs', function() {

    let preference = [];

    preference[0] =
    {
        option: 'toolbar',
        value: JSON.stringify({
            menuItems: {},
            openedMenu: -1
        })
    };

    cy.request({
        method: 'POST',
        url: '/api/user/pref',
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        }, 
        body: {
            preference: preference
        }
    });
});

// Layout
Cypress.Commands.add('createLayout', function(name) {

    cy.request({
        method: 'POST',
        url: '/api/layout',
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        },
        body: {
            name: name
        }
    }).then((res) => {
        return res.body.layoutId;
    });
});

Cypress.Commands.add('checkoutLayout', function(id) {

    cy.request({
        method: 'PUT',
        url: '/api/layout/checkout/' + id,
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        }
    });
});

Cypress.Commands.add('addMediaToLibrary', function(fileName) {

    //Declarations
    const method = 'POST';
    const url = '/api/library';
    const fileType = '*/*';

    // Get file from fixtures as binary
    cy.fixture(fileName, 'binary').then((zipBin) => {

        // File in binary format gets converted to blob so it can be sent as Form data
        return Cypress.Blob.binaryStringToBlob(zipBin, fileType).then((blob) => {

            // Build up the form
            const formData = new FormData();

            formData.set('files[]', blob, fileName); //adding a file to the form
            
            // Perform the request
            cy.formRequest(method, url, formData).then((res) => {

                const parsedJSON = JSON.parse(res);

                expect(typeof parsedJSON.files[0].name).to.eq('string');

                // Return id
                return parsedJSON.files[0].name;
            });
        });
    });
});

Cypress.Commands.add('importLayout', function(fileName) {

    //Declarations
    const method = 'POST';
    const url = '/api/layout/import';
    const fileType = 'application/zip';

    // Get file from fixtures as binary
    cy.fixture(fileName, 'binary').then((zipBin) => {

        // File in binary format gets converted to blob so it can be sent as Form data
        return Cypress.Blob.binaryStringToBlob(zipBin, fileType).then((blob) => {

            // Build up the form
            const formData = new FormData();

            // Create random name
            const uuid = Cypress._.random(0, 1e6);

            formData.set('files[]', blob, fileName); //adding a file to the form
            formData.set('name[]', uuid); //adding a name to the form

            // Perform the request
            cy.formRequest(method, url, formData).then((res) => {
                const parsedJSON = JSON.parse(res);

                expect(typeof parsedJSON.files[0].id).to.eq('number');

                // Return id
                return parsedJSON.files[0].id;
            });
        });
    });
});

Cypress.Commands.add('deleteLayout', function(id) {

    cy.request({
        method: 'DELETE',
        url: '/api/layout/' + id,
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        }
    });
});

// Playlist
Cypress.Commands.add('createNonDynamicPlaylist', function(name) {

    cy.request({
        method: 'POST',
        url: '/api/playlist',
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        },
        body: {
            name: name
        }
    }).then((res) => {
        return res.body.playlistId;
    });
});


Cypress.Commands.add('addWidgetToPlaylist', function(playlistId, widgetType, widgetData) {

    cy.request({
        method: 'POST',
        url: '/api/playlist/widget/' + widgetType + '/' + playlistId,
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        },
        body: widgetData
    });
});

Cypress.Commands.add('addRandomMediaToPlaylist', function(playlistId) {

    // Get media
    cy.request({
        method: 'GET',
        url: '/api/library?retired=0&assignable=1&start=0&length=1',
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        }
    }).then((res) => {

        let media = [];
        media.push(res.body[0].mediaId);
        
        // Add media to playlist
        cy.request({
            method: 'POST',
            url: '/api/playlist/library/assign/' + playlistId,
            form: true,
            headers: {
                Authorization: 'Bearer ' + Cypress.env('accessToken')
            },
            body: {
                media: media
            }
        });
    });
});

Cypress.Commands.add('deletePlaylist', function(id) {

    cy.request({
        method: 'DELETE',
        url: '/api/playlist/' + id,
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        }
    });
});

// Campaign
Cypress.Commands.add('createCampaign', function(name) {

    cy.request({
        method: 'POST',
        url: '/api/campaign',
        form: true,
        headers: {
            Authorization: 'Bearer ' + Cypress.env('accessToken')
        },
        body: {
            name: name
        }
    }).then((res) => {
        return res.body.campaignId;
    });
});