/*
 * Global JavaScript functions, when grouped functions are placed in seperate files this should get smaller..
 * @license Creative Commons by-nc-sa
 * @author Jip Moors <j.moors@home.nl>
 * @version 0.1 - functions need to be sorted and commented
 */

var game;
var chat;

var currentGameType = -1;

function Status() {
	this.gameServer = -1;
	this.chatServer = -1;
}
var status = new Status();


function aferLoading() {
	if(openInvite) {
		showRegister();
		openInvite = false;
	}
}

function clMouse(object) {
	this.id = object;
	this.x = 0;									// mouse position
	this.y = 0;									// mouse position
}
var mouse = new clMouse("mouse");

$(window).keydown(function(event){
	switch (event.keyCode) {
		case 27:	// Esc
			if(showingMessage) {
				hideMessage();
			} else if(showingForm) {
				hideForm();
			}
			
			break;
	}
});

function logout() {
	location.href = './?do=logout';
}

function showPopup(text) {
	// enter text
	// show popup at mouse
	$('#popup').html(text);
	
	$('#popup').css("left", mouse.x - 90);
	$('#popup').css("top", mouse.y + 18);
	
	$('#popup').show();
}

function hidePopup() {
	$('#popup').hide();
}

function togglePractise() {
	// force disabled the config (if opened)
	configActive = false;
	
	if(currentGameType == 2) {
		createGameFlash('single');
	} else {
		createGameFlash('multi');
	}
}

/*
 * Creating the flash items
 */
function createGameFlash(gameType) {
	if(configActive) return;
	
	if(forceSingle == 1 || status.gameServer == 0) {
		gameType = 'single';
	}
	
	newGameType = (gameType == 'multi')?2:1;
	if(newGameType == currentGameType) return;
	
	if(gameType == 'single') {
		gameAsMain();
		
		if(status.gameServer == 1) {
			$('#practise').attr('value', ' multiplayer ');
			
			if(!forceSingle) {
				$('#practise').removeAttr('disabled');
			}
		} else {
			$('#practise').attr('value', ' multiplayer ');
			$('#practise').attr('disabled', 'disabled');
		}
	} else {
		if(status.chatServer == 1) {
			chatAsMain();
		}
		
		$('#practise').attr('value', ' oefenen ');
		$('#practise').removeAttr('disabled');
	}
	
	// removing old flash:
	$('#'+gameDiv).html('<div id="'+gameHolder+'"></div>');
	createFlash(gameType, gameHolder);
	
	currentGameType = newGameType;
}

var chatLoaded = false;
function createChatFlash() {
	// if the chat is already loaded, skip this
	if(chatLoaded) return;
	chatLoaded = true;
	
	createFlash("chat", chatHolder);
	
	if(currentGameType == 2 && !inGame) {
		chatAsMain();
	}
}

var configActive = false;
function createConfigFlash() {
	return;
	
	if(configActive) return;
	configActive = true;
	
	gameAsMain();
	
	// removing old flash:
	$('#'+gameDiv).html('<div id="'+gameHolder+'"></div>');
	createFlash("settings", gameHolder);
}

function closeSettings() {
	configActive = false;
	
	chatAsMain();
	
	lastType = currentGameType;
	currentGameType = 0;
	
	if(lastType == 1) {
		createGameFlash('multi');
	} else {
		createGameFlash('single');
		gameAsMain();
	}
}

/*
 * Switch positions of the flash objects
 */
function gameAsMain() {
	if($('#'+gameDiv).css('left') == '265px') {
		return;
	}
	
	game = $('#'+gameDiv);
	game.css("left", "265px");
	game.css("width", "450px");
	game.css("height", "300px");

	chat = $('#'+chatDiv);
	chat.css("width", "200px");
	chat.css("height", "300px");
	chat.css("left", "745px");
}

function chatAsMain() {
	if(!chatLoaded) return;
	if(configActive) return;
	if(forceSingle == 1) return;
	
	if($('#'+chatDiv).css('left') == '265px') {
		return;
	}
	
	chat = $('#'+chatDiv);
	chat.css("left", "265px");
	chat.css("width", "450px");
	chat.css("height", "300px");
	
	game = $('#'+gameDiv);
	game.css("left", "745px");
	game.css("width", "200px");
	game.css("height", "300px");
}

