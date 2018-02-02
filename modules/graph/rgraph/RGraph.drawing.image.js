
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Image=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.src==='string'&&typeof conf.id==='string'){var id=conf.id,canvas=document.getElementById(id),x=conf.x,y=conf.y,src=conf.src,parseConfObjectForOptions=true;}else{var id=conf,canvas=document.getElementById(id),x=arguments[1],y=arguments[2],src=arguments[3];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d');this.colorsParsed=false;this.canvas.__object__=this;this.alignmentProcessed=false;this.original_colors=[];this.firstDraw=true;this.x=x;this.y=y;this.src=src;this.img=new Image();this.img.src=this.src;this.type='drawing.image';this.isRGraph=true;this.uid=RGraph.createUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.src':null,'chart.width':null,'chart.height':null,'chart.halign':'left','chart.valign':'top','chart.events.mousemove':null,'chart.events.click':null,'chart.shadow':false,'chart.shadow.color':'gray','chart.shadow.offsetx':3,'chart.shadow.offsety':3,'chart.shadow.blur':5,'chart.tooltips':null,'chart.tooltips.highlight':true,'chart.tooltips.css.class':'RGraph_tooltip','chart.tooltips.event':'onclick','chart.highlight.stroke':'rgba(0,0,0,0)','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.alpha':1,'chart.border':false,'chart.border.color':'black','chart.border.linewidth':1,'chart.border.radius':0,'chart.background.color':'rgba(0,0,0,0)','chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.IMAGE] No canvas support');return;}
this.coords=[];this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
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
{RG.fireCustomEvent(this,'onbeforedraw');var obj=this;this.img.onload=function()
{if(!obj.colorsParsed){obj.parseColors();obj.colorsParsed=true;}
obj.width=this.width;obj.height=this.height;if(!this.alignmentProcessed){var customWidthHeight=(typeof obj.properties['chart.width']=='number'&&typeof obj.properties['chart.width']=='number');if(obj.properties['chart.halign']==='center'){obj.x-=customWidthHeight?(obj.properties['chart.width']/2):(this.width/2);}else if(obj.properties['chart.halign']=='right'){obj.x-=customWidthHeight?obj.properties['chart.width']:this.width;}
if(obj.properties['chart.valign']==='center'){obj.y-=customWidthHeight?(obj.properties['chart.height']/2):(this.height/2);}else if(obj.properties['chart.valign']=='bottom'){obj.y-=customWidthHeight?obj.properties['chart.height']:this.height;}
this.alignmentProcessed=true;}}
if(this.img.complete||this.img.readyState===4){this.img.onload();}
if(prop['chart.shadow']){RG.setShadow(this,prop['chart.shadow.color'],prop['chart.shadow.offsetx'],prop['chart.shadow.offsety'],prop['chart.shadow.blur']);}
var oldAlpha=co.globalAlpha;co.globalAlpha=prop['chart.alpha'];if(prop['chart.border']){co.strokeStyle=prop['chart.border.color'];co.lineWidth=prop['chart.border.linewidth'];var borderRadius=0;if(this.width||this.height){borderRadius=ma.min(this.width/2,this.height/2)}
if((prop['chart.width']/2)>borderRadius&&(prop['chart.height']/2)>borderRadius){borderRadius=ma.min((prop['chart.width']/2),(prop['chart.height']/2))}
if(prop['chart.border.radius']<borderRadius){borderRadius=prop['chart.border.radius'];}
co.beginPath();this.roundedRect(ma.round(this.x)-ma.round(co.lineWidth/2),ma.round(this.y)-ma.round(co.lineWidth/2),(prop['chart.width']||this.img.width)+co.lineWidth,(prop['chart.height']||this.img.height)+co.lineWidth,borderRadius);}
if(borderRadius){co.save();this.drawBackgroundColor(borderRadius);co.beginPath();this.roundedRect(ma.round(this.x)-ma.round(co.lineWidth/2),ma.round(this.y)-ma.round(co.lineWidth/2),(prop['chart.width']||this.img.width)+co.lineWidth,(prop['chart.height']||this.img.height)+co.lineWidth,borderRadius);co.clip();}else{this.drawBackgroundColor(0);}
RG.noShadow(this);if(typeof prop['chart.height']==='number'||typeof prop['chart.width']==='number'){co.drawImage(this.img,ma.round(this.x),ma.round(this.y),prop['chart.width']||this.width,prop['chart.height']||this.height);}else{co.drawImage(this.img,ma.round(this.x),ma.round(this.y));}
if(borderRadius){co.restore();}
if(prop['chart.border']){RG.noShadow(this);co.stroke();}
co.globalAlpha=oldAlpha;this.img.onload=function()
{RG.redrawCanvas(ca);obj.coords[0]=[ma.round(obj.x),ma.round(obj.y),typeof prop['chart.width']==='number'?prop['chart.width']:this.width,typeof prop['chart.height']=='number'?prop['chart.height']:this.height];}
RG.noShadow(this);RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{var mouseXY=RG.getMouseXY(e);if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];if(this.coords&&this.coords[0]&&mouseXY[0]>=this.coords[0][0]&&mouseXY[0]<=(this.coords[0][0]+this.coords[0][2])&&mouseXY[1]>=this.coords[0][1]&&mouseXY[1]<=(this.coords[0][1]+this.coords[0][3])){return{0:this,1:this.coords[0][0],2:this.coords[0][1],3:this.coords[0][2],4:this.coords[0][3],5:0,'object':this,'x':this.coords[0][0],'y':this.coords[0][1],'width':this.coords[0][2],'height':this.coords[0][3],'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}
return null;};this.highlight=this.Highlight=function(shape)
{if(prop['chart.tooltips.highlight']){if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{pa2(co,['b','r',this.coords[0][0],this.coords[0][1],this.coords[0][2],this.coords[0][3],'f',prop['chart.highlight.fill'],'s',prop['chart.highlight.stroke']]);}}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.highlight.stroke']=RG.array_clone(prop['chart.highlight.stroke']);this.original_colors['chart.highlight.fill']=RG.array_clone(prop['chart.highlight.fill']);}
prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':'),grad=co.createLinearGradient(this.x,this.y,this.x+this.img.width,this.y),diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1;j<parts.length;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};this.roundedRect=function(x,y,width,height,radius)
{co.save();co.translate(x,y);co.moveTo(width/2,0);co.arcTo(width,0,width,height,ma.min(height/2,radius));co.arcTo(width,height,0,height,ma.min(width/2,radius));co.arcTo(0,height,0,0,ma.min(height/2,radius));co.arcTo(0,0,radius,0,ma.min(width/2,radius));co.lineTo(width/2,0);co.restore();};this.drawBackgroundColor=function(borderRadius)
{co.beginPath();co.fillStyle=prop['chart.background.color'];this.roundedRect(ma.round(this.x)-ma.round(co.lineWidth/2),ma.round(this.y)-ma.round(co.lineWidth/2),(prop['chart.width']||this.img.width)+co.lineWidth,(prop['chart.height']||this.img.height)+co.lineWidth,borderRadius);co.fill();};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};