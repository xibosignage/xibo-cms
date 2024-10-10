/*
 * Copyright (C) 2022 Xibo Signage Ltd
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
/* Int: Current logging level */
let LOG_LEVEL;

/* String: Client Version */
const VERSION = '3.1';

/* Int: Counter to ensure unique IDs */
let ID_COUNTER = 0;

/* Global preview object ( Layout ) */
let previewLayout;

window.dsInit = function(layoutid, options, layoutPreview) {
  LOG_LEVEL = 10;
  /* Hide the info and log divs */
  $('.preview-log').css('display', 'none');
  $('.preview-info').css('display', 'none');
  $('.preview-end').css('display', 'none');

  /* Setup a keypress handler for local commands */
  document.onkeypress = keyHandler;

  playLog(0, 'info', 'Xibo HTML Preview v' + VERSION + ' Starting Up', true);
  const preload = {
    addedFiles: [],
    preloader: html5Preloader(),
    addFiles: function(url) {
      // Wrapped add files method, checking if
      // the files were added already and save a list
      if (!this.addedFiles.includes(url)) {
        this.preloader.addFiles(url);
        this.addedFiles.push(url);
      }
    },
  };

  previewLayout = new Layout(layoutid, options, preload, layoutPreview);
};

/* Generate a unique ID for region DIVs, media nodes etc */
function nextId() {
  if (ID_COUNTER > 500) {
    ID_COUNTER = 0;
  }

  ID_COUNTER = ID_COUNTER + 1;
  return ID_COUNTER;
}

/* OnScreen Log */
function playLog(logLevel, logClass, logMessage, logToScreen) {
  if (logLevel <= LOG_LEVEL) {
    const msg = timestamp() + ' ' + logClass.toUpperCase() + ': ' + logMessage;
    if (logLevel > 0) {
      console.debug(msg);
    }

    if (logToScreen) {
      // document.getElementById("log").innerHTML = msg;
      $('.preview-log').html(msg);
    }
  }
}

/* Timestamp Function for Logs */
function timestamp() {
  let str = '';

  const currentTime = new Date();
  const day = currentTime.getDate();
  const month = currentTime.getMonth() + 1;
  const year = currentTime.getFullYear();
  const hours = currentTime.getHours();
  let minutes = currentTime.getMinutes();
  let seconds = currentTime.getSeconds();
  const milliseconds = currentTime.getMilliseconds();

  if (minutes < 10) {
    minutes = '0' + minutes;
  }
  if (seconds < 10) {
    seconds = '0' + seconds;
  }
  str += day + '/' + month + '/' + year + ' ';
  str += hours + ':' + minutes + ':' + seconds + '\'' + milliseconds;

  return str;
}

/* Function to handle key presses */
function keyHandler(event) {
  const chCode = ('charCode' in event) ? event.charCode : event.keyCode;
  const letter = String.fromCharCode(chCode);

  if (letter == 'l') {
    const log = $('.preview-log');
    if (log.css('display') == 'none') {
      log.css('display', 'block');
    } else {
      log.css('display', 'none');
    }
  }
  /* else if (letter == 'i') {
      if ($("#info_"+self.id).css("display") == 'none') {
          sw = $("#screen_"+self.id).width();
          sh = $("#screen_"+self.id).height();

          x = Math.round((sw - 500) / 2);
          y = Math.round((sh - 400) / 2);

          if (x > 0) {
              $("#info_"+self.id).css("left", x);
          }

          if (y > 0) {
              $("#info_"+self.id).css("top", y);
          }

          $("#info_"+self.id).css("display", "block");
      }
      else {
          $("#info_"+self.id).css("display", "none");
      }
  }*/
}