/*
 * Updating the player info through AJAX, calling itself at set interval
 */
var undefined;
var playerInfoTimer;
function updatePlayerInfo() {
	if(playerInfoTimer == undefined) {
		playerInfoTimer = setInterval('updatePlayerInfo()', 15000);
	}
	
	$.ajax({
		url: "assets/php/stats.Personal.php",
		type: "GET",
		cache: false,
		success: function(html){
			$('#personalStats').html(html);
		}
	});
}

/*
 * Updating the admin info through AJAX, calling itself at set interval
 */
var adminInfoTimer;
function updateAdminInfo() {
	if(adminInfoTimer == undefined) {
		adminInfoTimer = setInterval('updateAdminInfo()', 15000);
	}
	
	$.ajax({
		url: "assets/php/stats.Admin.php",
		type: "GET",
		cache: false,
		success: function(html){
			$('#adminStats').html(html);
		}
	});
}

/*
 * Update the images for server status and show/hide flash files when servers come on/off line.
 */
var serverStatusTimer;
function updateServerStatus() {
	if(serverStatusTimer == undefined) {
		serverStatusTimer = setInterval('updateServerStatus()', 5000);
	}
	
	$.ajax({
		url: "assets/php/status.Servers.php",
		type: "GET",
		cache: false,
		success: function(data){
			var servers = eval('('+data+')');
			
			$('#chatStatus').attr("src", "assets/html/images/" + ((servers.chatServer == 1)?"online.gif":"offline.gif"));
			$('#gameStatus').attr("src", "assets/html/images/" + ((servers.gameServer == 1)?"online.gif":"offline.gif"));
			
			status.chatServer = servers.chatServer;
			updateChatFlash();
		
			// do not force create! people can be playing a game..
			// just enable a button to switch. ---- and make sure this button is noticed.
			if(servers.gameServer != status.gameServer) {
				status.gameServer = servers.gameServer;
				inGame = false;
				updateGameFlash();
			}
		}
	 });
}

function updateChatFlash() {
	if(status.chatServer == 1) {
		createChatFlash();
	} else {
		if(chatLoaded) {
			chatLoaded = false;
			$('#'+chatDiv).html('<div id="'+chatHolder+'"></div>');
			
			gameAsMain();
		}
	}	
}

function updateGameFlash() {
	if(status.gameServer == 1 && !forceSingle) {
		$('#practise').attr('value', ' multiplayer ');
		$('#practise').removeAttr('disabled');
	} else {
		$('#practise').attr('value', ' multiplayer ');
		$('#practise').attr('disabled', 'disabled');
		createGameFlash('single');
	}
	
	if(currentGameType == -1) {
		createGameFlash((status.gameServer == 1)?'multi':'single');
	}	
}

/*
 * Updating the highscores, updating the top 5 every minute using AJAX.
 */
var highscoresTimer;
function updateHighscores() {
	if(highscoresTimer == undefined) {
		highscoresTimer = setInterval('updateHighscores()', 60000);
	}
	
	$.ajax({
		url: "assets/php/stats.Highscores.php",
		type: "GET",
		cache: false,
		data: {count:5},
		success: function(html){
			$('#topscores').html(html);
		}
	});
}

/*
 * Checking for messages, every second. When a message is found in the personal queue it is shown.
 */
var messageTimer;
var showingMessage = false;
function updateMessages() {
	if(messageTimer == undefined) {
		messageTimer = setInterval('updateMessages()', 10000);
	}
	
	if(inGame || showingMessage) return;
	
	$.ajax({
		url: "assets/php/message.php",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showMessage(text);
			}
		}
	});
}

/*
 * Checking for messages, every second. When a message is found in the personal queue it is shown.
 */
var achievementsTimer;
function updateAchievements() {
	return;
	
	if(achievementsTimer == undefined) {
		achievementsTimer = setInterval('updateAchievements()', 8000);
	}
	
	if(inGame || showingMessage) return;
	
	$.ajax({
		url: "assets/php/achievement.php",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showAchievement(text);
			}
		}
	});
}

/*
 * Keep track of a player being in a game; dont show popups when somebody is in a game!
 */
