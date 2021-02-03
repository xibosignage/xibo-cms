/**
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2006-2021 Xibo Signage Ltd
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
 * 
 * Based on:
 * bootstrap-tour
 * http://bootstraptour.com
 * Copyright 2012-2015 Ulrich Sossou
 */
(function(window, factory) {
    window.Tour = factory(window.jQuery);
    return window.Tour;
})(window, function($) {
    const DOMID_BACKDROP = "#tourBackdrop";
    const DOMID_BACKDROP_TEMP = "#tourBackdrop-temp"; // used for @ibastevan zindex fix: https://github.com/IGreatlyDislikeJavascript/bootstrap-tourist/issues/38
    const DOMID_HIGHLIGHT = "#tourHighlight";
    const DOMID_HIGHLIGHT_TEMP = "#tourHighlight-temp"; // used for @ibastevan zindex fix: https://github.com/IGreatlyDislikeJavascript/bootstrap-tourist/issues/38
    const DOMID_PREVENT = "#tourPrevent";

    var Tour, document;

    document = window.document;

    Tour = (function() {

        // Tour constructor
        function Tour(options) {
            var storage;
            try {
                storage = window.localStorage;
            } catch (error) {
                storage = false;
            }

            // Extend global options
            this._options = $.extend(true, {
                name: 'tour',
                steps: [],
                container: 'body',
                autoscroll: true,
                keyboard: false,
                storage: storage,
                debug: false,
                backdrop: false,
                backdropContainer: 'body',
                backdropOptions: {
                    highlightOpacity: 0.6,
                    highlightColor: "#FFF",
                    backdropSibling: false,
                    animation: {
                        // can be string of css class or function signature: function(domElement, step) {}
                        backdropShow: function(domElement, step) {
                            domElement.fadeIn();
                        },
                        backdropHide: function(domElement, step) {
                            domElement.fadeOut("slow");
                        },
                        highlightShow: function(domElement, step) {
                            // calling step.fnPositionHighlight() is the same as:
                            // domElement.width($(step.element).outerWidth()).height($(step.element).outerHeight()).offset($(step.element).offset());
                            step.fnPositionHighlight();
                            domElement.fadeIn();
                        },
                        highlightTransition: "tour-highlight-animation",
                        highlightHide: function(domElement, step) {
                            domElement.fadeOut("slow");
                        }
                    },
                },
                redirect: true,
                orphan: false,
                duration: false,
                delay: false,
                basePath: '',
                template: null,
                sanitizeWhitelist: [],
                sanitizeFunction: null, // function(content) return sanitizedContent
                showProgressBar: false,
                showProgressText: false,
                getProgressBarHTML: null, //function(percent) {},
                getProgressTextHTML: null, //function(stepNumber, percent, stepCount) {},
                afterSetState: function(key, value) {},
                afterGetState: function(key, value) {},
                afterRemoveState: function(key) {},
                onStart: function(tour) {
                    // Save tour playing to local storage
                    localStorage.tour_playing = tour._options.name;
                },
                onEnd: function(tour) {
                    var tourName = tour._options.name;

                    // Save tour as played to the local storage
                    localStorage.setItem(tourName + '_seen', 1);

                    // Remove tour playing from local storage
                    localStorage.removeItem(tourName + '_current_step');
                    localStorage.removeItem('tour_playing');

                    // if we're in welcome page, call update status for the buttons status
                    if (typeof updateTourCardStatus == "function") {
                        updateTourCardStatus();
                    }
                },
                onShow: function(tour) {},
                onShown: function(tour) {},
                onHide: function(tour) {},
                onHidden: function(tour) {},
                onNext: function(tour) {},
                onPrev: function(tour) {},
                onPause: function(tour, duration) {},
                onResume: function(tour, duration) {},
                onRedirectError: function(tour) {},
                onElementUnavailable: this.onElementUnavailable, // function (tour, stepNumber) {},
                onElementUnavailableStep: null,
                onPreviouslyEnded: null, // function (tour) {},
                onModalHidden: null, // function(tour, stepNumber) {}
            }, options);

            // Set default backdrop container
            if ($(this._options.backdropContainer).length == 0) {
                this._options.backdropContainer = "body";
            }

            // Sanitize function
            if (typeof(this._options.sanitizeFunction) == "function") {
                this._debug("Using custom sanitize function in place of bootstrap - security implications, be careful");
            } else {
                this._options.sanitizeFunction = null;

                this._debug("Extending Bootstrap sanitize options");

                var whiteListAdditions = {
                    "button": ["data-role", "style"],
                    "img": ["style"],
                    "div": ["style"]
                };

                // clone the default whitelist object first, otherwise we change the defaults for all of bootstrap!
                var whiteList = $.extend(true, {}, $.fn.popover.Constructor.Default.whiteList);

                // iterate the additions, and merge them into the defaults. We could just hammer them in manually but this is a little more expandable for the future
                $.each(whiteListAdditions, function(index, value) {
                    if (whiteList[index] == undefined) {
                        whiteList[index] = [];
                    }

                    $.merge(whiteList[index], value);
                });

                // and now do the same with the user specified whitelist in tour options
                $.each(this._options.sanitizeWhitelist, function(index, value) {
                    if (whiteList[index] == undefined) {
                        whiteList[index] = [];
                    }

                    $.merge(whiteList[index], value);
                });

                // save the merged whitelist back to the options, this is used by popover initialization when each step is shown
                this._options.sanitizeWhitelist = whiteList;
            }

            this._current = null;
            this.backdrops = [];

            return this;
        }

        /* Steps */
        Tour.prototype.addSteps = function(steps) {
            var j,
                len,
                step;
            for (j = 0, len = steps.length; j < len; j++) {
                step = steps[j];
                this.addStep(step);
            }
            return this;
        };

        Tour.prototype.addStep = function(step) {
            this._options.steps.push(step);
            return this;
        };

        Tour.prototype.getStepCount = function() {
            return this._options.steps.length;
        };

        Tour.prototype.getStep = function(i) {
            if (this._options.steps[i] != null) {

                if (typeof(this._options.steps[i].element) == "function") {
                    this._options.steps[i].element = this._options.steps[i].element();
                }

                // Step option: Global options overriden by the custom step options
                this._options.steps[i] = $.extend(true, {
                        id: "step-" + i,
                        path: '',
                        host: '',
                        placement: 'right',
                        title: '',
                        content: '<p></p>',
                        next: i === this._options.steps.length - 1 ? -1 : i + 1,
                        prev: i - 1,
                        animation: true,
                        container: this._options.container,
                        autoscroll: this._options.autoscroll,
                        backdrop: this._options.backdrop,
                        redirect: this._options.redirect,
                        preventInteraction: false,
                        orphan: this._options.orphan,
                        duration: this._options.duration,
                        delay: this._options.delay,
                        delayOnElement: null,
                        template: this._options.template,
                        boundary: null,
                        showProgressBar: this._options.showProgressBar,
                        showProgressText: this._options.showProgressText,
                        getProgressBarHTML: this._options.getProgressBarHTML,
                        getProgressTextHTML: this._options.getProgressTextHTML,
                        onShow: this._options.onShow,
                        onShown: this._options.onShown,
                        onHide: this._options.onHide,
                        onHidden: this._options.onHidden,
                        onNext: this._options.onNext,
                        onPrev: this._options.onPrev,
                        onPause: this._options.onPause,
                        onResume: this._options.onResume,
                        onRedirectError: this._options.onRedirectError,
                        onElementUnavailable: this._options.onElementUnavailable,
                        onElementUnavailableStep: this._options.onElementUnavailableStep,
                        onModalHidden: this._options.onModalHidden,
                        internalFlags: {
                            elementModal: null, // will store the jq modal object for a step
                            elementModalOriginal: null, // will store the original step.element string in steps that use a modal
                            elementBootstrapSelectpicker: null // will store jq bootstrap select picker object
                        }
                    },
                    this._options.steps[i]
                );

                // required so we don't overwrite the global options.
                this._options.steps[i].backdropOptions = $.extend(true, {}, this._options.backdropOptions, this._options.steps[i].backdropOptions);

                // safety to ensure consistent logic - reflex must == true if reflexOnly == true
                if (this._options.steps[i].reflexOnly == true) {
                    this._options.steps[i].reflex = true;
                }

                // set default delayOnElement for non orphan steps
                if (this._options.steps[i].orphan == false && this._options.steps[i].delayOnElement == null) {
                    this._options.steps[i].delayOnElement = {
                        delayElement: "element",
                        maxDelay: 2000
                    };
                }

                return this._options.steps[i];
            }
        };

        Tour.prototype._setStepFlag = function(stepNumber, flagName, value) {
            if (this._options.steps[stepNumber] != null) {
                this._options.steps[stepNumber].internalFlags[flagName] = value;
            }
        };

        Tour.prototype._getStepFlag = function(stepNumber, flagName) {
            if (this._options.steps[stepNumber] != null) {
                return this._options.steps[stepNumber].internalFlags[flagName];
            }
        };

        // Initialise tour: Tour only starts if no tour is ongoing
        Tour.prototype.init = function() {
            if (localStorage.tour_playing == undefined) {
                if (this.ended()) {
                    this.restart();
                } else {
                    this.start();
                }

                this._debug('Tour ' + this._options.name + 'has started');

                // Tour has started
                return true;
            }

            this._debug('Tour already playing, prevent initialise');

            // Tour did not start
            return false;
        };

        Tour.prototype.start = function(fromStep) {
            fromStep = (fromStep != undefined) ? fromStep : null;

            // Test if this tour has previously ended, and start() was called
            if (this.ended() && fromStep == null) {
                if (this._options.onPreviouslyEnded != null && typeof(this._options.onPreviouslyEnded) == "function") {
                    this._debug('Tour previously ended, exiting. Call tour.restart() to force restart. Firing onPreviouslyEnded()');
                    this._options.onPreviouslyEnded(this);
                } else {
                    this._debug('Tour previously ended, exiting. Call tour.restart() to force restart');
                }

                return this;
            }

            // Set current step to fromStep || 0 if fromStep==null
            this.setCurrentStep(fromStep);

            // Create the backdrop and highlight divs
            this._createOverlayElements();

            // Initialise mouse and keyboard
            this._initMouseNavigation();
            this._initKeyboardNavigation();

            // resize must destroy and recreate background, but popper.js handles popper positioning.
            var _this = this;
            $(window).on("resize.tour-" + _this._options.name, function() {
                _this.reshowCurrentStep();
            });

            // start the tour - see if user provided onStart function, and if it returns a promise, obey that promise before calling showStep
            var promise = this._makePromise(this._options.onStart != null ? this._options.onStart(this) : void 0);
            this._callOnPromiseDone(promise, this.showStep, this._current);

            return this;
        };

        // Next step
        Tour.prototype.next = function() {
            var promise;
            promise = this.hideStep();
            return this._callOnPromiseDone(promise, this._showNextStep);
        };

        // Previous step
        Tour.prototype.prev = function() {
            var promise;
            promise = this.hideStep();
            return this._callOnPromiseDone(promise, this._showPrevStep);
        };

        // Go to specific step
        Tour.prototype.goTo = function(i) {
            var promise;
            this._debug("goTo step " + i);
            promise = this.hideStep();
            return this._callOnPromiseDone(promise, this.showStep, i);
        };

        Tour.prototype.end = function() {
            this._debug("Tour.end() called");

            var endHelper,
                promise;

            endHelper = (function(_this) {
                return function(e) {
                    $(document).off("click.tour-" + _this._options.name);
                    $(document).off("keyup.tour-" + _this._options.name);
                    $(window).off("resize.tour-" + _this._options.name);
                    $(window).off("scroll.tour-" + _this._options.name);
                    _this._setState('end', 'yes');
                    _this._clearTimer();
                    $(".tour-step-element-reflex").removeClass("tour-step-element-reflex");
                    $(".tour-step-element-reflexOnly").removeClass("tour-step-element-reflexOnly");
                    _this._hideBackdrop();
                    _this._destroyOverlayElements();

                    if (_this._options.onEnd != null) {
                        return _this._options.onEnd(_this);
                    }
                };
            })(this);

            // Hide step and then call the end helper
            promise = this.hideStep();
            return this._callOnPromiseDone(promise, endHelper);
        };

        // Check if tour has ended
        Tour.prototype.ended = function() {
            return this._getState('end') == 'yes';
        };

        // Restart tour from the beginning
        Tour.prototype.restart = function() {
            this._removeState('current_step');
            this._removeState('end');
            this._removeState('redirect_to');
            return this.start();
        };

        // Restart tour from a specific step
        Tour.prototype.restartFromStep = function(step) {
            this._removeState('end');
            this._removeState('redirect_to');
            return this.start(step);
        };

        Tour.prototype.pause = function() {
            var step;
            step = this.getStep(this._current);
            if (!(step && step.duration)) {
                return this;
            }
            this._paused = true;
            this._duration -= new Date().getTime() - this._start;
            window.clearTimeout(this._timer);
            this._debug("Paused/Stopped step " + (this._current + 1) + " timer (" + this._duration + " remaining).");
            if (step.onPause != null) {
                return step.onPause(this, this._duration);
            }
        };

        Tour.prototype.resume = function() {
            var step;
            step = this.getStep(this._current);
            if (!(step && step.duration)) {
                return this;
            }
            this._paused = false;
            this._start = new Date().getTime();
            this._duration = this._duration || step.duration;
            this._timer = window.setTimeout((function(_this) {
                return function() {
                    if (_this._isLast()) {
                        return _this.end();
                    } else {
                        return _this.next();
                    }
                };
            })(this), this._duration);
            this._debug("Started step " + (this._current + 1) + " timer with duration " + this._duration);
            if ((step.onResume != null) && this._duration !== step.duration) {
                return step.onResume(this, this._duration);
            }
        };

        // fully closes and reopens the current step, triggering all callbacks etc
        Tour.prototype.reshowCurrentStep = function() {
            this._debug("Reshowing current step " + this.getCurrentStepIndex());
            var promise;
            promise = this.hideStep();
            return this._callOnPromiseDone(promise, this.showStep, this._current);
        };

        // Callback when the target element is unavailable (even after the waiting delta time)
        // Clear search interval and go to previous step (or predefined step by onElementUnavailableStep)
        Tour.prototype.onElementUnavailable = function(tour, step) {
            var currentStep = tour.getStep(step);

            tour._debug("Element unavailable at step " + step);

            // Clear search interval
            if(tour.delayFunc) {
                window.clearInterval(tour.delayFunc);
                tour.delayFunc = null;
            }

            // Get target step
            var targetStep;
            if (currentStep.onElementUnavailableStep != null) {
                targetStep = currentStep.onElementUnavailableStep;
            } else {
                targetStep = (step - 1);
            }

            // Go to step (if valid)
            if(targetStep < 0) {
                tour._debug("Step " + targetStep + " not available, end tour");
                tour.end();
            } else {
                tour._debug("Go to step " + targetStep);
                tour.goTo(targetStep);
            }
        };

        // Hide current step
        Tour.prototype.hideStep = function() {
            var hideDelay,
                hideStepHelper,
                promise,
                step;

            step = this.getStep(this.getCurrentStepIndex());

            if (!step) {
                return;
            }

            this._clearTimer();
            promise = this._makePromise(step.onHide != null ? step.onHide(this, this.getCurrentStepIndex()) : void 0);

            hideStepHelper = (function(_this) {
                return function(e) {
                    var $element;

                    $element = $(step.element);
                    if (!($element.data('bs.popover') || $element.data('popover'))) {
                        $element = $('body');
                    }

                    $element.popover('dispose');

                    $element.removeClass("tour-" + _this._options.name + "-element tour-" + _this._options.name + "-" + _this.getCurrentStepIndex() + "-element").removeData('bs.popover');

                    if (step.reflex) {
                        $element.removeClass('tour-step-element-reflex').off((_this._reflexEvent(step.reflex)) + ".tour-" + _this._options.name);
                        $element.removeClass('tour-step-element-reflexOnly');
                    }

                    // now handled by updateOverlayElements
                    //_this._hideOverlayElements(step);
                    _this._unfixBootstrapSelectPickerZindex(step);

                    // If this step was pointed at a modal, revert changes to the step.element. See the notes in showStep for explanation
                    var tmpModalOriginalElement = _this._getStepFlag(_this.getCurrentStepIndex(), "elementModalOriginal");
                    if (tmpModalOriginalElement != null) {
                        _this._setStepFlag(_this.getCurrentStepIndex(), "elementModalOriginal", null);
                        step.element = tmpModalOriginalElement;
                    }

                    if (step.onHidden != null) {
                        return step.onHidden(_this);
                    }
                };
            })(this);

            hideDelay = step.delay.hide || step.delay;
            if ({}
                .toString.call(hideDelay) === '[object Number]' && hideDelay > 0) {
                this._debug("Wait " + hideDelay + " milliseconds to hide the step " + (this._current + 1));
                window.setTimeout((function(_this) {
                    return function() {
                        return _this._callOnPromiseDone(promise, hideStepHelper);
                    };
                })(this), hideDelay);
            } else {
                this._callOnPromiseDone(promise, hideStepHelper);
            }
            return promise;
        };

        // Show step: loads all required step info and prepares to show
        Tour.prototype.showStep = function(i) {
            var path,
                promise,
                showDelay,
                showStepHelper,
                step;

                // Prevent to show if the tour has ended
            if (this.ended()) {
                this._debug('Tour ended, showStep prevented.');
                if (this._options.onEnd != null) {
                    this._options.onEnd(this);
                }

                return this;
            }

            step = this.getStep(i);
            if (!step) {
                return;
            }

            promise = this._makePromise(step.onShow != null ? step.onShow(this, i) : void 0);
            this.setCurrentStep(i);

            // Get path and redirect if exists
            path = (function() {
                switch ({}
                    .toString.call(step.path)) {
                    case '[object Function]':
                        return step.path();
                    case '[object String]':
                        return this._options.basePath + step.path;
                    default:
                        return step.path;
                }
            }).call(this);


            if (step.redirect && this._isRedirect(step.host, path, document.location)) {
                this._redirect(step, i, path);
                if (!this._isJustPathHashDifferent(step.host, path, document.location)) {
                    return;
                }
            }

            var revalidateDelayElement = function(step) {
                if (!step.delayOnElement) {
                    return $(step.element);
                } else if (typeof(step.delayOnElement.delayElement) == "function") {
                    return step.delayOnElement.delayElement();
                } else if (step.delayOnElement.delayElement == "element") {
                    return $(step.element);
                } else {
                    return $(step.delayOnElement.delayElement);
                }
            };

            // Helper function to actually show the popover using _showPopoverAndOverlay.
            showStepHelper = (function(_this) {
                return function(e) {
                    if (_this._isOrphan(step)) {
                        var $delayElement = revalidateDelayElement(step);
                        var hasDelayElement = (step.delayOnElement && step.delayOnElement.delayElement != "element");

                        if (step.orphan === false || (hasDelayElement && !$delayElement.is(':visible'))) {
                            _this._debug("Handle unintended orphan step " + (_this._current + 1) + ".\nOrphan option is false () and the element " + step.element + " does not exist or is hidden.");
                            
                            if (typeof(step.onElementUnavailable) == "function") {
                                _this._debug("Calling onElementUnavailable callback");
                                step.onElementUnavailable(_this, _this._current);
                            } else {
                                _this._showPrevStep(true);
                            }

                            return;
                        } else {
                            // It's an intended orphan
                            _this._debug("Show the orphan step " + (_this._current + 1) + ". Orphans option is true.");
                        }
                    }

                    // If the step to show is or is inside, a modal, process the events when we close it
                    _this.handleStepModals(i);

                    if (step.autoscroll && !_this._isOrphan(step)) {
                        _this._scrollIntoView(i);
                    } else {
                        _this._showPopoverAndOverlay(i);
                    }

                    if (step.duration) {
                        return _this.resume();
                    }
                };
            })(this);

            // delay in millisec specified in step options
            showDelay = step.delay.show || step.delay;
            if ({}
                .toString.call(showDelay) === '[object Number]' && showDelay > 0) {
                this._debug("Wait " + showDelay + " milliseconds to show the step " + (this._current + 1));
                window.setTimeout((function(_this) {
                    return function() {
                        return _this._callOnPromiseDone(promise, showStepHelper);
                    };
                })(this), showDelay);
            } else {
                if (step.delayOnElement) {
                    // delay by element existence or max delay (default 2 sec)
                    this.delayFunc = null;
                    var _this = this;

                    var $delayElement = revalidateDelayElement(step);

                    var delayElementLog = $delayElement.length > 0 ? $delayElement[0].tagName : step.delayOnElement.delayElement;

                    var delayMax = (step.delayOnElement.maxDelay ? step.delayOnElement.maxDelay : 2000);
                    this._debug("Wait for element " + delayElementLog + " visible or max " + delayMax + " milliseconds to show the step " + (this._current + 1));

                    _this.delayFunc = window.setInterval(function() {
                        _this._debug("Wait for element " + delayElementLog + ": checking...");
                        if ($delayElement.length === 0) {
                            $delayElement = revalidateDelayElement(step);
                        }
                        if ($delayElement.is(':visible')) {
                            _this._debug("Wait for element " + delayElementLog + ": found, showing step");
                            window.clearInterval(_this.delayFunc);
                            _this.delayFunc = null;
                            return _this._callOnPromiseDone(promise, showStepHelper);
                        }
                    }, 250);

                    //	set max delay to greater than default interval check for element appearance
                    if (delayMax < 250)
                        delayMax = 251;

                    // Set timer to kill the setInterval call after max delay time expires
                    window.setTimeout(function() {
                        if (_this.delayFunc) {
                            _this._debug("Wait for element " + delayElementLog + ": max timeout reached without element found");
                            window.clearInterval(_this.delayFunc);

                            // showStepHelper will handle broken/missing/invisible element
                            return _this._callOnPromiseDone(promise, showStepHelper);
                        }
                    }, delayMax);
                } else {
                    // no delay by milliseconds or delay by time
                    this._callOnPromiseDone(promise, showStepHelper);
                }
            }

            return promise;
        };

        // Handle modals containing the step (or if the step itself is a modal)
        Tour.prototype.handleStepModals = function(stepIdx) {
            var i = stepIdx;

            if (this.getCurrentStepIndex() !== i || this.ended()) {
                return;
            }

            var step = this.getStep(i);
            // will be set to element <div class="modal"> if modal in use
            var $modalObject = null;
            var $element;

            // Check if element is a modal
            if (step.orphan === false && ($(step.element).hasClass("modal") || $(step.element).data('bs.modal'))) {
                // element is exactly the modal div
                $modalObject = $(step.element);

                this._setStepFlag(this.getCurrentStepIndex(), "elementModalOriginal", step.element);

                // fix the tour element, the actual visible offset comes from modal > modal-dialog > modal-content and step.element is used to calc this offset & size
                step.element = $(step.element).find(".modal-content:first");
            }

            $element = $(step.element);

            // is element inside a modal? Find the parent modal
            if ($modalObject === null && $element.parents(".modal:first").length) {
                // find the parent modal div
                $modalObject = $element.parents(".modal:first");
            }

            // Is this step a modal?
            if ($modalObject && $modalObject.length > 0) {
                // store the modal element for other calls
                this._setStepFlag(i, "elementModal", $modalObject);

                if(this.funcModalHelper != undefined) {
                    $modalObject.off("hidden.bs.modal", this.funcModalHelper);
                }

                // modal in use, add callback
                this.funcModalHelper = function(_this, $_modalObject) {
                    return function() {
                        _this._debug("Modal close triggered");

                        if (typeof(step.onModalHidden) == "function") {
                            // if step onModalHidden returns false, do nothing. returns int, move to the step specified.
                            // Otherwise continue regular next/end functionality
                            var rslt = step.onModalHidden(_this, i);

                            if (rslt === false) {
                                _this._debug("onModalHidden returned exactly false, tour step unchanged");
                                return;
                            }

                            if (Number.isInteger(rslt)) {
                                _this._debug("onModalHidden returned int, tour moving to step " + rslt + 1);

                                $_modalObject.off("hidden.bs.modal", _this.funcModalHelper);
                                return _this.goTo(rslt);
                            }

                            _this._debug("onModalHidden did not return false or int, continuing tour");
                        }

                        $_modalObject.off("hidden.bs.modal", _this.funcModalHelper);
                    };
                }(this, $modalObject);

                $modalObject.on("hidden.bs.modal", this.funcModalHelper);
            }
        };

        Tour.prototype.getCurrentStepIndex = function() {
            return parseInt(this._current);
        };

        Tour.prototype.setCurrentStep = function(value) {
            if (value != null) {
                this._current = value;
                this._setState('current_step', value);
            } else {
                this._current = this._getState('current_step');
                this._current = this._current === null ? 0 : parseInt(this._current, 10);
            }

            return this;
        };

        /* Handle states */
        Tour.prototype._setState = function(key, value) {
            var e,
                keyName;
            if (this._options.storage) {
                keyName = this._options.name + "_" + key;
                try {
                    this._options.storage.setItem(keyName, value);
                } catch (error) {
                    e = error;
                    if (e.code === DOMException.QUOTA_EXCEEDED_ERR) {
                        this._debug('LocalStorage quota exceeded. State storage failed.');
                    }
                }
                return this._options.afterSetState(keyName, value);
            } else {
                if (this._state == null) {
                    this._state = {};
                }
                this._state[key] = value;
                return this._state[key];
            }
        };

        Tour.prototype._removeState = function(key) {
            var keyName;
            if (this._options.storage) {
                keyName = this._options.name + "_" + key;
                this._options.storage.removeItem(keyName);
                return this._options.afterRemoveState(keyName);
            } else {
                if (this._state != null) {
                    return delete this._state[key];
                }
            }
        };

        Tour.prototype._getState = function(key) {
            var keyName,
                value;
            if (this._options.storage) {
                keyName = this._options.name + "_" + key;
                value = this._options.storage.getItem(keyName);
            } else {
                if (this._state != null) {
                    value = this._state[key];
                }
            }
            if (value === void 0 || value === 'null') {
                value = null;
            }
            this._options.afterGetState(key, value);
            return value;
        };

        Tour.prototype._showNextStep = function(skipOrphanOpt) {
            var promise,
                showNextStepHelper,
                step;

            var skipOrphan = skipOrphanOpt || false;

            showNextStepHelper = (function(_this) {
                return function(e) {
                    return _this.showStep(_this._current + 1);
                };
            })(this);

            promise = void 0;

            step = this.getStep(this._current);

            // only call the onNext handler if this is a click and NOT an orphan skip due to missing element
            if (skipOrphan === false && step.onNext != null) {
                var rslt = step.onNext(this);

                if (rslt === false) {
                    this._debug("onNext callback returned false, preventing move to next step");
                    return this.showStep(this._current);
                }

                promise = this._makePromise(rslt);
            }

            return this._callOnPromiseDone(promise, showNextStepHelper);
        };

        Tour.prototype._showPrevStep = function(skipOrphanOpt) {
            var promise,
                showPrevStepHelper,
                step;

            var skipOrphan = skipOrphanOpt || false;

            showPrevStepHelper = (function(_this) {
                return function(e) {
                    return _this.showStep(step.prev);
                };
            })(this);

            promise = void 0;
            step = this.getStep(this._current);

            // only call the onPrev handler if this is a click and NOT an orphan skip due to missing element
            if (skipOrphan === false && step.onPrev != null) {
                var rslt = step.onPrev(this);

                if (rslt === false) {
                    this._debug("onPrev callback returned false, preventing move to previous step");
                    return this.showStep(this._current);
                }

                promise = this._makePromise(rslt);
            }

            return this._callOnPromiseDone(promise, showPrevStepHelper);
        };

        Tour.prototype._debug = function(text) {
            if (this._options.debug) {
                return window.console.log("[ Xibo Tour: '" + this._options.name + "' ] " + text);
            }
        };

        Tour.prototype._isRedirect = function(host, path, location) {
            var currentPath;
            if ((host != null) && host !== '' && (({}
                    .toString.call(host) === '[object RegExp]' && !host.test(location.origin)) || ({}
                    .toString.call(host) === '[object String]' && this._isHostDifferent(host, location)))) {
                return true;
            }
            currentPath = [location.pathname, location.search, location.hash].join('');
            return (path != null) && path !== '' && (({}
                .toString.call(path) === '[object RegExp]' && !path.test(currentPath)) || ({}
                .toString.call(path) === '[object String]' && this._isPathDifferent(path, currentPath)));
        };

        Tour.prototype._isHostDifferent = function(host, location) {
            switch ({}
                .toString.call(host)) {
                case '[object RegExp]':
                    return !host.test(location.origin);
                case '[object String]':
                    return this._getProtocol(host) !== this._getProtocol(location.href) || this._getHost(host) !== this._getHost(location.href);
                default:
                    return true;
            }
        };

        Tour.prototype._isPathDifferent = function(path, currentPath) {
            return this._getPath(path) !== this._getPath(currentPath) || !this._equal(this._getQuery(path), this._getQuery(currentPath)) || !this._equal(this._getHash(path), this._getHash(currentPath));
        };

        Tour.prototype._isJustPathHashDifferent = function(host, path, location) {
            var currentPath;
            if ((host != null) && host !== '') {
                if (this._isHostDifferent(host, location)) {
                    return false;
                }
            }
            currentPath = [location.pathname, location.search, location.hash].join('');
            if ({}
                .toString.call(path) === '[object String]') {
                return this._getPath(path) === this._getPath(currentPath) && this._equal(this._getQuery(path), this._getQuery(currentPath)) && !this._equal(this._getHash(path), this._getHash(currentPath));
            }
            return false;
        };

        Tour.prototype._redirect = function(step, i, path) {
            var href;
            if ($.isFunction(step.redirect)) {
                return step.redirect.call(this, path);
            } else {
                href = {}
                    .toString.call(step.host) === '[object String]' ? "" + step.host + path : path;
                this._debug("Redirect to " + href);
                if (this._getState('redirect_to') === ("" + i)) {
                    this._debug("Error redirection loop to " + path);
                    this._removeState('redirect_to');
                    if (step.onRedirectError != null) {
                        return step.onRedirectError(this);
                    }
                } else {
                    this._setState('redirect_to', "" + i);
                    document.location.href = href;
                    return document.location.href;
                }
            }
        };

        // Tests if the step is orphan
        // Step can be "orphan" (unattached to any element) if specifically set as such in tour step options, or with an invalid/hidden element
        Tour.prototype._isOrphan = function(step) {
            var isOrphan = (step.orphan == true) || (step.element == null) || !$(step.element).length || $(step.element).is(':hidden') && ($(step.element)[0].namespaceURI !== 'http://www.w3.org/2000/svg');

            return isOrphan;
        };

        Tour.prototype._isLast = function() {
            return this._current >= this._options.steps.length - 1;
        };

        // Show popopver and overlay: wraps the calls to show the tour step in a popover and the background overlay.
        // Note this is ALSO called by scroll event handler. Individual funcs called will determine whether redraws etc are required.
        Tour.prototype._showPopoverAndOverlay = function(i) {
            var step;

            if (this.getCurrentStepIndex() !== i || this.ended()) {
                return;
            }
            step = this.getStep(i);

            // handles all show, hide and move of the background and highlight
            this._updateBackdropElements(step);

            // Show the preventInteraction overlay etc
            this._updateOverlayElements(step);

            // Required to fix the z index issue with BS select dropdowns
            this._fixBootstrapSelectPickerZindex(step);

            // Ensure this is called last, to allow preceeding calls to check whether current step popover is already visible.
            // This is required because this func is called by scroll event. showPopover creates the actual popover with
            // current step index as a class. Therefore all preceeding funcs can check if they are being called because of a
            // scroll event (popover class using current step index exists), or because of a step change (class doesn't exist).
            this._showPopover(step, i);

            if (step.onShown != null) {
                step.onShown(this);
            }

            return this;
        };

        // Show popover: handles view of popover
        Tour.prototype._showPopover = function(step, i) {
            var $element,
                $tip,
                isOrphan,
                options,
                title,
                content,
                percentProgress,
                modalObject;

            isOrphan = this._isOrphan(step);

            // is this step already visible? _showPopover is called by _showPopoverAndOverlay, which is called by window scroll event. This
            // check prevents the continual flickering of the current tour step - original approach reloaded the popover every scroll event.
            // Why is this check here and not in _showPopoverAndOverlay? This allows us to selectively redraw elements on scroll.
            if ($(document).find(".popover.tour-" + this._options.name + ".tour-" + this._options.name + "-" + this.getCurrentStepIndex()).length == 0) {
                // Step not visible, draw first time

                $(".tour-" + this._options.name).remove();

                step.template = this._template(step, i);

                if (isOrphan) {
                    // Note: BS4 popper.js requires additional fiddling to work, see below where popOpts object is created
                    step.element = 'body';
                    step.placement = 'top';

                    // If step is an intended or unintended orphan, and reflexOnly is set, show a warning.
                    if (step.reflexOnly) {
                        this._debug("Step is an orphan, and reflexOnly is set: ignoring reflexOnly");
                    }
                }

                $element = $(step.element);

                $element.addClass("tour-" + this._options.name + "-element tour-" + this._options.name + "-" + i + "-element");

                if (step.reflex && !isOrphan) {
                    $element.addClass('tour-step-element-reflex');
                    $element.off((this._reflexEvent(step.reflex)) + ".tour-" + this._options.name).on((this._reflexEvent(step.reflex)) + ".tour-" + this._options.name, (function(_this) {
                        return function() {
                            if (_this._isLast()) {
                                return _this.end();
                            } else {
                                return _this.next();
                            }
                        };
                    })(this));

                    if (step.reflexOnly) {
                        // this pseudo-class is used to quickly identify reflexOnly steps in handlers / code that don't have access to tour.step (without
                        // costly reloading) but need to know about reflexOnly. For example, obeying reflexOnly in keyboard handler. Solves
                        // https://github.com/IGreatlyDislikeJavascript/bootstrap-tourist/issues/45
                        $element.addClass('tour-step-element-reflexOnly');

                        // Only disable the next button if this step is NOT an orphan.
                        // This is difficult to achieve robustly because tour creator can use a custom template. Instead of trying to manually
                        // edit the template - which must be a string to be passed to popover creation - use jquery to find the element, hide
                        // it, then use the resulting DOM code/string to search and replace

                        // Find "next" object (button, href, etc), create a copy
                        var $objNext = $(step.template).find('[data-role="next"]').clone();

                        if ($objNext.length) {
                            // Get the DOM code for the object
                            var strNext = $objNext[0].outerHTML;

                            $objNext.hide();

                            // Get the DOM code for the hidden object
                            var strHidden = $objNext[0].outerHTML;

                            // string replace it in the template
                            step.template = step.template.replace(strNext, strHidden);
                        }
                    }
                }

                title = step.title;
                content = step.content;
                percentProgress = parseInt(((i + 1) / this.getStepCount()) * 100);

                if (step.showProgressBar) {
                    if (typeof(step.getProgressBarHTML) == "function") {
                        content = step.getProgressBarHTML(percentProgress) + content;
                    } else {
                        content = '<div class="progress"><div class="progress-bar progress-bar-striped" role="progressbar" style="width: ' + percentProgress + '%;"></div></div>' + content;
                    }
                }

                if (step.showProgressText) {
                    if (typeof(step.getProgressTextHTML) == "function") {
                        title += step.getProgressTextHTML(i, percentProgress, this.getStepCount());
                    } else {
                        title += '<span class="float-right">' + (i + 1) + '/' + this.getStepCount() + '</span>';
                    }
                }

                // Tourist v0.10 - split popOpts out of bootstrap popper instantiation due to BS3 / BS4 diverging requirements
                var popOpts = {
                    placement: step.placement, // When auto is specified, it will dynamically reorient the popover.
                    trigger: 'manual',
                    title: title,
                    content: content,
                    html: true,
                    //sanitize: false, // turns off all bootstrap sanitization of popover content, only use in last resort case - use whiteListAdditions instead!
                    whiteList: this._options.sanitizeWhitelist, // ignored if sanitizeFn is specified
                    sanitizeFn: this._options.sanitizeFunction,
                    animation: step.animation,
                    container: step.container,
                    template: step.template,
                    selector: step.element,
                };

                // Fix ( if defined ) for elements that we need to overflow the target's container space
                if(step.boundary != null) {
                    popOpts.boundary = step.boundary;
                }

                if (isOrphan) {
                    // Center element
                    popOpts.offset = function(obj) {
                        var top = Math.max(0, (($(window).height() - obj.popper.height) / 2));
                        var left = Math.max(0, (($(window).width() - obj.popper.width) / 2));

                        obj.popper.position = "fixed";
                        obj.popper.top = top;
                        obj.popper.bottom = top + obj.popper.height;
                        obj.popper.left = left;
                        obj.popper.right = top + obj.popper.width;
                        return obj;
                    };
                } else {
                    popOpts.selector = "#" + step.element[0].id;
                }

                $element.popover(popOpts);
                $element.popover('show');

                $tip = $(($element.data('bs.popover') ? $element.data('bs.popover').getTipElement() : $element.data('popover').getTipElement()));

                $tip.attr('id', step.id);

                this._debug("Step " + (this._current + 1) + " of " + this._options.steps.length);
            }
        };

        // Get and build the step's template
        Tour.prototype._template = function(step, i) {
            var $navigation,
                $next,
                $prev,
                $resume,
                $template,
                template;
            template = step.template;
            if (this._isOrphan(step) && {}
                .toString.call(step.orphan) !== '[object Boolean]') {
                template = step.orphan;
            }
            $template = $.isFunction(template) ? $(template(i, step)) : $(template);
            $navigation = $template.find('.popover-navigation');
            $prev = $navigation.find('[data-role="prev"]');
            $next = $navigation.find('[data-role="next"]');
            $resume = $navigation.find('[data-role="pause-resume"]');
            if (this._isOrphan(step)) {
                $template.addClass('orphan');
            }
            $template.addClass("tour-" + this._options.name + " tour-" + this._options.name + "-" + i);
            if (step.reflex) {
                $template.addClass("tour-" + this._options.name + "-reflex");
            }
            if (step.prev < 0) {
                $prev.addClass('disabled').prop('disabled', true).prop('tabindex', -1);
            }
            if (step.next < 0) {
                $next.addClass('disabled').prop('disabled', true).prop('tabindex', -1);
            }
            if (!step.duration) {
                $resume.remove();
            }
            return $template.clone().wrap('<div>').parent().html();
        };

        Tour.prototype._reflexEvent = function(reflex) {
            if ({}
                .toString.call(reflex) === '[object Boolean]') {
                return 'click';
            } else {
                return reflex;
            }
        };

        // Calculate position and reposition step element
        Tour.prototype._reposition = function($tip, step) {
            var offsetBottom,
                offsetHeight,
                offsetRight,
                offsetWidth,
                originalLeft,
                originalTop,
                tipOffset;
            offsetWidth = $tip[0].offsetWidth;
            offsetHeight = $tip[0].offsetHeight;

            tipOffset = $tip.offset();
            originalLeft = tipOffset.left;
            originalTop = tipOffset.top;

            offsetBottom = $(document).height() - tipOffset.top - $tip.outerHeight();
            if (offsetBottom < 0) {
                tipOffset.top = tipOffset.top + offsetBottom;
            }

            offsetRight = $('html').outerWidth() - tipOffset.left - $tip.outerWidth();
            if (offsetRight < 0) {
                tipOffset.left = tipOffset.left + offsetRight;
            }
            if (tipOffset.top < 0) {
                tipOffset.top = 0;
            }
            if (tipOffset.left < 0) {
                tipOffset.left = 0;
            }

            $tip.offset(tipOffset);

            if (step.placement === 'bottom' || step.placement === 'top') {
                if (originalLeft !== tipOffset.left) {
                    return this._replaceArrow($tip, (tipOffset.left - originalLeft) * 2, offsetWidth, 'left');
                }
            } else {
                if (originalTop !== tipOffset.top) {
                    return this._replaceArrow($tip, (tipOffset.top - originalTop) * 2, offsetHeight, 'top');
                }
            }
        };

        // Position arrow according to step element
        Tour.prototype._replaceArrow = function($tip, delta, dimension, position) {
            return $tip.find('.arrow').css(position, delta ? 50 * (1 - delta / dimension) + '%' : '');
        };

        Tour.prototype._scrollIntoView = function(i) {
            var $element,
                $window,
                counter,
                height,
                offsetTop,
                scrollTop,
                step,
                windowHeight;
            step = this.getStep(i);
            $element = $(step.element);

            if (this._isOrphan(step)) {
                // If this is an orphan step, don't auto-scroll. Orphan steps are now css fixed to center of window
                return this._showPopoverAndOverlay(i);
            }

            if (!$element.length) {
                return this._showPopoverAndOverlay(i);
            }

            $window = $(window);
            offsetTop = $element.offset().top;
            height = $element.outerHeight();
            windowHeight = $window.height();
            scrollTop = 0;
            switch (step.placement) {
                case 'top':
                    scrollTop = Math.max(0, offsetTop - (windowHeight / 2));
                    break;
                case 'left':
                case 'right':
                    scrollTop = Math.max(0, (offsetTop + height / 2) - (windowHeight / 2));
                    break;
                case 'bottom':
                    scrollTop = Math.max(0, (offsetTop + height) - (windowHeight / 2));
            }
            this._debug("Scroll into view. ScrollTop: " + scrollTop + ". Element offset: " + offsetTop + ". Window height: " + windowHeight + ".");
            counter = 0;
            return $('body, html').stop(true, true).animate({
                scrollTop: Math.ceil(scrollTop)
            }, (function(_this) {
                return function() {
                    if (++counter === 2) {
                        _this._showPopoverAndOverlay(i);
                        return _this._debug("Scroll into view.\nAnimation end element offset: " + ($element.offset().top) + ".\nWindow height: " + ($window.height()) + ".");
                    }
                };
            })(this));
        };

        Tour.prototype._initMouseNavigation = function() {
            var _this;
            _this = this;
            return $(document).off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='prev']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='next']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='end']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='pause-resume']").on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='next']", (function(_this) {
                return function(e) {
                    e.preventDefault();
                    return _this.next();
                };
            })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='prev']", (function(_this) {
                return function(e) {
                    e.preventDefault();
                    if (_this._current > 0) {
                        return _this.prev();
                    }
                };
            })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='end']", (function(_this) {
                return function(e) {
                    e.preventDefault();
                    return _this.end();
                };
            })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='pause-resume']", function(e) {
                var $this;
                e.preventDefault();
                $this = $(this);
                $this.text(_this._paused ? $this.data('pause-text') : $this.data('resume-text'));
                if (_this._paused) {
                    return _this.resume();
                } else {
                    return _this.pause();
                }
            });
        };

        Tour.prototype._initKeyboardNavigation = function() {
            if (!this._options.keyboard) {
                return;
            }
            return $(document).on("keyup.tour-" + this._options.name, (function(_this) {
                return function(e) {
                    if (!e.which) {
                        return;
                    }
                    switch (e.which) {
                        case 39:
                            // arrow right
                            if ($(".tour-step-element-reflexOnly").length == 0) {
                                e.preventDefault();
                                if (_this._isLast()) {
                                    return _this.end();
                                } else {
                                    return _this.next();
                                }
                            }

                            break;

                        case 37:
                            // arrow left
                            if ($(".tour-step-element-reflexOnly").length == 0) {
                                e.preventDefault();
                                if (_this._current > 0) {
                                    return _this.prev();
                                }
                            }
                            break;

                        case 27:
                            // escape
                            e.preventDefault();
                            return _this.end();
                    }
                };
            })(this));
        };

        /* Handle promises */
        // If param is a promise, returns the promise back to the caller. Otherwise returns null.
        // Only purpose is to make calls to _callOnPromiseDone() simple - first param of _callOnPromiseDone()
        // accepts either null or a promise to smart call either promise or straight callback. This
        // pair of funcs therefore allows easy integration of user code to return callbacks or promises
        Tour.prototype._makePromise = function(possiblePromise) {
            if (possiblePromise && $.isFunction(possiblePromise.then)) {
                return possiblePromise;
            } else {
                return null;
            }
        };

        // Creates a promise wrapping the callback if valid promise is provided as first arg. If
        // first arg is not a promise, simply uses direct function call of callback.
        Tour.prototype._callOnPromiseDone = function(promise, callback, arg) {
            if (promise) {
                return promise.then(
                    (function(_this) {
                        return function(e) {
                            return callback.call(_this, arg);
                        };
                    })(this)
                );
            } else {
                return callback.call(this, arg);
            }
        };

        // Bootstrap Select custom draws the drop down, force the Z index between Tour overlay and popoper
        Tour.prototype._fixBootstrapSelectPickerZindex = function(step) {
            if (this._isOrphan(step)) {
                // If it's an orphan step, it can't be a selectpicker element
                return;
            }

            // is the current step already visible?
            if ($(document).find(".popover.tour-" + this._options.name + ".tour-" + this._options.name + "-" + this.getCurrentStepIndex()).length != 0) {
                // don't waste time redoing the fix
                return;
            }

            var $selectpicker;
            // is this element or child of this element a selectpicker
            if ($(step.element)[0].tagName.toLowerCase() == "select") {
                $selectpicker = $(step.element);
            } else {
                $selectpicker = $(step.element).find("select:first");
            }

            // is this selectpicker a bootstrap-select: https://github.com/snapappointments/bootstrap-select/
            if ($selectpicker.length > 0 && $selectpicker.parent().hasClass("bootstrap-select")) {
                this._debug("Fixing Bootstrap SelectPicker");
                // set zindex to open dropdown over background element and at zindex of highlight element
                $selectpicker.parent().css("z-index", "1111");

                // store the element for other calls. Mainly for when step is hidden, selectpicker must be unfixed / z index reverted to avoid visual issues.
                // storing element means we don't need to find it again later
                this._setStepFlag(this.getCurrentStepIndex(), "elementBootstrapSelectpicker", $selectpicker);
            }
        };

        // Revert the Z index between Tour overlay and popoper
        Tour.prototype._unfixBootstrapSelectPickerZindex = function(step) {
            var $selectpicker = this._getStepFlag(this.getCurrentStepIndex(), "elementBootstrapSelectpicker");
            if ($selectpicker) {
                this._debug("Unfixing Bootstrap SelectPicker");
                // set zindex to open dropdown over background element
                $selectpicker.parent().css("z-index", "auto");
            }
        };


        /* Handle Overlay */
        Tour.prototype._createOverlayElements = function() {
            // the .substr(1) is because the DOMID_ consts start with # for jq object ease...
            var $backdrop = $('<div class="tour-backdrop" id="' + DOMID_BACKDROP.substr(1) + '"></div>');
            var $highlight = $('<div class="tour-highlight" id="' + DOMID_HIGHLIGHT.substr(1) + '" style="width:0px;height:0px;top:0px;left:0px;"></div>');

            if ($(DOMID_BACKDROP).length === 0) {
                $(this._options.backdropContainer).append($backdrop);
            }
            if ($(DOMID_HIGHLIGHT).length === 0) {
                $(this._options.backdropContainer).append($highlight);
            }
        };

        Tour.prototype._destroyOverlayElements = function(step) {
            $(DOMID_BACKDROP).remove();
            $(DOMID_HIGHLIGHT).remove();
            $(DOMID_PREVENT).remove();

            $(".tour-highlight-element").removeClass("tour-highlight-element");
        };

        Tour.prototype._hideBackdrop = function(stepOpt) {
            var step = stepOpt || null;

            if (step) {
                // No backdrop? No need for highlight
                this._hideHighlightOverlay(step);

                // Does global or this step specify a function for the backdrop layer hide?
                if (typeof step.backdropOptions.animation.backdropHide == "function") {
                    // pass DOM element jq object to function
                    step.backdropOptions.animation.backdropHide($(DOMID_BACKDROP));
                } else {
                    // must be a CSS class
                    $(DOMID_BACKDROP).addClass(step.backdropOptions.animation.backdropHide);
                    $(DOMID_BACKDROP).hide(0, function() {
                        $(this).removeClass(step.backdropOptions.animation.backdropHide);
                    });
                }

            } else {
                $(DOMID_BACKDROP).hide(0);
                $(DOMID_HIGHLIGHT).hide(0);
                $(DOMID_BACKDROP_TEMP).remove();
                $(DOMID_HIGHLIGHT_TEMP).remove();
            }
        };

        Tour.prototype._showBackdrop = function(stepOpt) {
            var step = stepOpt || null;

            // Ensure we're always starting with a clean, hidden backdrop - this ensures any previous step.backdropOptions.animation.* functions
            // haven't messed with the classes
            $(DOMID_BACKDROP).removeClass().addClass("tour-backdrop").hide(0);

            if (step) {
                // Does global or this step specify a function for the backdrop layer show?
                if (typeof step.backdropOptions.animation.backdropShow == "function") {
                    // pass DOM element jq object to function
                    step.backdropOptions.animation.backdropShow($(DOMID_BACKDROP));
                } else {
                    // must be a CSS class
                    $(DOMID_BACKDROP).addClass(step.backdropOptions.animation.backdropShow);
                    $(DOMID_BACKDROP).show(0, function() {
                        $(this).removeClass(step.backdropOptions.animation.backdropShow);
                    });
                }


                // Now handle the highlight layer. The backdrop and highlight layers operate together to create the visual backdrop, but are handled
                // as separate DOM and code elements.
                if (this._isOrphan(step)) {
                    // Orphan step will never require a highlight, as there's no element
                    if ($(DOMID_HIGHLIGHT).is(':visible')) {
                        this._hideHighlightOverlay(step);
                    } else {
                        // orphan step with highlight layer already hidden - do nothing
                    }
                } else {
                    // Not an orphan, so requires a highlight layer.
                    if ($(DOMID_HIGHLIGHT).is(':visible')) {
                        // Already visible, so this is a transition - move from 1 position to another. This shouldn't be possible,
                        // as a call to showBackdrop() logically means the backdrop is hidden, therefore the highlight is hidden. Kept for safety.
                        this._positionHighlightOverlay(step);
                    } else {
                        // Not visible, this is a show
                        this._showHighlightOverlay(step);
                    }
                }

            } else {
                $(DOMID_BACKDROP).show(0);
                $(DOMID_HIGHLIGHT).show(0);
            }
        };

        // Creates an object representing the current step with a subset of properties and functions, for
        // tour creator to use when passing functions to step.backdropOptions.animation options
        Tour.prototype._createStepSubset = function(step) {
            var _this = this;
            var _stepElement = $(step.element);

            var stepSubset = {
                element: _stepElement,
                container: step.container,
                autoscroll: step.autoscroll,
                backdrop: step.backdrop,
                preventInteraction: step.preventInteraction,
                isOrphan: this._isOrphan(step),
                orphan: step.orphan,
                duration: step.duration,
                delay: step.delay,
                fnPositionHighlight: function() {
                    _this._debug("Positioning highlight (fnPositionHighlight) over step element " + _stepElement[0].id + ":\nWidth = " + _stepElement.outerWidth() + ", height = " + _stepElement.outerHeight() + "\nTop: " + _stepElement.offset().top + ", left: " + _stepElement.offset().left);
                    $(DOMID_HIGHLIGHT).width(_stepElement.outerWidth()).height(_stepElement.outerHeight()).offset(_stepElement.offset());
                },

            };

            return stepSubset;
        };

        // Shows the highlight and applies class to highlighted element
        Tour.prototype._showHighlightOverlay = function(step) {
            // safety check, ensure no other elem has the highlight class
            var $elemTmp = $(".tour-highlight-element");
            if ($elemTmp.length > 0) {
                $elemTmp.removeClass('tour-highlight-element');
            }

            // Is this a modal - we must set the zindex on the modal element, not the modal-content element
            var $modalCheck = $(step.element).parents(".modal:first");
            if ($modalCheck.length) {
                $modalCheck.addClass('tour-highlight-element');
            } else {
                $(step.element).addClass('tour-highlight-element');
            }

            // Ensure we're always starting with a clean, hidden highlight - this ensures any previous step.backdropOptions.animation.* functions
            // haven't messed with the classes
            $(DOMID_HIGHLIGHT).removeClass().addClass("tour-highlight").hide(0);

            if (typeof step.backdropOptions.animation.highlightShow == "function") {
                // pass DOM element jq object to function. Function is completely responsible for positioning and showing.
                // dupe the step to avoid function messing with original object.
                step.backdropOptions.animation.highlightShow($(DOMID_HIGHLIGHT), this._createStepSubset(step));
            } else {
                // must be a CSS class. Give a default animation
                $(DOMID_HIGHLIGHT).css({
                    "opacity": step.backdropOptions.highlightOpacity,
                    "background-color": step.backdropOptions.highlightColor
                });

                $(DOMID_HIGHLIGHT).width(0).height(0).offset({
                    top: 0,
                    left: 0
                });
                $(DOMID_HIGHLIGHT).show(0);
                $(DOMID_HIGHLIGHT).addClass(step.backdropOptions.animation.highlightShow);

                $(DOMID_HIGHLIGHT).width($(step.element).outerWidth()).height($(step.element).outerHeight()).offset($(step.element).offset());
                $(DOMID_HIGHLIGHT).one('webkitAnimationEnd oanimationend msAnimationEnd animationend', function() {
                    $(DOMID_HIGHLIGHT).removeClass(step.backdropOptions.animation.highlightShow);
                });
            }
        };

        // Repositions a currently visible highlight
        Tour.prototype._positionHighlightOverlay = function(step) {
            // safety check, ensure no other elem has the highlight class
            var $elemTmp = $(".tour-highlight-element");
            if ($elemTmp.length > 0) {
                $elemTmp.removeClass('tour-highlight-element');
            }

            // Is this a modal - we must set the zindex on the modal element, not the modal-content element
            var $modalCheck = $(step.element).parents(".modal:first");
            if ($modalCheck.length) {
                $modalCheck.addClass('tour-highlight-element');
            } else {
                $(step.element).addClass('tour-highlight-element');
            }

            if (typeof step.backdropOptions.animation.highlightTransition == "function") {
                // Don't clean existing classes - this allows tour coder to fully control the highlight between steps

                // pass DOM element jq object to function. Function is completely responsible for positioning and showing.
                // dupe the step to avoid function messing with original object.
                step.backdropOptions.animation.highlightTransition($(DOMID_HIGHLIGHT), this._createStepSubset(step));
            } else {
                // must be a CSS class. Start by cleaning all other classes
                $(DOMID_HIGHLIGHT).removeClass().addClass("tour-highlight");

                // obey step options
                $(DOMID_HIGHLIGHT).css({
                    "opacity": step.backdropOptions.highlightOpacity,
                    "background-color": step.backdropOptions.highlightColor
                });

                // add transition animations
                $(DOMID_HIGHLIGHT).addClass(step.backdropOptions.animation.highlightTransition);
                $(DOMID_HIGHLIGHT).width($(step.element).outerWidth()).height($(step.element).outerHeight()).offset($(step.element).offset());
                $(DOMID_HIGHLIGHT).one('webkitAnimationEnd oanimationend msAnimationEnd animationend', function() {
                    $(DOMID_HIGHLIGHT).removeClass(step.backdropOptions.animation.highlightTransition);
                });
            }
        };

        Tour.prototype._hideHighlightOverlay = function(step) {
            // remove the highlight class
            $(".tour-highlight-element").removeClass('tour-highlight-element');

            if (typeof step.backdropOptions.animation.highlightHide == "function") {
                // pass DOM element jq object to function. Function is completely responsible for positioning and showing.
                // dupe the step to avoid function messing with original object.
                step.backdropOptions.animation.highlightHide($(DOMID_HIGHLIGHT), this._createStepSubset(step));
            } else {
                // must be a CSS class
                $(DOMID_HIGHLIGHT).addClass(step.backdropOptions.animation.highlightHide);
                //$(DOMID_HIGHLIGHT).width(0).height(0).offset({ top: 0, left: 0 });
                $(DOMID_HIGHLIGHT).one('webkitAnimationEnd oanimationend msAnimationEnd animationend', function() {
                    // ensure we end with a clean div
                    $(DOMID_HIGHLIGHT).removeClass().addClass("tour-highlight");
                    $(DOMID_HIGHLIGHT).hide(0);
                });
            }
        };

        // Moves, shows or hides the backdrop and highlight element to match the specified step
        Tour.prototype._updateBackdropElements = function(step) {
            // Change to backdrop visibility required? (step.backdrop != current $(DOMID_BACKDROP) visibility)
            if (step.backdrop != $(DOMID_BACKDROP).is(':visible')) {
                // step backdrop not in sync with actual backdrop. Deal with it!
                if (step.backdrop) {
                    // handles both the background div and the highlight layer
                    this._showBackdrop(step);
                } else {
                    this._hideBackdrop(step);
                }
            } else {
                // backdrop is in the correct state (visible/non visible) for this step
                if (step.backdrop) {
                    // Step includes backdrop, and backdrop is already visible.
                    // Is this step an orphan (i.e.: no highlight)?
                    if (this._isOrphan(step)) {
                        // Orphan doesn't require highlight as no element. Is the highlight currently visible? (from the previous step)
                        if ($(DOMID_HIGHLIGHT).is(':visible')) {
                            // Need to hide it
                            this._hideHighlightOverlay(step);
                        } else {
                            // Highlight not visible, not required. Do nothing.
                        }
                    } else {
                        // Highlight required
                        if ($(DOMID_HIGHLIGHT).is(':visible')) {
                            // Transition it
                            this._positionHighlightOverlay(step);
                        } else {
                            // Show it
                            this._showHighlightOverlay(step);
                        }
                    }
                } else {
                    // Step does not include backdrop, backdrop is already hidden.
                    // Ensure highlight is also hidden - safety check as hideBackdrop also hides highlight
                    if ($(DOMID_HIGHLIGHT).is(':visible')) {
                        this._hideHighlightOverlay(step);
                    }
                }
            }

            // purpose of this code is due to elements with position: fixed and z-index: https://github.com/IGreatlyDislikeJavascript/bootstrap-tourist/issues/38
            $(DOMID_BACKDROP_TEMP).remove();
            $(DOMID_HIGHLIGHT_TEMP).remove();
            if (step.backdropOptions.backdropSibling == true) {
                $(DOMID_HIGHLIGHT).addClass('tour-behind');
                $(DOMID_BACKDROP).addClass('tour-zindexFix');
                $(DOMID_HIGHLIGHT).clone().prop('id', DOMID_HIGHLIGHT_TEMP.substring(1)).removeClass('tour-behind').insertAfter(".tour-highlight-element");
                $(DOMID_BACKDROP).clone().prop('id', DOMID_BACKDROP_TEMP.substring(1)).removeClass('tour-zindexFix').insertAfter(".tour-highlight-element");
            } else {
                $(DOMID_HIGHLIGHT).removeClass('tour-behind');
                $(DOMID_BACKDROP).removeClass('tour-zindexFix');
            }
        };

        // Updates visibility of the preventInteraction div and any other overlay elements added in future features
        Tour.prototype._updateOverlayElements = function(step) {
            // check if the popover for the current step already exists (is this a redraw)
            if (step.preventInteraction) {
                this._debug("preventInteraction == true, adding overlay");
                if ($(DOMID_PREVENT).length === 0) {
                    $('<div class="tour-prevent" id="' + DOMID_PREVENT.substr(1) + '" style="width:0px;height:0px;top:0px;left:0px;"></div>').insertAfter(DOMID_HIGHLIGHT);
                }

                $(DOMID_PREVENT).width($(step.element).outerWidth()).height($(step.element).outerHeight()).offset($(step.element).offset());
            } else {
                $(DOMID_PREVENT).remove();
            }

        };

        Tour.prototype._clearTimer = function() {
            window.clearTimeout(this._timer);
            this._timer = null;
            this._duration = null;
            return this._duration;
        };

        Tour.prototype._getProtocol = function(url) {
            url = url.split('://');
            if (url.length > 1) {
                return url[0];
            } else {
                return 'http';
            }
        };

        Tour.prototype._getHost = function(url) {
            url = url.split('//');
            url = url.length > 1 ? url[1] : url[0];
            return url.split('/')[0];
        };

        Tour.prototype._getPath = function(path) {
            return path.replace(/\/?$/, '').split('?')[0].split('#')[0];
        };

        Tour.prototype._getQuery = function(path) {
            return this._getParams(path, '?');
        };

        Tour.prototype._getHash = function(path) {
            return this._getParams(path, '#');
        };

        Tour.prototype._getParams = function(path, start) {
            var j,
                len,
                param,
                params,
                paramsObject;
            params = path.split(start);
            if (params.length === 1) {
                return {};
            }
            params = params[1].split('&');
            paramsObject = {};
            for (j = 0, len = params.length; j < len; j++) {
                param = params[j];
                param = param.split('=');
                paramsObject[param[0]] = param[1] || '';
            }
            return paramsObject;
        };

        Tour.prototype._equal = function(obj1, obj2) {
            var j,
                k,
                len,
                obj1Keys,
                obj2Keys,
                v;
            if ({}
                .toString.call(obj1) === '[object Object]' && {}
                .toString.call(obj2) === '[object Object]') {
                obj1Keys = Object.keys(obj1);
                obj2Keys = Object.keys(obj2);
                if (obj1Keys.length !== obj2Keys.length) {
                    return false;
                }
                for (k in obj1) {
                    v = obj1[k];
                    if (!this._equal(obj2[k], v)) {
                        return false;
                    }
                }
                return true;
            } else if ({}
                .toString.call(obj1) === '[object Array]' && {}
                .toString.call(obj2) === '[object Array]') {
                if (obj1.length !== obj2.length) {
                    return false;
                }
                for (k = j = 0, len = obj1.length; j < len; k = ++j) {
                    v = obj1[k];
                    if (!this._equal(v, obj2[k])) {
                        return false;
                    }
                }
                return true;
            } else {
                return obj1 === obj2;
            }
        };

        return Tour;

    })();
    return Tour;
});