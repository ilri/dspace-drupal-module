(function ($, Drupal) {

  Drupal.behaviors.checkall_widget = {
    attach: function (context) {
      $('.checkall-widget-btn').on('click', function (e) {
        e.preventDefault();
        $(this).parents('.fieldset__wrapper').find('input').prop('checked', typeof $(this).data('checkAll') !== 'undefined');
      });
    }
  };

})(jQuery, Drupal);
