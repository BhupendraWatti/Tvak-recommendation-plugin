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
     * Additional dynamic attributes.
     * @var array
     */
    private $extra_attributes = [];

    /**
     * Constructor.
     *
     * @param array $data Raw input array.
     */
    public function __construct(array $data = []) {
        $this->skin_type     = isset($data['skin_type']) ? sanitize_key($data['skin_type']) : 'normal';
        $this->skin_tone     = isset($data['skin_tone']) ? sanitize_key($data['skin_tone']) : 'fair_light';
        $this->undertone     = isset($data['undertone']) ? sanitize_key($data['undertone']) : 'neutral';

        if (isset($data['skin_concern']) && is_array($data['skin_concern'])) {
            $this->skin_concerns = array_map('sanitize_key', $data['skin_concern']);
        } elseif (isset($data['skin_concerns']) && is_array($data['skin_concerns'])) {
            $this->skin_concerns = array_map('sanitize_key', $data['skin_concerns']);
        }

        foreach ($data as $key => $val) {
            if (!in_array($key, ['skin_type', 'skin_tone', 'skin_concern', 'skin_concerns', 'undertone'], true)) {
                if (is_array($val)) {
                    $this->extra_attributes[sanitize_key($key)] = array_map('sanitize_key', $val);
                } else {
                    $this->extra_attributes[sanitize_key($key)] = sanitize_key($val);
                }
            }
        }
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
        return $this->skin_type === 'sensitive' || in_array('sensitive', $this->skin_concerns, true);
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
                return $this->extra_attributes[$code] ?? null;
        }
    }

    /**
     * Export vector to array format.
     *
     * @return array
     */
    public function to_array(): array {
        return array_merge(
            [
                'skin_type'    => $this->skin_type,
                'skin_tone'    => $this->skin_tone,
                'skin_concern' => $this->skin_concerns,
                'undertone'    => $this->undertone,
            ],
            $this->extra_attributes
        );
    }
}
