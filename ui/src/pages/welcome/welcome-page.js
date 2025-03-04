import './welcome-page.scss';

$(function () {

  for (let index = 0; index < onboardingCard.length; index++) {
    const card = onboardingCard[index];

    const $newCard = $(templates.welcome.welcomeCard(card));

    $newCard.on('click', function (e) {
      e.preventDefault();
      var targetId = $(this).attr("href");
      var targetElement = $(targetId);

      if (targetElement.length) {
        var offset = targetElement.offset().top - 100;

        $("html, body").animate({
          scrollTop: offset
        }, 800);

        targetElement.css({
          "border": "3px solid #0e70f6",
          "transition": "border-color 1s ease-out"
        });

        setTimeout(function () {
          targetElement.css("border-color", "transparent");
        }, 1000);
      }
    });

    $newCard.appendTo('.welcome-page .onboarding-cards-container');
  }

  for (let index = 0; index < serviceCards.length; index++) {
    const card = serviceCards[index];

    let targetContainer = null;

    if (card.featureFlag === "displays.view") {
      targetContainer = ".service-card-container .displays-enabled";
    } else if (Array.isArray(card.featureFlag) && card.featureFlag.includes("library.view") || card.featureFlag.includes("layout.view")) {
      targetContainer = ".service-card-container .library-layout-enabled";
    } else if (card.featureFlag === "schedule.view") {
      targetContainer = ".service-card-container .schedule-enabled";
    }

    if (targetContainer && $(targetContainer).length) {
      const $serviceCard = $(templates.welcome.serviceCard(card));
      $serviceCard.appendTo(targetContainer);
    }
  }

  for (let index = 0; index < othersCards.length; index++) {
    const card = othersCards[index];

    const $othersCard = $(templates.welcome.othersCard(card));

    $othersCard.appendTo('.welcome-page .others-card-container');
  }

  var scrollUpButton = $(".scroll-up");

  $(window).on("scroll", function () {
    if ($(window).scrollTop() > 200) {
      scrollUpButton.fadeIn();
    } else {
      scrollUpButton.fadeOut();
    }
  });

  scrollUpButton.on("click", function (e) {
    e.preventDefault();
    $("html, body").animate({ scrollTop: 0 }, "smooth");
  });

});
