﻿package com.moorscode.game {	import flash.display.Sprite;	import flash.display.MovieClip;	import flash.display.Bitmap;	import flash.display.DisplayObject;	import flash.display.Loader;	import flash.net.SharedObject;	import flash.net.URLVariables;		import flash.events.TimerEvent;	import flash.events.Event;	import flash.events.ProgressEvent;		import flash.geom.Point;	import flash.utils.getDefinitionByName;	import flash.utils.getQualifiedClassName;	import flash.utils.Timer;	import flash.utils.getTimer;		import flash.external.ExternalInterface;	import flash.display.StageAlign;	import flash.display.StageScaleMode;		import com.moorscode.game.GameCamera;	import com.moorscode.game.GameObjectList;		import com.moorscode.input.Input;	import com.moorscode.sound.Audio;		import com.moorscode.net.NetController;	import com.moorscode.net.NetEvent;		import com.moorscode.library.LibraryLoader;		public class GameSkeleton extends Sprite {		private var gameTimer:Timer;		protected var inGame:Boolean = false;				public var frameCount:int = 0;		private var clearFrameCounterTimer:Timer;				protected var paused:Boolean = false;		protected var pauseDisabled:Boolean = false;		protected var pauseOnBlur:Boolean = true;		public function GameSkeleton():void {			clearFrameCounterTimer = new Timer(1000);			clearFrameCounterTimer.addEventListener(TimerEvent.TIMER, function(event:TimerEvent) { frameCount = 0; /* clear the framecounter */ });			clearFrameCounterTimer.start();						// prepare the game timer			gameTimer = new Timer(30);			gameTimer.addEventListener(TimerEvent.TIMER, doTick);						Audio.path = getLogicalClassName(this) + "/";						addEventListener(Event.ENTER_FRAME, loading);		}				private function loading(event:Event):void {			if(event.target.loaderInfo.bytesTotal == event.target.loaderInfo.bytesLoaded) {				this.removeEventListener(event.type, arguments.callee);								LibraryLoader.addEventListener(Event.COMPLETE, initialize);				LibraryLoader.addEventListener(ProgressEvent.PROGRESS, loadProgress);				LibraryLoader.setPath(getLogicalClassName(this));				LibraryLoader.load();								return;			}		}				private function loadProgress(event:ProgressEvent):void {			dispatchEvent(event);		}				private function initialize(event:Event = null):void {			stage.scaleMode = StageScaleMode.NO_SCALE;			stage.align = StageAlign.TOP_LEFT;			stage.addEventListener(Event.ENTER_FRAME, doFrame);						Input.initialize(stage);						if(NetController.connected) {				NetController.addEventListener(NetEvent.DISCONNECT, endGame, false, 0, true);			}						stage.addEventListener(Event.RESIZE, updateCameraSize);			updateCameraSize();						dispatchEvent(new GameEvent(GameEvent.LOADED));						JavaScript("flashLoaded", getLogicalClassName(this));		}				public function unload(event:Event = null):void {			if(gameTimer.running) {				gameTimer.stop();			}						clearFrameCounterTimer.stop();						dispatchEvent(new Event(Event.CLOSE));		}				public function startGame():void {			stage.focus = stage;			Input.clearInput();						inGame = true;			gameTimer.start();						if(pauseOnBlur) {				stage.addEventListener(Event.ACTIVATE, togglePause);				stage.addEventListener(Event.DEACTIVATE, togglePause);			}						JavaScript("enteringGame");						// Input.setCursor(getSprite("targetCursor"));						// no event needed :: next flow = gameplay			// menu should be disabled already by now.		}				public function endGame(event:* = null):void {			// clean up?!			if(gameTimer.running) {				gameTimer.stop();			}						GameObjectList.removeAll();			inGame = false;						Input.clearInput();			Input.setCursor();						if(pauseOnBlur) {				stage.removeEventListener(Event.ACTIVATE, togglePause);				stage.removeEventListener(Event.DEACTIVATE, togglePause);			}			// send event :: next flow			// | 			// +--> goto menu			// +--> close game, return to game query window						dispatchEvent(new GameEvent(GameEvent.ENDED));		}				protected function togglePause(event:Event = null):Boolean {			if(!inGame || pauseDisabled) return paused;						if(event != null && event.type == Event.ACTIVATE && !paused) return paused;			if(event != null && event.type == Event.DEACTIVATE && paused) return paused;						if(paused) {				__unpause();			} else {				__pause();			}						Input.clearInput();						return paused;		}				protected function __pause():void {			if(paused || pauseDisabled) return;			paused = true;						GameObjectList.pause();			gameTimer.stop();						dispatchEvent(new GameEvent(GameEvent.PAUSED));		}				protected function __unpause():void {			if(!paused) return;			paused = false;						GameObjectList.unpause();			gameTimer.start();						dispatchEvent(new GameEvent(GameEvent.UNPAUSED));		}				// on tick - calculate - send event		private function doTick(event:TimerEvent):void {			if(inGame && !paused) {				GameObjectList.tick();				dispatchEvent(new GameEvent(GameEvent.TICK));			}		}				// on frame: update graphics - send event		private function doFrame(event:Event):void {			if(inGame && !paused) {				frameCount++;				GameObjectList.updateGfx();				dispatchEvent(new GameEvent(GameEvent.FRAME));			}		}				public function get tickTime():int {			return gameTimer.delay;		}				// MATH - player bouncer on proximity:		protected function bounce(obj1:IGameObject, obj2:IGameObject, type:String = "radial"):void { 			switch(type) {				case "radial":					var dx:Number = obj2.x - obj1.x;					var dy:Number = obj2.y - obj1.y;					var distance:Number = Math.sqrt(dx*dx + dy*dy);										var radius1:Number = obj1.hitRadius();					var radius2:Number = obj2.hitRadius();										if(radius2 == 0) return;										var totalRadius:Number = radius1 + radius2;										if(distance < totalRadius) {						 obj1.x = obj2.x - dx / distance * totalRadius; 						 obj1.y = obj2.y - dy / distance * totalRadius; 					}										break;									case "rectangular":					var rect1:Rectangle = obj1.hitbox(this);					var rect2:Rectangle = obj2.hitbox(this);										if(rect1.intersects(rect2)) {						// move away... ?						var rect3 = rect1.intersection(rect2);												if(rect3.width < rect3.height) {							if(rect3.x == rect1.x) {								obj1.x += rect3.width;							} else {								obj1.x -= rect3.width;							}						} else {							if(rect3.y == rect1.y) {								obj1.y += rect3.height;							} else {								obj1.y -= rect3.height;							}						}					}										break;			}		}						// MATH - do 2 objects collide?!		protected function collides(obj1:IGameObject, obj2:IGameObject, type:String = "radial"):Boolean {			if(obj1 == null || obj2 == null) return false;						switch(type) {				case "radial":									var dx:Number = obj2.x - obj1.x;					var dy:Number = obj2.y - obj1.y;					var distance:Number = Math.sqrt(dx*dx + dy*dy);										var radius1:Number = obj1.hitRadius();					var radius2:Number = obj2.hitRadius();										if(radius2 == 0) return false;										var totalRadius:Number = radius1 + radius2;										return Boolean(distance < totalRadius);										break;				case "rectangular":					var rect1:Rectangle = obj1.hitbox(this); // new Rectangle(obj1.x - (obj1.width / 2.0), obj1.y - (obj1.height / 2.0), obj1.width, obj1.height);					var rect2:Rectangle = obj2.hitbox(this); // new Rectangle(obj2.x - (obj2.width / 2.0), obj2.y - (obj2.height / 2.0), obj2.width, obj2.height);										return (rect1.intersects(rect2));										break;			}						return false;		}				// SOUND - calculate panning / volume according to distance		protected function calculateSurround(point1:Point, point2:Point):Array {			var panning:Number = (point1.x - point2.x) / (GameCamera.width / 2.0);				panning = (panning > 1)?1:panning;				panning = (panning < -1)?-1:panning;				panning = Math.round(panning * 10) / 10;				panning *= -1;							var maxDistance:Number = (GameCamera.width > GameCamera.height)?GameCamera.width:GameCamera.height;						var volume:Number = Point.distance(point1, point2) / (maxDistance / 2.0);				volume = (1 / volume) / 5;				volume *= 0.5;				volume = Math.round(volume * 100) / 100;						var surround:Array = new Array();				surround['panning'] = panning;				surround['volume'] = volume;						return surround;		}				// CORE - resize the playfield when somebody likes to fuck with the resolution..		protected function updateCameraSize(event:Event = null):void {			if(!stage || !GameCamera) return;						GameCamera.width = stage.stageWidth;			GameCamera.height = stage.stageHeight;		}				// WEB - communicate with JavaScript		public function JavaScript(functionName:String, arguments:String = ""):String {			if(ExternalInterface.available) {				return ExternalInterface.call(functionName, arguments);			}			return "";		}				// CORE - get logical class name (without package information)		protected function getLogicalClassName(object:*):String {			var className:String = getQualifiedClassName(object);						if(className.indexOf('::') > -1) {				className = className.substr(className.indexOf('::') + 2);			}						return className;		}				// CORE - get stuff from the library:				public function getSymbol(symbolName:String):Class {			return getDefinitionByName(symbolName) as Class;		}				public function getMovieClip(symbolName:String):MovieClip {			var classDef:Class = getSymbol(symbolName);			return MovieClip(new classDef());		}		public function getSprite(symbolName:String):Sprite {			var classDef:Class = getSymbol(symbolName);			return Sprite(new classDef());		}		public function getBitmap(symbolName:String):Bitmap {			var classDef:Class = getSymbol(symbolName);			return new Bitmap(new classDef(-1, -1));		}	}}