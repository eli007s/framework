<meta name="viewport" content="width=320, maximum-scale=1.0">
{!if isset($fatalError)!}
	<link href="/jinxup/assets/css/bootstrap.min.css" rel="stylesheet" media="screen">
	<link rel="stylesheet" href="/jinxup/assets/css/style.css" />
{!else!}
	<link rel="stylesheet" href="/jinxup/assets/css/error.css" />
{!/if!}

<link href='//fonts.googleapis.com/css?family=Nunito:400,300,700' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Muli:400,400italic' rel='stylesheet' type='text/css'>
<link href="/jinxup/assets/fonts/font-awesome/css/font-awesome.min.css" rel="stylesheet">
<style type="text/css"></style>
<!--[if lt IE 9]>
<script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
<![endif]-->
<link rel="shortcut icon" href="/jinxup/assets/img/icons/favicon.ico">
<script type="text/javascript" src="/jinxup/assets/js/modernizr-1.0.min.js"></script>
<div class="jxp-error container">
	<div id="wrapper">
		<article class="clearfix">
			<div class="content-wrapper">
				<div class="content active">
					<h2>Oops found some error...</h2>
					{!foreach $errors as $key => $error!}
					<div class="box error {!if isset($fatalError)!}fatal{!/if!} {!if !isset($errors[$key + 1])!}last{!/if!}">
						<div class="error-group">
							<span class="error key">Type: </span>
							<span class="error value">{!$error.type!}</span>
						</div>

						<div class="error-group">
							<span class="error key">Message: </span>
							<span class="error value">{!$error.msg!}</span>
						</div>

						<div class="error-group">
							<span class="error key">File: </span>
							<span class="error value">{!$error.script.name!}</span>
						</div>

						<div class="error-group">
							<span class="error key">Line: </span>
							<span class="error value">{!$error.script.line!}</span>
						</div>
						<div class="line-break"></div>
					</div>
					{!/foreach!}
				</div>
			</div>
		</article>
	</div>
</div>
<script src="/jinxup/assets/js/jquery.js"></script>
<script src="/jinxup/assets/js/bootstrap.min.js"></script>
<script src="/jinxup/assets/js/missing.js"></script>