var inGame = false;
function enteringGame() {
	if(inGame) return;
	inGame = true;
	
	$('#overlay').css("width", "100%");
	$('#overlay').css("height", "100%");
	$('#overlay').css("display", "block").fadeTo(0, 0.9);
}

function leavingGame() {
	if(!inGame) return;
	inGame = false;
	
	$('#overlay').fadeTo("normal", 0, function() {
		$('#overlay').css("display", "none");
	});
}

/*
 * minimize and maximize the flash. some browsers can't handle the z-index of (flash) objects.
 */

function minimizeFlash() {
	/* Dirty hack for admin panel, no flash there */
	try {
		if(!chatDiv || chatDiv == undefined) return;
	} catch(e) {
		return;
	}
	
	var game = $('#'+gameDiv);
	game.width(0);
	game.height(0);

	var chat = $('#'+chatDiv);
	chat.width(0);
	chat.height(0);
}
	

function restoreFlash() {
	/* Dirty hack for admin panel, no flash there */
	
	try {
		if(!chatDiv || chatDiv == undefined) return;
	} catch(e) {
		return;
	}
	
	var game = $('#'+gameDiv);
	game.width((gameDiv == 'mainHolder')?450:200);
	game.height(300);
	
	var chat = $('#'+chatDiv);
	chat.width((chatDiv == 'mainHolder')?450:200);
	chat.height(300);
}

/*
 * Show a black overlay below the popup to make them stick out;
 */
var showingOverlay = false;
var scrollPos;
function showOverlay() {
	if(showingOverlay) return;
	
	minimizeFlash();
	
	// save the current scroll position (mostly needed for admin purposes.
	scrollPos = {top:$(window).scrollTop(), left:$(window).scrollLeft()};
	
	$('#overlay').css("width", $(window).scrollLeft() + $(window).width());
	$('#overlay').css("height", $(window).scrollTop() + $(window).height());
	$('#overlay').css("display", "block").fadeTo(0, 0.9);
	
	$(window).scrollTo(0, 250);
	
	showingOverlay = true;
}

function hideOverlay() {
	if(showingMessage || showingForm || !showingOverlay) return;
	
	$(window).scrollTo(scrollPos, 250);
	
	showingOverlay = false;
	
	$('#overlay').fadeTo("normal", 0, function() {
		if(!showingOverlay) {
			$('#overlay').css("display", "none");
			restoreFlash();
		}
	});	
}

/*
 * Show/hide the message on top of the personal queue
 */
function showMessage(text) {
	showingMessage = true;
	
	showOverlay();

	$('#text').html(text);
	$('#message').show();
}

function hideMessage() {	
	$('#message').hide();
	showingMessage = false;
	hideOverlay();
}

/*
 * Show/hide the achievement gained window
 */

var hideAchievementTimer;
function showAchievement(text) {
	return;
	
	showingMessage = true;
	
	$('#achievement').html(text);
	$('#achievement').fadeIn(900);
	
	hideAchievementTimer = setTimeout('hideAchievement()', 4500);
}

function hideAchievement() {
	if(hideAchievementTimer) {
		clearTimeout(hideAchievementTimer);
	}
	
	showingMessage = false;
	
	$('#achievement').fadeOut(600, function() { updateAchievements(); } );
}

/*
 * Show the FAQ as a message - putting messaging on hold for a sec
 */
function showFAQ() {
	$.ajax({
		url: "assets/php/faq.php",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showMessage(text);
			}
		}
	});	
}

/*
 * Show the Bugs
 */
function showBugs() {
	$.ajax({
		url: "assets/php/status.Bugs.php",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showMessage(text);
			}
		}
	});
}

/*
 * Show achievements
 */
function showAchievements() {
	$.ajax({
		url: "assets/php/achievements.php",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showMessage(text);
			}
		}
	});	
}

/*
 * Showing the text in the FAQ item
 */
function showMyDiv(target) {
	$(target).find("div").slideToggle("normal");
}

/*
 * Submit Login form - with checks 
 */
