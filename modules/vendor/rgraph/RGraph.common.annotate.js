
RGraph=window.RGraph||{isRGraph:true};(function(win,doc,undefined)
{var RG=RGraph,ua=navigator.userAgent,ma=Math;RG.annotating_canvas_onmousedown=function(e)
{if(e.button===0){e.target.__object__.Set('chart.mousedown',true);var obj=e.target.__object__,prop=obj.properties
obj.context.beginPath();obj.context.strokeStyle=obj.Get('chart.annotate.color');obj.context.lineWidth=obj.Get('chart.annotate.linewidth');var mouseXY=RG.getMouseXY(e),mouseX=mouseXY[0],mouseY=mouseXY[1]
if(obj.type==='bar'&&prop['chart.variant']==='3d'){var adjustment=prop['chart.variant.threed.angle']*mouseXY[0];mouseY-=adjustment;}
RG.Registry.Set('annotate.actions',[obj.Get('chart.annotate.color')]);obj.context.moveTo(mouseX,mouseY);RG.Registry.Set('annotate.last.coordinates',[mouseX,mouseY]);RG.Registry.Set('started.annotating',false);RG.Registry.Set('chart.annotating',obj);RG.FireCustomEvent(obj,'onannotatebegin');}
return false;};RG.annotating_window_onmouseup=function(e)
{var obj=RG.Registry.Get('chart.annotating');var win=window;if(e.button!=0||!obj){return;}
var tags=doc.getElementsByTagName('canvas');for(var i=0;i<tags.length;++i){if(tags[i].__object__){tags[i].__object__.Set('chart.mousedown',false);}}
if(RG.Registry.Get('annotate.actions')&&RG.Registry.Get('annotate.actions').length>0&&win.localStorage){var id='__rgraph_annotations_'+e.target.id+'__';var annotations=win.localStorage[id]?win.localStorage[id]+'|':'';annotations+=RG.Registry.Get('annotate.actions');win.localStorage[id]=annotations;}
RG.Registry.Set('annotate.actions',[]);RG.FireCustomEvent(obj,'onannotateend');};RGraph.annotating_canvas_onmousemove=function(e)
{var obj=e.target.__object__;var prop=obj.properties;var mouseXY=RG.getMouseXY(e);var mouseX=mouseXY[0];var mouseY=mouseXY[1];var lastXY=RG.Registry.Get('annotate.last.coordinates');if(obj.Get('chart.mousedown')){if(obj.type==='bar'&&prop['chart.variant']==='3d'){var adjustment=prop['chart.variant.threed.angle']*mouseXY[0];mouseY-=adjustment;}
obj.context.beginPath();if(!lastXY){obj.context.moveTo(mouseX,mouseY)}else{obj.context.strokeStyle=obj.properties['chart.annotate.color'];obj.context.moveTo(lastXY[0],lastXY[1]);obj.context.lineTo(mouseX,mouseY);}
RG.Registry.Set('annotate.actions',RG.Registry.Get('annotate.actions')+'|'+mouseX+','+mouseY);RG.Registry.Set('annotate.last.coordinates',[mouseX,mouseY]);RG.FireCustomEvent(obj,'onannotate');obj.context.stroke();}};RG.ShowPalette=RG.Showpalette=function(e)
{var isSafari=navigator.userAgent.indexOf('Safari')?true:false;e=RG.FixEventObject(e);var canvas=e.target.parentNode.__canvas__,context=canvas.getContext('2d'),obj=canvas.__object__,div=document.createElement('DIV'),coords=RG.getMouseXY(e)
div.__object__=obj;div.className='RGraph_palette';div.style.position='absolute';div.style.backgroundColor='white';div.style.border='1px solid black';div.style.left=0;div.style.top=0;div.style.padding='3px';div.style.paddingLeft='5px';div.style.opacity=0;div.style.boxShadow='rgba(96,96,96,0.5) 3px 3px 3px';div.style.WebkitBoxShadow='rgba(96,96,96,0.5) 3px 3px 3px';div.style.MozBoxShadow='rgba(96,96,96,0.5) 3px 3px 3px';var colors=['Black','Red','Yellow','Green','Orange','White','Magenta','Pink'];for(var i=0,len=colors.length;i<len;i+=1){var div2=doc.createElement('DIV');div2.cssClass='RGraph_palette_color';div2.style.fontSize='12pt';div2.style.cursor='pointer';div2.style.padding='1px';div2.style.paddingRight='10px';div2.style.textAlign='left';var span=document.createElement('SPAN');span.style.display='inline-block';span.style.marginRight='9px';span.style.width='17px';span.style.height='17px';span.style.top='2px';span.style.position='relative';span.style.backgroundColor=colors[i];div2.appendChild(span);div2.innerHTML+=colors[i];div2.onmouseover=function()
{this.style.backgroundColor='#eee';}
div2.onmouseout=function()
{this.style.backgroundColor='';}
div2.onclick=function(e)
{var color=this.childNodes[0].style.backgroundColor;obj.Set('chart.annotate.color',color);}
div.appendChild(div2);}
doc.body.appendChild(div);div.style.left=e.pageX+'px';div.style.top=e.pageY+'px';if((e.pageX+(div.offsetWidth+5))>document.body.offsetWidth){div.style.left=(e.pageX-div.offsetWidth)+'px';}
RGraph.Registry.Set('chart.palette',div);setTimeout(function(){div.style.opacity=0.2;},50);setTimeout(function(){div.style.opacity=0.4;},100);setTimeout(function(){div.style.opacity=0.6;},150);setTimeout(function(){div.style.opacity=0.8;},200);setTimeout(function(){div.style.opacity=1;},250);RGraph.hideContext();window.onclick=function()
{RG.hidePalette();}
e.stopPropagation();return false;};RG.clearAnnotations=RG.ClearAnnotations=function(canvas)
{if(typeof canvas==='string'){var id=canvas;canvas=doc.getElementById(id);}else{var id=canvas.id}
var obj=canvas.__object__;if(win.localStorage&&win.localStorage['__rgraph_annotations_'+id+'__']&&win.localStorage['__rgraph_annotations_'+id+'__'].length){win.localStorage['__rgraph_annotations_'+id+'__']=[];RGraph.FireCustomEvent(obj,'onannotateclear');}};RG.replayAnnotations=RG.ReplayAnnotations=function(obj)
{if(!win.localStorage){return;}
var context=obj.context;var annotations=win.localStorage['__rgraph_annotations_'+obj.id+'__'];var i,len,move,coords;context.beginPath();context.lineWidth=obj.Get('annotate.linewidth');if(annotations&&annotations.length){annotations=annotations.split('|');}else{return;}
for(i=0,len=annotations.length;i<len;++i){if(annotations[i].match(/[a-z]+/)){context.stroke();context.beginPath();context.strokeStyle=annotations[i];move=true;continue;}
coords=annotations[i].split(',');coords[0]=Number(coords[0]);coords[1]=Number(coords[1]);if(move){context.moveTo(coords[0],coords[1]);move=false;}else{context.lineTo(coords[0],coords[1]);}}
context.stroke();};window.addEventListener('load',function(e)
{setTimeout(function()
{var tags=doc.getElementsByTagName('canvas');for(var i=0;i<tags.length;++i){if(tags[i].__object__&&tags[i].__object__.isRGraph&&tags[i].__object__.Get('chart.annotatable')){RG.replayAnnotations(tags[i].__object__);}}},100);},false);})(window,document);