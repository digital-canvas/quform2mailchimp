<?php

namespace Quform2Mailchimp;

/**
 * Admin Options class
 * @package Quform2Mailchimp
 * @subpackage Options
 */
class Options {

  protected $options = array(
    'form_id' => null,
    'newsletter' => null,
    'fields' => array()
  );

  /**
   * Class constructor
   * @param array $options Current options
   */
  public function __construct($options = array()){
    if(is_array($options)){
      $this->setOptions($options);
    }
  }

  /**
   * Sets current options
   * @param array $options The current options
   * @return array New options array
   */
  public function setOptions($options){
    $this->options = array_merge($this->options, array_intersect_key($options, $this->options));
    return $this->options;
  }

  /**
   * Returns the currently set options
   * @return array The currently set options
   */
  public function getOptions(){
    return $this->options;
  }

  /**
   * The description for the section on the settings page
   */
  public function section_text(){
    echo '<p>Select the form you wish to connect.</p>';
  }

  /**
   * Mailchimp List Settings
   */
  public function section_text_mailchimp(){
    $list_id = get_option('mc_list_id');
    $list_name = get_option('mc_list_name');
    $api_key = get_option('mc_apikey');
    if($list_id){
      echo '<table class="form-table">';
      echo '<tr valign="top"><th scope="row">List Name</th><td>'.esc_html($list_name).'</td></tr>';
      echo '<tr valign="top"><th scope="row">List ID</th><td>'.esc_html($list_id).'</td></tr>';
      echo '<tr valign="top"><th scope="row">Mailchimp API Key</th><td>'.esc_html($api_key).'</td></tr>';

      echo '</table>';
    }else{
      echo '<p>A Mailchimp list has not been selected.</p>';
      echo '<p>You must first select a Mailchimp list on the Mailchimp <a href="./options-general.php?page=mailchimpSF_options">settings page</a>.</p>';
    }
  }

  /**
   * The description for the section on the settings page
   */
  public function section_text_fields(){
    if($this->options['form_id']){
      echo '<p>For each of the Mailchimp fields select the form field to use.</p>';
      echo '<p>The mapped fields will be sent to Mailchimp.</p>';
      echo '<p>You must map all fields that are marked as required by Mailchimp.<br>If required fields are not mapped the user will not be subscribed.</p>';
    }else{
      echo '<p>Select a form above before mapping fields.</p>';
    }
  }

  /**
   * List ID selector
   */
  public function field_form_id(){
    global $wpdb;
    $table = $wpdb->prefix . 'iphorm_forms';
    $forms = $wpdb->get_results("SELECT id,config FROM {$table} WHERE active = 1", ARRAY_A);
    if($forms){
      echo '<select name="quform2mailchimp_options[form_id]">';
      echo '<option value="">Do not connect to a form</option>';
      foreach($forms as $form){
        $form = maybe_unserialize($form['config']);
        echo '<option value="'.esc_attr($form['id']).'" ' . selected( $this->options['form_id'], $form['id'] ) . '>'.esc_html($form['name']).'</option>';
      }
      echo '</select>';
    } else {
      echo 'There are no active forms to connect.';
    }
  }

  /**
   * Form subscribe field
   * @param array $args Form settings
   */
  public function field_form_subscribe($args){
    list($form) = $args;
    echo '<select name="quform2mailchimp_options[newsletter]">';
    echo '<option value="--" '.selected( $this->options['newsletter'], null ).'>Always Subscribe</option>';
    foreach($form['elements'] as $element){
      echo '<option value="'.esc_attr($element['id']).'" ' . selected( $this->options['newsletter'], $element['id'] ) . '>'.esc_html($element['label']).'</option>';
    }
    echo '</select>';
    echo '<div><em>If a form field is selected the user will only be subscribed if the selected field is filled out (Suggested to use a checkbox like "Subscribe to Newsletter").</em></div>';

  }

  /**
   * Mailchimp to Quform field mapping
   * @param array $args Mailchimp field and Quform settings
   */
  public function mailchimp_field($args){
    list($field, $form) = $args;
    echo '<select name="quform2mailchimp_options[fields]['.esc_html($field['id']).']">';
    echo '<option value="">No form field</option>';
    foreach($form['elements'] as $element){
      echo '<option value="'.esc_html($element['id']).'" ' . selected( $this->options['fields'][$field['id']], $element['id'] ) . '>'.esc_html($element['label']).'</option>';
    }
    echo '</select>';
    if($field['req']){
      echo '<span style="color:#FF0000;"> required</span>';
    }

  }

  /**
   * Returns details for selected form
   * @param int $form_id
   * @return array
   */
  public function getFormDetails($form_id){
    global $wpdb;
    $table = $wpdb->prefix . 'iphorm_forms';
    $form_id = intval($form_id);
    $form = $wpdb->get_row("SELECT config FROM {$table} WHERE active = 1 AND id = {$form_id}", ARRAY_A);
    if($form){
      $form = maybe_unserialize($form['config']);
    }
    return $form;
  }

  /**
   * Validates settings form submission
   * @param array Posted values
   * @return array Values to save
   */
  public function validate($input){
    if(!$input){
      return $this->options;
    }
    $options['form_id'] = (intval($input['form_id']) > 0) ? (int)$input['form_id'] : null;
    if(array_key_exists('newsletter', $input)){
      if($input['newsletter'] == '--'){
        $options['newsletter'] = null;
      } else {
        $options['newsletter'] = (int)$input['newsletter'];
      }
    }
    // Mapped Fields
    $options['fields'] = array();
    if(isset($input['fields']) && is_array($input['fields'])){
      foreach($input['fields'] as $key => $value){
        if($value){
          $options['fields'][$key] = (int)$value;
        }
      }
    }
    return $options;
  }
}
