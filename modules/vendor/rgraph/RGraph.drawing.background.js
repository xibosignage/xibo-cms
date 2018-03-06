
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Background=function(conf)
{if(typeof conf==='object'&&typeof conf.id==='string'){var id=conf.id,canvas=document.getElementById(id),parseConfObjectForOptions=true;}else{var id=conf,canvas=document.getElementById(id);}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d');this.canvas.__object__=this;this.original_colors=[];this.firstDraw=true;this.type='drawing.background';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.createUID();this.properties={'chart.background.barcolor1':'rgba(0,0,0,0)','chart.background.barcolor2':'rgba(0,0,0,0)','chart.background.grid':true,'chart.background.grid.color':'#ddd','chart.background.grid.width':1,'chart.background.grid.vlines':true,'chart.background.grid.hlines':true,'chart.background.grid.border':true,'chart.background.grid.autofit':true,'chart.background.grid.autofit.numhlines':5,'chart.background.grid.autofit.numvlines':20,'chart.background.grid.dashed':false,'chart.background.grid.dotted':false,'chart.background.image':null,'chart.background.image.stretch':true,'chart.background.image.x':null,'chart.background.image.y':null,'chart.background.image.w':null,'chart.background.image.h':null,'chart.background.image.align':null,'chart.background.color':null,'chart.gutter.left':25,'chart.gutter.right':25,'chart.gutter.top':25,'chart.gutter.bottom':25,'chart.text.color':'black','chart.text.size':12,'chart.text.font':'Segoe UI, Arial, Verdana, sans-serif','chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.events.click':null,'chart.events.mousemove':null,'chart.tooltips':null,'chart.tooltips.highlight':true,'chart.tooltips.event':'onclick','chart.highlight.stroke':'rgba(0,0,0,0)','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.linewidth':1,'chart.title':'','chart.title.size':null,'chart.title.font':null,'chart.title.background':null,'chart.title.hpos':null,'chart.title.vpos':null,'chart.title.bold':true,'chart.title.color':'black','chart.title.x':null,'chart.title.y':null,'chart.title.halign':null,'chart.title.valign':null,'chart.title.xaxis':'','chart.title.xaxis.bold':true,'chart.title.xaxis.size':null,'chart.title.xaxis.font':null,'chart.title.xaxis.x':null,'chart.title.xaxis.y':null,'chart.title.xaxis.pos':null,'chart.title.yaxis':'','chart.title.yaxis.bold':true,'chart.title.yaxis.size':null,'chart.title.yaxis.font':null,'chart.title.yaxis.color':'black','chart.title.yaxis.x':null,'chart.title.yaxis.y':null,'chart.title.yaxis.pos':null,'chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.BACKGROUND] No canvas support');return;}
this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa=RG.Path,pa2=RG.path2,win=window,doc=document,ma=Math
if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.fireCustomEvent(this,'onbeforedraw');this.gutterLeft=prop['chart.gutter.left'];this.gutterRight=prop['chart.gutter.right'];this.gutterTop=prop['chart.gutter.top'];this.gutterBottom=prop['chart.gutter.bottom'];if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
RG.drawBackgroundImage(this);RG.Background.draw(this);RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];if(mouseX>=this.gutterLeft&&mouseX<=(ca.width-this.gutterRight)&&mouseY>=this.gutterTop&&mouseY<=(ca.height-this.gutterBottom)){var tooltip=prop['chart.tooltips']?prop['chart.tooltips'][0]:null
return{0:this,1:0,2:tooltip,'object':this,'index':0,'tooltip':tooltip};}
return null;};this.highlight=this.Highlight=function(shape)
{if(prop['chart.tooltips.highlight']){if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{pa2(co,'b r % % % % f % s %',prop['chart.gutter.left'],prop['chart.gutter.top'],ca.width-prop['chart.gutter.left']-prop['chart.gutter.right'],ca.height-prop['chart.gutter.top']-prop['chart.gutter.bottom'],prop['chart.highlight.fill'],prop['chart.highlight.stroke']);}}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.strokestyle']=RG.arrayClone(prop['chart.strokestyle']);this.original_colors['chart.highlight.stroke']=RG.arrayClone(prop['chart.highlight.stroke']);this.original_colors['chart.highlight.fill']=RG.arrayClone(prop['chart.highlight.fill']);}
prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':'),grad=co.createLinearGradient(this.gutterLeft,this.gutterTop,ca.width-this.gutterRight,ca.height-this.gutterRight),diff=1/(parts.length-1);for(var j=0;j<parts.length;j+=1){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};