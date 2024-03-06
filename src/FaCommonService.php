<?php

namespace Drupal\fa;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Generates a Template.
 */
class FaCommonService
{

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  protected $messenger;
  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  protected $configFactory;
  protected $currentUser;

  /**
   * Constructs a FaCommonService instance.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $logger_factory,
    RendererInterface $renderer,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    MailManagerInterface $mailManager,
    ConfigFactoryInterface $configFactory,
    AccountInterface $current_user
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $logger_factory;
    $this->renderer = $renderer;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
    $this->mailManager = $mailManager;
    $this->configFactory = $configFactory;
    $this->currentUser = $current_user;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('renderer'),
      $container->get('messenger'),
      $container->get('language_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * Creates a User.
   *
   *
   * @return string|false
   *   The path to register a user or false if there's an error.
   */
  public function createUser($full_name, $email)
  {
    //  Handling existing user
    $existing_user = user_load_by_mail($email);
    if ($existing_user) {
      // @todo: handle existing user
      return FALSE;
    }

    // Create a new user entity.
    $user = User::create();

    // Set the user properties.
    $user->setUsername($email);
    $user->setEmail($email);
    $user->set('field_name', $full_name);
    // Set the user as active.
    $user->activate();
    // Save the user.
    $user->save();

    // Generate a one-time login link.
    $login_url = user_pass_reset_url($user);

    // Return the login URL.
    return $login_url;
  }

  /**
   * Send an email with an attachment using the mail() function.
   *
   * @param string $to
   *   The recipient's email address.
   * @param string $subject
   *   The email subject.
   * @param string $message
   *   The email message.
   * @param string $attachment_path
   *   The file path to the attachment.
   *
   * @return bool
   *   TRUE if the email was sent successfully, FALSE otherwise.
   */
  function sendEmailWithAttachment($to, $subject, $message, $attachment_paths)
  {
    $langcode = $this->languageManager->getDefaultLanguage()->getId();

    // Prepare the message without sending.
    $mail_message = $this->mailManager->mail('firmadvice_mail', 'fa_mail', $to, $langcode, [], NULL, FALSE);
    $mail_message['subject'] = $subject;
    
    $mail_message['body'] = $message;
    $mail_message['headers']['Content-Type'] = 'text/html; charset=UTF-8';

    // Send the message using PHPMailer SMTP.
    $phpMailerSmtp = $this->mailManager->createInstance('phpmailer_smtp');
    $phpMailerSmtp->isHTML(TRUE);
    $phpMailerSmtp->ContentType = 'text/html';
    foreach ($attachment_paths as $attachment_path) {
      $phpMailerSmtp->addAttachment($attachment_path);
    }
    $mail_status = $phpMailerSmtp->mail($mail_message);

    return $mail_status;
  }

  /**
   * Handling webform submission
   */

  public function processWebformSubmission($webform_id, $sid)
  {
    // Check if user is anonymous
    $is_anonymous = $this->currentUser->isAnonymous();

    $webform = \Drupal\webform\Entity\Webform::load($webform_id);
    // Get the webform title.
    $webform_title = $webform->label();
    $webform_submission = WebformSubmission::load($sid);
    $data = $webform_submission->getData();
    $pricing_package_name = $data['pricing_package'];
    if ($pricing_package_name == "") {
      $response = new RedirectResponse('/');
      \Drupal::messenger()->addError(t('Please select a package'));
      return $response->send();
    }
    $user_email = $data['e_mail_address'];
    $full_name = $data['your_name'];
    // Get total price for the given submission.
    $total_price = $data[$data['pricing_package']];
    foreach ($data['additional_documents_v2'] as $additional_doc) {
      $total_price += $data[$additional_doc . '_price'];
    }

    // Add VAT @5%;
    $total_price += 0.05 * $total_price;
    // Add Admin Fee @ 2.9%
    $total_price += 0.029 * $total_price;

    // Convert underscores to spaces
    $pricing_package_name = str_replace('_', ' ', $pricing_package_name);

    // Capitalize the first letter of each word
    $pricing_package_name = ucwords($pricing_package_name);

    if ($is_anonymous) {
      $user_login_link = $this->createUser($full_name, $user_email);
      if ($user_login_link) {
        $subject = 'Your account has been created';
        $message = 'Hi ' . $full_name . ',' . '<br/>' . '<br/> Your account has been created on FA. You can log in using the following link: <br/>' . $user_login_link . '<br/>' . '<br/> Warm Regards, <br/> FA';
        $attachment_paths = '';
        // Disabling email service till launch
        $this->sendEmailWithAttachment($user_email, $subject, $message, $attachment_paths);
      }
    }
    $stripe_payment = \Drupal::service('fa_stripe.service');
    $stripe_cid = $stripe_payment->createStripeCustomer($user_email, $full_name);
    $user = user_load_by_mail($user_email);
    $user->set('field_stripe_customer_id', $stripe_cid);
    $user->save();
    $stripe_checkout_link = $stripe_payment->createCheckout($stripe_cid, round($total_price, 2) * 100, $webform_title . ' ' . '-' . ' ' . $pricing_package_name . ' ' . 'Package', $sid);
    $response = new RedirectResponse($stripe_checkout_link);

    $response->send();
  }

  /**
   * Handling invoice number generation
   */
   public function generateInvoiceNumber() {
    $format = 'FA-' . date('my') .'-3476';
    $number = $this->getIncrementedNumber();
    $invoice_number = $format . $number;
    $invoice_config = $this->configFactory->getEditable('fa_stripe.settings');
    // Update the last invoice number in the settings.
    $invoice_config->set('last_invoice_number', $number)->save();

    return $invoice_number;
   }

