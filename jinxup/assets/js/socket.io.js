(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){

	/**
	 * Module dependencies.
	 */

	var Emitter = require('events').EventEmitter;
	var parser = require('socket.io-parser');
	var url = require('url');
	var debug = require('debug')('socket.io:socket');
	var hasBin = require('has-binary-data');

	/**
	 * Module exports.
	 */

	module.exports = exports = Socket;

	/**
	 * Blacklisted events.
	 *
	 * @api public
	 */

	exports.events = [
		'error',
		'connect',
		'disconnect',
		'newListener',
		'removeListener'
	];

	/**
	 * Flags.
	 *
	 * @api private
	 */

	var flags = [
		'json',
		'volatile',
		'broadcast'
	];

	/**
	 * `EventEmitter#emit` reference.
	 */

	var emit = Emitter.prototype.emit;

	/**
	 * Interface to a `Client` for a given `Namespace`.
	 *
	 * @param {Namespace} nsp
	 * @param {Client} client
	 * @api public
	 */

	function Socket(nsp, client){
		this.nsp = nsp;
		this.server = nsp.server;
		this.adapter = this.nsp.adapter;
		this.id = client.id;
		this.request = client.request;
		this.client = client;
		this.conn = client.conn;
		this.rooms = [];
		this.acks = {};
		this.connected = true;
		this.disconnected = false;
		this.handshake = this.buildHandshake();
	}

	/**
	 * Inherits from `EventEmitter`.
	 */

	Socket.prototype.__proto__ = Emitter.prototype;

	/**
	 * Apply flags from `Socket`.
	 */

	flags.forEach(function(flag){
		Socket.prototype.__defineGetter__(flag, function(){
			this.flags = this.flags || {};
			this.flags[flag] = true;
			return this;
		});
	});

	/**
	 * `request` engine.io shorcut.
	 *
	 * @api public
	 */

	Socket.prototype.__defineGetter__('request', function(){
		return this.conn.request;
	});

	/**
	 * Builds the `handshake` BC object
	 *
	 * @api private
	 */

	Socket.prototype.buildHandshake = function(){
		return {
			headers: this.request.headers,
			time: (new Date) + '',
			address: this.conn.remoteAddress,
			xdomain: !!this.request.headers.origin,
			secure: !!this.request.connection.encrypted,
			issued: +(new Date),
			url: this.request.url,
			query: url.parse(this.request.url, true).query || {}
		};
	};

	/**
	 * Emits to this client.
	 *
	 * @return {Socket} self
	 * @api public
	 */

	Socket.prototype.emit = function(ev){
		if (~exports.events.indexOf(ev)) {
			emit.apply(this, arguments);
		} else {
			var args = Array.prototype.slice.call(arguments);
			var packet = {};
			packet.type = hasBin(args) ? parser.BINARY_EVENT : parser.EVENT;
			packet.data = args;

			// access last argument to see if it's an ACK callback
			if ('function' == typeof args[args.length - 1]) {
				if (this._rooms || (this.flags && this.flags.broadcast)) {
					throw new Error('Callbacks are not supported when broadcasting');
				}

				debug('emitting packet with ack id %d', this.nsp.ids);
				this.acks[this.nsp.ids] = args.pop();
				packet.id = this.nsp.ids++;
			}

			if (this._rooms || (this.flags && this.flags.broadcast)) {
				this.adapter.broadcast(packet, {
					except: [this.id],
					rooms: this._rooms,
					flags: this.flags
				});
			} else {
				// dispatch packet
				this.packet(packet);
			}

			// reset flags
			delete this._rooms;
			delete this.flags;
		}
		return this;
	};

	/**
	 * Targets a room when broadcasting.
	 *
	 * @param {String} name
	 * @return {Socket} self
	 * @api public
	 */

	Socket.prototype.to =
		Socket.prototype.in = function(name){
			this._rooms = this._rooms || [];
			if (!~this._rooms.indexOf(name)) this._rooms.push(name);
			return this;
		};

	/**
	 * Sends a `message` event.
	 *
	 * @return {Socket} self
	 * @api public
	 */

	Socket.prototype.send =
		Socket.prototype.write = function(){
			var args = Array.prototype.slice.call(arguments);
			args.unshift('message');
			this.emit.apply(this, args);
			return this;
		};

	/**
	 * Writes a packet.
	 *
	 * @param {Object} packet object
	 * @api private
	 */

	Socket.prototype.packet = function(packet, preEncoded){
		packet.nsp = this.nsp.name;
		var volatile = this.flags && this.flags.volatile;
		this.client.packet(packet, preEncoded, volatile);
	};

	/**
	 * Joins a room.
	 *
	 * @param {String} room
	 * @param {Function} optional, callback
	 * @return {Socket} self
	 * @api private
	 */

	Socket.prototype.join = function(room, fn){
		debug('joining room %s', room);
		var self = this;
		if (~this.rooms.indexOf(room)) return this;
		this.adapter.add(this.id, room, function(err){
			if (err) return fn && fn(err);
			debug('joined room %s', room);
			self.rooms.push(room);
			fn && fn(null);
		});
		return this;
	};

	/**
	 * Leaves a room.
	 *
	 * @param {String} room
	 * @param {Function} optional, callback
	 * @return {Socket} self
	 * @api private
	 */

	Socket.prototype.leave = function(room, fn){
		debug('leave room %s', room);
		var self = this;
		this.adapter.del(this.id, room, function(err){
			if (err) return fn && fn(err);
			debug('left room %s', room);
			var idx = self.rooms.indexOf(room);
			if (idx >= 0) {
				self.rooms.splice(idx, 1);
			}
			fn && fn(null);
		});
		return this;
	};

	/**
	 * Leave all rooms.
	 *
	 * @api private
	 */

	Socket.prototype.leaveAll = function(){
		this.adapter.delAll(this.id);
		this.rooms = [];
	};

	/**
	 * Called by `Namespace` upon succesful
	 * middleware execution (ie: authorization).
	 *
	 * @api private
	 */

	Socket.prototype.onconnect = function(){
		debug('socket connected - writing packet');
		this.join(this.id);
		this.packet({ type: parser.CONNECT });
		this.nsp.connected[this.id] = this;
	};

	/**
	 * Called with each packet. Called by `Client`.
	 *
	 * @param {Object} packet
	 * @api private
	 */

	Socket.prototype.onpacket = function(packet){
		debug('got packet %j', packet);
		switch (packet.type) {
			case parser.EVENT:
				this.onevent(packet);
				break;

			case parser.BINARY_EVENT:
				this.onevent(packet);
				break;

			case parser.ACK:
				this.onack(packet);
				break;

			case parser.BINARY_ACK:
				this.onack(packet);
				break;

			case parser.DISCONNECT:
				this.ondisconnect();
				break;

			case parser.ERROR:
				this.emit('error', packet.data);
		}
	};

	/**
	 * Called upon event packet.
	 *
	 * @param {Object} packet object
	 * @api private
	 */

	Socket.prototype.onevent = function(packet){
		var args = packet.data || [];
		debug('emitting event %j', args);

		if (null != packet.id) {
			debug('attaching ack callback to event');
			args.push(this.ack(packet.id));
		}

		emit.apply(this, args);
	};

	/**
	 * Produces an ack callback to emit with an event.
	 *
	 * @param {Number} packet id
	 * @api private
	 */

	Socket.prototype.ack = function(id){
		var self = this;
		var sent = false;
		return function(){
			// prevent double callbacks
			if (sent) return;
			var args = Array.prototype.slice.call(arguments);
			debug('sending ack %j', args);

			var type = hasBin(args) ? parser.BINARY_ACK : parser.ACK;
			self.packet({
				id: id,
				type: type,
				data: args
			});
		};
	};

	/**
	 * Called upon ack packet.
	 *
	 * @api private
	 */

	Socket.prototype.onack = function(packet){
		var ack = this.acks[packet.id];
		if ('function' == typeof ack) {
			debug('calling ack %s with %j', packet.id, packet.data);
			ack.apply(this, packet.data);
			delete this.acks[packet.id];
		} else {
			debug('bad ack %s', packet.id);
		}
	};

	/**
	 * Called upon client disconnect packet.
	 *
	 * @api private
	 */

	Socket.prototype.ondisconnect = function(){
		debug('got disconnect packet');
		this.onclose('client namespace disconnect');
	};

	/**
	 * Handles a client error.
	 *
	 * @api private
	 */

	Socket.prototype.onerror = function(err){
		if (this.listeners('error').length) {
			this.emit('error', err);
		} else {
			console.error('Missing error handler on `socket`.');
			console.error(err.stack);
		}
	};

	/**
	 * Called upon closing. Called by `Client`.
	 *
	 * @param {String} reason
	 * @param {Error} optional error object
	 * @api private
	 */

	Socket.prototype.onclose = function(reason){
		if (!this.connected) return this;
		debug('closing socket - reason %s', reason);
		this.leaveAll();
		this.nsp.remove(this);
		this.client.remove(this);
		this.connected = false;
		this.disconnected = true;
		delete this.nsp.connected[this.id];
		this.emit('disconnect', reason);
	};

	/**
	 * Produces an `error` packet.
	 *
	 * @param {Object} error object
	 * @api private
	 */

	Socket.prototype.error = function(err){
		this.packet({ type: parser.ERROR, data: err });
	};

	/**
	 * Disconnects this client.
	 *
	 * @param {Boolean} if `true`, closes the underlying connection
	 * @return {Socket} self
	 * @api public
	 */

	Socket.prototype.disconnect = function(close){
		if (!this.connected) return this;
		if (close) {
			this.client.disconnect();
		} else {
			this.packet({ type: parser.DISCONNECT });
			this.onclose('server namespace disconnect');
		}
		return this;
	};

},{"debug":2,"events":18,"has-binary-data":5,"socket.io-parser":8,"url":23}],2:[function(require,module,exports){

	/**
	 * This is the web browser implementation of `debug()`.
	 *
	 * Expose `debug()` as the module.
	 */

	exports = module.exports = require('./debug');
	exports.log = log;
	exports.formatArgs = formatArgs;
	exports.save = save;
	exports.load = load;
	exports.useColors = useColors;

	/**
	 * Colors.
	 */

	exports.colors = [
		'lightseagreen',
		'forestgreen',
		'goldenrod',
		'dodgerblue',
		'darkorchid',
		'crimson'
	];

	/**
	 * Currently only WebKit-based Web Inspectors, Firefox >= v31,
	 * and the Firebug extension (any Firefox version) are known
	 * to support "%c" CSS customizations.
	 *
	 * TODO: add a `localStorage` variable to explicitly enable/disable colors
	 */

	function useColors() {
		// is webkit? http://stackoverflow.com/a/16459606/376773
		return ('WebkitAppearance' in document.documentElement.style) ||
				// is firebug? http://stackoverflow.com/a/398120/376773
			(window.console && (console.firebug || (console.exception && console.table))) ||
				// is firefox >= v31?
				// https://developer.mozilla.org/en-US/docs/Tools/Web_Console#Styling_messages
			(navigator.userAgent.toLowerCase().match(/firefox\/(\d+)/) && parseInt(RegExp.$1, 10) >= 31);
	}

	/**
	 * Map %j to `JSON.stringify()`, since no Web Inspectors do that by default.
	 */

	exports.formatters.j = function(v) {
		return JSON.stringify(v);
	};


	/**
	 * Colorize log arguments if enabled.
	 *
	 * @api public
	 */

	function formatArgs() {
		var args = arguments;
		var useColors = this.useColors;

		args[0] = (useColors ? '%c' : '')
			+ this.namespace
			+ (useColors ? ' %c' : ' ')
			+ args[0]
			+ (useColors ? '%c ' : ' ')
			+ '+' + exports.humanize(this.diff);

		if (!useColors) return args;

		var c = 'color: ' + this.color;
		args = [args[0], c, 'color: inherit'].concat(Array.prototype.slice.call(args, 1));

		// the final "%c" is somewhat tricky, because there could be other
		// arguments passed either before or after the %c, so we need to
		// figure out the correct index to insert the CSS into
		var index = 0;
		var lastC = 0;
		args[0].replace(/%[a-z%]/g, function(match) {
			if ('%%' === match) return;
			index++;
			if ('%c' === match) {
				// we only are interested in the *last* %c
				// (the user may have provided their own)
				lastC = index;
			}
		});

		args.splice(lastC, 0, c);
		return args;
	}

	/**
	 * Invokes `console.log()` when available.
	 * No-op when `console.log` is not a "function".
	 *
	 * @api public
	 */

	function log() {
		// This hackery is required for IE8,
		// where the `console.log` function doesn't have 'apply'
		return 'object' == typeof console
			&& 'function' == typeof console.log
			&& Function.prototype.apply.call(console.log, console, arguments);
	}

	/**
	 * Save `namespaces`.
	 *
	 * @param {String} namespaces
	 * @api private
	 */

	function save(namespaces) {
		try {
			if (null == namespaces) {
				localStorage.removeItem('debug');
			} else {
				localStorage.debug = namespaces;
			}
		} catch(e) {}
	}

	/**
	 * Load `namespaces`.
	 *
	 * @return {String} returns the previously persisted debug modes
	 * @api private
	 */

	function load() {
		var r;
		try {
			r = localStorage.debug;
		} catch(e) {}
		return r;
	}

	/**
	 * Enable namespaces listed in `localStorage.debug` initially.
	 */

	exports.enable(load());

},{"./debug":3}],3:[function(require,module,exports){

	/**
	 * This is the common logic for both the Node.js and web browser
	 * implementations of `debug()`.
	 *
	 * Expose `debug()` as the module.
	 */

	exports = module.exports = debug;
	exports.coerce = coerce;
	exports.disable = disable;
	exports.enable = enable;
	exports.enabled = enabled;
	exports.humanize = require('ms');

	/**
	 * The currently active debug mode names, and names to skip.
	 */

	exports.names = [];
	exports.skips = [];

	/**
	 * Map of special "%n" handling functions, for the debug "format" argument.
	 *
	 * Valid key names are a single, lowercased letter, i.e. "n".
	 */

	exports.formatters = {};

	/**
	 * Previously assigned color.
	 */

	var prevColor = 0;

	/**
	 * Previous log timestamp.
	 */

	var prevTime;

	/**
	 * Select a color.
	 *
	 * @return {Number}
	 * @api private
	 */

	function selectColor() {
		return exports.colors[prevColor++ % exports.colors.length];
	}

	/**
	 * Create a debugger with the given `namespace`.
	 *
	 * @param {String} namespace
	 * @return {Function}
	 * @api public
	 */

	function debug(namespace) {

		// define the `disabled` version
		function disabled() {
		}
		disabled.enabled = false;

		// define the `enabled` version
		function enabled() {

			var self = enabled;

			// set `diff` timestamp
			var curr = +new Date();
			var ms = curr - (prevTime || curr);
			self.diff = ms;
			self.prev = prevTime;
			self.curr = curr;
			prevTime = curr;

			// add the `color` if not set
			if (null == self.useColors) self.useColors = exports.useColors();
			if (null == self.color && self.useColors) self.color = selectColor();

			var args = Array.prototype.slice.call(arguments);

			args[0] = exports.coerce(args[0]);

			if ('string' !== typeof args[0]) {
				// anything else let's inspect with %o
				args = ['%o'].concat(args);
			}

			// apply any `formatters` transformations
			var index = 0;
			args[0] = args[0].replace(/%([a-z%])/g, function(match, format) {
				// if we encounter an escaped % then don't increase the array index
				if (match === '%%') return match;
				index++;
				var formatter = exports.formatters[format];
				if ('function' === typeof formatter) {
					var val = args[index];
					match = formatter.call(self, val);

					// now we need to remove `args[index]` since it's inlined in the `format`
					args.splice(index, 1);
					index--;
				}
				return match;
			});

			if ('function' === typeof exports.formatArgs) {
				args = exports.formatArgs.apply(self, args);
			}
			var logFn = enabled.log || exports.log || console.log.bind(console);
			logFn.apply(self, args);
		}
		enabled.enabled = true;

		var fn = exports.enabled(namespace) ? enabled : disabled;

		fn.namespace = namespace;

		return fn;
	}

	/**
	 * Enables a debug mode by namespaces. This can include modes
	 * separated by a colon and wildcards.
	 *
	 * @param {String} namespaces
	 * @api public
	 */

	function enable(namespaces) {
		exports.save(namespaces);

		var split = (namespaces || '').split(/[\s,]+/);
		var len = split.length;

		for (var i = 0; i < len; i++) {
			if (!split[i]) continue; // ignore empty strings
			namespaces = split[i].replace(/\*/g, '.*?');
			if (namespaces[0] === '-') {
				exports.skips.push(new RegExp('^' + namespaces.substr(1) + '$'));
			} else {
				exports.names.push(new RegExp('^' + namespaces + '$'));
			}
		}
	}

	/**
	 * Disable debug output.
	 *
	 * @api public
	 */

	function disable() {
		exports.enable('');
	}

	/**
	 * Returns true if the given mode name is enabled, false otherwise.
	 *
	 * @param {String} name
	 * @return {Boolean}
	 * @api public
	 */

	function enabled(name) {
		var i, len;
		for (i = 0, len = exports.skips.length; i < len; i++) {
			if (exports.skips[i].test(name)) {
				return false;
			}
		}
		for (i = 0, len = exports.names.length; i < len; i++) {
			if (exports.names[i].test(name)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Coerce `val`.
	 *
	 * @param {Mixed} val
	 * @return {Mixed}
	 * @api private
	 */

	function coerce(val) {
		if (val instanceof Error) return val.stack || val.message;
		return val;
	}

},{"ms":4}],4:[function(require,module,exports){
	/**
	 * Helpers.
	 */

	var s = 1000;
	var m = s * 60;
	var h = m * 60;
	var d = h * 24;
	var y = d * 365.25;

	/**
	 * Parse or format the given `val`.
	 *
	 * Options:
	 *
	 *  - `long` verbose formatting [false]
	 *
	 * @param {String|Number} val
	 * @param {Object} options
	 * @return {String|Number}
	 * @api public
	 */

	module.exports = function(val, options){
		options = options || {};
		if ('string' == typeof val) return parse(val);
		return options.long
			? long(val)
			: short(val);
	};

	/**
	 * Parse the given `str` and return milliseconds.
	 *
	 * @param {String} str
	 * @return {Number}
	 * @api private
	 */

	function parse(str) {
		var match = /^((?:\d+)?\.?\d+) *(ms|seconds?|s|minutes?|m|hours?|h|days?|d|years?|y)?$/i.exec(str);
		if (!match) return;
		var n = parseFloat(match[1]);
		var type = (match[2] || 'ms').toLowerCase();
		switch (type) {
			case 'years':
			case 'year':
			case 'y':
				return n * y;
			case 'days':
			case 'day':
			case 'd':
				return n * d;
			case 'hours':
			case 'hour':
			case 'h':
				return n * h;
			case 'minutes':
			case 'minute':
			case 'm':
				return n * m;
			case 'seconds':
			case 'second':
			case 's':
				return n * s;
			case 'ms':
				return n;
		}
	}

	/**
	 * Short format for `ms`.
	 *
	 * @param {Number} ms
	 * @return {String}
	 * @api private
	 */

	function short(ms) {
		if (ms >= d) return Math.round(ms / d) + 'd';
		if (ms >= h) return Math.round(ms / h) + 'h';
		if (ms >= m) return Math.round(ms / m) + 'm';
		if (ms >= s) return Math.round(ms / s) + 's';
		return ms + 'ms';
	}

	/**
	 * Long format for `ms`.
	 *
	 * @param {Number} ms
	 * @return {String}
	 * @api private
	 */

	function long(ms) {
		return plural(ms, d, 'day')
			|| plural(ms, h, 'hour')
			|| plural(ms, m, 'minute')
			|| plural(ms, s, 'second')
			|| ms + ' ms';
	}

	/**
	 * Pluralization helper.
	 */

	function plural(ms, n, name) {
		if (ms < n) return;
		if (ms < n * 1.5) return Math.floor(ms / n) + ' ' + name;
		return Math.ceil(ms / n) + ' ' + name + 's';
	}

},{}],5:[function(require,module,exports){
	(function (global,Buffer){
		/*
		 * Module requirements.
		 */

		var isArray = require('isarray');

		/**
		 * Module exports.
		 */

		module.exports = hasBinary;

		/**
		 * Checks for binary data.
		 *
		 * Right now only Buffer and ArrayBuffer are supported..
		 *
		 * @param {Object} anything
		 * @api public
		 */

		function hasBinary(data) {

			function _hasBinary(obj) {
				if (!obj) return false;

				if ( (global.Buffer && Buffer.isBuffer(obj)) ||
					(global.ArrayBuffer && obj instanceof ArrayBuffer) ||
					(global.Blob && obj instanceof Blob) ||
					(global.File && obj instanceof File)
				) {
					return true;
				}

				if (isArray(obj)) {
					for (var i = 0; i < obj.length; i++) {
						if (_hasBinary(obj[i])) {
							return true;
						}
					}
				} else if (obj && 'object' == typeof obj) {
					if (obj.toJSON) {
						obj = obj.toJSON();
					}

					for (var key in obj) {
						if (obj.hasOwnProperty(key) && _hasBinary(obj[key])) {
							return true;
						}
					}
				}

				return false;
			}

			return _hasBinary(data);
		}

	}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {},require("buffer").Buffer)
},{"buffer":14,"isarray":6}],6:[function(require,module,exports){
	module.exports = Array.isArray || function (arr) {
			return Object.prototype.toString.call(arr) == '[object Array]';
		};

},{}],7:[function(require,module,exports){
	(function (global){
		/*global Blob,File*/

		/**
		 * Module requirements
		 */

		var isArray = require('isarray');
		var isBuf = require('./is-buffer');

		/**
		 * Replaces every Buffer | ArrayBuffer in packet with a numbered placeholder.
		 * Anything with blobs or files should be fed through removeBlobs before coming
		 * here.
		 *
		 * @param {Object} packet - socket.io event packet
		 * @return {Object} with deconstructed packet and list of buffers
		 * @api public
		 */

		exports.deconstructPacket = function(packet){
			var buffers = [];
			var packetData = packet.data;

			function _deconstructPacket(data) {
				if (!data) return data;

				if (isBuf(data)) {
					var placeholder = { _placeholder: true, num: buffers.length };
					buffers.push(data);
					return placeholder;
				} else if (isArray(data)) {
					var newData = new Array(data.length);
					for (var i = 0; i < data.length; i++) {
						newData[i] = _deconstructPacket(data[i]);
					}
					return newData;
				} else if ('object' == typeof data && !(data instanceof Date)) {
					var newData = {};
					for (var key in data) {
						newData[key] = _deconstructPacket(data[key]);
					}
					return newData;
				}
				return data;
			}

			var pack = packet;
			pack.data = _deconstructPacket(packetData);
			pack.attachments = buffers.length; // number of binary 'attachments'
			return {packet: pack, buffers: buffers};
		};

		/**
		 * Reconstructs a binary packet from its placeholder packet and buffers
		 *
		 * @param {Object} packet - event packet with placeholders
		 * @param {Array} buffers - binary buffers to put in placeholder positions
		 * @return {Object} reconstructed packet
		 * @api public
		 */

		exports.reconstructPacket = function(packet, buffers) {
			var curPlaceHolder = 0;

			function _reconstructPacket(data) {
				if (data && data._placeholder) {
					var buf = buffers[data.num]; // appropriate buffer (should be natural order anyway)
					return buf;
				} else if (isArray(data)) {
					for (var i = 0; i < data.length; i++) {
						data[i] = _reconstructPacket(data[i]);
					}
					return data;
				} else if (data && 'object' == typeof data) {
					for (var key in data) {
						data[key] = _reconstructPacket(data[key]);
					}
					return data;
				}
				return data;
			}

			packet.data = _reconstructPacket(packet.data);
			packet.attachments = undefined; // no longer useful
			return packet;
		};

		/**
		 * Asynchronously removes Blobs or Files from data via
		 * FileReader's readAsArrayBuffer method. Used before encoding
		 * data as msgpack. Calls callback with the blobless data.
		 *
		 * @param {Object} data
		 * @param {Function} callback
		 * @api private
		 */

		exports.removeBlobs = function(data, callback) {
			function _removeBlobs(obj, curKey, containingObject) {
				if (!obj) return obj;

				// convert any blob
				if ((global.Blob && obj instanceof Blob) ||
					(global.File && obj instanceof File)) {
					pendingBlobs++;

					// async filereader
					var fileReader = new FileReader();
					fileReader.onload = function() { // this.result == arraybuffer
						if (containingObject) {
							containingObject[curKey] = this.result;
						}
						else {
							bloblessData = this.result;
						}

						// if nothing pending its callback time
						if(! --pendingBlobs) {
							callback(bloblessData);
						}
					};

					fileReader.readAsArrayBuffer(obj); // blob -> arraybuffer
				} else if (isArray(obj)) { // handle array
					for (var i = 0; i < obj.length; i++) {
						_removeBlobs(obj[i], i, obj);
					}
				} else if (obj && 'object' == typeof obj && !isBuf(obj)) { // and object
					for (var key in obj) {
						_removeBlobs(obj[key], key, obj);
					}
				}
			}

			var pendingBlobs = 0;
			var bloblessData = data;
			_removeBlobs(bloblessData);
			if (!pendingBlobs) {
				callback(bloblessData);
			}
		};

	}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{"./is-buffer":9,"isarray":12}],8:[function(require,module,exports){

	/**
	 * Module dependencies.
	 */

	var debug = require('debug')('socket.io-parser');
	var json = require('json3');
	var isArray = require('isarray');
	var Emitter = require('component-emitter');
	var binary = require('./binary');
	var isBuf = require('./is-buffer');

	/**
	 * Protocol version.
	 *
	 * @api public
	 */

	exports.protocol = 4;

	/**
	 * Packet types.
	 *
	 * @api public
	 */

	exports.types = [
		'CONNECT',
		'DISCONNECT',
		'EVENT',
		'BINARY_EVENT',
		'ACK',
		'BINARY_ACK',
		'ERROR'
	];

	/**
	 * Packet type `connect`.
	 *
	 * @api public
	 */

	exports.CONNECT = 0;

	/**
	 * Packet type `disconnect`.
	 *
	 * @api public
	 */

	exports.DISCONNECT = 1;

	/**
	 * Packet type `event`.
	 *
	 * @api public
	 */

	exports.EVENT = 2;

	/**
	 * Packet type `ack`.
	 *
	 * @api public
	 */

	exports.ACK = 3;

	/**
	 * Packet type `error`.
	 *
	 * @api public
	 */

	exports.ERROR = 4;

	/**
	 * Packet type 'binary event'
	 *
	 * @api public
	 */

	exports.BINARY_EVENT = 5;

	/**
	 * Packet type `binary ack`. For acks with binary arguments.
	 *
	 * @api public
	 */

	exports.BINARY_ACK = 6;

	/**
	 * Encoder constructor.
	 *
	 * @api public
	 */

	exports.Encoder = Encoder;

	/**
	 * Decoder constructor.
	 *
	 * @api public
	 */

	exports.Decoder = Decoder;

	/**
	 * A socket.io Encoder instance
	 *
	 * @api public
	 */

	function Encoder() {}

	/**
	 * Encode a packet as a single string if non-binary, or as a
	 * buffer sequence, depending on packet type.
	 *
	 * @param {Object} obj - packet object
	 * @param {Function} callback - function to handle encodings (likely engine.write)
	 * @return Calls callback with Array of encodings
	 * @api public
	 */

	Encoder.prototype.encode = function(obj, callback){
		debug('encoding packet %j', obj);

		if (exports.BINARY_EVENT == obj.type || exports.BINARY_ACK == obj.type) {
			encodeAsBinary(obj, callback);
		}
		else {
			var encoding = encodeAsString(obj);
			callback([encoding]);
		}
	};

	/**
	 * Encode packet as string.
	 *
	 * @param {Object} packet
	 * @return {String} encoded
	 * @api private
	 */

	function encodeAsString(obj) {
		var str = '';
		var nsp = false;

		// first is type
		str += obj.type;

		// attachments if we have them
		if (exports.BINARY_EVENT == obj.type || exports.BINARY_ACK == obj.type) {
			str += obj.attachments;
			str += '-';
		}

		// if we have a namespace other than `/`
		// we append it followed by a comma `,`
		if (obj.nsp && '/' != obj.nsp) {
			nsp = true;
			str += obj.nsp;
		}

		// immediately followed by the id
		if (null != obj.id) {
			if (nsp) {
				str += ',';
				nsp = false;
			}
			str += obj.id;
		}

		// json data
		if (null != obj.data) {
			if (nsp) str += ',';
			str += json.stringify(obj.data);
		}

		debug('encoded %j as %s', obj, str);
		return str;
	}

	/**
	 * Encode packet as 'buffer sequence' by removing blobs, and
	 * deconstructing packet into object with placeholders and
	 * a list of buffers.
	 *
	 * @param {Object} packet
	 * @return {Buffer} encoded
	 * @api private
	 */

	function encodeAsBinary(obj, callback) {

		function writeEncoding(bloblessData) {
			var deconstruction = binary.deconstructPacket(bloblessData);
			var pack = encodeAsString(deconstruction.packet);
			var buffers = deconstruction.buffers;

			buffers.unshift(pack); // add packet info to beginning of data list
			callback(buffers); // write all the buffers
		}

		binary.removeBlobs(obj, writeEncoding);
	}

	/**
	 * A socket.io Decoder instance
	 *
	 * @return {Object} decoder
	 * @api public
	 */

	function Decoder() {
		this.reconstructor = null;
	}

	/**
	 * Mix in `Emitter` with Decoder.
	 */

	Emitter(Decoder.prototype);

	/**
	 * Decodes an ecoded packet string into packet JSON.
	 *
	 * @param {String} obj - encoded packet
	 * @return {Object} packet
	 * @api public
	 */

	Decoder.prototype.add = function(obj) {
		var packet;
		if ('string' == typeof obj) {
			packet = decodeString(obj);
			if (exports.BINARY_EVENT == packet.type || exports.BINARY_ACK == packet.type) { // binary packet's json
				this.reconstructor = new BinaryReconstructor(packet);

				// no attachments, labeled binary but no binary data to follow
				if (this.reconstructor.reconPack.attachments === 0) {
					this.emit('decoded', packet);
				}
			} else { // non-binary full packet
				this.emit('decoded', packet);
			}
		}
		else if (isBuf(obj) || obj.base64) { // raw binary data
			if (!this.reconstructor) {
				throw new Error('got binary data when not reconstructing a packet');
			} else {
				packet = this.reconstructor.takeBinaryData(obj);
				if (packet) { // received final buffer
					this.reconstructor = null;
					this.emit('decoded', packet);
				}
			}
		}
		else {
			throw new Error('Unknown type: ' + obj);
		}
	};

	/**
	 * Decode a packet String (JSON data)
	 *
	 * @param {String} str
	 * @return {Object} packet
	 * @api private
	 */

	function decodeString(str) {
		var p = {};
		var i = 0;

		// look up type
		p.type = Number(str.charAt(0));
		if (null == exports.types[p.type]) return error();

		// look up attachments if type binary
		if (exports.BINARY_EVENT == p.type || exports.BINARY_ACK == p.type) {
			var buf = '';
			while (str.charAt(++i) != '-') {
				buf += str.charAt(i);
				if (i == str.length) break;
			}
			if (buf != Number(buf) || str.charAt(i) != '-') {
				throw new Error('Illegal attachments');
			}
			p.attachments = Number(buf);
		}

		// look up namespace (if any)
		if ('/' == str.charAt(i + 1)) {
			p.nsp = '';
			while (++i) {
				var c = str.charAt(i);
				if (',' == c) break;
				p.nsp += c;
				if (i == str.length) break;
			}
		} else {
			p.nsp = '/';
		}

		// look up id
		var next = str.charAt(i + 1);
		if ('' !== next && Number(next) == next) {
			p.id = '';
			while (++i) {
				var c = str.charAt(i);
				if (null == c || Number(c) != c) {
					--i;
					break;
				}
				p.id += str.charAt(i);
				if (i == str.length) break;
			}
			p.id = Number(p.id);
		}

		// look up json data
		if (str.charAt(++i)) {
			try {
				p.data = json.parse(str.substr(i));
			} catch(e){
				return error();
			}
		}

		debug('decoded %s as %j', str, p);
		return p;
	}

	/**
	 * Deallocates a parser's resources
	 *
	 * @api public
	 */

	Decoder.prototype.destroy = function() {
		if (this.reconstructor) {
			this.reconstructor.finishedReconstruction();
		}
	};

	/**
	 * A manager of a binary event's 'buffer sequence'. Should
	 * be constructed whenever a packet of type BINARY_EVENT is
	 * decoded.
	 *
	 * @param {Object} packet
	 * @return {BinaryReconstructor} initialized reconstructor
	 * @api private
	 */

	function BinaryReconstructor(packet) {
		this.reconPack = packet;
		this.buffers = [];
	}

	/**
	 * Method to be called when binary data received from connection
	 * after a BINARY_EVENT packet.
	 *
	 * @param {Buffer | ArrayBuffer} binData - the raw binary data received
	 * @return {null | Object} returns null if more binary data is expected or
	 *   a reconstructed packet object if all buffers have been received.
	 * @api private
	 */

	BinaryReconstructor.prototype.takeBinaryData = function(binData) {
		this.buffers.push(binData);
		if (this.buffers.length == this.reconPack.attachments) { // done with buffer list
			var packet = binary.reconstructPacket(this.reconPack, this.buffers);
			this.finishedReconstruction();
			return packet;
		}
		return null;
	};

	/**
	 * Cleans up binary packet reconstruction variables.
	 *
	 * @api private
	 */

	BinaryReconstructor.prototype.finishedReconstruction = function() {
		this.reconPack = null;
		this.buffers = [];
	};

	function error(data){
		return {
			type: exports.ERROR,
			data: 'parser error'
		};
	}

},{"./binary":7,"./is-buffer":9,"component-emitter":10,"debug":11,"isarray":12,"json3":13}],9:[function(require,module,exports){
	(function (global){

		module.exports = isBuf;

		/**
		 * Returns true if obj is a buffer or an arraybuffer.
		 *
		 * @api private
		 */

		function isBuf(obj) {
			return (global.Buffer && global.Buffer.isBuffer(obj)) ||
				(global.ArrayBuffer && obj instanceof ArrayBuffer);
		}

	}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{}],10:[function(require,module,exports){

	/**
	 * Expose `Emitter`.
	 */

	module.exports = Emitter;

	/**
	 * Initialize a new `Emitter`.
	 *
	 * @api public
	 */

	function Emitter(obj) {
		if (obj) return mixin(obj);
	};

	/**
	 * Mixin the emitter properties.
	 *
	 * @param {Object} obj
	 * @return {Object}
	 * @api private
	 */

	function mixin(obj) {
		for (var key in Emitter.prototype) {
			obj[key] = Emitter.prototype[key];
		}
		return obj;
	}

	/**
	 * Listen on the given `event` with `fn`.
	 *
	 * @param {String} event
	 * @param {Function} fn
	 * @return {Emitter}
	 * @api public
	 */

	Emitter.prototype.on =
		Emitter.prototype.addEventListener = function(event, fn){
			this._callbacks = this._callbacks || {};
			(this._callbacks[event] = this._callbacks[event] || [])
				.push(fn);
			return this;
		};

	/**
	 * Adds an `event` listener that will be invoked a single
	 * time then automatically removed.
	 *
	 * @param {String} event
	 * @param {Function} fn
	 * @return {Emitter}
	 * @api public
	 */

	Emitter.prototype.once = function(event, fn){
		var self = this;
		this._callbacks = this._callbacks || {};

		function on() {
			self.off(event, on);
			fn.apply(this, arguments);
		}

		on.fn = fn;
		this.on(event, on);
		return this;
	};

	/**
	 * Remove the given callback for `event` or all
	 * registered callbacks.
	 *
	 * @param {String} event
	 * @param {Function} fn
	 * @return {Emitter}
	 * @api public
	 */

	Emitter.prototype.off =
		Emitter.prototype.removeListener =
			Emitter.prototype.removeAllListeners =
				Emitter.prototype.removeEventListener = function(event, fn){
					this._callbacks = this._callbacks || {};

					// all
					if (0 == arguments.length) {
						this._callbacks = {};
						return this;
					}

					// specific event
					var callbacks = this._callbacks[event];
					if (!callbacks) return this;

					// remove all handlers
					if (1 == arguments.length) {
						delete this._callbacks[event];
						return this;
					}

					// remove specific handler
					var cb;
					for (var i = 0; i < callbacks.length; i++) {
						cb = callbacks[i];
						if (cb === fn || cb.fn === fn) {
							callbacks.splice(i, 1);
							break;
						}
					}
					return this;
				};

	/**
	 * Emit `event` with the given args.
	 *
	 * @param {String} event
	 * @param {Mixed} ...
	 * @return {Emitter}
	 */

	Emitter.prototype.emit = function(event){
		this._callbacks = this._callbacks || {};
		var args = [].slice.call(arguments, 1)
			, callbacks = this._callbacks[event];

		if (callbacks) {
			callbacks = callbacks.slice(0);
			for (var i = 0, len = callbacks.length; i < len; ++i) {
				callbacks[i].apply(this, args);
			}
		}

		return this;
	};

	/**
	 * Return array of callbacks for `event`.
	 *
	 * @param {String} event
	 * @return {Array}
	 * @api public
	 */

	Emitter.prototype.listeners = function(event){
		this._callbacks = this._callbacks || {};
		return this._callbacks[event] || [];
	};

	/**
	 * Check if this emitter has `event` handlers.
	 *
	 * @param {String} event
	 * @return {Boolean}
	 * @api public
	 */

	Emitter.prototype.hasListeners = function(event){
		return !! this.listeners(event).length;
	};

},{}],11:[function(require,module,exports){

	/**
	 * Expose `debug()` as the module.
	 */

	module.exports = debug;

	/**
	 * Create a debugger with the given `name`.
	 *
	 * @param {String} name
	 * @return {Type}
	 * @api public
	 */

	function debug(name) {
		if (!debug.enabled(name)) return function(){};

		return function(fmt){
			fmt = coerce(fmt);

			var curr = new Date;
			var ms = curr - (debug[name] || curr);
			debug[name] = curr;

			fmt = name
				+ ' '
				+ fmt
				+ ' +' + debug.humanize(ms);

			// This hackery is required for IE8
			// where `console.log` doesn't have 'apply'
			window.console
			&& console.log
			&& Function.prototype.apply.call(console.log, console, arguments);
		}
	}

	/**
	 * The currently active debug mode names.
	 */

	debug.names = [];
	debug.skips = [];

	/**
	 * Enables a debug mode by name. This can include modes
	 * separated by a colon and wildcards.
	 *
	 * @param {String} name
	 * @api public
	 */

	debug.enable = function(name) {
		try {
			localStorage.debug = name;
		} catch(e){}

		var split = (name || '').split(/[\s,]+/)
			, len = split.length;

		for (var i = 0; i < len; i++) {
			name = split[i].replace('*', '.*?');
			if (name[0] === '-') {
				debug.skips.push(new RegExp('^' + name.substr(1) + '$'));
			}
			else {
				debug.names.push(new RegExp('^' + name + '$'));
			}
		}
	};

	/**
	 * Disable debug output.
	 *
	 * @api public
	 */

	debug.disable = function(){
		debug.enable('');
	};

	/**
	 * Humanize the given `ms`.
	 *
	 * @param {Number} m
	 * @return {String}
	 * @api private
	 */

	debug.humanize = function(ms) {
		var sec = 1000
			, min = 60 * 1000
			, hour = 60 * min;

		if (ms >= hour) return (ms / hour).toFixed(1) + 'h';
		if (ms >= min) return (ms / min).toFixed(1) + 'm';
		if (ms >= sec) return (ms / sec | 0) + 's';
		return ms + 'ms';
	};

	/**
	 * Returns true if the given mode name is enabled, false otherwise.
	 *
	 * @param {String} name
	 * @return {Boolean}
	 * @api public
	 */

	debug.enabled = function(name) {
		for (var i = 0, len = debug.skips.length; i < len; i++) {
			if (debug.skips[i].test(name)) {
				return false;
			}
		}
		for (var i = 0, len = debug.names.length; i < len; i++) {
			if (debug.names[i].test(name)) {
				return true;
			}
		}
		return false;
	};

	/**
	 * Coerce `val`.
	 */

	function coerce(val) {
		if (val instanceof Error) return val.stack || val.message;
		return val;
	}

// persist

	try {
		if (window.localStorage) debug.enable(localStorage.debug);
	} catch(e){}

},{}],12:[function(require,module,exports){
	arguments[4][6][0].apply(exports,arguments)
},{"dup":6}],13:[function(require,module,exports){
	/*! JSON v3.2.6 | http://bestiejs.github.io/json3 | Copyright 2012-2013, Kit Cambridge | http://kit.mit-license.org */
	;(function (window) {
		// Convenience aliases.
		var getClass = {}.toString, isProperty, forEach, undef;

		// Detect the `define` function exposed by asynchronous module loaders. The
		// strict `define` check is necessary for compatibility with `r.js`.
		var isLoader = typeof define === "function" && define.amd;

		// Detect native implementations.
		var nativeJSON = typeof JSON == "object" && JSON;

		// Set up the JSON 3 namespace, preferring the CommonJS `exports` object if
		// available.
		var JSON3 = typeof exports == "object" && exports && !exports.nodeType && exports;

		if (JSON3 && nativeJSON) {
			// Explicitly delegate to the native `stringify` and `parse`
			// implementations in CommonJS environments.
			JSON3.stringify = nativeJSON.stringify;
			JSON3.parse = nativeJSON.parse;
		} else {
			// Export for web browsers, JavaScript engines, and asynchronous module
			// loaders, using the global `JSON` object if available.
			JSON3 = window.JSON = nativeJSON || {};
		}

		// Test the `Date#getUTC*` methods. Based on work by @Yaffle.
		var isExtended = new Date(-3509827334573292);
		try {
			// The `getUTCFullYear`, `Month`, and `Date` methods return nonsensical
			// results for certain dates in Opera >= 10.53.
			isExtended = isExtended.getUTCFullYear() == -109252 && isExtended.getUTCMonth() === 0 && isExtended.getUTCDate() === 1 &&
					// Safari < 2.0.2 stores the internal millisecond time value correctly,
					// but clips the values returned by the date methods to the range of
					// signed 32-bit integers ([-2 ** 31, 2 ** 31 - 1]).
				isExtended.getUTCHours() == 10 && isExtended.getUTCMinutes() == 37 && isExtended.getUTCSeconds() == 6 && isExtended.getUTCMilliseconds() == 708;
		} catch (exception) {}

		// Internal: Determines whether the native `JSON.stringify` and `parse`
		// implementations are spec-compliant. Based on work by Ken Snyder.
		function has(name) {
			if (has[name] !== undef) {
				// Return cached feature test result.
				return has[name];
			}

			var isSupported;
			if (name == "bug-string-char-index") {
				// IE <= 7 doesn't support accessing string characters using square
				// bracket notation. IE 8 only supports this for primitives.
				isSupported = "a"[0] != "a";
			} else if (name == "json") {
				// Indicates whether both `JSON.stringify` and `JSON.parse` are
				// supported.
				isSupported = has("json-stringify") && has("json-parse");
			} else {
				var value, serialized = '{"a":[1,true,false,null,"\\u0000\\b\\n\\f\\r\\t"]}';
				// Test `JSON.stringify`.
				if (name == "json-stringify") {
					var stringify = JSON3.stringify, stringifySupported = typeof stringify == "function" && isExtended;
					if (stringifySupported) {
						// A test function object with a custom `toJSON` method.
						(value = function () {
							return 1;
						}).toJSON = value;
						try {
							stringifySupported =
								// Firefox 3.1b1 and b2 serialize string, number, and boolean
								// primitives as object literals.
								stringify(0) === "0" &&
									// FF 3.1b1, b2, and JSON 2 serialize wrapped primitives as object
									// literals.
								stringify(new Number()) === "0" &&
								stringify(new String()) == '""' &&
									// FF 3.1b1, 2 throw an error if the value is `null`, `undefined`, or
									// does not define a canonical JSON representation (this applies to
									// objects with `toJSON` properties as well, *unless* they are nested
									// within an object or array).
								stringify(getClass) === undef &&
									// IE 8 serializes `undefined` as `"undefined"`. Safari <= 5.1.7 and
									// FF 3.1b3 pass this test.
								stringify(undef) === undef &&
									// Safari <= 5.1.7 and FF 3.1b3 throw `Error`s and `TypeError`s,
									// respectively, if the value is omitted entirely.
								stringify() === undef &&
									// FF 3.1b1, 2 throw an error if the given value is not a number,
									// string, array, object, Boolean, or `null` literal. This applies to
									// objects with custom `toJSON` methods as well, unless they are nested
									// inside object or array literals. YUI 3.0.0b1 ignores custom `toJSON`
									// methods entirely.
								stringify(value) === "1" &&
								stringify([value]) == "[1]" &&
									// Prototype <= 1.6.1 serializes `[undefined]` as `"[]"` instead of
									// `"[null]"`.
								stringify([undef]) == "[null]" &&
									// YUI 3.0.0b1 fails to serialize `null` literals.
								stringify(null) == "null" &&
									// FF 3.1b1, 2 halts serialization if an array contains a function:
									// `[1, true, getClass, 1]` serializes as "[1,true,],". FF 3.1b3
									// elides non-JSON values from objects and arrays, unless they
									// define custom `toJSON` methods.
								stringify([undef, getClass, null]) == "[null,null,null]" &&
									// Simple serialization test. FF 3.1b1 uses Unicode escape sequences
									// where character escape codes are expected (e.g., `\b` => `\u0008`).
								stringify({ "a": [value, true, false, null, "\x00\b\n\f\r\t"] }) == serialized &&
									// FF 3.1b1 and b2 ignore the `filter` and `width` arguments.
								stringify(null, value) === "1" &&
								stringify([1, 2], null, 1) == "[\n 1,\n 2\n]" &&
									// JSON 2, Prototype <= 1.7, and older WebKit builds incorrectly
									// serialize extended years.
								stringify(new Date(-8.64e15)) == '"-271821-04-20T00:00:00.000Z"' &&
									// The milliseconds are optional in ES 5, but required in 5.1.
								stringify(new Date(8.64e15)) == '"+275760-09-13T00:00:00.000Z"' &&
									// Firefox <= 11.0 incorrectly serializes years prior to 0 as negative
									// four-digit years instead of six-digit years. Credits: @Yaffle.
								stringify(new Date(-621987552e5)) == '"-000001-01-01T00:00:00.000Z"' &&
									// Safari <= 5.1.5 and Opera >= 10.53 incorrectly serialize millisecond
									// values less than 1000. Credits: @Yaffle.
								stringify(new Date(-1)) == '"1969-12-31T23:59:59.999Z"';
						} catch (exception) {
							stringifySupported = false;
						}
					}
					isSupported = stringifySupported;
				}
				// Test `JSON.parse`.
				if (name == "json-parse") {
					var parse = JSON3.parse;
					if (typeof parse == "function") {
						try {
							// FF 3.1b1, b2 will throw an exception if a bare literal is provided.
							// Conforming implementations should also coerce the initial argument to
							// a string prior to parsing.
							if (parse("0") === 0 && !parse(false)) {
								// Simple parsing test.
								value = parse(serialized);
								var parseSupported = value["a"].length == 5 && value["a"][0] === 1;
								if (parseSupported) {
									try {
										// Safari <= 5.1.2 and FF 3.1b1 allow unescaped tabs in strings.
										parseSupported = !parse('"\t"');
									} catch (exception) {}
									if (parseSupported) {
										try {
											// FF 4.0 and 4.0.1 allow leading `+` signs and leading
											// decimal points. FF 4.0, 4.0.1, and IE 9-10 also allow
											// certain octal literals.
											parseSupported = parse("01") !== 1;
										} catch (exception) {}
									}
									if (parseSupported) {
										try {
											// FF 4.0, 4.0.1, and Rhino 1.7R3-R4 allow trailing decimal
											// points. These environments, along with FF 3.1b1 and 2,
											// also allow trailing commas in JSON objects and arrays.
											parseSupported = parse("1.") !== 1;
										} catch (exception) {}
									}
								}
							}
						} catch (exception) {
							parseSupported = false;
						}
					}
					isSupported = parseSupported;
				}
			}
			return has[name] = !!isSupported;
		}

		if (!has("json")) {
			// Common `[[Class]]` name aliases.
			var functionClass = "[object Function]";
			var dateClass = "[object Date]";
			var numberClass = "[object Number]";
			var stringClass = "[object String]";
			var arrayClass = "[object Array]";
			var booleanClass = "[object Boolean]";

			// Detect incomplete support for accessing string characters by index.
			var charIndexBuggy = has("bug-string-char-index");

			// Define additional utility methods if the `Date` methods are buggy.
			if (!isExtended) {
				var floor = Math.floor;
				// A mapping between the months of the year and the number of days between
				// January 1st and the first of the respective month.
				var Months = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
				// Internal: Calculates the number of days between the Unix epoch and the
				// first day of the given month.
				var getDay = function (year, month) {
					return Months[month] + 365 * (year - 1970) + floor((year - 1969 + (month = +(month > 1))) / 4) - floor((year - 1901 + month) / 100) + floor((year - 1601 + month) / 400);
				};
			}

			// Internal: Determines if a property is a direct property of the given
			// object. Delegates to the native `Object#hasOwnProperty` method.
			if (!(isProperty = {}.hasOwnProperty)) {
				isProperty = function (property) {
					var members = {}, constructor;
					if ((members.__proto__ = null, members.__proto__ = {
							// The *proto* property cannot be set multiple times in recent
							// versions of Firefox and SeaMonkey.
							"toString": 1
						}, members).toString != getClass) {
						// Safari <= 2.0.3 doesn't implement `Object#hasOwnProperty`, but
						// supports the mutable *proto* property.
						isProperty = function (property) {
							// Capture and break the object's prototype chain (see section 8.6.2
							// of the ES 5.1 spec). The parenthesized expression prevents an
							// unsafe transformation by the Closure Compiler.
							var original = this.__proto__, result = property in (this.__proto__ = null, this);
							// Restore the original prototype chain.
							this.__proto__ = original;
							return result;
						};
					} else {
						// Capture a reference to the top-level `Object` constructor.
						constructor = members.constructor;
						// Use the `constructor` property to simulate `Object#hasOwnProperty` in
						// other environments.
						isProperty = function (property) {
							var parent = (this.constructor || constructor).prototype;
							return property in this && !(property in parent && this[property] === parent[property]);
						};
					}
					members = null;
					return isProperty.call(this, property);
				};
			}

			// Internal: A set of primitive types used by `isHostType`.
			var PrimitiveTypes = {
				'boolean': 1,
				'number': 1,
				'string': 1,
				'undefined': 1
			};

			// Internal: Determines if the given object `property` value is a
			// non-primitive.
			var isHostType = function (object, property) {
				var type = typeof object[property];
				return type == 'object' ? !!object[property] : !PrimitiveTypes[type];
			};

			// Internal: Normalizes the `for...in` iteration algorithm across
			// environments. Each enumerated key is yielded to a `callback` function.
			forEach = function (object, callback) {
				var size = 0, Properties, members, property;

				// Tests for bugs in the current environment's `for...in` algorithm. The
				// `valueOf` property inherits the non-enumerable flag from
				// `Object.prototype` in older versions of IE, Netscape, and Mozilla.
				(Properties = function () {
					this.valueOf = 0;
				}).prototype.valueOf = 0;

				// Iterate over a new instance of the `Properties` class.
				members = new Properties();
				for (property in members) {
					// Ignore all properties inherited from `Object.prototype`.
					if (isProperty.call(members, property)) {
						size++;
					}
				}
				Properties = members = null;

				// Normalize the iteration algorithm.
				if (!size) {
					// A list of non-enumerable properties inherited from `Object.prototype`.
					members = ["valueOf", "toString", "toLocaleString", "propertyIsEnumerable", "isPrototypeOf", "hasOwnProperty", "constructor"];
					// IE <= 8, Mozilla 1.0, and Netscape 6.2 ignore shadowed non-enumerable
					// properties.
					forEach = function (object, callback) {
						var isFunction = getClass.call(object) == functionClass, property, length;
						var hasProperty = !isFunction && typeof object.constructor != 'function' && isHostType(object, 'hasOwnProperty') ? object.hasOwnProperty : isProperty;
						for (property in object) {
							// Gecko <= 1.0 enumerates the `prototype` property of functions under
							// certain conditions; IE does not.
							if (!(isFunction && property == "prototype") && hasProperty.call(object, property)) {
								callback(property);
							}
						}
						// Manually invoke the callback for each non-enumerable property.
						for (length = members.length; property = members[--length]; hasProperty.call(object, property) && callback(property));
					};
				} else if (size == 2) {
					// Safari <= 2.0.4 enumerates shadowed properties twice.
					forEach = function (object, callback) {
						// Create a set of iterated properties.
						var members = {}, isFunction = getClass.call(object) == functionClass, property;
						for (property in object) {
							// Store each property name to prevent double enumeration. The
							// `prototype` property of functions is not enumerated due to cross-
							// environment inconsistencies.
							if (!(isFunction && property == "prototype") && !isProperty.call(members, property) && (members[property] = 1) && isProperty.call(object, property)) {
								callback(property);
							}
						}
					};
				} else {
					// No bugs detected; use the standard `for...in` algorithm.
					forEach = function (object, callback) {
						var isFunction = getClass.call(object) == functionClass, property, isConstructor;
						for (property in object) {
							if (!(isFunction && property == "prototype") && isProperty.call(object, property) && !(isConstructor = property === "constructor")) {
								callback(property);
							}
						}
						// Manually invoke the callback for the `constructor` property due to
						// cross-environment inconsistencies.
						if (isConstructor || isProperty.call(object, (property = "constructor"))) {
							callback(property);
						}
					};
				}
				return forEach(object, callback);
			};

			// Public: Serializes a JavaScript `value` as a JSON string. The optional
			// `filter` argument may specify either a function that alters how object and
			// array members are serialized, or an array of strings and numbers that
			// indicates which properties should be serialized. The optional `width`
			// argument may be either a string or number that specifies the indentation
			// level of the output.
			if (!has("json-stringify")) {
				// Internal: A map of control characters and their escaped equivalents.
				var Escapes = {
					92: "\\\\",
					34: '\\"',
					8: "\\b",
					12: "\\f",
					10: "\\n",
					13: "\\r",
					9: "\\t"
				};

				// Internal: Converts `value` into a zero-padded string such that its
				// length is at least equal to `width`. The `width` must be <= 6.
				var leadingZeroes = "000000";
				var toPaddedString = function (width, value) {
					// The `|| 0` expression is necessary to work around a bug in
					// Opera <= 7.54u2 where `0 == -0`, but `String(-0) !== "0"`.
					return (leadingZeroes + (value || 0)).slice(-width);
				};

				// Internal: Double-quotes a string `value`, replacing all ASCII control
				// characters (characters with code unit values between 0 and 31) with
				// their escaped equivalents. This is an implementation of the
				// `Quote(value)` operation defined in ES 5.1 section 15.12.3.
				var unicodePrefix = "\\u00";
				var quote = function (value) {
					var result = '"', index = 0, length = value.length, isLarge = length > 10 && charIndexBuggy, symbols;
					if (isLarge) {
						symbols = value.split("");
					}
					for (; index < length; index++) {
						var charCode = value.charCodeAt(index);
						// If the character is a control character, append its Unicode or
						// shorthand escape sequence; otherwise, append the character as-is.
						switch (charCode) {
							case 8: case 9: case 10: case 12: case 13: case 34: case 92:
							result += Escapes[charCode];
							break;
							default:
								if (charCode < 32) {
									result += unicodePrefix + toPaddedString(2, charCode.toString(16));
									break;
								}
								result += isLarge ? symbols[index] : charIndexBuggy ? value.charAt(index) : value[index];
						}
					}
					return result + '"';
				};

				// Internal: Recursively serializes an object. Implements the
				// `Str(key, holder)`, `JO(value)`, and `JA(value)` operations.
				var serialize = function (property, object, callback, properties, whitespace, indentation, stack) {
					var value, className, year, month, date, time, hours, minutes, seconds, milliseconds, results, element, index, length, prefix, result;
					try {
						// Necessary for host object support.
						value = object[property];
					} catch (exception) {}
					if (typeof value == "object" && value) {
						className = getClass.call(value);
						if (className == dateClass && !isProperty.call(value, "toJSON")) {
							if (value > -1 / 0 && value < 1 / 0) {
								// Dates are serialized according to the `Date#toJSON` method
								// specified in ES 5.1 section 15.9.5.44. See section 15.9.1.15
								// for the ISO 8601 date time string format.
								if (getDay) {
									// Manually compute the year, month, date, hours, minutes,
									// seconds, and milliseconds if the `getUTC*` methods are
									// buggy. Adapted from @Yaffle's `date-shim` project.
									date = floor(value / 864e5);
									for (year = floor(date / 365.2425) + 1970 - 1; getDay(year + 1, 0) <= date; year++);
									for (month = floor((date - getDay(year, 0)) / 30.42); getDay(year, month + 1) <= date; month++);
									date = 1 + date - getDay(year, month);
									// The `time` value specifies the time within the day (see ES
									// 5.1 section 15.9.1.2). The formula `(A % B + B) % B` is used
									// to compute `A modulo B`, as the `%` operator does not
									// correspond to the `modulo` operation for negative numbers.
									time = (value % 864e5 + 864e5) % 864e5;
									// The hours, minutes, seconds, and milliseconds are obtained by
									// decomposing the time within the day. See section 15.9.1.10.
									hours = floor(time / 36e5) % 24;
									minutes = floor(time / 6e4) % 60;
									seconds = floor(time / 1e3) % 60;
									milliseconds = time % 1e3;
								} else {
									year = value.getUTCFullYear();
									month = value.getUTCMonth();
									date = value.getUTCDate();
									hours = value.getUTCHours();
									minutes = value.getUTCMinutes();
									seconds = value.getUTCSeconds();
									milliseconds = value.getUTCMilliseconds();
								}
								// Serialize extended years correctly.
								value = (year <= 0 || year >= 1e4 ? (year < 0 ? "-" : "+") + toPaddedString(6, year < 0 ? -year : year) : toPaddedString(4, year)) +
									"-" + toPaddedString(2, month + 1) + "-" + toPaddedString(2, date) +
										// Months, dates, hours, minutes, and seconds should have two
										// digits; milliseconds should have three.
									"T" + toPaddedString(2, hours) + ":" + toPaddedString(2, minutes) + ":" + toPaddedString(2, seconds) +
										// Milliseconds are optional in ES 5.0, but required in 5.1.
									"." + toPaddedString(3, milliseconds) + "Z";
							} else {
								value = null;
							}
						} else if (typeof value.toJSON == "function" && ((className != numberClass && className != stringClass && className != arrayClass) || isProperty.call(value, "toJSON"))) {
							// Prototype <= 1.6.1 adds non-standard `toJSON` methods to the
							// `Number`, `String`, `Date`, and `Array` prototypes. JSON 3
							// ignores all `toJSON` methods on these objects unless they are
							// defined directly on an instance.
							value = value.toJSON(property);
						}
					}
					if (callback) {
						// If a replacement function was provided, call it to obtain the value
						// for serialization.
						value = callback.call(object, property, value);
					}
					if (value === null) {
						return "null";
					}
					className = getClass.call(value);
					if (className == booleanClass) {
						// Booleans are represented literally.
						return "" + value;
					} else if (className == numberClass) {
						// JSON numbers must be finite. `Infinity` and `NaN` are serialized as
						// `"null"`.
						return value > -1 / 0 && value < 1 / 0 ? "" + value : "null";
					} else if (className == stringClass) {
						// Strings are double-quoted and escaped.
						return quote("" + value);
					}
					// Recursively serialize objects and arrays.
					if (typeof value == "object") {
						// Check for cyclic structures. This is a linear search; performance
						// is inversely proportional to the number of unique nested objects.
						for (length = stack.length; length--;) {
							if (stack[length] === value) {
								// Cyclic structures cannot be serialized by `JSON.stringify`.
								throw TypeError();
							}
						}
						// Add the object to the stack of traversed objects.
						stack.push(value);
						results = [];
						// Save the current indentation level and indent one additional level.
						prefix = indentation;
						indentation += whitespace;
						if (className == arrayClass) {
							// Recursively serialize array elements.
							for (index = 0, length = value.length; index < length; index++) {
								element = serialize(index, value, callback, properties, whitespace, indentation, stack);
								results.push(element === undef ? "null" : element);
							}
							result = results.length ? (whitespace ? "[\n" + indentation + results.join(",\n" + indentation) + "\n" + prefix + "]" : ("[" + results.join(",") + "]")) : "[]";
						} else {
							// Recursively serialize object members. Members are selected from
							// either a user-specified list of property names, or the object
							// itself.
							forEach(properties || value, function (property) {
								var element = serialize(property, value, callback, properties, whitespace, indentation, stack);
								if (element !== undef) {
									// According to ES 5.1 section 15.12.3: "If `gap` {whitespace}
									// is not the empty string, let `member` {quote(property) + ":"}
									// be the concatenation of `member` and the `space` character."
									// The "`space` character" refers to the literal space
									// character, not the `space` {width} argument provided to
									// `JSON.stringify`.
									results.push(quote(property) + ":" + (whitespace ? " " : "") + element);
								}
							});
							result = results.length ? (whitespace ? "{\n" + indentation + results.join(",\n" + indentation) + "\n" + prefix + "}" : ("{" + results.join(",") + "}")) : "{}";
						}
						// Remove the object from the traversed object stack.
						stack.pop();
						return result;
					}
				};

				// Public: `JSON.stringify`. See ES 5.1 section 15.12.3.
				JSON3.stringify = function (source, filter, width) {
					var whitespace, callback, properties, className;
					if (typeof filter == "function" || typeof filter == "object" && filter) {
						if ((className = getClass.call(filter)) == functionClass) {
							callback = filter;
						} else if (className == arrayClass) {
							// Convert the property names array into a makeshift set.
							properties = {};
							for (var index = 0, length = filter.length, value; index < length; value = filter[index++], ((className = getClass.call(value)), className == stringClass || className == numberClass) && (properties[value] = 1));
						}
					}
					if (width) {
						if ((className = getClass.call(width)) == numberClass) {
							// Convert the `width` to an integer and create a string containing
							// `width` number of space characters.
							if ((width -= width % 1) > 0) {
								for (whitespace = "", width > 10 && (width = 10); whitespace.length < width; whitespace += " ");
							}
						} else if (className == stringClass) {
							whitespace = width.length <= 10 ? width : width.slice(0, 10);
						}
					}
					// Opera <= 7.54u2 discards the values associated with empty string keys
					// (`""`) only if they are used directly within an object member list
					// (e.g., `!("" in { "": 1})`).
					return serialize("", (value = {}, value[""] = source, value), callback, properties, whitespace, "", []);
				};
			}

			// Public: Parses a JSON source string.
			if (!has("json-parse")) {
				var fromCharCode = String.fromCharCode;

				// Internal: A map of escaped control characters and their unescaped
				// equivalents.
				var Unescapes = {
					92: "\\",
					34: '"',
					47: "/",
					98: "\b",
					116: "\t",
					110: "\n",
					102: "\f",
					114: "\r"
				};

				// Internal: Stores the parser state.
				var Index, Source;

				// Internal: Resets the parser state and throws a `SyntaxError`.
				var abort = function() {
					Index = Source = null;
					throw SyntaxError();
				};

				// Internal: Returns the next token, or `"$"` if the parser has reached
				// the end of the source string. A token may be a string, number, `null`
				// literal, or Boolean literal.
				var lex = function () {
					var source = Source, length = source.length, value, begin, position, isSigned, charCode;
					while (Index < length) {
						charCode = source.charCodeAt(Index);
						switch (charCode) {
							case 9: case 10: case 13: case 32:
							// Skip whitespace tokens, including tabs, carriage returns, line
							// feeds, and space characters.
							Index++;
							break;
							case 123: case 125: case 91: case 93: case 58: case 44:
							// Parse a punctuator token (`{`, `}`, `[`, `]`, `:`, or `,`) at
							// the current position.
							value = charIndexBuggy ? source.charAt(Index) : source[Index];
							Index++;
							return value;
							case 34:
								// `"` delimits a JSON string; advance to the next character and
								// begin parsing the string. String tokens are prefixed with the
								// sentinel `@` character to distinguish them from punctuators and
								// end-of-string tokens.
								for (value = "@", Index++; Index < length;) {
									charCode = source.charCodeAt(Index);
									if (charCode < 32) {
										// Unescaped ASCII control characters (those with a code unit
										// less than the space character) are not permitted.
										abort();
									} else if (charCode == 92) {
										// A reverse solidus (`\`) marks the beginning of an escaped
										// control character (including `"`, `\`, and `/`) or Unicode
										// escape sequence.
										charCode = source.charCodeAt(++Index);
										switch (charCode) {
											case 92: case 34: case 47: case 98: case 116: case 110: case 102: case 114:
											// Revive escaped control characters.
											value += Unescapes[charCode];
											Index++;
											break;
											case 117:
												// `\u` marks the beginning of a Unicode escape sequence.
												// Advance to the first character and validate the
												// four-digit code point.
												begin = ++Index;
												for (position = Index + 4; Index < position; Index++) {
													charCode = source.charCodeAt(Index);
													// A valid sequence comprises four hexdigits (case-
													// insensitive) that form a single hexadecimal value.
													if (!(charCode >= 48 && charCode <= 57 || charCode >= 97 && charCode <= 102 || charCode >= 65 && charCode <= 70)) {
														// Invalid Unicode escape sequence.
														abort();
													}
												}
												// Revive the escaped character.
												value += fromCharCode("0x" + source.slice(begin, Index));
												break;
											default:
												// Invalid escape sequence.
												abort();
										}
									} else {
										if (charCode == 34) {
											// An unescaped double-quote character marks the end of the
											// string.
											break;
										}
										charCode = source.charCodeAt(Index);
										begin = Index;
										// Optimize for the common case where a string is valid.
										while (charCode >= 32 && charCode != 92 && charCode != 34) {
											charCode = source.charCodeAt(++Index);
										}
										// Append the string as-is.
										value += source.slice(begin, Index);
									}
								}
								if (source.charCodeAt(Index) == 34) {
									// Advance to the next character and return the revived string.
									Index++;
									return value;
								}
								// Unterminated string.
								abort();
							default:
								// Parse numbers and literals.
								begin = Index;
								// Advance past the negative sign, if one is specified.
								if (charCode == 45) {
									isSigned = true;
									charCode = source.charCodeAt(++Index);
								}
								// Parse an integer or floating-point value.
								if (charCode >= 48 && charCode <= 57) {
									// Leading zeroes are interpreted as octal literals.
									if (charCode == 48 && ((charCode = source.charCodeAt(Index + 1)), charCode >= 48 && charCode <= 57)) {
										// Illegal octal literal.
										abort();
									}
									isSigned = false;
									// Parse the integer component.
									for (; Index < length && ((charCode = source.charCodeAt(Index)), charCode >= 48 && charCode <= 57); Index++);
									// Floats cannot contain a leading decimal point; however, this
									// case is already accounted for by the parser.
									if (source.charCodeAt(Index) == 46) {
										position = ++Index;
										// Parse the decimal component.
										for (; position < length && ((charCode = source.charCodeAt(position)), charCode >= 48 && charCode <= 57); position++);
										if (position == Index) {
											// Illegal trailing decimal.
											abort();
										}
										Index = position;
									}
									// Parse exponents. The `e` denoting the exponent is
									// case-insensitive.
									charCode = source.charCodeAt(Index);
									if (charCode == 101 || charCode == 69) {
										charCode = source.charCodeAt(++Index);
										// Skip past the sign following the exponent, if one is
										// specified.
										if (charCode == 43 || charCode == 45) {
											Index++;
										}
										// Parse the exponential component.
										for (position = Index; position < length && ((charCode = source.charCodeAt(position)), charCode >= 48 && charCode <= 57); position++);
										if (position == Index) {
											// Illegal empty exponent.
											abort();
										}
										Index = position;
									}
									// Coerce the parsed value to a JavaScript number.
									return +source.slice(begin, Index);
								}
								// A negative sign may only precede numbers.
								if (isSigned) {
									abort();
								}
								// `true`, `false`, and `null` literals.
								if (source.slice(Index, Index + 4) == "true") {
									Index += 4;
									return true;
								} else if (source.slice(Index, Index + 5) == "false") {
									Index += 5;
									return false;
								} else if (source.slice(Index, Index + 4) == "null") {
									Index += 4;
									return null;
								}
								// Unrecognized token.
								abort();
						}
					}
					// Return the sentinel `$` character if the parser has reached the end
					// of the source string.
					return "$";
				};

				// Internal: Parses a JSON `value` token.
				var get = function (value) {
					var results, hasMembers;
					if (value == "$") {
						// Unexpected end of input.
						abort();
					}
					if (typeof value == "string") {
						if ((charIndexBuggy ? value.charAt(0) : value[0]) == "@") {
							// Remove the sentinel `@` character.
							return value.slice(1);
						}
						// Parse object and array literals.
						if (value == "[") {
							// Parses a JSON array, returning a new JavaScript array.
							results = [];
							for (;; hasMembers || (hasMembers = true)) {
								value = lex();
								// A closing square bracket marks the end of the array literal.
								if (value == "]") {
									break;
								}
								// If the array literal contains elements, the current token
								// should be a comma separating the previous element from the
								// next.
								if (hasMembers) {
									if (value == ",") {
										value = lex();
										if (value == "]") {
											// Unexpected trailing `,` in array literal.
											abort();
										}
									} else {
										// A `,` must separate each array element.
										abort();
									}
								}
								// Elisions and leading commas are not permitted.
								if (value == ",") {
									abort();
								}
								results.push(get(value));
							}
							return results;
						} else if (value == "{") {
							// Parses a JSON object, returning a new JavaScript object.
							results = {};
							for (;; hasMembers || (hasMembers = true)) {
								value = lex();
								// A closing curly brace marks the end of the object literal.
								if (value == "}") {
									break;
								}
								// If the object literal contains members, the current token
								// should be a comma separator.
								if (hasMembers) {
									if (value == ",") {
										value = lex();
										if (value == "}") {
											// Unexpected trailing `,` in object literal.
											abort();
										}
									} else {
										// A `,` must separate each object member.
										abort();
									}
								}
								// Leading commas are not permitted, object property names must be
								// double-quoted strings, and a `:` must separate each property
								// name and value.
								if (value == "," || typeof value != "string" || (charIndexBuggy ? value.charAt(0) : value[0]) != "@" || lex() != ":") {
									abort();
								}
								results[value.slice(1)] = get(lex());
							}
							return results;
						}
						// Unexpected token encountered.
						abort();
					}
					return value;
				};

				// Internal: Updates a traversed object member.
				var update = function(source, property, callback) {
					var element = walk(source, property, callback);
					if (element === undef) {
						delete source[property];
					} else {
						source[property] = element;
					}
				};

				// Internal: Recursively traverses a parsed JSON object, invoking the
				// `callback` function for each value. This is an implementation of the
				// `Walk(holder, name)` operation defined in ES 5.1 section 15.12.2.
				var walk = function (source, property, callback) {
					var value = source[property], length;
					if (typeof value == "object" && value) {
						// `forEach` can't be used to traverse an array in Opera <= 8.54
						// because its `Object#hasOwnProperty` implementation returns `false`
						// for array indices (e.g., `![1, 2, 3].hasOwnProperty("0")`).
						if (getClass.call(value) == arrayClass) {
							for (length = value.length; length--;) {
								update(value, length, callback);
							}
						} else {
							forEach(value, function (property) {
								update(value, property, callback);
							});
						}
					}
					return callback.call(source, property, value);
				};

				// Public: `JSON.parse`. See ES 5.1 section 15.12.2.
				JSON3.parse = function (source, callback) {
					var result, value;
					Index = 0;
					Source = "" + source;
					result = get(lex());
					// If a JSON string contains multiple tokens, it is invalid.
					if (lex() != "$") {
						abort();
					}
					// Reset the parser state.
					Index = Source = null;
					return callback && getClass.call(callback) == functionClass ? walk((value = {}, value[""] = result, value), "", callback) : result;
				};
			}
		}

		// Export for asynchronous module loaders.
		if (isLoader) {
			define(function () {
				return JSON3;
			});
		}
	}(this));

},{}],14:[function(require,module,exports){
	/*!
	 * The buffer module from node.js, for the browser.
	 *
	 * @author   Feross Aboukhadijeh <feross@feross.org> <http://feross.org>
	 * @license  MIT
	 */

	var base64 = require('base64-js')
	var ieee754 = require('ieee754')
	var isArray = require('is-array')

	exports.Buffer = Buffer
	exports.SlowBuffer = SlowBuffer
	exports.INSPECT_MAX_BYTES = 50
	Buffer.poolSize = 8192 // not used by this implementation

	var kMaxLength = 0x3fffffff
	var rootParent = {}

	/**
	 * If `Buffer.TYPED_ARRAY_SUPPORT`:
	 *   === true    Use Uint8Array implementation (fastest)
	 *   === false   Use Object implementation (most compatible, even IE6)
	 *
	 * Browsers that support typed arrays are IE 10+, Firefox 4+, Chrome 7+, Safari 5.1+,
	 * Opera 11.6+, iOS 4.2+.
	 *
	 * Note:
	 *
	 * - Implementation must support adding new properties to `Uint8Array` instances.
	 *   Firefox 4-29 lacked support, fixed in Firefox 30+.
	 *   See: https://bugzilla.mozilla.org/show_bug.cgi?id=695438.
	 *
	 *  - Chrome 9-10 is missing the `TypedArray.prototype.subarray` function.
	 *
	 *  - IE10 has a broken `TypedArray.prototype.subarray` function which returns arrays of
	 *    incorrect length in some situations.
	 *
	 * We detect these buggy browsers and set `Buffer.TYPED_ARRAY_SUPPORT` to `false` so they will
	 * get the Object implementation, which is slower but will work correctly.
	 */
	Buffer.TYPED_ARRAY_SUPPORT = (function () {
		try {
			var buf = new ArrayBuffer(0)
			var arr = new Uint8Array(buf)
			arr.foo = function () { return 42 }
			return arr.foo() === 42 && // typed array instances can be augmented
				typeof arr.subarray === 'function' && // chrome 9-10 lack `subarray`
				new Uint8Array(1).subarray(1, 1).byteLength === 0 // ie10 has broken `subarray`
		} catch (e) {
			return false
		}
	})()

	/**
	 * Class: Buffer
	 * =============
	 *
	 * The Buffer constructor returns instances of `Uint8Array` that are augmented
	 * with function properties for all the node `Buffer` API functions. We use
	 * `Uint8Array` so that square bracket notation works as expected -- it returns
	 * a single octet.
	 *
	 * By augmenting the instances, we can avoid modifying the `Uint8Array`
	 * prototype.
	 */
	function Buffer (arg) {
		if (!(this instanceof Buffer)) {
			// Avoid going through an ArgumentsAdaptorTrampoline in the common case.
			if (arguments.length > 1) return new Buffer(arg, arguments[1])
			return new Buffer(arg)
		}

		this.length = 0
		this.parent = undefined

		// Common case.
		if (typeof arg === 'number') {
			return fromNumber(this, arg)
		}

		// Slightly less common case.
		if (typeof arg === 'string') {
			return fromString(this, arg, arguments.length > 1 ? arguments[1] : 'utf8')
		}

		// Unusual.
		return fromObject(this, arg)
	}

	function fromNumber (that, length) {
		that = allocate(that, length < 0 ? 0 : checked(length) | 0)
		if (!Buffer.TYPED_ARRAY_SUPPORT) {
			for (var i = 0; i < length; i++) {
				that[i] = 0
			}
		}
		return that
	}

	function fromString (that, string, encoding) {
		if (typeof encoding !== 'string' || encoding === '') encoding = 'utf8'

		// Assumption: byteLength() return value is always < kMaxLength.
		var length = byteLength(string, encoding) | 0
		that = allocate(that, length)

		that.write(string, encoding)
		return that
	}

	function fromObject (that, object) {
		if (Buffer.isBuffer(object)) return fromBuffer(that, object)

		if (isArray(object)) return fromArray(that, object)

		if (object == null) {
			throw new TypeError('must start with number, buffer, array or string')
		}

		if (typeof ArrayBuffer !== 'undefined' && object.buffer instanceof ArrayBuffer) {
			return fromTypedArray(that, object)
		}

		if (object.length) return fromArrayLike(that, object)

		return fromJsonObject(that, object)
	}

	function fromBuffer (that, buffer) {
		var length = checked(buffer.length) | 0
		that = allocate(that, length)
		buffer.copy(that, 0, 0, length)
		return that
	}

	function fromArray (that, array) {
		var length = checked(array.length) | 0
		that = allocate(that, length)
		for (var i = 0; i < length; i += 1) {
			that[i] = array[i] & 255
		}
		return that
	}

// Duplicate of fromArray() to keep fromArray() monomorphic.
	function fromTypedArray (that, array) {
		var length = checked(array.length) | 0
		that = allocate(that, length)
		// Truncating the elements is probably not what people expect from typed
		// arrays with BYTES_PER_ELEMENT > 1 but it's compatible with the behavior
		// of the old Buffer constructor.
		for (var i = 0; i < length; i += 1) {
			that[i] = array[i] & 255
		}
		return that
	}

	function fromArrayLike (that, array) {
		var length = checked(array.length) | 0
		that = allocate(that, length)
		for (var i = 0; i < length; i += 1) {
			that[i] = array[i] & 255
		}
		return that
	}

// Deserialize { type: 'Buffer', data: [1,2,3,...] } into a Buffer object.
// Returns a zero-length buffer for inputs that don't conform to the spec.
	function fromJsonObject (that, object) {
		var array
		var length = 0

		if (object.type === 'Buffer' && isArray(object.data)) {
			array = object.data
			length = checked(array.length) | 0
		}
		that = allocate(that, length)

		for (var i = 0; i < length; i += 1) {
			that[i] = array[i] & 255
		}
		return that
	}

	function allocate (that, length) {
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			// Return an augmented `Uint8Array` instance, for best performance
			that = Buffer._augment(new Uint8Array(length))
		} else {
			// Fallback: Return an object instance of the Buffer class
			that.length = length
			that._isBuffer = true
		}

		var fromPool = length !== 0 && length <= Buffer.poolSize >>> 1
		if (fromPool) that.parent = rootParent

		return that
	}

	function checked (length) {
		// Note: cannot use `length < kMaxLength` here because that fails when
		// length is NaN (which is otherwise coerced to zero.)
		if (length >= kMaxLength) {
			throw new RangeError('Attempt to allocate Buffer larger than maximum ' +
				'size: 0x' + kMaxLength.toString(16) + ' bytes')
		}
		return length | 0
	}

	function SlowBuffer (subject, encoding) {
		if (!(this instanceof SlowBuffer)) return new SlowBuffer(subject, encoding)

		var buf = new Buffer(subject, encoding)
		delete buf.parent
		return buf
	}

	Buffer.isBuffer = function isBuffer (b) {
		return !!(b != null && b._isBuffer)
	}

	Buffer.compare = function compare (a, b) {
		if (!Buffer.isBuffer(a) || !Buffer.isBuffer(b)) {
			throw new TypeError('Arguments must be Buffers')
		}

		if (a === b) return 0

		var x = a.length
		var y = b.length

		var i = 0
		var len = Math.min(x, y)
		while (i < len) {
			if (a[i] !== b[i]) break

			++i
		}

		if (i !== len) {
			x = a[i]
			y = b[i]
		}

		if (x < y) return -1
		if (y < x) return 1
		return 0
	}

	Buffer.isEncoding = function isEncoding (encoding) {
		switch (String(encoding).toLowerCase()) {
			case 'hex':
			case 'utf8':
			case 'utf-8':
			case 'ascii':
			case 'binary':
			case 'base64':
			case 'raw':
			case 'ucs2':
			case 'ucs-2':
			case 'utf16le':
			case 'utf-16le':
				return true
			default:
				return false
		}
	}

	Buffer.concat = function concat (list, length) {
		if (!isArray(list)) throw new TypeError('list argument must be an Array of Buffers.')

		if (list.length === 0) {
			return new Buffer(0)
		} else if (list.length === 1) {
			return list[0]
		}

		var i
		if (length === undefined) {
			length = 0
			for (i = 0; i < list.length; i++) {
				length += list[i].length
			}
		}

		var buf = new Buffer(length)
		var pos = 0
		for (i = 0; i < list.length; i++) {
			var item = list[i]
			item.copy(buf, pos)
			pos += item.length
		}
		return buf
	}

	function byteLength (string, encoding) {
		if (typeof string !== 'string') string = String(string)

		if (string.length === 0) return 0

		switch (encoding || 'utf8') {
			case 'ascii':
			case 'binary':
			case 'raw':
				return string.length
			case 'ucs2':
			case 'ucs-2':
			case 'utf16le':
			case 'utf-16le':
				return string.length * 2
			case 'hex':
				return string.length >>> 1
			case 'utf8':
			case 'utf-8':
				return utf8ToBytes(string).length
			case 'base64':
				return base64ToBytes(string).length
			default:
				return string.length
		}
	}
	Buffer.byteLength = byteLength

// pre-set for values that may exist in the future
	Buffer.prototype.length = undefined
	Buffer.prototype.parent = undefined

// toString(encoding, start=0, end=buffer.length)
	Buffer.prototype.toString = function toString (encoding, start, end) {
		var loweredCase = false

		start = start | 0
		end = end === undefined || end === Infinity ? this.length : end | 0

		if (!encoding) encoding = 'utf8'
		if (start < 0) start = 0
		if (end > this.length) end = this.length
		if (end <= start) return ''

		while (true) {
			switch (encoding) {
				case 'hex':
					return hexSlice(this, start, end)

				case 'utf8':
				case 'utf-8':
					return utf8Slice(this, start, end)

				case 'ascii':
					return asciiSlice(this, start, end)

				case 'binary':
					return binarySlice(this, start, end)

				case 'base64':
					return base64Slice(this, start, end)

				case 'ucs2':
				case 'ucs-2':
				case 'utf16le':
				case 'utf-16le':
					return utf16leSlice(this, start, end)

				default:
					if (loweredCase) throw new TypeError('Unknown encoding: ' + encoding)
					encoding = (encoding + '').toLowerCase()
					loweredCase = true
			}
		}
	}

	Buffer.prototype.equals = function equals (b) {
		if (!Buffer.isBuffer(b)) throw new TypeError('Argument must be a Buffer')
		if (this === b) return true
		return Buffer.compare(this, b) === 0
	}

	Buffer.prototype.inspect = function inspect () {
		var str = ''
		var max = exports.INSPECT_MAX_BYTES
		if (this.length > 0) {
			str = this.toString('hex', 0, max).match(/.{2}/g).join(' ')
			if (this.length > max) str += ' ... '
		}
		return '<Buffer ' + str + '>'
	}

	Buffer.prototype.compare = function compare (b) {
		if (!Buffer.isBuffer(b)) throw new TypeError('Argument must be a Buffer')
		if (this === b) return 0
		return Buffer.compare(this, b)
	}

	Buffer.prototype.indexOf = function indexOf (val, byteOffset) {
		if (byteOffset > 0x7fffffff) byteOffset = 0x7fffffff
		else if (byteOffset < -0x80000000) byteOffset = -0x80000000
		byteOffset >>= 0

		if (this.length === 0) return -1
		if (byteOffset >= this.length) return -1

		// Negative offsets start from the end of the buffer
		if (byteOffset < 0) byteOffset = Math.max(this.length + byteOffset, 0)

		if (typeof val === 'string') {
			if (val.length === 0) return -1 // special case: looking for empty string always fails
			return String.prototype.indexOf.call(this, val, byteOffset)
		}
		if (Buffer.isBuffer(val)) {
			return arrayIndexOf(this, val, byteOffset)
		}
		if (typeof val === 'number') {
			if (Buffer.TYPED_ARRAY_SUPPORT && Uint8Array.prototype.indexOf === 'function') {
				return Uint8Array.prototype.indexOf.call(this, val, byteOffset)
			}
			return arrayIndexOf(this, [ val ], byteOffset)
		}

		function arrayIndexOf (arr, val, byteOffset) {
			var foundIndex = -1
			for (var i = 0; byteOffset + i < arr.length; i++) {
				if (arr[byteOffset + i] === val[foundIndex === -1 ? 0 : i - foundIndex]) {
					if (foundIndex === -1) foundIndex = i
					if (i - foundIndex + 1 === val.length) return byteOffset + foundIndex
				} else {
					foundIndex = -1
				}
			}
			return -1
		}

		throw new TypeError('val must be string, number or Buffer')
	}

// `get` will be removed in Node 0.13+
	Buffer.prototype.get = function get (offset) {
		console.log('.get() is deprecated. Access using array indexes instead.')
		return this.readUInt8(offset)
	}

// `set` will be removed in Node 0.13+
	Buffer.prototype.set = function set (v, offset) {
		console.log('.set() is deprecated. Access using array indexes instead.')
		return this.writeUInt8(v, offset)
	}

	function hexWrite (buf, string, offset, length) {
		offset = Number(offset) || 0
		var remaining = buf.length - offset
		if (!length) {
			length = remaining
		} else {
			length = Number(length)
			if (length > remaining) {
				length = remaining
			}
		}

		// must be an even number of digits
		var strLen = string.length
		if (strLen % 2 !== 0) throw new Error('Invalid hex string')

		if (length > strLen / 2) {
			length = strLen / 2
		}
		for (var i = 0; i < length; i++) {
			var parsed = parseInt(string.substr(i * 2, 2), 16)
			if (isNaN(parsed)) throw new Error('Invalid hex string')
			buf[offset + i] = parsed
		}
		return i
	}

	function utf8Write (buf, string, offset, length) {
		return blitBuffer(utf8ToBytes(string, buf.length - offset), buf, offset, length)
	}

	function asciiWrite (buf, string, offset, length) {
		return blitBuffer(asciiToBytes(string), buf, offset, length)
	}

	function binaryWrite (buf, string, offset, length) {
		return asciiWrite(buf, string, offset, length)
	}

	function base64Write (buf, string, offset, length) {
		return blitBuffer(base64ToBytes(string), buf, offset, length)
	}

	function ucs2Write (buf, string, offset, length) {
		return blitBuffer(utf16leToBytes(string, buf.length - offset), buf, offset, length)
	}

	Buffer.prototype.write = function write (string, offset, length, encoding) {
		// Buffer#write(string)
		if (offset === undefined) {
			encoding = 'utf8'
			length = this.length
			offset = 0
			// Buffer#write(string, encoding)
		} else if (length === undefined && typeof offset === 'string') {
			encoding = offset
			length = this.length
			offset = 0
			// Buffer#write(string, offset[, length][, encoding])
		} else if (isFinite(offset)) {
			offset = offset | 0
			if (isFinite(length)) {
				length = length | 0
				if (encoding === undefined) encoding = 'utf8'
			} else {
				encoding = length
				length = undefined
			}
			// legacy write(string, encoding, offset, length) - remove in v0.13
		} else {
			var swap = encoding
			encoding = offset
			offset = length | 0
			length = swap
		}

		var remaining = this.length - offset
		if (length === undefined || length > remaining) length = remaining

		if ((string.length > 0 && (length < 0 || offset < 0)) || offset > this.length) {
			throw new RangeError('attempt to write outside buffer bounds')
		}

		if (!encoding) encoding = 'utf8'

		var loweredCase = false
		for (;;) {
			switch (encoding) {
				case 'hex':
					return hexWrite(this, string, offset, length)

				case 'utf8':
				case 'utf-8':
					return utf8Write(this, string, offset, length)

				case 'ascii':
					return asciiWrite(this, string, offset, length)

				case 'binary':
					return binaryWrite(this, string, offset, length)

				case 'base64':
					// Warning: maxLength not taken into account in base64Write
					return base64Write(this, string, offset, length)

				case 'ucs2':
				case 'ucs-2':
				case 'utf16le':
				case 'utf-16le':
					return ucs2Write(this, string, offset, length)

				default:
					if (loweredCase) throw new TypeError('Unknown encoding: ' + encoding)
					encoding = ('' + encoding).toLowerCase()
					loweredCase = true
			}
		}
	}

	Buffer.prototype.toJSON = function toJSON () {
		return {
			type: 'Buffer',
			data: Array.prototype.slice.call(this._arr || this, 0)
		}
	}

	function base64Slice (buf, start, end) {
		if (start === 0 && end === buf.length) {
			return base64.fromByteArray(buf)
		} else {
			return base64.fromByteArray(buf.slice(start, end))
		}
	}

	function utf8Slice (buf, start, end) {
		var res = ''
		var tmp = ''
		end = Math.min(buf.length, end)

		for (var i = start; i < end; i++) {
			if (buf[i] <= 0x7F) {
				res += decodeUtf8Char(tmp) + String.fromCharCode(buf[i])
				tmp = ''
			} else {
				tmp += '%' + buf[i].toString(16)
			}
		}

		return res + decodeUtf8Char(tmp)
	}

	function asciiSlice (buf, start, end) {
		var ret = ''
		end = Math.min(buf.length, end)

		for (var i = start; i < end; i++) {
			ret += String.fromCharCode(buf[i] & 0x7F)
		}
		return ret
	}

	function binarySlice (buf, start, end) {
		var ret = ''
		end = Math.min(buf.length, end)

		for (var i = start; i < end; i++) {
			ret += String.fromCharCode(buf[i])
		}
		return ret
	}

	function hexSlice (buf, start, end) {
		var len = buf.length

		if (!start || start < 0) start = 0
		if (!end || end < 0 || end > len) end = len

		var out = ''
		for (var i = start; i < end; i++) {
			out += toHex(buf[i])
		}
		return out
	}

	function utf16leSlice (buf, start, end) {
		var bytes = buf.slice(start, end)
		var res = ''
		for (var i = 0; i < bytes.length; i += 2) {
			res += String.fromCharCode(bytes[i] + bytes[i + 1] * 256)
		}
		return res
	}

	Buffer.prototype.slice = function slice (start, end) {
		var len = this.length
		start = ~~start
		end = end === undefined ? len : ~~end

		if (start < 0) {
			start += len
			if (start < 0) start = 0
		} else if (start > len) {
			start = len
		}

		if (end < 0) {
			end += len
			if (end < 0) end = 0
		} else if (end > len) {
			end = len
		}

		if (end < start) end = start

		var newBuf
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			newBuf = Buffer._augment(this.subarray(start, end))
		} else {
			var sliceLen = end - start
			newBuf = new Buffer(sliceLen, undefined)
			for (var i = 0; i < sliceLen; i++) {
				newBuf[i] = this[i + start]
			}
		}

		if (newBuf.length) newBuf.parent = this.parent || this

		return newBuf
	}

	/*
	 * Need to make sure that buffer isn't trying to write out of bounds.
	 */
	function checkOffset (offset, ext, length) {
		if ((offset % 1) !== 0 || offset < 0) throw new RangeError('offset is not uint')
		if (offset + ext > length) throw new RangeError('Trying to access beyond buffer length')
	}

	Buffer.prototype.readUIntLE = function readUIntLE (offset, byteLength, noAssert) {
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) checkOffset(offset, byteLength, this.length)

		var val = this[offset]
		var mul = 1
		var i = 0
		while (++i < byteLength && (mul *= 0x100)) {
			val += this[offset + i] * mul
		}

		return val
	}

	Buffer.prototype.readUIntBE = function readUIntBE (offset, byteLength, noAssert) {
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) {
			checkOffset(offset, byteLength, this.length)
		}

		var val = this[offset + --byteLength]
		var mul = 1
		while (byteLength > 0 && (mul *= 0x100)) {
			val += this[offset + --byteLength] * mul
		}

		return val
	}

	Buffer.prototype.readUInt8 = function readUInt8 (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 1, this.length)
		return this[offset]
	}

	Buffer.prototype.readUInt16LE = function readUInt16LE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 2, this.length)
		return this[offset] | (this[offset + 1] << 8)
	}

	Buffer.prototype.readUInt16BE = function readUInt16BE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 2, this.length)
		return (this[offset] << 8) | this[offset + 1]
	}

	Buffer.prototype.readUInt32LE = function readUInt32LE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)

		return ((this[offset]) |
			(this[offset + 1] << 8) |
			(this[offset + 2] << 16)) +
			(this[offset + 3] * 0x1000000)
	}

	Buffer.prototype.readUInt32BE = function readUInt32BE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)

		return (this[offset] * 0x1000000) +
			((this[offset + 1] << 16) |
			(this[offset + 2] << 8) |
			this[offset + 3])
	}

	Buffer.prototype.readIntLE = function readIntLE (offset, byteLength, noAssert) {
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) checkOffset(offset, byteLength, this.length)

		var val = this[offset]
		var mul = 1
		var i = 0
		while (++i < byteLength && (mul *= 0x100)) {
			val += this[offset + i] * mul
		}
		mul *= 0x80

		if (val >= mul) val -= Math.pow(2, 8 * byteLength)

		return val
	}

	Buffer.prototype.readIntBE = function readIntBE (offset, byteLength, noAssert) {
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) checkOffset(offset, byteLength, this.length)

		var i = byteLength
		var mul = 1
		var val = this[offset + --i]
		while (i > 0 && (mul *= 0x100)) {
			val += this[offset + --i] * mul
		}
		mul *= 0x80

		if (val >= mul) val -= Math.pow(2, 8 * byteLength)

		return val
	}

	Buffer.prototype.readInt8 = function readInt8 (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 1, this.length)
		if (!(this[offset] & 0x80)) return (this[offset])
		return ((0xff - this[offset] + 1) * -1)
	}

	Buffer.prototype.readInt16LE = function readInt16LE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 2, this.length)
		var val = this[offset] | (this[offset + 1] << 8)
		return (val & 0x8000) ? val | 0xFFFF0000 : val
	}

	Buffer.prototype.readInt16BE = function readInt16BE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 2, this.length)
		var val = this[offset + 1] | (this[offset] << 8)
		return (val & 0x8000) ? val | 0xFFFF0000 : val
	}

	Buffer.prototype.readInt32LE = function readInt32LE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)

		return (this[offset]) |
			(this[offset + 1] << 8) |
			(this[offset + 2] << 16) |
			(this[offset + 3] << 24)
	}

	Buffer.prototype.readInt32BE = function readInt32BE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)

		return (this[offset] << 24) |
			(this[offset + 1] << 16) |
			(this[offset + 2] << 8) |
			(this[offset + 3])
	}

	Buffer.prototype.readFloatLE = function readFloatLE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)
		return ieee754.read(this, offset, true, 23, 4)
	}

	Buffer.prototype.readFloatBE = function readFloatBE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 4, this.length)
		return ieee754.read(this, offset, false, 23, 4)
	}

	Buffer.prototype.readDoubleLE = function readDoubleLE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 8, this.length)
		return ieee754.read(this, offset, true, 52, 8)
	}

	Buffer.prototype.readDoubleBE = function readDoubleBE (offset, noAssert) {
		if (!noAssert) checkOffset(offset, 8, this.length)
		return ieee754.read(this, offset, false, 52, 8)
	}

	function checkInt (buf, value, offset, ext, max, min) {
		if (!Buffer.isBuffer(buf)) throw new TypeError('buffer must be a Buffer instance')
		if (value > max || value < min) throw new RangeError('value is out of bounds')
		if (offset + ext > buf.length) throw new RangeError('index out of range')
	}

	Buffer.prototype.writeUIntLE = function writeUIntLE (value, offset, byteLength, noAssert) {
		value = +value
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) checkInt(this, value, offset, byteLength, Math.pow(2, 8 * byteLength), 0)

		var mul = 1
		var i = 0
		this[offset] = value & 0xFF
		while (++i < byteLength && (mul *= 0x100)) {
			this[offset + i] = (value / mul) & 0xFF
		}

		return offset + byteLength
	}

	Buffer.prototype.writeUIntBE = function writeUIntBE (value, offset, byteLength, noAssert) {
		value = +value
		offset = offset | 0
		byteLength = byteLength | 0
		if (!noAssert) checkInt(this, value, offset, byteLength, Math.pow(2, 8 * byteLength), 0)

		var i = byteLength - 1
		var mul = 1
		this[offset + i] = value & 0xFF
		while (--i >= 0 && (mul *= 0x100)) {
			this[offset + i] = (value / mul) & 0xFF
		}

		return offset + byteLength
	}

	Buffer.prototype.writeUInt8 = function writeUInt8 (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 1, 0xff, 0)
		if (!Buffer.TYPED_ARRAY_SUPPORT) value = Math.floor(value)
		this[offset] = value
		return offset + 1
	}

	function objectWriteUInt16 (buf, value, offset, littleEndian) {
		if (value < 0) value = 0xffff + value + 1
		for (var i = 0, j = Math.min(buf.length - offset, 2); i < j; i++) {
			buf[offset + i] = (value & (0xff << (8 * (littleEndian ? i : 1 - i)))) >>>
				(littleEndian ? i : 1 - i) * 8
		}
	}

	Buffer.prototype.writeUInt16LE = function writeUInt16LE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 2, 0xffff, 0)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = value
			this[offset + 1] = (value >>> 8)
		} else {
			objectWriteUInt16(this, value, offset, true)
		}
		return offset + 2
	}

	Buffer.prototype.writeUInt16BE = function writeUInt16BE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 2, 0xffff, 0)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = (value >>> 8)
			this[offset + 1] = value
		} else {
			objectWriteUInt16(this, value, offset, false)
		}
		return offset + 2
	}

	function objectWriteUInt32 (buf, value, offset, littleEndian) {
		if (value < 0) value = 0xffffffff + value + 1
		for (var i = 0, j = Math.min(buf.length - offset, 4); i < j; i++) {
			buf[offset + i] = (value >>> (littleEndian ? i : 3 - i) * 8) & 0xff
		}
	}

	Buffer.prototype.writeUInt32LE = function writeUInt32LE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 4, 0xffffffff, 0)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset + 3] = (value >>> 24)
			this[offset + 2] = (value >>> 16)
			this[offset + 1] = (value >>> 8)
			this[offset] = value
		} else {
			objectWriteUInt32(this, value, offset, true)
		}
		return offset + 4
	}

	Buffer.prototype.writeUInt32BE = function writeUInt32BE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 4, 0xffffffff, 0)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = (value >>> 24)
			this[offset + 1] = (value >>> 16)
			this[offset + 2] = (value >>> 8)
			this[offset + 3] = value
		} else {
			objectWriteUInt32(this, value, offset, false)
		}
		return offset + 4
	}

	Buffer.prototype.writeIntLE = function writeIntLE (value, offset, byteLength, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) {
			var limit = Math.pow(2, 8 * byteLength - 1)

			checkInt(this, value, offset, byteLength, limit - 1, -limit)
		}

		var i = 0
		var mul = 1
		var sub = value < 0 ? 1 : 0
		this[offset] = value & 0xFF
		while (++i < byteLength && (mul *= 0x100)) {
			this[offset + i] = ((value / mul) >> 0) - sub & 0xFF
		}

		return offset + byteLength
	}

	Buffer.prototype.writeIntBE = function writeIntBE (value, offset, byteLength, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) {
			var limit = Math.pow(2, 8 * byteLength - 1)

			checkInt(this, value, offset, byteLength, limit - 1, -limit)
		}

		var i = byteLength - 1
		var mul = 1
		var sub = value < 0 ? 1 : 0
		this[offset + i] = value & 0xFF
		while (--i >= 0 && (mul *= 0x100)) {
			this[offset + i] = ((value / mul) >> 0) - sub & 0xFF
		}

		return offset + byteLength
	}

	Buffer.prototype.writeInt8 = function writeInt8 (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 1, 0x7f, -0x80)
		if (!Buffer.TYPED_ARRAY_SUPPORT) value = Math.floor(value)
		if (value < 0) value = 0xff + value + 1
		this[offset] = value
		return offset + 1
	}

	Buffer.prototype.writeInt16LE = function writeInt16LE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 2, 0x7fff, -0x8000)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = value
			this[offset + 1] = (value >>> 8)
		} else {
			objectWriteUInt16(this, value, offset, true)
		}
		return offset + 2
	}

	Buffer.prototype.writeInt16BE = function writeInt16BE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 2, 0x7fff, -0x8000)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = (value >>> 8)
			this[offset + 1] = value
		} else {
			objectWriteUInt16(this, value, offset, false)
		}
		return offset + 2
	}

	Buffer.prototype.writeInt32LE = function writeInt32LE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 4, 0x7fffffff, -0x80000000)
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = value
			this[offset + 1] = (value >>> 8)
			this[offset + 2] = (value >>> 16)
			this[offset + 3] = (value >>> 24)
		} else {
			objectWriteUInt32(this, value, offset, true)
		}
		return offset + 4
	}

	Buffer.prototype.writeInt32BE = function writeInt32BE (value, offset, noAssert) {
		value = +value
		offset = offset | 0
		if (!noAssert) checkInt(this, value, offset, 4, 0x7fffffff, -0x80000000)
		if (value < 0) value = 0xffffffff + value + 1
		if (Buffer.TYPED_ARRAY_SUPPORT) {
			this[offset] = (value >>> 24)
			this[offset + 1] = (value >>> 16)
			this[offset + 2] = (value >>> 8)
			this[offset + 3] = value
		} else {
			objectWriteUInt32(this, value, offset, false)
		}
		return offset + 4
	}

	function checkIEEE754 (buf, value, offset, ext, max, min) {
		if (value > max || value < min) throw new RangeError('value is out of bounds')
		if (offset + ext > buf.length) throw new RangeError('index out of range')
		if (offset < 0) throw new RangeError('index out of range')
	}

	function writeFloat (buf, value, offset, littleEndian, noAssert) {
		if (!noAssert) {
			checkIEEE754(buf, value, offset, 4, 3.4028234663852886e+38, -3.4028234663852886e+38)
		}
		ieee754.write(buf, value, offset, littleEndian, 23, 4)
		return offset + 4
	}

	Buffer.prototype.writeFloatLE = function writeFloatLE (value, offset, noAssert) {
		return writeFloat(this, value, offset, true, noAssert)
	}

	Buffer.prototype.writeFloatBE = function writeFloatBE (value, offset, noAssert) {
		return writeFloat(this, value, offset, false, noAssert)
	}

	function writeDouble (buf, value, offset, littleEndian, noAssert) {
		if (!noAssert) {
			checkIEEE754(buf, value, offset, 8, 1.7976931348623157E+308, -1.7976931348623157E+308)
		}
		ieee754.write(buf, value, offset, littleEndian, 52, 8)
		return offset + 8
	}

	Buffer.prototype.writeDoubleLE = function writeDoubleLE (value, offset, noAssert) {
		return writeDouble(this, value, offset, true, noAssert)
	}

	Buffer.prototype.writeDoubleBE = function writeDoubleBE (value, offset, noAssert) {
		return writeDouble(this, value, offset, false, noAssert)
	}

