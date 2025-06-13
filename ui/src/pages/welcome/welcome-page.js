import './welcome-page.scss';

function showVideoModal(videoLinks) {
  const multipleVideo = (videoLinks.length > 1);

  const showVideo = function($modal, index) {
    const $videoModalContent = $(templates.welcome.videoModalContent({
      videoIndex: index,
      videoLink: videoLinks[index].link,
      showControls: multipleVideo,
      numVideoLinks: videoLinks.length,
      videoThumbnails: videoLinks.map((_el, idx) => {
        _el.selected = (idx == index);
        return _el;
      }),
      buttonDisabled: {
        previous: (index === 0),
        next: (index === (videoLinks.length - 1)),
      },
    }));

    // Append to modal body
    $modal.find('.welcome-video-body').html($videoModalContent);

    // Handle controls
    if (multipleVideo) {
      $videoModalContent.find('.welcome-video-thumb:not(.checked)').on(
        'click',
        function(ev) {
          const $btn = $(ev.currentTarget);
          showVideo($modal, $btn.data('idx'));
        });
    }
  };

  const removeModal = function($modal) {
    $modal.modal('hide');
    // Remove modal
    $modal.remove();

    // Remove backdrop
    $('.modal-backdrop.show').remove();
  };

  // Create modal
  const $videoModal = $(templates.welcome.videoModal());

  // Add modal to the DOM
  $('body').append($videoModal);

  // Show first video
  showVideo($videoModal, 0);

  // Show modal
  $videoModal.modal('show');

  // Close button
  $videoModal.find('button.modal-close').on(
    'click',
    function() {
      removeModal($videoModal);
    });
}

$(function() {
  // Onboarding cards
  for (let index = 0; index < onboardingCard.length; index++) {
    const card = onboardingCard[index];

    const $newCard = $(templates.welcome.welcomeCard(card));

    $newCard.on('click', function(e) {
      e.preventDefault();
      const targetId = $(e.currentTarget).attr('href');
      const $targetElement = $(targetId);

      if ($targetElement.length) {
        const offset = $targetElement.offset().top - 100;

        $('html, body').animate({
          scrollTop: offset,
        }, 800);

        $targetElement.css({
          border: '3px solid #0e70f6',
          transition: 'border-color 1s ease-out',
        });

        $targetElement.addClass('highlighted');

        setTimeout(function() {
          $targetElement.css('border-color', 'transparent');
          $targetElement.removeClass('highlighted');
        }, 1000);
      }
    });

    $newCard.appendTo('.welcome-page .onboarding-cards-container');
  }

  // Service cards
  for (let index = 0; index < serviceCards.length; index++) {
    const card = serviceCards[index];

    let targetContainer = null;

    if (card.featureFlag === 'displays.view') {
      targetContainer = '.service-card-container .displays-enabled';
    } else if (
      Array.isArray(card.featureFlag) &&
      card.featureFlag.includes('library.view') ||
      card.featureFlag.includes('layout.view')
    ) {
      targetContainer = '.service-card-container .library-layout-enabled';
    } else if (card.featureFlag === 'schedule.view') {
      targetContainer = '.service-card-container .schedule-enabled';
    }

    if (targetContainer && $(targetContainer).length) {
      const $serviceCard = $(templates.welcome.serviceCard(card));
      $serviceCard.appendTo(targetContainer);

      // Card video link
      // don't show for white label
      if (card.videoLinks && isXiboThemed) {
        const $videoOverlay =
          $serviceCard.find('.service-card-image-video-overlay');
        const videoLinks = Array.isArray(card.videoLinks) ?
          card.videoLinks : [card.videoLinks];

        // Only add if we have links
        if (videoLinks.length > 0) {
          // Show and handle overlay click
          $videoOverlay.addClass('active').on('click', function(e) {
            console.log('Open video: ' + videoLinks.length);
            console.log(videoLinks);
            showVideoModal(videoLinks);
          });
        }
      }
    }
  }

  // Other cards
  const $otherCardContainer = $('.welcome-page .others-card-container');
  $otherCardContainer.toggleClass('multi-card', (othersCards.length > 1));
  for (let index = 0; index < othersCards.length; index++) {
    const card = othersCards[index];
    const $othersCard = $(templates.welcome.othersCard(card));

    $othersCard.appendTo($otherCardContainer);
  }

  // Scroll up button
  const scrollUpButton = $('.scroll-up');

  $(window).on('scroll', function() {
    if ($(window).scrollTop() > 200) {
      scrollUpButton.fadeIn();
    } else {
      scrollUpButton.fadeOut();
    }
  });

  scrollUpButton.on('click', function(e) {
    e.preventDefault();
    $('html, body').animate({scrollTop: 0}, 'smooth');
  });
});
