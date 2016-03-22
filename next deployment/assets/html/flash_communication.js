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
	if(name == 'rupsbandjenooitgenoeg') {
		aferLoading();
	}
}

function createFlash(swf, replaceId) {
	swfobject.embedSWF(swf+".swf?r="+randomSeed, replaceId, "100%", "100%", flashVersionRequired, expressInstall, 0, {menu:"false", scale:"noScale"}, {id:swf, name:swf});	
}

function getFlashMovie(movieName) {
	var isIE = (navigator.appName.indexOf("Microsoft") != -1);
	return (isIE)?window[movieName]:document[movieName];
}

function tellChat(data) {
	var chatFlash = getFlashMovie('chat');
	if(chatFlash) {
		return chatFlash.pushData(data);
	}
	
	return false;
	// javascript:tellChat('ach1');
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
