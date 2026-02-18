(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner(0);


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 45) {
            $('.navbar').addClass('sticky-top shadow-sm');
        } else {
            $('.navbar').removeClass('sticky-top shadow-sm');
        }
    });


    // International Tour carousel
    $(".InternationalTour-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        center: false,
        dots: true,
        loop: true,
        margin: 25,
        nav : false,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ],
        responsiveClass: true,
        responsive: {
            0:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:2
            },
            1200:{
                items:3
            }
        }
    });


    // packages carousel
    $(".packages-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        center: false,
        dots: false,
        loop: true,
        margin: 25,
        nav : true,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ],
        responsiveClass: true,
        responsive: {
            0:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:2
            },
            1200:{
                items:3
            }
        }
    });


    // testimonial carousel
    $(".testimonial-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1000,
        center: true,
        dots: true,
        loop: true,
        margin: 25,
        nav : true,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ],
        responsiveClass: true,
        responsive: {
            0:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:2
            },
            1200:{
                items:3
            }
        }
    });

    
   // Back to top button
   $(window).scroll(function () {
    if ($(this).scrollTop() > 300) {
        $('.back-to-top').fadeIn('slow');
    } else {
        $('.back-to-top').fadeOut('slow');
    }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    }); 

})(jQuery);

let URLactual = window.location.pathname;
//extraigo el texto de la pagina actual
let url = URLactual.split('/');
let urlPagina = url[1];
console.log(urlPagina);
let pageSidebarMenu = document.querySelector('.navbar-nav');

// Recorre los enlaces en el menú y verifica si la URL coincide con la dirección del enlace
if (pageSidebarMenu) {
    let menuLinks = pageSidebarMenu.querySelectorAll('a');
    menuLinks.forEach(function(link) {
        if (link.getAttribute('href') === urlPagina) {
            //quito active
            let activeLink = pageSidebarMenu.querySelector('.active');
            if (activeLink) {
                activeLink.classList.remove('active');
            }
            link.classList.add('active');
        }
    });
}

function submit(){
    let name = document.getElementById('name').value;
    let email = document.getElementById('email').value;
    let subject = document.getElementById('subject').value;
    let message = document.getElementById('message').value;
    if (name === '' || email === '' || subject === '' || message === '') {
        let text = 'All fields are required';
        let title = 'Error';
        let status = 'error';
        notification(text,title,status);
    } else {
        let text = 'Message sent successfully';
        let title = 'Success';
        let status = 'success';
        notification(text,title,status);
    }
}

///////////////////////// NOTIFICACIONES /////////////////////////
function notification(text,title,status){
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
        "hideMethod": "fadeOut",
        "background": "#000",
        "color": "#000",
        "borderRadius": "10px",
        "progressBar": true
    }
    if(status == "success"){
      toastr.success(text,title)
    } else{
      toastr.error(text,title)
    }
}

