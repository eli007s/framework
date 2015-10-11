var Jinxup = function() {

	$.ajaxPrefilter(function (options, originalOptions, jqXHR) {

		var token = $('meta[name="csrf-token"]').attr('content');

		if (token) {

			jqXHR.setRequestHeader('X-CSRF-Token', token);
		}
	});

	var _socket      = null;
	var channels     = {};
	var _checkSocket = function() {

		var args = Array.prototype.slice.call(arguments)

		if (args.length >= 1) {

			if (typeof socketCluster == 'object') {

				if (_socket == null) {

					_socket = socketCluster.connect(/*{

					 autoReconnectOptions: {

					 initialDelay: 1000,
					 randomness  : 1000,
					 maxDelay    : 4000
					 }
					 }*/);

					_socket.on('connect', function(data) {

						var socketID = data.id;
						var _data    = [];

						switch (args[0]) {

							case 'emit' :

								_socket.emit(args[1], args[2]);

								break;

							case 'join' :

								channels[args[1]] = _socket.subscribe(args[1]);

								channels[args[1]].watch(args[2]);

								return channels[args[1]];

								break;
						}
					});

					_socket.on('subscribe', function(_data) {

						//console.log(_data);
					});
				}
			}
		}
	};

	return {

		join : function(channel, callback) {

			return _checkSocket('join', channel, callback);
			//console.log(channels);
			//channels['main'].publish('test');
		}
	};
};

var jinxup = new Jinxup();

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