function Layout(id, options, preload, layoutPreview) {
  /* Layout Object */
  /* Parses a layout and when run runs it in containerName */

  const self = this;
  self.id = id;
  self.parseXlf = function(data) {
    playLog(10, 'debug', 'Parsing Layout ' + self.id, false);
    self.containerName = 'L' + self.id + '-' + nextId();

    // Set max z-index var
    self.regionMaxZIndex = 0;

    /* Create a hidden div to show the layout in */
    const screen = $('#screen_' + self.id);
    screen.append('<div id="' + self.containerName + '"></div>');
    if (layoutPreview === false) {
      screen.append(
        '<a style="position:absolute;top:0;left:0;width:100%;height:100%;"' +
        ' target="_blank" href="' + screen.parent().parent().attr('data-url') +
        '"></a>',
      );
    }

    const layout = $('#' + self.containerName);
    layout.css('display', 'none');
    layout.css('outline', 'red solid thin');

    /* Calculate the screen size */
    self.sw = screen.width();
    self.sh = screen.height();
    playLog(7, 'debug', 'Screen is (' + self.sw + 'x' + self.sh + ') pixels');

    /* Find the Layout node in the XLF */
    self.layoutNode = data;

    /* Get Layout Size */
    self.xw = $(self.layoutNode).filter(':first').attr('width');
    self.xh = $(self.layoutNode).filter(':first').attr('height');
    self.zIndex = $(self.layoutNode).filter(':first').attr('zindex');
    playLog(7, 'debug', 'Layout is (' + self.xw + 'x' + self.xh + ') pixels');

    /* Calculate Scale Factor */
    self.scaleFactor = Math.min((self.sw / self.xw), (self.sh / self.xh));
    self.sWidth = Math.round(self.xw * self.scaleFactor);
    self.sHeight = Math.round(self.xh * self.scaleFactor);
    self.offsetX = Math.abs(self.sw - self.sWidth) / 2;
    self.offsetY = Math.abs(self.sh - self.sHeight) / 2;
    playLog(7, 'debug', 'Scale Factor is ' + self.scaleFactor);
    playLog(
      7,
      'debug', 'Render will be (' +
      self.sWidth + 'x' + self.sHeight + ') pixels');
    playLog(
      7,
      'debug', 'Offset will be (' +
      self.offsetX + ',' + self.offsetY + ') pixels');

    /* Scale the Layout Container */
    layout.css('width', self.sWidth + 'px');
    layout.css('height', self.sHeight + 'px');
    layout.css('position', 'absolute');
    layout.css('left', self.offsetX + 'px');
    layout.css('top', self.offsetY + 'px');

    if (self.zIndex != null) {
      layout.css('z-index', self.zIndex);
    }

    /* Set the layout background */
    self.bgColour = $(self.layoutNode).filter(':first').attr('bgcolor');
    self.bgImage = $(self.layoutNode).filter(':first').attr('background');

    if (!(self.bgImage == '' || self.bgImage == undefined)) {
      /* Extract the image ID from the filename */
      self.bgId = self.bgImage.substring(0, self.bgImage.indexOf('.'));

      const tmpUrl =
        options.layoutBackgroundDownloadUrl
          .replace(':id', self.id) + '?preview=1';

      preload.addFiles(
        tmpUrl + '&width=' + self.sWidth +
        '&height=' + self.sHeight + '&dynamic&proportional=0',
      );
      layout.css(
        'background',
        'url(\'' + tmpUrl + '&width=' + self.sWidth + '&height=' +
        self.sHeight + '&dynamic&proportional=0\')',
      );
      layout.css('background-repeat', 'no-repeat');
      layout.css('background-size', self.sWidth + 'px ' + self.sHeight + 'px');
      layout.css('background-position', '0px 0px');
    }

    // Set the background color
    layout.css('background-color', self.bgColour);

    // Create actions
    const actions = [];
    $($.parseXML(self.layoutNode)).find('action').each(function(_idx, el) {
      playLog(4, 'debug', 'Creating action ' + $(el).attr('id'), false);
      actions.push(new Action($(el).attr('id'), el));
    });

    // Create action controller
    self.actionController = new ActionController(self, actions, options);

    // Create drawer
    $($.parseXML(self.layoutNode)).find('drawer').each(function(_idx, el) {
      playLog(4, 'debug', 'Creating drawer ' + $(el).attr('id'), false);
      self.drawer = el;
    });

    // Create regions
    $($.parseXML(self.layoutNode)).find('region').each(function(_idx, el) {
      playLog(4, 'debug', 'Creating region ' + $(el).attr('id'), false);
      self.regionObjects.push(
        new Region(self, $(el).attr('id'), el, options, preload),
      );
    });
    playLog(
      4,
      'debug',
      'Layout ' + self.id + ' has ' + self.regionObjects.length + ' regions',
    );

    self.actionController.initTouchActions();

    self.ready = false;
    preload.addFiles(options.loaderUrl);

    if (layoutPreview) {
      // previewing only one layout in the layout preview page
      preload.preloader.on('finish', self.run);
    } else {
      // previewing a set of layouts in the campaign preview page
      self.run();
    }
  };

  self.run = function() {
    playLog(4, 'debug', 'Running Layout ID ' + self.id, false);
    if (self.ready) {
      $('#' + self.containerName).css('display', 'block');
      $('#splash_' + self.id).css('display', 'none');

      for (let i = 0; i < self.regionObjects.length; i++) {
        playLog(4, 'debug',
          'Running region ' + self.regionObjects[i].id, false);
        self.regionObjects[i].run();
      }
    } else {
      self.checkReadyState(40, self.run, function() {
        playLog(4, 'error',
          'Attempted to run Layout ID ' + self.id + ' before it was ready.',
          false);
      });
    }
  };

  self.end = function() {
    // Send message to parent window
    parent.postMessage('viewerStoppedPlaying');

    /* Ask the layout to gracefully stop running now */
    for (let i = 0; i < self.regionObjects.length; i++) {
      self.regionObjects[i].end();
    }
  };

  self.destroy = function() {
    /* Forcibly remove the layout and destroy this object
       Layout Object may not be reused after this */
  };

  self.regionExpired = function() {
    /* One of the regions on the layout expired
       Check if all the regions have expired, and if they did
       end the layout */
    playLog(5, 'debug',
      'A region expired. Checking if all regions have expired.', false);

    self.allExpired = true;

    for (let i = 0; i < self.regionObjects.length; i++) {
      playLog(4, 'debug',
        'Region ' + self.regionObjects[i].id + ' expired? ' +
        self.regionObjects[i].complete,
        false);
      if (!self.regionObjects[i].complete) {
        self.allExpired = false;
      }
    }

    if (self.allExpired) {
      playLog(4, 'debug', 'All regions have expired', false);
      self.end();
    }
  };

  self.regionEnded = function() {
    /* One of the regions completed it's exit transition
       Check al the regions have completed exit transitions.
       If they did, bring on the next layout */

    playLog(5, 'debug',
      'A region ended. Checking if all regions have ended.', false);

    self.allEnded = true;

    for (let i = 0; i < self.regionObjects.length; i++) {
      playLog(4, 'debug',
        'Region ' + self.regionObjects[i].id + ': ' +
        self.regionObjects[i].ended, false);
      if (!self.regionObjects[i].ended) {
        self.allEnded = false;
      }
    }

    if (self.allEnded) {
      playLog(4, 'debug', 'All regions have ended', false);

      self.stopAllMedia();

      $('#end_' + self.id).css('display', 'block');
      // $("#" + self.containerName).remove();
    }
  };

  self.stopAllMedia = function() {
    playLog(3, 'debug', 'Stop all media!');

    for (let i = 0; i < self.regionObjects.length; i++) {
      const region = self.regionObjects[i];
      for (let j = 0; j < region.mediaObjects.length; j++) {
        const media = region.mediaObjects[j];
        media.stop();
      }
    }
  };

  // Check layout state
  self.checkReadyState = function(numTries, success, failure) {
    self.ready = true;

    // Check every region
    for (let i = 0; i < self.regionObjects.length; i++) {
      const region = self.regionObjects[i];
      region.checkReadyState();
      if (!region.ready) {
        self.ready = false;
      }
    }

    if (!self.ready) {
      numTries--;

      if (numTries <= 0) {
        failure();
      } else {
        // Not ready, check every 250ms
        setTimeout(function() {
          self.checkReadyState(numTries, success, failure);
        }, 250);
      }
    } else {
      success();
    }
  };

  self.ready = false;
  self.id = id;
  self.regionObjects = [];
  self.drawer = [];
  self.allExpired = false;

  playLog(3, 'debug', 'Loading Layout ' + self.id, true);
  $.ajax({
    type: 'GET',
    url: options.getXlfUrl,
    success: self.parseXlf,
  });
}

