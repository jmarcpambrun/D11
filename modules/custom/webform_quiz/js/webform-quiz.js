(function ($) {
    Drupal.behaviors.myModuleBehavior = {
      attach: function (context, settings) {
          var sent = [];
        $(context).find('[quiz-background]').once('processed').each(function () {
            console.log(this, $(this).attr('quiz-background'));
            var bg = $('<div style="position: absolute; width: 100%; height: 100%; z-index: -1"></div>')
            $(bg).css('background-image', 'url("' + $(this).attr('quiz-background') + '")' ).
            css("background-repeat", "no-repeat").
            css("background-size", "cover").
            css("background-position", "center").
            css("opacity", "0.5");

            $(this).prepend(bg);
        });
        $(context).find('.webform-submission-form').once('processed').on('submit', function (event) {
            var count = 0;
            $('.webform-submission-form').find('[quiz-has-cad]').once('answer-show-sent').each(function () {
                var target = this;
                while($(target).parents('[quiz-has-cad]').length !== 0) {
                    target = $(target).parents('[quiz-has-cad]')[0];
                }
                if($(this).find('[quiz-has-cad]').length !== 0) {
                    return;
                }
                if(sent.includes(target)) {
                    return;
                }
                var event = new CustomEvent("show_answer", {
                    detail: {
                      hazcheeseburger: TRUE
                    }
                  });
                  this.dispatchEvent(event);
                  count++;
                  sent.push(target);

            });
            if(count) {
                $('.webform-submission-form').find('*').each(function () {
                    //console.log('cmp', $(this).parent(), button.parent(), $(this).parent() !== button.parent());
                    if( ($(this).attr('data-drupal-selector') !== 'edit-wizard-next') &&
                    ($(this).attr('data-drupal-selector') !== 'edit-submit') &&
                    ($(this).attr('data-drupal-selector') !== 'edit-actions')
                    ) {
                        $(this).on('keydown mousedown click', function (event) {
                            event.preventDefault();
                            event.stopPropagation();
                        });
                        event.preventDefault();
                        event.stopPropagation();
                    }
                });
            }
        });
      }
    };
  })(jQuery);
