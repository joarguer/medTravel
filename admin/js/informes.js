let dataCarrucel = [];
$(document).ready(function(){
    let url = 'ajax/home_edit.php';
    let data = {
        'tipo': 'get_home'
    };
    $.post(url, data, function(res){
        let response = JSON.parse(res);
        ComponentsEditors.init(); 
    });
});

function carga(ventana){
    //agrego hide a todos los demas con class page-content-col e id diferente a ventana
    $('.page-content-col').each(function(){
        if($(this).attr('id') != ventana){
            $(this).addClass('hide');
        } else{
            $(this).removeClass('hide');
        }
    });
    //separo ventana _
    let ventanaSplit = ventana.split('_');
    let ventanaFinal = ventanaSplit[0];
    //aGREGO active btn-carrucel a todos los demas con class btn-carrucel e id diferente a ventana
    $('.btn-carrucel').each(function(){
        if($(this).attr('id') != ventanaFinal){
            $(this).removeClass('active');
        } else{
            $(this).addClass('active');
        }
    });
}

function notification(text,title,status){
    if(status == "success"){
      toastr.success(text,title)
    } else{
      toastr.error(text,title)
    }
  
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "positionClass": "toast-top-right",
        "onclick": null,
        "showDuration": "1000",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }
}

var ComponentsEditors = function () {
    
    var handleWysihtml5 = function () {
        if (!jQuery().wysihtml5) {
            return;
        }

        if ($('.wysihtml5').size() > 0) {
            $('.wysihtml5').wysihtml5({
                "stylesheets": ["../assets/global/plugins/bootstrap-wysihtml5/wysiwyg-color.css"]
            });
        }
    }

    return {
        //main function to initiate the module
        init: function () {
            handleWysihtml5();
        }
    };

}();