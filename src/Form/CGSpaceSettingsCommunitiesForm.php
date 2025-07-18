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
    $config = $this->configFactory->getEditable(static::SETTINGS);
    $config_communities = $this->configFactory->getEditable(static::COMMUNITIES);

    if(!empty($config->get('endpoint'))) {

      $form['communities'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Communities'),
        '#description' => $this->t('Select the Communities you want to include in the proxy generated XML and then pick the collections below'),
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

    $form['#attached']['library'][] = 'cgspace_importer/cgspace_settings_form';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {


    // Retrieve the Communities configuration.
    $communities_settings = $this->configFactory->getEditable(static::COMMUNITIES);

    //delete previously submitted data
    $communities_settings->delete();

    //set communities
    foreach($form_state->getValue('communities') as $community_uuid => $community_value) {
      $communities_settings->set($community_uuid, $community_value);
    }

    $communities_settings->save();

    parent::submitForm($form, $form_state);
  }

}
