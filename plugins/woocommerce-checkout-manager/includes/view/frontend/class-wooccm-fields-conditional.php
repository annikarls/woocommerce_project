<?php

class WOOCCM_Fields_Conditional {

  protected static $_instance;

  public function __construct() {
    $this->init();
  }

  public static function instance() {
    if (is_null(self::$_instance)) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  public function remove_required($fields) {

    foreach ($fields as $field_id => $field) {

      if (!empty($field['conditional']) && !empty($field['conditional_parent_key']) && ($field['conditional_parent_key'] != $field['key'])) {


        // Unset if parent is disabled
        // -----------------------------------------------------------------
        if (empty($fields[$field['conditional_parent_key']])) {
          unset($fields[$field['key']]);
          continue;
        }
        
        // Remove required
        // -----------------------------------------------------------------
        if (isset($_REQUEST['woocommerce-process-checkout-nonce']) && (!isset($_POST[$field['conditional_parent_key']]) || !isset($field['conditional_parent_value']) || !array_intersect((array) $field['conditional_parent_value'], (array) $_POST[$field['conditional_parent_key']]))) {
          $field['required'] = false;
          unset($fields[$field['key']]);
        }
      }
    }

    return $fields;
  }

  public function add_field_attributes($field) {
    if (!empty($field['conditional']) && !empty($field['conditional_parent_key']) && isset($field['conditional_parent_value']) && ($field['conditional_parent_key'] != $field['name'])) {
      $field['class'][] = 'wooccm-conditional-child';
      $field['custom_attributes']['data-conditional-parent'] = $field['conditional_parent_key'];
      $field['custom_attributes']['data-conditional-parent-value'] = $field['conditional_parent_value'];
    }
    return $field;
  }

  public function init() {
    // Add field attributes
    add_filter('wooccm_checkout_field_filter', array($this, 'add_field_attributes'));
    add_action('wooccm_billing_fields', array($this, 'remove_required'));
    add_action('wooccm_shipping_fields', array($this, 'remove_required'));
    add_action('wooccm_additional_fields', array($this, 'remove_required'));
  }

}

WOOCCM_Fields_Conditional::instance();
