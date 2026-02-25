<?php
declare(strict_types=1);

namespace App\Http\Controllers;

//require '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendEmailController {

    /**
     * Send email using cURL (e.g. via Mailgun, SendGrid API, or a custom endpoint)
     * This implementation assumes a generic POST request structure.
     * You should customize the URL and parameters based on your actual provider.
     */
    public static function sendViaCurl(string $to, string $subject, string $body): bool {
        $url = $_ENV['EMAIL_API_URL'] ?? '';
        $apiKey = $_ENV['EMAIL_API_KEY'] ?? '';

        if (empty($url)) {
            // Log error: Email API URL not configured
            return false;
        }

        $data = [
            'to' => $to,
            'subject' => $subject,
            'html' => $body,
            // 'from' => $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@example.com', // Some APIs need this
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // If using Basic Auth (like Mailgun)
        if (!empty($apiKey)) {
            curl_setopt($ch, CURLOPT_USERPWD, "api:" . $apiKey);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
    
    public static function sendViaZeptomail(string $to, string $subject, string $body): bool {
        $url = $_ENV['EMAIL_API_URL'] ?? '';
        $apiKey = $_ENV['EMAIL_API_KEY'] ?? '';
        if (empty($url) || empty($apiKey)) {
            // Log error: Email API URL not configured
            return false;
        }
        $sender = $_ENV['EMAIL_SENDER'] ?? 'noreply@example.com';
        $data = [
            'to' => $to,
            'subject' => $subject,
            'htmlbody' => $body,
            'from' => $sender, 
        ];
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => '{
        "from": { "address": "'.$sender.'"},
        "to": [{"email_address": {"address": "'.$to.'"}}],
        "subject":"'.$subject.'",
        "htmlbody":"'.$body.'",
        }',
            CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Zoho-enczapikey ".$apiKey."",
                "cache-control: no-cache",
                "content-type: application/json",
            ),
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);

        return $httpCode >= 200 && $httpCode < 300;
    }


    public static function sendViaSmtp(string $to, string $subject, string $body): bool {
        $host = $_ENV['SMTP_HOST'] ?? '';
        $port = $_ENV['SMTP_PORT'] ?? '';
        $user = $_ENV['SMTP_USER'] ?? '';
        $pass = $_ENV['SMTP_PASS'] ?? '';
        $from = $_ENV['EMAIL_SENDER'] ?? '';
        $sendername = $_ENV['SITE_NAME'] ?? 'OIDC Service';
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();                                            
            $mail->Host       = $host;                     
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = $user;               
            $mail->Password   = $pass;                               
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = $port;                                    
        
            // Recipients
            $mail->setFrom($from, $sendername);
            $mail->addAddress($to);
        
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
        
            $mail->send();
            return true;
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            return false;
        }
        
        return false;
    }

    /**
     * Main entry point to send email.
     * Currently defaults to cURL, but can be switched based on config.
     */
    public static function send(string $to, string $subject, string $body): bool {
        $method = $_ENV['EMAIL_SEND_METHOD'] ?? 'curl';

        if ($method === 'smtp') {
            return self::sendViaSmtp($to, $subject, $body);
        } else if ($method === 'zoho') {
            return self::sendViaZeptomail($to, $subject, $body);
        }
        
        return self::sendViaCurl($to, $subject, $body);
    }
}
