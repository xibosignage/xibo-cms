/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011 Daniel Garner
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

var dataSetData = function() {
    $('.XiboDataSetDataForm').submit(function() {
        return false;
    });
    
    $('.XiboDataSetDataForm input').change(XiboDataSetDataFormChange);
    $('.XiboDataSetDataForm select').change(XiboDataSetDataFormChange);
}

var XiboDataSetDataFormChange = function() {
    // Submit this form using AJAX.
    var url = $(this.form).attr("action") + "&ajax=true";

    $.ajax({
        type:"post",
        url:url,
        cache:false,
        dataType:"json",
        data:$(this.form).serialize(),
        success: XiboDataSetDataFormSubmitResponse
    });

    return false;
}

var XiboDataSetDataFormSubmitResponse = function(response) {

    if (response.success) {
        $('#' + response.uniqueReference).attr("action", response.loadFormUri);
    }
    else {
        // Login Form needed?
        if (response.login) {
            LoginBox(response.message);
            return false;
        }
        else {
            // Just an error we dont know about
            if (response.message == undefined) {
                SystemMessage(response);
            }
            else {
                SystemMessage(response.message);
            }
        }
    }

    return false;
}