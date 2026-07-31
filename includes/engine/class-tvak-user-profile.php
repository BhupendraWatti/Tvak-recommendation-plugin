<?php
/**
 * User Profile Vector Data Object
 *
 * Encapsulates and normalizes customer quiz selections into a type-safe profile vector.
 *
 * @package TVAK_Beauty_Kit
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tvak_User_Profile {

    /**
     * Skin Type.
     * @var string
     */
    private $skin_type;

    /**
     * Skin Tone.
     * @var string
     */
    private $skin_tone;

    /**
     * Skin Concerns.
     * @var array
     */
    private $skin_concerns = [];

    /**
     * Undertone.
     * @var string
     */
    private $undertone;

    /**
     * Dynamic quiz/profile attributes keyed by attribute code.
     * @var array
     */
    private $attributes = [];

    /**
     * Customer-preferred shade selections: maps product_id (int) => variation_id (int).
     * Populated when the customer has already selected a specific shade before
     * requesting a fresh recommendation (e.g. re-running the quiz after shade swatch pick).
     * @var array
     */
    private $preferred_shades = [];

    /**
     * Constructor.
     *
     * @param array $data Raw input array.
     */
    public function __construct(array $data = []) {
        // Capture preferred shade selections (product_id => variation_id)
        if (isset($data['preferred_shades']) && is_array($data['preferred_shades'])) {
            foreach ($data['preferred_shades'] as $prod_id => $var_id) {
                $prod_id_int = (int) $prod_id;
                $var_id_int  = (int) $var_id;
                if ($prod_id_int > 0 && $var_id_int > 0) {
                    $this->preferred_shades[$prod_id_int] = $var_id_int;
                }
            }
        }

        foreach ($data as $key => $val) {
            $clean_key = sanitize_key($key);
            if ($clean_key === '' || in_array($clean_key, ['preferred_shades', 'nocache'], true)) {
                continue;
            }

            if (is_array($val)) {
                $this->attributes[$clean_key] = array_values(array_filter(array_map('sanitize_key', $val)));
            } else {
                $this->attributes[$clean_key] = sanitize_key($val);
            }
        }

        $this->skin_type     = (string) ($this->attributes['skin_type'] ?? '');
        $this->skin_tone     = (string) ($this->attributes['skin_tone'] ?? '');
        $this->undertone     = (string) ($this->attributes['undertone'] ?? '');
        $this->skin_concerns = (array) ($this->attributes['skin_concern'] ?? ($this->attributes['skin_concerns'] ?? []));
    }

    public function get_skin_type(): string {
        return $this->skin_type;
    }

    public function get_skin_tone(): string {
        return $this->skin_tone;
    }

    public function get_skin_concerns(): array {
        return $this->skin_concerns;
    }

    public function get_undertone(): string {
        return $this->undertone;
    }

    public function is_sensitive(): bool {
        $sensitive_terms = get_option('tvak_sensitive_profile_terms', []);
        if (!is_array($sensitive_terms) || empty($sensitive_terms)) {
            return false;
        }

        $sensitive_terms = array_map('sanitize_key', $sensitive_terms);
        return in_array($this->skin_type, $sensitive_terms, true) || !empty(array_intersect($sensitive_terms, $this->skin_concerns));
    }

    /**
     * Get customer's preferred variation ID for a specific product (shade override).
     *
     * @param int $product_id WooCommerce Product ID.
     * @return int|null Preferred variation ID or null if no preference set.
     */
    public function get_preferred_shade(int $product_id): ?int {
        return $this->preferred_shades[$product_id] ?? null;
    }

    /**
     * Get value for attribute code.
     *
     * @param string $code Attribute code.
     * @return mixed
     */
    public function get_attribute_value(string $code) {
        switch ($code) {
            case 'skin_type':
                return $this->skin_type;
            case 'skin_tone':
                return $this->skin_tone;
            case 'skin_concern':
            case 'skin_concerns':
                return $this->skin_concerns;
            case 'undertone':
                return $this->undertone;
            default:
                return $this->attributes[$code] ?? null;
        }
    }

    /**
     * Export vector to array format.
     *
     * @return array
     */
    public function to_array(): array {
        $base = $this->attributes;

        if (!empty($this->preferred_shades)) {
            $base['preferred_shades'] = $this->preferred_shades;
        }

        return $base;
    }
}
