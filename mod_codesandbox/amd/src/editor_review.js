/* global monaco */
define(['jquery'], function($) {
    return {
        init: function(params) {
            var container = document.getElementById(params.containerId);
            var script = document.createElement('script');
            script.src = params.monacoloader;
            script.type = 'text/javascript';
            script.async = true;

            script.onload = function() {
                require.config({paths: {'vs': params.monacobase}});
                require(['vs/editor/editor.main'], function() {
                    $(container).empty();
                    monaco.editor.create(container, {
                        value: params.code,
                        language: params.language,
                        theme: 'vs-dark',
                        readOnly: true,
                        automaticLayout: true,
                        minimap: {enabled: true}
                    });
                });
            };
            document.body.appendChild(script);
        }
    };
});