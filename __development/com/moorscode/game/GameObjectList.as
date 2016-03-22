﻿package com.moorscode.game {		import flash.display.DisplayObject;	import flash.display.Stage;	import flash.events.Event;	import flash.utils.getQualifiedClassName;	import flash.geom.Point;		import flash.filters.DropShadowFilter;	import flash.filters.BitmapFilterQuality;		import com.moorscode.game.GameCamera;	import com.moorscode.particles.*;	import com.moorscode.rupsbandjenooitgenoeg.*;		public class GameObjectList {		private static var objects:Array = new Array();		private static var objectsByClass:Array = new Array();				public static var shadows:Boolean = true;				public static function addObject(gameObject:IGameObject):void {			objects.push(gameObject);						if(shadows && !(gameObject is TankTracks) && !(gameObject is TankCorpse)) {				var shade:DropShadowFilter = new DropShadowFilter();					shade.angle = 45;					shade.blurX = shade.blurY = 2;					shade.distance = (gameObject is Bullet) ? 10 : 5;					shade.alpha = 0.2;					shade.quality = (gameObject is Tank) ? BitmapFilterQuality.HIGH : BitmapFilterQuality.LOW;							gameObject.filters = [shade];			}						var className:String = getLogicalClassName(gameObject);			if(objectsByClass[className] == null) {				objectsByClass[className] = new Array();			}			objectsByClass[className].push(gameObject);		}				public static function removeObject(gameObject:IGameObject):void {			for(var o:uint = 0; o < objects.length; o++) {				if(objects[o] == gameObject) {					objects.splice(o, 1);				}			}						var className:String = getLogicalClassName(gameObject);			for(var c:uint = 0; c < objectsByClass[className].length; c++) {				if(objectsByClass[className][c] == gameObject) {					objectsByClass[className].splice(c, 1);				}			}		}				public static function pause():void {			for(var o:uint = 0; o < objects.length; o++) {				objects[o].pause(true);			}		}				public static function unpause():void {			for(var o:uint = 0; o < objects.length; o++) {				objects[o].pause(false);			}		}				public static function tick(event:Event = null):void {			for(var o:uint = 0; o < objects.length; o++) {				objects[o].tick();			}		}				public static function updateGfx(event:Event = null):void {			for(var o:uint = 0; o < objects.length; o++) {				objects[o].updateGfx();			}		}				public static function removeAll():void {			while(objects.length > 0) {				objects[0].unload();			}			objectsByClass = new Array();		}				public static function getAll(className:String):Array {			if(objectsByClass[className] != null) {				return objectsByClass[className];			}						return new Array();		}				private static function getLogicalClassName(object:*):String {			var className:String = getQualifiedClassName(object);						if(className.indexOf('::') > -1) {				className = className.substr(className.indexOf('::') + 2);			}						return className;		}	}}