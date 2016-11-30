/*
 * Xibo - Digital Signage - http://www.xibo.org.uk
 * Copyright (C) 2014-15 Alex Harrington
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
var LOG_LEVEL;

/* String: Client Version */
var VERSION = "1.8.0";

/* Int: Counter to ensure unique IDs */
var ID_COUNTER = 0;
function dsInit(layoutid, options, layoutPreview) {
    LOG_LEVEL = 10;
    /* Hide the info and log divs */
    $(".preview-log").css("display", "none");
    $(".preview-info").css("display", "none");
    $(".preview-end").css("display", "none");

    /* Setup a keypress handler for local commands */
    document.onkeypress = keyHandler;

    playLog(0, "info", "Xibo HTML Preview v" + VERSION + " Starting Up", true);
    var preload = html5Preloader();
    new Layout(layoutid, options, preload, layoutPreview);
}

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
    if (logLevel <= LOG_LEVEL)
    {
        var msg = timestamp() + " " + logClass.toUpperCase() + ": " + logMessage;
        if (logLevel > 0)
        {
            console.log(msg);
        }
        
        if (logToScreen) {
            //document.getElementById("log").innerHTML = msg;
            $('.preview-log').html(msg);
        }
    }
}

/* Timestamp Function for Logs */
function timestamp() {
    var str = "";

    var currentTime = new Date();
    var day = currentTime.getDate();
    var month = currentTime.getMonth() + 1;
    var year = currentTime.getFullYear();
    var hours = currentTime.getHours();
    var minutes = currentTime.getMinutes();
    var seconds = currentTime.getSeconds();

    if (minutes < 10) {
        minutes = "0" + minutes
    }
    if (seconds < 10) {
        seconds = "0" + seconds
    }
    str += day + "/" + month + "/" + year + " ";
    str += hours + ":" + minutes + ":" + seconds;
    return str;
}

