// https://github.com/craftcms/cms/pull/8017
function compare(draftId, siteId, isProvisionalDraft) {

    if (draftId == -1)
        draftId = newDraftId

    if (compareUrl.indexOf('?') > -1) {
        compareUrl = compareUrl + '&'
    } else {
        compareUrl = compareUrl + '?'
    }

    url = `${compareUrl}draftId=${draftId}&siteId=${siteId}&isProvisionalDraft=${isProvisionalDraft}`
    $.get(url, function(data) {
            var slideout = new Craft.Slideout(data, {
                containerAttributes: {class: 'compare-container'}
            });
        })
        .fail(function() {
            alert("Error");
        })
}

setTimeout(() => {
    Craft.cp.$primaryForm.data('elementEditor').on('createProvisionalDraft', function() {
        newDraftId = Craft.cp.$primaryForm.data('elementEditor').settings.draftId
        $('#draft-new').css('display', '')
    });
}, 500)