function Region(parent, id, xml, options, preload) {
  const self = this;
  self.layout = parent;
  self.id = id;
  self.xml = xml;
  self.mediaObjects = [];
  self.mediaObjectsActions = [];
  self.currentMedia = -1;
  self.complete = false;
  self.containerName = 'R-' + self.id + '-' + nextId();
  self.ending = false;
  self.ended = false;
  self.oneMedia = false;
  self.oldMedia = undefined;
  self.curMedia = undefined;
  self.totalMediaObjects = $(self.xml).children('media').length;
  self.ready = false;

  self.finished = function() {
    // Remove temporary media elements
    self.mediaObjects = self.mediaObjects.filter(function(media) {
      return !media.singlePlay;
    });

    // Mark as complete
    self.complete = true;
    self.layout.regionExpired();
  };

  self.exitTransition = function() {
    /* TODO: Actually implement region exit transitions */
    $('#' + self.containerName).css('display', 'none');
    self.exitTransitionComplete();
  };

  self.end = function() {
    playLog(8, 'debug', 'Region ' + self.id + ' has ended!');
    self.ending = true;
    /* The Layout has finished running */
    /* Do any region exit transition then clean up */

    self.exitTransition();
  };

  self.exitTransitionComplete = function() {
    self.ended = true;
    self.layout.regionEnded();
  };

  self.transitionNodes = function(oldMedia, newMedia) {
    /* TODO: Actually support the transition */

    const loop =
      newMedia.options['loop'] == '1' ||
      (
        newMedia.region.options['loop'] == '1' &&
        newMedia.region.totalMediaObjects == 1
      );

    if (oldMedia) {
      oldMedia.pause();
    }

    if (oldMedia == newMedia && !loop) {
      return;
    }

    if (loop && oldMedia == newMedia) {
      oldMedia.reset();
    }

    if (oldMedia) {
      oldMedia.stop();
    }

    // If the region has finished, don't run/show media
    if (self.ended) {
      return;
    }

    newMedia.run();

    $('#' + newMedia.containerName).css('display', 'block');
  };

  self.nextMedia = function() {
    /* The current media has finished running */
    /* Show the next item */
    if (self.ended) {
      return;
    }

    if (self.curMedia) {
      playLog(8, 'debug', 'nextMedia -> Old: ' + self.curMedia.id);
      self.oldMedia = self.curMedia;
    } else {
      self.oldMedia = undefined;
    }

    self.currentMedia = self.currentMedia + 1;

    if (self.currentMedia >= self.mediaObjects.length) {
      self.finished();
      self.currentMedia = 0;
    }

    playLog(8, 'debug',
      'nextMedia -> Next up is media ' +
      (self.currentMedia + 1) + ' of ' + self.mediaObjects.length);

    self.curMedia = self.mediaObjects[self.currentMedia];

    if (self.curMedia != undefined) {
      playLog(8, 'debug', 'nextMedia -> New: ' + self.curMedia.id);
    }

    /* Do the transition */
    self.transitionNodes(self.oldMedia, self.curMedia);
  };

  self.previousMedia = function() {
    self.currentMedia = self.currentMedia - 1;

    if (self.currentMedia < 0 || self.ended) {
      self.currentMedia = 0;
      return;
    }

    if (self.curMedia) {
      playLog(8, 'debug', 'previousMedia -> Old: ' + self.curMedia.id);
      self.oldMedia = self.curMedia;
    } else {
      self.oldMedia = undefined;
    }

    self.curMedia = self.mediaObjects[self.currentMedia];

    if (self.curMedia != undefined) {
      playLog(8, 'debug', 'previousMedia -> New: ' + self.curMedia.id);
    }

    /* Do the transition */
    self.transitionNodes(self.oldMedia, self.curMedia);
  };

  // Check if region is ready to play
  self.checkReadyState = function() {
    for (let index = 0; index < self.mediaObjects.length; index++) {
      const media = self.mediaObjects[index];
      if (!media.ready) {
        self.ready = false;
        return;
      }
    }

    self.ready = true;
  };

  self.run = function() {
    if (self.totalMediaObjects > 0) {
      self.nextMedia();
    }
  };

  /* Build Region Options */
  self.options = [];
  $(self.xml).children('options').children().each(function(_idx, el) {
    playLog(9, 'debug',
      'Option ' + el.nodeName.toLowerCase() + ' -> ' + $(el).text(), false);
    self.options[el.nodeName.toLowerCase()] = $(el).text();
  });

  self.sWidth = $(xml).attr('width') * self.layout.scaleFactor;
  self.sHeight = $(xml).attr('height') * self.layout.scaleFactor;
  self.offsetX = $(xml).attr('left') * self.layout.scaleFactor;
  self.offsetY = $(xml).attr('top') * self.layout.scaleFactor;
  self.zIndex = $(xml).attr('zindex');

  $('#' + self.layout.containerName)
    .append('<div id="' + self.containerName + '"></div>');

  /* Scale the Layout Container */
  $('#' + self.containerName).css('width', self.sWidth + 'px');
  $('#' + self.containerName).css('height', self.sHeight + 'px');
  $('#' + self.containerName).css('position', 'absolute');
  $('#' + self.containerName).css('left', self.offsetX + 'px');
  $('#' + self.containerName).css('top', self.offsetY + 'px');

  if (self.zIndex != null) {
    $('#' + self.containerName).css('z-index', self.zIndex);

    // Set new layout max z-index value
    if (parseInt(self.zIndex) > self.layout.regionMaxZIndex) {
      self.layout.regionMaxZIndex = parseInt(self.zIndex);
    }
  }

  playLog(4, 'debug', 'Created region ' + self.id, false);
  playLog(7,
    'debug',
    'Render will be (' + self.sWidth + 'x' + self.sHeight + ') pixels');
  playLog(7,
    'debug',
    'Offset will be (' + self.offsetX + ',' + self.offsetY + ') pixels');

  $(self.xml).children('media').each(function(_idx, el) {
    playLog(5, 'debug', 'Creating media ' + $(el).attr('id'), false);
    self.mediaObjects.push(
      new media(self, $(el).attr('id'), el, options, preload));
  });

  // Add media to region for targetted actions
  for (
    let index = 0;
    index < self.layout.actionController.actions.length;
    index++
  ) {
    const action = self.layout.actionController.actions[index];
    // Get action from drawer
    const attributes = $(action.xml).prop('attributes');

    if (
      attributes.target.value == 'region' &&
      attributes.actionType.value == 'navWidget' &&
      attributes.targetId.value == self.id
    ) {
      const drawerMedia =
        $(self.layout.drawer).find('media#' + attributes.widgetId.value)[0];

      // Add drawer media to the region
      self.mediaObjectsActions.push(
        new media(
          self,
          $(drawerMedia).attr('id'),
          drawerMedia,
          options,
          preload,
        ),
      );
    }
  }

  // If the regions does not have any media
  // change its background to transparent red
  if ($(self.xml).children('media').length == 0) {
    $self = $('#' + self.containerName);

    // Mark empty region as complete
    self.complete = true;

    messageSize = (self.sWidth > self.sHeight) ? self.sHeight : self.sWidth;

    $self.css('background-color', 'rgba(255, 0, 0, 0.25)');
    $self.append(
      '<div class="empty-message" id="empty_' + self.containerName + '"></div>',
    );

    $message = $('#empty_' + self.containerName);
    $message.append(
      '<span class="empty-icon fa fa-exclamation-triangle" style="font-size:' +
      messageSize / 4 + 'px"></span>',
    );
    $message.append(
      '<span class="empty-icon">' +
      previewTranslations.emptyRegionMessage + '</span>',
    );
  }

  playLog(4, 'debug',
    'Region ' + self.id + ' has ' + self.mediaObjects.length + ' media items');
}

