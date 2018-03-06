
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Text=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.id==='string'){var id=conf.id
var x=conf.x;var y=conf.y;var text=String(conf.text);var parseConfObjectForOptions=true;}else{var id=conf;var x=arguments[1];var y=arguments[2];var text=arguments[3];}
this.id=id;this.canvas=document.getElementById(id);this.context=this.canvas.getContext('2d');this.colorsParsed=false;this.canvas.__object__=this;this.x=x;this.y=y;this.text=String(text);this.coords=[];this.coordsText=[];this.original_colors=[];this.firstDraw=true;this.type='drawing.text';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.size':10,'chart.font':'Segoe UI, Arial, Verdana, sans-serif','chart.bold':false,'chart.angle':0,'chart.colors':['black'],'chart.events.click':null,'chart.events.mousemove':null,'chart.highlight.stroke':'#ccc','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.tooltips':null,'chart.tooltips.effect':'fade','chart.tooltips.css.class':'RGraph_tooltip','chart.tooltips.event':'onclick','chart.tooltips.highlight':true,'chart.tooltips.coords.page':false,'chart.bounding':false,'chart.bounding.fill':'rgba(255,255,255,0.7)','chart.bounding.stroke':'#777','chart.bounding.shadow':false,'chart.bounding.shadow.color':'#ccc','chart.bounding.shadow.blur':3,'chart.bounding.shadow.offsetx':3,'chart.bounding.shadow.offsety':3,'chart.marker':false,'chart.halign':'left','chart.valign':'bottom','chart.link':null,'chart.link.target':'_self','chart.link.options':'','chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.clearto':'rgba(0,0,0,0)','chart.shadow':false,'chart.shadow.color':'#ccc','chart.shadow.offsetx':2,'chart.shadow.offsety':2,'chart.shadow.blur':3}
if(!this.canvas){alert('[DRAWING.TEXT] No canvas support');return;}
this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa2=RG.path2,win=window,doc=document,ma=Math
if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.fireCustomEvent(this,'onbeforedraw');if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
this.coords=[];this.coordsText=[];var dimensions=RG.measureText(this.text,prop['chart.text.bold'],prop['chart.text.font'],prop['chart.text.size']);co.fillStyle=prop['chart.colors'][0];if(prop['chart.shadow']){RG.setShadow(this,prop['chart.shadow.color'],prop['chart.shadow.offsetx'],prop['chart.shadow.offsety'],prop['chart.shadow.blur']);}
var ret=RG.text2(this,{font:prop['chart.font'],size:prop['chart.size'],x:this.x,y:this.y,text:this.text,bold:prop['chart.bold'],angle:prop['chart.angle'],bounding:prop['chart.bounding'],'bounding.fill':prop['chart.bounding.fill'],'bounding.stroke':prop['chart.bounding.stroke'],'bounding.shadow':prop['chart.bounding.shadow'],'bounding.shadow.color':prop['chart.bounding.shadow.color'],'bounding.shadow.blur':prop['chart.bounding.shadow.blur'],'bounding.shadow.offsetx':prop['chart.bounding.shadow.offsetx'],'bounding.shadow.offsety':prop['chart.bounding.shadow.offsety'],marker:prop['chart.marker'],halign:prop['chart.halign'],valign:prop['chart.valign']});if(prop['chart.shadow']){RG.noShadow(this);}
this.coords.push({0:ret.x,'x':ret.x,1:ret.y,'y':ret.y,2:ret.width,'width':ret.width,3:ret.height,'height':ret.height});RG.InstallEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.FireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var prop=this.properties;var coords=this.coords;var mouseXY=RGraph.getMouseXY(e);var mouseX=mouseXY[0];var mouseY=mouseXY[1];for(var i=0,len=this.coords.length;i<len;i++){var left=coords[i].x;var top=coords[i].y;var width=coords[i].width;var height=coords[i].height;if(mouseX>=left&&mouseX<=(left+width)&&mouseY>=top&&mouseY<=(top+height)){return{0:this,1:left,2:top,3:width,4:height,5:0,'object':this,'x':left,'y':top,'width':width,'height':height,'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}}
return null;};this.highlight=this.Highlight=function(shape)
{if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{RG.Highlight.Rect(this,shape);}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.colors']=RG.array_clone(prop['chart.colors'])[0];this.original_colors['chart.fillstyle']=RG.array_clone(prop['chart.fillstyle']);this.original_colors['chart.strokestyle']=RG.array_clone(prop['chart.strokestyle']);this.original_colors['chart.highlight.stroke']=RG.array_clone(prop['chart.highlight.stroke']);this.original_colors['chart.highlight.fill']=RG.array_clone(prop['chart.highlight.fill']);}
prop['chart.colors'][0]=this.parseSingleColorForGradient(prop['chart.colors'][0]);prop['chart.fillstyle']=this.parseSingleColorForGradient(prop['chart.fillstyle']);prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':');var grad=co.createLinearGradient(0,0,ca.width,0);var diff=1/(parts.length-1);grad.addColorStop(0,RGraph.trim(parts[0]));for(var j=1,len=parts.length;j<len;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.att(ca);RG.Register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};