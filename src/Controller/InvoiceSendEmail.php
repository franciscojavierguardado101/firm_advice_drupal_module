<?php


namespace Drupal\fa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Drupal\node\Entity\Node;


class InvoiceSendEmail extends ControllerBase
{
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

    protected $messenger;

    public function __construct(MessengerInterface $messenger, MailManagerInterface $mail_manager, LanguageManagerInterface $language_manager) {
        $this->messenger = $messenger;
        $this->mailManager = $mail_manager;
        $this->languageManager = $language_manager;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
            $container->get('messenger'),
            $container->get('plugin.manager.mail'),
            $container->get('language_manager'),
        );
    }

    public function sendEmailInvoice($nid) {
        $data = \Drupal::service('fa.common_service')->emailInvoice($nid);
        $invoice_number = Node::load($nid)->get('field_invoice_number')->value;
        $email_subject = 'Invoice # ' . $invoice_number . ' Generated for ' . ' ' . $data[2];
        $langcode = $this->languageManager->getDefaultLanguage()->getId();
        // Prepare the message without sending.
        $message = $this->mailManager->mail('firmadvice_invoice', 'invoice', $data[1], $langcode, [], NULL, FALSE);
        $message['subject'] = $email_subject;
        $message['body'] = $data[0];
        $finance_email = 'finance@firmadvice.ae';
        $headers = array(
            'From' => $finance_email,
            'Bcc' => $finance_email,
            'Reply-To' => $finance_email,
            'Content-Type' => 'text/html; charset=UTF-8'
        );
        
        $message['headers'] = $headers;
    
        // Send the message using PHPMailer SMTP.
        $phpMailerSmtp = $this->mailManager->createInstance('phpmailer_smtp');
        $phpMailerSmtp->isHTML(TRUE);
        $phpMailerSmtp->ContentType = 'text/html';
        $phpMailerSmtp->mail($message);

        $this->messenger->addMessage($this->t('Email sent successfully'));
        return new RedirectResponse('/node' . '/' . $nid);
    }
}