
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