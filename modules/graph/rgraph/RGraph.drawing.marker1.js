
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Marker1=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.radius=='number'&&typeof conf.id==='string'&&typeof conf.text==='string'){var id=conf.id,canvas=document.getElementById(id),x=conf.x,y=conf.y,radius=conf.radius,text=conf.text,parseConfObjectForOptions=true;}else{var id=conf,canvas=document.getElementById(id),x=arguments[1],y=arguments[2],radius=arguments[3],text=arguments[4];}
this.id=id;this.canvas=canvas;this.context=this.canvas.getContext("2d");this.colorsParsed=false;this.canvas.__object__=this;this.original_colors=[];this.firstDraw=true;this.centerx=x;this.centery=y;this.radius=radius;this.text=text;this.type='drawing.marker1';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.strokestyle':'black','chart.fillstyle':'white','chart.linewidth':2,'chart.text.color':'black','chart.text.size':12,'chart.text.font':'Segoe UI, Arial, Verdana, sans-serif','chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.events.click':null,'chart.events.mousemove':null,'chart.shadow':true,'chart.shadow.color':'#aaa','chart.shadow.offsetx':0,'chart.shadow.offsety':0,'chart.shadow.blur':15,'chart.highlight.stroke':'rgba(0,0,0,0)','chart.highlight.fill':'rgba(255,0,0,0.7)','chart.tooltips':null,'chart.tooltips.highlight':true,'chart.tooltips.event':'onclick','chart.align':'center','chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.MARKER1] No canvas support');return;}
this.$0={};this.coords=[];this.coordsText=[];if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
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
{RG.FireCustomEvent(this,'onbeforedraw');var r=this.radius;if(prop['chart.align']=='left'){this.markerCenterx=this.centerx-r-r-3;this.markerCentery=this.centery-r-r-3;}else if(prop['chart.align']=='right'){this.markerCenterx=this.centerx+r+r+3;this.markerCentery=this.centery-r-r-3;}else{this.markerCenterx=this.centerx;this.markerCentery=this.centery-r-r-3;}
if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
this.coordsText=[];pa2(co,['b','lw',prop['chart.linewidth']]);if(prop['chart.shadow']){RG.setShadow(this,prop['chart.shadow.color'],prop['chart.shadow.offsetx'],prop['chart.shadow.offsety'],prop['chart.shadow.blur']);}
this.drawMarker();pa2(co,['c','s',prop['chart.strokestyle'],'f',prop['chart.fillstyle']]);RG.noShadow(this);co.fillStyle=prop['chart.text.color'];RG.text2(this,{font:prop['chart.text.font'],size:prop['chart.text.size'],x:this.coords[0][0]-1,y:this.coords[0][1]-1,text:this.text,valign:'center',halign:'center',tag:'labels'});RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];co.beginPath();this.drawMarker();if(co.isPointInPath(mouseXY[0],mouseXY[1])){return{0:this,1:this.coords[0][0],2:this.coords[0][1],3:this.coords[0][2],4:0,'object':this,'x':this.coords[0][0],'y':this.coords[0][1],'radius':this.coords[0][2],'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}
return null;};this.highlight=this.Highlight=function(shape)
{if(prop['chart.tooltips.highlight']){if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{co.beginPath();co.strokeStyle=prop['chart.highlight.stroke'];co.fillStyle=prop['chart.highlight.fill'];this.drawMarker();co.closePath();co.stroke();co.fill();}}};this.drawMarker=this.DrawMarker=function()
{var r=this.radius;if(prop['chart.align']==='left'){var x=this.markerCenterx,y=this.markerCentery;pa2(co,['a',x,y,r,RG.HALFPI,RG.TWOPI,false]);pa2(co,['qc',x+r,y+r,x+r+r,y+r+r]);pa2(co,['qc',x+r,y+r,x,y+r]);}else if(prop['chart.align']==='right'){var x=this.markerCenterx,y=this.markerCentery;pa2(co,['a',x,y,r,RG.HALFPI,RG.PI,true]);pa2(co,['qc',x-r,y+r,x-r-r,y+r+r]);pa2(co,['qc',x-r,y+r,x,y+r]);}else{var x=this.markerCenterx,y=this.markerCentery;pa2(co,['a',x,y,r,RG.HALFPI/2,RG.PI-(RG.HALFPI/2),true]);pa2(co,['qc',x,y+r+(r/4),x,y+r+r-2]);pa2(co,['qc',x,y+r+(r/4),x+(ma.cos(RG.HALFPI/2)*r),y+(ma.sin(RG.HALFPI/2)*r)]);}
this.coords[0]=[x,y,r];};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.fillstyle']=RG.arrayClone(prop['chart.fillstyle']);this.original_colors['chart.strokestyle']=RG.arrayClone(prop['chart.strokestyle']);this.original_colors['chart.highlight.fill']=RG.arrayClone(prop['chart.highlight.fill']);this.original_colors['chart.highlight.stroke']=RG.arrayClone(prop['chart.highlight.stroke']);this.original_colors['chart.text.color']=RG.arrayClone(prop['chart.text.color']);}
prop['chart.fillstyle']=this.parseSingleColorForGradient(prop['chart.fillstyle']);prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);prop['chart.text.color']=this.parseSingleColorForGradient(prop['chart.text.color']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color||typeof(color)!='string'){return color;}
if(color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':'),grad=co.createRadialGradient(this.markerCenterx,this.markerCentery,0,this.markerCenterx,this.markerCentery,this.radius),diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1;j<parts.length;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};