/* Function to handle key presses */
function keyHandler(event) {
    var chCode = ('charCode' in event) ? event.charCode : event.keyCode;
    var letter = String.fromCharCode(chCode);

    if (letter == 'l') {
        var log = $(".preview-log");
        if (log.css("display") == 'none') {
            log.css("display", "block");
        }
        else {
            log.css("display", "none");
        }
    }
    /*else if (letter == 'i') {
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

    var self = this;
    self.id = id;
    self.parseXlf = function(data) {
        playLog(10, "debug", "Parsing Layout " + self.id, false);
        self.containerName = "L" + self.id + "-" + nextId();
        
        /* Create a hidden div to show the layout in */
        var screen = $('#screen_' + self.id) ;
        screen.append('<div id="' + self.containerName  + '"></div>');
        if (layoutPreview === false){
          screen.append('<a style="position:absolute;top:0;left:0;width:100%;height:100%;" target="_blank" href="'+ screen.parent().parent().attr('data-url') + '"></a>');
        }

        var layout = $("#" + self.containerName);
        layout.css("display", "none");
        layout.css("outline", "red solid thin");
        
        /* Calculate the screen size */
        self.sw = screen.width();
        self.sh = screen.height();
        playLog(7, "debug", "Screen is (" + self.sw + "x" + self.sh + ") pixels");
        
        /* Find the Layout node in the XLF */
        self.layoutNode = data;
        
        /* Get Layout Size */
        self.xw = $(self.layoutNode).filter(":first").attr('width');
        self.xh = $(self.layoutNode).filter(":first").attr('height');
        self.zIndex = $(self.layoutNode).filter(":first").attr('zindex');
        playLog(7, "debug", "Layout is (" + self.xw + "x" + self.xh + ") pixels");
        
        /* Calculate Scale Factor */
        self.scaleFactor = Math.min((self.sw/self.xw), (self.sh/self.xh));
        self.sWidth = Math.round(self.xw * self.scaleFactor);
        self.sHeight = Math.round(self.xh * self.scaleFactor);
        self.offsetX = Math.abs(self.sw - self.sWidth) / 2;
        self.offsetY = Math.abs(self.sh - self.sHeight) / 2;
        playLog(7, "debug", "Scale Factor is " + self.scaleFactor);
        playLog(7, "debug", "Render will be (" + self.sWidth + "x" + self.sHeight + ") pixels");
        playLog(7, "debug", "Offset will be (" + self.offsetX + "," + self.offsetY + ") pixels");
        
        /* Scale the Layout Container */
        layout.css("width", self.sWidth + "px");
        layout.css("height", self.sHeight + "px");
        layout.css("position", "absolute");
        layout.css("left", self.offsetX + "px");
        layout.css("top", self.offsetY + "px");

        if (self.zIndex != null)
            layout.css("z-index", self.zIndex);
        
        /* Set the layout background */
        self.bgColour = $(self.layoutNode).filter(":first").attr('bgcolor');
        self.bgImage = $(self.layoutNode).filter(":first").attr('background');
        
        if (!(self.bgImage == "" || self.bgImage == undefined)) {
            /* Extract the image ID from the filename */
            self.bgId = self.bgImage.substring(0, self.bgImage.indexOf('.'));

            var tmpUrl = options.libraryDownloadUrl.replace(":id", self.bgId).replace(":type", "image") + '?preview=1';
            
            preload.addFiles(tmpUrl + "&width=" + self.sWidth + "&height=" + self.sHeight + "&dynamic&proportional=0");
            layout.css("background", "url('" + tmpUrl + "&width=" + self.sWidth + "&height=" + self.sHeight + "&dynamic&proportional=0')");
            layout.css("background-repeat", "no-repeat");
            layout.css("background-size", self.sWidth + "px " + self.sHeight + "px");
            layout.css("background-position", "0px 0px");
        }

        // Set the background color
        layout.css("background-color", self.bgColour);
        
        $(self.layoutNode).find("region").each(function() {
            playLog(4, "debug", "Creating region " + $(this).attr('id'), false);

            self.regionObjects.push(new Region(self, $(this).attr('id'), this, options, preload));
        });

        playLog(4, "debug", "Layout " + self.id + " has " + self.regionObjects.length + " regions");
        self.ready = true;
        preload.addFiles(options.loaderUrl);
        if (layoutPreview){
            // previewing only one layout in the layout preview page
            preload.on('finish', self.run);
        } else {
            // previewing a set of layouts in the campaign preview page
            self.run();
        }
    };

    self.run = function() {
        playLog(4, "debug", "Running Layout ID " + self.id, false);
        if (self.ready) {
            $("#" + self.containerName).css("display", "block");
            $("#splash_" + self.id).css("display", "none");

            for (var i = 0; i < self.regionObjects.length; i++) {
                playLog(4, "debug", "Running region " + self.regionObjects[i].id, false);
                self.regionObjects[i].run();
            }
        }
        else {
            playLog(4, "error", "Attempted to run Layout ID " + self.id + " before it was ready.", false);
        }
    };
    
    self.end = function() {
        /* Ask the layout to gracefully stop running now */
        for (var i = 0; i < self.regionObjects.length; i++) {
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
        playLog(5, "debug", "A region expired. Checking if all regions have expired.", false);
        
        self.allExpired = true;
        
        for (var i = 0; i < self.regionObjects.length; i++) {
            playLog(4, "debug", "Region " + self.regionObjects[i].id + ": " + self.regionObjects[i].complete, false);
            if (! self.regionObjects[i].complete) {
                self.allExpired = false;
            }
        }
        
        if (self.allExpired) {
            playLog(4, "debug", "All regions have expired", false);
            self.end();
        }
    };
    
    self.regionEnded = function() {
        /* One of the regions completed it's exit transition
           Check al the regions have completed exit transitions.
           If they did, bring on the next layout */
           
        playLog(5, "debug", "A region ended. Checking if all regions have ended.", false);
        
        self.allEnded = true;
        
        for (var i = 0; i < self.regionObjects.length; i++) {
            playLog(4, "debug", "Region " + self.regionObjects[i].id + ": " + self.regionObjects[i].ended, false);
            if (! self.regionObjects[i].ended) {
                self.allEnded = false;
            }
        }
        
        if (self.allEnded) {
            playLog(4, "debug", "All regions have ended", false);
            $("#end_" +  self.id).css("display", "block");
            //$("#" + self.containerName).remove();
        }

    };
    
    self.ready = false;
    self.id = id;
    self.regionObjects = [];
    
    playLog(3, "debug", "Loading Layout " + self.id , true);
    $.ajax({
        "type": "GET",
        "url": options.getXlfUrl,
        "success": self.parseXlf
    }); 
}

