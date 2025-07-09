/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

$(function() {
  const $help = $('#help-pane');
  const $helpButton = $help.find('.help-pane-btn');
  const $helpContainer = $help.find('.help-pane-container');
  let fileStore = [];

  // 0: Disabled, 1: Main, 2: Feedback form, 3: Feedback outro
  let helperStep = 0;

  const hideHelper = function() {
    $helpContainer.hide();
    $('.help-pane-overlay').remove();
  };

  const renderPanelContent = function() {
    if (helperStep === 2) {
      // Feedback form
      $helpContainer.html(
        templates.help.feedbackForm({
          trans: translations.helpPane,
          pageURL: window.location.pathname,
          accountId: accountId,
          currentUserName,
          currentUserEmail,
        }),
      );

      handleFileUpload();
    } else {
      // Main or end panel
      const template = (helperStep === 3) ?
        templates.help.endPanel :
        templates.help.mainPanel;

      $helpContainer.html(
        template(
          {
            trans: translations.helpPane,
            helpLinks: $('#help-pane').data('helpLinks'),
            helpLandingPageURL: $('#help-pane').data('urlHelpLandingPage'),
            isXiboThemed,
            welcomeViewURL,
            supportURL,
            appName,
          }),
      );
    }

    handleControls();
  };

  const handleControls = function() {
    // Close button
    $help.find('.close-icon').on('click', hideHelper);

    // Back button
    $help.find('.back-icon')
      .on('click', (ev) => {
        ev.preventDefault();
        // Move to previous screen
        helperStep--;
        renderPanelContent();
      });

    // Feedback card button
    $help.find('.help-pane-card[data-action="feedback_form"]')
      .on('click', (ev) => {
        ev.preventDefault();
        helperStep = 2;
        renderPanelContent();
      });

    // Submit form
    $help.find('.submit-form-btn')
      .on('click', (ev) => {
        ev.preventDefault();

        const $form = $help.find('form');
        const formData = new FormData($form[0]);

        const validateEmail = function(email) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        };

        const showErrorOnInput = function($input, msg) {
          $input.parent('.xibo-form-input').addClass('invalid');
          $input.after($(`<div class="error-message">${msg}</div>`));
        };

        // Remove invalid class from fields
        $form.find('.xibo-form-input.invalid')
          .removeClass('invalid');
        $form.find('.error-message').remove();
        $form.find('.feedback-form-error').addClass('d-none');

        // Validate fields
        let isValid = true;

        // User name
        const $userName = $('[name=userName]');
        if (!$userName.val().trim()) {
          isValid = false;
          showErrorOnInput($userName, translations.helpPane.form.errors.name);
        }

        // Email
        const $email = $('[name=email]');
        const emailVal = $email.val().trim();
        if (!emailVal || !validateEmail(emailVal)) {
          isValid = false;
          showErrorOnInput($email, translations.helpPane.form.errors.email);
        }

        // Message
        const $message = $('[name=message]');
        if (!$message.val().trim()) {
          isValid = false;
          showErrorOnInput(
            $message,
            translations.helpPane.form.errors.comments,
          );
        }

        // If any fields are invalid, show form error message
        if (!isValid) {
          $form.find('.feedback-form-error span')
            .html(translations.helpPane.form.errors.form);
          $form.find('.feedback-form-error')
            .removeClass('d-none');
          return;
        }

        // Generate 32 char string as id
        const rndString =
          [...Array(32)].map(
            () => (Math.random() * 36 | 0).toString(36),
          ).join('');
        formData.append('id', rndString);

        fileStore.forEach((file) => {
          formData.append('files[]', file);
        });

        // Submit form
        const requestOptions = {
          method: $form.data('method'),
          body: formData,
        };
        fetch($form.data('action'), requestOptions)
          .then((res) => {
            if (!res.ok) {
              throw res;
            }

            if (res.status === 204) {
              // Nothing more to do
              return;
            }

            return response.json();
          })
          .then((_res) => {
            // Sucess, go to final screen
            helperStep = 3;
            renderPanelContent();
          })
          .catch(async (error) => {
            let message = translations.helpPane.form.errors.request;
            try {
              const data = await error.json();
              message = data.message || message;
            } catch {
              try {
                const text = await error.text();
                if (text) {
                  message = text;
                }
              } catch {}
            }

            $form.find('.feedback-form-error span').html(message);
            $form.find('.feedback-form-error').removeClass('d-none');
          });
      });
  };

  const handleFileUpload = function() {
    // Attachments
    const $uploadMain = $help.find('.file-uploader-attachments');
    const $uploadsArea = $help.find('.uploads-area');
    const $uploadsDrop = $help.find('.uploads-drop');
    const $browseLink = $help.find('.upload-text-browse');
    const $fileInput = $help.find('#feedback_form_attachments');
    const $uploadedFiles = $help.find('.help-pane-upload-files');
    const maxFiles = 3;
    const maxFileSize = 15 * 1024 * 1024;
    const allowedTypes = [
      'image/jpeg',
      'image/png',
      'application/pdf',
      'video/quicktime',
    ];

    // Show error message
    const showFileErrorMessage = function() {
      $uploadMain.append(templates.help.components.errorMessage({
        trans: translations.helpPane,
      }));

      $uploadsArea.hide();
    };

    const removeFileErrorMessage = function() {
      $uploadMain.find('.max-uploads-message').remove();
      $uploadsArea.show();
    };

    // Add uploaded file to form
    const addFileCard = function(file) {
      $uploadedFiles.append(templates.help.components.uploadCard({
        name: file.name,
        type: file.fileTypeName,
        thumbURL: file.thumbURL,
        icon: file.fileIcon,
      }));


      // Update file container if we reach max files
      if ($uploadedFiles.find('.help-pane-upload-file').length >= maxFiles) {
        showFileErrorMessage();
      }

      // Show file container
      $uploadedFiles.removeClass('d-none');
    };

    const handleFilesDrop = function(files) {
      if (!files.length) {
        return;
      }

      const currentUploads =
        $uploadedFiles.find('.help-pane-upload-file').length;

      if (currentUploads + files.length > maxFiles) {
        alert('Maximum file number exceeded');
        return;
      }

      for (let i = 0; i < files.length; i++) {
        const file = files[i];

        if (!allowedTypes.includes(file.type)) {
          alert(`${file.name}: Invalid file type`);
          continue;
        }

        if (file.size > maxFileSize) {
          alert(`${file.name}: File too large`);
          continue;
        }

        // Prevent duplicates
        if (fileStore.find(
          (f) => (f.name === file.name && f.size === file.size))
        ) {
          continue;
        }

        // Add to file store
        fileStore.push(file);

        // Render files
        if (file.type.startsWith('image/')) {
          // Get thumb for image
          const reader = new FileReader();
          reader.onload = function(e) {
            const thumbURL = e.target.result;
            addFileCard({
              name: file.name,
              type: file.type,
              thumbURL: thumbURL,
              fileTypeName: translations.helpPane.form.image,
            });
          };
          reader.readAsDataURL(file);
        } else {
          // Get icons for others
          if (file.type === 'application/pdf') {
            file.fileIcon = 'fa-file-pdf';
            file.fileTypeName = translations.helpPane.form.pdf;
          } else if (file.type.startsWith('video/')) {
            file.fileIcon = 'fa-file-video';
            file.fileTypeName = translations.helpPane.form.video;
          }

          addFileCard(file);
        }
      }

      // Clear file input, we handle on submit
      $fileInput.val('');
    };

    // Browse link
    $browseLink.on('click', function(e) {
      e.preventDefault();
      $fileInput.trigger('click');
    });

    // Drag and drop
    let dragCounter = 0;
    $uploadsDrop.on('dragover', function(e) {
      e.preventDefault();
    }).on('dragenter', function(e) {
      e.preventDefault();
      dragCounter++;
      $uploadsDrop.addClass('highlight');
    }).on('dragleave', function(e) {
      e.preventDefault();
      dragCounter--;

      if (dragCounter <= 0) {
        dragCounter = 0;
        $uploadsDrop.removeClass('highlight');
      }
    }).on('dragend', function() {
      dragCounter = 0;
      $uploadsDrop.removeClass('highlight');
    }).on('drop', function(e) {
      e.preventDefault();
      dragCounter = 0;
      $uploadsDrop.removeClass('highlight');
      handleFilesDrop(e.originalEvent.dataTransfer.files);
    });

    // File input
    $fileInput.on('change', function(e) {
      handleFilesDrop(e.target.files);
    });

    $uploadedFiles.on('click', '.remove-file-icon', function(ev) {
      const $file = $(ev.currentTarget).closest('.help-pane-upload-file');
      const filename = $file.find('.help-pane-upload-file-name').text();

      // Remove from file store
      fileStore = fileStore.filter((f) => f.name !== filename);

      // Remove from container
      $file.remove();

      const messageLength =
        $uploadedFiles.find('.help-pane-upload-file').length;
      // Remove error message if num files isn't max
      if (messageLength < maxFiles) {
        removeFileErrorMessage();
      }

      // Hide file container if it was the last removed message
      if (messageLength === 0) {
        $uploadedFiles.addClass('d-none');
      }
    });
  };

  // Help main button
  $helpButton.on('click', () => {
    if ($helpContainer.is(':visible')) {
      hideHelper();
    } else {
      $helpContainer.show();
      helperStep = 1;

      // Render main panel
      renderPanelContent();

      $('<div class="help-pane-overlay"></div>')
        .appendTo('body')
        .on('click', () => {
          if (helperStep === 1 || helperStep === 3) {
            hideHelper();
          }
        });
    }
  });
});
