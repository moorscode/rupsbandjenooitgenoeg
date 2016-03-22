var randomSeed = new Date().getTime();
var flashVersionRequired = "10.0.0";
var expressInstall = "assets/html/expressInstall.swf";


function getVariable(name) {
	return eval(name);
}

function flashDebug(text) {
	alert(text);
}

var loaded = false;
function flashLoaded(name) {
	switch(name) {
		case 'rupsbandjenooitgenoeg':
			aferLoading();
			break;
		case 'chat':
			var chatFlash = getFlashMovie('chat');
			chatFlash.addEventListener('onIdentified', 'onChatIdentified');
			chatFlash.addEventListener('onDisconnected', 'onChatDisconnect');
			break;
	}
}

var chatIdentified = false;
function onChatIdentified() {
	// enable achievement announcing
	chatIdentified = true;
}

function onChatDisconnect() {
	chatIdentified = false;
}

function showPing(ping) {
	$('#ping').html(ping + 'ms');
}

function createFlash(swf, replaceId) {
	swfobject.embedSWF(swf+".swf?r="+randomSeed, replaceId, "100%", "100%", flashVersionRequired, expressInstall, 0, {menu:"false", scale:"noScale"}, {id:swf, name:swf});	
}

function getFlashMovie(movieName) {
	var isIE = (navigator.appName.indexOf("Microsoft") != -1);
	return (isIE)?window[movieName]:document[movieName];
}

var tellChatTimer;
var tellChatQueue = Array();
function tellChat(data) {
	var chatFlash = getFlashMovie('chat');
	if(chatFlash && chatIdentified) {
		chatFlash.pushData(data);
	} else {
		tellChatQueue.push(data);
		
		if(tellChatTimer == undefined) {
			tellChatTimer = setInterval('parseTellChat()', 1000);
		}
	}
}

function parseTellChat() {
	if(tellChatQueue.length == 0) {
		if(tellChatTimer != undefined) {
			clearInterval(tellChatTimer);
			tellChatTimer = undefined;
		}
		return;
	}
	
	var chatFlash = getFlashMovie('chat');
	if(chatFlash && chatIdentified) {
		data = tellChatQueue.shift();
		tellChat(data);
	}
}

function playSound(name) {
	var flashName;
	
	switch(currentGameType) {
		case 1:
			flashName = 'single';
			break;
		case 2:
			flashName = 'multi';
			break;
		default:
			return;
			break;
	}
	
	var gameFlash = getFlashMovie(flashName);
	if(gameFlash) {
		try {
			gameFlash.playSound(name);
		} catch(e) {
			// flash not loaded correctly... ?
		}
	}
}
