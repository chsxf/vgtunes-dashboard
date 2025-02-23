function setupActionSelector() {
    const actionList = document.getElementById('action');
    actionList.addEventListener('change', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const targetUrl = `${document.location.origin}${document.location.pathname}?action=${e.target.value}`;
        document.location.href = targetUrl;
    });
}

(function() {
    setupActionSelector();
})();