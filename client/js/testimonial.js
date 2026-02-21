(function(){
    var statusEl = $('#testimonial-status');
    var form = $('#testimonialForm');

    function safe(text){
        return $('<div>').text(text || '').html();
    }

    function setStatus(status){
        var map = {
            pending: { cls: 'alert-info', text: 'En revision' },
            approved: { cls: 'alert-success', text: 'Publicado' },
            rejected: { cls: 'alert-danger', text: 'Rechazado. Puedes reenviar.' },
            archived: { cls: 'alert-warning', text: 'Archivado' }
        };
        var def = { cls: 'alert-info', text: 'No testimonial yet. Submit one to start.' };
        var data = map[status] || def;
        statusEl.attr('class', 'alert ' + data.cls).text(data.text);
    }

    function fillForm(data){
        if (!data) {
            setStatus('');
            return;
        }
        $('#testimonial_location').val(data.client_location || '');
        $('#testimonial_rating').val(String(data.rating || 5));
        $('#testimonial_comment').val(data.comment || '');
        setStatus(data.status || '');
    }

    function loadMine(){
        $.getJSON('/client/ajax/testimonials.php', { action: 'get_mine' }, function(res){
            if (res && res.ok) {
                fillForm(res.data);
                return;
            }
            setStatus('');
        }).fail(function(){
            statusEl.attr('class', 'alert alert-danger').text('Error loading testimonial');
        });
    }

    form.on('submit', function(e){
        e.preventDefault();
        var payload = {
            action: 'create_or_update',
            location: $('#testimonial_location').val(),
            rating: $('#testimonial_rating').val(),
            comment: $('#testimonial_comment').val()
        };
        $.ajax({
            url: '/client/ajax/testimonials.php',
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function(res){
            if (res && res.ok) {
                setStatus('pending');
                if (window.toastr) {
                    toastr.success('Testimonial submitted for review');
                }
                return;
            }
            var message = res && res.message ? safe(res.message) : 'Error submitting testimonial';
            if (window.toastr) {
                toastr.error(message);
            } else {
                alert(message);
            }
        }).fail(function(){
            if (window.toastr) {
                toastr.error('Error submitting testimonial');
            } else {
                alert('Error submitting testimonial');
            }
        });
    });

    loadMine();
})();
