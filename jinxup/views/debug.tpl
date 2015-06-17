<meta name="viewport" content="width=320, maximum-scale=1.0">
<link rel="stylesheet" href="/jinxup/assets/css/error.css" media="all" />
<script type="text/javascript" src="/jinxup/assets/js/jquery.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/modernizr.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/jquery.menu-aim.js"></script>
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
			</ul>
			<div class="jxp-console-content">
				<div id="server-variables">
					{!foreach $debug.server as $key => $server!}
					<div class="column">
						<ul>
							{!foreach $server as $key => $val!}
							<li>
								<span class="key">{!$key!}</span>
								<span class="val">{!$val!}</span>
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
	}
</script>