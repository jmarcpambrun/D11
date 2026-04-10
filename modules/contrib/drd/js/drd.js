(function ($, Drupal, drupalSettings, DrupalDRD) {
  DrupalDRD = DrupalDRD || {};

  Drupal.behaviors.drd = {
    attach() {
      const $toggleDomainName = $(
        '<div class="toggle-domain-name">-</div>',
      ).click(function () {
        $('.drd-domain-name').toggleClass('show-domain');
      });
      $(
        once(
          'toggleDomainname',
          '.drd-view .view-filters table thead th.views-field-name-2',
        ),
      ).append($toggleDomainName);

      const $toggleFilter = $(
        `<div class="toggle-filter">${Drupal.t('Filter')}</div>`,
      ).click(function () {
        $(this).parent().toggleClass('visible');
      });
      $(once('drd-view-filters', '.drd-view .view-filters')).prepend(
        $toggleFilter,
      );

      $(
        once(
          'drd-action-form-elements',
          'body.drd .views-form form #edit-header,' +
            'body.drd .views-form form #edit-actions,' +
            'body.drd .views-form form #edit-actions--1,' +
            'body.drd .views-form form #edit-actions--2,' +
            'body.drd .views-form form #edit-actions--3',
        ),
      ).addClass('drd-action-form-elements');
      $(
        once(
          'drd-action-form-selected',
          'body.drd .views-form form .form-checkbox',
        ),
      ).change(function () {
        if (this.checked || $('.drd-view form tbody tr.selected').length > 0) {
          $('.drd-action-form-elements').slideDown('fast');
        } else {
          $('.drd-action-form-elements').slideUp('fast');
        }
      });

      $(
        once(
          'drd-project',
          'body.drd.drd-project tbody .views-field-domain, body.drd.drd-project tbody .views-field-version',
        ),
      ).each(function () {
        const content = this.innerHTML;
        const count = (content.match(/<br>/g) || []).length + 1;
        this.innerHTML = `<div class="count">${count}<div class="list">${content}</div></div>`;
        $(this).addClass('visible');
      });

      DrupalDRD.domainNameHandler();
    },
  };

  DrupalDRD.domainNameHandler = function () {
    $(
      once('drd-domain-token-widget', '.drd-domain-name div.token-widget'),
    ).click(function () {
      const temp = $('<input>');
      $('body').append(temp);
      temp.val($(this).attr('token')).select();
      document.execCommand('copy');
      temp.remove();
    });
  };
})(jQuery, Drupal, drupalSettings);
