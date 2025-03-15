<?php
class WCL_Protection {
    private $id;
    private $content_id;
    private $protection_type;
    private $countdown_mode;
    private $countdown_first;
    private $countdown_second;
    private $first_message;
    private $second_message;
    private $redirect_message;
    private $requires_ga;
    private $status;
	private $ga4_enabled;
    private $ga4_measurement_id;
    private $gtm_container_id;

    public function __construct($data = null) {
        if ($data) {
            $this->hydrate($data);
        }
    }

    private function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set_' . $key;
            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }
    }

    // Getters
    public function get_id() { return $this->id; }
    public function get_content_id() { return $this->content_id; }
    public function get_protection_type() { return $this->protection_type; }
    public function get_countdown_mode() { return $this->countdown_mode; }
    public function get_countdown_first() { return $this->countdown_first; }
    public function get_countdown_second() { return $this->countdown_second; }
    public function get_first_message() { return $this->first_message; }
    public function get_second_message() { return $this->second_message; }
    public function get_redirect_message() { return $this->redirect_message; }
    public function get_requires_ga() { return $this->requires_ga; }
    public function get_status() { return $this->status; }
	public function get_ga4_enabled() { return $this->ga4_enabled; }
	public function get_ga4_measurement_id() { return $this->ga4_measurement_id; }
	public function get_gtm_container_id() { return $this->gtm_container_id; }
	
    // Setters
    public function set_id($value) { $this->id = intval($value); }
    public function set_content_id($value) { $this->content_id = intval($value); }
    public function set_protection_type($value) { $this->protection_type = sanitize_text_field($value); }
    public function set_countdown_mode($value) { $this->countdown_mode = sanitize_text_field($value); }
    public function set_countdown_first($value) { $this->countdown_first = intval($value); }
    public function set_countdown_second($value) { $this->countdown_second = intval($value); }
    public function set_first_message($value) { $this->first_message = wp_kses_post($value); }
    public function set_second_message($value) { $this->second_message = wp_kses_post($value); }
    public function set_redirect_message($value) { $this->redirect_message = wp_kses_post($value); }
    public function set_requires_ga($value) { $this->requires_ga = boolval($value); }
    public function set_status($value) { $this->status = sanitize_text_field($value); }
	public function set_ga4_enabled($value) { $this->ga4_enabled = boolval($value); }
	public function set_ga4_measurement_id($value) { $this->ga4_measurement_id = sanitize_text_field($value); }
	public function set_gtm_container_id($value) { $this->gtm_container_id = sanitize_text_field($value); }
    
	public function to_array() {
        return [
            'id' => $this->id,
            'content_id' => $this->content_id,
            'protection_type' => $this->protection_type,
            'countdown_mode' => $this->countdown_mode,
            'countdown_first' => $this->countdown_first,
            'countdown_second' => $this->countdown_second,
            'first_message' => $this->first_message,
            'second_message' => $this->second_message,
            'redirect_message' => $this->redirect_message,
            'requires_ga' => $this->requires_ga,
            'status' => $this->status,
			'ga4_enabled' => $this->ga4_enabled,
			'ga4_measurement_id' => $this->ga4_measurement_id,
			'gtm_container_id' => $this->gtm_container_id
        ];
    }
}