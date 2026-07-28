(function ($) {
  $(document).on("click", "#rkp-reprint", function () {
    var $btn = $(this);
    var orderId = $btn.data("order");
    $btn.prop("disabled", true).text("Queuing…");
    $.post(rkpAdmin.ajaxUrl, {
      action: "rkp_reprint_ticket",
      nonce: rkpAdmin.nonce,
      order_id: orderId,
    })
      .done(function (res) {
        alert((res && res.data && res.data.message) || "Queued");
        window.location.reload();
      })
      .fail(function () {
        alert("Could not re-queue ticket");
        $btn.prop("disabled", false).text("Re-queue print");
      });
  });
})(jQuery);
