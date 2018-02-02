
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Rect=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.width==='number'&&typeof conf.height==='number'&&typeof conf.id==='string'){var id=conf.id,x=conf.x,y=conf.y,width=conf.width,height=conf.height,parseConfObjectForOptions=true;}else{var id=conf,x=arguments[1],y=arguments[2],width=arguments[3],height=arguments[4];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d');this.colorsParsed=false;this.canvas.__object__=this;this.original_colors=[];this.coordsText=[];this.firstDraw=true;this.type='drawing.rect';this.isRGraph=true;this.uid=RGraph.createUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.strokestyle':'rgba(0,0,0,0)','chart.fillstyle':'red','chart.events.click':null,'chart.events.mousemove':null,'chart.shadow':false,'chart.shadow.color':'gray','chart.shadow.offsetx':3,'chart.shadow.offsety':3,'chart.shadow.blur':5,'chart.highlight.stroke':'black','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.tooltips':null,'chart.tooltips.effect':'fade','chart.tooltips.css.class':'RGraph_tooltip','chart.tooltips.event':'onclick','chart.tooltips.highlight':true,'chart.tooltips.coords.page':false,'chart.tooltips.valign':'top','chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.RECT] No canvas support');return;}
this.coords=[[Math.round(x),Math.round(y),width,height]];this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
var RG=RGraph,ca=this.canvas,co=ca.getContext('2d'),prop=this.properties,pa2=RG.path2,win=window,doc=document,ma=Math;if(RG.Effects&&typeof RG.Effects.decorate==='function'){RG.Effects.decorate(this);}
this.set=this.Set=function(name)
{var value=typeof arguments[1]==='undefined'?null:arguments[1];if(arguments.length===1&&typeof name==='object'){RG.parseObjectStyleConfig(this,name);return this;}
if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
prop[name]=value;return this;};this.get=this.Get=function(name)
{if(name.substr(0,6)!='chart.'){name='chart.'+name;}
while(name.match(/([A-Z])/)){name=name.replace(/([A-Z])/,'.'+RegExp.$1.toLowerCase());}
return prop[name.toLowerCase()];};this.draw=this.Draw=function()
{RG.fireCustomEvent(this,'onbeforedraw');this.coordsText=[];if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
pa2(co,['b']);if(prop['chart.shadow']){pa2(co,['sc',prop['chart.shadow.color'],'sx',prop['chart.shadow.offsetx'],'sy',prop['chart.shadow.offsety'],'sb',prop['chart.shadow.blur']]);}
pa2(co,['r',this.coords[0][0],this.coords[0][1],this.coords[0][2],this.coords[0][3],'f',prop['chart.fillstyle']]);RG.NoShadow(this);pa2(co,['s',prop['chart.strokestyle']]);RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];for(var i=0,len=this.coords.length;i<len;i++){var coords=this.coords[i];var left=coords[0],top=coords[1],width=coords[2],height=coords[3];if(mouseX>=left&&mouseX<=(left+width)&&mouseY>=top&&mouseY<=(top+height)){return{0:this,1:left,2:top,3:width,4:height,5:0,'object':this,'x':left,'y':top,'width':width,'height':height,'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}}
return null;};this.highlight=this.Highlight=function(shape)
{if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{RG.Highlight.rect(this,shape);}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.fillstyle']=RG.array_clone(prop['chart.fillstyle']);this.original_colors['chart.strokestyle']=RG.array_clone(prop['chart.strokestyle']);this.original_colors['chart.highlight.stroke']=RG.array_clone(prop['chart.highlight.stroke']);this.original_colors['chart.highlight.fill']=RG.array_clone(prop['chart.highlight.fill']);}
prop['chart.fillstyle']=this.parseSingleColorForGradient(prop['chart.fillstyle']);prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':'),grad=co.createLinearGradient(0,0,ca.width,0),diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1,len=parts.length;j<len;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};