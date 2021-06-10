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
          e.preventDefault();
          alert('Please save the document(s) first.');
          return false;
        }
      });
    }
  }
})();

