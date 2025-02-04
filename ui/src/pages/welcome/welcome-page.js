import './welcome-page.scss';

$(function() {
  // TODO: debug to delete!
  console.log('Welcome Page!');
  console.log('Is it Xibo themed: ' + isXiboThemed);

  // Create Onboarding cards
  for (let index = 0; index < onboardingCard.length; index++) {
    // Get card data
    const card = onboardingCard[index];

    // Build HTML with template
    const $newCard = $(templates.welcome.welcomeCard(card));

    // TODO: Handle click behaviour
    $newCard.on('click', function() {
      console.log('Go to serviceCard!');
    });

    // Add to container
    $newCard.appendTo('.welcome-page .onboarding-cards-container');
  }

  // TODO: Create service cards

  // TODO: Create other cards

  // TODO: Handle go to top button
});
