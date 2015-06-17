<meta name="viewport" content="width=320, maximum-scale=1.0">
<link rel="stylesheet" href="/jinxup/assets/css/error.css" media="all" />
<script type="text/javascript" src="/jinxup/assets/js/jquery.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/modernizr.js"></script>
<script type="text/javascript" src="/jinxup/assets/js/jquery.menu-aim.js"></script>
<script>
	jQuery(document).ready(function($){
		//open/close mega-navigation
		$('.cd-dropdown-trigger').on('click', function(event){
			event.preventDefault();
			toggleNav();
		});

		//close meganavigation
		$('.cd-dropdown .cd-close').on('click', function(event){
			event.preventDefault();
			toggleNav();
		});

		//on mobile - open submenu
		$('.has-children').children('a').on('click', function(event){
			//prevent default clicking on direct children of .cd-dropdown-content
			if( $(this).parent('.has-children').parent('.cd-dropdown-content').length > 0 ) event.preventDefault();
			var selected = $(this);
			selected.next('ul').removeClass('is-hidden').end().parent('.has-children').parent('ul').addClass('move-out');
		});

		//on desktop - differentiate between a user trying to hover over a dropdown item vs trying to navigate into a submenu's contents
		var submenuDirection = ( !$('.cd-dropdown-wrapper').hasClass('open-to-left') ) ? 'right' : 'left';
		$('.cd-dropdown-content').menuAim({
			activate: function(row) {
				$(row).children().addClass('is-active').removeClass('fade-out');
				if( $('.cd-dropdown-content .fade-in').length == 0 ) $(row).children('ul').addClass('fade-in');
			},
			deactivate: function(row) {
				$(row).children().removeClass('is-active');
				if( $('li.has-children:hover').length == 0 || $('li.has-children:hover').is($(row)) ) {
					$('.cd-dropdown-content').find('.fade-in').removeClass('fade-in');
					$(row).children('ul').addClass('fade-out')
				}
			},
			exitMenu: function() {
				$('.cd-dropdown-content').find('.is-active').removeClass('is-active');
				return true;
			},
			submenuDirection: submenuDirection,
		});

		//submenu items - go back link
		$('.go-back').on('click', function(){
			var selected = $(this),
					visibleNav = $(this).parent('ul').parent('.has-children').parent('ul');
			selected.parent('ul').addClass('is-hidden').parent('.has-children').parent('ul').removeClass('move-out');
		});

		function toggleNav(){
			var navIsVisible = ( !$('.cd-dropdown').hasClass('dropdown-is-active') ) ? true : false;
			$('.cd-dropdown').toggleClass('dropdown-is-active', navIsVisible);
			$('.cd-dropdown-trigger').toggleClass('dropdown-is-active', navIsVisible);
			if( !navIsVisible ) {
				$('.cd-dropdown').one('webkitTransitionEnd otransitionend oTransitionEnd msTransitionEnd transitionend',function(){
					$('.has-children ul').addClass('is-hidden');
					$('.move-out').removeClass('move-out');
					$('.is-active').removeClass('is-active');
				});
			}
		}

		//IE9 placeholder fallback
		//credits http://www.hagenburger.net/BLOG/HTML5-Input-Placeholder-Fix-With-jQuery.html
		if(!Modernizr.input.placeholder){
			$('[placeholder]').focus(function() {
				var input = $(this);
				if (input.val() == input.attr('placeholder')) {
					input.val('');
				}
			}).blur(function() {
				var input = $(this);
				if (input.val() == '' || input.val() == input.attr('placeholder')) {
					input.val(input.attr('placeholder'));
				}
			}).blur();
			$('[placeholder]').parents('form').submit(function() {
				$(this).find('[placeholder]').each(function() {
					var input = $(this);
					if (input.val() == input.attr('placeholder')) {
						input.val('');
					}
				})
			});
		}
	});