// copy(targetBuffer, targetStart=0, sourceStart=0, sourceEnd=buffer.length)
	Buffer.prototype.copy = function copy (target, targetStart, start, end) {
		if (!start) start = 0
		if (!end && end !== 0) end = this.length
		if (targetStart >= target.length) targetStart = target.length
		if (!targetStart) targetStart = 0
		if (end > 0 && end < start) end = start

		// Copy 0 bytes; we're done
		if (end === start) return 0
		if (target.length === 0 || this.length === 0) return 0

		// Fatal error conditions
		if (targetStart < 0) {
			throw new RangeError('targetStart out of bounds')
		}
		if (start < 0 || start >= this.length) throw new RangeError('sourceStart out of bounds')
		if (end < 0) throw new RangeError('sourceEnd out of bounds')

		// Are we oob?
		if (end > this.length) end = this.length
		if (target.length - targetStart < end - start) {
			end = target.length - targetStart + start
		}

		var len = end - start

		if (len < 1000 || !Buffer.TYPED_ARRAY_SUPPORT) {
			for (var i = 0; i < len; i++) {
				target[i + targetStart] = this[i + start]
			}
		} else {
			target._set(this.subarray(start, start + len), targetStart)
		}

		return len
	}

// fill(value, start=0, end=buffer.length)
	Buffer.prototype.fill = function fill (value, start, end) {
		if (!value) value = 0
		if (!start) start = 0
		if (!end) end = this.length

		if (end < start) throw new RangeError('end < start')

		// Fill 0 bytes; we're done
		if (end === start) return
		if (this.length === 0) return

		if (start < 0 || start >= this.length) throw new RangeError('start out of bounds')
		if (end < 0 || end > this.length) throw new RangeError('end out of bounds')

		var i
		if (typeof value === 'number') {
			for (i = start; i < end; i++) {
				this[i] = value
			}
		} else {
			var bytes = utf8ToBytes(value.toString())
			var len = bytes.length
			for (i = start; i < end; i++) {
				this[i] = bytes[i % len]
			}
		}

		return this
	}

	/**
	 * Creates a new `ArrayBuffer` with the *copied* memory of the buffer instance.
	 * Added in Node 0.12. Only available in browsers that support ArrayBuffer.
	 */
	Buffer.prototype.toArrayBuffer = function toArrayBuffer () {
		if (typeof Uint8Array !== 'undefined') {
			if (Buffer.TYPED_ARRAY_SUPPORT) {
				return (new Buffer(this)).buffer
			} else {
				var buf = new Uint8Array(this.length)
				for (var i = 0, len = buf.length; i < len; i += 1) {
					buf[i] = this[i]
				}
				return buf.buffer
			}
		} else {
			throw new TypeError('Buffer.toArrayBuffer not supported in this browser')
		}
	}

