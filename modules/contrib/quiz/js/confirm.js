(function ($, Drupal, once) {
  Drupal.behaviors.quizAnswerConfirm = {
    attach: function (context, settings) {
      $(once('quizConfirm', '#quiz-question-answering-form #edit-navigation-submit, #quiz-question-answering-form #edit-navigation-skip', context)).click(function (event) {
        // Return false to avoid submitting if user aborts
        if (!confirm($('#quiz-question-answering-form').data('confirm-message'))) {
          event.preventDefault();
        }
      });
    }
  };
})(jQuery, Drupal, once);