</script>
<header>
	<div class="cd-dropdown-wrapper">
		<a class="cd-dropdown-trigger" href="#0">Debug Console</a>
		<nav class="cd-dropdown">
			<h2>Title</h2>
			<a href="#0" class="cd-close">Close</a>
			<ul class="cd-dropdown-content">
				<li>
					<form class="cd-search">
						<input type="search" placeholder="Search...">
					</form>
				</li>
				<li class="has-children">
					<a href="#">Server Variables</a>

					<ul class="cd-dropdown-icons is-hidden">
						<li class="go-back"><a href="#0">Menu</a></li>
						{!foreach $debug.server as $i => $chunk!}
						<li class="">
							<a href="#">{!if $i == 0!}Server Variables{!/if!}</a>
							<ul class="">
								<li class="go-back"><a href="#0">Server Variables</a></li>
								{!foreach $chunk as $key => $val!}
								<li><a href="#">{!$key!} => {!$val!}</a></li>
								{!/foreach!}
							</ul>
						</li>
						{!/foreach!}
					</ul> <!-- .cd-secondary-dropdown -->
				</li> <!-- .has-children -->

				<li class="has-children">
					<a href="http://codyhouse.co/?p=748">Gallery</a>

					<ul class="cd-dropdown-gallery is-hidden">
						<li class="go-back"><a href="#0">Menu</a></li>
						<li class="see-all"><a href="http://codyhouse.co/?p=748">Browse Gallery</a></li>
						<li>
							<a class="cd-dropdown-item" href="http://codyhouse.co/?p=748">
								<img src="img/img.png" alt="Product Image">
								<h3>Product #1</h3>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item" href="http://codyhouse.co/?p=748">
								<img src="img/img.png" alt="Product Image">
								<h3>Product #2</h3>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item" href="http://codyhouse.co/?p=748">
								<img src="img/img.png" alt="Product Image">
								<h3>Product #3</h3>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item" href="http://codyhouse.co/?p=748">
								<img src="img/img.png" alt="Product Image">
								<h3>Product #4</h3>
							</a>
						</li>
					</ul> <!-- .cd-dropdown-gallery -->
				</li> <!-- .has-children -->

				<li class="has-children">
					<a href="http://codyhouse.co/?p=748">Services</a>
					<ul class="cd-dropdown-icons is-hidden">
						<li class="go-back"><a href="#0">Menu</a></li>
						<li class="see-all"><a href="http://codyhouse.co/?p=748">Browse Services</a></li>
						<li>
							<a class="cd-dropdown-item item-1" href="http://codyhouse.co/?p=748">
								<h3>Service #1</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-2" href="http://codyhouse.co/?p=748">
								<h3>Service #2</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-3" href="http://codyhouse.co/?p=748">
								<h3>Service #3</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-4" href="http://codyhouse.co/?p=748">
								<h3>Service #4</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-5" href="http://codyhouse.co/?p=748">
								<h3>Service #5</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-6" href="http://codyhouse.co/?p=748">
								<h3>Service #6</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-7" href="http://codyhouse.co/?p=748">
								<h3>Service #7</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-8" href="http://codyhouse.co/?p=748">
								<h3>Service #8</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-9" href="http://codyhouse.co/?p=748">
								<h3>Service #9</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-10" href="http://codyhouse.co/?p=748">
								<h3>Service #10</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-11" href="http://codyhouse.co/?p=748">
								<h3>Service #11</h3>
								<p>This is the item description</p>
							</a>
						</li>

						<li>
							<a class="cd-dropdown-item item-12" href="http://codyhouse.co/?p=748">
								<h3>Service #12</h3>
								<p>This is the item description</p>
							</a>
						</li>

					</ul> <!-- .cd-dropdown-icons -->
				</li> <!-- .has-children -->

				<li class="cd-divider">Divider</li>

				<li><a href="http://codyhouse.co/?p=748">Page 1</a></li>
				<li><a href="http://codyhouse.co/?p=748">Page 2</a></li>
				<li><a href="http://codyhouse.co/?p=748">Page 3</a></li>
			</ul> <!-- .cd-dropdown-content -->
		</nav> <!-- .cd-dropdown -->
	</div> <!-- .cd-dropdown-wrapper -->
</header>