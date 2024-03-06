<?php

namespace Drupal\fa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

class TemplateMapping extends ConfigFormBase {

  protected $configFactory;
  protected $entityTypeManager;

  public function __construct(
    ConfigFactoryInterface $configFactory, 
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  protected function getEditableConfigNames() {
    return ['fa.template_mapping'];
  }

  public function getFormId() {
    return 'fa_template_mapping';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();
    $config = $this->configFactory->get('fa.template_mapping');
    $form['fa_business_email']  = [
      '#type' => 'textfield',
      '#title' => 'Business E-mail',
      '#description' => $this->t('Add the business email to which processed documents needs to be sent.'),
      '#default_value' => $config->get('fa_business_email') ?: '',
    ];
    $skip_elements_array = [
      'container',
      'webform_actions',
      'webform_flexbox',
      'processed_text',
      'webform_wizard_page',
      'hidden',
      'radios'
    ];

    foreach ($webforms as $webform) {
      $categories = $webform->get('categories');
      if ((count($categories) > 0 && strpos($categories[0], 'FA') !== 0) || empty($categories)) {
        continue;
      }


      $filename = $webform->id() . '.docx'; // Extension needs to be fixed
      $file_uri = 'private://fa_documents/' . $filename;

      $form['fa_fieldset_'.$webform->id()] = [
        '#type' => 'fieldset',
        '#title' => $webform->label() . $this->t(' - Configuration'),
      ];

      $form['fa_fieldset_'.$webform->id()]['uploaded_document_'.$webform->id()] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Upload Template'),
        '#description' => $this->t('Upload a document for the selected webform.'),
        '#upload_location' => 'private://fa_documents/',
        '#upload_validators' => [
          'file_validate_extensions' => ['docx'],
        ],
      ];

      if (file_exists($file_uri)) {
        $form['fa_fieldset_' . $webform->id()]['message'] = [
          '#markup' => '<div class="message">' .
            $this->t('A document has been uploaded for the "%webform_label form". If you wish to replace the existing document, please upload a new one.', ['%webform_label' => $webform->label()]) .
            '</div>',
        ];
      }

      $elements = $webform->getElementsDecodedAndFlattened();
      foreach ($elements as $element_key => $element) {
        if (!in_array($element['#type'], $skip_elements_array)) {
          $field_label = isset($element['#title']) ? $element['#title'] : $element_key;
          $form['fa_fieldset_'.$webform->id()]['pricing'] = [
            // '#type' => 'fieldset',
            '#type' => 'details',
            '#title' => $webform->label() . $this->t(' - Pricing'),
            '#open' => FALSE,
          ];
          $form['fa_fieldset_' . $webform->id()]['pricing'][$webform->id() . '_basic']  = [
            '#type' => 'textfield',
            '#title' => 'Basic Price (In AED)',
            '#default_value' => $config->get($webform->id() . '_basic') ?: '',
          ];
          $form['fa_fieldset_' . $webform->id()]['pricing'][$webform->id() . '_standard']  = [
            '#type' => 'textfield',
            '#title' => 'Standard Price (In AED)',
            '#default_value' => $config->get($webform->id() . '_standard') ?: '',
          ];
          $form['fa_fieldset_' . $webform->id()]['pricing'][$webform->id() . '_premium']  = [
            '#type' => 'textfield',
            '#title' => 'Premium Price (In AED)',
            '#default_value' => $config->get($webform->id() . '_premium') ?: '',
          ];
          $form['fa_fieldset_' . $webform->id()]['field_' . $element_key]  = [
            '#type' => 'textfield',
            '#title' => $field_label,
            '#default_value' => $config->get($webform->id() . '.' . $element_key) ?: '',
          ];
        }        
      }
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();
    $config = $this->configFactory->getEditable('fa.template_mapping');
    $config->set('fa_business_email', $form_state->getValues()['fa_business_email'])->save();
    foreach ($webforms as $webform) {
      $file_element_name = 'uploaded_document_' . $webform->id();
      $file_ids = $form_state->getValue($file_element_name);
      
      foreach ($file_ids as $file_id) {
        if (!empty($file_id)) {
          $file = File::load($file_id);
          if ($file) {
            $file_info = pathinfo($file->getFileUri());
            $new_filename = $webform->id() . '.' . $file_info['extension'];
            $destination = 'private://fa_documents/' . $new_filename;
            \Drupal::service('file.repository')->move($file, $destination, FileSystemInterface::EXISTS_REPLACE);
            $new_file = File::create([
              'uri' => $destination,
            ]);
            $new_file->save();
            $file->delete();
          }
        }
      }

      $elements = $webform->getElementsDecodedAndFlattened();
      foreach ($elements as $element_key => $element) {
        if (isset($element['#type']) && is_string($element['#type']) &&
          $element['#type'] !== 'container' && $element['#type'] !== 'webform_actions') {
          $field_value = $form_state->getValue('field_' . $element_key);
          $basic_price = $form_state->getValues()[$webform->id() . '_basic'];
          $standard_price = $form_state->getValues()[$webform->id() . '_standard'];
          $premium_price = $form_state->getValues()[$webform->id() . '_premium'];

          $config->set($webform->id() . '.' . $element_key, $field_value)->save();
          // Save pricing.
          $config->set($webform->id() . '_basic', $basic_price)->save();
          $config->set($webform->id() . '_standard', $standard_price)->save();
          $config->set($webform->id() . '_premium', $premium_price)->save();
        }
      }
    }
  }
}
