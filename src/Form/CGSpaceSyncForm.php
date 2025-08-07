<?php

namespace Drupal\cgspace_importer\Form;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\cgspace_importer\Commands\CGSpaceImporterCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;

class CGSpaceSyncForm extends FormBase {

  protected CGSpaceImporterCommands $command;

  /**
   * {@inheritDoc}
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient, LoggerChannelFactoryInterface $loggerChannelFactory)
  {
    $this->command = new CGSpaceImporterCommands($configFactory, $httpClient, $loggerChannelFactory);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('logger.factory')
    );
  }
  /**
   * {@inheritDoc}
   */
  public function getFormId()
  {
    return 'cgspace_importer_sync_form';
  }
  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $last_run = \Drupal::state()->get('cgspace_importer.last_run');

    $form['last_run'] = [
      '#type' => 'datetime',
      '#title' => t('Since'),
      '#default_value' => isset($last_run) ? new DrupalDateTime($last_run) : '',
    ];

    $form['all'] = [
      '#type' => 'checkbox',
      '#title' => t('Import all CGSpace Items ignoring Communities and Collections configuration'),
      '#default' => \Drupal::state()->get('cgspace_importer.full_imported') ?? false,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync'),
      '#button_type' => 'primary',
    ];

    return $form;

  }
  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $options = ['all' => false];
    $last_run = '';

    if($form_state->hasValue('all') && $form_state->getValue('all')) {
      $options = ['all' => true];
    }

    if($form_state->hasValue('last_run')) {
      $date = $form_state->getValue('last_run');
      $last_run = $date->format('Y-m-d');
    }

    $this->command->update($last_run, $options);

    $form_state->setRedirectUrl(new Url('cgspace_importer.sync'));
  }


}
