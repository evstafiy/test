var $tabActs;

function initActsContainer($container) {
  setFixedHeaderTables($container.find('.obj-table'));

  $container.find('.tabs-acts .nav-link').on('click', function() {
    var $tab = $(this),
      src_tbl = $tab.attr('src-tbl');

    if ($tab.attr('href') == '#tab-dicts-act-work-container') {
      $container.find('#tab-dicts-act-work-container >.nav-tabs .active').trigger('click');
      return;
    }

    if ($tab.hasClass('active'))
      return;

    var $table = $($tab.attr('html-tbl'));

    if ($table.find('[tr-id]').length) return;

    ajaxView({
      url: 'table/gettable',
      data: { table: src_tbl },
      success: function (html) {

        $table.find('tbody').html(html);

        var select_id = $table.data('select_id');
        if (select_id != undefined)
          $table.find('[tr-id="' + select_id + '"]').addClass('select');
      }
    });
  });

  initActs($container);

  initDictActWorks($container)
}

function initActs($container) {
  $tabActs = $container.find('#tab-acts');

  var $tblActs = $tabActs.find('.acts-table');

  $tblActs
    .on('dblclick', '[tr-id]', function() {
      var $row = $tblActs.data('select_row');

      if ($row == undefined || !$row.length)
        return;

      openEditActModal({
        $row: $row,
        id_act: $row.attr('tr-id'),
        callback: function (data) {
          $row.find('[term="date_create"]').text(moment.unix(data.date_create).format(DATE_TIME_FORMAT));
          $row.find('[term="vehicle"]').text(data.name_vehicle);
          $row.find('[term="enterprise"] input').text(data.name_enterprise);
          $row.find('[term="masters"]').text(data.name_masters);

          if (data.name_creator) $row.find('[term="creator"]').text(data.name_creator);
          if (data.name_changer) $row.find('[term="changer"]').text(data.name_changer);
        }
      });
    });

  var $intervalActWorks = $tabActs.find('.interval-act-works').intervalPickerDate();

  var $actFilterEnt = $tabActs.find('.act-filter-ents').multiselect({
    includeSelectAllOption: true,
    enableFiltering: true,
    filterPlaceholder: 'Поиск...'
  });

  $tabActs.find('.export-acts-excel').click(function() {
    var $btn = $(this);

    var url = 'report/export/calcactworks?';

    var interval = $intervalActWorks.data('intervalpicker').getInterval();
    url += 'beg=' + toUnixDate(interval.beg) + '&' + 'end=' +  toUnixDate(interval.end);

    var entFilter = getMultiSelectFilter($actFilterEnt.closest('.multiselect-dropdown'));

    if (entFilter == null) {
      return;
    }

    if (entFilter != 'all')
      url += '&id_ents=' + entFilter;

    ajaxRequest({
      url: url,
      success:function(answer) {
        window.location = 'report/download/' + answer.filename;
      }
    });
  });

  $tabActs.find('.show-act-works').click(function() {
    var interval = $intervalActWorks.data('intervalpicker').getInterval();

    ajaxView({
      url: 'table/gettable',
      data: {
        table: 'acts_tbl',
        interval: toUnixDate(interval.beg) + '-' + toUnixDate(interval.end)
      },
      success: function (html) {
        $tblActs.find('tbody').html(html);

        var select_id = $tblActs.data('select_id');
        if (select_id != undefined)
          $tblActs.find('[tr-id="' + select_id + '"]').addClass('select');
      }
    });

  });

  $tabActs.find('.edit-act').click(function() {
    var $row = $tblActs.data('select_row');

    if ($row == undefined || !$row.length)
      return;

    openEditActModal({
      $row: $row,
      id_act: $row.attr('tr-id'),
      callback: function(data) {
        $row.find('[term="date_create"]').text(moment.unix(data.date_create).format(DATE_FORMAT));
        $row.find('[term="vehicle"]').text(data.name_vehicle);
        $row.find('[term="enterprise"] input').text(data.name_enterprise);
        $row.find('[term="masters"]').text(data.name_masters);

        if (data.name_creator) $row.find('[term="creator"]').text(data.name_creator);
        if (data.name_changer) $row.find('[term="changer"]').text(data.name_changer);
      }
    });

  });

  $tabActs.find('.remove-act').click(function() {
    var $row = $tblActs.data('select_row');

    if ($row == undefined || !$row.length)
      return;

    var deleted = $row.hasClass('deleted') ? 0 : 1;

    var titleModal = 'Удаление акта',
      bodyModel = 'Вы, действительно хотите удалить акт?',
      labelAction = 'Удалить';

    //TODO завернуть в функцию
    if (!deleted) {
      titleModal = 'Восстановление акта';
      bodyModel = 'Вы, действительно хотите восстановить акт?';
      labelAction = 'Восстановить';
    }

    $('<div>').customModal({
      title: titleModal,
      body: bodyModel,
      btnCancel: true,
      buttons: [
        {
          label: labelAction,
          class: 'btn-primary',
          closeModal: true,
          action: function() {
            ajaxRequest({
              url: 'act/removeact',
              data: { id: $row.attr('tr-id'), deleted: deleted  },
              success: function (answer) {
                if (deleted) {
                  if ($tabActs.find('.toggle-remove-act').hasClass('show'))
                    $row.addClass('deleted');
                  else
                    $row.remove();
                }
                else
                  $row.removeClass('deleted');

              }
            });
          }
        }
      ]
    });
  });

  $tabActs.find('.upload-act').click(function() {
    var $row = $tblActs.data('select_row');

    if ($row == undefined || !$row.length)
      return;

    var id_scan = $row.attr('id-scan');
    if (id_scan) {
      $('<div>').customModal({
        title: 'Внимание',
        body: 'Акт уже содержит файл скана.<br>Вы, действительно хотите заменить?',
        buttons: [
          { label: 'Заменить', class: 'btn-primary', closeModal: true, action: function () { openUploadFile($row); } }
        ],
        btnCancel: true
      });

      return;
    }

    openUploadFile($row);
  });

  $tabActs.find('.download-act').click(function() {
    var $row = $tblActs.data('select_row');

    if ($row == undefined || !$row.length)
      return;

    var id_scan = $row.attr('id-scan');
    if (!id_scan) {
      return;
    }

    downloadLink('file/download/' + id_scan);
    //window.location = 'file/download/' + id_scan;

  });

  $tabActs.find('.toggle-remove-act').click(function() {
    var $btn = $(this).toggleClass('show');

    var isShow = $btn.hasClass('show');

    $btn.attr('title', isShow ? 'Скрыть удаленные' : 'Показать удаленные');
    $btn.find('i').text(isShow ? 'visibility' : 'visibility_off');

    var data = $.extend({
      table: 'acts_tbl'
    }, getFilterActWorks());

    ajaxView({
      url: 'table/gettable',
      data: data,
      success: function (html) {

        $tblActs.find('tbody').html(html);

        var select_id = $tblActs.data('select_id');
        if (select_id != undefined)
          $tblActs.find('[tr-id="' + select_id + '"]').addClass('select');
      }
    });
  });
}

