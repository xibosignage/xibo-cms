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
function week_change(value) {
	var field = document.getElementById("3");
	
	field.value = parseInt(field.value) + parseInt(value);
	
	return;
}

function month_change(value) {
	var field = document.getElementById("4");
	
	field.value = parseInt(field.value) + parseInt(value);
	
	return;
}

function reset_time() {
	document.getElementById("3").value = 0;
	document.getElementById("4").value = 0;
}