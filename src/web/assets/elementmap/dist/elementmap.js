var $btn = $('#map-btn');

$btn.on('click', function() {

    url = elementMapAjaxBaseUrl.replace('%s', elementMapElementId);

    $.get(url)
        .done(function(data) {
            hud = new Garnish.HUD($btn, data, {
                orientations: ['top', 'bottom', 'right', 'left'],
                hudClass: 'hud guide-hud',
            });
        })
        .fail(function() {
            alert("Error");
        });
});

setTimeout(() => {
    Craft.cp.$primaryForm.data('elementEditor').on('createProvisionalDraft', function() {
        elementMapElementId = Craft.cp.$primaryForm.data('elementEditor').settings.elementId;
    });
}, 500)