function openUploadFile($row) {
  ajaxView({
    url: '/file',
    success: function(answer) {
      var $modal = $(answer).htmlModal();

      $modal.find('[type="file"]').on('change', function() {
        $modal
          .find('.custom-file-control')
          .attr('data-content', $(this).val().split('\\').pop());
      });

      $modal.find('.submit-file').click(function() {
        var fileData = $modal.find('[type="file"]').prop('files')[0],
          _token = $modal.find('[name="_token"]').val();

        var formData = new FormData();
        formData.append('file', fileData);
        formData.append('_token', _token);
        formData.append('id_act', $row.attr('tr-id'));

        ajaxUploadFile({
          url: 'file/upload',
          formData: formData,
          callback: function(answer) {
            $modal.modal('hide');

            $row.find('[term="scan_basefile"]').text(answer.filename);
            $row.attr('id-scan', answer.id);
          }
        });
      });
    }
  });
}

function getFilterActWorks() {
  var interval = $tabActs.find('.interval-act-works').data('intervalpicker').getInterval();

  var filter = {
    interval: toUnixDate(interval.beg) + '-' + toUnixDate(interval.end)
  };

  if ($tabActs.find('.toggle-remove-act').hasClass('show'))
    filter.deleted = 1;

  return filter;
}

function openEditActModal(options) {
  var data = { view: 'editact' };
  if (options.id_problem != undefined)
    data.id_problem = options.id_problem;
  else if (options.id_act != undefined)
    data.id_act = options.id_act;

  ajaxView({
    url: 'act/getview',
    data: data,
    success: function (view) {
      var $modal = $(view).htmlModal({
        backdrop: 'static'
      });

      $modal.find('select:not([name="id_user_it"])').multiselect();
      $modal.find('[name="id_user_it"]').multiselect({
        dropUp: true
      });

      $modal.find('[name="date_create"]').datetimepicker({
        locale: moment.locale(),
        format: 'DD.MM.YYYY',
        defaultDate: moment()
      });

      $modal.find('.datetime').datetimepicker({
        locale: moment.locale(),
        //format: 'DD.MM.YYYY',
        defaultDate: moment()
      });

      $modal.find('.downtime').datetimepicker({
        locale: moment.locale(),
        format: 'HH:mm',
        defaultDate: moment('01:00', 'HH:mm')
      });

      $modal.find('[name="distance_work"]').on('input', function() {
        this.value = inputOnlyNumeric(this.value);
      });

      $modal.on('input', '[type-param="numeric"] input', function() {
        this.value = inputOnlyNumeric(this.value);
      });

      $modal.find('.checkbox-date').each(function() {
        var $check = $(this);

        $check.on('change', function() {
          $check
            .parent()
            .parent()
            .find('.datepicker-control input')
            .toggleClass('hidden-text', !$check.prop('checked'));
        })
      });

      $modal.find('.work-equip').each(function() {
        var $this = $(this),
          $parent = $this.closest('.act-work');

        $this.multiselect('setOptions', {
          onChange: function(e) {
            changeWorkEquip($parent, e.context.value);
          }
        });
      });

      $modal.find('.add-act-equip >a').click(function() {
        var $this = $(this),
          $body = $this.closest('.act-group').find('>.body');

        var dict_id = $this.attr('dict-id');

        ajaxView({
          url: 'act/getview',
          data: {
            view: 'actworkequip',
            id_dict: dict_id
          },
          success: function(view) {
            var $view = $(view).prependTo($body);

            $view.find('.act-done-works').multiselect();

            $view.find('.work-equip').multiselect({
              onChange: function(e) {
                changeWorkEquip($view, e.context.value);
              }
            });
          }
        });
      });

      $modal.on('click', '.remove-act-equip', function() {
        var $wrapper = $(this).closest('[dict-id]');

        var id = $wrapper.attr('data-id');
        if (id == undefined) {
          $wrapper.remove();
          return;
        }

        $('<div>').customModal({
          title: 'Удаление оборудования акта',
          body: 'Вы, действительно хотите удалить оборуование',
          btnCancel: true,
          buttons: [
            {
              label: 'Удалить',
              class: 'btn-primary',
              closeModal: true,
              action: function() {
                ajaxRequest({
                  url: 'act/removeactwork',
                  data: { id: id  },
                  success: function (answer) {
                    $wrapper.remove();
                  }
                });
              }
            }
          ]
        });
      });


      $modal.on('click', '.dd-act-equip-additional >a', function() {
        var $a = $(this),
          $body = $a.closest('.additional-container').find('>.body');

        var dict_id = $a.attr('dict-id');

        ajaxView({
          url: 'act/getview',
          data: {
            view: 'actworkequip',
            id_dict: dict_id
          },
          success: function(view) {
            var $view = $(view).prependTo($body);

            $view.find('.act-done-works').multiselect();

            $view.find('.work-equip').multiselect({
              onChange: function(e) {
                changeWorkEquip($view, e.context.value);
              }
            });
          }
        });

      });

      /*
       $modal.find('.transfer-equip').each(function() {
       var $this = $(this);

       $this.find('.transfer-col [type="radio"]').on('change', function() {
       $this.find('.diagnost-col').toggle(this.value == '0');
       });
       });
       */

      $modal.find('.btn-edit-act').click(function() {
        editAct($modal, options)
      });

      $modal.find('.btn-close-act').click(function() {
        closeAct($(this), options);
      });
    }
  });
}

