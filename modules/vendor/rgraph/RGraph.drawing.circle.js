
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Circle=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.radius==='number'&&typeof conf.id==='string'){var id=conf.id,canvas=document.getElementById(id),x=conf.x,y=conf.y,radius=conf.radius,parseConfObjectForOptions=true;}else{var id=conf,canvas=document.getElementById(id),x=arguments[1],y=arguments[2],radius=arguments[3];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d');this.canvas.__object__=this;this.original_colors=[];this.firstDraw=true;this.centerx=x;this.centery=y;this.radius=radius;this.type='drawing.circle';this.isRGraph=true;this.uid=RGraph.createUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.createUID();this.properties={'chart.strokestyle':'rgba(0,0,0,0)','chart.fillstyle':'red','chart.events.click':null,'chart.events.mousemove':null,'chart.shadow':false,'chart.shadow.color':'gray','chart.shadow.offsetx':3,'chart.shadow.offsety':3,'chart.shadow.blur':5,'chart.highlight.stroke':'black','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.tooltips':null,'chart.tooltips.highlight':true,'chart.tooltips.event':'onclick','chart.linewidth':2,'chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.CIRCLE] No canvas support');return;}
this.coords=[[this.centerx,this.centery,this.radius]];this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa2=RG.path2,win=window,doc=document,ma=Math;if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.fireCustomEvent(this,'onbeforedraw');if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
pa2(co,'b lw %',prop['chart.linewidth']);if(prop['chart.shadow']){RG.setShadow(this,prop['chart.shadow.color'],prop['chart.shadow.offsetx'],prop['chart.shadow.offsety'],prop['chart.shadow.blur']);}
pa2(co,'b a % % % % % % f % s %',this.coords[0][0],this.coords[0][1],this.radius,0,RG.TWOPI,false,prop['chart.fillstyle'],prop['chart.strokestyle']);RG.noShadow(this);RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];if(RG.getHypLength(this.centerx,this.centery,mouseXY[0],mouseXY[1])<=this.radius){return{0:this,1:this.centerx,2:this.centery,3:this.radius,4:null,5:0,'object':this,'x':this.centerx,'y':this.centery,'radius':this.radius,'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}
return null;};this.highlight=this.Highlight=function(shape)
{if(prop['chart.tooltips.highlight']){if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{pa2(co,'b a % % % % % % f % s %',this.centerx,this.centery,this.radius+0.5,0,RG.TWOPI,false,prop['chart.highlight.fill'],prop['chart.highlight.stroke']);}}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.fillstyle']=RG.array_clone(prop['chart.fillstyle']);this.original_colors['chart.strokestyle']=RG.array_clone(prop['chart.strokestyle']);this.original_colors['chart.highlight.stroke']=RG.array_clone(prop['chart.highlight.stroke']);this.original_colors['chart.highlight.fill']=RG.array_clone(prop['chart.highlight.fill']);}
prop['chart.fillstyle']=this.parseSingleColorForGradient(prop['chart.fillstyle']);prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':');var grad=co.createRadialGradient(this.centerx,this.centery,0,this.centerx,this.centery,this.radius),diff=1/(parts.length-1);for(var j=0;j<parts.length;j+=1){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};