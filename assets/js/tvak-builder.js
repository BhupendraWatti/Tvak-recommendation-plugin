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

    escapeHtml: function(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    renderResults: function(data) {
      var self = this;
      var $area = self.$container.find('.tvak-step-content-area');

      data.items.forEach(function(item) {
        if (typeof item.selected === 'undefined') {
          item.selected = (item.is_in_stock !== false);
        }
      });

      var itemsHtml = '';
      data.items.forEach(function(item, idx) {
        var isChecked = item.selected ? 'checked' : '';
        var cardSelectedClass = item.selected ? 'selected-card' : 'unselected-card';
        var stockClass = item.is_in_stock === false ? 'out-of-stock-card' : '';

        // Product Image HTML
        var imgUrl = item.image_url || 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMTYxNjFBIi8+PC9zdmc+';
        var imgHtml = `<div class="tvak-item-img-wrap"><img src="${imgUrl}" class="tvak-item-img" alt="${self.escapeHtml(item.title)}" /></div>`;

        // Shade Swatches HTML
        var swatchesHtml = '';
        var hasShadesArray = Array.isArray(item.all_shades) && item.all_shades.length > 0;
        var renderShades = (typeof item.has_shades !== 'undefined') ? item.has_shades : hasShadesArray;

        if (hasShadesArray) {
          var swatchItemsHtml = '';
          item.all_shades.forEach(function(sh, sIdx) {
            var isActive = (sh.variation_id == item.variation_id || sh.shade_name === item.shade_name);
            var activeClass = isActive ? 'active' : '';
            var disabledClass = !sh.is_in_stock ? 'out-of-stock-swatch' : '';
            var hex = sh.hex_color || '#D4AF37';
            var safeShadeName = self.escapeHtml(sh.shade_name);

            swatchItemsHtml += `
              <div class="tvak-shade-swatch ${activeClass} ${disabledClass}" 
                   data-item-idx="${idx}" 
                   data-shade-idx="${sIdx}"
                   title="${safeShadeName} ${!sh.is_in_stock ? '(Out of Stock)' : ''}">
                <span class="tvak-swatch-circle" style="background-color: ${hex};"></span>
                ${!sh.is_in_stock ? '<span class="tvak-swatch-slash"></span>' : ''}
              </div>
            `;
          });

          swatchesHtml = `
            <div class="tvak-shades-picker-area">
              <div class="tvak-shade-picker-label">
                <strong>Shade:</strong> <span class="tvak-current-shade-name">${self.escapeHtml(item.shade_name)}</span>
              </div>
              <div class="tvak-swatch-list">
                ${swatchItemsHtml}
              </div>
            </div>
          `;
        } else if (renderShades && item.shade_name) {
          var hexColor = item.shade_hex || '#D4AF37';
          swatchesHtml = `
            <div class="tvak-shades-picker-area">
              <div class="tvak-shade-picker-label" style="display:flex; align-items:center; gap:8px;">
                <strong>Shade:</strong> 
                <span class="tvak-swatch-circle inline-swatch" style="background-color: ${hexColor};"></span>
                <span class="tvak-current-shade-name">${self.escapeHtml(item.shade_name)}</span>
              </div>
            </div>
          `;
        }

        // Price Badge HTML
        var priceFormatted = item.price_formatted || ('$' + (item.price || 49.00).toFixed(2));

        itemsHtml += `
          <div class="tvak-kit-item-card ${cardSelectedClass} ${stockClass}" data-idx="${idx}">
            <div class="tvak-card-body">
              <div class="tvak-chk-column">
                <input type="checkbox" class="tvak-item-toggle-chk" data-idx="${idx}" ${isChecked} ${item.is_in_stock === false ? 'disabled' : ''} />
              </div>
              ${imgHtml}
              <div class="tvak-details-column">
                <div class="tvak-item-header">
                  <span class="tvak-item-slot-badge">${self.escapeHtml(item.slot_name)}</span>
                  <span class="tvak-item-price-tag">${priceFormatted}</span>
                </div>
                <h4 class="tvak-item-title">${self.escapeHtml(item.title)}</h4>
                <div class="tvak-match-score">✦ ${item.score_pct}% Fit Match</div>
                ${swatchesHtml}
                <p class="tvak-item-rationale">${self.escapeHtml(item.rationale)}</p>
                ${item.is_in_stock === false ? '<div class="tvak-stock-warning">⚠️ Currently Out of Stock</div>' : ''}
              </div>
            </div>
          </div>
        `;
      });

      var html = `
        <div class="tvak-step-card">
          <div style="text-align:center; margin-bottom:25px;">
            <span class="tvak-header-badge">Kit Ref: ${self.escapeHtml(data.kit_id)}</span>
            <h3 class="tvak-step-heading" style="margin-top:10px;">Your Bespoke Personalized Regimen</h3>
            <p style="color:#A0A0A8; font-size:13px; margin-top:-10px;">Customize your shades and select individual items for your personalized luxury kit</p>
          </div>

          <div class="tvak-results-grid">
            ${itemsHtml}
          </div>

          <div class="tvak-cart-action-bar">
            <div class="tvak-summary-pricing-panel">
              <div class="tvak-price-breakdown">
                <span class="tvak-total-label">Subtotal:</span>
                <span class="tvak-raw-subtotal-val">$0.00</span>
                <span class="tvak-discount-badge" style="display:none;"></span>
              </div>
              <div class="tvak-final-total-line">
                <span class="tvak-total-heading">Total Kit Price:</span>
                <span class="tvak-final-total-val">$0.00</span>
              </div>
            </div>

            <button class="tvak-btn tvak-btn-primary btn-add-kit-cart" style="padding:16px 40px; font-size:16px;">
              🛍️ Add Selected Items to Bag (<span class="tvak-selected-count">0</span> Products)
            </button>
            <div id="tvak-cart-toast" style="margin-top:15px;"></div>
          </div>
        </div>
      `;

      $area.html(html);
      self.updateSummaryBar();
    },

    updateSummaryBar: function() {
      var self = this;
      if (!self.recommendationPayload || !self.recommendationPayload.items) {
        return;
      }

      var items = self.recommendationPayload.items;
      var selectedItems = items.filter(function(i) { return i.selected && i.is_in_stock !== false; });
      var count = selectedItems.length;

      var subtotal = 0;
      selectedItems.forEach(function(item) {
        subtotal += parseFloat(item.price || 49.00);
      });

      // ── Dynamic Tiered Bundle Discount ──────────────────────────────────────
      // Tiers are configured from WP Admin (TVAK Engine → Bundle Discount).
      // The API response carries them in discount_thresholds so JS never needs
      // to hardcode values again.
      var discountPct = 0;
      var thresholds = self.recommendationPayload.discount_thresholds || null;

      if (thresholds) {
        // New structured format: { tier_1: {min_items, pct}, tier_2: …, tier_3: … }
        var tiers = [];
        Object.keys(thresholds).forEach(function(key) {
          var t = thresholds[key];
          if (t && typeof t.min_items !== 'undefined' && typeof t.pct !== 'undefined') {
            tiers.push({ min: parseInt(t.min_items, 10), pct: parseInt(t.pct, 10) });
          }
        });
        // Sort tiers descending by minimum item count so highest discount wins first
        tiers.sort(function(a, b) { return b.min - a.min; });
        for (var ti = 0; ti < tiers.length; ti++) {
          if (count >= tiers[ti].min) {
            discountPct = tiers[ti].pct;
            break;
          }
        }
      } else {
        // Legacy fallback (in case response format is still old flat keys)
        if (count >= 5) { discountPct = 20; }
        else if (count >= 3) { discountPct = 15; }
        else if (count >= 2) { discountPct = 10; }
      }
      // ────────────────────────────────────────────────────────────────────────

      var discountAmount = (subtotal * discountPct) / 100;
      var finalTotal = subtotal - discountAmount;

      var currencySymbol = (tvak_vars && tvak_vars.currency_symbol) ? tvak_vars.currency_symbol : '₹';
      var fmtSubtotal = currencySymbol + subtotal.toFixed(2);
      var fmtFinal = currencySymbol + finalTotal.toFixed(2);

      self.$container.find('.tvak-selected-count').text(count);
      self.$container.find('.tvak-raw-subtotal-val').text(fmtSubtotal);

      var $discountBadge = self.$container.find('.tvak-discount-badge');
      if (discountPct > 0) {
        $discountBadge.text(`✦ ${discountPct}% Kit Discount Applied (-${currencySymbol}${discountAmount.toFixed(2)})`).show();
        self.$container.find('.tvak-raw-subtotal-val').css('text-decoration', 'line-through');
      } else {
        $discountBadge.hide();
        self.$container.find('.tvak-raw-subtotal-val').css('text-decoration', 'none');
      }

      self.$container.find('.tvak-final-total-val').text(fmtFinal);

      var $btn = self.$container.find('.btn-add-kit-cart');
      if (count === 0) {
        $btn.prop('disabled', true).html('🛍️ Select At Least 1 Product to Add to Bag');
      } else {
        $btn.prop('disabled', false).html(`🛍️ Add Selected Items to Bag (${count} of ${items.length} Products — ${fmtFinal})`);
      }
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

        self.updateSummaryBar();
      });

      // Shade Swatch Selection Click Handler (Refactored to JS Memory Lookup to prevent HTML Attribute explosions)
      self.$container.on('click', '.tvak-shade-swatch', function(e) {
        e.stopPropagation();
        var $swatch = $(this);

        if ($swatch.hasClass('out-of-stock-swatch')) {
          alert('This shade is currently out of stock.');
          return;
        }

        var itemIdx = parseInt($swatch.data('item-idx'), 10);
        var shadeIdx = parseInt($swatch.data('shade-idx'), 10);

        if (self.recommendationPayload && self.recommendationPayload.items[itemIdx]) {
          var item = self.recommendationPayload.items[itemIdx];
          var shade = (Array.isArray(item.all_shades) && item.all_shades[shadeIdx]) ? item.all_shades[shadeIdx] : null;

          if (shade) {
            item.variation_id = shade.variation_id;
            item.shade_name = shade.shade_name;
            item.shade_hex = shade.hex_color;
            if (typeof shade.price !== 'undefined' && shade.price !== null) {
              item.price = parseFloat(shade.price);
              if (shade.price_formatted) {
                item.price_formatted = shade.price_formatted;
              } else if (tvak_vars && tvak_vars.currency_symbol) {
                item.price_formatted = tvak_vars.currency_symbol + item.price.toFixed(2);
              }
            }
            if (shade.image_url) {
              item.image_url = shade.image_url;
            }

            // Update Card Visuals Live
            var $card = $swatch.closest('.tvak-kit-item-card');
            $card.find('.tvak-shade-swatch').removeClass('active');
            $swatch.addClass('active');
            $card.find('.tvak-current-shade-name').text(shade.shade_name);
            if (item.price_formatted) {
              $card.find('.tvak-item-price-tag').html(item.price_formatted);
            }
            if (shade.image_url) {
              $card.find('.tvak-item-img').attr('src', shade.image_url);
            }

            self.updateSummaryBar();
          }
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
        return item.selected && item.is_in_stock !== false;
      });

      if (itemsToAdd.length === 0) {
        $toast.html('<div class="tvak-toast-notice" style="background:#4A1B1B; border-color:#FF6B6B; padding:12px; border-radius:6px;">Please select at least one available product to add to bag.</div>');
        return;
      }

      $btn.prop('disabled', true).text('Adding Selected Products to Bag...');

      var cartEndpoint = (tvak_vars && tvak_vars.cart_api) ? tvak_vars.cart_api : '/wp-json/tvak/v1/cart/add-kit';

      $.ajax({
        url: cartEndpoint,
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
          if (res && res.success) {
            $toast.html(`
              <div class="tvak-toast-notice" style="background:#1B3B2B; border:1px solid #4AB866; color:#E7F5EA; padding:15px; border-radius:8px; margin-top:15px;">
                <strong>${res.message}</strong><br />
                <div style="margin-top:10px;">
                  <a href="${res.cart_url}" class="button" style="display:inline-block; background:#D4AF37; color:#0B0B0C; font-weight:bold; padding:8px 20px; border-radius:20px; text-decoration:none; margin-right:10px;">View Cart & Checkout &rarr;</a>
                </div>
              </div>
            `);

            // Trigger WooCommerce AJAX cart fragment refresh
            if (typeof $(document.body).trigger === 'function') {
              $(document.body).trigger('wc_fragment_refresh');
              $(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash]);
            }
          } else {
            var msg = (res && res.message) ? res.message : 'Error adding selected items to cart.';
            $toast.html(`<div class="tvak-toast-notice" style="background:#4A1B1B; border:1px solid #FF6B6B; color:#FFD1D1; padding:12px; border-radius:6px;">${msg}</div>`);
          }
          self.updateSummaryBar();
        },
        error: function(err) {
          console.error('TVAK Add to Cart Error:', err);
          $toast.html('<div class="tvak-toast-notice" style="background:#4A1B1B; border:1px solid #FF6B6B; color:#FFD1D1; padding:12px; border-radius:6px;">Failed to process bag request. Please try again.</div>');
          self.updateSummaryBar();
        }
      });
    }
  };

  $(document).ready(function() {
    TvakApp.init();
  });

})(jQuery);
