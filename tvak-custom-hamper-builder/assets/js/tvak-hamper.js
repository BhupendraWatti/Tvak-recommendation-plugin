/**
 * TVAK Custom Hamper Builder.
 *
 * Product-page builder for designated hamper shell products only.
 */
(function($) {
  'use strict';

  var TvakHamper = {
    hamper: null,
    state: {},

    init: function() {
      this.$root = $('#tvak-hamper-builder');
      if (!this.$root.length || typeof tvak_hamper_vars === 'undefined') {
        return;
      }

      this.moveIntoProductSummary();
      this.fetchConfig();
      this.bindEvents();
    },

    moveIntoProductSummary: function() {
      var $summary = $('.elementor-element-7ab38a5 .wdt-product-summary').first();
      if (!$summary.length) {
        $summary = $('.summary.entry-summary').filter(function() {
          return $(this).find('.price, .woocommerce-product-details__short-description').length;
        }).first();
      }

      if (!$summary.length || $.contains($summary[0], this.$root[0])) {
        return;
      }

      var $after = $summary.find('.woocommerce-product-details__short-description').last();
      this.$root.addClass('is-inside-summary');
      if ($after.length) {
        this.$root.detach().insertAfter($after);
      } else {
        this.$root.detach().appendTo($summary);
      }
    },

    fetchConfig: function() {
      var self = this;
      $.ajax({
        url: tvak_hamper_vars.config_api,
        type: 'GET',
        success: function(res) {
          if (res && res.success && res.hamper) {
            self.hamper = res.hamper;
            self.initializeState();
            self.render();
            return;
          }
          self.renderError('This hamper is not available.');
        },
        error: function(xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'This hamper is not available.';
          self.renderError(message);
        }
      });
    },

    initializeState: function() {
      var self = this;
      self.state = {};
      self.hamper.items.forEach(function(item, idx) {
        var selected = Boolean(item.is_required || item.is_preselected);
        var variation = self.getFirstAvailableVariation(item);

        if (item.is_optional && !self.hamper.allow_optional_items) {
          selected = false;
        }

        self.state[idx] = {
          selected: selected,
          quantity: selected ? Math.max(1, parseInt(item.default_quantity || 1, 10)) : 0,
          variationId: selected && variation ? parseInt(variation.variation_id, 10) : 0,
          attributes: selected && variation ? (variation.attributes || {}) : {}
        };
      });
    },

    bindEvents: function() {
      var self = this;

      $(document).on('click', '.tvak-hamper-product-action', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('item-idx'), 10);
        var item = self.hamper.items[idx];
        var state = self.state[idx];
        if (!item || !state || item.is_required) {
          return;
        }

        if (state.selected) {
          state.selected = false;
          state.quantity = 0;
          state.variationId = 0;
          state.attributes = {};
        } else {
          state.selected = true;
          state.quantity = Math.max(1, parseInt(item.default_quantity || 1, 10));
          var variation = self.getFirstAvailableVariation(item);
          state.variationId = variation ? parseInt(variation.variation_id, 10) : 0;
          state.attributes = variation ? (variation.attributes || {}) : {};
          self.enforceMax(idx);
        }
        self.render();
      });

      $(document).on('click', '.tvak-hamper-qty-button', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('item-idx'), 10);
        var delta = parseInt($(this).data('delta'), 10);
        self.adjustQuantity(idx, delta);
      });

      $(document).on('click', '.tvak-hamper-shade-button', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).data('item-idx'), 10);
        var variationIdx = parseInt($(this).data('variation-idx'), 10);
        var variation = self.hamper.items[idx] && self.hamper.items[idx].variations[variationIdx];
        if (!variation || !self.state[idx] || !self.state[idx].selected) {
          return;
        }

        self.state[idx].variationId = parseInt(variation.variation_id, 10);
        self.state[idx].attributes = variation.attributes || {};
        self.render();
      });

      $(document).on('click', '.tvak-hamper-submit', function(e) {
        e.preventDefault();
        var cartUrl = $(this).data('cart-url');
        if ($(this).hasClass('is-added') && cartUrl) {
          window.location.href = cartUrl;
          return;
        }
        self.submit();
      });
    },

    adjustQuantity: function(idx, delta) {
      var item = this.hamper.items[idx];
      var state = this.state[idx];
      if (!item || !state) {
        return;
      }

      var nextQty = Math.max(0, parseInt(state.quantity || 0, 10) + delta);
      if (item.is_required) {
        nextQty = Math.max(1, nextQty);
      }

      if (nextQty === 0) {
        state.selected = false;
        state.quantity = 0;
        state.variationId = 0;
        state.attributes = {};
      } else {
        state.selected = true;
        state.quantity = nextQty;
        var variation = this.getFirstAvailableVariation(item);
        if (!state.variationId && variation) {
          state.variationId = parseInt(variation.variation_id, 10);
          state.attributes = variation.attributes || {};
        }
        this.enforceMax(idx);
      }

      this.render();
    },

    enforceMax: function(changedIdx) {
      var selected = this.getSelectedIndexes();
      if (selected.length <= this.hamper.max_items) {
        return;
      }
      this.state[changedIdx].selected = false;
      this.state[changedIdx].quantity = 0;
      this.state[changedIdx].variationId = 0;
      this.state[changedIdx].attributes = {};
    },

    render: function() {
      var self = this;
      var itemsHtml = self.hamper.items.map(function(item, idx) {
        return self.renderItem(item, idx);
      }).join('');

      self.$root.html(
        '<section class="tvak-hamper-panel" aria-label="Build your kit">' +
          '<div class="tvak-hamper-header">' +
            '<div>' +
              '<h2>Build Your Kit</h2>' +
              '<p>Min ' + parseInt(self.hamper.min_items, 10) + ' &bull; Max ' + parseInt(self.hamper.max_items, 10) + ' products</p>' +
            '</div>' +
            '<div class="tvak-hamper-count"></div>' +
          '</div>' +
          '<div class="tvak-hamper-items">' + itemsHtml + '</div>' +
          '<div class="tvak-hamper-footer">' +
            '<div class="tvak-hamper-summary">' +
              '<span class="tvak-hamper-gift" aria-hidden="true"></span>' +
              '<div>' +
                '<strong class="tvak-hamper-selected-label"></strong>' +
                '<p class="tvak-hamper-message"></p>' +
              '</div>' +
            '</div>' +
            '<div class="tvak-hamper-total">' +
              '<span>Total</span>' +
              '<strong></strong>' +
            '</div>' +
            '<button type="button" class="button alt tvak-hamper-submit">Add Hamper to Cart</button>' +
          '</div>' +
        '</section>'
      );

      self.updateStatus();
    },

    renderItem: function(item, idx) {
      var state = this.state[idx];
      var selected = state && state.selected;
      var quantity = selected ? Math.max(1, parseInt(state.quantity || 1, 10)) : 0;
      var actionLabel = selected ? (item.is_required ? 'Included' : 'Remove') : 'Add to hamper';
      var badge = item.is_required ? 'Included' : (selected ? 'In hamper' : 'Optional');
      var unavailable = item.is_in_stock ? '' : '<span class="tvak-hamper-stock">Out of stock</span>';
      var description = this.trimText(item.description || '', 54);
      var variationHtml = this.renderVariationControl(item, idx, selected);

      return '' +
        '<article class="tvak-hamper-item' + (selected ? ' is-selected' : '') + (item.is_in_stock ? '' : ' is-disabled') + '">' +
          '<div class="tvak-hamper-image-wrap">' +
            '<img src="' + this.escapeAttr(item.image_url || '') + '" alt="' + this.escapeAttr(item.name) + '" />' +
            '<span class="tvak-hamper-badge">' + this.escapeHtml(badge) + '</span>' +
          '</div>' +
          '<div class="tvak-hamper-item-body">' +
            '<h3>' + this.escapeHtml(item.name) + '</h3>' +
            '<p>' + this.escapeHtml(description || 'TVAK beauty essential') + '</p>' +
            '<strong class="tvak-hamper-price">' + this.formatPrice(this.getItemPrice(item, idx)) + '</strong>' +
            unavailable +
          '</div>' +
          '<div class="tvak-hamper-controls" aria-label="Choose quantity and hamper inclusion">' +
            '<div class="tvak-hamper-qty">' +
              '<button type="button" class="tvak-hamper-qty-button" data-item-idx="' + idx + '" data-delta="-1"' + (item.is_required && quantity <= 1 ? ' disabled' : '') + '>&minus;</button>' +
              '<span>' + quantity + '</span>' +
              '<button type="button" class="tvak-hamper-qty-button" data-item-idx="' + idx + '" data-delta="1">+</button>' +
            '</div>' +
            '<button type="button" class="tvak-hamper-product-action" data-item-idx="' + idx + '"' + (item.is_required || !item.is_in_stock ? ' disabled' : '') + '>' + this.escapeHtml(actionLabel) + '</button>' +
          '</div>' +
          variationHtml +
        '</article>';
    },

    renderVariationControl: function(item, idx, selected) {
      if (!item.variations || !item.variations.length) {
        return '';
      }

      var currentId = this.state[idx] ? parseInt(this.state[idx].variationId || 0, 10) : 0;
      var selectedLabel = this.getSelectedVariationLabel(item, idx);
      var buttons = item.variations.map(function(variation, variationIdx) {
        if (!variation.is_in_stock || !variation.variation_id) {
          return '';
        }
        var variationId = parseInt(variation.variation_id, 10);
        var active = currentId === variationId ? ' is-active' : '';
        var swatch = variation.hex ? '<span style="background-color:' + TvakHamper.escapeAttr(variation.hex) + '"></span>' : '';
        return '' +
          '<button type="button" class="tvak-hamper-shade-button' + active + '" data-item-idx="' + idx + '" data-variation-idx="' + variationIdx + '"' + (selected ? '' : ' disabled') + ' title="' + TvakHamper.escapeAttr(variation.label) + '">' +
            swatch +
            '<b>' + TvakHamper.escapeHtml(variation.label) + '</b>' +
          '</button>';
      }).join('');

      return '' +
        '<div class="tvak-hamper-variation-row' + (selected ? '' : ' is-muted') + '">' +
          '<div class="tvak-hamper-shade-label">Select Shade</div>' +
          '<div class="tvak-hamper-shade-list">' + buttons + '</div>' +
          '<div class="tvak-hamper-selected-shade">' + (selectedLabel ? 'Selected shade: ' + this.escapeHtml(selectedLabel) : 'Choose a shade') + '</div>' +
        '</div>';
    },

    updateStatus: function() {
      if (!this.hamper) {
        return;
      }

      var selected = this.getSelectedIndexes();
      var message = '';
      var valid = true;

      if (selected.length < this.hamper.min_items) {
        valid = false;
        message = 'Select at least ' + this.hamper.min_items + ' products.';
      } else if (selected.length > this.hamper.max_items) {
        valid = false;
        message = 'Select no more than ' + this.hamper.max_items + ' products.';
      }

      selected.forEach(function(idx) {
        var item = TvakHamper.hamper.items[idx];
        if (item.variations && item.variations.length && !TvakHamper.state[idx].variationId) {
          valid = false;
          message = 'Choose shades for all selected shade products.';
        }
      });

      $('.tvak-hamper-count').text(selected.length + ' / ' + this.hamper.max_items + ' selected');
      $('.tvak-hamper-selected-label').text(selected.length + ' Products Selected');
      $('.tvak-hamper-message').text(message || this.getRemainingMessage(selected.length));
      $('.tvak-hamper-total strong').text(this.formatPrice(this.getTotal()));
      $('.tvak-hamper-submit').prop('disabled', !valid);
    },

    getRemainingMessage: function(count) {
      var remaining = Math.max(0, parseInt(this.hamper.max_items, 10) - count);
      if (remaining === 0) {
        return 'Your hamper is full.';
      }
      return 'You can add ' + remaining + ' more product' + (remaining === 1 ? '' : 's') + '.';
    },

    getSelectedIndexes: function() {
      var selected = [];
      Object.keys(this.state).forEach(function(idx) {
        if (TvakHamper.state[idx].selected) {
          selected.push(parseInt(idx, 10));
        }
      });
      return selected;
    },

    getTotal: function() {
      var total = 0;
      var self = this;
      this.getSelectedIndexes().forEach(function(idx) {
        total += self.getItemPrice(self.hamper.items[idx], idx) * Math.max(1, parseInt(self.state[idx].quantity || 1, 10));
      });
      return total;
    },

    getItemPrice: function(item, idx) {
      var state = this.state[idx];
      if (state && state.variationId && item.variations) {
        var match = item.variations.filter(function(variation) {
          return parseInt(variation.variation_id, 10) === parseInt(state.variationId, 10);
        })[0];
        if (match) {
          return match.price;
        }
      }
      return item.price;
    },

    getSelectedVariationLabel: function(item, idx) {
      var state = this.state[idx];
      if (!state || !state.variationId || !item.variations) {
        return '';
      }

      var match = item.variations.filter(function(variation) {
        return parseInt(variation.variation_id, 10) === parseInt(state.variationId, 10);
      })[0];

      return match ? match.label : '';
    },

    getFirstAvailableVariation: function(item) {
      if (!item.variations || !item.variations.length) {
        return null;
      }
      return item.variations.filter(function(variation) {
        return variation.is_in_stock && variation.variation_id;
      })[0] || null;
    },

    submit: function() {
      var self = this;
      self.updateStatus();
      if ($('.tvak-hamper-submit').prop('disabled')) {
        return;
      }

      var payload = {
        hamper_product_id: self.hamper.hamper_product_id,
        items: self.getSelectedIndexes().map(function(idx) {
          var item = self.hamper.items[idx];
          var state = self.state[idx];
          return {
            product_id: item.product_id,
            quantity: Math.max(1, parseInt(state.quantity || 1, 10)),
            variation_id: state.variationId,
            attributes: state.attributes
          };
        })
      };

      $('.tvak-hamper-submit').removeClass('is-added').removeData('cart-url').prop('disabled', true).text('Adding...');

      $.ajax({
        url: tvak_hamper_vars.add_api,
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(payload),
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', tvak_hamper_vars.nonce);
        },
        success: function(res) {
          if (res && res.success && res.cart_url) {
            $('.tvak-hamper-message').text(res.message || 'Your hamper has been added to cart.');
            $('.tvak-hamper-submit')
              .prop('disabled', false)
              .addClass('is-added')
              .data('cart-url', res.cart_url)
              .text('View Cart');

            if ($(document.body).trigger) {
              $(document.body).trigger('added_to_cart', [res.fragments || {}, res.cart_hash || '']);
              $(document.body).trigger('wc_fragment_refresh');
            }
            return;
          }
          self.renderError('Unable to add this hamper.');
        },
        error: function(xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to add this hamper.';
          $('.tvak-hamper-message').text(message);
          $('.tvak-hamper-submit').prop('disabled', false).text('Add Hamper to Cart');
        }
      });
    },

    renderError: function(message) {
      this.$root.html('<div class="tvak-hamper-error">' + this.escapeHtml(message) + '</div>');
    },

    formatPrice: function(price) {
      var amount = parseFloat(price || 0);
      var symbol = tvak_hamper_vars.currency_symbol || '';
      return symbol + amount.toFixed(0);
    },

    trimText: function(value, limit) {
      var text = String(value || '').replace(/\s+/g, ' ').trim();
      if (text.length <= limit) {
        return text;
      }
      return text.substring(0, limit - 3).trim() + '...';
    },

    escapeHtml: function(value) {
      return String(value || '').replace(/[&<>"']/g, function(match) {
        return ({
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        })[match];
      });
    },

    escapeAttr: function(value) {
      return this.escapeHtml(value);
    }
  };

  $(function() {
    TvakHamper.init();
  });
})(jQuery);
