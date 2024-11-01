'use strict';

(function($) {
  $(function() {
    $('.wpc_product_table').on('init.dt', function() {
      $(this).wrap('<div class="wpc_product_table_container"></div>');
    }).DataTable(JSON.parse(wpcpt_vars.datatable_params));
  });
})(jQuery);