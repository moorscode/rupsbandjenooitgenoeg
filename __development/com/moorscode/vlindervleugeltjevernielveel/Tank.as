﻿package com.moorscode.vlindervleugeltjevernielveel {	import flash.display.Sprite;	import flash.geom.Point;		import com.moorscode.game.IGameObject;		public class Tank extends IGameObject {		protected var unloading:Boolean = false;				protected var body:Sprite;		protected var turret:Sprite;				public function Tank(position:Point):void {			super(position);						this.body = Sprite(addChild(getSprite("tankBody")));			this.turret = Sprite(addChild(getSprite("tankTop")));						this.scaleX = this.scaleY = 0.7;			this.alpha = 0.8;						this.rotation = Math.random() * 360;			this.turret.rotation = Math.random() * 360;;		}				public override function unload():void {			unloading = true;						// remove all attached objects.			while(numChildren > 0) {				removeChildAt(0);			}						this.body = null;			this.turret = null;		}	}}