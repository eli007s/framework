// JavaScript Document
//Author Name: Saptarang
//Author URI: http://www.saptarang.org
//Themeforest: http://themeforest.net/user/saptarang?ref=Saptarang
//Creation Date: 18th January, 2014
//Description: A script stylesheet for Missing - Responsive 404 Page Template Designed & Developed By Saptarang.


$(document).ready(function() {
	
	// Load Animation
	$('body').delay(500).animate({opacity:'1'}, 900);
	
	// Global Color
	$('head style').append('h1, h1a, h2, h2 a, h3, h3 a, h4, h4 a, h5, h5 a, h6, h6 a  {font-family:"'+Heading_Font+'"; } html, body, div, p, table, tr, td, th, tbody, tfoot, ul, li, ol, dl, dd, dt, fieldset, cite, input, select, textarea, button, a, section, article, aside, header, footer, nav {font-family:"'+Site_Font+'"; }  body {background-color:#'+page_background_color+';} .tabs li a:hover, #contact-form .btn {background-color:#'+main_color+'; }  h1 {color:#'+main_color+'} header, footer {background-color:#'+color2+';}  .form-dark, span.section-icon {background-color:#'+color3+';} ::selection {background-color:#'+main_color+'; color:#fff;} ::-moz-selection {background-color:#'+main_color+'; color:#fff;} ');

	
	// Tooltip
	$('a.tips').tooltip();
	
	// tabs
	$('.tab-content-wrapper .tb-content').not('.tab-content-wrapper .tb-content:first').hide();
	$('.tabs li a[href^=#]').click(function(event) {
			event.preventDefault();
			$(this.hash).prev().delay(100).slideUp('slow');
			$('.tb-content').delay(200).slideUp('slow');
			$(this.hash).delay(300).slideDown('slow');	
	});
	
	
	// Year Update
	var curYear = new Date().getFullYear();
	$('.year').html(curYear);
	
	// Subscription Form Validation
	   $("#subscribe-form input").focus(function() {
		  $(this).prev("label").hide();
		  $(this).prev().prev("label").hide();	 		 	
	  });
	   
	  $("#subscribe-form").submit(function() {
		  // validate and process form here
		  var emailSubscribe = $("#emailSubscribe").val();
		  if (emailSubscribe == "") {
				$('#emailSubscribe').addClass('reqfld');
				$('<span class="error" style="display:none;"><i class="fa fa-exclamation-circle"></i> Enter Your Email Address</span>').insertBefore('#subscribe-form .input-group').fadeIn(400);
				$("#emailSubscribe").focus(function() {  $('#emailSubscribe').removeClass('reqfld');  $(this).parent().prev().fadeOut(400);});
				return false;
		   } else if(emailSubscribe.indexOf('@') == -1 || emailSubscribe.indexOf('.') == -1) {
				$('#emailSubscribe').addClass('reqfld');
				$('<span class="error" style="display:none;">Invalid!</span>').insertBefore('#subscribe-form .input-group').fadeIn(400);
				$("#emailSubscribe").focus(function() {  $('#emailSubscribe').removeClass('reqfld');  $(this).parent().prev().fadeOut(400);});
				return false;
		  }
		  
		  var dataString = 'emailSubscribe=' + emailSubscribe;
		  $.ajax({
			type: "POST",
			url: "form/subscribe.php",
			data: dataString,
			success: function() {
			  $("#subscribe-form").hide();
			  $("<div id='subscribesuccess' class='alert alert-success' style='margin:2em; border:#"+sub_successBox_Border_Color+" 1px "+sub_successBoxBorderStyle+"; background:#"+sub_successBoxColor+";' ></div>").insertAfter('#subscribe-form');
			  $('#subscribesuccess').html("<h5 style='color:#"+sub_textColor+";'>"+sub_submitMessage+"</h5>")
			  .hide().delay(300)
			  .fadeIn(1500);
			  
			  $('#subscribe-form').delay(6000).slideUp('fast');
			  
			}
		  });
		  return false;
	});	
	
	// contact Form
	   $('.loader').hide();
	   $("#contact-form input, #contact-form textarea").focus(function() {
		  $(this).prev("label").hide();
		  $(this).prev().prev("label").hide();	 		 	
	  });
	   
	  $("#contact-form").submit(function() {
				// validate and process form here
				var name = $("#name").val();
					  if (name == "") {
					  $('#name').addClass('reqfld');
					  $('<span class="error" style="display:none; margin-top:0px;"><i class="fa fa-exclamation-circle"></i></span>').insertBefore('#name').fadeIn(400);
					  $("#name").focus(function() {  $('#name').removeClass('reqfld');  $(this).prev().fadeOut(400);});
					  return false;
				} 
				  
				var phone = $("#phone").val();
					  if (phone == "") {
					  $('#phone').addClass('reqfld');
					  $('<span class="error" style="display:none;"><i class="fa fa-exclamation-circle"></i></span>').insertBefore('#phone').fadeIn(400);
					  $("#phone").focus(function() {  $('#phone').removeClass('reqfld');  $(this).prev().fadeOut(400);});
					  return false;
				}
				
				var email = $("#email").val();
				if (email == "") {
					  $('#email').addClass('reqfld');
					  $('<span class="error" style="display:none;"><i class="fa fa-exclamation-circle"></i></span>').insertBefore('#email').fadeIn(400);
					  $("#email").focus(function() {  $('#email').removeClass('reqfld');  $(this).prev().fadeOut(400);});
					  return false;
				 } else if(email.indexOf('@') == -1 || email.indexOf('.') == -1) {
					  $('#email').addClass('reqfld');
					  $('<span class="error" style="display:none;">Invalid!</span>').insertBefore('#email').fadeIn(400);
					  $("#email").focus(function() {  $('#email').removeClass('reqfld');  $(this).prev().fadeOut(400);});
					  $("#email").keyup(function() {  $('#email').removeClass('reqfld');  $(this).prev().delay(400).fadeOut(400);});
					  return false;
				}
				
				var comment = $("#comment").val();
					  if (comment == "") {
					  $('#comment').addClass('reqfld');
					  $('<span class="error" style="display:none;"><i class="fa fa-exclamation-circle"></i></span>').insertBefore('#comment').fadeIn(400);
					  $("#comment").focus(function() {  $('#comment').removeClass('reqfld');  $(this).prev().fadeOut(400);});
					  return false;
				}
				
				$('#contact-form').animate({opacity:'0.3'}, 500);
				
				var dataString = 'name='+ name + '&email=' + email + '&phone=' + phone + '&comment=' + comment;
				$.ajax({
				  type: "POST",
				  url: "form/contact.php",
				  data: dataString,
				  success: function() {
					$("#contact-form").animate({opacity:'1'}, 500);
					$('.loader').hide();
					$("<div id='success' class='alert alert-success' style='border:#"+successBox_Border_Color+" 1px "+successBoxBorderStyle+"; background:#"+successBoxColor+";' ></div>").insertAfter('.field-wrapper');
					$('.field-wrapper').slideUp(300);
					$('#success').html("<h5 style='color:#"+textColor+";'>"+submitMessage+"</h5><p style='color:#"+textColor+";'>"+successParagraph+"</p>")
					.hide().delay(300)
					.fadeIn(1500);
					
					$('#contact-form').delay(6000).slideUp('fast');
					
				  }
				});
				return false;
	  });
	  
	  
});