function editAct($form, options) {
  var $btn = $(this),
    btnLocked = $btn.data('locked');

  if (btnLocked) {
    return;
  }

  var is_error = false,
    data = {
    id_ord_prob: $form.find('[name="id_ord_problem"]').val(),
    date_create: toUnixDate($form.find('[name="date_create"]').data('DateTimePicker').date()),
    date_work: toUnixDate($form.find('[name="date_work"]').data('DateTimePicker').date()),
    date_close: toUnixDate($form.find('[name="date_close"]').data('DateTimePicker').date()),
    place_work: $form.find('[name="place_work"]').val(),
    distance_work: $form.find('[name="distance_work"]').val(),
    work: $form.find('[name="act_work"]').val()
  };

  var id_act = $form.find('[name="id_act"]').val();
  if (id_act != null && id_act != '') {
    data.id = id_act;
  }

  if ($form.find('[name="chb_downtime"]').prop('checked')) {
    data.downtime = $form.find('[name="downtime"]').val();
  }

  var $actMasters = $form.find('[name="act_masters"]'),
    $parenMasters = $actMasters.closest('.parent-select');

  is_error = setErrorInput({
    is_error: $actMasters.val() == null,
    $parent: $parenMasters,
    error_text: 'Выберите специалистов'
  });

  if (is_error) {
    $form.scrollToElement($parenMasters, { offsetTop: 50 });
    return;
  }

  data.masters = getMultiSelectValues($actMasters.closest('.multiselect-dropdown')).join(';');

  var $userIt = $form.find('[name="id_user_it"]'),
    $parentUserit = $userIt.closest('.parent-select');

  is_error = setErrorInput({
    is_error: $userIt.val() == '-1',
    $parent: $parentUserit,
    error_text: 'Выберите специалистов IT'
  });

  if (is_error) {
    $form.scrollToElement($parentUserit, { offsetTop: 50 });
    return;
  }

  data.id_user_it = $userIt.val();

  data.resp_person_absent = $form.find('[name="resp_person_absent"]').prop('checked') ? 1 : 0;
  data.resp_person = $form.find('[name="resp_person"]').val();

  data.violations = $form.find('[name="violations"]').val();
  data.violated = $form.find('[name="violated"]').prop('checked') ? 1 : 0;
  data.eq_efficient = $form.find('[name="equip_efficient"]').prop('checked') ? 1 : 0;
  data.note = $form.find('[name="note"]').val();

  data.work_eqs = [];
  $form.find('.act-work').each(function() {
    var act_eq = getActWorksEquips($(this));

    data.work_eqs.push(act_eq);
  });

  showLoader(data.id ?  'Изменение акта' : 'Создание акта');

  $btn.data('locked', 1);

  ajaxRequest({
    url: 'act/editact',
    method: 'POST',
    data: JSON.stringify(data),
    success: function (answer) {
      if (answer.status != 'OK') {
        console.error(answer);
        return;
      }

      $form.modal('hide');

      answer.date_create = data.date_create;
      answer.name_masters = $actMasters.find('[selected="selected"]').map(function() { return $(this).text() }).get().join(', ');
      answer.name_enterprise = $form.find('[name="enterprise"]').val();
      answer.name_vehicle = $form.find('[name="veh_model"]').val() + ' ' + $form.find('[name="veh_gos_num"]').val();

      if (options.callback)
        options.callback(answer);
    },
    anySuccess: function() {
      hideLoader();
      $btn.data('locked', 0);
    }
  });
}

