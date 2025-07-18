<?php

namespace Drupal\cgspace_importer\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Vocabulary;

class CGSpaceSettingsProcessorsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cgspace_importer_processors_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cgspace_importer.settings.processors.research_initiatives',
      'cgspace_importer.settings.processors.impact_areas'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


    $form['research_initiatives'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Research Initiatives'),
      '#required' => true,
      '#tree' => true,
    ];

    $form['research_initiatives']['create'] = [
      '#type' => 'radios',
      '#title' => $this->t('Would you like to create a new Vocabulary to Map Research Initiatives?'),
      '#description' => $this->t('You can create a new vocabulary or map research initiatives to an existing one'),
      '#default_value' => $this->config('cgspace_importer.settings.processors.research_initiatives')->get('create'),
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#required' => true,
    ];

    $vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    $options = [];
    foreach($vocabularies as $machine_name => $entity) {
      if($entity instanceof Vocabulary) {
        $options[$machine_name] = $entity->label();
      }
    }

    $form['research_initiatives']['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Please select a vocabulary'),
      '#description' => $this->t('You must select a vocabulary in your system to map Research Initiatives'),
      '#options' => $options,
      '#default_value' => $this->config('cgspace_importer.settings.processors.research_initiatives')->get('vocabulary'),
      '#states' => [
        'visible' => [
          [
            ':input[name="research_initiatives[create]"]' => ['value' => 0]
          ]
        ],
        'required' => [
          [
            ':input[name="research_initiatives[create]"]' => ['value' => 0]
          ]
        ],
      ]
    ];

    $form['impact_areas'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Impact Areas'),
      '#required' => true,
      '#tree' => true,
    ];

    $form['impact_areas']['create'] = [
      '#type' => 'radios',
      '#title' => $this->t('Would you like to create a new Vocabulary to Map Impact Areas?'),
      '#description' => $this->t('You can create a new vocabulary or map impact areas to an existing one'),
      '#default_value' => $this->config('cgspace_importer.settings.processors.impact_areas')->get('create'),
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#required' => true,
    ];

    $form['impact_areas']['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Please select a vocabulary'),
      '#description' => $this->t('You must select a vocabulary in your system to map Impact Areas'),
      '#options' => $options,
      '#default_value' => $this->config('cgspace_importer.settings.processors.impact_areas')->get('vocabulary'),
      '#states' => [
        'visible' => [
          [
            ':input[name="impact_areas[create]"]' => ['value' => 0]
          ]
        ],
        'required' => [
          [
            ':input[name="impact_areas[create]"]' => ['value' => 0]
          ]
        ],
      ]
    ];

    $form['tags'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tags'),
      '#required' => true,
      '#tree' => true,
    ];

    $form['tags']['create'] = [
      '#type' => 'radios',
      '#title' => $this->t('Would you like to create a new Vocabulary to Map Tags?'),
      '#description' => $this->t('You can create a new vocabulary or map tags to an existing one'),
      '#default_value' => $this->config('cgspace_importer.settings.processors.tags')->get('create'),
      '#options' => [
        1 => $this->t('Yes'),
        0 => $this->t('No'),
      ],
      '#required' => true,
    ];

    $form['tags']['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Please select a vocabulary'),
      '#description' => $this->t('You must select a vocabulary in your system to map Tags'),
      '#options' => $options,
      '#default_value' => $this->config('cgspace_importer.settings.processors.tags')->get('vocabulary'),
      '#states' => [
        'visible' => [
          [
            ':input[name="tags[create]"]' => ['value' => 0]
          ]
        ],
        'required' => [
          [
            ':input[name="tags[create]"]' => ['value' => 0]
          ]
        ],
      ]
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {

    $this->setMappingVocabulary(
      $form_state,
      'research_initiatives',
      'cgspace_research_initiatives',
      'Research Initiatives',
      'Vocabulary used to map CGSpace Research Initiatives',
      'field_cg_initiatives_ref'
    );

    $this->setMappingVocabulary(
      $form_state,
      'impact_areas',
      'cgspace_impact_areas',
      'Impact Areas',
      'Vocabulary used to map Impact Areas',
      'field_cg_impact_areas_ref'
    );

    $this->setMappingVocabulary(
      $form_state,
      'tags',
      'cgspace_tags',
      'Tags',
      'Vocabulary used to map Tags',
      'field_cg_tags_ref'
    );
  }

  private function setMappingVocabulary(FormStateInterface $form_state, $processor, $vid, $vocabulary_name, $vocabulary_description, $field_name) {

    // Retrieve the configuration.
    $this->configFactory->getEditable('cgspace_importer.settings.processors.'.$processor)
      // Set the submitted configuration setting.
      ->set('create', $form_state->getValue($processor)['create'])
      ->set('vocabulary', $form_state->getValue($processor)['vocabulary'])
      ->save();

    if($form_state->getValue($processor)['create'] == 1) {

      $name = $this->t($vocabulary_name);
      $description = $this->t($vocabulary_description);

      $vocabularies = Vocabulary::loadMultiple();

      if (!isset($vocabularies[$vid])) {
        try {
          Vocabulary::create(array(
            'vid' => $vid,
            'description' => $description,
            'name' => $name,
          ))->save();
        }
        catch (EntityStorageException $exception) {

        }
      }
      else {
        \Drupal::messenger()->addMessage($name . ' vocabulary already exits');
      }
    }

    //update field_cg_impact_areas_ref settings according to vocabulary
    if (!$field_storage_configs = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(array('field_type' => 'entity_reference'))) {
      return;
    }

    foreach ($field_storage_configs as $field_storage) {

      if($field_storage instanceof FieldConfig) {
        if($field_storage->id() === 'node.cgspace_publication.'.$field_name) {
          $settings = $field_storage->getSettings();
          $settings['handler_settings']['target_bundles'] = [
            $vid => $vid
          ];
          $field_storage->setSettings($settings);
          try {
            $field_storage->save();
          }
          catch (EntityStorageException $exception) {

          }
        }
      }
    }
  }
}
