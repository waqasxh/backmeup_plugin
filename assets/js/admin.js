jQuery(document).ready(function ($) {

    // Sync buttons
    $('#bmu-sync-pull').on('click', function () {
        performSync('pull');
    });

    $('#bmu-sync-push').on('click', function () {
        if (!confirm('Are you sure you want to push local changes to live? This will overwrite the live site.')) {
            return;
        }
        performSync('push');
    });

    function performSync(direction) {
        var $progress = $('#bmu-sync-progress');
        var $result = $('#bmu-sync-result');
        var $message = $('#bmu-sync-message');
        var $buttons = $('.bmu-sync-buttons button');

        $buttons.prop('disabled', true);
        $result.hide();
        $progress.show();
        $message.text('Starting sync...');

        // Animate progress bar
        var progress = 0;
        var progressInterval = setInterval(function () {
            progress += 5;
            if (progress > 90) progress = 90;
            $('.bmu-progress-fill').css('width', progress + '%');
        }, 500);

        $.ajax({
            url: bmuAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'bmu_sync',
                direction: direction,
                nonce: bmuAjax.nonce
            },
            success: function (response) {
                clearInterval(progressInterval);
                $('.bmu-progress-fill').css('width', '100%');

                setTimeout(function () {
                    $progress.hide();

                    if (response.success) {
                        $result.removeClass('error').addClass('success');
                        $result.html('<strong>Success!</strong> ' + response.data.message);
                    } else {
                        $result.removeClass('success').addClass('error');
                        $result.html('<strong>Error!</strong> ' + response.data.message);
                    }

                    $result.show();
                    $buttons.prop('disabled', false);

                    // Reload page after 2 seconds on success
                    if (response.success) {
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    }
                }, 500);
            },
            error: function (xhr, status, error) {
                clearInterval(progressInterval);
                $progress.hide();
                $result.removeClass('success').addClass('error');
                $result.html('<strong>Error!</strong> Connection failed. Please try again.');
                $result.show();
                $buttons.prop('disabled', false);
            }
        });
    }

    // Settings form
    $('#bmu-settings-form').on('submit', function (e) {
        e.preventDefault();

        var formData = $(this).serializeArray();
        var data = {
            action: 'bmu_save_settings',
            nonce: $('#bmu_nonce').val()
        };

        // Convert form data to object
        $.each(formData, function (i, field) {
            if (field.name === 'exclude_paths[]') {
                if (!data.exclude_paths) {
                    data.exclude_paths = [];
                }
                data.exclude_paths.push(field.value);
            } else {
                data[field.name] = field.value;
            }
        });

        var $message = $('#bmu-settings-message');
        var $submitBtn = $(this).find('button[type="submit"]');

        $submitBtn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: bmuAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    $message.removeClass('error').addClass('updated');
                    $message.html('<p>' + response.data + '</p>');
                } else {
                    $message.removeClass('updated').addClass('error');
                    $message.html('<p>' + response.data + '</p>');
                }

                $message.show();
                $submitBtn.prop('disabled', false).text('Save Settings');

                setTimeout(function () {
                    $message.fadeOut();
                }, 3000);
            },
            error: function () {
                $message.removeClass('updated').addClass('error');
                $message.html('<p>Failed to save settings. Please try again.</p>');
                $message.show();
                $submitBtn.prop('disabled', false).text('Save Settings');
            }
        });
    });

    // Add exclude path
    $('#add-exclude-path').on('click', function () {
        var $container = $('#exclude-paths-container');
        var $newRow = $('<div class="exclude-path-row">' +
            '<input type="text" name="exclude_paths[]" value="" class="regular-text">' +
            '<button type="button" class="button remove-exclude-path">Remove</button>' +
            '</div>');
        $container.append($newRow);
    });

    // Remove exclude path
    $(document).on('click', '.remove-exclude-path', function () {
        $(this).closest('.exclude-path-row').remove();
    });

});
