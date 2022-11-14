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

      if (exportButtonWrapper && summaryCount) {
        var count = parseInt(summaryCount.innerText.split(' ')[0], 10);
        if (count == 0 || count > 9999) {
          exportButtonWrapper.remove();
          this.removed = true;
          return;
        }

        if (this.timerId) {
          window.clearTimeout(this.timerId);
        }
        this.timerId = window.setTimeout(function () {
          document.querySelector('.cd-export-button--wrapper').style.display = 'auto';
        }, 500);
      }
    }
  };
})(Drupal);