(function () {
    var KEY_STARTED = 'mt_booking_started';
    var KEY_STEP1_SUBMITTED = 'mt_booking_step1_submitted';
    var KEY_DRAFT = 'mt_booking_draft';
    var KEY_SELECTED_SERVICES = 'mt_selected_services';
    var KEY_PRESELECTED_OFFER = 'mt_preselected_offer_id';
    var RESET_KEYS = [
        KEY_STARTED,
        KEY_DRAFT,
        KEY_STEP1_SUBMITTED,
        KEY_SELECTED_SERVICES,
        KEY_PRESELECTED_OFFER
    ];
    var ENABLE_FLOATING_CART = (typeof window.MT_ENABLE_FLOATING_CART === 'undefined')
        ? false
        : !!window.MT_ENABLE_FLOATING_CART;

    function safeParse(raw) {
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function getSelectedServices() {
        var parsed = safeParse(localStorage.getItem(KEY_SELECTED_SERVICES));
        return Array.isArray(parsed) ? parsed : [];
    }

    function hasBookingProgress() {
        return localStorage.getItem(KEY_STARTED) === '1';
    }

    function hasWizardSelectionState() {
        if (localStorage.getItem(KEY_STEP1_SUBMITTED) === '1') return true;
        if (localStorage.getItem(KEY_PRESELECTED_OFFER)) return true;
        return getSelectedServices().length > 0;
    }

    function isWizardPage() {
        var path = String((window.location && window.location.pathname) || '').toLowerCase();
        return path.indexOf('/booking/wizard.php') !== -1;
    }

    function ensureScrollFallback() {
        if (typeof window.scrollToBooking === 'function') return;
        window.scrollToBooking = function (offerId) {
            try {
                localStorage.setItem(KEY_STARTED, '1');
                if (offerId) {
                    localStorage.setItem(KEY_PRESELECTED_OFFER, String(offerId));
                }
            } catch (e) {}

            var bookingSection = document.getElementById('booking-section');
            if (bookingSection) {
                bookingSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            window.location.href = '/booking.php#booking-section';
        };
    }

    function ensureStyles() {
        if (document.getElementById('mt-booking-floating-style')) return;
        var style = document.createElement('style');
        style.id = 'mt-booking-floating-style';
        style.textContent =
            '.mt-booking-floating{position:fixed;left:20px;right:auto;bottom:20px;transform:none;z-index:999;max-width:320px;width:calc(100% - 40px);background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 10px 24px rgba(15,23,42,.14);padding:14px;display:none}' +
            '.mt-booking-floating.show{display:block}' +
            '.mt-booking-floating h6{margin:0 0 6px 0;font-weight:700;color:#0f172a}' +
            '.mt-booking-floating .mt-booking-lines{font-size:13px;color:#475569;line-height:1.45;margin-bottom:10px;max-height:120px;overflow:auto}' +
            '.mt-booking-floating .btn{border-radius:999px;font-weight:600}' +
            '.mt-booking-floating .btn-outline-secondary{border-color:#cbd5e1;color:#334155}' +
            '.mt-booking-floating .btn-outline-danger{border-color:#fecaca;color:#b91c1c}' +
            '@media (max-width: 767px){.mt-booking-floating{width:90%;max-width:90%;left:50%;right:auto;bottom:15px;transform:translateX(-50%);}}';
        document.head.appendChild(style);
    }

    function ensureWidget() {
        var existing = document.getElementById('mt-booking-floating');
        if (existing) return existing;

        var wrapper = document.createElement('div');
        wrapper.id = 'mt-booking-floating';
        wrapper.className = 'mt-booking-floating';
        wrapper.innerHTML =
            '<h6>Tienes una solicitud en progreso</h6>' +
            '<div class="mt-booking-lines">Retoma tu solicitud donde la dejaste.</div>' +
            '<div class="d-flex gap-2">' +
            '<button type="button" class="btn btn-primary btn-sm" id="mt-booking-floating-continue">Continuar solicitud</button>' +
            '<button type="button" class="btn btn-outline-danger btn-sm" id="mt-booking-floating-reset">Limpiar</button>' +
            '</div>';
        document.body.appendChild(wrapper);
        return wrapper;
    }

    function renderFloatingSummary() {
        var widget = ensureWidget();
        if (!widget) return;

        if (!hasBookingProgress()) {
            widget.classList.remove('show');
            return;
        }
        widget.classList.add('show');
    }

    function emitFieldEvents(field) {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function clearBookingFormIfPresent() {
        var form = document.querySelector('.book-tour-form');
        if (!form) return;
        if (!window.confirm('¿Deseas vaciar también el formulario actual?')) return;

        var fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function(field) {
            var name = field.name || '';
            if (!name) return;
            if (name === 'origin') return;

            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = false;
                emitFieldEvents(field);
                return;
            }

            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
                emitFieldEvents(field);
                return;
            }

            field.value = '';
            emitFieldEvents(field);
        });
    }

    function clearBookingState() {
        RESET_KEYS.forEach(function(key) {
            try { localStorage.removeItem(key); } catch (e) {}
        });
        try { sessionStorage.removeItem('preselected_offer_id'); } catch (e) {}

        clearBookingFormIfPresent();
        renderFloatingSummary();
        window.dispatchEvent(new Event('mt-booking-state-changed'));
    }

    function bindActions() {
        var continueBtn = document.getElementById('mt-booking-floating-continue');
        var resetBtn = document.getElementById('mt-booking-floating-reset');
        var widget = document.getElementById('mt-booking-floating');
        if (!continueBtn || !resetBtn || !widget) return;

        continueBtn.addEventListener('click', function () {
            if (hasWizardSelectionState()) {
                window.location.href = '/booking/wizard.php';
                return;
            }
            window.location.href = '/booking.php#booking-section';
        });

        resetBtn.addEventListener('click', function () {
            if (!window.confirm('¿Deseas vaciar el carrito/resumen de booking?')) return;
            clearBookingState();
        });
    }

    function initFloatingSummary() {
        ensureScrollFallback();
        if (!ENABLE_FLOATING_CART) {
            var existingDisabled = document.getElementById('mt-booking-floating');
            if (existingDisabled) {
                existingDisabled.classList.remove('show');
                existingDisabled.style.display = 'none';
            }
            return;
        }
        if (isWizardPage()) {
            var existing = document.getElementById('mt-booking-floating');
            if (existing) {
                existing.classList.remove('show');
                existing.style.display = 'none';
            }
            return;
        }
        ensureStyles();
        ensureWidget();
        bindActions();
        renderFloatingSummary();
        window.addEventListener('storage', renderFloatingSummary);
        window.addEventListener('mt-booking-state-changed', renderFloatingSummary);
        setInterval(renderFloatingSummary, 1200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFloatingSummary);
    } else {
        initFloatingSummary();
    }
})();
