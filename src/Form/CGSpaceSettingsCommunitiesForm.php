<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class CGSpaceSettingsCommunitiesForm extends CGSpaceSettingsBaseForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cgspace_importer_admin_settings_communities';
  }


  private function buildCommunitiesHierarchyForm(array &$form, $uuid, $config_collections, $config_communities, $level=0) {
    $subcommunities = $this->proxy->getSubCommunities($uuid);

    if(count($subcommunities) > 0) {
      $level++;
      foreach($subcommunities as $subcommunity_uuid => $subcommunity) {
        $form['communities'][$subcommunity_uuid] = [
          '#type' => 'checkbox',
          '#title' => $subcommunity,
          '#default_value' => !is_null($config_collections->get($subcommunity_uuid)),
          '#wrapper_attributes' => ['class' => ['form-item-indent-'.$level]],
        ];

        $config_communities->set($subcommunity_uuid, $subcommunity);
        $this->buildCommunitiesHierarchyForm($form, $subcommunity_uuid, $config_collections, $config_communities, $level);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(static::SETTINGS);
    $config_collections = $this->configFactory->getEditable(static::COLLECTIONS);
    $config_communities = $this->configFactory->getEditable(static::COMMUNITIES);

    if(!empty($config->get('endpoint'))) {

      $form['communities'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Communities'),
        '#description' => $this->t('Select the Communities you want to import and then select Collections.'),
        '#tree' => true,
      ];

      $this->addCheckAll($form['communities']);

      $communities = $this->proxy->getCommunities();

      foreach($communities as $uuid => $community) {

        $form['communities'][$uuid] = [
          '#type' => 'checkbox',
          '#title' => $community,
          '#default_value' => !is_null($config_collections->get($uuid)),
        ];

        $config_communities->set($uuid, $community);



        $this->buildCommunitiesHierarchyForm($form, $uuid, $config_collections, $config_communities);

      }

      $config_communities->save();
    }

    $form['#attached']['library'][] = 'cgspace_importer/cgspace_settings_form';


    //dpm($config_collections->get());

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {


    // Retrieve the Communities configuration.
    $communities_settings = $this->configFactory->getEditable(static::COLLECTIONS);

    $old_communities = $communities_settings->get();
    $communities_settings->delete();
    //set communities
    foreach($form_state->getValue('communities') as $community => $checked) {
      if($checked) {
        if(empty($old_communities[$community])) {
          $communities_settings->set($community, []);
        }
        else {
          $communities_settings->set($community, $old_communities[$community]);
        }
      }
    }

    $communities_settings->save();

    parent::submitForm($form, $form_state);
  }

}