function getActWorksEquips($form) {

  var act_eq = {};

  var id = $form.attr('data-id');
  if (id != undefined)
    act_eq.id = id;

  act_eq.dict_eq = $form.attr('dict-id');

  //act_eq.defective = $this.find('.defective').prop('checked') ? 1: 0;
  act_eq.work = $form.find('.work-equip').val();

  var done_works = getMultiSelectValues($form.find('.act-done-works').closest('.multiselect-dropdown'));
  if (done_works.length)
    act_eq.done_works = done_works.join(';');

  if (act_eq.work == '2' || act_eq.work == '3') {

    var $trans = $form.find('.radio-transfer:checked');
    if ($trans.length) {
      act_eq.transfer = $trans.val();

      if (act_eq.transfer == '0') {
        act_eq.cause_work = $form.find('.cause-work-equip').val();
        act_eq.diagnosted = $form.find('.radio-diagnost:checked').val();
      }
    }
  }

  act_eq.curr_eq = {};
  var $currEq = $form.find('>.curr-equip');


  var id_curr_eq = $currEq.attr('eq-id');
  if (id_curr_eq != undefined)
    act_eq.curr_eq.id = id_curr_eq;

  act_eq.curr_eq.owner = $currEq.find('>.owner-row .owner-equip:checked').val();
  act_eq.curr_eq.values = getEquipParamValues($currEq.find('>.equip-param-container .equip-param'));

  var $additionals = $currEq.find('>.additional-container .act-work-additional');

  if ($additionals.length) {
    act_eq.curr_eq.addits = [];
    $additionals.each(function() {
      var addit = getActWorksEquips($(this));

      act_eq.curr_eq.addits.push(addit);
    });
  }

  if (act_eq.work == '2') {
    act_eq.repl_eq = {};
    var $replEq = $form.find('>.repl-equip');
    var id_repl_eq = $replEq.attr('eq-id');

    if (id_repl_eq != undefined)
      act_eq.repl_eq.id = id_repl_eq;

    act_eq.repl_eq.values = getEquipParamValues($replEq.find('>.equip-param-container  .equip-param'));
    act_eq.repl_eq.owner = $replEq.find('>.owner-row .owner-equip:checked').val();

    $additionals = $replEq.find('>.additional-container .act-work-additional');
    if ($additionals.length) {
      act_eq.repl_eq.addits = [];
      $additionals.each(function() {
        var addit = getActWorksEquips($(this));

        act_eq.repl_eq.addits.push(addit);
      });
    }
  }

  return act_eq;
}

