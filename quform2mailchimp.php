<?php
/*
Plugin Name: Quform 2 Mailchimp
Plugin URI: https://github.com/digital-canvas/wp-quform2mailchimp
Description: Allows subscribing users to Mailchimp lists on Quform form submissions. Requires both the Quform and MailChimp plugins to be installed.
Version: 0.1
Author: Digital Canvas
Author URI: http://www.digitalcanvas.com
*/

// get_option('mc_apikey');

register_activation_hook(__FILE__,function(){
  require_once(__DIR__.'/includes/Options.php');
  $option = new Quform2Mailchimp\Options();
  add_option("quform2mailchimp_options", $option->getOptions(), '', 'yes');
});

register_deactivation_hook(__FILE__,function(){
  delete_option('quform2mailchimp_options');
});

if ( is_admin() ){
  add_action('admin_init', function(){

    add_filter('plugin_action_links_'.plugin_basename(__FILE__), function($links){
      $settings_page = add_query_arg(array('page' => 'quform2mailchimp'), admin_url('options-general.php'));
      $settings_link = '<a href="'.esc_url($settings_page).'">Settings</a>';
    	array_unshift($links, $settings_link);
      return $links;
    }, 10, 1);

    require_once(__DIR__.'/includes/Options.php');
    $settings = get_option('quform2mailchimp_options');
    $option = new Quform2Mailchimp\Options($settings);
    register_setting( 'quform2mailchimp_options', 'quform2mailchimp_options', array($option,'validate') );
    add_settings_section('quform2mailchimp_form', 'Select Quform Form', array($option,'section_text'), 'quform2mailchimp');
    add_settings_section('quform2mailchimp_mailchimp', 'Mailchimp List', array($option,'section_text_mailchimp'), 'quform2mailchimp');
    add_settings_section('quform2mailchimp_fields', 'Map Fields', array($option,'section_text_fields'), 'quform2mailchimp');


    add_settings_field('quform2mailchimp_form_id', 'Form ID', array($option,'field_form_id'), 'quform2mailchimp', 'quform2mailchimp_form');
    if($settings['form_id']){
      // Pull The currently saved form
      $form = $option->getFormDetails($settings['form_id']);
      if($form){
        add_settings_field('quform2mailchimp_form_subscribe', 'Newsletter Subscription', array($option,'field_form_subscribe'), 'quform2mailchimp', 'quform2mailchimp_form', array($form));

        $mailchimp_fields = get_option('mc_merge_vars');
        if($mailchimp_fields){
          foreach($mailchimp_fields as $field){
            add_settings_field('quform2mailchimp_'.$field['id'], $field['name'], array($option,'mailchimp_field'), 'quform2mailchimp', 'quform2mailchimp_fields', array($field, $form));
          }
        }
      }
    }

  });

  add_action('admin_menu', function(){
    add_options_page('Quform to Mailchimp', 'Quform2Mailchimp', 'manage_options', 'quform2mailchimp', 'quform2mailchimp_options_page');

  });

}


function quform2mailchimp_options_page(){
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>Quform2Mailchimp</h2>
<form method="post" action="options.php">
  <?php settings_fields('quform2mailchimp_options'); ?>
  <?php do_settings_sections('quform2mailchimp'); ?>

  <?php submit_button(); ?>
</form>
</div>
<?php
}

/**
 * Hook into form submission
 */
add_action('iphorm_post_process', function($form){
  // Form has been submitted
  $options = get_option('quform2mailchimp_options');
  if($options['form_id'] == $form->getId()){
    // This is the selected form
    if($options['newsletter']){
      // Check if newsletter was checked
      $element_id = 'iphorm_'.$form->getId().'_'.$options['newsletter'];
      $element = $form->getElement($element_id);
      if(!$element){
        // Newsletter checkbox was not found
        echo 'no element';
        return;
      }
      $value = $element->getValue();
      if(!$value){
        // Do not send this submission to Mailchimp
        echo 'no value';
        return;
      }
    }
    $apiKey = get_option('mc_apikey');
    $listId = get_option('mc_list_id');
    $merge_vars = get_option('mc_merge_vars');
    if($apiKey && $listId){
      // Mailchimp Settings are set
      // Other settings
      $emailType = 'html';
      $doubleOptin = false;
      $updateExisting = false;
      $replaceInterests = false;
      $sendWelcome = false;
      $mergeVars = array();
      // Map fields
      foreach($options['fields'] as $mailchimp_field_id => $form_field_id){
        foreach($merge_vars as $field){
          if($field['id'] == $mailchimp_field_id){
            // This is a mapped field
            $form_field_id = 'iphorm_'.$form->getId().'_'.$form_field_id;
            $element = $form->getElement($form_field_id);
            if(!$element){
              // Form Element not found
              continue;
            }
            if($field['field_type'] == 'email'){
              // This is the subscriber's email address
              $email = $element->getValue();
              continue;
            }
            $mergeVars[$field['tag']] = $element->getValue();
          }
        }
      }
      require_once(__DIR__.'/includes/MCAPI.class.php');
      $api = new Quform2Mailchimp\MCAPI($apiKey);
      $api->listSubscribe($listId, $email, $mergeVars, $emailType, $doubleOptin, $updateExisting, $replaceInterests, $sendWelcome);
      if($api->hasError()){
        $error = $api->getError();
        if($error['errorCode'] == 214){
          // User was already subscribed to mailing list
        }else{
          // Another error was thrown
          // @TODO log error
        }
      }
    }
  }
}, 10, 1);

