let REBOOT_FUNCTION = null;
let OPEN_REPLY = null;

$(function(){
  'use strict'

  function mailbox_start() {
    new PerfectScrollbar('#mailGroup', {
      suppressScrollX: true
    });

    const mc = new PerfectScrollbar('#mailContent', {
      suppressScrollX: true
    });

    $('[data-bs-toggle="tooltip"]').tooltip();

    $('.sidebar .nav-link').on('click', function(e){
      e.preventDefault();
      $(this).addClass('active');
      $(this).siblings().removeClass('active');

      $(this).closest('.nav').siblings('.nav').find('.nav-link').removeClass('active');
    });

    $('.mailbox-menu').on('click', function(e){
      e.preventDefault();

      if (window.matchMedia('(max-width: 767px)').matches) {
        $('body').toggleClass('sidebar-show');
      } else {
        $('body').toggleClass('sidebar-hide');
      }

      mc.update();
    });

    $('.backdrop').on('click', function(e){
      $('body').removeClass('sidebar-show');
    });

    $('.mailbox-search .form-control').on('focusin focusout', function(e){
      if(e.type === 'focusin') {
        $(this).parent().addClass('onfocus');
      } else {
        $(this).parent().removeClass('onfocus');
      }
    });

    $('.mailbox-select').on('mouseenter mouseleave', '.dropdown-check, .dropdown-link', function(e){
      if(e.type === 'mouseenter') {
        $(this).parent().addClass('onhover');
      } else {
        $(this).parent().removeClass('onhover');
      }
    });

    $('.dropdown-check').on('click', function(e){
      e.preventDefault();
      $(this).toggleClass('checkall');

      $('#mailGroup .mail-item').toggleClass('selected');

      var m = $(this).hasClass('checkall')? '.all' : '.none';
      $('.mailbox-select '+m).addClass('active').siblings().removeClass('active');

    });

    $('.mailbox-select .dropdown-item').on('click', function(e){
      e.preventDefault();
      $(this).addClass('active').siblings().removeClass('active');

      if($(this).hasClass('all')) {
        $('#mailGroup .mail-item').addClass('selected');
        $('.dropdown-check').addClass('checkall');
      }

      if($(this).hasClass('none')) {
        $('#mailGroup .mail-item').removeClass('selected');
        $('.dropdown-check').removeClass('checkall');
      }

      if($(this).hasClass('read')) {
        $('#mailGroup .mail-item').removeClass('selected');
        $('#mailGroup .mail-item:not(.unread)').addClass('selected');
      }

      if($(this).hasClass('unread')) {
        $('#mailGroup .mail-item').removeClass('selected');
        $('#mailGroup .mail-item.unread').addClass('selected');
      }

      if($(this).hasClass('starred')) {
        $('#mailGroup .mail-item').removeClass('selected');
        $('#mailGroup .mail-star.active').each(function(){
          $(this).closest('.mail-item').addClass('selected');
        });
      }
    });

    $('.mail-item').on('click', function(e){
      e.preventDefault();
      callMailContentLoader(e.target, $(this),rebootListens);
    });

    $('.mail-star').on('click', function(e){
      updateFlag(e.target);
      $(this).toggleClass('active');
    });

    $('.menu-compose').on('click', function(e){
      e.preventDefault();
      $(this).addClass('d-none');
      $('.compose').removeClass('d-none');
    });

    $('.compose-title, .nav-link-minimize').on('click', function(e){
      $(this).closest('.compose').toggleClass('minimize');
    });

    $('.nav-link-fullscreen').on('click', function(e){
      e.preventDefault();
      $(this).closest('.compose').toggleClass('fullscreen');
    });

    $('.nav-link-close').on('click', function(e){
      e.preventDefault();
      $(this).closest('.compose').addClass('d-none').removeClass('minimize fullscreen');
      $('.menu-compose').removeClass('d-none');
    });

    $('#mailBack').on('click touch', function(e){
      e.preventDefault();

      $('body').removeClass('mailcontent-show');
    });



  }

  function openReplyBox(e, obj) {
    const message_id = e.getAttribute('data-message-id');
    const msgno = e.getAttribute('data-msgno');
    const subject_title = e.getAttribute('data-subject');
    const to_mail = e.getAttribute('data-to-mail');
    const box_name = e.getAttribute('data-to-box');
    $('.reply-compose').removeClass('d-none');

    $('.reply-compose .compose-title, .nav-link-minimize').on('click', function(e){
      $(this).closest('.reply-compose').toggleClass('minimize');
    });

    $('.reply-compose .nav-link-fullscreen').on('click', function(e){
      e.preventDefault();
      $(this).closest('.reply-compose').toggleClass('fullscreen');
    });

    $('.reply-compose .nav-link-close').on('click', function(e){
      e.preventDefault();
      $(this).closest('.reply-compose').addClass('d-none').removeClass('minimize fullscreen');
    });
    reply_composer(message_id, msgno,subject_title, to_mail, box_name);
  }

  function rebootListens(emitter) {

    emitter.addClass('active').siblings().removeClass('active');
    emitter.removeClass('unread');

    $('.mailcontent-placeholder').siblings().removeClass('d-none');
    $('.mailcontent-placeholder').addClass('d-none');

    if (window.matchMedia('(max-width: 1199px)').matches) {
      $('body').addClass('mailcontent-show');
    }
    handleTopAction();
    // Mail Content
    $('.mailcontent-header').on('click', function(e){
      const i = e.target.tagName.toLowerCase();
      const only = ['i', 'a'];
      if(only.includes(i)) {
        singleAddListener(e.target, $(this));
      }else {
        $(this).siblings('.mailcontent-body').toggleClass('d-none');
        mc.update();
      }
    });
    $('#mailBack').on('click touch', function(e){
      e.preventDefault();

      $('body').removeClass('mailcontent-show');
    });
  }

  mailbox_start();

  REBOOT_FUNCTION = function reboot_init() {
    mailbox_start();
  }

  OPEN_REPLY = openReplyBox;
})
