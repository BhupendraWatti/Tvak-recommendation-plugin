/**
 * TVAK Beauty Kit Builder - Interactive Application Engine
 *
 * Manages reactive multi-step quiz state, recommendation REST API requests,
 * dynamic shade customization, and 1-click WooCommerce cart injection.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

(function($) {
  'use strict';

  var TvakApp = {
    currentStep: 1,
    totalSteps: 3,
    profile: {
      skin_type: 'normal',
      skin_tone: 'fair_light',
      skin_concern: []
    },
    recommendationPayload: null,

    swatchColors: {
      'fair_light': '#F6E5D7',
      'light_medium': '#E8CEB8',
      'medium_deep': '#C9A382',
      'deep_rich': '#8D5B3A',
      'very_deep': '#4F301F'
    },

    init: function() {
      var self = this;
      self.$container = $('#tvak-beauty-kit-app');

      if (!self.$container.length) {
        return;
      }

      self.renderLayout();
      self.bindEvents();
    },

    renderLayout: function() {
      var self = this;
      var title = self.$container.data('title') || 'Build Your Personalized Beauty Kit';
      var subtitle = self.$container.data('subtitle') || 'Experience a bespoke digital skin consultation tailored to your unique skin profile.';

      var html = `
        <div class="tvak-header">
          <span class="tvak-header-badge">Digital Aesthetician</span>
          <h2 class="tvak-header-title">${title}</h2>
          <p class="tvak-header-subtitle">${subtitle}</p>
        </div>

        <div class="tvak-progress-bar">
          <div class="tvak-progress-step step-1 active"></div>
          <div class="tvak-progress-step step-2"></div>
          <div class="tvak-progress-step step-3"></div>
        </div>

        <div class="tvak-step-content-area"></div>
      `;

      self.$container.html(html);
      self.renderStep(1);
    },

    renderStep: function(step) {
      var self = this;
      self.currentStep = step;

      // Update progress bar
      self.$container.find('.tvak-progress-step').removeClass('active completed');
      for (var i = 1; i <= step; i++) {
        if (i < step) {
          self.$container.find('.step-' + i).addClass('completed');
        } else if (i === step) {
          self.$container.find('.step-' + i).addClass('active');
        }
      }

      var $area = self.$container.find('.tvak-step-content-area');
      var html = '';

      if (step === 1) {
        html = `
          <div class="tvak-step-card">
            <h3 class="tvak-step-heading">Step 1: What is your primary Skin Type?</h3>
            <div class="tvak-option-grid">
              <div class="tvak-option-card ${self.profile.skin_type === 'dry' ? 'selected' : ''}" data-val="dry">
                <div class="tvak-label">Dry</div>
                <div style="font-size:12px; color:#A0A0A8;">Tightness, flaking or dullness</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_type === 'oily' ? 'selected' : ''}" data-val="oily">
                <div class="tvak-label">Oily</div>
                <div style="font-size:12px; color:#A0A0A8;">Excess shine & enlarged pores</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_type === 'normal' ? 'selected' : ''}" data-val="normal">
                <div class="tvak-label">Normal</div>
                <div style="font-size:12px; color:#A0A0A8;">Well-balanced hydration</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_type === 'combination' ? 'selected' : ''}" data-val="combination">
                <div class="tvak-label">Combination</div>
                <div style="font-size:12px; color:#A0A0A8;">Oily T-zone, normal/dry cheeks</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_type === 'sensitive' ? 'selected' : ''}" data-val="sensitive">
                <div class="tvak-label">Sensitive</div>
                <div style="font-size:12px; color:#A0A0A8;">Easily irritated or red</div>
              </div>
            </div>
            <div class="tvak-actions" style="justify-content: flex-end;">
              <button class="tvak-btn tvak-btn-primary btn-next">Next: Select Skin Tone &rarr;</button>
            </div>
          </div>
        `;
      } else if (step === 2) {
        html = `
          <div class="tvak-step-card">
            <h3 class="tvak-step-heading">Step 2: Select your Skin Tone Group</h3>
            <div class="tvak-option-grid">
              <div class="tvak-option-card ${self.profile.skin_tone === 'fair_light' ? 'selected' : ''}" data-val="fair_light">
                <div class="tvak-swatch" style="background:${self.swatchColors['fair_light']};"></div>
                <div class="tvak-label">Fair / Light</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_tone === 'light_medium' ? 'selected' : ''}" data-val="light_medium">
                <div class="tvak-swatch" style="background:${self.swatchColors['light_medium']};"></div>
                <div class="tvak-label">Light – Medium</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_tone === 'medium_deep' ? 'selected' : ''}" data-val="medium_deep">
                <div class="tvak-swatch" style="background:${self.swatchColors['medium_deep']};"></div>
                <div class="tvak-label">Medium – Deep</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_tone === 'deep_rich' ? 'selected' : ''}" data-val="deep_rich">
                <div class="tvak-swatch" style="background:${self.swatchColors['deep_rich']};"></div>
                <div class="tvak-label">Deep & Rich</div>
              </div>
              <div class="tvak-option-card ${self.profile.skin_tone === 'very_deep' ? 'selected' : ''}" data-val="very_deep">
                <div class="tvak-swatch" style="background:${self.swatchColors['very_deep']};"></div>
                <div class="tvak-label">Very Deep</div>
              </div>
            </div>
            <div class="tvak-actions">
              <button class="tvak-btn tvak-btn-secondary btn-prev">&larr; Back</button>
              <button class="tvak-btn tvak-btn-primary btn-next">Next: Select Concerns &rarr;</button>
            </div>
          </div>
        `;
      } else if (step === 3) {
        var concerns = [
          { key: 'acne', label: 'Acne & Breakouts' },
          { key: 'dry_dehydrated', label: 'Dry & Dehydrated' },
          { key: 'oily_enlarged_pores', label: 'Oily & Enlarged Pores' },
          { key: 'sensitive', label: 'Sensitivity & Redness' },
          { key: 'hyperpigmentation', label: 'Hyperpigmentation & Dark Spots' },
          { key: 'uneven_texture', label: 'Uneven Texture' },
          { key: 'fine_lines_wrinkles', label: 'Fine Lines & Wrinkles' }
        ];

        var gridHtml = '';
        concerns.forEach(function(c) {
          var isSelected = self.profile.skin_concern.indexOf(c.key) !== -1 ? 'selected' : '';
          gridHtml += `
            <div class="tvak-option-card tvak-multi-card ${isSelected}" data-val="${c.key}">
              <div class="tvak-label">${c.label}</div>
            </div>
          `;
        });

        html = `
          <div class="tvak-step-card">
            <h3 class="tvak-step-heading">Step 3: What are your target Skin Concerns?</h3>
            <p style="text-align:center; color:#A0A0A8; margin-top:-15px; margin-bottom:20px;">Select all that apply</p>
            <div class="tvak-option-grid">
              ${gridHtml}
            </div>
            <div class="tvak-actions">
              <button class="tvak-btn tvak-btn-secondary btn-prev">&larr; Back</button>
              <button class="tvak-btn tvak-btn-primary btn-compute">Generate Bespoke Beauty Kit ✨</button>
            </div>
          </div>
        `;
      }

      $area.html(html);
    },

    fetchRecommendation: function() {
      var self = this;
      var $area = self.$container.find('.tvak-step-content-area');

      // Render loading state
      $area.html(`
        <div class="tvak-consultation-loading">
          <div class="tvak-luxury-spinner"></div>
          <h3 class="tvak-step-heading">Formulating Your Bespoke Beauty Kit...</h3>
          <p style="color:#A0A0A8;">Evaluating dermatological weights, shade matrices, and anti-collision guardrails</p>
        </div>
      `);

      $.ajax({
        url: tvak_vars.api_url,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(self.profile),
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tvak_vars.nonce);
        },
        success: function(response) {
          if (response.success) {
            self.recommendationPayload = response;
            self.renderResults(response);
          } else {
            $area.html('<p style="color:#FF6B6B; text-align:center;">Failed to compute recommendations. Please try again.</p>');
          }
        },
        error: function(err) {
          console.error(err);
          $area.html('<p style="color:#FF6B6B; text-align:center;">API Error occurred. Please verify plugin configuration.</p>');
        }
      });
    },

    renderResults: function(data) {
      var self = this;
      var $area = self.$container.find('.tvak-step-content-area');

      // Initialize all items as selected by default
      data.items.forEach(function(item) {
        if (typeof item.selected === 'undefined') {
          item.selected = true;
        }
      });

      var itemsHtml = '';
      data.items.forEach(function(item, idx) {
        var shadeBadge = item.shade_name ? `<div style="font-size:12px; color:#D4AF37; margin-bottom:8px;"><strong>Shade:</strong> ${item.shade_name}</div>` : '';
        var isChecked = item.selected ? 'checked' : '';
        var cardSelectedClass = item.selected ? 'selected-card' : 'unselected-card';

        itemsHtml += `
          <div class="tvak-kit-item-card ${cardSelectedClass}" data-idx="${idx}">
            <div style="display:flex; align-items:flex-start; gap:12px;">
              <div style="padding-top:4px;">
                <input type="checkbox" class="tvak-item-toggle-chk" data-idx="${idx}" ${isChecked} style="width:20px; height:20px; cursor:pointer; accent-color:#D4AF37;" />
              </div>
              <div style="flex:1;">
                <div class="tvak-item-slot-badge">${item.slot_name}</div>
                <h4 class="tvak-item-title" style="margin:5px 0;">${item.title}</h4>
                <span class="tvak-match-score">✦ ${item.score_pct}% Fit Match</span>
                ${shadeBadge}
                <p class="tvak-item-rationale" style="margin-top:8px;">${item.rationale}</p>
              </div>
            </div>
          </div>
        `;
      });

      var selectedCount = data.items.filter(function(i) { return i.selected; }).length;

      var html = `
        <div class="tvak-step-card">
          <div style="text-align:center; margin-bottom:25px;">
            <span class="tvak-header-badge">Kit Ref: ${data.kit_id}</span>
            <h3 class="tvak-step-heading" style="margin-top:10px;">Your Bespoke Personalized Regimen</h3>
            <p style="color:#A0A0A8; font-size:13px; margin-top:-10px;">Select or unselect individual products below to customize your final kit</p>
          </div>

          <div class="tvak-results-grid">
            ${itemsHtml}
          </div>

          <div class="tvak-cart-action-bar" style="margin-top:35px; text-align:center;">
            <button class="tvak-btn tvak-btn-primary btn-add-kit-cart" ${selectedCount === 0 ? 'disabled' : ''} style="padding:16px 40px; font-size:16px;">
              🛍️ Add Selected Items to Bag (<span class="tvak-selected-count">${selectedCount}</span> of ${data.items.length} Products)
            </button>
            <div id="tvak-cart-toast" style="margin-top:15px;"></div>
          </div>
        </div>
      `;

      $area.html(html);
    },

    bindEvents: function() {
      var self = this;

      // Option Card Selection
      self.$container.on('click', '.tvak-option-card', function() {
        var $card = $(this);
        var val = $card.data('val');

        if (self.currentStep === 1) {
          self.profile.skin_type = val;
          self.$container.find('.tvak-option-card').removeClass('selected');
          $card.addClass('selected');
        } else if (self.currentStep === 2) {
          self.profile.skin_tone = val;
          self.$container.find('.tvak-option-card').removeClass('selected');
          $card.addClass('selected');
        } else if (self.currentStep === 3) {
          var idx = self.profile.skin_concern.indexOf(val);
          if (idx === -1) {
            self.profile.skin_concern.push(val);
            $card.addClass('selected');
          } else {
            self.profile.skin_concern.splice(idx, 1);
            $card.removeClass('selected');
          }
        }
      });

      // Checkbox Toggle for Individual Kit Items
      self.$container.on('change', '.tvak-item-toggle-chk', function(e) {
        e.stopPropagation();
        var idx = $(this).data('idx');
        var isChecked = $(this).is(':checked');

        if (self.recommendationPayload && self.recommendationPayload.items[idx]) {
          self.recommendationPayload.items[idx].selected = isChecked;
        }

        var $card = $(this).closest('.tvak-kit-item-card');
        if (isChecked) {
          $card.removeClass('unselected-card').addClass('selected-card');
        } else {
          $card.removeClass('selected-card').addClass('unselected-card');
        }

        var selectedItems = self.recommendationPayload.items.filter(function(i) { return i.selected; });
        var count = selectedItems.length;

        self.$container.find('.tvak-selected-count').text(count);
        var $btn = self.$container.find('.btn-add-kit-cart');
        if (count === 0) {
          $btn.prop('disabled', true).html('🛍️ Select At Least 1 Product to Add to Bag');
        } else {
          $btn.prop('disabled', false).html(`🛍️ Add Selected Items to Bag (<span class="tvak-selected-count">${count}</span> of ${self.recommendationPayload.items.length} Products)`);
        }
      });

      // Navigation Buttons
      self.$container.on('click', '.btn-next', function() {
        self.renderStep(self.currentStep + 1);
      });

      self.$container.on('click', '.btn-prev', function() {
        self.renderStep(self.currentStep - 1);
      });

      // Compute Recommendation
      self.$container.on('click', '.btn-compute', function() {
        self.fetchRecommendation();
      });

      // Add Selected Kit Items to Cart
      self.$container.on('click', '.btn-add-kit-cart', function() {
        self.addKitToCart();
      });
    },

    addKitToCart: function() {
      var self = this;
      var $btn = self.$container.find('.btn-add-kit-cart');
      var $toast = $('#tvak-cart-toast');

      var itemsToAdd = self.recommendationPayload.items.filter(function(item) {
        return item.selected !== false;
      });

      if (itemsToAdd.length === 0) {
        $toast.html('<div class="tvak-toast-notice" style="background:#4A1B1B; border-color:#FF6B6B;">Please select at least one product.</div>');
        return;
      }

      $btn.prop('disabled', true).text('Injecting Selected Products into Bag...');

      $.ajax({
        url: tvak_vars.cart_api,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          kit_id: self.recommendationPayload.kit_id,
          items: itemsToAdd,
          profile: self.profile
        }),
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tvak_vars.nonce);
        },
        success: function(res) {
          if (res.success) {
            $toast.html(`
              <div class="tvak-toast-notice">
                ${res.message}<br />
                <a href="${res.cart_url}" class="button" style="margin-top:10px; display:inline-block; background:#D4AF37; color:#0B0B0C; font-weight:bold; padding:8px 20px; border-radius:20px; text-decoration:none;">View Cart & Checkout &rarr;</a>
              </div>
            `);
          } else {
            $toast.html(`<div class="tvak-toast-notice" style="background:#4A1B1B; border-color:#FF6B6B;">${res.message}</div>`);
          }
          $btn.prop('disabled', false).html(`🛍️ Add Selected Items to Bag (<span class="tvak-selected-count">${itemsToAdd.length}</span> of ${self.recommendationPayload.items.length} Products)`);
        },
        error: function(err) {
          console.error(err);
          $toast.html('<div class="tvak-toast-notice" style="background:#4A1B1B; border-color:#FF6B6B;">Error adding kit to cart.</div>');
          $btn.prop('disabled', false).html(`🛍️ Add Selected Items to Bag (<span class="tvak-selected-count">${itemsToAdd.length}</span> of ${self.recommendationPayload.items.length} Products)`);
        }
      });
    }
  };

  $(document).ready(function() {
    TvakApp.init();
  });

})(jQuery);
