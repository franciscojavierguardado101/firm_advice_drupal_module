<?php

namespace Drupal\fa;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use PhpOffice\PhpWord\TemplateProcessor;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Generates a Template.
 */
class FADocumentBuilderService {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a FADocumentBuilderService instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Generates a template.
   *
   * @param int $submission_id
   *   The ID of the webform submission.
   *
   * @return string|false
   *   The path to the generated template or false if there's an error.
   */
  public function generateTemplate($submission_id, $email) {
    $submission = WebformSubmission::load($submission_id);
  
  // Replace special characters in the email address to generate the document name
    $document_name = preg_replace(
      '/[^a-zA-Z0-9 ]/m', // 1. regex to apply
      '_',                 // 2. replacement for regex matches 
      $email             // 3. the original string
    );

    if (!$submission) {
      $this->loggerFactory->get('fa')->error("Webform submission with ID %id not found.", ['%id' => $submission_id]);
      return false;
    }

     // Get the webform ID from the submission
    $webform_id = $submission->getWebform()->id();
    $template_path = 'private://fa_documents/' . $webform_id . '.docx';


    // Get configration key values
    $configFactory = \Drupal::service('config.factory');
    $config = $configFactory->get('fa.template_mapping');
    $values = $config->getRawData();

    if (!isset($values[$webform_id])) {
      $this->loggerFactory->get('fa')->error("Template values for webform %webform not found.", ['%webform' => $webform_id]);
      return false;
    }

    // Load the template
    $template = new TemplateProcessor($template_path);

    foreach ($values[$webform_id] as $field_key => $placeholder) {
      $field_value = $submission->getElementData($field_key);
      $template->setValue($placeholder, $field_value);
    }

    // Save the modified template
    $output_path = 'private://fa_processed_docs/' . $document_name . '_' . $submission_id . '.docx';
    $template->saveAs($output_path);

    return $output_path;
  }

}