   protected function getIncrementedNumber() {
    // Get the current month and year.
    $current_month_year = date('my');

    $invoice_config = $this->configFactory->getEditable('fa_stripe.settings');


    // Get the last used month and year.
    $last_month_year = $invoice_config->get('last_invoice_month_year');

    // If the current month is different, reset the counter.
    if ($current_month_year != $last_month_year) {
      // Settings::set('last_invoice_month_year', $current_month_year);
      $invoice_config->set('last_invoice_month_year', $current_month_year)->save();
      $invoice_config->set('invoice_counter', 1)->save();
      return 1;
    }

    // Increment the counter.
    $counter = $invoice_config->get('invoice_counter') + 1;
    $invoice_config->set('invoice_counter', $counter)->save();

    return $counter;
  }
  
  public function emailInvoice($nid) {
    // Load the node.
    $node = $this->entityTypeManager->getStorage('node')->load($nid);


    if ($node) {
      // View mode. You can change 'full' to the desired view mode.
      $view_mode = 'full';

      // View builder for rendering nodes.
      $view_builder = $this->entityTypeManager->getViewBuilder('node');

      // Render the node using the view mode.
      $render_array = $view_builder->view($node, $view_mode);

      // Render the render array into HTML.
      $html = $this->renderer->renderRoot($render_array);
      // Extract content within the "invoice" div.
      $invoice_content = $this->extractInvoiceContent($html);
      $additional_content = '<html><head>
              <style type="text/css">
              body {
                  font-family: arial, sans-serif;
                  padding: 0;
                  margin: 0;
                  color: #000000 !important;
                  background-color: #fff;
              }
              #company,
              #company td {
                  border: none;
              }
              #invoice {
                  width: 90%;
                  padding: 15px;
                  margin: 0 auto;
                  background-color: #fff;
              }
              #billship tr,
              #billship td {
                  border: none !important;
              }
              
              #items th,
              #items td {
                  border: 2px solid rgba(0,169,206,1);
              }
              
              #billship,
              #company,
              #items {
                  width: 100%;
                  border-collapse: collapse;
              }
              #billship td,
              #items td,
              #items th {
                  padding: 15px;
              }
              #company,
              #billship {
                  margin-bottom: 30px;
              }
              #company img {
                  max-width: 250px;
                  height: auto;
              }
              #bigi {
                  font-size: 32px;
                  color: black;
                  font-weight: bold;
                  margin-top: 10px;
              }
              #billship {
                  background: linear-gradient(340deg, rgba(117,59,189,1) 18%, rgba(0,169,206,1) 96.21%, rgba(0,169,206,1) 100%);
                  color: #fff;
              }
              #billship td {
                  width: 66%;
              }
              #items th {
                  text-align: left;
                  border-top: 2px solid rgba(0,169,206,1);
                  border-bottom: 2px solid rgba(0,169,206,1);
              }
              #items td {
                  border-bottom: 1px solid rgba(0,169,206,1);
              }
              .idesc {
                  color: #ca3f3f;
              }
              .ttl {
                  font-weight: 700;
              }
              .right {
                  text-align: right;
              }
              #notes {
                  margin-top: 30px;
                  font-size: 0.95em;
              }
              #footer {
                  display: flex;
                  gap: 150px;
                  border-top: 1px solid  #00a9ce;
                  padding-top: 25px;
              }
              tr.ttl:last-child {
                  background: linear-gradient(340deg, rgba(117,59,189,1) 18%, rgba(0,169,206,1) 96.21%, rgba(0,169,206,1) 100%);
                  color: #fff;
              }
              
              tr.ttl:last-child > td {
                  border: none !important;
              }
              
              #paid, #pay-now {
                  text-align: center;
                  padding-bottom: 20px;
                  padding-top: 20px;
              }
              #pay-now {
                  margin-top: 40px;
              }
              #pay-now a {
                  background: #28a745;
                  padding: 15px 30px;
                  text-decoration: none;
                  color: white;
                  border-radius: 50px;
              }
              #download a {
                  background: var(--green);
                  padding: 15px 10px;
              }
              #send-email a {
                  background: var(--green);
                  padding: 15px 10px;
              }
              .footer {
                display: flex;
                border-top: 1px solid #00a9ce;
                padding-top: 25px;
                justify-content: space-between;
              }
              .gtotal {
                background: linear-gradient(340deg, rgba(117,59,189,1) 18%, rgba(0,169,206,1) 96.21%, rgba(0,169,206,1) 100%);
                color: #fff; 
              }
              @media only screen and (max-width: 800px) {
                #invoice {
                  width: auto;
                 }
              }
              @media only screen and (max-width: 600px) {
                #bigi {
                  font-size: 16px;
                  color: black;
               }
               #billship td {
                font-size: 10px;
               }
               #invoice {
                width: auto;
               }
               #items th {
                font-size: 9px;
               }
               #items td {
                font-size: 9px;
               }
               .footer-item {
                font-size: 8px;
               }
               #billship {
                margin-bottom: 0px;
               }
               #company {
                   margin-bottom: 0px;
               }
              }
              </style>
             </head>' . $invoice_content . '</html>';
      $recipient = $node->get('field_customer_email')->value;
      $customer_name = $node->get('field_customer_name')->value;
      return [$additional_content, $recipient, $customer_name];
    }
  }


  /**
   * Extracts content within the "invoice" div from HTML.
   *
   * @param string $html
   *   The HTML content.
   *
   * @return string
   *   The content within the "invoice" div.
   */
  protected function extractInvoiceContent($html)
  {
    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_use_internal_errors(false);

    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query('//div[@id="invoice"]');

    $invoice_content = '';

    foreach ($nodes as $node) {
      $invoice_content .= $doc->saveHTML($node);
    }

    return $invoice_content;
  }
}