function Region(parent, id, xml, options, preload) {
    var self = this;
    self.layout = parent;
    self.id = id;
    self.xml = xml;
    self.mediaObjects = [];
    self.currentMedia = -1;
    self.complete = false;
    self.containerName = "R-" + self.id + "-" + nextId();
    self.ending = false;
    self.ended = false;
    self.oneMedia = false;
    self.oldMedia = undefined;
    self.curMedia = undefined;
    
    self.finished = function() {
        self.complete = true;
        self.layout.regionExpired()
    };
    
    self.exitTransition = function() {
        /* TODO: Actually implement region exit transitions */
        $("#" + self.containerName).css("display", "none");
        self.exitTransitionComplete();
    };
    
    self.end = function() {
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
        
        if (oldMedia == newMedia) {
            return;
        }
        
        newMedia.run();
        
        if (oldMedia) {
            $("#" + oldMedia.containerName).css("display", "none");

            if (oldMedia.mediaType == "video") {
                $("#" + oldMedia.containerName + "-vid").get(0).pause();
                $("#" + oldMedia.containerName + "-vid").get(0).currentTime = 0;
            }
        }
        
        $("#" + newMedia.containerName).css("display", "block");
    };
    
    self.nextMedia = function() {
        /* The current media has finished running */
        /* Show the next item */
        
        if (self.ended) {
            return;
        }
        
        if (self.curMedia) {
            playLog(8, "debug", "nextMedia -> Old: " + self.curMedia.id);
            self.oldMedia = self.curMedia;
        }
        else {
            self.oldMedia = undefined;
        }
        
        self.currentMedia = self.currentMedia + 1;

        if (self.currentMedia >= self.mediaObjects.length) {
            self.finished();
            self.currentMedia = 0;
        }
        
        playLog(8, "debug", "nextMedia -> Next up is media " + (self.currentMedia + 1) + " of " + self.mediaObjects.length);
        
        self.curMedia = self.mediaObjects[self.currentMedia];
        
        playLog(8, "debug", "nextMedia -> New: " + self.curMedia.id);
        
        /* Do the transition */
        self.transitionNodes(self.oldMedia, self.curMedia);
    };
    
    self.run = function() {
        self.nextMedia();
    };
    
    self.sWidth = $(xml).attr("width") * self.layout.scaleFactor;
    self.sHeight = $(xml).attr("height") * self.layout.scaleFactor;
    self.offsetX = $(xml).attr("left") * self.layout.scaleFactor;
    self.offsetY = $(xml).attr("top") * self.layout.scaleFactor;
    self.zIndex = $(xml).attr("zindex");

    $("#" + self.layout.containerName).append('<div id="' + self.containerName + '"></div>');
    
    /* Scale the Layout Container */
    $("#" + self.containerName).css("width", self.sWidth + "px");
    $("#" + self.containerName).css("height", self.sHeight + "px");
    $("#" + self.containerName).css("position", "absolute");
    $("#" + self.containerName).css("left", self.offsetX + "px");
    $("#" + self.containerName).css("top", self.offsetY + "px");

    if (self.zIndex != null)
        $("#" + self.containerName).css("z-index", self.zIndex);

    playLog(4, "debug", "Created region " + self.id, false);
    playLog(7, "debug", "Render will be (" + self.sWidth + "x" + self.sHeight + ") pixels");
    playLog(7, "debug", "Offset will be (" + self.offsetX + "," + self.offsetY + ") pixels");
    
    $(self.xml).find("media").each(function() { playLog(5, "debug", "Creating media " + $(this).attr('id'), false);
                                                self.mediaObjects.push(new media(self, $(this).attr('id'), this, options, preload));
                                              });
    
    playLog(4, "debug", "Region " + self.id + " has " + self.mediaObjects.length + " media items");
}

