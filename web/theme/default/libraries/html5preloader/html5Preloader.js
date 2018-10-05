var html5Preloader = (function () {

var	XHR = typeof XMLHttpRequest === 'undefined' ? function () { // IE FIX
		try {
			return new ActiveXObject("Msxml2.XMLHTTP.6.0");
		} catch (err1) {}
		try {
			return new ActiveXObject("Msxml2.XMLHTTP.3.0");
		} catch (err2) {}

		return null;
	} : XMLHttpRequest,
	AudioElement = typeof Audio !== 'undefined' ? // IE FIX
		function(){
			return new Audio();
		} :
		function(){
			return document.createElement('audio');
		},
	VideoElement = typeof Video !== 'undefined' ? // IE FIX
		function () {
			return new Video();
		} :
		function () {
			return document.createElement('video');
		},
	ImageElement = function () {
		return new Image();
	},
	codecs = { // Chart from jPlayer
		oga: { // OGG
			codec: 'audio/ogg; codecs="vorbis"',
			media: 'audio'
		},
		wav: { // PCM
			codec: 'audio/wav; codecs="1"',
			media: 'audio'
		},
		webma: { // WEBM
			codec: 'audio/webm; codecs="vorbis"',
			media: 'audio'
		},
		mp3: {
			codec: 'audio/mpeg; codecs="mp3"',
			media: 'audio'
		},
		m4a: { // AAC / MP4
			codec: 'audio/mp4; codecs="mp4a.40.2"',
			media: 'audio'
		},
		ogv: { // OGG
			codec: 'video/ogg; codecs="theora, vorbis"',
			media: 'video'
		},
		webmv: { // WEBM
			codec: 'video/webm; codecs="vorbis, vp8"',
			media: 'video'
		},
		m4v: { // H.264 / MP4
			codec: 'video/mp4; codecs="avc1.42E01E, mp4a.40.2"',
			media: 'video'
		}
	},
	support = {
		imageTypes: ['jpg', 'png', 'jpeg', 'tiff', 'gif']
	},
	ID_PREFIX = 'FILE@';
/* :) may fail sometimes, but these are the most common cases */
codecs.ogg = codecs.oga;
codecs.mp4 = codecs.m4v;
codecs.webm = codecs.webmv;

function isIn (needle, haystack) {
	for (var i=0; i<haystack.length; i++) {
		if (haystack[i] === needle) {
			return true;
		}
	}

	return false;
}

function map (arr, callback) {
	if (arr.map) {
		return arr.map(callback);
	}

	var	r = [],
		i;
	for (i=0; i<arr.length; i++) {
		r.push(callback(arr[i]));
	}

	return r;
}

function bind (func, self) {
	return func.bind ? func.bind(self) : function () {
		return func.apply(self, arguments);
	};
}

function delay (callback) {
	var args = [].slice.call(arguments, 1);
	setTimeout(function () {
		callback.apply(this, args);
	}, 0);
}

function EventEmitter () {
	var k;
	for (k in EventEmitter.prototype) {
		if (EventEmitter.prototype.hasOwnProperty(k)) {
			this[k] = EventEmitter.prototype[k];
		}
	}
	this._listeners = {};
};

EventEmitter.prototype = {
	_listeners: null,

	emit: function (name, args) {
		args = args || [];
		if (this._listeners[name]) {
			for (var i=0; i<this._listeners[name].length; i++) {
				this._listeners[name][i].apply(this, args);
			}
		}
		return this;
	},

	on: function (name, listener) {
		this._listeners[name] = this._listeners[name] || [];
		this._listeners[name].push(listener);
		return this;
	},

	off: function (name, listener) {
		if (this._listeners[name]) {
			if (!listener) {
				delete this._listeners[name];
				return this;
			}
			for (var i=0; i<this._listeners[name].length; i++) {
				if (this._listeners[name][i] === listener) {
					this._listeners[name].splice(i--, 1);
				}
			}
			this._listeners[name].length || delete this._listeners[name];
		}
		return this;
	},

	once: function (name, listener) {
		function ev () {
			this.off(ev);
			return listener.apply(this, arguments);
		}

		return this.on(name, ev);
	}
};

function loadFile (file, callback, timeout) {
	if (!(this instanceof loadFile)) {
		return new loadFile(file, callback, timeout);
	}

	var	self		= this,
		alternates	= [],
		a, b, c, t;

	if (typeof file === 'string') {
		a = file.split('*:');
		b = a[ a[1] ? 1 : 0 ].split('||');
		self.id = a[1] ? a[0] : b[0];
		self.alternates = alternates;

		for (a=0; a<b.length; a++) {
			c = b[a].split('.');
			c = c[c.length - 1].toLowerCase();

			t = codecs[c] ? codecs[c].media : isIn(c, support.imageTypes) ? 'image' : 'document';

			if (codecs[c] && !codecs[c].supported) {
				continue;
			}

			alternates.push({
				type: t,
				path: b[a]
			});
		}

		alternates.length || alternates.push({
			type: t,
			path: b[a-1]
		});
	} else {
		delay(callback, TypeError('Invalid path'), self);
		return;
	}

	function loadNext() {
		var file = alternates.shift(),
			_timeoutTimer = null;

		if (!file) {
			delay(callback, {e: Error('No viable alternatives')}, null);
			return;
		}

		if (typeof timeout === 'number') {
			_timeoutTimer = setTimeout(function() {
				delay(callback, {e: Error('Load event not fired within ' + timeout + 'ms')}, self);
			}, timeout);
		}

		new loadFile[file.type](
				file.path,
				function (e, f) {

					_timeoutTimer && clearTimeout(_timeoutTimer);

					self.dom = f && f.dom;

					if (e && self.alternates.length) {
						return loadNext();
					}

					callback(e, self);
				});
	}

	loadNext();
}

function MediaFile (construct) {
	return function (filename, callback) {
		var	self = this,
			file = construct();

		function onready () {
			file.onload = file.onerror = null;
			file.removeEventListener && file.removeEventListener('canplaythrough', onready, true);

			callback(null, self);
		}

		file.addEventListener && file.addEventListener('canplaythrough', onready, true);
		file.onload = onready;
		file.onerror = function (e) {
			callback(e, self);
		};

		self.dom = file;
		file.src = filename;

		file.load && file.load();
	};
}

loadFile.audio = MediaFile(AudioElement);
loadFile.video = MediaFile(VideoElement);
loadFile.image = MediaFile(ImageElement);

loadFile.document = function (file, callback) {
	var	self		= this,
		parsedUrl	= /(\[(!)?(.+)?\])?$/.exec(file),
		mimeType	= parsedUrl[3],
		xhr		= self.dom = new XHR();

	if (!xhr) {
		delay(callback, Error('No XHR!'), self);
		return;
	}

	file		= file.substr(0, file.length - parsedUrl[0].length);
	file		+= parsedUrl[2] ? (file.indexOf('?') === -1 ? '?' : '&') + 'fobarz=' + (+new Date) : '';

	mimeType && xhr.overrideMimeType(mimeType === '@' ? 'text/plain; charset=x-user-defined' : mimeType);

	xhr.onreadystatechange = function () {
		if (xhr.readyState !== 4) return;

		try {
			self.dom = xhr.responseXML && xhr.responseXML.documentElement ?
				xhr.responseXML :
				String(xhr.responseText || '') ;

			xhr.status === 200 ?
				callback(null, self) :
				callback({e: Error('Request failed: ' + xhr.status)}, self) ;
		} catch (e) {
			callback({e: e}, self);
		}
	};

	xhr.onerror = function (e) {
		callback(e, self);
	};

	xhr.open('GET', file, true);
	xhr.send();
};

(function () {
	var 	dummyAudio = AudioElement(),
		dummyVideo = VideoElement(),
		i;

	support.audio = !!dummyAudio.canPlayType;
	support.video = !!dummyVideo.canPlayType;

	support.audioTypes = [];
	support.videoTypes = [];

	for (i in codecs) {
		if (codecs.hasOwnProperty(i)) {
			if (codecs[i].media === 'video') {
				(codecs[i].supported = support.video &&
					dummyVideo.canPlayType(codecs[i].codec)) &&
					support.videoTypes.push(i);
			} else if (codecs[i].media === 'audio') {
				(codecs[i].supported = support.audio &&
					dummyAudio.canPlayType(codecs[i].codec)) &&
					support.audioTypes.push(i);
			}
		}
	}
}());

if (!support.audio) {
	loadFile.audio = function (a, callback) {
		delay(callback, Error('<AUDIO> not supported.'), a);
	};
}
if (!support.video) {
	loadFile.video = function (a, callback) {
		delay(callback, Error('<VIDEO> not supported.'), a);
	};
}

function html5Preloader () {
	var	self = this,
		args = arguments;

	if (!(self instanceof html5Preloader)) {
		self = new html5Preloader();
		args.length && self.loadFiles.apply(self, args);
		return self;
	}

	self.files = [];

	html5Preloader.EventEmitter.call(self);

	self.loadCallback = bind(self.loadCallback, self);

	args.length && self.loadFiles.apply(self, args);
}

html5Preloader.prototype = {
	active: false,
	files: null,
	filesLoading: 0,
	filesLoaded: 0,
	filesLoadedMap: {},
	timeout: null,

	loadCallback: function (e, f) {

		if (!this.filesLoadedMap[f.id]) {
			this.filesLoaded++;
			this.filesLoadedMap[f.id] = f;
		}

		this.emit(e ? 'error' : 'fileloaded', e ? [e, f] : [f]);

		if (this.filesLoading - this.filesLoaded === 0) {
			this.active = false;
			this.emit('finish');
			this.filesLoading = 0;
			this.filesLoaded = 0;
		}
	},

	getFile: function (id) {
		return	typeof id === 'undefined' ? map(this.files, function (f) {
				return f.dom;
			}) :
			typeof id === 'number' ? this.files[id].dom :
			typeof id === 'string' ? this.files[ID_PREFIX + id].dom :
			null;
	},

	removeFile: function (id) {
		var f, i;
		switch (typeof id) {
		case 'undefined':
			this.files = [];
			break;
		case 'number':
			f = this.files[id];
			this.files[ID_PREFIX + f.id] && delete this.files[ID_PREFIX + f.id];
			this.files.splice(id, 1);
			break;
		case 'string':
			f = this.files[ID_PREFIX + id];
			f && delete this.files[ID_PREFIX + id];

			for (i=0; i<this.files.length; i++) {
				this.files[i] === f && this.files.splice(i--, 1);
			}
		}
	},

	loadFiles: function () {
		var	files	= [].slice.call(arguments),
			i, f;

		for (i=0; i<files.length; i++) {
			f = html5Preloader.loadFile(files[i], this.loadCallback, this.timeout);
			this.files.push(f);
			this.files[ID_PREFIX + f.id] = f;
			this.filesLoading++;
		}

		this.active = this.active || !!this.filesLoading;
	},

	addFiles: function (list) {
		return this.loadFiles.apply(this, list instanceof Array ? list : arguments);
	},

	getProgress: function () {
		return this.filesLoading ? this.filesLoaded / this.filesLoading : 1.0;
	}
};

html5Preloader.support = support;
html5Preloader.loadFile = loadFile;
html5Preloader.EventEmitter = EventEmitter;

return html5Preloader;

}());