function media(parent, id, xml, options, preload) {
  // eslint-disable-next-line no-invalid-this
  const self = this;

  self.region = parent;
  self.xml = xml;
  self.id = id;
  self.containerName = 'M-' + self.id + '-' + nextId();
  self.iframeName = self.containerName + '-iframe';
  self.mediaType = $(self.xml).attr('type');
  self.render = $(self.xml).attr('render');
  self.attachedAudio = false;
  self.singlePlay = false;
  self.timeoutId = undefined;
  self.ready = true;
  self.checkIframeStatus = false;
  self.loadIframeOnRun = false;
  self.tempSrc = '';

  if (self.render == undefined) {
    self.render = 'module';
  }

  self.run = function() {
    if (self.iframe) {
      if (self.checkIframeStatus) {
        // Reload iframe
        const iframeDOM = $('#' + self.containerName + ' #' + self.iframeName);
        iframeDOM.css({visibility: 'hidden'});
        iframeDOM[0].src = iframeDOM[0].src;
      } else if (self.loadIframeOnRun) {
        const iframe = self.iframe;
        iframe[0].src = self.tempSrc;
        $('#' + self.containerName).empty().append(iframe);
      } else {
        $('#' + self.containerName).empty().append(self.iframe);
      }
    }

    playLog(5, 'debug',
      'Running media ' + self.id + ' for ' + self.duration + ' seconds');

    if (self.mediaType == 'video') {
      $('#' + self.containerName + '-vid').get(0).play();
    }

    if (self.mediaType == 'audio') {
      $('#' + self.containerName + '-aud').get(0).play();
    }

    if (self.attachedAudio) {
      $('#' + self.containerName + '-attached-aud').get(0).play();
    }

    if (self.duration == 0) {
      if (self.mediaType == 'video') {
        $('#' + self.containerName + '-vid').on('ended', self.region.nextMedia);
        $('#' + self.containerName + '-vid').on('error', self.region.nextMedia);
        $('#' + self.containerName + '-vid').on('click', self.region.nextMedia);
      } else if (self.mediaType == 'audio') {
        $('#' + self.containerName + '-aud').on('ended', self.region.nextMedia);
        $('#' + self.containerName + '-aud').on('error', self.region.nextMedia);
        $('#' + self.containerName + '-aud').on('click', self.region.nextMedia);
      } else {
        self.duration = 3;
        self.timeoutId =
          setTimeout(self.region.nextMedia, self.duration * 1000);
      }
    } else {
      self.timeoutId = setTimeout(self.region.nextMedia, self.duration * 1000);
    }
  };

  self.reset = function() {
    playLog(5, 'debug', 'Reset media ' + self.id);

    // Reset video
    if (self.mediaType == 'video') {
      $('#' + self.containerName + '-vid').get(0).currentTime = 0;
    }

    // Reset audio
    if (self.mediaType == 'audio') {
      $('#' + self.containerName + '-aud').get(0).currentTime = 0;
    }

    // Reset attached audio
    if (self.attachedAudio) {
      $('#' + self.containerName + '-attached-aud').get(0).currentTime = 0;
    }
  };

  self.pause = function() {
    // Stop video
    if (self.mediaType == 'video') {
      $('#' + self.containerName + '-vid').get(0).pause();
    }

    // Stop audio
    if (self.mediaType == 'audio') {
      $('#' + self.containerName + '-aud').get(0).pause();
    }

    // Stop attached audio
    if (self.attachedAudio) {
      $('#' + self.containerName + '-attached-aud').get(0).pause();
    }
  };

  self.stop = function() {
    playLog(5, 'debug', 'Stop media ' + self.id);

    // Hide container
    $('#' + self.containerName).css('display', 'none');
  };

  /* Build Media Options */
  self.duration = $(self.xml).attr('duration');
  self.lkid = $(self.xml).attr('lkid');
  self.options = [];

  $(self.xml).find('options').children().each(function(_idx, el) {
    playLog(9, 'debug',
      'Option ' + el.nodeName.toLowerCase() + ' -> ' + $(el).text(), false);
    self.options[el.nodeName.toLowerCase()] = $(el).text();
  });

  // Show in fullscreen?
  if (self.options.showfullscreen === '1') {
    // Set dimensions as the layout ones
    self.divWidth = self.region.layout.sWidth;
    self.divHeight = self.region.layout.sHeight;
  } else {
    // Set dimensions as the region ones
    self.divWidth = self.region.sWidth;
    self.divHeight = self.region.sHeight;
  }

  $('#' + self.region.containerName)
    .append('<div id="' + self.containerName + '"></div>');

  /* Scale the Content Container */
  const media = $('#' + self.containerName);
  media.css('display', 'none');
  media.css('width', self.divWidth + 'px');
  media.css('height', self.divHeight + 'px');
  media.css('position', 'absolute');
  media.css('background-size', 'contain');
  media.css('background-repeat', 'no-repeat');
  media.css('background-position', 'center');

  // If fullscreen, set position offset to origin
  // ( negative of the region offset ) and set z-index over other elements
  if (self.options.showfullscreen === '1') {
    media.css('left', -self.region.offsetX + 'px');
    media.css('top', -self.region.offsetY + 'px');
    media.css('z-index', self.region.layout.regionMaxZIndex + 1);
  }

  const tmpUrl = options.getResourceUrl
    .replace(':regionId', self.region.id)
    .replace(':id', self.id) + '?preview=1&layoutPreview=1&scale_override=' +
    self.region.layout.scaleFactor;

  // Loop if media has loop, or if region has loop and a single media
  const loop =
    self.options['loop'] == '1' ||
    (self.region.options['loop'] == '1' && self.region.totalMediaObjects == 1);

  if (self.mediaType == 'webpage' || self.mediaType == 'embedded') {
    self.loadIframeOnRun = true;
    self.iframe = $('<iframe scrolling="no" id="' +
      self.iframeName + '" width="' + self.divWidth + 'px" height="' +
      self.divHeight + 'px" style="border:0;"></iframe>');
    self.tempSrc = tmpUrl + '&width=' + self.divWidth + '&height=' +
      self.divHeight + '" width="' + self.divWidth + 'px';
  } else if (self.mediaType === 'image') {
    preload.addFiles(tmpUrl);
    media.css('background-image', 'url(\'' + tmpUrl + '\')');
    if (self.options['scaletype'] === 'stretch') {
      media.css('background-size', '100% 100%');
    } else if (self.options['scaletype'] === 'fit') {
      media.css('background-size', 'cover');
    } else {
      // Center scale type, do we have align or valign?
      const align = (self.options['align'] == '') ?
        'center' : self.options['align'];
      const valign =
        (
          self.options['valign'] == '' ||
          self.options['valign'] == 'middle'
        ) ?
          'center' : self.options['valign'];
      media.css('background-position', align + ' ' + valign);
    }
  } else if (self.mediaType == 'text' || self.mediaType == 'datasetview') {
    self.checkIframeStatus = true;
    self.iframe = $('<iframe scrolling="no" id="' + self.iframeName +
      '" src="' + tmpUrl + '&width=' + self.divWidth + '&height=' +
      self.divHeight + '" width="' + self.divWidth +
      'px" height="' + self.divHeight +
      'px" style="border:0; visibility: hidden;"></iframe>');
  } else if (self.mediaType == 'video') {
    preload.addFiles(tmpUrl);

    self.iframe = $(
      '<video id="' + self.containerName + '-vid" preload="auto" ' +
      ((self.options['mute'] == 1) ? 'muted' : '') + ' ' +
      (loop ? 'loop' : '') + '><source src="' + tmpUrl +
      '">Unsupported Video</video>');

    // Stretch video?
    if (self.options['scaletype'] == 'stretch') {
      self.iframe.css('object-fit', 'fill');
    }
  } else if (self.mediaType == 'audio') {
    preload.addFiles(tmpUrl);

    media.append(
      '<audio id="' + self.containerName + '-aud" preload="auto" ' +
      (loop ? 'loop' : '') + ' ' +
      ((self.options['mute'] == 1) ? 'muted' : '') +
      '><source src="' + tmpUrl + '">Unsupported Audio</audio>');
  } else if (self.mediaType == 'flash') {
    let embedCode = '<OBJECT classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0" WIDTH="100%" HEIGHT="100%" id="Yourfilename" ALIGN="">';
    embedCode = embedCode + '<PARAM NAME=movie VALUE="' + tmpUrl + '"> <PARAM NAME=quality VALUE=high> <param name="wmode" value="transparent"> <EMBED src="' + tmpUrl + '" quality="high" wmode="transparent" WIDTH="100%" HEIGHT="100%" NAME="Yourfilename" ALIGN="" TYPE="application/x-shockwave-flash" PLUGINSPAGE="http://www.macromedia.com/go/getflashplayer"></EMBED> </OBJECT>';
    preload.addFiles(tmpUrl);
    self.iframe = $(embedCode);
  } else if (self.render == 'html' || self.mediaType == 'ticker') {
    self.checkIframeStatus = true;
    self.iframe =
      $('<iframe scrolling="no" id="' + self.iframeName +
        '" src="' + tmpUrl + '&width=' + self.divWidth + '&height=' +
        self.divHeight + '" width="' + self.divWidth + 'px" height="' +
        self.divHeight + 'px" style="border:0; visibility: hidden;"></iframe>');
    /* Check if the ticker duration is based
    on the number of items in the feed */
    if (self.options['durationisperitem'] == '1') {
      const regex = new RegExp('<!-- NUMITEMS=(.*?) -->');
      jQuery.ajax({
        url: tmpUrl + '&width=' + self.divWidth + '&height=' + self.divHeight,
        success: function(html) {
          const res = regex.exec(html);
          if (res != null) {
            /* The ticker is duration per item, so multiply the duration
               by the number of items from the feed */
            self.duration = parseInt(self.duration) * parseInt(res[1]);
          }
        },
        async: false,
      });
    }
  } else {
    media.css('outline', 'red solid thin');
  }

  // Check/set iframe based widgets play status
  if (self.iframe && self.checkIframeStatus) {
    // Set state as false ( for now )
    self.ready = false;

    // Append iframe
    $('#' + self.containerName).empty().append(self.iframe);

    // On iframe load, set state as ready to play full preview
    $(self.iframe).on('load', function() {
      self.ready = true;
      $(self.iframe).css({visibility: 'visible'});
    });
  }

  // Attached audio
  if ($(self.xml).find('audio').length > 0) {
    const $audioObj = $(self.xml).find('audio');
    const $audioUri = $audioObj.find('uri');
    const mediaId = $audioUri.attr('mediaid');

    // Get media url and preload
    const tmpUrl2 = options.libraryDownloadUrl.replace(':id', mediaId);

    // preload.getFile(tmpUrl2);
    if (preload.preloader.filesLoadedMap[tmpUrl2] != undefined) {
      preload.addFiles(tmpUrl2);
    }

    // Set volume if defined
    if ($audioUri.attr('volume') != undefined) {
      const volume = $audioUri.attr('volume') / 100;
      $audioObj.get(0).volume = volume;
    }

    // Loop
    $audioObj.prop('loop', $audioUri.get(0).getAttribute('loop') == '1');
    $audioObj.attr('id', self.containerName + '-attached-aud');
    // $audioUri.remove();
    $audioObj.append('<source src="' + tmpUrl2 + '">Unsupported Audio');

    media.append($audioObj);
    self.attachedAudio = true;
  }

  playLog(5, 'debug', 'Created media ' + self.id);
}

