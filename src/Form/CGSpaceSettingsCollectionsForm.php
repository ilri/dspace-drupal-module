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
    $config_communities = $this->config(static::COMMUNITIES);
    $config_collections = $this->config(static::COLLECTIONS);

    //if we have communities set get collections
    if(!empty($communities = $config_communities->get()) && !empty($config->get('endpoint'))) {


      $form['collections'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Collections'),
        '#description' => $this->t('Select the Collections you want to include in the proxy generated XML.'),
        '#prefix' => '<div id="edit-collections-output">',
        '#suffix' => '</div>',
        '#tree' => true,
      ];

      foreach ($communities as $community => $value) {

        if ($value) {

          $form['collections'][$community] = [
            '#type' => 'fieldset',
            '#title' => $this->proxy->getCommunityName($community),
          ];

          $collections = $this->proxy->getCollections($community);
          foreach ($collections as $collection_uuid => $collection_name) {

            $form['collections'][$community][$collection_uuid] = array(
              '#type' => 'checkbox',
              '#title' => $collection_name,
              '#default_value' => $config_collections->get($collection_uuid),
            );
          }
        }

      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Retrieve the Communities configuration.
    $collections_settings = $this->configFactory->getEditable(static::COLLECTIONS);

    $collections_added = [];

    //set collections
    $old_collections = $collections_settings->get();
    foreach($form_state->getValue('collections') as $community => $collections) {
      foreach ($collections as $collection_uuid => $collection_value) {
        if( ($collection_value === 1) && ($old_collections[$collection_uuid] === 0) ){
          //added
          \Drupal::logger('cgspace_importer')->notice(
            t('Collection @collection added', [
              '@collection' => $collection_uuid
            ])
          );

          $collections_added[] = $collection_uuid;
        }

        $collections_settings->set($collection_uuid, $collection_value);
      }
    }

    $collections_settings->save();

    //update collections added and deleted state array
    \Drupal::state()->set('cgspace_importer.collections_added', $collections_added);


    parent::submitForm($form, $form_state);
  }

}
