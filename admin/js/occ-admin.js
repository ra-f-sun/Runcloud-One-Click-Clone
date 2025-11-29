jQuery(document).ready(function ($) {
  // Cache Elements
  var $form = $("#occ-clone-form");
  var $btn = $form.find('button[type="submit"]');
  var $nameInput = $("#app_name");
  var $subInput = $("#app_subdomain");
  var $userInput = $("#system_user_id");

  // Run check immediately on load (in case browser auto-fills)
  validateAll();

  // 1. App Name Listener (The "Test Clone" Scenario)
  $nameInput.on("input blur", function () {
    var val = $(this).val();

    // Rule: Alphanumeric and hyphens only. No spaces.
    // If user types "Test Clone", we flag it immediately.
    if (/[^a-z0-9-]/i.test(val)) {
      showInlineError(
        $(this),
        "App name cannot contain spaces or special characters."
      );
    } else if (val.length > 0 && val.length < 3) {
      showInlineError($(this), "Name is too short (min 3 chars).");
    } else {
      clearInlineError($(this));
    }

    // Auto-fill Subdomain (Sanitized)
    // We clean it up for them here even if they typed spaces above
    var subSlug = val
      .toLowerCase()
      .replace(/[^a-z0-9-]/g, "")
      .replace(/-+/g, "-");
    $subInput.val(subSlug);

    // Re-validate everything to toggle button
    validateAll();
  });

  // 2. Subdomain Listener
  $subInput.on("input blur", function () {
    var val = $(this).val();
    if (/[^a-z0-9-]/.test(val)) {
      showInlineError(
        $(this),
        "Subdomain must be lowercase letters, numbers, or hyphens."
      );
    } else {
      clearInlineError($(this));
    }
    validateAll();
  });

  // 3. User ID Listener
  $userInput.on("input blur", function () {
    var val = $(this).val();
    if (val === "" || isNaN(val)) {
      // We don't show text here usually, just keep button disabled,
      // but we can add a border if needed.
      $(this).addClass("occ-input-error");
    } else {
      $(this).removeClass("occ-input-error");
    }
    validateAll();
  });

  // --- Core Validation Functions ---

  function validateAll() {
    var isNameValid =
      !/[^a-z0-9-]/i.test($nameInput.val()) && $nameInput.val().length >= 3;
    var isSubValid =
      !/[^a-z0-9-]/.test($subInput.val()) && $subInput.val().length >= 3;
    var isUserValid = $userInput.val().length > 0 && !isNaN($userInput.val());

    if (isNameValid && isSubValid && isUserValid) {
      $btn.prop("disabled", false).removeClass("occ-btn-disabled");
    } else {
      $btn.prop("disabled", true).addClass("occ-btn-disabled");
    }
  }

  function showInlineError($el, message) {
    $el.addClass("occ-input-error");

    // Check if error message exists next to it
    var $msg = $el.siblings(".occ-error-text");
    if ($msg.length === 0) {
      // If inside a group (like subdomain), find the parent container
      if ($el.parent(".occ-input-group").length) {
        $el
          .parent()
          .after('<span class="occ-error-text">' + message + "</span>");
      } else {
        $el.after('<span class="occ-error-text">' + message + "</span>");
      }
    } else {
      $msg.text(message);
    }
  }

  function clearInlineError($el) {
    $el.removeClass("occ-input-error");

    // Remove text if exists
    var $msg = $el.siblings(".occ-error-text");
    if ($msg.length > 0) $msg.remove();

    // Check group parent sibling
    if ($el.parent(".occ-input-group").length) {
      $el.parent().next(".occ-error-text").remove();
    }
  }

  // --- Submission Handler (Same as before) ---
  $form.on("submit", function (e) {
    e.preventDefault();

    // Double check before sending (Safety)
    if ($btn.hasClass("occ-btn-disabled")) return;

    var $msg = $("#occ-response-area");
    var $progressCont = $(".occ-progress-container");
    var $progressBar = $("#occ-progress-bar");

    $msg
      .hide()
      .removeClass("occ-notice-error occ-notice-success occ-notice-info");
    $btn.prop("disabled", true).text("Initiating...");
    $progressCont.show();
    $progressBar.css("width", "5%");

    var data = {
      action: "occ_clone_app",
      nonce: occVars.nonce,
      app_name: $nameInput.val(),
      app_subdomain: $subInput.val(),
      server_id: $("#server_id").val(),
      source_app_id: $("#source_app_id").val(),
      source_db_id: $("#source_db_id").val(),
      system_user_id: $userInput.val(),
    };

    $.post(occVars.ajaxurl, data, function (response) {
      if (response.success) {
        $msg
          .addClass("occ-notice-info")
          .html(
            '<p><strong>Clone Initiated!</strong> RunCloud is provisioning... <span id="occ-timer">0s</span></p>'
          )
          .show();
        $btn.text("Cloning in progress...");
        $progressBar.css("width", "20%");
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

  // ... [startPolling and finishClone functions remain unchanged] ...
  function startPolling(appName, domain, serverId) {
    var elapsed = 0;
    var progress = 20;

    var poller = setInterval(function () {
      elapsed += 10;
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
    }, 10000);
  }

  function finishClone(domain) {
    var url = "http://" + domain;
    var $msg = $("#occ-response-area");
    var $btn = $("#occ-clone-form").find('button[type="submit"]');
    var $progressBar = $("#occ-progress-bar");

    $progressBar.css("width", "100%");

    setTimeout(function () {
      $msg
        .removeClass("occ-notice-info")
        .addClass("occ-notice-success")
        .html(
          '<p><strong>Success!</strong> Cloning complete.</p><p><a href="' +
            url +
            '" target="_blank" class="occ-btn-primary" style="display:inline-block; text-decoration:none; margin-top:10px;">Visit New Site &rarr;</a></p>'
        );

      $btn.text("Clone Complete").hide();
    }, 600);
  }
});
