<?php


namespace Drupal\fa\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebformSendEmail extends ControllerBase
{
    public function webformEmailHandler()
    {
        $request = \Drupal::request();
        $webform_data = explode('&', $request->getContent());
        $message_body = [];;
        foreach ($webform_data as $item) {
            list($key, $value) = explode('=', $item);
            $decoded_value = urldecode($value); // Decode the URL-encoded value
            $key = str_replace('_', ' ', ucwords($key)); // Capitalize the key and replace underscores with spaces
        
            array_push($message_body, "$key : $decoded_value");
        }
        // Capturing the last element since the webform name is added as the last field
        $webform_name = end($message_body);
        $email_subject = explode(': ', $webform_name)[1];
        
        array_unshift($message_body, 'The following are the details entered in the ' . $email_subject . ' form: <br/>');
        $message_body = implode('<br/>', $message_body);
        $message_body .= '<br/><br/>Warm Regards,<br/>';
        $message_body .= 'FA';
        $config = \Drupal::configFactory()->get('fa.template_mapping');
        $businessEmail = $config->get('fa_business_email');
        \Drupal::logger('fa_webform_email')->notice('<pre>' . print_r($message_body, TRUE) . '</pre>');

        $boundary = md5(uniqid(time()));

    // $headers = 'From: FA <info@firmadvice.ae>' . "\r\n" .
    //   'Reply-To: info@firmadvice.ae' . "\r\n" .
    //   'X-Mailer: PHP/' . phpversion() . "\r\n" .
    //   'MIME-Version: 1.0' . "\r\n" .
        $header_content_type = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        \Drupal::service('fa.common_service')->sendEmailWithAttachment($businessEmail, 'Enquiry from: ' . $email_subject, $message_body, [], $header_content_type);
        return new JsonResponse($message_body);

    }
}