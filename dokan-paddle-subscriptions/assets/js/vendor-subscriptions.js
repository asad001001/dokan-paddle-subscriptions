(function($) {

  // You must include Paddle's JS SDK somewhere in your site:
  // <script src="https://cdn.paddle.com/paddle.js"></script>
  // and initialize it here:
  // Paddle.Setup({ vendor: YOUR_VENDOR_ID, environment: 'sandbox' or 'production' });

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
      currency:'PKR',
      customer: {
        email: DPVS.currentEmail
      },
      customData: {
        user_id: DPVS.currentUserId,
        pack_id: packId,
        action: action
      },
      successCallback: function (data) {
        // For UX only. Final subscription status is updated via webhooks.
        // You can show a message or reload:
        window.location.href = window.location.href;
      }
    });
  });

})(jQuery);
