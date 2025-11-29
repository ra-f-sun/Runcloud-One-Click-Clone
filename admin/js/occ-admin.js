jQuery(document).ready(function ($) {
  // Cache Elements
  var $form = $("#occ-clone-form");
  var $btn = $form.find('button[type="submit"]');
  var $nameInput = $("#app_name");
  var $subInput = $("#app_subdomain");

  // User Mode Elements
  var $modeRadios = $('input[name="sys_user_mode"]');
  var $wrapExisting = $("#wrapper-user-existing");
  var $wrapNew = $("#wrapper-user-new");
  var $selectExisting = $("#system_user_id");
  var $inputNew = $("#new_sys_user_name");

  // Init
  validateAll();

  // 1. Toggle User Mode
  $modeRadios.on("change", function () {
    var mode = $(this).val();
    if (mode === "new") {
      $wrapExisting.hide();
      $wrapNew.show();
      // Trigger auto-fill if empty
      if ($inputNew.val() === "") {
        generateUserName($subInput.val());
      }
    } else {
      $wrapNew.hide();
      $wrapExisting.show();
    }
    validateAll();
  });

  // 2. Auto-fill Subdomain & User Name
  $nameInput.on("input", function () {
    var val = $(this).val();
    var sub = val
      .toLowerCase()
      .replace(/[^a-z0-9-]/g, "")
      .replace(/-+/g, "-");

    $subInput.val(sub);

    // Also update New User Name if in "New" mode
    if ($('input[name="sys_user_mode"]:checked').val() === "new") {
      generateUserName(sub);
    }

    validateAll();
  });

  // Helper: Generate clean username (no hyphens allowed in RunCloud users sometimes)
  function generateUserName(base) {
    // Strip hyphens for username to be safe (e.g. 'test-app' -> 'testapp')
    var safeUser = base.replace(/-/g, "");
    $inputNew.val(safeUser);
  }

  // 3. Real-time Validation Listeners
  $form.find("input, select").on("input blur change", function () {
    validateAll();
  });

  function validateAll() {
    var isNameValid =
      !/[^a-z0-9-]/i.test($nameInput.val()) && $nameInput.val().length >= 3;
    var isSubValid =
      !/[^a-z0-9-]/.test($subInput.val()) && $subInput.val().length >= 3;

    // User Validation depends on Mode
    var mode = $('input[name="sys_user_mode"]:checked').val();
    var isUserValid = false;

    if (mode === "existing") {
      isUserValid = $selectExisting.val() !== "";
    } else {
      // New user validation: Alphanumeric only (safe Linux user)
      var newUser = $inputNew.val();
      isUserValid = newUser.length >= 3 && /^[a-z0-9]+$/.test(newUser);

      if (!isUserValid && newUser.length > 0) {
        showInlineError(
          $inputNew,
          "Username must be lowercase letters/numbers (no hyphens)."
        );
      } else {
        clearInlineError($inputNew);
      }
    }

    if (isNameValid && isSubValid && isUserValid) {
      $btn.prop("disabled", false).removeClass("occ-btn-disabled");
    } else {
      $btn.prop("disabled", true).addClass("occ-btn-disabled");
    }
  }

  function showInlineError($el, message) {
    $el.addClass("occ-input-error");
    var $msg = $el.siblings(".occ-error-text");
    if ($msg.length === 0) {
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
    var $msg = $el.siblings(".occ-error-text");
    if ($msg.length > 0) $msg.remove();
    if ($el.parent(".occ-input-group").length) {
      $el.parent().next(".occ-error-text").remove();
    }
  }

  // 4. Submit Handler (Triggers Modal)
  $form.on("submit", function (e) {
    e.preventDefault();
    if ($btn.hasClass("occ-btn-disabled")) return;

    // Open Modal
    $("#occ-confirm-modal").fadeIn(200);
  });

  // 5. Modal Actions
  $("#occ-modal-cancel").on("click", function () {
    $("#occ-confirm-modal").fadeOut(200);
  });

  $("#occ-modal-confirm").on("click", function () {
    $("#occ-confirm-modal").fadeOut(200);
    executeClone();
  });

  $(window).on("click", function (e) {
    if ($(e.target).is("#occ-confirm-modal")) {
      $("#occ-confirm-modal").fadeOut(200);
    }
  });

  // --- Core Cloning Logic ---
  function executeClone() {
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
      sys_user_mode: $('input[name="sys_user_mode"]:checked').val(),
      system_user_id: $("#system_user_id").val(),
      new_sys_user_name: $("#new_sys_user_name").val(),
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

        // IMPORTANT CHANGE 1: Pass the entire data object (response.data)
        // This object contains app_name, domain, AND potentially new_sys_user/pass
        startPolling(response.data, data.server_id);
      } else {
        stopError("Error: " + (response.data || "Unknown error"));
      }
    }).fail(function (xhr, status, error) {
      stopError("System Error: " + error);
    });
  }

  function stopError(message) {
    $(".occ-progress-container").hide();
    $btn.prop("disabled", false).text("Clone Site");
    $("#occ-response-area")
      .addClass("occ-notice-error")
      .html("<p>" + message + "</p>")
      .show();
  }

  // IMPORTANT CHANGE 2: Accept 'cloneData' object
  function startPolling(cloneData, serverId) {
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
          app_name: cloneData.app_name, // Use .app_name property
        },
        function (res) {
          if (res.success && res.data.status === "ready") {
            clearInterval(poller);
            // Pass the data forward so we can display the password
            finishClone(cloneData);
          }
        }
      );
    }, 10000);
  }

  // IMPORTANT CHANGE 3: Display Logic
  function finishClone(cloneData) {
    var url = "http://" + cloneData.domain;
    var $msg = $("#occ-response-area");
    var $btn = $("#occ-clone-form").find('button[type="submit"]');
    var $progressBar = $("#occ-progress-bar");

    $progressBar.css("width", "100%");

    setTimeout(function () {
      var successHtml = "<p><strong>Success!</strong> Cloning complete.</p>";

      // CHECK: Do we have new user credentials?
      if (cloneData.new_sys_user && cloneData.new_sys_pass) {
        successHtml +=
          '<div style="background:#fff; border:1px solid #c3c4c7; padding:10px; margin:10px 0; border-radius:4px;">';
        successHtml += "<strong>New System User Created:</strong><br>";
        successHtml += "User: <code>" + cloneData.new_sys_user + "</code><br>";
        successHtml += "Pass: <code>" + cloneData.new_sys_pass + "</code>";
        successHtml +=
          '<p style="margin:5px 0 0 0; font-size:11px; color:#d63638;">Save this password now. It will not be shown again.</p>';
        successHtml += "</div>";
      }

      successHtml +=
        '<p><a href="' +
        url +
        '" target="_blank" class="occ-btn-primary" style="display:inline-block; text-decoration:none; margin-top:10px;">Visit New Site &rarr;</a></p>';

      $msg
        .removeClass("occ-notice-info")
        .addClass("occ-notice-success")
        .html(successHtml);

      $btn.text("Clone Complete").hide();
    }, 600);
  }
});
