/*
 * Copyright (c) 2022 Xibo Signage Ltd
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

const { defineConfig } = require('cypress')

module.exports = defineConfig({
  viewportWidth: 1366,
  viewportHeight: 768,
  numTestsKeptInMemory: 5,
  defaultCommandTimeout: 10000,
  requestTimeout: 10000,
  env: {
      client_id: "7dd456e9e26d128e0c9d61045212d0afbcd5ea64",
      client_secret: "9004c8edaea588a16ea8c4334d41683ccc9af48036b0d798da5ab8d038e69704e8d768483ea0c7870058cdf60f1c320386bd8702413c226d0ba75dcb617e2dc1f7693cb4e285bbe81d1cf1a5cbf8c7f9288a4ff912b5c73d59fe9f4f7b7e3383e5d276ccbdd1302c3be8acd176fa3d07aaa8dc0903a825d98eec0ee94e10b4"
  },
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost',
  },
})
