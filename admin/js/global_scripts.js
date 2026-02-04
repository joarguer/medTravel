// Scope script to avoid clashing with other globals
(() => {
    const currentPage = window.location.pathname.split('/').pop();

    const activateMenu = (navRoot) => {
        if (!navRoot) return;
        const links = navRoot.querySelectorAll('a');
        links.forEach((link) => {
            const href = (link.getAttribute('href') || '').replace('./', '');
            const cleanHref = href.split('?')[0];
            if (cleanHref === currentPage) {
                const li = link.closest('li');
                if (li) li.classList.add('active');

                const dropdownLi = link.closest('li.dropdown');
                if (dropdownLi) {
                    dropdownLi.classList.add('active', 'selected', 'open');
                }

                let ancestor = li ? li.parentElement : null;
                while (ancestor && ancestor !== navRoot) {
                    if (ancestor.classList.contains('dropdown-menu')) {
                        const dropdownParent = ancestor.closest('li');
                        if (dropdownParent && dropdownParent !== dropdownLi) {
                            dropdownParent.classList.add('active', 'selected');
                        }
                    }
                    ancestor = ancestor.parentElement;
                }
            }
        });
    };

    document.querySelectorAll('.navbar-nav').forEach(activateMenu);

    // Ensure dropdowns work even if hover plugin fails
    if (window.jQuery) {
        window.jQuery(function($){
            $('.dropdown-toggle').dropdown();
            // Responsive tables: wrap any table not already in a .table-responsive
            $('.page-content table.table').each(function(){
                if ($(this).closest('.table-responsive').length === 0) {
                    $(this).wrap('<div class="table-responsive"></div>');
                }
            });
        });
    }
})();
