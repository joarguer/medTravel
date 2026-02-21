(function(){
    var statusEl = $('#testimonial-status');
    var form = $('#testimonialForm');
    var previewName = $('#testimonial_preview_name');
    var previewLocation = $('#testimonial_preview_location');
    var previewComment = $('#testimonial_preview_comment');
    var previewInitial = $('#testimonial_preview_initial');
    var previewStars = $('#testimonial_preview_stars');
    var commentPlaceholder = 'Your testimonial will appear like this.';

    function safe(text){
        return $('<div>').text(text || '').html();
    }

    function buildStars(rating){
        var value = parseInt(rating || 0, 10);
        if (!value || value < 1) value = 1;
        if (value > 5) value = 5;
        var html = '';
        for (var i = 1; i <= 5; i++) {
            html += '<i class="fas fa-star ' + (i <= value ? 'text-primary' : 'text-muted') + '"></i>';
        }
        return html;
    }

    function initialFromName(name){
        var text = String(name || '').trim();
        if (!text) return 'M';
        return text.charAt(0).toUpperCase();
    }

    function truncate(text, max){
        var raw = String(text || '');
        if (raw.length <= max) return raw;
        return raw.slice(0, max) + '...';
    }

    function updatePreview(){
        var name = $('#testimonial_name').val();
        var location = $('#testimonial_location').val();
        var rating = $('#testimonial_rating').val();
        var comment = $('#testimonial_comment').val();
        var safeName = safe(name);
        var safeLocation = safe(location);
        var safeComment = safe(truncate(comment, 260));

        previewName.html(safeName || '');
        previewLocation.html(safeLocation || '');
        previewComment.html(safeComment || commentPlaceholder);
        previewInitial.text(initialFromName(name));
        previewStars.html(buildStars(rating));
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
            updatePreview();
            return;
        }
        $('#testimonial_location').val(data.client_location || '');
        $('#testimonial_rating').val(String(data.rating || 5));
        $('#testimonial_comment').val(data.comment || '');
        setStatus(data.status || '');
        updatePreview();
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

    $('#testimonial_location, #testimonial_rating, #testimonial_comment').on('input change', updatePreview);
    $('#testimonial_name').on('input change', updatePreview);

    updatePreview();

    loadMine();
})();