// HELPER FUNCTIONS
// ================

	var BP = Buffer.prototype

	/**
	 * Augment a Uint8Array *instance* (not the Uint8Array class!) with Buffer methods
	 */
	Buffer._augment = function _augment (arr) {
		arr.constructor = Buffer
		arr._isBuffer = true

		// save reference to original Uint8Array set method before overwriting
		arr._set = arr.set

		// deprecated, will be removed in node 0.13+
		arr.get = BP.get
		arr.set = BP.set

		arr.write = BP.write
		arr.toString = BP.toString
		arr.toLocaleString = BP.toString
		arr.toJSON = BP.toJSON
		arr.equals = BP.equals
		arr.compare = BP.compare
		arr.indexOf = BP.indexOf
		arr.copy = BP.copy
		arr.slice = BP.slice
		arr.readUIntLE = BP.readUIntLE
		arr.readUIntBE = BP.readUIntBE
		arr.readUInt8 = BP.readUInt8
		arr.readUInt16LE = BP.readUInt16LE
		arr.readUInt16BE = BP.readUInt16BE
		arr.readUInt32LE = BP.readUInt32LE
		arr.readUInt32BE = BP.readUInt32BE
		arr.readIntLE = BP.readIntLE
		arr.readIntBE = BP.readIntBE
		arr.readInt8 = BP.readInt8
		arr.readInt16LE = BP.readInt16LE
		arr.readInt16BE = BP.readInt16BE
		arr.readInt32LE = BP.readInt32LE
		arr.readInt32BE = BP.readInt32BE
		arr.readFloatLE = BP.readFloatLE
		arr.readFloatBE = BP.readFloatBE
		arr.readDoubleLE = BP.readDoubleLE
		arr.readDoubleBE = BP.readDoubleBE
		arr.writeUInt8 = BP.writeUInt8
		arr.writeUIntLE = BP.writeUIntLE
		arr.writeUIntBE = BP.writeUIntBE
		arr.writeUInt16LE = BP.writeUInt16LE
		arr.writeUInt16BE = BP.writeUInt16BE
		arr.writeUInt32LE = BP.writeUInt32LE
		arr.writeUInt32BE = BP.writeUInt32BE
		arr.writeIntLE = BP.writeIntLE
		arr.writeIntBE = BP.writeIntBE
		arr.writeInt8 = BP.writeInt8
		arr.writeInt16LE = BP.writeInt16LE
		arr.writeInt16BE = BP.writeInt16BE
		arr.writeInt32LE = BP.writeInt32LE
		arr.writeInt32BE = BP.writeInt32BE
		arr.writeFloatLE = BP.writeFloatLE
		arr.writeFloatBE = BP.writeFloatBE
		arr.writeDoubleLE = BP.writeDoubleLE
		arr.writeDoubleBE = BP.writeDoubleBE
		arr.fill = BP.fill
		arr.inspect = BP.inspect
		arr.toArrayBuffer = BP.toArrayBuffer

		return arr
	}

	var INVALID_BASE64_RE = /[^+\/0-9A-z\-]/g

	function base64clean (str) {
		// Node strips out invalid characters like \n and \t from the string, base64-js does not
		str = stringtrim(str).replace(INVALID_BASE64_RE, '')
		// Node converts strings with length < 2 to ''
		if (str.length < 2) return ''
		// Node allows for non-padded base64 strings (missing trailing ===), base64-js does not
		while (str.length % 4 !== 0) {
			str = str + '='
		}
		return str
	}

	function stringtrim (str) {
		if (str.trim) return str.trim()
		return str.replace(/^\s+|\s+$/g, '')
	}

	function toHex (n) {
		if (n < 16) return '0' + n.toString(16)
		return n.toString(16)
	}

	function utf8ToBytes (string, units) {
		units = units || Infinity
		var codePoint
		var length = string.length
		var leadSurrogate = null
		var bytes = []
		var i = 0

		for (; i < length; i++) {
			codePoint = string.charCodeAt(i)

			// is surrogate component
			if (codePoint > 0xD7FF && codePoint < 0xE000) {
				// last char was a lead
				if (leadSurrogate) {
					// 2 leads in a row
					if (codePoint < 0xDC00) {
						if ((units -= 3) > -1) bytes.push(0xEF, 0xBF, 0xBD)
						leadSurrogate = codePoint
						continue
					} else {
						// valid surrogate pair
						codePoint = leadSurrogate - 0xD800 << 10 | codePoint - 0xDC00 | 0x10000
						leadSurrogate = null
					}
				} else {
					// no lead yet

					if (codePoint > 0xDBFF) {
						// unexpected trail
						if ((units -= 3) > -1) bytes.push(0xEF, 0xBF, 0xBD)
						continue
					} else if (i + 1 === length) {
						// unpaired lead
						if ((units -= 3) > -1) bytes.push(0xEF, 0xBF, 0xBD)
						continue
					} else {
						// valid lead
						leadSurrogate = codePoint
						continue
					}
				}
			} else if (leadSurrogate) {
				// valid bmp char, but last char was a lead
				if ((units -= 3) > -1) bytes.push(0xEF, 0xBF, 0xBD)
				leadSurrogate = null
			}

			// encode utf8
			if (codePoint < 0x80) {
				if ((units -= 1) < 0) break
				bytes.push(codePoint)
			} else if (codePoint < 0x800) {
				if ((units -= 2) < 0) break
				bytes.push(
					codePoint >> 0x6 | 0xC0,
					codePoint & 0x3F | 0x80
				)
			} else if (codePoint < 0x10000) {
				if ((units -= 3) < 0) break
				bytes.push(
					codePoint >> 0xC | 0xE0,
					codePoint >> 0x6 & 0x3F | 0x80,
					codePoint & 0x3F | 0x80
				)
			} else if (codePoint < 0x200000) {
				if ((units -= 4) < 0) break
				bytes.push(
					codePoint >> 0x12 | 0xF0,
					codePoint >> 0xC & 0x3F | 0x80,
					codePoint >> 0x6 & 0x3F | 0x80,
					codePoint & 0x3F | 0x80
				)
			} else {
				throw new Error('Invalid code point')
			}
		}

		return bytes
	}

	function asciiToBytes (str) {
		var byteArray = []
		for (var i = 0; i < str.length; i++) {
			// Node's code seems to be doing this and not & 0x7F..
			byteArray.push(str.charCodeAt(i) & 0xFF)
		}
		return byteArray
	}

	function utf16leToBytes (str, units) {
		var c, hi, lo
		var byteArray = []
		for (var i = 0; i < str.length; i++) {
			if ((units -= 2) < 0) break

			c = str.charCodeAt(i)
			hi = c >> 8
			lo = c % 256
			byteArray.push(lo)
			byteArray.push(hi)
		}

		return byteArray
	}

	function base64ToBytes (str) {
		return base64.toByteArray(base64clean(str))
	}

	function blitBuffer (src, dst, offset, length) {
		for (var i = 0; i < length; i++) {
			if ((i + offset >= dst.length) || (i >= src.length)) break
			dst[i + offset] = src[i]
		}
		return i
	}

	function decodeUtf8Char (str) {
		try {
			return decodeURIComponent(str)
		} catch (err) {
			return String.fromCharCode(0xFFFD) // UTF 8 invalid char
		}
	}

},{"base64-js":15,"ieee754":16,"is-array":17}],15:[function(require,module,exports){
	var lookup = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

	;(function (exports) {
		'use strict';

		var Arr = (typeof Uint8Array !== 'undefined')
			? Uint8Array
			: Array

		var PLUS   = '+'.charCodeAt(0)
		var SLASH  = '/'.charCodeAt(0)
		var NUMBER = '0'.charCodeAt(0)
		var LOWER  = 'a'.charCodeAt(0)
		var UPPER  = 'A'.charCodeAt(0)
		var PLUS_URL_SAFE = '-'.charCodeAt(0)
		var SLASH_URL_SAFE = '_'.charCodeAt(0)

		function decode (elt) {
			var code = elt.charCodeAt(0)
			if (code === PLUS ||
				code === PLUS_URL_SAFE)
				return 62 // '+'
			if (code === SLASH ||
				code === SLASH_URL_SAFE)
				return 63 // '/'
			if (code < NUMBER)
				return -1 //no match
			if (code < NUMBER + 10)
				return code - NUMBER + 26 + 26
			if (code < UPPER + 26)
				return code - UPPER
			if (code < LOWER + 26)
				return code - LOWER + 26
		}

		function b64ToByteArray (b64) {
			var i, j, l, tmp, placeHolders, arr

			if (b64.length % 4 > 0) {
				throw new Error('Invalid string. Length must be a multiple of 4')
			}

			// the number of equal signs (place holders)
			// if there are two placeholders, than the two characters before it
			// represent one byte
			// if there is only one, then the three characters before it represent 2 bytes
			// this is just a cheap hack to not do indexOf twice
			var len = b64.length
			placeHolders = '=' === b64.charAt(len - 2) ? 2 : '=' === b64.charAt(len - 1) ? 1 : 0

			// base64 is 4/3 + up to two characters of the original data
			arr = new Arr(b64.length * 3 / 4 - placeHolders)

			// if there are placeholders, only get up to the last complete 4 chars
			l = placeHolders > 0 ? b64.length - 4 : b64.length

			var L = 0

			function push (v) {
				arr[L++] = v
			}

			for (i = 0, j = 0; i < l; i += 4, j += 3) {
				tmp = (decode(b64.charAt(i)) << 18) | (decode(b64.charAt(i + 1)) << 12) | (decode(b64.charAt(i + 2)) << 6) | decode(b64.charAt(i + 3))
				push((tmp & 0xFF0000) >> 16)
				push((tmp & 0xFF00) >> 8)
				push(tmp & 0xFF)
			}

			if (placeHolders === 2) {
				tmp = (decode(b64.charAt(i)) << 2) | (decode(b64.charAt(i + 1)) >> 4)
				push(tmp & 0xFF)
			} else if (placeHolders === 1) {
				tmp = (decode(b64.charAt(i)) << 10) | (decode(b64.charAt(i + 1)) << 4) | (decode(b64.charAt(i + 2)) >> 2)
				push((tmp >> 8) & 0xFF)
				push(tmp & 0xFF)
			}

			return arr
		}

		function uint8ToBase64 (uint8) {
			var i,
				extraBytes = uint8.length % 3, // if we have 1 byte left, pad 2 bytes
				output = "",
				temp, length

			function encode (num) {
				return lookup.charAt(num)
			}

			function tripletToBase64 (num) {
				return encode(num >> 18 & 0x3F) + encode(num >> 12 & 0x3F) + encode(num >> 6 & 0x3F) + encode(num & 0x3F)
			}

			// go through the array every three bytes, we'll deal with trailing stuff later
			for (i = 0, length = uint8.length - extraBytes; i < length; i += 3) {
				temp = (uint8[i] << 16) + (uint8[i + 1] << 8) + (uint8[i + 2])
				output += tripletToBase64(temp)
			}

			// pad the end with zeros, but make sure to not forget the extra bytes
			switch (extraBytes) {
				case 1:
					temp = uint8[uint8.length - 1]
					output += encode(temp >> 2)
					output += encode((temp << 4) & 0x3F)
					output += '=='
					break
				case 2:
					temp = (uint8[uint8.length - 2] << 8) + (uint8[uint8.length - 1])
					output += encode(temp >> 10)
					output += encode((temp >> 4) & 0x3F)
					output += encode((temp << 2) & 0x3F)
					output += '='
					break
			}

			return output
		}

		exports.toByteArray = b64ToByteArray
		exports.fromByteArray = uint8ToBase64
	}(typeof exports === 'undefined' ? (this.base64js = {}) : exports))

},{}],16:[function(require,module,exports){
	exports.read = function (buffer, offset, isLE, mLen, nBytes) {
		var e, m
		var eLen = nBytes * 8 - mLen - 1
		var eMax = (1 << eLen) - 1
		var eBias = eMax >> 1
		var nBits = -7
		var i = isLE ? (nBytes - 1) : 0
		var d = isLE ? -1 : 1
		var s = buffer[offset + i]

		i += d

		e = s & ((1 << (-nBits)) - 1)
		s >>= (-nBits)
		nBits += eLen
		for (; nBits > 0; e = e * 256 + buffer[offset + i], i += d, nBits -= 8) {}

		m = e & ((1 << (-nBits)) - 1)
		e >>= (-nBits)
		nBits += mLen
		for (; nBits > 0; m = m * 256 + buffer[offset + i], i += d, nBits -= 8) {}

		if (e === 0) {
			e = 1 - eBias
		} else if (e === eMax) {
			return m ? NaN : ((s ? -1 : 1) * Infinity)
		} else {
			m = m + Math.pow(2, mLen)
			e = e - eBias
		}
		return (s ? -1 : 1) * m * Math.pow(2, e - mLen)
	}

	exports.write = function (buffer, value, offset, isLE, mLen, nBytes) {
		var e, m, c
		var eLen = nBytes * 8 - mLen - 1
		var eMax = (1 << eLen) - 1
		var eBias = eMax >> 1
		var rt = (mLen === 23 ? Math.pow(2, -24) - Math.pow(2, -77) : 0)
		var i = isLE ? 0 : (nBytes - 1)
		var d = isLE ? 1 : -1
		var s = value < 0 || (value === 0 && 1 / value < 0) ? 1 : 0

		value = Math.abs(value)

		if (isNaN(value) || value === Infinity) {
			m = isNaN(value) ? 1 : 0
			e = eMax
		} else {
			e = Math.floor(Math.log(value) / Math.LN2)
			if (value * (c = Math.pow(2, -e)) < 1) {
				e--
				c *= 2
			}
			if (e + eBias >= 1) {
				value += rt / c
			} else {
				value += rt * Math.pow(2, 1 - eBias)
			}
			if (value * c >= 2) {
				e++
				c /= 2
			}

			if (e + eBias >= eMax) {
				m = 0
				e = eMax
			} else if (e + eBias >= 1) {
				m = (value * c - 1) * Math.pow(2, mLen)
				e = e + eBias
			} else {
				m = value * Math.pow(2, eBias - 1) * Math.pow(2, mLen)
				e = 0
			}
		}

		for (; mLen >= 8; buffer[offset + i] = m & 0xff, i += d, m /= 256, mLen -= 8) {}

		e = (e << mLen) | m
		eLen += mLen
		for (; eLen > 0; buffer[offset + i] = e & 0xff, i += d, e /= 256, eLen -= 8) {}

		buffer[offset + i - d] |= s * 128
	}

},{}],17:[function(require,module,exports){

	/**
	 * isArray
	 */

	var isArray = Array.isArray;

	/**
	 * toString
	 */

	var str = Object.prototype.toString;

	/**
	 * Whether or not the given `val`
	 * is an array.
	 *
	 * example:
	 *
	 *        isArray([]);
	 *        // > true
	 *        isArray(arguments);
	 *        // > false
	 *        isArray('');
	 *        // > false
	 *
	 * @param {mixed} val
	 * @return {bool}
	 */

	module.exports = isArray || function (val) {
			return !! val && '[object Array]' == str.call(val);
		};

},{}],18:[function(require,module,exports){
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.

	function EventEmitter() {
		this._events = this._events || {};
		this._maxListeners = this._maxListeners || undefined;
	}
	module.exports = EventEmitter;

// Backwards-compat with node 0.10.x
	EventEmitter.EventEmitter = EventEmitter;

	EventEmitter.prototype._events = undefined;
	EventEmitter.prototype._maxListeners = undefined;

// By default EventEmitters will print a warning if more than 10 listeners are
// added to it. This is a useful default which helps finding memory leaks.
	EventEmitter.defaultMaxListeners = 10;

// Obviously not all Emitters should be limited to 10. This function allows
// that to be increased. Set to zero for unlimited.
	EventEmitter.prototype.setMaxListeners = function(n) {
		if (!isNumber(n) || n < 0 || isNaN(n))
			throw TypeError('n must be a positive number');
		this._maxListeners = n;
		return this;
	};

	EventEmitter.prototype.emit = function(type) {
		var er, handler, len, args, i, listeners;

		if (!this._events)
			this._events = {};

		// If there is no 'error' event listener then throw.
		if (type === 'error') {
			if (!this._events.error ||
				(isObject(this._events.error) && !this._events.error.length)) {
				er = arguments[1];
				if (er instanceof Error) {
					throw er; // Unhandled 'error' event
				}
				throw TypeError('Uncaught, unspecified "error" event.');
			}
		}

		handler = this._events[type];

		if (isUndefined(handler))
			return false;

		if (isFunction(handler)) {
			switch (arguments.length) {
				// fast cases
				case 1:
					handler.call(this);
					break;
				case 2:
					handler.call(this, arguments[1]);
					break;
				case 3:
					handler.call(this, arguments[1], arguments[2]);
					break;
				// slower
				default:
					len = arguments.length;
					args = new Array(len - 1);
					for (i = 1; i < len; i++)
						args[i - 1] = arguments[i];
					handler.apply(this, args);
			}
		} else if (isObject(handler)) {
			len = arguments.length;
			args = new Array(len - 1);
			for (i = 1; i < len; i++)
				args[i - 1] = arguments[i];

			listeners = handler.slice();
			len = listeners.length;
			for (i = 0; i < len; i++)
				listeners[i].apply(this, args);
		}

		return true;
	};

	EventEmitter.prototype.addListener = function(type, listener) {
		var m;

		if (!isFunction(listener))
			throw TypeError('listener must be a function');

		if (!this._events)
			this._events = {};

		// To avoid recursion in the case that type === "newListener"! Before
		// adding it to the listeners, first emit "newListener".
		if (this._events.newListener)
			this.emit('newListener', type,
				isFunction(listener.listener) ?
					listener.listener : listener);

		if (!this._events[type])
		// Optimize the case of one listener. Don't need the extra array object.
			this._events[type] = listener;
		else if (isObject(this._events[type]))
		// If we've already got an array, just append.
			this._events[type].push(listener);
		else
		// Adding the second element, need to change to array.
			this._events[type] = [this._events[type], listener];

		// Check for listener leak
		if (isObject(this._events[type]) && !this._events[type].warned) {
			var m;
			if (!isUndefined(this._maxListeners)) {
				m = this._maxListeners;
			} else {
				m = EventEmitter.defaultMaxListeners;
			}

			if (m && m > 0 && this._events[type].length > m) {
				this._events[type].warned = true;
				console.error('(node) warning: possible EventEmitter memory ' +
					'leak detected. %d listeners added. ' +
					'Use emitter.setMaxListeners() to increase limit.',
					this._events[type].length);
				if (typeof console.trace === 'function') {
					// not supported in IE 10
					console.trace();
				}
			}
		}

		return this;
	};

	EventEmitter.prototype.on = EventEmitter.prototype.addListener;

	EventEmitter.prototype.once = function(type, listener) {
		if (!isFunction(listener))
			throw TypeError('listener must be a function');

		var fired = false;

		function g() {
			this.removeListener(type, g);

			if (!fired) {
				fired = true;
				listener.apply(this, arguments);
			}
		}

		g.listener = listener;
		this.on(type, g);

		return this;
	};

// emits a 'removeListener' event iff the listener was removed
	EventEmitter.prototype.removeListener = function(type, listener) {
		var list, position, length, i;

		if (!isFunction(listener))
			throw TypeError('listener must be a function');

		if (!this._events || !this._events[type])
			return this;

		list = this._events[type];
		length = list.length;
		position = -1;

		if (list === listener ||
			(isFunction(list.listener) && list.listener === listener)) {
			delete this._events[type];
			if (this._events.removeListener)
				this.emit('removeListener', type, listener);

		} else if (isObject(list)) {
			for (i = length; i-- > 0;) {
				if (list[i] === listener ||
					(list[i].listener && list[i].listener === listener)) {
					position = i;
					break;
				}
			}

			if (position < 0)
				return this;

			if (list.length === 1) {
				list.length = 0;
				delete this._events[type];
			} else {
				list.splice(position, 1);
			}

			if (this._events.removeListener)
				this.emit('removeListener', type, listener);
		}

		return this;
	};

	EventEmitter.prototype.removeAllListeners = function(type) {
		var key, listeners;

		if (!this._events)
			return this;

		// not listening for removeListener, no need to emit
		if (!this._events.removeListener) {
			if (arguments.length === 0)
				this._events = {};
			else if (this._events[type])
				delete this._events[type];
			return this;
		}

		// emit removeListener for all listeners on all events
		if (arguments.length === 0) {
			for (key in this._events) {
				if (key === 'removeListener') continue;
				this.removeAllListeners(key);
			}
			this.removeAllListeners('removeListener');
			this._events = {};
			return this;
		}

		listeners = this._events[type];

		if (isFunction(listeners)) {
			this.removeListener(type, listeners);
		} else {
			// LIFO order
			while (listeners.length)
				this.removeListener(type, listeners[listeners.length - 1]);
		}
		delete this._events[type];

		return this;
	};

	EventEmitter.prototype.listeners = function(type) {
		var ret;
		if (!this._events || !this._events[type])
			ret = [];
		else if (isFunction(this._events[type]))
			ret = [this._events[type]];
		else
			ret = this._events[type].slice();
		return ret;
	};

	EventEmitter.listenerCount = function(emitter, type) {
		var ret;
		if (!emitter._events || !emitter._events[type])
			ret = 0;
		else if (isFunction(emitter._events[type]))
			ret = 1;
		else
			ret = emitter._events[type].length;
		return ret;
	};

	function isFunction(arg) {
		return typeof arg === 'function';
	}

	function isNumber(arg) {
		return typeof arg === 'number';
	}

	function isObject(arg) {
		return typeof arg === 'object' && arg !== null;
	}

	function isUndefined(arg) {
		return arg === void 0;
	}

},{}],19:[function(require,module,exports){
	(function (global){
		/*! https://mths.be/punycode v1.3.2 by @mathias */
		;(function(root) {

			/** Detect free variables */
			var freeExports = typeof exports == 'object' && exports &&
				!exports.nodeType && exports;
			var freeModule = typeof module == 'object' && module &&
				!module.nodeType && module;
			var freeGlobal = typeof global == 'object' && global;
			if (
				freeGlobal.global === freeGlobal ||
				freeGlobal.window === freeGlobal ||
				freeGlobal.self === freeGlobal
			) {
				root = freeGlobal;
			}

			/**
			 * The `punycode` object.
			 * @name punycode
			 * @type Object
			 */
			var punycode,

				/** Highest positive signed 32-bit float value */
				maxInt = 2147483647, // aka. 0x7FFFFFFF or 2^31-1

				/** Bootstring parameters */
				base = 36,
				tMin = 1,
				tMax = 26,
				skew = 38,
				damp = 700,
				initialBias = 72,
				initialN = 128, // 0x80
				delimiter = '-', // '\x2D'

				/** Regular expressions */
				regexPunycode = /^xn--/,
				regexNonASCII = /[^\x20-\x7E]/, // unprintable ASCII chars + non-ASCII chars
				regexSeparators = /[\x2E\u3002\uFF0E\uFF61]/g, // RFC 3490 separators

				/** Error messages */
				errors = {
					'overflow': 'Overflow: input needs wider integers to process',
					'not-basic': 'Illegal input >= 0x80 (not a basic code point)',
					'invalid-input': 'Invalid input'
				},

				/** Convenience shortcuts */
				baseMinusTMin = base - tMin,
				floor = Math.floor,
				stringFromCharCode = String.fromCharCode,

				/** Temporary variable */
				key;

			/*--------------------------------------------------------------------------*/

			/**
			 * A generic error utility function.
			 * @private
			 * @param {String} type The error type.
			 * @returns {Error} Throws a `RangeError` with the applicable error message.
			 */
			function error(type) {
				throw RangeError(errors[type]);
			}

			/**
			 * A generic `Array#map` utility function.
			 * @private
			 * @param {Array} array The array to iterate over.
			 * @param {Function} callback The function that gets called for every array
			 * item.
			 * @returns {Array} A new array of values returned by the callback function.
			 */
			function map(array, fn) {
				var length = array.length;
				var result = [];
				while (length--) {
					result[length] = fn(array[length]);
				}
				return result;
			}

			/**
			 * A simple `Array#map`-like wrapper to work with domain name strings or email
			 * addresses.
			 * @private
			 * @param {String} domain The domain name or email address.
			 * @param {Function} callback The function that gets called for every
			 * character.
			 * @returns {Array} A new string of characters returned by the callback
			 * function.
			 */
			function mapDomain(string, fn) {
				var parts = string.split('@');
				var result = '';
				if (parts.length > 1) {
					// In email addresses, only the domain name should be punycoded. Leave
					// the local part (i.e. everything up to `@`) intact.
					result = parts[0] + '@';
					string = parts[1];
				}
				// Avoid `split(regex)` for IE8 compatibility. See #17.
				string = string.replace(regexSeparators, '\x2E');
				var labels = string.split('.');
				var encoded = map(labels, fn).join('.');
				return result + encoded;
			}

			/**
			 * Creates an array containing the numeric code points of each Unicode
			 * character in the string. While JavaScript uses UCS-2 internally,
			 * this function will convert a pair of surrogate halves (each of which
			 * UCS-2 exposes as separate characters) into a single code point,
			 * matching UTF-16.
			 * @see `punycode.ucs2.encode`
			 * @see <https://mathiasbynens.be/notes/javascript-encoding>
			 * @memberOf punycode.ucs2
			 * @name decode
			 * @param {String} string The Unicode input string (UCS-2).
			 * @returns {Array} The new array of code points.
			 */
			function ucs2decode(string) {
				var output = [],
					counter = 0,
					length = string.length,
					value,
					extra;
				while (counter < length) {
					value = string.charCodeAt(counter++);
					if (value >= 0xD800 && value <= 0xDBFF && counter < length) {
						// high surrogate, and there is a next character
						extra = string.charCodeAt(counter++);
						if ((extra & 0xFC00) == 0xDC00) { // low surrogate
							output.push(((value & 0x3FF) << 10) + (extra & 0x3FF) + 0x10000);
						} else {
							// unmatched surrogate; only append this code unit, in case the next
							// code unit is the high surrogate of a surrogate pair
							output.push(value);
							counter--;
						}
					} else {
						output.push(value);
					}
				}
				return output;
			}

			/**
			 * Creates a string based on an array of numeric code points.
			 * @see `punycode.ucs2.decode`
			 * @memberOf punycode.ucs2
			 * @name encode
			 * @param {Array} codePoints The array of numeric code points.
			 * @returns {String} The new Unicode string (UCS-2).
			 */
			function ucs2encode(array) {
				return map(array, function(value) {
					var output = '';
					if (value > 0xFFFF) {
						value -= 0x10000;
						output += stringFromCharCode(value >>> 10 & 0x3FF | 0xD800);
						value = 0xDC00 | value & 0x3FF;
					}
					output += stringFromCharCode(value);
					return output;
				}).join('');
			}

			/**
			 * Converts a basic code point into a digit/integer.
			 * @see `digitToBasic()`
			 * @private
			 * @param {Number} codePoint The basic numeric code point value.
			 * @returns {Number} The numeric value of a basic code point (for use in
			 * representing integers) in the range `0` to `base - 1`, or `base` if
			 * the code point does not represent a value.
			 */
			function basicToDigit(codePoint) {
				if (codePoint - 48 < 10) {
					return codePoint - 22;
				}
				if (codePoint - 65 < 26) {
					return codePoint - 65;
				}
				if (codePoint - 97 < 26) {
					return codePoint - 97;
				}
				return base;
			}

			/**
			 * Converts a digit/integer into a basic code point.
			 * @see `basicToDigit()`
			 * @private
			 * @param {Number} digit The numeric value of a basic code point.
			 * @returns {Number} The basic code point whose value (when used for
			 * representing integers) is `digit`, which needs to be in the range
			 * `0` to `base - 1`. If `flag` is non-zero, the uppercase form is
			 * used; else, the lowercase form is used. The behavior is undefined
			 * if `flag` is non-zero and `digit` has no uppercase form.
			 */
			function digitToBasic(digit, flag) {
				//  0..25 map to ASCII a..z or A..Z
				// 26..35 map to ASCII 0..9
				return digit + 22 + 75 * (digit < 26) - ((flag != 0) << 5);
			}

			/**
			 * Bias adaptation function as per section 3.4 of RFC 3492.
			 * http://tools.ietf.org/html/rfc3492#section-3.4
			 * @private
			 */
			function adapt(delta, numPoints, firstTime) {
				var k = 0;
				delta = firstTime ? floor(delta / damp) : delta >> 1;
				delta += floor(delta / numPoints);
				for (/* no initialization */; delta > baseMinusTMin * tMax >> 1; k += base) {
					delta = floor(delta / baseMinusTMin);
				}
				return floor(k + (baseMinusTMin + 1) * delta / (delta + skew));
			}

			/**
			 * Converts a Punycode string of ASCII-only symbols to a string of Unicode
			 * symbols.
			 * @memberOf punycode
			 * @param {String} input The Punycode string of ASCII-only symbols.
			 * @returns {String} The resulting string of Unicode symbols.
			 */
			function decode(input) {
				// Don't use UCS-2
				var output = [],
					inputLength = input.length,
					out,
					i = 0,
					n = initialN,
					bias = initialBias,
					basic,
					j,
					index,
					oldi,
					w,
					k,
					digit,
					t,
					/** Cached calculation results */
					baseMinusT;

				// Handle the basic code points: let `basic` be the number of input code
				// points before the last delimiter, or `0` if there is none, then copy
				// the first basic code points to the output.

				basic = input.lastIndexOf(delimiter);
				if (basic < 0) {
					basic = 0;
				}

				for (j = 0; j < basic; ++j) {
					// if it's not a basic code point
					if (input.charCodeAt(j) >= 0x80) {
						error('not-basic');
					}
					output.push(input.charCodeAt(j));
				}

				// Main decoding loop: start just after the last delimiter if any basic code
				// points were copied; start at the beginning otherwise.

				for (index = basic > 0 ? basic + 1 : 0; index < inputLength; /* no final expression */) {

					// `index` is the index of the next character to be consumed.
					// Decode a generalized variable-length integer into `delta`,
					// which gets added to `i`. The overflow checking is easier
					// if we increase `i` as we go, then subtract off its starting
					// value at the end to obtain `delta`.
					for (oldi = i, w = 1, k = base; /* no condition */; k += base) {

						if (index >= inputLength) {
							error('invalid-input');
						}

						digit = basicToDigit(input.charCodeAt(index++));

						if (digit >= base || digit > floor((maxInt - i) / w)) {
							error('overflow');
						}

						i += digit * w;
						t = k <= bias ? tMin : (k >= bias + tMax ? tMax : k - bias);

						if (digit < t) {
							break;
						}

						baseMinusT = base - t;
						if (w > floor(maxInt / baseMinusT)) {
							error('overflow');
						}

						w *= baseMinusT;

					}

					out = output.length + 1;
					bias = adapt(i - oldi, out, oldi == 0);

					// `i` was supposed to wrap around from `out` to `0`,
					// incrementing `n` each time, so we'll fix that now:
					if (floor(i / out) > maxInt - n) {
						error('overflow');
					}

					n += floor(i / out);
					i %= out;

					// Insert `n` at position `i` of the output
					output.splice(i++, 0, n);

				}

				return ucs2encode(output);
			}

			/**
			 * Converts a string of Unicode symbols (e.g. a domain name label) to a
			 * Punycode string of ASCII-only symbols.
			 * @memberOf punycode
			 * @param {String} input The string of Unicode symbols.
			 * @returns {String} The resulting Punycode string of ASCII-only symbols.
			 */
			function encode(input) {
				var n,
					delta,
					handledCPCount,
					basicLength,
					bias,
					j,
					m,
					q,
					k,
					t,
					currentValue,
					output = [],
					/** `inputLength` will hold the number of code points in `input`. */
					inputLength,
					/** Cached calculation results */
					handledCPCountPlusOne,
					baseMinusT,
					qMinusT;

				// Convert the input in UCS-2 to Unicode
				input = ucs2decode(input);

				// Cache the length
				inputLength = input.length;

				// Initialize the state
				n = initialN;
				delta = 0;
				bias = initialBias;

				// Handle the basic code points
				for (j = 0; j < inputLength; ++j) {
					currentValue = input[j];
					if (currentValue < 0x80) {
						output.push(stringFromCharCode(currentValue));
					}
				}

				handledCPCount = basicLength = output.length;

				// `handledCPCount` is the number of code points that have been handled;
				// `basicLength` is the number of basic code points.

				// Finish the basic string - if it is not empty - with a delimiter
				if (basicLength) {
					output.push(delimiter);
				}

				// Main encoding loop:
				while (handledCPCount < inputLength) {

					// All non-basic code points < n have been handled already. Find the next
					// larger one:
					for (m = maxInt, j = 0; j < inputLength; ++j) {
						currentValue = input[j];
						if (currentValue >= n && currentValue < m) {
							m = currentValue;
						}
					}

					// Increase `delta` enough to advance the decoder's <n,i> state to <m,0>,
					// but guard against overflow
					handledCPCountPlusOne = handledCPCount + 1;
					if (m - n > floor((maxInt - delta) / handledCPCountPlusOne)) {
						error('overflow');
					}

					delta += (m - n) * handledCPCountPlusOne;
					n = m;

					for (j = 0; j < inputLength; ++j) {
						currentValue = input[j];

						if (currentValue < n && ++delta > maxInt) {
							error('overflow');
						}

						if (currentValue == n) {
							// Represent delta as a generalized variable-length integer
							for (q = delta, k = base; /* no condition */; k += base) {
								t = k <= bias ? tMin : (k >= bias + tMax ? tMax : k - bias);
								if (q < t) {
									break;
								}
								qMinusT = q - t;
								baseMinusT = base - t;
								output.push(
									stringFromCharCode(digitToBasic(t + qMinusT % baseMinusT, 0))
								);
								q = floor(qMinusT / baseMinusT);
							}

							output.push(stringFromCharCode(digitToBasic(q, 0)));
							bias = adapt(delta, handledCPCountPlusOne, handledCPCount == basicLength);
							delta = 0;
							++handledCPCount;
						}
					}

					++delta;
					++n;

				}
				return output.join('');
			}

			/**
			 * Converts a Punycode string representing a domain name or an email address
			 * to Unicode. Only the Punycoded parts of the input will be converted, i.e.
			 * it doesn't matter if you call it on a string that has already been
			 * converted to Unicode.
			 * @memberOf punycode
			 * @param {String} input The Punycoded domain name or email address to
			 * convert to Unicode.
			 * @returns {String} The Unicode representation of the given Punycode
			 * string.
			 */
			function toUnicode(input) {
				return mapDomain(input, function(string) {
					return regexPunycode.test(string)
						? decode(string.slice(4).toLowerCase())
						: string;
				});
			}

			/**
			 * Converts a Unicode string representing a domain name or an email address to
			 * Punycode. Only the non-ASCII parts of the domain name will be converted,
			 * i.e. it doesn't matter if you call it with a domain that's already in
			 * ASCII.
			 * @memberOf punycode
			 * @param {String} input The domain name or email address to convert, as a
			 * Unicode string.
			 * @returns {String} The Punycode representation of the given domain name or
			 * email address.
			 */
			function toASCII(input) {
				return mapDomain(input, function(string) {
					return regexNonASCII.test(string)
						? 'xn--' + encode(string)
						: string;
				});
			}

			/*--------------------------------------------------------------------------*/

			/** Define the public API */
			punycode = {
				/**
				 * A string representing the current Punycode.js version number.
				 * @memberOf punycode
				 * @type String
				 */
				'version': '1.3.2',
				/**
				 * An object of methods to convert from JavaScript's internal character
				 * representation (UCS-2) to Unicode code points, and back.
				 * @see <https://mathiasbynens.be/notes/javascript-encoding>
				 * @memberOf punycode
				 * @type Object
				 */
				'ucs2': {
					'decode': ucs2decode,
					'encode': ucs2encode
				},
				'decode': decode,
				'encode': encode,
				'toASCII': toASCII,
				'toUnicode': toUnicode
			};

			/** Expose `punycode` */
			// Some AMD build optimizers, like r.js, check for specific condition patterns
			// like the following:
			if (
				typeof define == 'function' &&
				typeof define.amd == 'object' &&
				define.amd
			) {
				define('punycode', function() {
					return punycode;
				});
			} else if (freeExports && freeModule) {
				if (module.exports == freeExports) { // in Node.js or RingoJS v0.8.0+
					freeModule.exports = punycode;
				} else { // in Narwhal or RingoJS v0.7.0-
					for (key in punycode) {
						punycode.hasOwnProperty(key) && (freeExports[key] = punycode[key]);
					}
				}
			} else { // in Rhino or a web browser
				root.punycode = punycode;
			}

		}(this));

	}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})
},{}],20:[function(require,module,exports){
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.

	'use strict';

// If obj.hasOwnProperty has been overridden, then calling
// obj.hasOwnProperty(prop) will break.
// See: https://github.com/joyent/node/issues/1707
	function hasOwnProperty(obj, prop) {
		return Object.prototype.hasOwnProperty.call(obj, prop);
	}

	module.exports = function(qs, sep, eq, options) {
		sep = sep || '&';
		eq = eq || '=';
		var obj = {};

		if (typeof qs !== 'string' || qs.length === 0) {
			return obj;
		}

		var regexp = /\+/g;
		qs = qs.split(sep);

		var maxKeys = 1000;
		if (options && typeof options.maxKeys === 'number') {
			maxKeys = options.maxKeys;
		}

		var len = qs.length;
		// maxKeys <= 0 means that we should not limit keys count
		if (maxKeys > 0 && len > maxKeys) {
			len = maxKeys;
		}

		for (var i = 0; i < len; ++i) {
			var x = qs[i].replace(regexp, '%20'),
				idx = x.indexOf(eq),
				kstr, vstr, k, v;

			if (idx >= 0) {
				kstr = x.substr(0, idx);
				vstr = x.substr(idx + 1);
			} else {
				kstr = x;
				vstr = '';
			}

			k = decodeURIComponent(kstr);
			v = decodeURIComponent(vstr);

			if (!hasOwnProperty(obj, k)) {
				obj[k] = v;
			} else if (isArray(obj[k])) {
				obj[k].push(v);
			} else {
				obj[k] = [obj[k], v];
			}
		}

		return obj;
	};

	var isArray = Array.isArray || function (xs) {
			return Object.prototype.toString.call(xs) === '[object Array]';
		};

},{}],21:[function(require,module,exports){
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.

	'use strict';

	var stringifyPrimitive = function(v) {
		switch (typeof v) {
			case 'string':
				return v;

			case 'boolean':
				return v ? 'true' : 'false';

			case 'number':
				return isFinite(v) ? v : '';

			default:
				return '';
		}
	};

	module.exports = function(obj, sep, eq, name) {
		sep = sep || '&';
		eq = eq || '=';
		if (obj === null) {
			obj = undefined;
		}

		if (typeof obj === 'object') {
			return map(objectKeys(obj), function(k) {
				var ks = encodeURIComponent(stringifyPrimitive(k)) + eq;
				if (isArray(obj[k])) {
					return map(obj[k], function(v) {
						return ks + encodeURIComponent(stringifyPrimitive(v));
					}).join(sep);
				} else {
					return ks + encodeURIComponent(stringifyPrimitive(obj[k]));
				}
			}).join(sep);

		}

		if (!name) return '';
		return encodeURIComponent(stringifyPrimitive(name)) + eq +
			encodeURIComponent(stringifyPrimitive(obj));
	};

	var isArray = Array.isArray || function (xs) {
			return Object.prototype.toString.call(xs) === '[object Array]';
		};

	function map (xs, f) {
		if (xs.map) return xs.map(f);
		var res = [];
		for (var i = 0; i < xs.length; i++) {
			res.push(f(xs[i], i));
		}
		return res;
	}

	var objectKeys = Object.keys || function (obj) {
			var res = [];
			for (var key in obj) {
				if (Object.prototype.hasOwnProperty.call(obj, key)) res.push(key);
			}
			return res;
		};

},{}],22:[function(require,module,exports){
	'use strict';

	exports.decode = exports.parse = require('./decode');
	exports.encode = exports.stringify = require('./encode');

},{"./decode":20,"./encode":21}],23:[function(require,module,exports){
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.

	var punycode = require('punycode');

	exports.parse = urlParse;
	exports.resolve = urlResolve;
	exports.resolveObject = urlResolveObject;
	exports.format = urlFormat;

	exports.Url = Url;

	function Url() {
		this.protocol = null;
		this.slashes = null;
		this.auth = null;
		this.host = null;
		this.port = null;
		this.hostname = null;
		this.hash = null;
		this.search = null;
		this.query = null;
		this.pathname = null;
		this.path = null;
		this.href = null;
	}

// Reference: RFC 3986, RFC 1808, RFC 2396

// define these here so at least they only have to be
// compiled once on the first module load.
	var protocolPattern = /^([a-z0-9.+-]+:)/i,
		portPattern = /:[0-9]*$/,

	// RFC 2396: characters reserved for delimiting URLs.
	// We actually just auto-escape these.
		delims = ['<', '>', '"', '`', ' ', '\r', '\n', '\t'],

	// RFC 2396: characters not allowed for various reasons.
		unwise = ['{', '}', '|', '\\', '^', '`'].concat(delims),

	// Allowed by RFCs, but cause of XSS attacks.  Always escape these.
		autoEscape = ['\''].concat(unwise),
	// Characters that are never ever allowed in a hostname.
	// Note that any invalid chars are also handled, but these
	// are the ones that are *expected* to be seen, so we fast-path
	// them.
		nonHostChars = ['%', '/', '?', ';', '#'].concat(autoEscape),
		hostEndingChars = ['/', '?', '#'],
		hostnameMaxLen = 255,
		hostnamePartPattern = /^[a-z0-9A-Z_-]{0,63}$/,
		hostnamePartStart = /^([a-z0-9A-Z_-]{0,63})(.*)$/,
	// protocols that can allow "unsafe" and "unwise" chars.
		unsafeProtocol = {
			'javascript': true,
			'javascript:': true
		},
	// protocols that never have a hostname.
		hostlessProtocol = {
			'javascript': true,
			'javascript:': true
		},
	// protocols that always contain a // bit.
		slashedProtocol = {
			'http': true,
			'https': true,
			'ftp': true,
			'gopher': true,
			'file': true,
			'http:': true,
			'https:': true,
			'ftp:': true,
			'gopher:': true,
			'file:': true
		},
		querystring = require('querystring');

	function urlParse(url, parseQueryString, slashesDenoteHost) {
		if (url && isObject(url) && url instanceof Url) return url;

		var u = new Url;
		u.parse(url, parseQueryString, slashesDenoteHost);
		return u;
	}

	Url.prototype.parse = function(url, parseQueryString, slashesDenoteHost) {
		if (!isString(url)) {
			throw new TypeError("Parameter 'url' must be a string, not " + typeof url);
		}

		var rest = url;

		// trim before proceeding.
		// This is to support parse stuff like "  http://foo.com  \n"
		rest = rest.trim();

		var proto = protocolPattern.exec(rest);
		if (proto) {
			proto = proto[0];
			var lowerProto = proto.toLowerCase();
			this.protocol = lowerProto;
			rest = rest.substr(proto.length);
		}

		// figure out if it's got a host
		// user@server is *always* interpreted as a hostname, and url
		// resolution will treat //foo/bar as host=foo,path=bar because that's
		// how the browser resolves relative URLs.
		if (slashesDenoteHost || proto || rest.match(/^\/\/[^@\/]+@[^@\/]+/)) {
			var slashes = rest.substr(0, 2) === '//';
			if (slashes && !(proto && hostlessProtocol[proto])) {
				rest = rest.substr(2);
				this.slashes = true;
			}
		}

		if (!hostlessProtocol[proto] &&
			(slashes || (proto && !slashedProtocol[proto]))) {

			// there's a hostname.
			// the first instance of /, ?, ;, or # ends the host.
			//
			// If there is an @ in the hostname, then non-host chars *are* allowed
			// to the left of the last @ sign, unless some host-ending character
			// comes *before* the @-sign.
			// URLs are obnoxious.
			//
			// ex:
			// http://a@b@c/ => user:a@b host:c
			// http://a@b?@c => user:a host:c path:/?@c

			// v0.12 TODO(isaacs): This is not quite how Chrome does things.
			// Review our test case against browsers more comprehensively.

			// find the first instance of any hostEndingChars
			var hostEnd = -1;
			for (var i = 0; i < hostEndingChars.length; i++) {
				var hec = rest.indexOf(hostEndingChars[i]);
				if (hec !== -1 && (hostEnd === -1 || hec < hostEnd))
					hostEnd = hec;
			}

			// at this point, either we have an explicit point where the
			// auth portion cannot go past, or the last @ char is the decider.
			var auth, atSign;
			if (hostEnd === -1) {
				// atSign can be anywhere.
				atSign = rest.lastIndexOf('@');
			} else {
				// atSign must be in auth portion.
				// http://a@b/c@d => host:b auth:a path:/c@d
				atSign = rest.lastIndexOf('@', hostEnd);
			}

			// Now we have a portion which is definitely the auth.
			// Pull that off.
			if (atSign !== -1) {
				auth = rest.slice(0, atSign);
				rest = rest.slice(atSign + 1);
				this.auth = decodeURIComponent(auth);
			}

			// the host is the remaining to the left of the first non-host char
			hostEnd = -1;
			for (var i = 0; i < nonHostChars.length; i++) {
				var hec = rest.indexOf(nonHostChars[i]);
				if (hec !== -1 && (hostEnd === -1 || hec < hostEnd))
					hostEnd = hec;
			}
			// if we still have not hit it, then the entire thing is a host.
			if (hostEnd === -1)
				hostEnd = rest.length;

			this.host = rest.slice(0, hostEnd);
			rest = rest.slice(hostEnd);

			// pull out port.
			this.parseHost();

			// we've indicated that there is a hostname,
			// so even if it's empty, it has to be present.
			this.hostname = this.hostname || '';

			// if hostname begins with [ and ends with ]
			// assume that it's an IPv6 address.
			var ipv6Hostname = this.hostname[0] === '[' &&
				this.hostname[this.hostname.length - 1] === ']';

			// validate a little.
			if (!ipv6Hostname) {
				var hostparts = this.hostname.split(/\./);
				for (var i = 0, l = hostparts.length; i < l; i++) {
					var part = hostparts[i];
					if (!part) continue;
					if (!part.match(hostnamePartPattern)) {
						var newpart = '';
						for (var j = 0, k = part.length; j < k; j++) {
							if (part.charCodeAt(j) > 127) {
								// we replace non-ASCII char with a temporary placeholder
								// we need this to make sure size of hostname is not
								// broken by replacing non-ASCII by nothing
								newpart += 'x';
							} else {
								newpart += part[j];
							}
						}
						// we test again with ASCII char only
						if (!newpart.match(hostnamePartPattern)) {
							var validParts = hostparts.slice(0, i);
							var notHost = hostparts.slice(i + 1);
							var bit = part.match(hostnamePartStart);
							if (bit) {
								validParts.push(bit[1]);
								notHost.unshift(bit[2]);
							}
							if (notHost.length) {
								rest = '/' + notHost.join('.') + rest;
							}
							this.hostname = validParts.join('.');
							break;
						}
					}
				}
			}

			if (this.hostname.length > hostnameMaxLen) {
				this.hostname = '';
			} else {
				// hostnames are always lower case.
				this.hostname = this.hostname.toLowerCase();
			}

			if (!ipv6Hostname) {
				// IDNA Support: Returns a puny coded representation of "domain".
				// It only converts the part of the domain name that
				// has non ASCII characters. I.e. it dosent matter if
				// you call it with a domain that already is in ASCII.
				var domainArray = this.hostname.split('.');
				var newOut = [];
				for (var i = 0; i < domainArray.length; ++i) {
					var s = domainArray[i];
					newOut.push(s.match(/[^A-Za-z0-9_-]/) ?
					'xn--' + punycode.encode(s) : s);
				}
				this.hostname = newOut.join('.');
			}

			var p = this.port ? ':' + this.port : '';
			var h = this.hostname || '';
			this.host = h + p;
			this.href += this.host;

			// strip [ and ] from the hostname
			// the host field still retains them, though
			if (ipv6Hostname) {
				this.hostname = this.hostname.substr(1, this.hostname.length - 2);
				if (rest[0] !== '/') {
					rest = '/' + rest;
				}
			}
		}

		// now rest is set to the post-host stuff.
		// chop off any delim chars.
		if (!unsafeProtocol[lowerProto]) {

			// First, make 100% sure that any "autoEscape" chars get
			// escaped, even if encodeURIComponent doesn't think they
			// need to be.
			for (var i = 0, l = autoEscape.length; i < l; i++) {
				var ae = autoEscape[i];
				var esc = encodeURIComponent(ae);
				if (esc === ae) {
					esc = escape(ae);
				}
				rest = rest.split(ae).join(esc);
			}
		}


		// chop off from the tail first.
		var hash = rest.indexOf('#');
		if (hash !== -1) {
			// got a fragment string.
			this.hash = rest.substr(hash);
			rest = rest.slice(0, hash);
		}
		var qm = rest.indexOf('?');
		if (qm !== -1) {
			this.search = rest.substr(qm);
			this.query = rest.substr(qm + 1);
			if (parseQueryString) {
				this.query = querystring.parse(this.query);
			}
			rest = rest.slice(0, qm);
		} else if (parseQueryString) {
			// no query string, but parseQueryString still requested
			this.search = '';
			this.query = {};
		}
		if (rest) this.pathname = rest;
		if (slashedProtocol[lowerProto] &&
			this.hostname && !this.pathname) {
			this.pathname = '/';
		}

		//to support http.request
		if (this.pathname || this.search) {
			var p = this.pathname || '';
			var s = this.search || '';
			this.path = p + s;
		}

		// finally, reconstruct the href based on what has been validated.
		this.href = this.format();
		return this;
	};

// format a parsed object into a url string
	function urlFormat(obj) {
		// ensure it's an object, and not a string url.
		// If it's an obj, this is a no-op.
		// this way, you can call url_format() on strings
		// to clean up potentially wonky urls.
		if (isString(obj)) obj = urlParse(obj);
		if (!(obj instanceof Url)) return Url.prototype.format.call(obj);
		return obj.format();
	}

	Url.prototype.format = function() {
		var auth = this.auth || '';
		if (auth) {
			auth = encodeURIComponent(auth);
			auth = auth.replace(/%3A/i, ':');
			auth += '@';
		}

		var protocol = this.protocol || '',
			pathname = this.pathname || '',
			hash = this.hash || '',
			host = false,
			query = '';

		if (this.host) {
			host = auth + this.host;
		} else if (this.hostname) {
			host = auth + (this.hostname.indexOf(':') === -1 ?
					this.hostname :
				'[' + this.hostname + ']');
			if (this.port) {
				host += ':' + this.port;
			}
		}

		if (this.query &&
			isObject(this.query) &&
			Object.keys(this.query).length) {
			query = querystring.stringify(this.query);
		}

		var search = this.search || (query && ('?' + query)) || '';

		if (protocol && protocol.substr(-1) !== ':') protocol += ':';

		// only the slashedProtocols get the //.  Not mailto:, xmpp:, etc.
		// unless they had them to begin with.
		if (this.slashes ||
			(!protocol || slashedProtocol[protocol]) && host !== false) {
			host = '//' + (host || '');
			if (pathname && pathname.charAt(0) !== '/') pathname = '/' + pathname;
		} else if (!host) {
			host = '';
		}

		if (hash && hash.charAt(0) !== '#') hash = '#' + hash;
		if (search && search.charAt(0) !== '?') search = '?' + search;

		pathname = pathname.replace(/[?#]/g, function(match) {
			return encodeURIComponent(match);
		});
		search = search.replace('#', '%23');

		return protocol + host + pathname + search + hash;
	};

	function urlResolve(source, relative) {
		return urlParse(source, false, true).resolve(relative);
	}

	Url.prototype.resolve = function(relative) {
		return this.resolveObject(urlParse(relative, false, true)).format();
	};

	function urlResolveObject(source, relative) {
		if (!source) return relative;
		return urlParse(source, false, true).resolveObject(relative);
	}

	Url.prototype.resolveObject = function(relative) {
		if (isString(relative)) {
			var rel = new Url();
			rel.parse(relative, false, true);
			relative = rel;
		}

		var result = new Url();
		Object.keys(this).forEach(function(k) {
			result[k] = this[k];
		}, this);

		// hash is always overridden, no matter what.
		// even href="" will remove it.
		result.hash = relative.hash;

		// if the relative url is empty, then there's nothing left to do here.
		if (relative.href === '') {
			result.href = result.format();
			return result;
		}

		// hrefs like //foo/bar always cut to the protocol.
		if (relative.slashes && !relative.protocol) {
			// take everything except the protocol from relative
			Object.keys(relative).forEach(function(k) {
				if (k !== 'protocol')
					result[k] = relative[k];
			});

			//urlParse appends trailing / to urls like http://www.example.com
			if (slashedProtocol[result.protocol] &&
				result.hostname && !result.pathname) {
				result.path = result.pathname = '/';
			}

			result.href = result.format();
			return result;
		}

		if (relative.protocol && relative.protocol !== result.protocol) {
			// if it's a known url protocol, then changing
			// the protocol does weird things
			// first, if it's not file:, then we MUST have a host,
			// and if there was a path
			// to begin with, then we MUST have a path.
			// if it is file:, then the host is dropped,
			// because that's known to be hostless.
			// anything else is assumed to be absolute.
			if (!slashedProtocol[relative.protocol]) {
				Object.keys(relative).forEach(function(k) {
					result[k] = relative[k];
				});
				result.href = result.format();
				return result;
			}

			result.protocol = relative.protocol;
			if (!relative.host && !hostlessProtocol[relative.protocol]) {
				var relPath = (relative.pathname || '').split('/');
				while (relPath.length && !(relative.host = relPath.shift()));
				if (!relative.host) relative.host = '';
				if (!relative.hostname) relative.hostname = '';
				if (relPath[0] !== '') relPath.unshift('');
				if (relPath.length < 2) relPath.unshift('');
				result.pathname = relPath.join('/');
			} else {
				result.pathname = relative.pathname;
			}
			result.search = relative.search;
			result.query = relative.query;
			result.host = relative.host || '';
			result.auth = relative.auth;
			result.hostname = relative.hostname || relative.host;
			result.port = relative.port;
			// to support http.request
			if (result.pathname || result.search) {
				var p = result.pathname || '';
				var s = result.search || '';
				result.path = p + s;
			}
			result.slashes = result.slashes || relative.slashes;
			result.href = result.format();
			return result;
		}

		var isSourceAbs = (result.pathname && result.pathname.charAt(0) === '/'),
			isRelAbs = (
				relative.host ||
				relative.pathname && relative.pathname.charAt(0) === '/'
			),
			mustEndAbs = (isRelAbs || isSourceAbs ||
			(result.host && relative.pathname)),
			removeAllDots = mustEndAbs,
			srcPath = result.pathname && result.pathname.split('/') || [],
			relPath = relative.pathname && relative.pathname.split('/') || [],
			psychotic = result.protocol && !slashedProtocol[result.protocol];

		// if the url is a non-slashed url, then relative
		// links like ../.. should be able
		// to crawl up to the hostname, as well.  This is strange.
		// result.protocol has already been set by now.
		// Later on, put the first path part into the host field.
		if (psychotic) {
			result.hostname = '';
			result.port = null;
			if (result.host) {
				if (srcPath[0] === '') srcPath[0] = result.host;
				else srcPath.unshift(result.host);
			}
			result.host = '';
			if (relative.protocol) {
				relative.hostname = null;
				relative.port = null;
				if (relative.host) {
					if (relPath[0] === '') relPath[0] = relative.host;
					else relPath.unshift(relative.host);
				}
				relative.host = null;
			}
			mustEndAbs = mustEndAbs && (relPath[0] === '' || srcPath[0] === '');
		}

		if (isRelAbs) {
			// it's absolute.
			result.host = (relative.host || relative.host === '') ?
				relative.host : result.host;
			result.hostname = (relative.hostname || relative.hostname === '') ?
				relative.hostname : result.hostname;
			result.search = relative.search;
			result.query = relative.query;
			srcPath = relPath;
			// fall through to the dot-handling below.
		} else if (relPath.length) {
			// it's relative
			// throw away the existing file, and take the new path instead.
			if (!srcPath) srcPath = [];
			srcPath.pop();
			srcPath = srcPath.concat(relPath);
			result.search = relative.search;
			result.query = relative.query;
		} else if (!isNullOrUndefined(relative.search)) {
			// just pull out the search.
			// like href='?foo'.
			// Put this after the other two cases because it simplifies the booleans
			if (psychotic) {
				result.hostname = result.host = srcPath.shift();
				//occationaly the auth can get stuck only in host
				//this especialy happens in cases like
				//url.resolveObject('mailto:local1@domain1', 'local2@domain2')
				var authInHost = result.host && result.host.indexOf('@') > 0 ?
					result.host.split('@') : false;
				if (authInHost) {
					result.auth = authInHost.shift();
					result.host = result.hostname = authInHost.shift();
				}
			}
			result.search = relative.search;
			result.query = relative.query;
			//to support http.request
			if (!isNull(result.pathname) || !isNull(result.search)) {
				result.path = (result.pathname ? result.pathname : '') +
					(result.search ? result.search : '');
			}
			result.href = result.format();
			return result;
		}

		if (!srcPath.length) {
			// no path at all.  easy.
			// we've already handled the other stuff above.
			result.pathname = null;
			//to support http.request
			if (result.search) {
				result.path = '/' + result.search;
			} else {
				result.path = null;
			}
			result.href = result.format();
			return result;
		}

		// if a url ENDs in . or .., then it must get a trailing slash.
		// however, if it ends in anything else non-slashy,
		// then it must NOT get a trailing slash.
		var last = srcPath.slice(-1)[0];
		var hasTrailingSlash = (
		(result.host || relative.host) && (last === '.' || last === '..') ||
		last === '');

		// strip single dots, resolve double dots to parent dir
		// if the path tries to go above the root, `up` ends up > 0
		var up = 0;
		for (var i = srcPath.length; i >= 0; i--) {
			last = srcPath[i];
			if (last == '.') {
				srcPath.splice(i, 1);
			} else if (last === '..') {
				srcPath.splice(i, 1);
				up++;
			} else if (up) {
				srcPath.splice(i, 1);
				up--;
			}
		}

		// if the path is allowed to go above the root, restore leading ..s
		if (!mustEndAbs && !removeAllDots) {
			for (; up--; up) {
				srcPath.unshift('..');
			}
		}

		if (mustEndAbs && srcPath[0] !== '' &&
			(!srcPath[0] || srcPath[0].charAt(0) !== '/')) {
			srcPath.unshift('');
		}

		if (hasTrailingSlash && (srcPath.join('/').substr(-1) !== '/')) {
			srcPath.push('');
		}

		var isAbsolute = srcPath[0] === '' ||
			(srcPath[0] && srcPath[0].charAt(0) === '/');

		// put the host back
		if (psychotic) {
			result.hostname = result.host = isAbsolute ? '' :
				srcPath.length ? srcPath.shift() : '';
			//occationaly the auth can get stuck only in host
			//this especialy happens in cases like
			//url.resolveObject('mailto:local1@domain1', 'local2@domain2')
			var authInHost = result.host && result.host.indexOf('@') > 0 ?
				result.host.split('@') : false;
			if (authInHost) {
				result.auth = authInHost.shift();
				result.host = result.hostname = authInHost.shift();
			}
		}

		mustEndAbs = mustEndAbs || (result.host && srcPath.length);

		if (mustEndAbs && !isAbsolute) {
			srcPath.unshift('');
		}

		if (!srcPath.length) {
			result.pathname = null;
			result.path = null;
		} else {
			result.pathname = srcPath.join('/');
		}

		//to support request.http
		if (!isNull(result.pathname) || !isNull(result.search)) {
			result.path = (result.pathname ? result.pathname : '') +
				(result.search ? result.search : '');
		}
		result.auth = relative.auth || result.auth;
		result.slashes = result.slashes || relative.slashes;
		result.href = result.format();
		return result;
	};

	Url.prototype.parseHost = function() {
		var host = this.host;
		var port = portPattern.exec(host);
		if (port) {
			port = port[0];
			if (port !== ':') {
				this.port = port.substr(1);
			}
			host = host.substr(0, host.length - port.length);
		}
		if (host) this.hostname = host;
	};

	function isString(arg) {
		return typeof arg === "string";
	}

	function isObject(arg) {
		return typeof arg === 'object' && arg !== null;
	}

	function isNull(arg) {
		return arg === null;
	}
	function isNullOrUndefined(arg) {
		return  arg == null;
	}

},{"punycode":19,"querystring":22}]},{},[1]);
