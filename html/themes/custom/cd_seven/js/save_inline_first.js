/**
 * @file
 * Force to save inline forms first.
 */

 (function () {
  'use strict';

  var isAssessmentForm = document.querySelector('form.assessment-form');
  if (isAssessmentForm) {
    var saveButton = document.getElementById('edit-submit');
    if (saveButton) {
      saveButton.addEventListener('click', function (e) {
        if (document.querySelectorAll('[name^="ief-add-submit"]').length > 0) {
          var buttons = document.querySelectorAll('[name^="ief-add-submit"]');
          var message = 'Please save the following first: ';
          for (var i = 0; i < buttons.length; i++) {
            buttons[i].style.backgroundColor = 'green';

            // Find parent.
            var parent = buttons[i];
            while ((parent = parent.parentElement) && !parent.classList.contains('field--widget-inline-entity-form-complex'));

            if (!parent) {
              continue;
            }

            var legend = parent.querySelector('.fieldset-legend').innerText;
            if (i > 0) {
              message = message + ', ';
            }
            message = message + legend;
          }

          e.preventDefault();
          alert(message);
          return false;
        }
      });
    }
  }
})();

