/**
 * Copyright (C) 2020 Xibo Signage Ltd
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
const xiboIC = (function() {
    'use strict';

    // Private vars
    const _lib = {
        protocol: '', // default protocol 
        hostName: '', // default URL 
        port: '', // default PORT 
        headers: [], // Default headers 
        timelimit: 5000, // timelimit in milliseconds
        callbackQueue : [],
        
        /**
         * Get URL string
         */
        getOriginURL: function() {
            if (this.protocol != '' && this.hostName != '') {
                return this.protocol + '://' + this.hostName + ((this.port != '') ? ':' + this.port : '');
            }
            return '';
        },
        /**
         * Make a request to the configured server/player
         * @param  {string} path - Request path
         * @param  {Object} [options] - Optional params
         * @param  {string} options.type
         * @param  {Object[]} options.headers - Request headers in the format {key: key, value: value}
         * @param  {Object} options.data
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        makeRequest: function(path, {type, headers, data, done, progress, error} = {}) {
            const self = this;

            // Check if we are in preview mode
            let playerAction;
            const isPreview = function() {
                // For the widget preview in viewer
                if(typeof window.parent.lD != 'undefined') {
                    playerAction = window.parent.lD.playerAction;
                    return true;
                }

                // For layout preview in viewer
                if(typeof window.parent.parent.lD != 'undefined') {
                    playerAction = window.parent.parent.lD.playerAction;
                    return true;
                }
            }();

            // Preview/Handle action in viewer if exists
            if(isPreview && playerAction != undefined) {
                playerAction(path, data);
                return;
            }
            
            const urlToUse = self.getOriginURL() + path;
            const typeToUse = (type) ? type : 'GET';
            const reqHeaders = (headers) ? headers : self.headers;

            // Init AJAX
            let xhr = new XMLHttpRequest();
            xhr.timeout = self.timelimit;

            xhr.open(typeToUse, urlToUse, true);

            // Set headers
            if(type == 'POST') {
                xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
            }

            reqHeaders.forEach(header => {
                xhr.setRequestHeader(header.key, header.value);
            });

            // Append data
            let newData = null;
            if (typeof(data) == "object") {
                newData = JSON.stringify(data);
            }

            // On load complete
            xhr.onload = function() {
                if (typeof(done) == "function") {
                    done(this);
                }
            };

            // On error
            if (typeof(error) == "function") {
                xhr.onerror = error;
            }

            // On progress
            if (typeof(progress) == "function") {
                xhr.onprogress = progress;
            }

            // Send!
            xhr.send(newData);
        },
    };

    // Public library
    const mainLib = {
        isVisible: true, // Widget visibility on the player 

        checkVisible: function() { // Check if the widget is hidden or visible
            const urlParams = new URLSearchParams(location.search);
            mainLib.isVisible = (urlParams.get("visible")) ? (urlParams.get("visible") == 1) : true;
            return mainLib.isVisible;
        },

        /**
         * Configure the library options
         * @param  {Object} [options]
         * @param  {string} options.hostName
         * @param  {string} options.port
         * @param  {Object[]} options.headers - Request headers in the format {key: key, value: value}
         * @param  {string} options.headers.key
         * @param  {string} options.headers.value
         * @param  {string} options.protocol
         */
        config: function({ hostName, port, headers, protocol } = {}) {
            // Initialise custom request params
            _lib.hostName = hostName ? hostName : _lib.hostName;
            _lib.port = port ? port : _lib.port;
            _lib.headers = headers ? headers : _lib.headers;
            _lib.protocol = protocol ? protocol : _lib.protocol;
        },

        /**
         * Get player info
         * @param  {Object[]} [options] - Request options
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        info: function({ done, progress, error } = {}) {
            _lib.makeRequest(
                '/info',
                {
                    done: done,
                    progress: progress,
                    error: error
                }
            );
        },
        
        /**
         * Trigger a predefined action
         * @param  {string} code - The trigger code
         * @param  {Object[]} [options] - Request options
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        trigger(code, { done, progress, error } = {}) {
            _lib.makeRequest(
                '/trigger',
                {
                    type: 'POST',
                    data: {
                        trigger: code
                    },
                    done: done,
                    progress: progress,
                    error: error
                }
            );
        },

        /**
         * Expire widget
         * @param  {Object[]} [options] - Request options
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        expireNow({ done, progress, error } = {}) {
            _lib.makeRequest(
                '/expirenow',
                {
                    type: 'POST',
                    done: done,
                    progress: progress,
                    error: error
                }
            );
        },
        
        /**
         * Extend widget duration
         * @param  {string} extend - Duration value to extend
         * @param  {Object[]} [options] - Request options
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        extendWidgetDuration(extend, { done, progress, error } = {}) {
            _lib.makeRequest(
                '/extendduration',
                {
                    type: 'POST',
                    data: {
                        extend: extend
                    },
                    done: done,
                    progress: progress,
                    error: error
                }
            );
        },
        
        /**
         * Set widget duration
         * @param  {string} duration - New widget duration
         * @param  {Object[]} [options] - Request options
         * @param  {requestCallback} options.done
         * @param  {requestCallback} options.progress
         * @param  {requestCallback} options.error
         */
        setWidgetDuration(duration, { done, progress, error } = {}) {
            _lib.makeRequest(
                '/setduration',
                {
                    type: 'POST',
                    data: {
                        duration: duration
                    },
                    done: done,
                    progress: progress,
                    error: error
                }
            );
        },

        /**
         * Add callback function to the queue
         * @param  {callback} callback - Function to store
         * @param  {Object[]} [args] - Function arguments
         */
        addToQueue(callback, ...args) {
            if(typeof callback != 'function') {
                console.error('Invalid callback function');
            }

            _lib.callbackQueue.push({
                callback: callback,
                arguments: args
            });
        },

        /**
         * Run promised functions in queue
         */
        runQueue() {
            _lib.callbackQueue.forEach((element) => {
                element.callback.apply(_lib, element.arguments);
            });

            // Empty queue
            _lib.callbackQueue = [];
        },

        /**
         * Set visible and run queue
         */
        setVisible() {
            this.isVisible = true;
            this.runQueue();
        }
    };

    // Check visibility on load
    mainLib.checkVisible();
    
    return mainLib;
})();
