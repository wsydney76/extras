var $btn = $('#map-btn');

$btn.on('click', function() {

    if(ajaxBaseUrl.indexOf('?') === -1) {
        url = ajaxBaseUrl + '?draftId=' + draftId;
    } else {
        url = ajaxBaseUrl + '&draftId=' + draftId;
    }


    $.get(url)
        .done(function(data) {
            hud = new new Garnish.HUD($btn, data, {
                orientations: ['top', 'bottom', 'right', 'left'],
                hudClass: 'hud guide-hud',
            });
        })
        .fail(function() {
            alert("error");
        });
});

if (window.draftEditor) {
    window.draftEditor.on('createDraft', function() {
        draftId = window.draftEditor.settings.draftId;
    });
}
