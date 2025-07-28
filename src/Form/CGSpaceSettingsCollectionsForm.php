<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class CGSpaceSettingsCollectionsForm extends CGSpaceSettingsBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cgspace_importer_admin_settings_collections';
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $config_collections = $this->config(static::COLLECTIONS);

    //if we have communities set get collections
    if(!empty($communities = $config_collections->get()) && !empty($config->get('endpoint'))) {

      $form['collections'] = [
        '#type' => 'fieldset',
        '#title' => $this->t("Select Collections from your Communities"),
        '#tree' => true,
      ];

      $this->addCheckAll($form['collections']);

      foreach ($communities as $community => $collections) {
        $options = [];
        $collections = $this->proxy->getCollections($community);
        foreach ($collections as $collection_uuid => $collection_name) {
          $options[$collection_uuid] = $collection_name;
        }

        if(count($options) > 0) {
          $form['collections'][$community] = [
            '#type' => 'checkboxes',
            '#title' => $this->proxy->getCommunityName($community),
            '#default_value' => $config_collections->get($community),
            '#options' => $options,
            '#check_all' => true,
          ];
        }
      }
    }

    //dpm($config_collections->get());

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the Communities configuration.
    $collections_settings = $this->configFactory->getEditable(static::COLLECTIONS);

    $collections_added = [];
    foreach($form_state->getValue('collections') as $community => $collections) {
      $new_collections = [];
      $old_collections = $collections_settings->get($community);
      foreach($collections as $collection => $checked) {
        if($checked) {
          $new_collections[] = $collection;
        }


        if(is_null($old_collections) || !in_array($collection, $old_collections)) {
          \Drupal::logger('cgspace_importer')->notice(
            t('Collection @collection added', [
              '@collection' => $collection
            ])
          );

          $collections_added[] = $collection;
        }

      }

      $collections_settings->set($community, $new_collections);
    }


    $collections_settings->save();

    //update collections added and deleted state array
    \Drupal::state()->set('cgspace_importer.collections_added', $collections_added);


    parent::submitForm($form, $form_state);
  }

}
