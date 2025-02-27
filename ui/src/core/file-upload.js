/*
 * Copyright (C) 2024 Xibo Signage Ltd
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
let uploadTemplate = null;
let videoImageCovers = {};

/**
 * Opens the upload form
 * @param options
 */
window.openUploadForm = function(options) {
  options = $.extend(true, {}, {
    templateId: 'template-file-upload',
    uploadTemplateId: 'template-upload',
    downloadTemplateId: 'template-download',
    videoImageCovers: true,
    className: '',
    animateDialog: true,
    formOpenedEvent: null,
    onHideCallback: null,
    templateOptions: {
      layoutImport: false,
      multi: true,
      includeTagsInput: true,
    },
  }, options);

  // Keep a cache of the upload template (unless we are a non-standard form)
  if (
    uploadTemplate === null ||
    options.templateId !== 'template-file-upload'
  ) {
    uploadTemplate = Handlebars.compile($('#' + options.templateId).html());
  }

  if (typeof maxImagePixelSize === undefined || maxImagePixelSize === '') {
    maxImagePixelSize = 0;
  }

  // Handle bars and open a dialog
  const dialog = bootbox.dialog({
    message: uploadTemplate(options.templateOptions),
    title: options.title,
    buttons: options.buttons,
    className: options.className + ' upload-modal',
    animate: options.animateDialog,
    size: 'large',
  }).on('hidden.bs.modal', function() {
    // Reset video image covers.
    videoImageCovers = {};

    // Call the onHideCallback if it exists
    if (options.onHideCallback !== null) {
      options.onHideCallback(dialog);
    }
  }).attr('id', Date.now());

  setTimeout(function() {
    console.debug('Timeout fired, we should be shown by now');

    // Configure the upload form
    const form = $(dialog).find('form');
    let uploadOptions = {
      url: options.url,
      disableImageResize: true,
      previewMaxWidth: 100,
      previewMaxHeight: 100,
      previewCrop: true,
      acceptFileTypes: new RegExp(
        '\\.(' + options.templateOptions.upload.validExt + ')$',
        'i',
      ),
      maxFileSize: options.templateOptions.upload.maxSize,
      includeTagsInput: options.templateOptions.includeTagsInput,
      uploadTemplateId: options.uploadTemplateId,
      limitConcurrentUploads: 3,
    };
    let refreshSessionInterval;

    $(form).on('keydown', function(event) {
      if (event.keyCode == 13) {
        event.preventDefault();
        return false;
      }
    });

    // Video thumbnail capture.
    if (options.videoImageCovers) {
      $(dialog).find('#files').on('change', handleVideoCoverImage);
    }

    if (maxImagePixelSize > 0) {
      $(dialog).find('#files').on('change', checkImagePixelSize);
    }

    // If we are not a multi-upload, then limit to 1
    if (!options.templateOptions.multi) {
      uploadOptions = $.extend({}, uploadOptions, {
        maxNumberOfFiles: 1,
        limitMultiFileUploads: 1,
      });
    }

    // Widget dates?
    if (options.templateOptions.showWidgetDates) {
      XiboInitialise('.row-widget-dates');
    }

    // Handle expiry dates fields
    const expiryDatesStatus = function() {
      const setExpiryFlag = form.find('#setExpiryDates').is(':checked');

      // Hide and disable fiels ( to avoid form submitting)
      form.find('.row-widget-set-expiry').toggleClass('hidden', !setExpiryFlag);
      form.find('.row-widget-set-expiry input')
        .prop('disabled', !setExpiryFlag);
    };

    // Call when checkbox changes
    form.find('#setExpiryDates').on('change', expiryDatesStatus);

    // Call on start
    expiryDatesStatus();

    // Ready to initialise the widget and bind to some events
    form
      .fileupload(uploadOptions)
      .bind('fileuploadsubmit', function(e, data) {
        const inputs = data.context.find(':input');
        if (inputs.filter('[required][value=""]').first().focus().length) {
          return false;
        }
        data.formData = inputs.serializeArray().concat(form.serializeArray());

        inputs.filter('input').prop('disabled', true);
      })
      .bind('fileuploadstart', function(e, data) {
        // Show progress data
        form.find('.fileupload-progress .progress-extended').show();
        form.find('.fileupload-progress .progress-end').hide();

        if (form.fileupload('active') <= 0) {
          refreshSessionInterval =
            setInterval(
              'XiboPing(\'' + pingUrl + '?refreshSession=true\')',
              1000 * 60 * 3,
            );
        }
        return true;
      })
      .bind('fileuploaddone', function(e, data) {
        // if we throw an error in the backend, the
        // data.result.files is undefined, check if we have a message
        if (
          data.result.files === undefined &&
          data.result.message !== undefined &&
          data.result.message != null
        ) {
          toastr.error(data.result.message);
          return;
        }

        // If the upload was an error, then don't process the remaining methods.
        if (
          data.result.files[0].error != null &&
          data.result.files[0].error !== ''
        ) {
          toastr.error(data.result.files[0].error);
          return;
        }

        if (options.videoImageCovers) {
          saveVideoCoverImage(data);
        }

        if (refreshSessionInterval != null && form.fileupload('active') <= 0) {
          clearInterval(refreshSessionInterval);
        }

        // Run the callback function for done when
        // we're processing the last uploading element
        const filesToUploadCount = form.find('tr.template-upload').length;
        if (
          filesToUploadCount == 1 &&
          options.uploadDoneEvent !== undefined &&
          options.uploadDoneEvent !== null &&
          typeof options.uploadDoneEvent == 'function'
        ) {
          // Run in a short while.
          // this gives time for file-upload's own deferreds to run
          setTimeout(function() {
            options.uploadDoneEvent(data);
          }, 300);
        }
      })
      .bind('fileuploadprogressall', function(e, data) {
        // Hide progress data and show processing
        if (data.total > 0 && data.loaded === data.total) {
          form.find('.fileupload-progress .progress-extended').hide();
          form.find('.fileupload-progress .progress-end').show();
        }
      })
      .bind('fileuploadadd', function(e, data) {
        if (uploadOptions.limitMultiFileUploads === 1) {
          const totalFiles =
            form.find('.files > tr.template-upload, tr.template-download')
              .length;

          // Check if the number of files exceeds the limit
          if (totalFiles >= uploadOptions.maxNumberOfFiles) {
            // Show toast error message
            toastr.error(
              translations.canOnlyUploadMax
                .replace('%s', uploadOptions.maxNumberOfFiles),
            );

            // Prevent adding the file
            return false;
          }
        }
      })
      .bind('fileuploadadded fileuploadcompleted fileuploadfinished',
        function(e, data) {
          // Get uploaded and downloaded files and toggle Done button
          const filesToUploadCount = form.find('tr.template-upload').length;
          const $button =
            form.parents('.modal:first').find('button.btn-bb-main');
          if (!options.templateOptions.includeTagsInput) {
            $('.tags-input-container').addClass('d-none');
          }
          if (filesToUploadCount === 0) {
            $button.removeAttr('disabled');
            videoImageCovers = {};
          } else {
            $button.attr('disabled', 'disabled');
          }
        })
      .bind('fileuploaddrop', function(e, data) {
        if (options.videoImageCovers) {
          handleVideoCoverImage(e, data);
        }

        if (maxImagePixelSize > 0) {
          checkImagePixelSize(e, data);
        }
      });

    if (options.templateOptions.folderSelector) {
      // Handle creating a folder selector
      // compile tree folder modal and append it to Form

      // make bootstrap happy.
      if ($('#folder-tree-form-modal').length != 0) {
        $('#folder-tree-form-modal').remove();
      }

      if ($('#folder-tree-form-modal').length === 0) {
        const folderTreeModal = templates['folder-tree'];
        $('body').append(folderTreeModal({
          container: 'container-folder-form-tree',
          modal: 'folder-tree-form-modal',
        }));

        $('#folder-tree-form-modal').on('hidden.bs.modal', function(ev) {
          // Fix for 2nd/overlay modal
          $('.modal:visible').length && $(document.body).addClass('modal-open');

          $(ev.currentTarget).data('bs.modal', null);
        });
      }

      // Init JS Tree
      initJsTreeAjax(
        $('#folder-tree-form-modal').find('#container-folder-form-tree'),
        options.initialisedBy,
        true,
        600,
      );
    }

    // Handle any form opened event
    if (
      options.formOpenedEvent !== null &&
      options.formOpenedEvent !== undefined
    ) {
      eval(options.formOpenedEvent)(dialog);
    }
  }, 500);

  return dialog;
};

