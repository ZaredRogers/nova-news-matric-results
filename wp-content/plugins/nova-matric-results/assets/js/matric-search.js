jQuery(document).ready(function($) {
    $('.matric-search-form').on('submit', function(e) {
        var form = $(this);
        var input = form.find('#exam_number');
        var examNumber = input.val().trim();
        var errorContainer = form.find('.matric-search-error');
        
        // Reset Error State
        input.removeClass('error-field');
        errorContainer.hide().text('');

        // Client-Side Validation
        if (!/^\d+$/.test(examNumber)) {
            e.preventDefault();
            input.addClass('error-field');
            errorContainer.text('Please enter a valid examination number').show();
            return;
        }

        // AJAX Check if exists
        e.preventDefault(); // Stop standard submission first
        
        // Disable button
        var btn = form.find('.matric-search-btn');
        var btnText = btn.text();
        btn.prop('disabled', true).text('Searching...');

        $.ajax({
            url: matric_search_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'check_matric_exam',
                exam_number: examNumber,
                matric_nonce: matric_search_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // It exists, submit the form normally to the results page!
                    // We need to bypass this handler to submit
                    form.off('submit'); // Unbind this handler
                    form[0].submit(); // Native submit
                } else {
                    // Not found or error
                    input.addClass('error-field');
                    var msg = response.data ? response.data : 'Please enter a valid examination number';
                    errorContainer.text(msg).show();
                    btn.prop('disabled', false).text(btnText);
                    
                    // Rebind if we want to allow trying again? Yes, the handler is bound to the selector, but if we form.off('submit') it removes it from this element.
                    // Actually, since we didn't submit, we should ideally not remove the handler unless we're submitting.
                    // But wait, invalid result means we STAY here. So we should NOT unbind.
                    // So wait - simple logic:
                    // If success -> form.off('submit').submit()
                    // If fail -> show error, do nothing (preventDefault already called)
                }
            },
            error: function() {
                input.addClass('error-field');
                errorContainer.text('An error occurred. Please try again.').show();
                btn.prop('disabled', false).text(btnText);
            }
        });
    });
    
    // Clear error on input
    $('#exam_number').on('input', function() {
        $(this).removeClass('error-field');
        $('.matric-search-error').hide();
    });
});
