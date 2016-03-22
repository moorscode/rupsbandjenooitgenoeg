﻿package com.moorscode.game {		import flash.geom.Rectangle;	import flash.geom.Point;		public class GameCamera {		private static var __field:Rectangle = new Rectangle(0,0,450,300);		private static var __pos:Point = new Point(0,0);		private static var __size:Point = new Point(0,0);		private static var __rotation:Number = 0;				public function GameCamera() {}				public static function intersects(rect:Rectangle):Boolean {			return __field.intersects(rect);		}				public static function containsPoint(point:Point):Boolean {			var cam:Rectangle = GameCamera.__field;			return Boolean(point.x > cam.x && point.x < cam.x + cam.width && point.y > cam.y && point.y < cam.y + cam.height);		}				public static function set rotation(value:Number):void {			__rotation = Math.abs(value % 360);		}				public static function get rotation():Number {			return __rotation;		}				public static function set x(value:Number):void {			__pos.x = value;			__field.x = value - (__size.x / 2.0);		}				public static function get x():Number {			return __field.x;		}				public static function set y(value:Number):void {			__pos.y = value;			__field.y = value - (__size.y / 2.0);		}				public static function get y():Number {			return __field.y;		}				public static function set width(value:Number):void {			__size.x = value;			__field.width = value;		}				public static function get width():Number {			return __size.x;		}		public static function set height(value:Number):void {			__size.y = value;			__field.height = value;		}		public static function get height():Number {			return __size.y;		}	}}