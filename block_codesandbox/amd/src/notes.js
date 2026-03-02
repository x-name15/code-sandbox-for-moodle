define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    return {
        init: function(params) {
            let timeout = null;
            const $textarea = $('#sandbox-notes');
            const $status = $('#notes-status');

            $textarea.on('input', function() {
                const cmid = $(this).data('cmid');
                const text = $(this).val();
                $status.stop(true, true).text(params.typing_msg).fadeIn(100);
                clearTimeout(timeout);

                timeout = setTimeout(function() {
                    Ajax.call([{
                        methodname: 'block_codesandbox_save_notes',
                        args: { cmid: cmid, note_text: text }
                    }])[0].done(function() {

                        const now = new Date();
                        const timeStr = now.toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        });
                        const savedMsg = params.saved_msg.replace('{{time}}', timeStr);
                        $status.fadeOut(150, function() {
                            $(this).text(savedMsg).fadeIn(150);
                        });
                    }).fail(function(ex) {
                        $status.text('Error al guardar');
                        Notification.exception(ex);
                    });
                }, 2000);
            });
        }
    };
});