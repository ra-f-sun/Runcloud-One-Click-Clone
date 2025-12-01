jQuery(document).ready(function ($) {
  // Cache Elements
  var $form = $("#occ-clone-form");
  var $btn = $form.find('button[type="submit"]');
  var $nameInput = $("#app_name");
  var $subInput = $("#app_subdomain");

  // DB Elements
  var $dbName = $("#db_name");
  var $dbUser = $("#db_user");
  var dbUserManuallyEdited = false;
  var dbNameManuallyEdited = false;

  // Lists (Injected from PHP)
  var unavailable = window.occ_unavailable_names || {
    apps: [],
    dbs: [],
    db_users: [],
  };

  // --- Tab Logic ---
  window.openTab = function (id) {
    $(".occ-tab-content").removeClass("active");
    $("#" + id).addClass("active");
    $(".occ-tab-btn").removeClass("active");
    // Check if event exists (clicked) before accessing classList
    if (event && event.target) {
      event.target.classList.add("active");
    }
  };

  // --- Init (Silent Mode: Check button state but don't show red errors) ---
  validateAll(true);

  // 1. App Name Listener (Auto-Fill Master)
  $nameInput.on("input", function () {
    var val = $(this).val();

    // Sanitize for Subdomain
    var cleanSlug = val
      .toLowerCase()
      .replace(/[^a-z0-9-]/g, "")
      .replace(/-+/g, "-");
    $subInput.val(cleanSlug);

    // Sanitize for DB (Strict alphanumeric, underscores allowed)
    var dbSlug = cleanSlug.replace(/-/g, "_");

    // Auto-fill DB Name (if not edited manually)
    if (!dbNameManuallyEdited) {
      $dbName.val(dbSlug.substring(0, 16));
    }

    // Auto-fill DB User (if not edited manually)
    if (!dbUserManuallyEdited) {
      $dbUser.val(dbSlug.substring(0, 10));
    }

    validateAll();
  });

  // 2. DB Input Listeners (Manual Edit Flags)
  $dbName.on("input", function () {
    dbNameManuallyEdited = true;
    validateAll();
  });
  $dbUser.on("input", function () {
    dbUserManuallyEdited = true;
    validateAll();
  });

  // 3. Validation Logic
  $form.find("input, select").on("input blur change", function () {
    validateAll();
  });

  function validateAll(silent = false) {
    var isValid = true;
    var nameValid = true,
      dbNameValid = true,
      dbUserValid = true;

    // A. App Name
    if (checkConflict($nameInput.val(), unavailable.apps)) {
      if (!silent)
        showInlineError($nameInput, "This App Name is already taken.");
      nameValid = false;
    } else if (
      /[^a-z0-9-]/i.test($nameInput.val()) ||
      $nameInput.val().length < 3
    ) {
      if (!silent && $nameInput.val().length > 0)
        showInlineError($nameInput, "Min 3 chars. Alphanumeric & hyphens.");
      nameValid = false;
    } else {
      clearInlineError($nameInput);
    }

    // B. DB Name
    var fullDbName = $dbName.val() + "_db";
    if (checkConflict(fullDbName, unavailable.dbs)) {
      if (!silent)
        showInlineError($dbName, 'DB Name "' + fullDbName + '" is taken.');
      dbNameValid = false;
    } else if (/[^a-z0-9_]/.test($dbName.val())) {
      if (!silent)
        showInlineError(
          $dbName,
          "Lowercase letters, numbers, underscore only."
        );
      dbNameValid = false;
    } else {
      clearInlineError($dbName);
    }

    // C. DB User
    var fullDbUser = $dbUser.val() + "_u";
    if (checkConflict(fullDbUser, unavailable.db_users)) {
      if (!silent)
        showInlineError($dbUser, 'DB User "' + fullDbUser + '" is taken.');
      dbUserValid = false;
    } else if (/[^a-z0-9_]/.test($dbUser.val())) {
      if (!silent)
        showInlineError(
          $dbUser,
          "Lowercase letters, numbers, underscore only."
        );
      dbUserValid = false;
    } else {
      clearInlineError($dbUser);
    }

    isValid = nameValid && dbNameValid && dbUserValid;

    // D. Toggle Button
    if (isValid) {
      $btn.prop("disabled", false).removeClass("occ-btn-disabled");
    } else {
      $btn.prop("disabled", true).addClass("occ-btn-disabled");
    }
  }

  function checkConflict(value, list) {
    if (!list || !value) return false;
    return list.includes(value);
  }

  // FIX: Updated logic to find existing error message correctly
  function showInlineError($el, message) {
    $el.addClass("occ-input-error");

    var $msg;
    var $parentGroup = $el.closest(".occ-input-group");

    // Identify where the error message should be (or is)
    if ($parentGroup.length > 0) {
      // If in group, error is after the group div
      $msg = $parentGroup.next(".occ-error-text");
    } else {
      // If standalone, error is immediate sibling
      $msg = $el.next(".occ-error-text");
    }

    if ($msg.length === 0) {
      var errorHtml = '<span class="occ-error-text">' + message + "</span>";
      if ($parentGroup.length > 0) {
        $parentGroup.after(errorHtml);
      } else {
        $el.after(errorHtml);
      }
    } else {
      // Update text of existing message instead of adding new one
      $msg.text(message);
    }
  }

  function clearInlineError($el) {
    $el.removeClass("occ-input-error");

    var $parentGroup = $el.closest(".occ-input-group");
    var $msg;

    if ($parentGroup.length > 0) {
      $msg = $parentGroup.next(".occ-error-text");
    } else {
      $msg = $el.next(".occ-error-text");
    }

    if ($msg.length > 0) {
      $msg.remove();
    }
  }

  // 4. Submit Handler (Open Modal)
  $form.on("submit", function (e) {
    e.preventDefault();
    if ($btn.hasClass("occ-btn-disabled")) return;
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
      // Manual DB Inputs
      db_name_custom: $dbName.val(),
      db_user_custom: $dbUser.val(),
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
          app_name: cloneData.app_name,
        },
        function (res) {
          if (res.success && res.data.status === "ready") {
            clearInterval(poller);
            finishClone(cloneData);
          }
        }
      );
    }, 10000);
  }

  function finishClone(cloneData) {
    var url = "http://" + cloneData.domain;
    var $msg = $("#occ-response-area");
    var $btn = $("#occ-clone-form").find('button[type="submit"]');
    var $progressBar = $("#occ-progress-bar");

    $progressBar.css("width", "100%");

    setTimeout(function () {
      var successHtml = "<p><strong>Success!</strong> Cloning complete.</p>";

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
