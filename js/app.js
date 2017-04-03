$(document).foundation();

// Any code tag with the class name "plate" can be clicked to add plate data to plate input field
$.fn.plateClick = function() {
    return this.each(function() {
		$(this).on("click", function() {
			var VAL = $(this).text();
			$("#plate").val(VAL).trigger("keyup");
		});
    });
};

$(document).ready(function() {
	// Update server status flags (using Foundation class names success|alert)
	$.ajax({
		url: '_status.php', 
		dataType: 'json', 
		success: function(json) {
			$.each(json,function(key,status) {
				$("#" + key).fadeOut('fast', function() {
					$(this).addClass(status).fadeIn('slow');
				});
			});
		}
	});
	
	// Automatically focus on first empty input field in all forms
	$('form').find('*').filter(":input[value='']:visible:first").focus();
	
	$("#user_email").on("paste, keyup", function() {
		var VAL = this.value;
		var email = new RegExp('^[a-zA-Z-\.]+@scilifelab\.se$');
		if(email.test(VAL)) {
			$("#plate").focus();
		}
	});

	// Any code tag with the class name "position" can be clicked to add position data to position input field
	$("code.position").on("click", function() {
		var VAL = $(this).text();
		$("#position").val(VAL).trigger("keyup");
	});

	$("code.plate").plateClick();

	// Automatically trigger visualization of storage units and racks depending on what's entered in position input field
	$("#position").on("paste, keyup", function() {
		var VAL = this.value;
		var position_storage = new RegExp('^S[0-9]{4}$');
		var position_rack = new RegExp('^R[0-9]{4}_$');
		var position_full = new RegExp('^R[0-9]{4}X[0-9]{2}Y[0-9]{2}$');
		
		if(position_storage.test(VAL) || position_rack.test(VAL) || position_full.test(VAL)) {
			var request=$.ajax({
				url: "_rackview.php", 
				method: "POST", 
				data: { position : VAL }, 
				dataType: "html"
			});
		
			request.done(function(msg) {
				$("#query_data").html(msg);
				$("table.rack td").not(".full").click(function() {
					$("table.rack td").removeClass("selected");
					$(this).addClass("selected");
					var VAL=$(this).attr("data-position");
					$("#position").val(VAL).trigger("keyup");
				});
			});
		} else {
			$("#query_data").html("");
		}
	});

	$("#plate").on("paste, keyup", function() {
		var VAL = this.value;
		
		if(VAL.length>3) {
			var request=$.ajax({
				url: "_platesearch.php", 
				method: "POST", 
				data: { query : VAL }, 
				dataType: "html"
			});
		
			request.done(function(msg) {
				$("#query_data").html(msg);
				$("code.plate").plateClick();
			});
		} else {
			$("#query_data").html("");
		}
	});
});
