
RGraph=window.RGraph||{isRGraph:true};RGraph.Drawing=RGraph.Drawing||{};RGraph.Drawing.Marker2=function(conf)
{if(typeof conf==='object'&&typeof conf.x==='number'&&typeof conf.y==='number'&&typeof conf.id==='string'&&typeof conf.text==='string'){var id=conf.id,canvas=document.getElementById(id),x=conf.x,y=conf.y,text=conf.text,parseConfObjectForOptions=true;}else{var id=conf,canvas=document.getElementById(id),x=arguments[1],y=arguments[2],text=arguments[3];}
this.id=id;this.canvas=document.getElementById(this.id);this.context=this.canvas.getContext('2d')
this.colorsParsed=false;this.canvas.__object__=this;this.original_colors=[];this.firstDraw=true;this.x=x;this.y=y;this.text=text;this.type='drawing.marker2';this.isRGraph=true;this.uid=RGraph.CreateUID();this.canvas.uid=this.canvas.uid?this.canvas.uid:RGraph.CreateUID();this.properties={'chart.strokestyle':'black','chart.fillstyle':'white','chart.text.color':'black','chart.text.size':12,'chart.text.font':'Segoe UI, Arial, Verdana, sans-serif','chart.text.accessible':true,'chart.text.accessible.overflow':'visible','chart.text.accessible.pointerevents':true,'chart.events.click':null,'chart.events.mousemove':null,'chart.shadow':true,'chart.shadow.color':'gray','chart.shadow.offsetx':3,'chart.shadow.offsety':3,'chart.shadow.blur':5,'chart.highlight.stroke':'rgba(0,0,0,0)','chart.highlight.fill':'rgba(255,255,255,0.7)','chart.tooltips':null,'chart.tooltips.highlight':true,'chart.tooltips.event':'onclick','chart.voffset':20,'chart.clearto':'rgba(0,0,0,0)'}
if(!this.canvas){alert('[DRAWING.MARKER2] No canvas support');return;}
this.coords=[];this.coordsText=[];this.$0={};if(!this.canvas.__rgraph_aa_translated__){this.context.translate(0.5,0.5);this.canvas.__rgraph_aa_translated__=true;}
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
{co.lineWidth=1;RG.fireCustomEvent(this,'onbeforedraw');this.metrics=RG.measureText(this.text,prop['chart.text.bold'],prop['chart.text.font'],prop['chart.text.size']);if(this.x+this.metrics[0]>=ca.width){this.alignRight=true;}
if(!this.colorsParsed){this.parseColors();this.colorsParsed=true;}
var x=this.alignRight?this.x-this.metrics[0]-6:this.x,y=this.y-6-prop['chart.voffset']-this.metrics[1],width=this.metrics[0]+6,height=this.metrics[1]+6;this.coords[0]=[x,y,width,height];this.coordsText=[];co.lineWidth=prop['chart.linewidth'];if(prop['chart.shadow']){RG.setShadow(this,prop['chart.shadow.color'],prop['chart.shadow.offsetx'],prop['chart.shadow.offsety'],prop['chart.shadow.blur']);}
co.strokeStyle=prop['chart.strokestyle'];co.fillStyle=prop['chart.fillstyle'];co.strokeRect(x+(this.alignRight?width:0),y,0,height+prop['chart.voffset']-6);co.strokeRect(x,y,width,height);co.fillRect(x,y,width,height);RG.noShadow(this);co.fillStyle=prop['chart.text.color'];RG.text2(this,{font:prop['chart.text.font'],size:prop['chart.text.size'],x:ma.round(this.x)-(this.alignRight?this.metrics[0]+3:-3),y:this.y-3-prop['chart.voffset'],text:this.text,valign:'bottom',halign:'left',tag:'labels'});this.coords[0].push([x,y,width,height]);RG.noShadow(this);co.textBaseline='alphabetic';RG.installEventListeners(this);if(this.firstDraw){this.firstDraw=false;RG.fireCustomEvent(this,'onfirstdraw');this.firstDrawFunc();}
RG.fireCustomEvent(this,'ondraw');return this;};this.exec=function(func)
{func(this);return this;};this.getObjectByXY=function(e)
{if(this.getShape(e)){return this;}};this.getShape=function(e)
{var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1];if(mouseX>=this.coords[0][0]&&mouseX<=(this.coords[0][0]+this.coords[0][2])){if(mouseY>=this.coords[0][1]&&mouseY<=(this.coords[0][1]+this.coords[0][3])){return{0:this,1:this.coords[0][0],2:this.coords[0][1],3:this.coords[0][2],4:this.coords[0][3],5:0,'object':this,'x':this.coords[0][0],'y':this.coords[0][1],'width':this.coords[0][2],'height':this.coords[0][3],'index':0,'tooltip':prop['chart.tooltips']?prop['chart.tooltips'][0]:null};}}
return null;};this.highlight=this.Highlight=function(shape)
{if(prop['chart.tooltips.highlight']){if(typeof prop['chart.highlight.style']==='function'){(prop['chart.highlight.style'])(shape);}else{pa2(co,['b','r',this.coords[0][0],this.coords[0][1],this.coords[0][2],this.coords[0][3],'f',prop['chart.highlight.fill'],'s',prop['chart.highlight.stroke']]);}}};this.parseColors=function()
{if(this.original_colors.length===0){this.original_colors['chart.fillstyle']=RG.array_clone(prop['chart.fillstyle']);this.original_colors['chart.strokestyle']=RG.array_clone(prop['chart.strokestyle']);this.original_colors['chart.highlight.fill']=RG.array_clone(prop['chart.highlight.fill']);this.original_colors['chart.highlight.stroke']=RG.array_clone(prop['chart.highlight.stroke']);this.original_colors['chart.text.color']=RG.array_clone(prop['chart.text.color']);}
prop['chart.fillstyle']=this.parseSingleColorForGradient(prop['chart.fillstyle']);prop['chart.strokestyle']=this.parseSingleColorForGradient(prop['chart.strokestyle']);prop['chart.highlight.stroke']=this.parseSingleColorForGradient(prop['chart.highlight.stroke']);prop['chart.highlight.fill']=this.parseSingleColorForGradient(prop['chart.highlight.fill']);prop['chart.text.color']=this.parseSingleColorForGradient(prop['chart.text.color']);};this.reset=function()
{};this.parseSingleColorForGradient=function(color)
{if(!color){return color;}
if(typeof color==='string'&&color.match(/^gradient\((.*)\)$/i)){var parts=RegExp.$1.split(':'),grad=co.createLinearGradient(this.x,this.y,this.x+this.metrics[0],this.y),diff=1/(parts.length-1);grad.addColorStop(0,RG.trim(parts[0]));for(var j=1;j<parts.length;++j){grad.addColorStop(j*diff,RG.trim(parts[j]));}}
return grad?grad:color;};this.on=function(type,func)
{if(type.substr(0,2)!=='on'){type='on'+type;}
if(typeof this[type]!=='function'){this[type]=func;}else{RG.addCustomEventListener(this,type,func);}
return this;};this.firstDrawFunc=function()
{};RG.register(this);if(parseConfObjectForOptions){RG.parseObjectStyleConfig(this,conf.options);}};