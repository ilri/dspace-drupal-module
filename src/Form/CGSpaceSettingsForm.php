<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Component\Utility\UrlHelper;
use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;

/**
 * Configure example settings for this site.
 */
class CGSpaceSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'cgspace_importer.settings';
  const COMMUNITIES = 'cgspace_importer.settings.communities';
  const COLLECTIONS = 'cgspace_importer.settings.collections';

  private $endpoint;
  private $proxy;

  public function __construct(ConfigFactoryInterface $configFactory, protected TypedConfigManagerInterface $typedConfigManager, CGSpaceProxy $proxy)
  {
    parent::__construct($configFactory, $typedConfigManager);
    $this->proxy = $proxy;
    $this->endpoint = $this->config(static::SETTINGS)->get('endpoint');
  }

  public static function create($container): static {
    $endpoint = $container->get('config.factory')->get(static::SETTINGS)->get('endpoint');
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      new CGSpaceProxy(
        $endpoint,
        $container->get('config.factory'),
        $container->get('http_client')
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cgspace_importer_admin_settings';
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


  private function buildCommunitiesHierarchyForm(array &$form, $uuid, $config_communities, $level=0) {
    $subcommunities = $this->proxy->getSubCommunities($uuid);

    if(count($subcommunities) > 0) {
      $level++;
      foreach($subcommunities as $subcommunity_uuid => $subcommunity) {
        $form['communities'][$subcommunity_uuid] = [
          '#type' => 'checkbox',
          '#title' => $subcommunity,
          '#default_value' => $config_communities->get($subcommunity_uuid),
          '#wrapper_attributes' => ['class' => ['form-item-indent-'.$level]],
        ];

        $this->buildCommunitiesHierarchyForm($form, $subcommunity_uuid, $config_communities, $level);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config_communities = $this->config(static::COMMUNITIES);
    $config_collections = $this->config(static::COLLECTIONS);

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
      '#description' => $this->t('The CGSpace Importer ID sent as Header to REST API.'),
      '#default_value' => $this->config(static::SETTINGS)->get('importer'),
      '#required' => true,
    ];

    if(!empty($this->endpoint)) {


      $form['communities'] = [
        '#type' => 'details',
        '#title' => $this->t('Communities'),
        '#description' => $this->t('Select the Communities you want to include in the proxy generated XML and then pick the collections below'),
        '#open'   => empty($config_communities->getRawData()) ? true : false,
        '#tree' => true,
      ];

      $communities = $this->proxy->getCommunities();

      foreach($communities as $uuid => $community) {

        $form['communities'][$uuid] = [
          '#type' => 'checkbox',
          '#title' => $community,
          '#default_value' => $config_communities->get($uuid),
        ];

        $this->buildCommunitiesHierarchyForm($form, $uuid, $config_communities);

      }
    }

    //if we have communities set get collections
    if(!empty($communities = $config_communities->get())) {


      $form['collections'] = [
        '#type' => 'details',
        '#title' => $this->t('Collections'),
        '#description' => $this->t('Select the Collections you want to include in the proxy generated XML.'),
        '#open' => true,
        '#prefix' => '<div id="edit-collections-output">',
        '#suffix' => '</div>',
        '#tree' => true,
      ];

      foreach($communities as $community => $value) {

        if($value) {

          $form['collections'][$community] = [
            '#type' => 'fieldset',
            '#title' => $this->proxy->getCommunityName($community),
          ];

          $collections = $this->proxy->getCollections($community);
          foreach($collections as $collection_uuid => $collection_name) {

            $form['collections'][$community][$collection_uuid] = array(
              '#type' => 'checkbox',
              '#title' => $collection_name,
              '#default_value' => $config_collections->get($collection_uuid),
            );
          }
        }

      }
    }

    $form['#attached']['library'][] = 'cgspace_importer/cgspace-settings-form';

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
      ->save();

    // Retrieve the Communities configuration.
    $communities_settings = $this->configFactory->getEditable(static::COMMUNITIES);
    $collections_settings = $this->configFactory->getEditable(static::COLLECTIONS);

    //delete previously submitted data
    $collections_settings->delete();
    $communities_settings->delete();

    //set communities
    foreach($form_state->getValue('communities') as $community_uuid => $community_value) {
      $communities_settings->set($community_uuid, $community_value);
    }
    //set collections
    foreach($form_state->getValue('collections') as $community => $collections) {
      //set collections only if community is selected
      if($communities_settings->get($community)) {
        foreach ($collections as $collection_uuid => $collection_value) {
          $collections_settings->set($collection_uuid, $collection_value);
        }
      }
    }

    $collections_settings->save();
    $communities_settings->save();

    parent::submitForm($form, $form_state);
  }

}
