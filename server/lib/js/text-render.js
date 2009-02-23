/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */ 
var tr;

function TextRender(parent, container, direction)
{
	this.parent	= document.getElementById(parent);
	this.container = document.getElementById(container);
	this.width = this.container.clientWidth;
	this.height = this.container.clientHeight;
	this.direction = direction;
}

TextRender.prototype.TimerTick = function()
{
	if (this.direction == "up")
	{
		// If the container is above the top line
		if ((this.container.offsetTop - 10) * -1 > (this.height + 10))
		{
			this.container.style.top = this.parent.clientHeight + 10 + "px";
		}
		else
		{
			// Move the container up by 1px;
			this.container.style.top = (this.container.offsetTop - 1) + "px";			
		}
	}
	else if (this.direction == "down")
	{
		// If the container is below the bottom line
		if ((this.parent.clientHeight + 10) < (this.container.offsetTop))
		{
			this.container.style.top = (this.height + 10) * -1 + "px";
		}
		else
		{
			// Move the container down by 1px;
			this.container.style.top = (this.container.offsetTop + 1) + "px";			
		}
	}
	else if (this.direction == "left")
	{
		if ((this.container.offsetLeft + this.container.clientWidth) < this.parent.offsetLeft)
		{
			this.container.style.left = this.parent.clientWidth + 10 + "px";
		}
		else
		{
			//Move left one pixel
			this.container.style.left = this.container.offsetLeft -1 + "px";
		}
	}
	else if (this.direction == "right")
	{
		if (this.container.offsetLeft > (this.parent.offsetLeft + this.parent.clientWidth))
		{
			this.container.style.left = (this.container.clientWidth + 10) * -1 + "px";
		}
		else
		{
			//Move right one pixel
			this.container.style.left = this.container.offsetLeft +1 + "px";
		}
	}
	else
	{
		return;
	}
}