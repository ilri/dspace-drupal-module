<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\cgspace_importer\CGSpaceProxy;

/**
 * Configure example settings for this site.
 */
abstract class CGSpaceSettingsBaseForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'cgspace_importer.settings.general';
  const COMMUNITIES = 'cgspace_importer.settings.communities';
  const COLLECTIONS = 'cgspace_importer.settings.collections';

  protected $endpoint;
  protected $proxy;

  public function __construct(ConfigFactoryInterface $configFactory, TypedConfigManagerInterface $typedConfigManager, CGSpaceProxy $proxy)
  {
    parent::__construct($configFactory, $typedConfigManager);
    $this->proxy = $proxy;
    $this->endpoint = $this->config(static::SETTINGS)->get('endpoint');
  }

  public static function create($container): static {
    $endpoint = $container->get('config.factory')->get(static::SETTINGS)->get('endpoint');
    if (is_null($endpoint)) {
      $endpoint = '';
    }
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      new CGSpaceProxy(
        $endpoint,
        $container->get('config.factory'),
        $container->get('http_client'),
        $container->get('logger.factory')
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
      static::COMMUNITIES,
      static::COLLECTIONS
    ];
  }

  protected function addCheckAll(array &$form) {

    $form['all_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['checkall-widget']],
      '#weight' => 0,
    ];
    $form['all_wrapper']['check_all'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Check all'),
      '#default_value' => FALSE,
      '#attributes' => [
        'class' => [
          'checkall-widget-btn',
          'button--small',
        ],
        'data-check-all' => TRUE,
      ],
    ];
    $form['all_wrapper']['uncheck_all'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Uncheck all'),
      '#default_value' => FALSE,
      '#attributes' => [
        'class' => [
          'checkall-widget-btn',
          'button--small',
        ],
        'data-check-all' => FALSE,
      ],
    ];

    $form['#attached']['library'][] = 'cgspace_importer/checkall_widget';
  }

}