/**
 * Binds to a File Input and listens for changes
 * when it finds some it sets up for capturing a video thumbnail
 * @param e
 * @param data
 */
function handleVideoCoverImage(e, data) {
  // handle click and drag&drop ways
  const files = data === undefined ? e.currentTarget.files : data.files;
  let video = null;

  // wait a little bit for the preview to be in the form
  const checkExist = setInterval(function() {
    if ($('.preview').find('video').length) {
      // iterate through our files, check if we have videos
      // if we do, then set params on video object,
      // convert 2nd second of the video to an image
      // and register onseeked and onpause events
      Array.from(files).forEach(function(file, index) {
        if (!file.error && file.type.includes('video') && file.preview) {
          video = file.preview;
          video.name = file.name;
          video.setAttribute('id', file.name);
          video.preload = 'metadata';
          video.onseeked = createImage;
          video.onpause = createImage;
          // set current time to trigger event
          // and create the cover image
          video.currentTime = 2;
        }
      });

      // show help text describing this feature.
      const helpText = translations.videoImageCoverHelpText;
      const $helpTextSelector = $('.template-upload video:first')
        .closest('tr')
        .find('td span.info');
      $helpTextSelector.empty();
      $helpTextSelector.append(helpText);

      clearInterval(checkExist);
    }
  }, 100);
}

