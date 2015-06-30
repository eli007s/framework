<link rel="stylesheet" href="/jinxup/assets/css/fonts.css" media="all" />
<link rel="stylesheet" href="/jinxup/assets/css/jinxup-console.css" media="all" />
<script src="/jinxup/assets/js/socket.io.js"></script>
<script src="/jinxup/assets/js/jinxup.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/jquery.js"></script>
<div id="jxp-debug-console">
	<div class="jxp-console-wrapper">

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

		jxpConsole = null;
	}
</script>