(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.quizCountdown = {
    attach: function (context) {
      const countdowns = drupalSettings.jquery_countdown_quiz;
      if (countdowns.length > 0 ) {
        countdowns.forEach(countdown => {
          const dt = new Date();
          dt.setSeconds(dt.getSeconds() + countdown.since);
          $(once('countdown', '.' + countdown.id, context)).countdown(dt)
            .on('update.countdown', function (event) {
              $(this).html(event.strftime(countdown.format));
            })
            .on('finish.countdown', function () {
              Drupal.behaviors.quizCountdown.quizFinished();
            });
        });
      }
    },
    quizFinished: function () {
      let skip_button = $('#edit-navigation-skip');
      if (skip_button.length > 0) {
        skip_button.click();
      }
      else {
        $('#edit-navigation-timer-expired-finish').click();
      }
    }
  };
})(jQuery, Drupal, drupalSettings, once);
