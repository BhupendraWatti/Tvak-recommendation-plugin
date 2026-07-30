/**
 * TVAK Beauty Kit Builder - Interactive Application Engine
 *
 * Manages reactive multi-step quiz state dynamically fetched from REST API,
 * recommendation engine evaluation requests, dynamic shade customization,
 * and 1-click WooCommerce cart injection.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.1.0
 */

(function($) {
  'use strict';

  var TvakApp = {
    currentStep: 1,
    totalSteps: 3,
    quizConfig: null,
    profile: {},
    recommendationPayload: null,

    init: function() {
      var self = this;
      self.$container = $('#tvak-beauty-kit-app');

      if (!self.$container.length) {
        return;
      }

      self.renderLoadingState();
      self.fetchQuizConfig();
      self.bindEvents();
    },

    renderLoadingState: function() {
      var self = this;
      self.$container.html(`
        <div class="tvak-consultation-loading" style="padding:60px 20px; text-align:center;">
          <div class="tvak-luxury-spinner"></div>
          <h3 class="tvak-step-heading">Initializing Digital Consultation Engine...</h3>
          <p style="color:#A0A0A8;">Loading active dermatological master data and quiz configuration</p>
        </div>
      `);
    },

    fetchQuizConfig: function() {
      var self = this;
      var configUrl = (tvak_vars && tvak_vars.api_url) 
        ? tvak_vars.api_url.replace('/recommend', '/quiz-config')
        : '/wp-json/tvak/v1/quiz-config';

      $.ajax({
        url: configUrl,
        type: 'GET',
        success: function(res) {
          if (res && res.success && res.quiz_config && res.quiz_config.steps.length > 0) {
            self.quizConfig = res.quiz_config;
            self.totalSteps = res.quiz_config.total_steps;

            // Initialize default profile state from master attributes
            res.quiz_config.steps.forEach(function(s) {
              if (s.input_type === 'multi_select') {
                self.profile[s.attribute_code] = [];
              } else {
                self.profile[s.attribute_code] = (s.terms.length > 0) ? s.terms[0].term_slug : '';
              }
            });

            self.renderLayout();
          } else {
            self.fallbackInit();
          }
        },
        error: function(err) {
          console.warn('REST quiz-config fetch error, running fallback init:', err);
          self.fallbackInit();
        }
      });
    },

    fallbackInit: function() {
      var self = this;
      // Legacy fallback default quiz configuration
      self.quizConfig = {
        total_steps: 3,
        steps: [
          {
            step: 1,
            attribute_code: 'skin_type',
            heading: 'Step 1: What is your primary Skin Type?',
            subheading: 'Select your primary skin type',
            input_type: 'single_select',
            terms: [
              { term_slug: 'dry', label: 'Dry', description: 'Tightness, flaking or dullness' },
              { term_slug: 'oily', label: 'Oily', description: 'Excess shine & enlarged pores' },
              { term_slug: 'normal', label: 'Normal', description: 'Well-balanced hydration' },
              { term_slug: 'combination', label: 'Combination', description: 'Oily T-zone, normal/dry cheeks' },
              { term_slug: 'sensitive', label: 'Sensitive', description: 'Easily irritated or red' }
            ]
          },
          {
            step: 2,
            attribute_code: 'skin_tone',
            heading: 'Step 2: Select your Skin Tone Group',
            subheading: 'Select your skin tone group',
            input_type: 'single_select',
            terms: [
              { term_slug: 'fair_light', label: 'Fair / Light', swatch_color: '#F6E5D7' },
              { term_slug: 'light_medium', label: 'Light – Medium', swatch_color: '#E8CEB8' },
              { term_slug: 'medium_deep', label: 'Medium – Deep', swatch_color: '#C9A382' },
              { term_slug: 'deep_rich', label: 'Deep & Rich', swatch_color: '#8D5B3A' },
              { term_slug: 'very_deep', label: 'Very Deep', swatch_color: '#4F301F' }
            ]
          },
          {
            step: 3,
            attribute_code: 'skin_concern',
            heading: 'Step 3: What are your target Skin Concerns?',
            subheading: 'Select all that apply',
            input_type: 'multi_select',
            terms: [
              { term_slug: 'acne', label: 'Acne & Breakouts' },
              { term_slug: 'dry_dehydrated', label: 'Dry & Dehydrated' },
              { term_slug: 'oily_enlarged_pores', label: 'Oily & Enlarged Pores' },
              { term_slug: 'sensitive', label: 'Sensitivity & Redness' },
              { term_slug: 'hyperpigmentation', label: 'Hyperpigmentation & Dark Spots' },
              { term_slug: 'uneven_texture', label: 'Uneven Texture' },
              { term_slug: 'fine_lines_wrinkles', label: 'Fine Lines & Wrinkles' }
            ]
          }
        ]
      };

      self.totalSteps = 3;
      self.profile = { skin_type: 'normal', skin_tone: 'fair_light', skin_concern: [] };
      self.renderLayout();
    },

    renderLayout: function() {
      var self = this;
      var title = self.$container.data('title') || 'Build Your Personalized Beauty Kit';
      var subtitle = self.$container.data('subtitle') || 'Experience a bespoke digital skin consultation tailored to your unique skin profile.';

      var progressHtml = '';
      for (var i = 1; i <= self.totalSteps; i++) {
        progressHtml += `<div class="tvak-progress-step step-${i}"></div>`;
      }

      var html = `
        <div class="tvak-header">
          <span class="tvak-header-badge">Digital Aesthetician</span>
          <h2 class="tvak-header-title">${title}</h2>
          <p class="tvak-header-subtitle">${subtitle}</p>
        </div>

        <div class="tvak-progress-bar">
          ${progressHtml}
        </div>

        <div class="tvak-step-content-area"></div>
      `;

      self.$container.html(html);
      self.renderStep(1);
    },

    renderStep: function(step) {
      var self = this;
      self.currentStep = step;

      // Update progress bar indicator
      self.$container.find('.tvak-progress-step').removeClass('active completed');
      for (var i = 1; i <= self.totalSteps; i++) {
        if (i < step) {
          self.$container.find('.step-' + i).addClass('completed');
        } else if (i === step) {
          self.$container.find('.step-' + i).addClass('active');
        }
      }

      var $area = self.$container.find('.tvak-step-content-area');
      var stepDef = self.quizConfig.steps[step - 1];

      if (!stepDef) {
        return;
      }

      var attrCode = stepDef.attribute_code;
      var inputType = stepDef.input_type;
      var terms = stepDef.terms || [];

      var gridHtml = '';
      terms.forEach(function(term) {
        var slug = term.term_slug;
        var isSelected = false;

        if (inputType === 'multi_select') {
          isSelected = Array.isArray(self.profile[attrCode]) && self.profile[attrCode].indexOf(slug) !== -1;
        } else {
          isSelected = self.profile[attrCode] === slug;
        }

        var selectedClass = isSelected ? 'selected' : '';
        var swatchHtml = term.swatch_color ? `<div class="tvak-swatch" style="background:${term.swatch_color};"></div>` : '';
        var descHtml = term.description ? `<div style="font-size:12px; color:#A0A0A8; margin-top:4px;">${term.description}</div>` : '';
        var cardTypeClass = inputType === 'multi_select' ? 'tvak-multi-card' : '';

        gridHtml += `
          <div class="tvak-option-card ${cardTypeClass} ${selectedClass}" data-attr="${attrCode}" data-val="${slug}" data-type="${inputType}">
            ${swatchHtml}
            <div class="tvak-label">${term.label}</div>
            ${descHtml}
          </div>
        `;
      });

      var actionBtnsHtml = '';
      if (step === 1) {
        actionBtnsHtml = `
          <div class="tvak-actions" style="justify-content: flex-end;">
            <button class="tvak-btn tvak-btn-primary btn-next">Next Step &rarr;</button>
          </div>
        `;
      } else if (step < self.totalSteps) {
        actionBtnsHtml = `
          <div class="tvak-actions">
            <button class="tvak-btn tvak-btn-secondary btn-prev">&larr; Back</button>
            <button class="tvak-btn tvak-btn-primary btn-next">Next Step &rarr;</button>
          </div>
        `;
      } else {
        actionBtnsHtml = `
          <div class="tvak-actions">
            <button class="tvak-btn tvak-btn-secondary btn-prev">&larr; Back</button>
            <button class="tvak-btn tvak-btn-primary btn-compute">Generate Bespoke Beauty Kit ✨</button>
          </div>
        `;
      }

      var subtext = stepDef.subheading ? `<p style="text-align:center; color:#A0A0A8; margin-top:-15px; margin-bottom:20px;">${stepDef.subheading}</p>` : '';

      var html = `
        <div class="tvak-step-card">
          <h3 class="tvak-step-heading">${stepDef.heading}</h3>
          ${subtext}
          <div class="tvak-option-grid">
            ${gridHtml}
          </div>
          ${actionBtnsHtml}
        </div>
      `;

      $area.html(html);
    },

    fetchRecommendation: function() {
      var self = this;
      var $area = self.$container.find('.tvak-step-content-area');

      // Loading spinner state
      $area.html(`
        <div class="tvak-consultation-loading">
          <div class="tvak-luxury-spinner"></div>
          <h3 class="tvak-step-heading">Formulating Your Bespoke Beauty Kit...</h3>
          <p style="color:#A0A0A8;">Evaluating dynamic master data weights, shade matrices, and anti-collision guardrails</p>
        </div>
      `);

      $.ajax({
        url: tvak_vars.api_url,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(self.profile),
        beforeSend: function(xhr) {
          if (tvak_vars && tvak_vars.nonce) {
            xhr.setRequestHeader('X-WP-Nonce', tvak_vars.nonce);
          }
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

      // Dynamic Option Card Selection Handler
      self.$container.on('click', '.tvak-option-card', function() {
        var $card = $(this);
        var attr = $card.data('attr');
        var val = $card.data('val');
        var type = $card.data('type');

        if (type === 'multi_select') {
          if (!Array.isArray(self.profile[attr])) {
            self.profile[attr] = [];
          }
          var idx = self.profile[attr].indexOf(val);
          if (idx === -1) {
            self.profile[attr].push(val);
            $card.addClass('selected');
          } else {
            self.profile[attr].splice(idx, 1);
            $card.removeClass('selected');
          }
        } else {
          self.profile[attr] = val;
          self.$container.find(`.tvak-option-card[data-attr="${attr}"]`).removeClass('selected');
          $card.addClass('selected');
        }
      });

      // Item Checkbox Toggle
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

      // Step Navigation Buttons
      self.$container.on('click', '.btn-next', function() {
        if (self.currentStep < self.totalSteps) {
          self.renderStep(self.currentStep + 1);
        }
      });

      self.$container.on('click', '.btn-prev', function() {
        if (self.currentStep > 1) {
          self.renderStep(self.currentStep - 1);
        }
      });

      // Compute Recommendation Trigger
      self.$container.on('click', '.btn-compute', function() {
        self.fetchRecommendation();
      });

      // Add Kit to Cart Action
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
          if (tvak_vars && tvak_vars.nonce) {
            xhr.setRequestHeader('X-WP-Nonce', tvak_vars.nonce);
          }
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
