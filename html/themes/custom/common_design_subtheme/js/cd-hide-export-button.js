(function (Drupal) {
  'use strict';

  Drupal.behaviors.hideExportButton = {
    removed: false,
    timerId: 0,
    attach: function (context, settings) {
      if (this.removed) {
        return;
      }

      var exportButtonWrapper = document.querySelector('.cd-export-button--wrapper');
      var summaryCount = document.querySelector('.source-summary-count');
      var feedButton = document.querySelector('.CSV-disaggregated-feed');
      if (feedButton) {
        feedButton.style.display = 'none';
      }

      if (exportButtonWrapper && summaryCount) {
        var count = parseInt(summaryCount.innerText.split(' ')[0], 10);
        if (count > 99) {
          document.querySelector('.CSV-disaggregated-feed').remove();
          this.removed = true;
          return;
        }

        if (this.timerId) {
          window.clearTimeout(this.timerId);
        }
        this.timerId = window.setTimeout(function () {
          document.querySelector('.CSV-disaggregated-feed').style.display = 'inherit';
        }, 500);
      }
    }
  };
})(Drupal);
