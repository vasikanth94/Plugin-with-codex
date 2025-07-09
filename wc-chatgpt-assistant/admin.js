jQuery(function($){
    $('#wc-chatgpt-send').on('click', function(){
        var message = $('#wc-chatgpt-input').val();
        if (!message) return;
        $('#wc-chatgpt-input').val('');
        appendToLog('You: ' + message);
        $.post(WCChatGPT.ajaxUrl, {
            action: 'wc_chatgpt_send_message',
            message: message,
            _ajax_nonce: WCChatGPT.nonce
        }, function(resp){
            if (resp.success) {
                appendToLog('Bot: ' + resp.data.response);
            } else {
                appendToLog('Error: ' + resp.data);
            }
        });
    });

    function appendToLog(text){
        var log = $('#wc-chatgpt-log');
        log.append('<p>' + text + '</p>');
        log.scrollTop(log[0].scrollHeight);
    }
});
