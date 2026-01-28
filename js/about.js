$(document).ready(function(){
    let url = 'admin/ajax/about_edit.php';
    let data = {
        'tipo': 'get_home'
    };
    $.post(url, data, function(res){
        let data = JSON.parse(res);
        $('#home').val(data.home);
        let img = data.header.img;
        console.log(img);
        //bg-breadcrumb
        $('.bg-breadcrumb').css('background','linear-gradient(rgba(19, 53, 123, 0.5), rgba(19, 53, 123, 0.5)), url(../'+img+')');
        $('.bg-breadcrumb').css('background-size','cover');
    });
});