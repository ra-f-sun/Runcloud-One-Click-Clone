jQuery(document).ready(function ($) {
  // Auto-fill Subdomain
  $("#app_name").on("input", function () {
    var val = $(this).val();
    var sub = val.toLowerCase().replace(/[^a-z0-9]/g, "");
    $("#app_subdomain").val(sub);
  });

  // Handle Clone Form
  $("#occ-clone-form").on("submit", function (e) {
    e.preventDefault();

    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    var $msg = $("#occ-response-area");
    var $spinner = $form.find(".spinner");

    // Reset UI
    $msg.hide().removeClass("notice-error notice-success notice-warning");
    $btn.prop("disabled", true).text("Initiating...");
    $spinner.addClass("is-active");

    var data = {
      action: "occ_clone_app",
      nonce: occVars.nonce,
      app_name: $("#app_name").val(),
      app_subdomain: $("#app_subdomain").val(),
      server_id: $("#server_id").val(),
      source_app_id: $("#source_app_id").val(),
      source_db_id: $("#source_db_id").val(),
      system_user_id: $("#system_user_id").val(),
    };

    // Send Request
    $.post(occVars.ajaxurl, data, function (response) {
      if (response.success) {
        // Success: Start Polling
        $msg
          .addClass("notice-warning")
          .html(
            '<p><strong>Clone Initiated!</strong> RunCloud is working... <span id="occ-timer">0s</span></p>'
          )
          .show();
        $btn.text("Cloning in progress...").prop("disabled", true);
        startPolling(
          response.data.app_name,
          response.data.domain,
          data.server_id
        );
      } else {
        // Logic Error (API returned 422/400)
        stopSpinner("Error: " + (response.data || "Unknown error"));
      }
    }).fail(function (xhr, status, error) {
      // CRASH HANDLER: Catches 500 Fatal Errors
      console.error(xhr.responseText); // Check Console for details
      stopSpinner(
        "System Error: " + error + ". Check browser console for details."
      );
    });

    function stopSpinner(message) {
      $spinner.removeClass("is-active");
      $btn.prop("disabled", false).text("Clone Site");
      $msg
        .addClass("notice-error")
        .html("<p>" + message + "</p>")
        .show();
    }
  });

  function startPolling(appName, domain, serverId) {
    var elapsed = 0;
    var poller = setInterval(function () {
      elapsed += 15;
      $("#occ-timer").text(elapsed + "s");

      $.post(
        occVars.ajaxurl,
        {
          action: "occ_check_status",
          nonce: occVars.nonce,
          server_id: serverId,
          app_name: appName,
        },
        function (res) {
          if (res.success && res.data.status === "ready") {
            clearInterval(poller);
            finishClone(domain);
          }
        }
      );
    }, 15000); // Check every 15s
  }

  function finishClone(domain) {
    var url = "http://" + domain;
    var $msg = $("#occ-response-area");
    var $btn = $("#occ-clone-form").find('button[type="submit"]');
    var $spinner = $("#occ-clone-form").find(".spinner");

    $spinner.removeClass("is-active");
    $msg
      .removeClass("notice-warning")
      .addClass("notice-success")
      .html(
        '<p><strong>Success!</strong> Cloning complete.</p><p><a href="' +
          url +
          '" target="_blank" class="button button-primary">Visit New Site</a></p>'
      );

    $btn.text("Clone Complete");
  }
});
