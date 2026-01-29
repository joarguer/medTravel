let URLactual = window.location.pathname;
//extraigo el texto de la pagina actual
let url = URLactual.split('/');
let urlPagina = url[2];
let pageSidebarMenu = document.querySelector('.navbar-nav');

// Recorre los enlaces en el menú y verifica si la URL coincide con la dirección del enlace
if (pageSidebarMenu) {
    let menuLinks = pageSidebarMenu.querySelectorAll('li ul li a');
    menuLinks.forEach(function(link) {
        if (link.getAttribute('href') === urlPagina) {
            //console.log(urlPagina,link.getAttribute('href'));
            let abuelo = link.parentElement.parentElement.parentElement;
            abuelo.classList.add('active');
            abuelo.classList.add('selected');
            abuelo.classList.add('open');
            //busco el bisabuelo del enlace y le agrego la clase open
            let parent = link.parentElement.parentElement.parentElement.parentElement.parentElement;
            //parent.classList.add('open');
            parent.classList.add('selected');
            //busco el padre del enlace y le agrego la clase active
            let parentLink = link.parentElement.parentElement.parentElement;
            parentLink.classList.add('active');
            let linkParent = link.parentElement;
            linkParent.classList.add('active');
        }
    });
}