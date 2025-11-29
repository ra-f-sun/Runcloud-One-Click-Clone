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
    var $progressCont = $(".occ-progress-container");
    var $progressBar = $("#occ-progress-bar");

    // Reset UI
    $msg
      .hide()
      .removeClass("occ-notice-error occ-notice-success occ-notice-info");
    $btn.prop("disabled", true).text("Initiating...");
    $progressCont.show();
    $progressBar.css("width", "5%"); // Starting State

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

    $.post(occVars.ajaxurl, data, function (response) {
      if (response.success) {
        // PHASE 2: POLLING
        $msg
          .addClass("occ-notice-info")
          .html(
            '<p><strong>Clone Initiated!</strong> RunCloud is provisioning... <span id="occ-timer">0s</span></p>'
          )
          .show();
        $btn.text("Cloning in progress...");
        $progressBar.css("width", "20%"); // Jump to 20%

        startPolling(
          response.data.app_name,
          response.data.domain,
          data.server_id
        );
      } else {
        stopError("Error: " + (response.data || "Unknown error"));
      }
    }).fail(function (xhr, status, error) {
      stopError("System Error: " + error);
    });

    function stopError(message) {
      $progressCont.hide();
      $btn.prop("disabled", false).text("Clone Site");
      $msg
        .addClass("occ-notice-error")
        .html("<p>" + message + "</p>")
        .show();
    }
  });

  function startPolling(appName, domain, serverId) {
    var elapsed = 0;
    var progress = 20;

    var poller = setInterval(function () {
      elapsed += 10;
      // Fake progress increment for visual feedback while waiting
      if (progress < 90) {
        progress += 5;
      }
      $("#occ-progress-bar").css("width", progress + "%");
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
    }, 10000); // Check every 10s
  }

  function finishClone(domain) {
    var url = "http://" + domain; // Or https if SSL worked immediately
    var $msg = $("#occ-response-area");
    var $btn = $("#occ-clone-form").find('button[type="submit"]');
    var $progressBar = $("#occ-progress-bar");

    $progressBar.css("width", "100%"); // Complete

    // Wait a moment for the bar to fill before showing success
    setTimeout(function () {
      $msg
        .removeClass("occ-notice-info")
        .addClass("occ-notice-success")
        .html(
          '<p><strong>Success!</strong> Cloning complete.</p><p><a href="' +
            url +
            '" target="_blank" class="occ-btn-primary" style="display:inline-block; text-decoration:none; margin-top:10px;">Visit New Site &rarr;</a></p>'
        );

      $btn.text("Clone Complete").hide(); // Hide the form button, show the Visit button
    }, 600);
  }
});
