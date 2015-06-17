<meta name="viewport" content="width=320, maximum-scale=1.0">
<link rel="stylesheet" href="/jinxup/assets/css/error.css" media="all" />
<script type="text/javascript" src="/jinxup/assets/js/jquery.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/modernizr.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/jquery.menu-aim.js"></script>
<script type="text/javascript">
	/*jQuery(document).ready(function($){

		var jxpConsole = $('#jxp-debug-console').html();

		$('#jxp-debug-console').remove();
		$('body').prepend('<div id="jxp-debug-console">' + jxpConsole + '</div>');

		$('.dropdown-trigger').on('click', function(event){
			event.preventDefault();
			toggleNav();
		});

		$('.dropdown .close').on('click', function(event){
			event.preventDefault();
			toggleNav();
		});

		$('.has-children').children('a').on('click', function(event){

			if( $(this).parent('.has-children').parent('.dropdown-content').length > 0 ) event.preventDefault();
			var selected = $(this);
			selected.next('ul').removeClass('is-hidden').end().parent('.has-children').parent('ul').addClass('move-out');
		});

		var submenuDirection = (!$('.dropdown-wrapper').hasClass('open-to-left')) ? 'right' : 'left';

		$('.dropdown-content').menuAim({
			activate: function(row) {
				$(row).children().addClass('is-active').removeClass('fade-out');
				if( $('.dropdown-content .fade-in').length == 0 ) $(row).children('ul').addClass('fade-in');
			},
			deactivate: function(row) {
				$(row).children().removeClass('is-active');
				if( $('li.has-children:hover').length == 0 || $('li.has-children:hover').is($(row)) ) {
					$('.dropdown-content').find('.fade-in').removeClass('fade-in');
					$(row).children('ul').addClass('fade-out')
				}
			},
			exitMenu: function() {
				$('.dropdown-content').find('.is-active').removeClass('is-active');
				return true;
			},
			submenuDirection: submenuDirection
		});

		function toggleNav() {

			var navIsVisible = (!$('.dropdown').hasClass('dropdown-is-active')) ? true : false;

			$('.dropdown').toggleClass('dropdown-is-active', navIsVisible);
			$('.dropdown-trigger').toggleClass('dropdown-is-active', navIsVisible);

			if (!navIsVisible) {

				$('.dropdown').one('webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend', function() {

					$('.has-children ul').addClass('is-hidden');
					$('.move-out').removeClass('move-out');
					$('.is-active').removeClass('is-active');
				});
			}
		}
	});*/
</script>
<div id="jxp-debug-console">
	<div class="jxp-console-wrapper">
		<a class="jxp-console-dropdown-trigger" href="#">Debug Console</a>
		<div class="jxp-console-dropdown">
			<ul class="jxp-console-dropdown-content">
				<li><a href="#">Server Variables</a></li>
				<li><a href="#">Cookies</a></li>
				<li><a href="#">Sessions</a></li>
				<li><a href="#">Queries</a></li>
				<li><a href="#">Template Variables</a></li>
				<li><a href="#">Application Errors</a></li>
			</ul>
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

				jinxupFrameworkDebugConsole();

				script.onload = script.onreadystatechange = null;

				head.removeChild(script);
			}
		};

	} else {

		jinxupFrameworkDebugConsole();
	}

	function jinxupFrameworkDebugConsole() {

		var _$         = $.noConflict(true);
		var jxpConsole = _$('#jxp-debug-console').html();

		_$('#jxp-debug-console').remove();
		_$('body').prepend('<div id="jxp-debug-console">' + jxpConsole + '</div>');

		_$('.dropdown-content').menuAim({
			activate: function(row) {
				_$(row).children().addClass('is-active').removeClass('fade-out');
				if( _$('.dropdown-content .fade-in').length == 0 ) _$(row).children('ul').addClass('fade-in');
			},
			deactivate: function(row) {
				_$(row).children().removeClass('is-active');
				if( _$('li.has-children:hover').length == 0 || _$('li.has-children:hover').is(_$(row)) ) {
					_$('.dropdown-content').find('.fade-in').removeClass('fade-in');
					_$(row).children('ul').addClass('fade-out')
				}
			},
			exitMenu: function() {
				_$('.dropdown-content').find('.is-active').removeClass('is-active');
				return true;
			},
			submenuDirection: submenuDirection
		});
	}
</script>