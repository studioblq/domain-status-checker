(function ($, Drupal) {
  Drupal.behaviors.domainStatusChecker = {
    attach: function (context, settings) {
      // Check Now button
      $('.domain-check-now', context).once('checkNow').click(function (e) {
        e.preventDefault();
        var $btn = $(this);
        var domain = $btn.data('domain');
        $btn.text('Checking...').prop('disabled', true);
        $.ajax({
          url: Drupal.url('admin/config/system/domain-status/check/' + domain),
          type: 'GET',
          dataType: 'json'
        }).done(function (response) {
          if (response.status) {
            alert('Status: ' + response.status);
            location.reload();
          } else {
            alert('Error: ' + response.error);
          }
        }).fail(function () {
          alert('AJAX request failed');
        }).always(function () {
          $btn.prop('disabled', false).text('Check Now');
        });
      });

      // Clear Logs button
      $('#domain-clear-logs', context).once('clearLogs').click(function (e) {
        e.preventDefault();
        if (!confirm('Clear all logs?')) {
          return;
        }
        $.ajax({
          url: Drupal.url('admin/config/system/domain-status/clear-logs'),
          type: 'GET',
          dataType: 'json'
        }).done(function (response) {
          if (response.cleared) {
            alert('Logs cleared');
            location.reload();
          } else {
            alert('Error: ' + response.error);
          }
        }).fail(function () {
          alert('AJAX request failed');
        });
      });
    }
  };
})(jQuery, Drupal);
