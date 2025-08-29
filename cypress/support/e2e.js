// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands';
import './toolbarCommands';
import './layoutCommands';
import './playlistCommands';
import './displayCommands';
import './menuboardCommands';
import './userCommands';

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Run before every test spec, to disable User Welcome tour
before(function() {
    cy.login().then(() => {
        cy.tutorialClose();
    });
});

Cypress.on('uncaught:exception', (err, runnable) => {
    // returning false here prevents Cypress from
    // failing the test
    return false
})