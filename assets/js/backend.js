'use strict';

(function($) {
  $(function() {
    wpcpt_arrange();
    wpcpt_active_type();
    wpcpt_terms_init();
  });

  $(document).on('click touch', '.wpcpt_shortcode_input', function() {
    $(this).select();
  });

  if ($('.wpcpt_shortcode_input').length) {
    $(document).on('keyup change', '#title', function() {
      var _title = $(this).
          val().
          replace(/&/g, '&amp;').
          replace(/>/g, '&gt;').
          replace(/</g, '&lt;').
          replace(/"/g, '&quot;');
      var _id = $('.wpcpt_shortcode_input').attr('data-id');

      $('.wpcpt_shortcode_input').
          val('[wpc_product_table id="' + _id + '" name="' + _title + '"]');
    });
  }

  $(document).on('click touch', '.wpcpt_configuration_nav', function(e) {
    var tab = $(this).attr('data-tab');

    $('.wpcpt_configuration_nav').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    $('.wpcpt_configuration').hide();
    $('.wpcpt_configuration_' + tab).show();

    e.preventDefault();
  });

  // search product
  $(document).on('change', '.wpcpt-product-search', function() {
    var _val = $(this).val();

    if (Array.isArray(_val)) {
      $(this).
          closest('div').
          find('.wpcpt-product-search-input').
          val(_val.join());
    } else {
      if (_val === null) {
        $(this).
            closest('div').
            find('.wpcpt-product-search-input').
            val('');
      } else {
        $(this).
            closest('div').
            find('.wpcpt-product-search-input').
            val(String(_val));
      }
    }
  });

  setInterval(function() {
    $('.wpcpt-product-search').each(function() {
      var _val = $(this).val();

      if (Array.isArray(_val)) {
        $(this).
            closest('div').
            find('.wpcpt-product-search-input').
            val(_val.join());
      } else {
        if (_val === null) {
          $(this).
              closest('div').
              find('.wpcpt-product-search-input').
              val('');
        } else {
          $(this).
              closest('div').
              find('.wpcpt-product-search-input').
              val(String(_val));
        }
      }
    });
  }, 1000);

  // search category
  $(document).on('change', '.wpcpt-category-search', function() {
    var _val = $(this).val();

    if (Array.isArray(_val)) {
      $(this).
          closest('div').
          find('.wpcpt-category-search-input').
          val(_val.join());
    } else {
      if (_val === null) {
        $(this).
            closest('div').
            find('.wpcpt-category-search-input').
            val('');
      } else {
        $(this).
            closest('div').
            find('.wpcpt-category-search-input').
            val(String(_val));
      }
    }
  });

  $(document).on('change', '.wpcpt_configuration_type', function() {
    wpcpt_active_type();
    wpcpt_terms_init();
  });

  // search terms
  $(document).on('change', '.wpcpt_configuration_terms_select', function() {
    var $this = $(this);
    var val = $this.val();
    var source = $this.closest('.wpcpt_configuration_source').
        find('.wpcpt_configuration_type').
        val();
    var $terms = $this.closest('.wpcpt_configuration_source').
        find('.wpcpt_configuration_terms');

    if (Array.isArray(val)) {
      $terms.val(val.join()).trigger('change');
    } else {
      if (val === null) {
        $terms.val('').trigger('change');
      } else {
        $terms.val(String(val)).trigger('change');
      }
    }

    $this.data(source, val.join());
  });

  $(document).on('click touch', '.wpcpt-column-new', function() {
    var wpcpt_new = $(this);
    var wpcpt_type = $('.wpcpt-column-type').val();
    var wpcpt_editor = 'wpcpt_editor_' + Date.now().toString();

    wpcpt_new.prop('disabled', true);

    var data = {
      action: 'wpcpt_add_column', editor: wpcpt_editor, type: wpcpt_type,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wpcpt-columns').append(response);

      if (wpcpt_type == 'custom') {
        wp.editor.initialize(wpcpt_editor, {
          mediaButtons: true, tinymce: {
            wpautop: true,
            plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
            toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more | spellchecker',
          }, quicktags: true,
        });
      }

      wpcpt_arrange();
      wpcpt_new.prop('disabled', false);
    });
  });

  $(document).on('click touch', '.wpcpt-column-remove', function() {
    var r = confirm(
        'Do you want to remove this column? This action cannot undo.');

    if (r == true) {
      $(this).closest('.wpcpt-column').remove();
    }
  });

  function wpcpt_terms_init() {
    var $terms = $('.wpcpt_configuration_terms_select');
    var source = $terms.closest('.wpcpt_configuration_source').
        find('.wpcpt_configuration_type').
        val();

    $terms.selectWoo({
      ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            q: params.term, action: 'wpcpt_search_term', taxonomy: source,
          };
        }, processResults: function(data) {
          var options = [];
          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }
          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1,
    });

    if ((typeof $terms.data(source) === 'string' ||
        $terms.data(source) instanceof String) && $terms.data(source) !== '') {
      $terms.val($terms.data(source).split(',')).change();
    } else {
      $terms.val([]).change();
    }
  }

  function wpcpt_arrange() {
    $('.wpcpt-columns').sortable({
      handle: '.wpcpt-column-move',
    });
  }

  function wpcpt_active_type() {
    var type = $('select[name="wpcpt_configuration_type"]').val();
    var text = $('select[name="wpcpt_configuration_type"]').
        find(':selected').
        text().
        trim();

    $('.wpcpt_configuration_type_row').hide();

    if (type === 'products' || type === 'categories_tags' || type ===
        'on_sale' || type === 'best_selling') {
      $('.wpcpt_configuration_type_' + type).show();
    } else {
      $('.wpcpt_configuration_type_terms_label').text(text);
      $('.wpcpt_configuration_type_terms').show();
    }
  }
})(jQuery);