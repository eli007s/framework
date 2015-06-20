var Jinxup = function(data) {

	$.ajaxPrefilter(function (options, originalOptions, jqXHR) {

		var token = $('meta[name="csrf-token"]').attr('content');

		if (token) {

			jqXHR.setRequestHeader('X-CSRF-Token', token);
		}
	});

	var _thisUser, _channels = {}, _socket = null;

	var _checkSocket = function() {
		console.log(socketCluser);
		if (typeof socketCluser == 'object') {
console.log(typeof _socket);
			if (typeof _socket != null) {

				_socket = socketCluster.connect(function(data) {

					console.log(data);
				});

				console.log(_socket);

				_socket.on('connect', function(data) {

					_thisUser = data.id;
				});
			}

			return true;

		} else {

			return false;
		}
	};

	var _join = function(channel, callback) {

		if (_checkSocket()) {

			if (typeof _channels[channel] == 'undefined') {

				//_channels[channel] = _socket.subscribe(channel);

				//_socket.watch(channel, callback);
			}
		}
	};

	var _on = function(event, callback) {

		if (_checkSocket) {

			if (event == 'connect') {

				_socket.on(event, callback);
			}
		}
	};

	return {

		id : _thisUser,

		on : function(event, callback) {

			_on(event, callback);
		},

		join : function(channel, callback) {

			_join(channel, callback);
		}
	};
};

var jinxup = new Jinxup({

	data : { hello : 'world' }
});

/*
 var socket = socketCluster.connect();
 socket.on('error', function (err) {
 throw 'Socket error - ' + err;
 });
 socket.on('connect', function () {
 console.log('CONNECTED');
 });

 socket.on('rand', function (data) {
 console.log('RANDOM STREAM: ' + data.rand);
 });
 var pongChannel = socket.subscribe('pong');
 pongChannel.on('subscribeFail', function (err) {
 console.log('Failed to subscribe to PONG channel due to error: ' + err);
 });
 var c = 0;
 pongChannel.watch(function (num) {
 console.log('PONG:', num);
 });
 */