function Action(id, xml) {
  const self = this;

  self.id = id;
  self.xml = xml;
}

function ActionController(parent, actions, options) {
  const self = this;
  self.parent = parent;
  self.actions = [];

  const $container = $('<div class="action-controller noselect"></div>')
    .appendTo($('#' + parent.containerName));
  $container.append(
    $('<div class="action-controller-title"><button class="toggle">' +
      '</button><span class="title">' +
      previewTranslations.actionControllerTitle +
      '</span></div>'));
  const $actionsContainer = $('<div class="actions-container"></div>')
    .appendTo($container);

  for (let index = 0; index < actions.length; index++) {
    const newAction = actions[index];

    // Add action to the controller
    self.actions.push(newAction);

    // Create new action object
    const $newActionHTML = $('<div>');

    // Copy element attributes
    const attributes = $(newAction.xml).prop('attributes');

    $.each(attributes, function(_idx, el) {
      $newActionHTML.data(el.name, el.value);
      $newActionHTML.attr(el.name, el.value);
    });

    // Build HTML for the new action
    let html = '';

    // Add action type
    html += '<span class="action-row-title">' +
      previewTranslations[$newActionHTML.attr('actiontype')];
    if ($newActionHTML.attr('actiontype') == 'navWidget') {
      html += ' <span title="' + previewTranslations.widgetId + '">[' +
        $newActionHTML.attr('widgetId') + ']</span>';
    } else if ($newActionHTML.attr('actiontype') == 'navLayout') {
      html += ' <span title="' + previewTranslations.layoutCode + '">[' +
        $newActionHTML.attr('layoutCode') + ']</span>';
    }
    html += '</span>';

    // Add target
    html += '<span class="action-row-target" title="' +
      previewTranslations.target + '">' + $newActionHTML.attr('target');
    if ($newActionHTML.attr('targetid') != '') {
      html += '(' + $newActionHTML.attr('targetid') +
        $newActionHTML.attr('layoutcode') + ')';
    }
    html += '</span>';

    // Add HTML string to the action
    $newActionHTML.html(html);

    // Append new action to the controller
    $newActionHTML.addClass('action', newAction.id);
    $newActionHTML.attr('originalId', newAction.id);
    $newActionHTML.attr('id', 'A-' + newAction.id + '-' + nextId());
    $newActionHTML.appendTo($actionsContainer);
  }

  // Enable dragging
  $container.draggable({
    handle: '.action-controller-title',
    scroll: false,
    cursor: 'dragging',
    containment: 'parent',
  });

  // Toggle actions visibility
  $container.find('.toggle').on('click', function() {
    $container.toggleClass('d-none');
  });

  // Display according to the number of clickable actions
  $container.toggle(
    $container.find('.action[triggerType="webhook"]').length > 0,
  );

  // Actions
  /** Open a layout preview in a new tab */
  const openLayoutInNewTab = function(layoutCode) {
    if (
      confirm(previewTranslations.navigateToLayout
        .replace('[layoutTag]', layoutCode))
    ) {
      const url = options.layoutPreviewUrl
        .replace('[layoutCode]', layoutCode) + '?findByCode=1';
      window.open(url, '_blank');
    }
  };

  /** Change media in region (next/previous) */
  const nextMediaInRegion = function(regionId, actionType) {
    // Find target region
    for (let index = 0; index < self.parent.regionObjects.length; index++) {
      const region = self.parent.regionObjects[index];
      if (region.id == regionId) {
        if (actionType == 'next') {
          region.nextMedia();
        } else {
          region.previousMedia();
        }
      }
    }
  };

  /** Load media from drawer in a specific region  */
  const loadMediaInRegion = function(regionId, widgetId) {
    // Find target region
    let targetRegion;
    let index = 0;
    for (index = 0; index < self.parent.regionObjects.length; index++) {
      const regionEl = self.parent.regionObjects[index];
      if (regionEl.id == regionId) {
        targetRegion = regionEl;
      }
    }

    // Find media in actions
    let targetMedia;
    for (index = 0; index < targetRegion.mediaObjectsActions.length; index++) {
      const media = targetRegion.mediaObjectsActions[index];

      if (media.id == widgetId) {
        targetMedia = media;
      }
    }

    // Mark media as temporary ( removed after region stop playing or loops )
    targetMedia.singlePlay = true;

    // If region is empty, remove the background colour and empty message
    if (targetRegion.mediaObjects.length === 0) {
      $('#' + targetRegion.containerName).find('.empty-message').remove();
      $('#' + targetRegion.containerName).css('background-color', '');

      // Mark empty region as incomplete
      self.complete = false;
    }

    // Create media in region and play it next
    targetRegion.mediaObjects
      .splice(targetRegion.currentMedia + 1, 0, targetMedia);
    targetRegion.nextMedia();
  };

  /** Run action based on action data */
  const runAction = function(actionData) {
    if (actionData.actionType == 'navLayout') {
      // Open layout preview in a new tab
      openLayoutInNewTab(actionData.layoutCode);
    } else if (
      (
        actionData.actionType == 'previous' ||
        actionData.actionType == 'next'

      ) &&
      actionData.target == 'region'
    ) {
      nextMediaInRegion(actionData.targetId, actionData.actionType);
    } else if (
      actionData.actionType == 'navWidget' &&
      actionData.target == 'region'
    ) {
      loadMediaInRegion(actionData.targetId, actionData.widgetId);
    } else {
      // TODO Handle other action types ( later? )
      console.debug(
        actionData.actionType + ' > ' +
        actionData.target + '[' +
        actionData.targetId + ']',
      );
    }
  };

  // Handle webhook action trigger click
  $container.find('.action[triggerType="webhook"]')
    .on('click', function(event) {
      event.stopPropagation();
      runAction($(event.currentTarget).data());
    }).addClass('clickable');

  // Create/handle layout object user interactions
  self.initTouchActions = function() {
    $container.find('.action[triggerType="touch"]').each(function(_idx, el) {
      const data = $(el).data();

      // Find source object
      let $sourceObj;

      if (data.source == 'layout') {
        $sourceObj = $('#' + self.parent.containerName);
      } else {
        for (let index = 0; index < self.parent.regionObjects.length; index++) {
          const region = self.parent.regionObjects[index];
          if (data.source == 'region') {
            // Try to find region
            if (region.id == data.sourceId) {
              $sourceObj = $('#' + region.containerName);
              break;
            }
          } else if (data.source == 'widget') {
            // Try to find widget/media
            for (
              let index2 = 0;
              index2 < region.mediaObjects.length;
              index2++
            ) {
              const media = region.mediaObjects[index2];

              if (media.id == data.sourceId) {
                $sourceObj = $('#' + media.containerName);
                break;
              }
            }
          }

          // Break loop if we already have a source object
          if ($sourceObj != undefined) {
            break;
          }
        }
      }

      // Handle source click
      // FIXME: We need to handle the case where a drawer
      // widget has an action and it has been loaded to the preview
      if ($sourceObj != undefined) {
        $sourceObj.on('click', function(event) {
          event.stopPropagation();
          runAction(data);
        }).addClass('clickable');
      }
    });
  };
}

