function editAlbumManually(form, platformName) {
    const platformId = prompt(`Enter the new platform Id for ${platformName}`);
    if (platformId == null) {
        return;
    }

    var platformIdInput = form.elements['platform_id'];
    platformIdInput.value = platformId;
    form.submit();
}
