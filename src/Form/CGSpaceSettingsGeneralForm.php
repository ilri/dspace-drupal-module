<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * Configure example settings for this site.
 */
class CGSpaceSettingsGeneralForm extends CGSpaceSettingsBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cgspace_importer_admin_settings_general';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('The CGSpace endpoint URL for REST API.'),
      '#default_value' => $this->endpoint,
      '#required' => true,
    ];

    $form['importer'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Importer ID'),
      '#description' => $this->t('The CGSpace Importer ID sent as User-Agent Header to REST API in format "YOUR_VALUE Publications Importer BOT".'),
      '#default_value' => $this->config(static::SETTINGS)->get('importer'),
      '#required' => true,
    ];

    $form['page_size'] = [
      '#type' => 'number',
      '#title' => $this->t('CGSpace discover query Page Size'),
      '#description' => $this->t('The CGSpace discover query page size that represents the amount of items to return in one query (default:100). Tweak for best performances!'),
      '#default_value' => $this->config(static::SETTINGS)->get('page_size') ?? 100,
      '#min' => 10,
      '#max' => 100,
      '#required' => true,
    ];

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('DEBUG mode.'),
      '#description' => $this->t('Disable on Production environment'),
      '#default_value' => $this->config(static::SETTINGS)->get('debug') ?? 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.

    if($form_state->hasValue('endpoint')) {
      $is_valid = UrlHelper::isValid($form_state->getValue('endpoint'), true);
      if(!$is_valid) {
        $form_state->setError($form['endpoint'], $this->t('Please submit a valid endpoint URL!'));
      }
    }
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the configuration.
    $this->configFactory->getEditable(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('importer', $form_state->getValue('importer'))
      ->set('page_size', $form_state->getValue('page_size'))
      ->set('debug', $form_state->getValue('debug'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
