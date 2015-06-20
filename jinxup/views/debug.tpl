<link rel="stylesheet" href="/jinxup/assets/css/fonts.css" media="all" />
<link rel="stylesheet" href="/jinxup/assets/css/error.css" media="all" />
<script src="/jinxup/assets/js/socketcluster.js"></script>
<script src="/jinxup/assets/js/jinxup.js"></script>
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

		$('.jxp-console-trigger').on('click', function (e) {

			e.preventDefault();

			$(this).toggleClass('close');
			$('.jxp-console-dropdown').slideToggle();
		});

		//jinxup.connect();
		//var id = jinxup.id;

		//console.log(id);
		jinxup.join('{!$app.name!}', function(data) {

			console.log(data);
		});

		/*pongChannel.on('subscribeFail', function (err) {
			console.log('Failed to subscribe to PONG channel due to error: ' + err);
		});
		var c = 0;
		pongChannel.watch(function (num) {
			console.log('PONG:', num);
		});*/




		if ("performance" in window) {
			if ("now" in window.performance || "mozNow" in window.performance || "msNow" in window.performance || "oNow" in window.performance || "webkitNow" in window.performance) {
				document.getElementById("result").innerHTML = "Page Performance API supported";

				var start_time = performance.now() || performance.mozNow() || performance.msNow() || performance.oNow() || performance.webkitNow();
				add();
				var end_time = performance.now() || performance.mozNow() || performance.msNow() || performance.oNow() || performance.webkitNow();
				document.getElementById("time_taken").innerHTML = "Time taken to add two numbers is : " + (end_time - start_time);

				document.getElementBy
			}
			else {
				document.getElementById("result").innerHTML = "High Resolution Time API not supported";
			}
		}
		else {
			document.getElementById("result").innerHTML = "Page Performance API not supported";
		}
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
</script>