/**
 *
 * @param {string} path - request path
 * @param {Object} [data] - optional data object
 * @param {callback} [done] - done callback
 */
// eslint-disable-next-line no-unused-vars
function previewActionTrigger(path, data, done) {
  /**
   * Find media by ID
   * @param {string} id
   */
  const findMediaById = function(id) {
    let newMedia;

    // Find media in all regions
    main:
    for (i = 0; i < previewLayout.regionObjects.length; i++) {
      const region = previewLayout.regionObjects[i];
      for (j = 0; j < region.mediaObjects.length; j++) {
        const media = region.mediaObjects[j];
        if (media.id == id) {
          newMedia = media;
          break main; // break to main loop
        }
      }
    }

    return newMedia;
  };

  // ACTIONS
  if (path == '/duration/set') {
    // Set duration action
    const mediaToChange = findMediaById(data.id);

    if (mediaToChange != undefined) {
      // Change duration
      mediaToChange.duration = data.duration;

      // Update timeout
      clearTimeout(mediaToChange.timeoutId);
      mediaToChange.timeoutId =
        setTimeout(
          mediaToChange.region.nextMedia,
          mediaToChange.duration * 1000,
        );
    }
  } else if (path == '/trigger') {
    // trigger action
    const $actionDOMObj = $('.action[triggercode=' + data.trigger + ']');

    // If action object exists, click to simulate behaviour
    if ($actionDOMObj.length) {
      $actionDOMObj.click();
    }
  }

  // Call callback if exists
  if (typeof done == 'function') {
    done();
  }
}
