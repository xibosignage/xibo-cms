/*
 * Copyright (C) 2022-2023 Xibo Signage Ltd
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

const { defineConfig } = require('cypress')

module.exports = defineConfig({
  viewportWidth: 1366,
  viewportHeight: 768,
  numTestsKeptInMemory: 5,
  defaultCommandTimeout: 20000,
  requestTimeout: 20000,
  env: {
      client_id: "MrGPc7e3IL1hA6w13l7Ru5giygxmNiafGNhFv89d",
      client_secret: "Pk6DdDgu2HzSoepcMHRabY60lDEvQ9ucTejYvc5dOgNVSNaOJirCUM83oAzlwe0KBiGR2Nhi6ltclyNC1rmcq0CiJZXzE42KfeatQ4j9npr6nMIQAzMal8O8RiYrIoono306CfyvSSJRfVfKExIjj0ZyE4TUrtPezJbKmvkVDzh8aj3kbanDKatirhwpfqfVdfgsqVNjzIM9ZgKHnbrTX7nNULL3BtxxNGgDMuCuvKiJFrLSyIIz1F4SNrHwHz"
  },
  e2e: {
    experimentalSessionAndOrigin: true,
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost',
  },
})
