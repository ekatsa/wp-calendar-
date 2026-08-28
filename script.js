jQuery(document).ready(function($){
    let currentDate = '';

    // Load notes for each date
    $('td[data-date]').each(function(){
        const date = $(this).data('date');
        const container = $(this).find('.note-preview');

        $.post(cn_ajax.ajax_url, {
            action: 'get_note',
            nonce: cn_ajax.nonce,
            date: date
        }, function(response){
            if(response.success){
                container.text(response.data.note);
            } else {
                container.text('');
            }
        });
    });

    // Click on date cell
$(document).on('click', 'td[data-date]', function(){
    currentDate = $(this).data('date');
    $('#cn-modal-date').text(currentDate);

    // Load existing note
    $.post(cn_ajax.ajax_url, {
        action: 'get_note',
        nonce: cn_ajax.nonce,
        date: currentDate
    }, function(response){
        $('#cn-note-text').val(response.success ? response.data.note : '');
        $('#cn-note-modal').show();
    });
});

// Save note
$(document).on('click', '#cn-save-note', function(){
    const note = $('#cn-note-text').val();

    $.post(cn_ajax.ajax_url, {
        action: 'save_note',
        nonce: cn_ajax.nonce,
        date: currentDate,
        note: note
    }, function(response){
        if(response.success){
            // Update preview
            $('td[data-date="' + currentDate + '"] .note-preview').text(note);
            $('#cn-note-modal').hide();
        } else {
            alert('Error saving note.');
        }
    });
});

// Close modal
$(document).on('click', '#cn-close-modal', function(){
    $('#cn-note-modal').hide();
});
});