function submitLogin(frm, type) {
	if(frm.email.value == 'enter e-mail address') {
		frm.email.value = '';
	}
	
	frm.type.value = type;
	
	if(frm.email.value == '' || frm.pass.value == '') {
		if(frm.email.value == '') {
			frm.email.value = 'enter e-mail address';
		}
		if(frm.pass.value == '') {
			frm.pass.value = 'password';
		}
		alert('Please enter an e-mail address and password.');
		return false;
	}
	
	// make sure no messages can be recieved while submitting:
	showingMessage = true;
	clearInterval(messageTimer);
	
	// if registering, we need to trigger submit manually.. else just return true
	if(type == 'login') {
		return true;
	}
	
	frm.submit();
}

/*
 * Showing a form - save function can be defined for customization purposes
 */
var showingForm = false;
var formPosted = false;
function showForm(formHTML, postUrl, onSaveData) {
	$('#form').html(formHTML);
	
	$("#dynamicFormSubmit").unbind('click');
	
	/* Set button actions */
	$("#dynamicFormSubmit").click(function() {
		// prevent re-post!
		if(formPosted) return;
		formPosted = true;
		
		/* Disable buttons to prevent re-post */
		$("#dynamicFormSubmit").attr('disabled', 'disabled');
		$("#dynamicFormCancel").attr('disabled', 'disabled');
		
		/* Get the values for all the inputs in the form */
		var inputs = $('#form :input');
		
		/* Post it to the specified url */
		$.post(postUrl, inputs.serialize(), function(data){
			data = eval("("+data+")"); // unserialize JSON data
			// data = JSON.parse(data);
			
			if(onSaveData(data)) {
				hideForm();
			} else {
				enableFormButtons();
				updateMessages();
			}
		});
	});
	
	$("#dynamicFormCancel").click(function(){
		hideForm();
	});
	
	/* If posted before, re-enable buttons */
	enableFormButtons();
	
	/* Show the form */
	showOverlay();
	$('#dynamicForm').css("display", "block");
	
	/* Make sure it's not loaded over eachother */
	showingForm = true;
}

function enableFormButtons() {
	formPosted = false;
	
	$("#dynamicFormSubmit").removeAttr('disabled');
	$("#dynamicFormCancel").removeAttr('disabled');
}

/*
 * Removing the form from sight
 */
function hideForm() {
	if(!showingForm) return;
	
	/* Clear the form inputs */
	$("#form").html('');
	
	/* Hide the form */
	$('#dynamicForm').css("display", "none");
	showingForm = false;
	
	/* If overlay can be closed, do it now */
	hideOverlay();
}

/*
 * Load a page into the form, setting callback-save function and handling all the actions done.
 */
function loadForm(page, querydata, onSaveData) {
	if(showingForm) return;
	
	$.ajax({
		url: page,
		data: querydata,
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showForm(text, page, onSaveData);
			}
		}
	});
}

/*
 * Show the registration form
 */
function showRegister() {
	loadForm("assets/php/register.php", null, afterRegister);
}

function afterRegister(data) {
	return data.saved;
}

/*
 * Show the profile popup.
 & 	Not disabling the messages because of form errors.
 */
function showProfile() {
	loadForm("assets/php/profile.php", null, saveProfile);
}

/*
 * Handle the data recieved back from the posted page
 */
function saveProfile(data) {
	if(data.saved) {
		/* Update the username field in the page, matching the new name */
		$('#un').html(data.username);
		history.go(0);
	}
	
	return data.saved;
}

function showContactForm() {
	loadForm("assets/php/contact.php", null, afterContactForm);
}

function afterContactForm(data) {
	return data.saved;
}

var keepMenuOpen = false;
var closeMenuTimer;
function showMenu() {
	keepMenuOpen = true;
	
	$('#logout').bind("mouseenter", function() { keepMenuOpen = true; }).bind("mouseleave", function() { keepMenuOpen = false; });
	$('#menu').bind("mouseenter", function() { keepMenuOpen = true; }).bind("mouseleave", function() { keepMenuOpen = false; });
	
	$('#menu').fadeIn(240);
	
	if(!closeMenuTimer) {
		closeMenuTimer = setInterval("hideMenu();", 500);
	}
}

function hideMenu() {
	if(keepMenuOpen) return;
	
	
	if(closeMenuTimer) {
		clearInterval(closeMenuTimer);
		closeMenuTimer = undefined;
	}
	
	$('#menu').fadeOut(600);
}

function showInvite() {
	loadForm("assets/php/invite.php", null, afterInvite);
}

function afterInvite(data) {
	return data.saved;
}