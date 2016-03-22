/*
 * Global JavaScript functions, when grouped functions are placed in seperate files this should get smaller..
 * @license Creative Commons by-nc-sa
 * @author Jip Moors <j.moors@home.nl>
 * @version 0.1 - functions need to be sorted and commented
 */

var game;
var chat;
var loaded = false;

var currentGameType = -1;

var statusGameServer = -1;
var statusChatServer = -1;

var loading_queue = Array();
function loadingQueue(load) {
	loading_queue.push(load);
}

function aferLoading() {
	loaded = true;
	
	while(loading_queue.length > 0) {
		eval(loading_queue[0]);
		loading_queue.shift();
	}
	
	updateMessages();
}

function openUrl(url) {
	var newWindow = window.open(url, '_wnd_'+Math.random());
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
		createGameFlash('single', true);
		
		// add to statistics:
		$.ajax({
			url: "assets/php/practise.php",
			type: "GET",
			cache: false,
			success: function(){
				// updated.
			}
		});
	} else {
		createGameFlash('multi', true);
	}
}

/*
 * Creating the flash items
 */
function createGameFlash(gameType, byChoice) {
	if(configActive) return;
	
	if(loggedin == 0) {
		gameAsMain();
		$('#'+gameDiv).html('<div id="start123"></div>');
		$('#'+gameDiv).css('z-index', '0');
		return;
	}
	
	if(currentGameType == -1) {
		var savedType = getGameType();
		if(savedType != null && savedType != -1) {
			gameType = (savedType == 2)?'multi':'single';
		}
	}
	
	if(statusGameServer == 0) {
		gameType = 'single';
	}
	
	newGameType = (gameType == 'multi')?2:1;
	if(newGameType == currentGameType) return;
	
	if(gameType == 'single') {
		gameAsMain();
		
		if(statusGameServer == 1) {
			if(loggedin) {
				$('#practise').text('multiplayer');
				$('#practise').removeClass('disabled');
			} else {
				$('#practise').text('multiplayer');
				$('#practise').addClass('disabled');
			}
		} else {
			$('#practise').html('multiplayer');
			$('#practise').addClass('disabled');
		}
	} else {
		if(statusChatServer == 1) {
			chatAsMain();
		}
		
		$('#practise').html('oefenen');
		$('#practise').removeClass('disabled');
	}
	
	// removing old flash:
	$('#'+gameDiv).html('<div id="'+gameHolder+'"></div>');
	createFlash(gameType, gameHolder);
	
	currentGameType = newGameType;
	
	if(byChoice) {
		saveGameType();
	}
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
	if(configActive) return;
	configActive = true;
	
	gameAsMain();
	
	// removing old flash:
	$('#'+gameDiv).html('<div id="config"></div>');
	createFlash("settings", "config");
}

function closeSettings() {
	configActive = false;
	
	lastType = currentGameType;
	currentGameType = 0;
	
	if(lastType == 2) {
		createGameFlash('multi');
		chatAsMain();
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
	if(loggedin == 0) return;
	
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
			
			statusChatServer = servers.chatServer;
			updateChatFlash();
		
			// do not force create! people can be playing a game..
			// just enable a button to switch. ---- and make sure this button is noticed.
			if(servers.gameServer != statusGameServer) {
				statusGameServer = servers.gameServer;
				inGame = false;
				updateGameFlash();
			}
			
			if((statusGameServer == 0 && !loaded) || !loggedin) {
				aferLoading();
			}
		}
	 });
}

function updateChatFlash() {
	if(statusChatServer == 1) {
		createChatFlash();
	} else if(statusChatServer == 0) {
		if(chatLoaded) {
			chatLoaded = false;
		}
		
		$('#'+chatDiv).html('<div id="'+chatHolder+'"></div>');
		gameAsMain();
	}
}

