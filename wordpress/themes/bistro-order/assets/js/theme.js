document.addEventListener("DOMContentLoaded", function () {
  var cards = document.querySelectorAll("ul.products li.product");
  cards.forEach(function (card, index) {
    card.style.animationDelay = Math.min(index * 0.05, 0.4) + "s";
  });
});
