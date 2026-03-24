(function (window, $) {
    'use strict';

    if (!$ || typeof $.fn === 'undefined') {
        return;
    }

    var config = {
        wrapperSelector: '.page-quick-sidebar-wrapper',
        chatSelector: '#quick_sidebar_tab_1.page-quick-sidebar-chat',
        panelSelector: '#qs-staff-detail-panel',
        messagesSelector: '#qs-staff-detail-messages',
        templatesSelector: '#qs-staff-templates',
        rowSelector: '#qs-staff-list > li.qs-staff-row',
        editLinkSelector: '.qs-staff-edit-link, #qs-staff-detail-edit',
        backSelector: '#qs-staff-detail-panel .page-quick-sidebar-back-to-list',
        detailHeaderSelector: '#qs-staff-detail-header',
        detailPhotoSelector: '#qs-staff-detail-photo',
        detailNameSelector: '#qs-staff-detail-name',
        detailRoleSelector: '#qs-staff-detail-role',
        detailSpecialtySelector: '#qs-staff-detail-specialty',
        detailStatsSelector: '#qs-staff-detail-stats',
        detailTitleSelector: '#qs-staff-detail-title',
        detailSubtitleSelector: '#qs-staff-detail-subtitle',
        detailEditSelector: '#qs-staff-detail-edit',
        defaultDetailTitle: 'Detalle del staff',
        defaultDetailSubtitle: 'Casos e items asignados',
        defaultEditUrl: 'staff_medico.php',
        shownClass: 'page-quick-sidebar-content-item-shown',
        activeClass: 'active',
        resizeNamespace: 'resize.qsStaffSidebar'
    };

    function getTemplateByStaffId($templates, staffId) {
        var idText = String(parseInt(staffId, 10) || 0);
        if (idText === '0') {
            return $();
        }

        return $templates.find('.qs-staff-template').filter(function () {
            return String($(this).attr('data-staff-id') || '') === idText;
        }).first();
    }

    $(function () {
        var $wrapper = $(config.wrapperSelector);
        var $chat = $(config.chatSelector);
        var $panel = $(config.panelSelector);
        var $messages = $(config.messagesSelector);
        var $templates = $(config.templatesSelector);
        var $detailHeader = $(config.detailHeaderSelector);
        var $detailPhoto = $(config.detailPhotoSelector);
        var $detailName = $(config.detailNameSelector);
        var $detailRole = $(config.detailRoleSelector);
        var $detailSpecialty = $(config.detailSpecialtySelector);
        var $detailStats = $(config.detailStatsSelector);

        if (!$wrapper.length || !$chat.length || !$panel.length || !$messages.length || !$templates.length) {
            return;
        }

        function refreshStaffDetailScroll() {
            if (typeof App === 'undefined' || !App || typeof App.destroySlimScroll !== 'function' || typeof App.initSlimScroll !== 'function') {
                return;
            }

            var chatUsersHeight = $wrapper.height() - ($wrapper.find('.nav-tabs').outerHeight(true) || 0);
            var navHeight = $panel.find('.page-quick-sidebar-nav').outerHeight(true) || 0;
            var formHeight = $panel.find('.page-quick-sidebar-chat-user-form').outerHeight(true) || 0;
            var messagesHeight = chatUsersHeight - navHeight - formHeight;

            if (messagesHeight < 120) {
                messagesHeight = 120;
            }

            App.destroySlimScroll($messages);
            $messages.attr('data-height', messagesHeight);
            App.initSlimScroll($messages);
        }

        $(document)
            .off('click.qsStaffRow', config.rowSelector)
            .on('click.qsStaffRow', config.rowSelector, function (event) {
                if ($(event.target).closest('.qs-staff-edit-link').length) {
                    return;
                }

                var $item = $(this);
                var staffId = parseInt($item.attr('data-staff-id') || '0', 10) || 0;
                var $template = getTemplateByStaffId($templates, staffId);

                if (!$template.length) {
                    return;
                }

                $(config.rowSelector).removeClass(config.activeClass);
                $item.addClass(config.activeClass);
                $(config.detailTitleSelector).text($item.attr('data-staff-name') || config.defaultDetailTitle);
                $(config.detailSubtitleSelector).text($item.attr('data-staff-meta') || config.defaultDetailSubtitle);
                $(config.detailEditSelector).attr('href', $item.attr('data-edit-url') || config.defaultEditUrl);
                if ($detailHeader.length) {
                    var roleText = $item.attr('data-staff-role') || '';
                    var specialtyText = $item.attr('data-staff-specialty') || '';
                    var totalCases = parseInt($item.attr('data-staff-cases') || '0', 10) || 0;
                    var pendingCases = parseInt($item.attr('data-staff-pending') || '0', 10) || 0;
                    var statsText = totalCases + ' caso' + (totalCases === 1 ? '' : 's') + ' asignado' + (totalCases === 1 ? '' : 's');
                    if (pendingCases > 0) {
                        statsText += ' · ' + pendingCases + ' pendiente' + (pendingCases === 1 ? '' : 's');
                    }
                    $detailPhoto.attr('src', $item.attr('data-staff-photo') || $detailPhoto.attr('src')).attr('alt', $item.attr('data-staff-name') || 'Staff médico');
                    $detailName.text($item.attr('data-staff-name') || config.defaultDetailTitle);
                    $detailRole.text(roleText);
                    $detailSpecialty.text(specialtyText);
                    $detailStats.text(statsText);
                    $detailRole.toggle(!!roleText);
                    $detailSpecialty.toggle(!!specialtyText);
                    $detailHeader.show();
                }
                $messages.html($template.html());

                refreshStaffDetailScroll();

                if (typeof $messages.slimScroll === 'function') {
                    $messages.slimScroll({ scrollTo: '0px' });
                }

                $chat.addClass(config.shownClass);
            });

        $(document)
            .off('click.qsStaffEdit', config.editLinkSelector)
            .on('click.qsStaffEdit', config.editLinkSelector, function (event) {
                event.stopPropagation();
            });

        $(document)
            .off('click.qsStaffBack', config.backSelector)
            .on('click.qsStaffBack', config.backSelector, function () {
                $(config.rowSelector).removeClass(config.activeClass);
            });

        $(window).off(config.resizeNamespace).on(config.resizeNamespace, refreshStaffDetailScroll);
    });
}(window, window.jQuery));