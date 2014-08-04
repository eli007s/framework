/* CUSTOM PARAMETERS */

/* ==================================  GLOBAL  ==================================== */

			var main_color = "323a45"; // Dark Grey
			
			var page_background_color = "FDFDFD";//"dfe3e9";
			
			var color2 = "f9fafc"; // Header Light Grey
			
			var color3 = "9ea7b3"; // Grey
			
			var Heading_Font = "Nunito"; // thats your special Heading font
			
			var Site_Font = "Muli"; // thats your special body content font

// Google Web Fonts

WebFontConfig = {
	google: { families: [ 'Nunito:400,300,700:latin', 'Muli:400,400italic:latin'] }
};

(function() {
	var wf = document.createElement('script');

	wf.src = ('https:' == document.location.protocol ? 'https' : 'http') + '://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js';
	wf.type = 'text/javascript';
	wf.async = 'true';

	var s = document.getElementsByTagName('script')[0];

	s.parentNode.insertBefore(wf, s);
})();
 