function media(parent, id, xml, options, preload) {
    var self = this;
    self.region = parent;
    self.xml = xml;
    self.id = id;
    self.containerName = "M-" + self.id + "-" + nextId();
    self.iframeName = self.containerName + "-iframe";
    self.mediaType = $(self.xml).attr('type');
    self.render = $(self.xml).attr('render');
    if (self.render == undefined)
        self.render = "module";
    
    self.run = function() {
        playLog(5, "debug", "Running media " + self.id + " for " + self.duration + " seconds");
               
        if (self.mediaType == "video") {
            $("#" + self.containerName + "-vid").get(0).play();
        }
        
        if (self.duration == 0) {
            if (self.mediaType == "video") {
                $("#" + self.containerName + "-vid").bind('ended', self.region.nextMedia);
                $("#" + self.containerName + "-vid").bind('error', self.region.nextMedia);
                $("#" + self.containerName + "-vid").bind('click', self.region.nextMedia);
            }
            else {
                self.duration = 3;
                setTimeout(self.region.nextMedia, self.duration * 1000);
            }
        }
        else {
            setTimeout(self.region.nextMedia, self.duration * 1000);
        }
    };
    
    self.divWidth = self.region.sWidth;
    self.divHeight = self.region.sHeight;
    
    /* Build Media Options */
    self.duration = $(self.xml).attr('duration');
    self.lkid = $(self.xml).attr('lkid');
    self.options = [];
    
    $(self.xml).find('options').children().each(function() {
        playLog(9, "debug", "Option " + this.nodeName.toLowerCase() + " -> " + $(this).text(), false);
        self.options[this.nodeName.toLowerCase()] = $(this).text();
    });
    
    $("#" + self.region.containerName).append('<div id="' + self.containerName + '"></div>');

    /* Scale the Content Container */
    var media = $("#" + self.containerName);
    media.css("display", "none");
    media.css("width", self.divWidth + "px");
    media.css("height", self.divHeight + "px");
    media.css("position", "absolute");
    media.css("background-size", "contain");
    media.css("background-repeat", "no-repeat");
    media.css("background-position", "center");
    /* media.css("left", self.offsetX + "px");
    media.css("top", self.offsetY + "px"); */

    var tmpUrl = options.getResourceUrl.replace(":regionId", self.region.id).replace(":id", self.id) + '?preview=1';
    
    if (self.render == "html" || self.mediaType == "ticker") {
        media.append('<iframe scrolling="no" id="' + self.iframeName + '" src="' + tmpUrl + '&width=' + self.divWidth + '&height=' + self.divHeight + '" width="' + self.divWidth + 'px" height="' + self.divHeight + 'px" style="border:0;"></iframe>');
        /* Check if the ticker duration is based on the number of items in the feed */
        if (self.options['durationisperitem'] == '1') {
            var regex =  new RegExp("<!-- NUMITEMS=(.*?) -->"); 
            jQuery.ajax({
                url: tmpUrl + '&width=' + self.divWidth + '&height=' + self.divHeight,
                success: function(html) {
                  var res = regex.exec(html);
                  if (res != null) {
                    /* The ticker is duration per item, so multiply the duration
                       by the number of items from the feed */
                    self.duration = parseInt(self.duration) * parseInt(res[1]);
                  }
                },
                async:false
            });
        }
    }
    else if (self.mediaType == "image") {
        preload.addFiles(tmpUrl);
        media.css("background-image", "url('" + tmpUrl + "')");
        if (self.options['scaletype'] == 'stretch')
            media.css("background-size", "cover");
        else {
            // Center scale type, do we have align or valign?
            var align = (self.options['align'] == "") ? "center" : self.options['align'];
            var valign = (self.options['valign'] == "" || self.options['valign'] == "middle") ? "center" : self.options['valign'];
            media.css("background-position", align + " " + valign);
        }
    }
    else if (self.mediaType == "text" || self.mediaType == "datasetview" || self.mediaType == "webpage" || self.mediaType == "embedded") {
        media.append('<iframe scrolling="no" id="' + self.iframeName + '" src="' + tmpUrl + '&width=' + self.divWidth + '&height=' + self.divHeight + '" width="' + self.divWidth + 'px" height="' + self.divHeight + 'px" style="border:0;"></iframe>');
    }
    else if (self.mediaType == "video") {
        preload.addFiles(tmpUrl);
        media.append('<video id="' + self.containerName + '-vid" preload="auto" ' + ((self.options["mute"] == 1) ? 'muted' : '') + '><source src="' + tmpUrl + '">Unsupported Video</video>');
    }
    else if (self.mediaType == "flash") {
        var embedCode = '<OBJECT classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0" WIDTH="100%" HEIGHT="100%" id="Yourfilename" ALIGN="">';
        embedCode = embedCode + '<PARAM NAME=movie VALUE="' + tmpUrl + '"> <PARAM NAME=quality VALUE=high> <param name="wmode" value="transparent"> <EMBED src="' + tmpUrl + '" quality="high" wmode="transparent" WIDTH="100%" HEIGHT="100%" NAME="Yourfilename" ALIGN="" TYPE="application/x-shockwave-flash" PLUGINSPAGE="http://www.macromedia.com/go/getflashplayer"></EMBED> </OBJECT>';
        preload.addFiles(tmpUrl);
        media.append(embedCode);
    }
    else {
        media.css("outline", "red solid thin");
    }
    
    playLog(5, "debug", "Created media " + self.id)
}

