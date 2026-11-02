(function($) {

  // Paddle.js v2 is loaded and initialised in templates/vendor-subscription-status.php.

  $(document).on('click', '.dpvs-pack-button', function(e) {
    e.preventDefault();

    var $btn      = $(this);
    var priceId   = $btn.data('price-id');
    var packId    = $btn.data('pack-id');
    var action    = $btn.data('action') || 'buy';

    if ( typeof Paddle === 'undefined' ) {
      alert('Payment system not ready. Please contact support.');
      return;
    }

    Paddle.Checkout.open({
      // Adjust to Paddle's expected checkout options
      items: [{
        price_id: priceId,
        quantity: 1
      }],
      customer: {
        email: DPVS.currentEmail
      },
      customData: {
        user_id: DPVS.currentUserId,
        pack_id: packId,
        action: action
      },
      successCallback: function () {
        // UX only. The real subscription state is set by the webhook.
        window.location.reload();
      }
    });
  });

})(jQuery);
