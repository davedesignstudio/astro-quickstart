(function ($) {
  function syncType() {
    var type = $('input[name="rkp_order_type"]:checked').val() || "pickup";
    $("#rkp-order-type-fields").attr("data-type", type);
  }

  $(document.body).on("change", 'input[name="rkp_order_type"]', syncType);
  $(syncType);
})(jQuery);
