const table = $('#templates').DataTable({
  language: dataTablesLanguage,
  dom: dataTablesTemplate,
  serverSide: true,
  stateSave: true,
  stateDuration: 0,
  responsive: true,
  stateLoadCallback: dataTableStateLoadCallback,
  stateSaveCallback: dataTableStateSaveCallback,
  filter: false,
  searchDelay: 3000,
  order: [[0, 'asc']],
  ajax: {
    url: developerTemplatesSearchURL,
    data: function(d) {
      $.extend(d, $('#templates').closest('.XiboGrid')
        .find('.FilterDiv form').serializeObject());
    },
  },
  columns: [
    {data: 'id', responsivePriority: 2},
    {data: 'templateId', responsivePriority: 2},
    {data: 'dataType'},
    {data: 'title', orderable: false},
    {data: 'type', orderable: false},
    {
      data: 'groupsWithPermissions',
      responsivePriority: 3,
      render: dataTableCreatePermissions,
    },
    {
      orderable: false,
      responsivePriority: 1,
      data: dataTableButtonsColumn,
    },
  ],
});

table.on('draw', dataTableDraw);
table.on('processing.dt', dataTableProcessing);
dataTableAddButtons(table, $('#templates_wrapper').find('.dataTables_buttons'));

$('#refreshGrid').on('click', function() {
  table.ajax.reload();
});

$('#module-template-xml-import').on('click', function(e) {
  e.preventDefault();

  openUploadForm({
    url: developerTemplatesImportURL,
    templateId: 'template-module-xml-upload',
    uploadTemplateId: 'template-module-xml-upload-files',
    title: developerTemplatePageTrans.importXML,
    initialisedBy: 'module-templates-upload',
    buttons: {
      main: {
        label: developerTemplatePageTrans.done,
        className: 'btn-primary btn-bb-main',
        callback: function() {
          table.ajax.reload();
          XiboDialogClose();
        },
      },
    },
    templateOptions: {
      multi: false,
      trans: developerTemplatePageTrans.templateOptions,
      upload: {
        validExt: 'xml',
      },
    },
  });
});

window.moduleTemplateAddFormOpen = function(dialog) {
  const $form = $(dialog).find('#form-module-template');
  $('#dataType', $form)
    .on('select2:select', function(e) {
      const dataType = $(e.currentTarget).select2('data')[0].id;
      const $templateSelect = $(dialog).find('#copyTemplateId');
      const searchUrl =
        moduleTemplateSearchURL.replace(':dataType', dataType);

      $templateSelect.data('searchUrl', searchUrl);
      makeTemplateSelect($templateSelect);
      $templateSelect.parent().parent().removeClass('d-none');
      $templateSelect.val(null).trigger('change');
    });
};

function makeTemplateSelect($element) {
  // clear existing options.
  $element.empty().trigger('change');
  // append empty option
  $element.append(new Option('', '', false, false));

  // get static templates for the selected dataType
  $.ajax({
    method: 'GET',
    url: $element.data('searchUrl'),
    data: $element.data('filterOptions'),
    dataType: 'json',
    success: function(response) {
      $.each(response.data, function(key, el) {
        $element.append(new Option(el.title, el.templateId, false, false));
      });

      $element.select2({
        allowClear: true,
        placeholder: {
          id: null,
          value: '',
        },
      });
    },
    error: function(xhr) {
      SystemMessage(
        xhr.message || developerTemplatePageTrans.unknownError,
        false);
    },
  });
}
