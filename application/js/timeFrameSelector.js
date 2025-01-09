function setupTimeFrameSelector() {
  document.getElementById("days").addEventListener("change", function (e) {
    e.preventDefault();
    e.stopPropagation();
    this.form.submit();
  });
}

(function () {
  setupTimeFrameSelector();
})();