function updateGameFlash() {
	if(loggedin) {
		if(statusGameServer == 1) {
			$('#practise').text('multiplayer');
			$('#practise').removeClass('disabled');
		} else {
			$('#practise').html('multiplayer');
			$('#practise').addClass('disabled');
			
			createGameFlash('single');
		}
	}
	
	if(currentGameType == -1) {
		createGameFlash((statusGameServer == 1)?'multi':'single');
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
		data: {count:7},
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
	
	if(inGame || showingMessage || !loaded) return;
	
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
 * Checking for achievements, every x seconds. When an achievement is found it is shown.
 */
var achievementsTimer;
function updateAchievements() {
	
	if(achievementsTimer == undefined) {
		achievementsTimer = setInterval('updateAchievements()', 8000);
		$('#close_achievement').click(hideAchievement);
	}
	
	if(inGame || showingMessage) return;
	
	$.ajax({
		url: "assets/php/achievement.php",
		type: "GET",
		cache: false,
		success: function(data){
			if(data != '') {
				showAchievement(data);
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
	
	gameAsMain();
	
	$('#overlay').css("width", "100%");
	$('#overlay').css("height", "100%");
	$('#overlay').css("display", "block").fadeTo(0, 0.9);
	
	$('#'+gameDiv).css("height", "350px");
}

function leavingGame() {
	if(!inGame) return;
	inGame = false;
	
	$('#ping').html('');
	
	$('#'+gameDiv).css("height", "300px");
	
	$('#overlay').fadeTo("normal", 0, function() {
		$('#overlay').css("display", "none");
	});
	
	updatePlayerInfo();
	updateHighscores();
	updateAchievements();
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
	var chat = $('#'+chatDiv);
	
	game.height(300);
	chat.height(300);
	
	if(configActive) {
		game.width(450);
		chat.width(200);
	} else {
		var gameIsMain = (parseInt(game.css('left')) < 500);
		
		game.width((gameIsMain)?450:200);
		chat.width((!gameIsMain)?450:200);
	}
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
			updateMessages();
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

function showAchievement(data) {
	showingMessage = true;
	
	data = eval("("+data+")");
	
	tellChat(data.tell_chat);
	
	playSound('achievement');
	
	$('#achievement_text').html(data.html);
	$('#achievement').fadeIn(900);
}

function hideAchievement() {
	$('#achievement').fadeOut(600, function() {
		showingMessage = false;
		updateAchievements();
	});
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
		
		if(passwordReset == true) {
			logout();
		}
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
	if(passwordReset == true) {
		logout();
		return;
	}
	
	if(data.saved) {
		// reload the page for the chat and game to re-initialize the new username.
		history.go(0);
		return;
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

function showPasswordRecover() {
	loadForm("assets/php/recover.php", null, afterRecover);
}

function afterRecover(data) {
	return data.saved;
}

function showInvite() {
	loadForm("assets/php/invite.php", null, afterInvite);
}

function afterInvite(data) {
	return data.saved;
}

function showIntro() {
	$.ajax({
		url: "assets/html/templates/intro.html",
		type: "GET",
		cache: false,
		success: function(text){
			if(text != '') {
				showMessage(text);
			}
		}
	});
}

function initializeMenuFeature() {
	$('#leftHolder .menuItem:first').css({fontSize: '19px', lineHeight: '30px'});
	
	$('#leftHolder').bind('mouseleave', function() {
		menuTankTo({left: 30, top: -11}, 500);												
	});
	
	$('#leftHolder .menuItem').each(function() {
		$(this).click(function() {
			if(!$(this).hasClass('disabled')) {
				$(this).fadeOut(200).fadeIn(300);
		 	}
		});
		
		$(this).hover(function() {
			var pos = $(this).position();
			menuTankTo(pos, 250);
			if(!$(this).hasClass('disabled')) {
				$(this).css({color: '#9f0000'});
			}
		}, function() {
			if(!$(this).hasClass('disabled')) {
				$(this).css('color', '#006f00');
			}
		});
	});
	
	$('#offlineMenu .menuItem').each(function() {
		$(this).hover(function() {
		if(!$(this).hasClass('disabled')) {
				$(this).css({color: '#9f0000'});
			}
		}, function() {
			if(!$(this).hasClass('disabled')) {
				$(this).css('color', '#006f00');
			}
		});
	});
}

function menuTankTo(pos, speed) {
	$('#menuTank').stop(true);
	$('#menuTank').animate({left: pos.left - $('#menuTank').width() + 10, top: pos.top - 5}, speed, 'linear');
}

function saveGameType() {
	document.cookie = "currentGameType="+currentGameType+"; path=/";
}

function getGameType() {
	var nameEQ = "currentGameType=";
	var ca = document.cookie.split(';');
	
	for(var i=0; i < ca.length; i++) {
		var c = ca[i];
		while (c.charAt(0) == ' ') c = c.substring(1, c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
	}
	
	return null;
}