function closeAct($btn, options) {
  if (options.id_act == undefined)
    return;

  var closed = $btn.attr('closed');

  ajaxRequest({
    url: 'act/closeact',
    data: {
      id: options.id_act,
      closed: closed == '1' ? 0 : 1
    },
    success: function (answer) {

      if (closed == '0') {
        $btn
          .attr('status', 1)
          .text('Открыть акт');

        options.$row.addClass('success');
      }
      else {
        $btn
          .attr('status', 0)
          .text('Закрыть акт');

        options.$row.removeClass('success');
      }
    }
  });

}

function changeWorkEquip($container , value) {
  $container.removeClass('mount');
  $container.removeClass('replace');
  $container.removeClass('dismantle');

  if (value == '0')
    return;

  if (value == '1') {
    $container.addClass('mount');
    return;
  }

  if (value == '2') {
    $container.addClass('replace');
    return;
  }

  $container.addClass('dismantle');
}

function initDictActWorks($container) {
  var $tab = $container.find('#tab-dicts-act-work-container');

  $tab.find('.tabs-dict-act-works [src-tbl]').on('click', function() {
    var $tab = $(this),
      src_tbl = $tab.attr('src-tbl');

    if (src_tbl == undefined)
      return;

    ajaxView({
      url: 'table/gettable',
      data: { table: src_tbl },
      success: function (html) {

        var $table = $($tab.attr('html-tbl'));
        $table.find('tbody').html(html);

        var select_id = $table.data('select_id');
        if (select_id != undefined)
          $table.find('[tr-id="' + select_id + '"]').addClass('select');
      }
    });
  });

  var $tabDictActWorks = $tab.find('#tab-dict-act-works'),
    $tblDictActWorks = $tabDictActWorks.find('.dict-act-works-table');

  $tabDictActWorks.find('.search-dict-act-works').on('input', function() {
    _filterTable({table: $tblDictActWorks, filter: this.value});
  });

  $tabDictActWorks.find('.add-dict-act-work').click(function() {
    editDictActWorkModal({
      callback: function (data) {
        $tblDictActWorks.prepend(
          '<tr tr-id="' + data.id + '">' +
          '<td term="name">' + data.name + '</td>' +
          '<td term="groups">' + data.name_groups + '</td>' +
          '<td term="def_price">' + data.def_price + '</td>' +
          '</tr>'
        );
      }
    });
  });

  $tabDictActWorks.find('.edit-dict-act-work').click(function() {
    var $row = $tblDictActWorks.data('select_row');
    if ($row == undefined) return;

    editDictActWorkModal({
      id: $row.attr('tr-id'),
      callback: function (data) {
        $row.find('[term="name"]').text(data.name);
        $row.find('[term="groups"]').text(data.name_groups);
        $row.find('[term="def_price"]').text(data.def_price);
      }
    });
  });

  $tabDictActWorks.find('.remove-dict-act-work').click(function() {
    var $row = $tblDictActWorks.data('select_row');
    if ($row == undefined) return;

    $('<div>').customModal({
      title: 'Удаление работы',
      body: 'Вы, действительно хотите удалить работу?',
      btnCancel: true,
      buttons: [
        {
          label: 'Удалить',
          class: 'btn-primary',
          closeModal: true,
          action: function() {
            ajaxRequest({
              url: 'act/removedictactwork',
              data: { id: $row.attr('tr-id') },
              success: function () {
                $row.remove();
              }
            });
          }
        }
      ]
    });

  });
}

