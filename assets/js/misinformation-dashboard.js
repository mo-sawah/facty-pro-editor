/**
 * Facty Pro Misinformation Dashboard JavaScript
 * Handles claim loading, filtering, and article generation
 */

(function ($) {
  "use strict";

  let currentFilters = {
    status: "",
    category: "",
    source: "",
  };

  $(document).ready(function () {
    // Load claims on page load
    loadClaims();

    // Refresh button
    $("#refresh-claims").on("click", refreshClaims);

    // Filter changes
    $("#filter-status, #filter-category, #filter-source").on(
      "change",
      function () {
        currentFilters.status = $("#filter-status").val();
        currentFilters.category = $("#filter-category").val();
        currentFilters.source = $("#filter-source").val();
        loadClaims();
      }
    );

    // Auto-refresh every 5 minutes
    setInterval(function () {
      loadClaims(true); // Silent refresh
    }, 300000); // 5 minutes
  });

  /**
   * Load claims from server
   */
  function loadClaims(silent) {
    if (!silent) {
      showLoading();
    }

    $.ajax({
      url: factyProMisinfo.ajaxUrl,
      type: "POST",
      data: {
        action: "facty_pro_get_claims",
        nonce: factyProMisinfo.nonce,
        status: currentFilters.status,
        category: currentFilters.category,
        source: currentFilters.source,
      },
      success: function (response) {
        if (response.success) {
          renderClaims(response.data.claims);
          updateLastUpdateTime();
        } else {
          showError("Failed to load claims: " + response.data);
        }
      },
      error: function () {
        showError("Failed to load claims. Please try again.");
      },
    });
  }

  /**
   * Refresh claims (trigger collection)
   */
  function refreshClaims() {
    const $button = $("#refresh-claims");
    $button.prop("disabled", true).addClass("loading");
    $button.find(".dashicons").addClass("dashicons-update");
    $button.find("span:not(.dashicons)").text("Collecting...");

    $.ajax({
      url: factyProMisinfo.ajaxUrl,
      type: "POST",
      data: {
        action: "facty_pro_refresh_claims",
        nonce: factyProMisinfo.nonce,
      },
      success: function (response) {
        if (response.success) {
          let message = response.data.message;

          // Show debug info if available
          if (response.data.debug) {
            console.log("Collection Debug:", response.data.debug);

            // Add more details to message
            if (response.data.debug.sources) {
              const sources = response.data.debug.sources;
              message += "\n\nSources:\n";
              message += "- Google: " + sources.google + " claims\n";
              message += "- Full Fact: " + sources.full_fact + " claims\n";
              message += "- Perplexity: " + sources.perplexity + " claims";
            }
          }

          showSuccess(message);
          loadClaims();
        } else {
          showError("Failed to refresh: " + response.data);
        }
      },
      error: function (xhr, status, error) {
        console.error("Refresh error:", error);
        console.error("Response:", xhr.responseText);
        showError("Failed to refresh claims. Check console for details.");
      },
      complete: function () {
        $button.prop("disabled", false).removeClass("loading");
        $button.find("span:not(.dashicons)").text("Refresh Now");
      },
    });
  }

  /**
   * Render claims in table
   */
  function renderClaims(claims) {
    const $tbody = $("#claims-tbody");
    $tbody.empty();

    hideLoading();

    if (!claims || claims.length === 0) {
      $("#no-claims-message").show();
      $("#claims-table").hide();
      return;
    }

    $("#no-claims-message").hide();
    $("#claims-table").show();

    claims.forEach(function (claim) {
      const $row = renderClaimRow(claim);
      $tbody.append($row);
    });

    // Attach event handlers
    attachActionHandlers();
  }

  /**
   * Render single claim row
   */
  function renderClaimRow(claim) {
    const claimText = escapeHtml(claim.claim_text);
    const truncatedClaim =
      claimText.length > 200 ? claimText.substring(0, 200) + "..." : claimText;

    const category = claim.category || "uncategorized";
    const source = claim.source || "unknown";
    const rating = claim.rating || "Unknown";
    const status = claim.status || "pending";

    const discoveredDate = new Date(claim.discovered_date);
    const dateStr = discoveredDate.toLocaleDateString("en-GB", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
    const timeStr = discoveredDate.toLocaleTimeString("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
    });

    let sourceDisplay = source
      .replace("_", " ")
      .replace(/\b\w/g, (l) => l.toUpperCase());

    let actions = "";
    if (status === "pending") {
      actions = `
                <button class="btn-generate" data-claim-id="${claim.id}">
                    Generate Article
                </button>
                <button class="btn-dismiss" data-claim-id="${claim.id}">
                    Dismiss
                </button>
            `;
    } else if (status === "article_generated" && claim.post_id) {
      actions = `
                <a href="${getEditUrl(
                  claim.post_id
                )}" class="btn-view" target="_blank">
                    View Draft
                </a>
                <button class="btn-dismiss" data-claim-id="${claim.id}">
                    Dismiss
                </button>
            `;
    } else if (status === "published" && claim.post_id) {
      actions = `
                <a href="${getEditUrl(
                  claim.post_id
                )}" class="btn-view" target="_blank">
                    View Post
                </a>
            `;
    } else if (status === "ignored") {
      actions =
        '<span style="color: #9ca3af; font-size: 12px;">Dismissed</span>';
    }

    return $(`
            <tr data-claim-id="${claim.id}">
                <td class="column-claim">
                    <div class="claim-text">${truncatedClaim}</div>
                    ${
                      claim.source_url
                        ? `<div class="claim-meta"><a href="${escapeHtml(
                            claim.source_url
                          )}" target="_blank" style="color: #3b82f6;">View Source</a></div>`
                        : ""
                    }
                </td>
                <td class="column-category">
                    <span class="category-badge ${category}">${getCategoryName(
      category
    )}</span>
                </td>
                <td class="column-source">
                    <span class="source-badge">${sourceDisplay}</span>
                </td>
                <td class="column-rating">
                    <span class="rating-badge ${getRatingClass(
                      rating
                    )}">${rating}</span>
                </td>
                <td class="column-date">
                    <div style="font-size: 13px; font-weight: 500;">${dateStr}</div>
                    <div style="font-size: 11px; color: #9ca3af;">${timeStr}</div>
                </td>
                <td class="column-status">
                    <span class="status-badge ${status}">${getStatusName(
      status
    )}</span>
                </td>
                <td class="column-actions">
                    <div class="action-buttons">
                        ${actions}
                    </div>
                </td>
            </tr>
        `);
  }

  /**
   * Attach event handlers to action buttons
   */
  function attachActionHandlers() {
    // Generate article
    $(".btn-generate")
      .off("click")
      .on("click", function () {
        const claimId = $(this).data("claim-id");
        generateArticle(claimId, $(this));
      });

    // Dismiss claim
    $(".btn-dismiss")
      .off("click")
      .on("click", function () {
        const claimId = $(this).data("claim-id");
        if (confirm("Are you sure you want to dismiss this claim?")) {
          dismissClaim(claimId);
        }
      });
  }

  /**
   * Generate article from claim
   */
  function generateArticle(claimId, $button) {
    $button.prop("disabled", true).text("Generating...");

    $.ajax({
      url: factyProMisinfo.ajaxUrl,
      type: "POST",
      data: {
        action: "facty_pro_generate_article",
        nonce: factyProMisinfo.nonce,
        claim_id: claimId,
      },
      success: function (response) {
        if (response.success) {
          showSuccess("Article generated successfully!");
          // Reload claims to show updated status
          loadClaims();

          // Optionally open the draft in a new tab
          if (response.data.edit_url) {
            window.open(response.data.edit_url, "_blank");
          }
        } else {
          showError("Failed to generate article: " + response.data);
          $button.prop("disabled", false).text("Generate Article");
        }
      },
      error: function () {
        showError("Failed to generate article. Please try again.");
        $button.prop("disabled", false).text("Generate Article");
      },
    });
  }

  /**
   * Dismiss claim
   */
  function dismissClaim(claimId) {
    $.ajax({
      url: factyProMisinfo.ajaxUrl,
      type: "POST",
      data: {
        action: "facty_pro_dismiss_claim",
        nonce: factyProMisinfo.nonce,
        claim_id: claimId,
      },
      success: function (response) {
        if (response.success) {
          showSuccess("Claim dismissed");
          loadClaims();
        } else {
          showError("Failed to dismiss claim: " + response.data);
        }
      },
      error: function () {
        showError("Failed to dismiss claim. Please try again.");
      },
    });
  }

  /**
   * Helper functions
   */
  function showLoading() {
    $("#loading-spinner").show();
    $("#claims-table").hide();
    $("#no-claims-message").hide();
  }

  function hideLoading() {
    $("#loading-spinner").hide();
  }

  function updateLastUpdateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
    });
    $("#last-update-time").text(timeStr);
  }

  function showSuccess(message) {
    // Simple alert for now - can be replaced with a nicer notification
    alert(message);
  }

  function showError(message) {
    alert("Error: " + message);
  }

  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return String(text).replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  function getEditUrl(postId) {
    return "/wp-admin/post.php?post=" + postId + "&action=edit";
  }

  function getCategoryName(category) {
    const names = {
      health: "Health",
      politics: "Politics",
      economy: "Economy",
      immigration: "Immigration",
      climate: "Climate",
      covid: "COVID-19",
      crime: "Crime",
      international: "International",
      uncategorized: "Other",
    };
    return names[category] || category;
  }

  function getRatingClass(rating) {
    const lower = rating.toLowerCase();
    if (lower.includes("false") || lower.includes("fake")) {
      return "false";
    }
    if (lower.includes("misleading")) {
      return "misleading";
    }
    return "unverified";
  }

  function getStatusName(status) {
    const names = {
      pending: "Pending",
      article_generated: "Draft Created",
      published: "Published",
      ignored: "Dismissed",
    };
    return names[status] || status;
  }
})(jQuery);
