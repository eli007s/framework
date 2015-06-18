<link rel="stylesheet" href="/jinxup/assets/css/fonts.css" media="all" />
<link rel="stylesheet" href="/jinxup/assets/css/error.css" media="all" />
<script type="text/javascript" src="/jinxup/assets/js/jquery.js"></script>
<div id="jxp-debug-console">
	<div class="jxp-console-wrapper">
		<a class="jxp-console-trigger" href="#">App Console | Controller : {!$app.controller!} | Action : {!$app.action!}</a>
		<div class="jxp-console-dropdown">
			<ul class="jxp-console-dropdown-content">
				<li><a href="#">Server Variables</a></li>
				<li><a href="#">Cookies</a></li>
				<li><a href="#">Sessions</a></li>
				<li><a href="#">Queries</a></li>
				<li><a href="#">Template Variables</a></li>
				<li><a href="#">Application Errors</a></li>
				<li>
					<div id="result"></div>
					<div id="total_download_time"></div>
					<div id="total_render_time"></div>
					<div id="time_taken"></div>
				</li>
			</ul>
			<div class="jxp-console-content">
				<div id="server-variables">
					{!foreach $debug.server as $key => $server!}
					<div class="column">
						<ul>
							{!foreach $server as $key => $val!}
							<li>
								<!--<span class="key">{!$key!}</span>
								<span class="val">{!$val!}</span>-->
							</li>
							{!/foreach!}
						</ul>
					</div>
					{!/foreach!}
				</div>
				<div id="cookies">

				</div>
				<div id="sessions">

				</div>
				<div id="queries">

				</div>
				<div id="template-vars">

				</div>
				<div id="errors">

				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	if (typeof jQuery == 'undefined') {
		
		var script = document.createElement('script');

		script.src = 'http://code.jquery.com/jquery-1.10.2.min.js';

		var head = document.getElementsByTagName('head')[0], done = false;

		head.appendChild(script);

		script.onload = script.onreadystatechange = function() {

			if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) {

				done = true;

				jinxupFrameworkDebugConsole($.noConflict(true));

				script.onload = script.onreadystatechange = null;

				head.removeChild(script);
			}
		};

	} else {

		jinxupFrameworkDebugConsole($);
	}

	function jinxupFrameworkDebugConsole($) {

		var jxpConsole = $('#jxp-debug-console').html();

		$('#jxp-debug-console').remove();
		$('body').prepend('<div id="jxp-debug-console">' + jxpConsole + '</div>');

		$('.jxp-console-dropdown').hide();

		$('.jxp-console-trigger').on('click', function(e) {

			e.preventDefault();

			$(this).toggleClass('close');
			$('.jxp-console-dropdown').slideToggle();
		});

		// just put the token as the request header, will work for all GET, PUT, POST ect...
		$.ajaxPrefilter( function( options, originalOptions, jqXHR ) {

			var token = $('meta[name="csrf-token"]').attr('content');

			if (token) {

				jqXHR.setRequestHeader('X-CSRF-Token', token);
			}
		});

		if("performance" in window)
		{
			if("now" in window.performance || "mozNow" in window.performance || "msNow" in window.performance || "oNow" in window.performance || "webkitNow" in window.performance)
			{
				document.getElementById("result").innerHTML = "Page Performance API supported";

				var start_time = performance.now() || performance.mozNow() || performance.msNow() || performance.oNow() || performance.webkitNow();
				add();
				var end_time = performance.now() || performance.mozNow() || performance.msNow() || performance.oNow() || performance.webkitNow();
				document.getElementById("time_taken").innerHTML = "Time taken to add two numbers is : " + (end_time - start_time);

				document.getElementBy
			}
			else
			{
				document.getElementById("result").innerHTML = "High Resolution Time API not supported";
			}
		}
		else
		{
			document.getElementById("result").innerHTML = "Page Performance API not supported";
		}

		var comet = new iComet({
			channel: 'abc',
			signUrl: 'http://52.8.189.179:8000/sign',
			subUrl: 'http://52.8.189.179:8100/sub',
			callback: function(content){
				// on server push
				alert(content);
			}
		});
	}

	function simplePerf() {
		var pe = performance.getEntries();
		for (var i = 0; i < pe.length; i++) {
			if (window.console) console.log("Name: " + pe[i].name +
					" Start Time: " + pe[i].startTime +
					" Duration: " + pe[i].duration + "\n");
		}
	}
	function add()
	{
		for(var i = 0; i < 1000000; i++)
		{
			//some overhead
		}
		return 12 + 89;
	}

	function iComet(config){
		var self = this;
		if(iComet.id__ == undefined){
			iComet.id__ = 0;
		}
		config.sub_url = config.sub_url || config.subUrl;
		config.pub_url = config.pub_url || config.pubUrl;
		config.sign_url = config.sign_url || config.signUrl;

		self.cname = config.channel;
		self.sub_cb = function(msg){
			var cb = config.callback || config.sub_callback;
			if(cb){
				try{
					cb(msg.content);
				}catch(e){
					self.log(e);
				}
			}
		}
		self.sub_timeout = config.sub_timeout || (60 * 1000);

		self.id = iComet.id__++;
		self.cb = 'icomet_cb_' + self.id;
		self.timer = null;
		self.sign_timer = null;
		self.stopped = true;
		self.last_sub_time = 0;
		self.need_fast_reconnect = true;
		self.token = '';

		self.data_seq = 0;
		self.noop_seq = 0;
		self.sign_cb = null;

		self.pub_url = config.pub_url;
		if(config.sub_url.indexOf('?') == -1){
			self.sub_url = config.sub_url + '?';
		}else{
			self.sub_url = config.sub_url + '&';
		}
		self.sub_url += 'cb=' + self.cb;
		if(config.sign_url){
			if(config.sign_url.indexOf('?') == -1){
				self.sign_url = config.sign_url + '?';
			}else{
				self.sign_url = config.sign_url + '&';
			}
			self.sign_url += 'cb=' + self.cb + '&cname=' + self.cname;
		}

		window[self.cb] = function(msg, in_batch){
			// batch response
			if(msg instanceof Array){
				self.log('batch response', msg.length);
				for(var i in msg){
					if(msg[i] && msg[i].type == 'data'){
						if(i == msg.length - 1){
							window[self.cb](msg[i]);
						}else{
							window[self.cb](msg[i], true);
						}
					}
				}
				return;
			}
			//self.log('resp', msg);
			if(self.stopped){
				return;
			}
			if(!msg){
				return;
			}
			if(msg.type == '404'){
				self.log('resp', msg);
				// TODO channel id error!
				alert('channel not exists!');
				return;
			}
			if(msg.type == '401'){
				// TODO token error!
				self.log('resp', msg);
				alert('token error!');
				return;
			}
			if(msg.type == '429'){
				//alert('too many connections');
				self.log('resp', msg);
				setTimeout(self_sub, 5000 + Math.random() * 5000);
				return;
			}
			if(msg.type == 'sign'){
				self.log('proc', msg);
				if(self.sign_cb){
					self.sign_cb(msg);
				}
				return;
			}
			if(msg.type == 'noop'){
				self.last_sub_time = (new Date()).getTime();
				if(msg.seq == self.noop_seq){
					self.log('proc', msg);
					if(self.noop_seq == 2147483647){
						self.noop_seq = -2147483648;
					}else{
						self.noop_seq ++;
					}
					// if the channel is empty, it is probably empty next time,
					// so pause some seconds before sub again
					setTimeout(self_sub, 1000 + Math.random() * 2000);
				}else{
					// we have created more than one connection, ignore it
					self.log('ignore exceeded connections');
				}
				return;
			}
			if(msg.type == 'next_seq'){
				self.log('proc', msg);
				self.data_seq = msg.seq;
				self_sub();
			}
			if(msg.type == 'data'){
				self.last_sub_time = (new Date()).getTime();
				if(msg.seq != self.data_seq){
					if(msg.seq == 0 || msg.seq == 1){
						self.log('server restarted');
						// TODO: lost_cb(msg);
						self.sub_cb(msg);
					}else if(msg.seq < self.data_seq){
						self.log('drop', msg);
					}else{
						self.log('msg lost', msg);
						// TODO: lost_cb(msg);
						self.sub_cb(msg);
					}

					self.data_seq = msg.seq;
					if(self.data_seq == 2147483647){
						self.data_seq = -2147483648;
					}else{
						self.data_seq ++;
					}
					if(!in_batch){
						// fast reconnect
						var now = new Date().getTime();
						if(self.need_fast_reconnect || now - self.last_sub_time > 3 * 1000){
							self.log('fast reconnect');
							self.need_fast_reconnect = false;
							self_sub();
						}
					}
				}else{
					self.log('proc', msg);
					if(self.data_seq == 2147483647){
						self.data_seq = -2147483648;
					}else{
						self.data_seq ++;
					}
					self.sub_cb(msg);
					if(!in_batch){
						self_sub();
					}
				}
				return;
			}
		}

		self.sign = function(callback){
			self.log('sign in icomet server...');
			self.sign_cb = callback;
			var url = self.sign_url + '&_=' + new Date().getTime();
			$.ajax({
				url: url,
				dataType: "jsonp",
				jsonpCallback: "cb"
			});
		}

		var self_sub = function(){
			self.stopped = false;
			self.last_sub_time = (new Date()).getTime();
			$('script.' + self.cb).remove();
			var url = self.sub_url
					+ '&cname=' + self.cname
					+ '&seq=' + self.data_seq
					+ '&noop=' + self.noop_seq
					+ '&token=' + self.token
					+ '&_=' + new Date().getTime();
			self.log('sub ' + url);
			$.ajax({
				url: url,
				dataType: "jsonp",
				jsonpCallback: "cb"
			});
		}

		self.start = function(){
			self.stopped = false;
			if(self.timer){
				clearTimeout(self.timer);
				self.timer = null;
			}
			if(self.sign_url){
				if(!self.sign_timer){
					self.sign_timer = setInterval(self.start, 3000 + Math.random() * 2000);
				}
				self.sign(function(msg){
					if(self.sign_timer){
						clearTimeout(self.sign_timer);
						self.sign_timer = null;
					}else{
						return;
					}
					if(!self.stopped){
						self.cname = msg.cname;
						self.token = msg.token;
						try{
							var a = parseInt(msg.sub_timeout) || 0;
							self.sub_timeout = (a * 1.2) * 1000;
						}catch(e){}
						self.log('start sub ' + self.cname + ', seq=' + self.data_seq + ', timeout=' + self.sub_timeout + 'ms');
						self._start_timeout_checker();
						self_sub();
					}
				});
			}else{
				self.log('start sub ' + self.cname + ', seq=' + self.data_seq + ', timeout=' + self.sub_timeout + 'ms');
				self._start_timeout_checker();
				self_sub();
			}
		}

		self.stop = function(){
			self.stopped = true;
			self.last_sub_time = 0;
			self.need_fast_reconnect = true;
			if(self.timer){
				clearTimeout(self.timer);
				self.timer = null;
			}
			if(self.sign_timer){
				clearTimeout(self.sign_timer);
				self.sign_timer = null;
			}
		}

		self._start_timeout_checker = function(){
			if(self.timer){
				clearTimeout(self.timer);
			}
			self.timer = setInterval(function(){
				var now = (new Date()).getTime();
				if(now - self.last_sub_time > self.sub_timeout){
					self.log('timeout');
					self.stop();
					self.start();
				}
			}, 1000);
		}

		// msg must be string
		self.pub = function(content, callback){
			if(typeof(content) != 'string' || !self.pub_url){
				alert(self.pub_url);
				return false;
			}
			if(callback == undefined){
				callback = function(){};
			}
			var data = {};
			data.cname = self.cname;
			data.content = content;

			$.getJSON(self.pub_url, data, callback);
		}

		self.log = function(){
			try{
				var v = arguments;
				var p = 'icomet[' + self.id + ']';
				var t = new Date().toTimeString().substr(0, 8);
				if(v.length == 1){
					console.log(t, p, v[0]);
				}else if(v.length == 2){
					console.log(t, p, v[0], v[1]);
				}else if(v.length == 3){
					console.log(t, p, v[0], v[1], v[2]);
				}else if(v.length == 4){
					console.log(t, p, v[0], v[1], v[2], v[3]);
				}else if(v.length == 5){
					console.log(t, p, v[0], v[1], v[2], v[3], v[4]);
				}else{
					console.log(t, p, v);
				}
			}catch(e){}
		}

		self.start();

	}
</script>