function editDictActWorkModal(options) {
  options = $.extend({}, options);

  ajaxView({
    url: 'act/getview',
    data: {
      view: 'editdictactwork',
      id: options.id
    },
    success: function (view) {
      var $modal = $(view).htmlModal({
        backdrop: 'static'
      });

      $modal.find('select').multiselect({
        includeSelectAllOption: true
      });

      $modal.find('.edit-dict-act-work-modal').click(function() {
        editDictActWork($modal, options);
      });
    }
  })
}

function editDictActWork($form, options) {
  var is_error = false;
  var $name = $form.find('[name="name"]');

  is_error = setErrorInput({
    is_error: $name.val() == '',
    $parent: $name.closest('.row'),
    error_text: 'Введите название'
  });

  if (is_error) return;

  var $eqGroups = $form.find('.eq-groups');

  is_error = setErrorInput({
    is_error: $eqGroups.val() == '-1',
    $parent: $eqGroups.closest('.row'),
    error_text: 'Выберите группу'
  });

  if (is_error) return;

  var data = {
    name: $name.val(),
    groups: getMultiSelectValues($eqGroups.closest('.multiselect-dropdown')).join(';')
  };

  var def_price = $form.find('[name="def_name"]').val();
  if (def_price != undefined && def_price != '')
    data.def_price = def_price;

  if (options.id != undefined)
    data.id = options.id;

  ajaxRequest({
    url: 'act/editdictactwork',
    data: data,
    success: function (answer) {
      data.id = answer.id;

      data.name_groups = getMultiSelectTexts($eqGroups);

      if (options.callback)
        options.callback(data);

      $form.modal('hide');
    }
  });
}

function actWorkToString(work) {
  switch(work) {
    case 1: return 'Установка';
    case 2: return 'Осмотр';
    case 3: return 'Демонтаж';
    default: return '';
  }
}