function createImage() {
  // eslint-disable-next-line no-invalid-this
  const self = this;
  // this will actually create the image
  // and save it to an object with file name as a key
  const canvas = document.createElement('canvas');
  canvas.height = self.videoHeight;
  canvas.width = self.videoWidth;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(self, 0, 0, canvas.width, canvas.height);

  const videoImageCover = new Image();
  videoImageCover.src = canvas.toDataURL();

  videoImageCovers[self.name] = videoImageCover.src;
}

function saveVideoCoverImage(data) {
  // this is called when fileUpload is finished
  // reason being that we need mediaId to save videoCover image correctly.
  const results = data.result.files[0];
  const thumbnailData = {};

  // we only want to call this for videos
  // (it would not do anything for other types).
  if (results.mediaType === 'video') {
    // get mediaId from results (finished upload)
    thumbnailData['mediaId'] = results.mediaId;

    // get the base64 image we captured and stored for this file name
    thumbnailData['image'] = videoImageCovers[results.fileName];

    // remove this key from our object
    delete videoImageCovers[results.name];

    // this calls function in library controller that decodes the image and
    // saves it to library as
    // "{libraryLocation}/{$mediaId}_{mediaType}cover.png".
    $.ajax({
      url: addMediaThumbnailUrl,
      type: 'POST',
      data: thumbnailData,
    });
  }
}
/**
 * Binds to a File Input and listens for changes,
 * if Image was added, check the max resize limit
 * and show a warning message if added image is too large
 * @param e
 * @param data
 */
function checkImagePixelSize(e, data) {
  const files = data === undefined ? e.currentTarget.files : data.files;

  const $existingFiles = $('.template-upload canvas')
    .closest('tr')
    .find('td span.info');

  const checkExist = setInterval(function() {
    if ($('.preview').find('canvas').length) {
      // iterate through our files
      Array.from(files).forEach(function(file, index) {
        if (!file.error && file.type.includes('image')) {
          // if we have existing files, adjust index
          // to ensure we put the warning in the right place
          if ($existingFiles.length > 0) {
            if (index === 0) {
              index = $existingFiles.length;
            } else {
              index += $existingFiles.length;
            }
          }
          img = new Image();
          const objectUrl = URL.createObjectURL(file);
          img.onload = function() {
            if (this.width > maxImagePixelSize ||
              this.height > maxImagePixelSize
            ) {
              const helpText = translations.imagePixelSizeTooLarge;
              $('.template-upload canvas')
                .closest('tr')
                .find('td span.info')[index]
                .append(helpText);
            }

            URL.revokeObjectURL(objectUrl);
          };
          img.src = objectUrl;
        }
      });

      clearInterval(checkExist);
    }
  }, 300);
}
