<?php
// Centralized Mailer Helper with SMTP, Native PHP mail() Fallback, and Dynamic HTML Email Templates
require_once __DIR__ . '/config.php';

function getSmtpConfig()
{
    return [
        'host' => omgGetEnv('SMTP_HOST'),
        'port' => (int) (omgGetEnv('SMTP_PORT', '465')),
        'user' => omgGetEnv('SMTP_USER'),
        'pass' => omgGetEnv('SMTP_PASS'),
        'from' => omgGetEnv('SMTP_FROM', 'info@ohmygudness.in'),
        'from_name' => omgGetEnv('SMTP_FROM_NAME', 'OH MY GUDNESS'),
        'secure' => strtolower(omgGetEnv('SMTP_SECURE', 'ssl'))
    ];
}

/**
 * Sends HTML Email using SMTP socket client or native PHP mail() fallback
 */
function sendEmail($to, $subject, $htmlBody)
{
    $config = getSmtpConfig();

    // 1. Try sending via SMTP socket if credentials exist
    if (!empty($config['host']) && !empty($config['user']) && !empty($config['pass'])) {
        try {
            $smtpResult = sendViaSmtpSocket($to, $subject, $htmlBody, $config);
            if ($smtpResult) {
                return true;
            }
        } catch (Exception $e) {
            error_log("SMTP Mail send failed: " . $e->getMessage());
        }
    }

    // 2. Fallback to native PHP mail() function
    $fromHeader = !empty($config['from_name'])
        ? '="UTF-8"B?' . base64_encode($config['from_name']) . '?= <' . $config['from'] . '>'
        : $config['from'];

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromHeader}\r\n";
    $headers .= "Reply-To: {$config['from']}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    return @mail($to, $subject, $htmlBody, $headers, "-f" . $config['from']);
}

/**
 * Socket-based SMTP Client (supports SSL port 465 & TLS port 587 without external libraries)
 */
function sendViaSmtpSocket($to, $subject, $htmlBody, $config)
{
    $host = $config['host'];
    $port = $config['port'];
    $user = $config['user'];
    $pass = $config['pass'];
    $from = $config['from'];
    $fromName = $config['from_name'];

    $prefix = ($config['secure'] === 'ssl' || $port === 465) ? 'ssl://' : '';
    $socketHost = $prefix . $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client($socketHost . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new Exception("Could not connect to SMTP host {$host}:{$port} ({$errstr})");
    }

    $read = function () use ($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) === ' ')
                break;
        }
        return $response;
    };

    $send = function ($cmd) use ($socket, $read) {
        fputs($socket, $cmd . "\r\n");
        return $read();
    };

    $read();
    $send("EHLO " . gethostname());

    if ($config['secure'] === 'tls' || $port === 587) {
        $send("STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        $send("EHLO " . gethostname());
    }

    $authRes = $send("AUTH LOGIN");
    if (strpos($authRes, '334') === 0) {
        $send(base64_encode($user));
        $passRes = $send(base64_encode($pass));
        if (strpos($passRes, '235') !== 0) {
            fclose($socket);
            throw new Exception("SMTP Authentication failed: " . trim($passRes));
        }
    }

    $send("MAIL FROM: <{$from}>");
    $send("RCPT TO: <{$to}>");
    $send("DATA");

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Date: " . date('r') . "\r\n\r\n";

    $messageData = $headers . $htmlBody . "\r\n.";
    $sendRes = $send($messageData);

    $send("QUIT");
    fclose($socket);

    return strpos($sendRes, '250') === 0;
}

/**
 * Returns Default Dynamic Placeholders
 */
function getDefaultEmailPlaceholders()
{
    $baseUrl = getenv('BASE_URL') ?: (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'https://ohmygudness.in');
    return [
        '{{APP_NAME}}' => getenv('APP_NAME') ?: 'OH MY GUDNESS',
        '{{SUPPORT_EMAIL}}' => getenv('SUPPORT_EMAIL') ?: 'info@ohmygudness.in',
        '{{WEBSITE_URL}}' => getenv('WEBSITE_URL') ?: 'https://ohmygudness.in',
        '{{LOGO_URL}}' => getenv('LOGO_URL') ?: ($baseUrl . '/backend/assets/logo.png'),
        '{{CURRENT_YEAR}}' => date('Y')
    ];
}

/**
 * MASTER DYNAMIC HTML EMAIL TEMPLATE RENDERER
 */
function renderEmailTemplate($templateType, $customPlaceholders = [])
{
    $defaults = getDefaultEmailPlaceholders();
    $placeholders = array_merge($defaults, $customPlaceholders);

    // Default Fallbacks
    if (!isset($placeholders['{{USER_NAME}}']) || empty($placeholders['{{USER_NAME}}'])) {
        $placeholders['{{USER_NAME}}'] = 'Valued Guest';
    }
    if (!isset($placeholders['{{OTP_CODE}}'])) {
        $placeholders['{{OTP_CODE}}'] = '000000';
    }

    $appName = $placeholders['{{APP_NAME}}'];
    $userName = $placeholders['{{USER_NAME}}'];
    $otpCode = $placeholders['{{OTP_CODE}}'];
    $supportEmail = $placeholders['{{SUPPORT_EMAIL}}'];
    $websiteUrl = $placeholders['{{WEBSITE_URL}}'];
    $logoUrl = $placeholders['{{LOGO_URL}}'];
    $currentYear = $placeholders['{{CURRENT_YEAR}}'];

    // Template Configurations
    if ($templateType === 'email_verification') {
        $subject = "Verify Your " . $appName . " Account";
        $preheader = "Your 6-digit email verification code is " . $otpCode;
        $heading = "Verify Your Email Address";
        $subHeading = "Welcome to " . htmlspecialchars($appName) . "! To complete your registration and activate your account, please enter the verification code below.";
        $showOtp = true;
        $validityText = "⏱️ Code valid for <strong>10 minutes</strong>";
        $securityNote = "For your protection, never share this verification code with anyone. Our concierge team will never ask for your code.";
        $ignoreNote = "If you did not create an account with us, you can safely ignore this email.";
        $ctaButton = null;

    } elseif ($templateType === 'forgot_password') {
        $subject = "Reset Your " . $appName . " Password";
        $preheader = "Your 6-digit password reset security code is " . $otpCode;
        $heading = "Password Reset Request";
        $subHeading = "We received a request to reset the password for your account. Please use the 6-digit security code below to set your new password.";
        $showOtp = true;
        $validityText = "⏱️ Code valid for <strong>10 minutes</strong>";
        $securityNote = "If you did not request a password reset, please ignore this message or contact support immediately if you suspect unauthorized activity.";
        $ignoreNote = "This code is single-use and will automatically expire after 10 minutes.";
        $ctaButton = null;

    } else { // welcome
        $subject = "Welcome to " . $appName . " | Luxury Floral & Experiences";
        $preheader = "Welcome to " . $appName . "! Your account has been verified.";
        $heading = "Welcome to " . htmlspecialchars($appName);
        $subHeading = "Your account has been successfully verified! We are thrilled to welcome you to our exclusive world of luxury floral arrangements, bespoke gift hampers, and unforgettable surprise experiences.";
        $showOtp = false;
        $validityText = "✨ Your account is active & fully verified";
        $securityNote = "You can now sign in to track live orders and save custom preferences";
        $ignoreNote = "Thank you for choosing " . htmlspecialchars($appName) . ".";
        $ctaButton = [
            'label' => 'Explore Collections',
            'url' => $websiteUrl . '/products'
        ];
    }

    $otpHtml = '';
    if ($showOtp) {
        $otpHtml = '
        <!-- OTP Card -->
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 24px 0;">
            <tr>
                <td align="center">
                    <div style="background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 2px dashed #D4AF37; border-radius: 16px; padding: 24px; display: inline-block; width: 85%; max-width: 380px; box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);">
                        <span style="font-family: \'Courier New\', Courier, monospace; font-size: 38px; font-weight: bold; color: #D4AF37; letter-spacing: 10px; display: block; text-shadow: 0 0 12px rgba(212, 175, 55, 0.4);">' . htmlspecialchars($otpCode) . '</span>
                    </div>
                </td>
            </tr>
        </table>';
    }

    $ctaHtml = '';
    if ($ctaButton) {
        $ctaHtml = '
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 28px 0 12px 0;">
            <tr>
                <td align="center">
                    <a href="' . htmlspecialchars($ctaButton['url']) . '" target="_blank" style="background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%); color: #070D0A; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; font-size: 14px; font-weight: bold; text-decoration: none; padding: 14px 36px; border-radius: 12px; display: inline-block; letter-spacing: 1px; text-transform: uppercase; box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);">' . htmlspecialchars($ctaButton['label']) . '</a>
                </td>
            </tr>
        </table>';
    }

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
            table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
            img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
            body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #090D16; color: #E2E8F0; }
            @media screen and (max-width: 600px) {
                .email-container { width: 100% !important; padding: 16px !important; }
                .content-padding { padding: 24px 18px !important; }
                .otp-code { font-size: 32px !important; letter-spacing: 6px !important; }
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #090D16; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;">
        <!-- Hidden Preheader Text -->
        <div style="display: none; font-size: 1px; color: #090D16; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
            ' . htmlspecialchars($preheader) . '
        </div>

        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #090D16; table-layout: fixed;">
            <tr>
                <td align="center" style="padding: 40px 10px;">
                    <!-- Outer Card Wrapper -->
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 560px; background-color: #0F172A; border: 1px solid #334155; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.6);">
                        
                        <!-- Header Banner -->
                        <tr>
                            <td align="center" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); padding: 32px 24px; border-bottom: 1px solid #334155;">
                                <a href="' . htmlspecialchars($websiteUrl) . '" target="_blank" style="text-decoration: none;">
                                    <img src="' . htmlspecialchars($logoUrl) . '" alt="' . htmlspecialchars($appName) . '" style="max-height: 52px; width: auto; display: block;" onerror="this.onerror=null; this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">
                                    <div style="display: none; font-family: \'Georgia\', serif; font-size: 24px; font-weight: bold; color: #D4AF37; letter-spacing: 3px; text-transform: uppercase;">' . htmlspecialchars($appName) . '</div>
                                </a>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td class="content-padding" style="padding: 36px 32px; text-align: center; background-color: #0F172A;">
                                <p style="font-size: 13px; font-weight: 600; color: #D4AF37; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 12px 0;">' . htmlspecialchars($appName) . ' Security</p>
                                
                                <h1 style="font-family: \'Georgia\', serif; font-size: 24px; font-weight: bold; color: #F8FAFC; margin: 0 0 12px 0; line-height: 1.3;">' . htmlspecialchars($heading) . '</h1>
                                
                                <p style="font-size: 14px; color: #CBD5E1; margin: 0 0 20px 0; line-height: 1.6;">Hello <strong style="color: #F8FAFC;">' . htmlspecialchars($userName) . '</strong>,</p>
                                
                                <p style="font-size: 13px; color: #94A3B8; margin: 0 0 24px 0; line-height: 1.6;">' . htmlspecialchars($subHeading) . '</p>

                                ' . $otpHtml . '
                                ' . $ctaHtml . '

                                <!-- Validity Badge -->
                                <div style="margin: 20px 0; display: inline-block; background-color: rgba(212, 175, 55, 0.1); border: 1px solid rgba(212, 175, 55, 0.3); border-radius: 12px; padding: 10px 18px; font-size: 12px; color: #F1F5F9;">
                                    ' . $validityText . '
                                </div>

                                <!-- Security Notice Box -->
                                <div style="background-color: #1E293B; border-left: 3px solid #D4AF37; border-radius: 8px; padding: 14px 16px; margin: 20px 0 10px 0; text-align: left;">
                                    <p style="font-size: 12px; color: #CBD5E1; margin: 0; line-height: 1.5;">🔒 <strong>Security Note:</strong> ' . htmlspecialchars($securityNote) . '</p>
                                </div>

                                <p style="font-size: 11px; color: #64748B; margin: 16px 0 0 0; line-height: 1.5;">' . htmlspecialchars($ignoreNote) . '</p>
                            </td>
                        </tr>

                        <!-- Professional Footer -->
                        <tr>
                            <td align="center" style="background-color: #090D16; padding: 24px 20px; border-top: 1px solid #1E293B; text-align: center;">
                                <p style="font-size: 12px; font-weight: bold; color: #D4AF37; margin: 0 0 6px 0; letter-spacing: 1px;">' . htmlspecialchars($appName) . '</p>
                                <p style="font-size: 11px; color: #64748B; margin: 0 0 12px 0;">Luxury Floral Arrangements & Bespoke Surprises</p>
                                
                                <p style="font-size: 11px; color: #94A3B8; margin: 0 0 14px 0;">
                                    Need assistance? Contact our team at 
                                    <a href="mailto:' . htmlspecialchars($supportEmail) . '" style="color: #D4AF37; text-decoration: none; font-weight: 500;">' . htmlspecialchars($supportEmail) . '</a>
                                </p>

                                <p style="font-size: 11px; color: #64748B; margin: 0 0 16px 0;">
                                    <a href="' . htmlspecialchars($websiteUrl) . '" target="_blank" style="color: #64748B; text-decoration: none;">Website</a> &bull; 
                                    <a href="' . htmlspecialchars($websiteUrl) . '/privacy" target="_blank" style="color: #64748B; text-decoration: none;">Privacy Policy</a> &bull; 
                                    <a href="' . htmlspecialchars($websiteUrl) . '/terms" target="_blank" style="color: #64748B; text-decoration: none;">Terms of Service</a>
                                </p>

                                <p style="font-size: 10px; color: #475569; margin: 0;">
                                    &copy; ' . htmlspecialchars($currentYear) . ' ' . htmlspecialchars($appName) . '. All rights reserved.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    return $html;
}

/**
 * Convenience Helper Functions
 */
function buildEmailVerificationTemplate($userName, $otpCode)
{
    return renderEmailTemplate('email_verification', [
        '{{USER_NAME}}' => $userName,
        '{{OTP_CODE}}' => $otpCode
    ]);
}

function buildForgotPasswordTemplate($userName, $otpCode)
{
    return renderEmailTemplate('forgot_password', [
        '{{USER_NAME}}' => $userName,
        '{{OTP_CODE}}' => $otpCode
    ]);
}

function buildWelcomeEmailTemplate($userName)
{
    return renderEmailTemplate('welcome', [
        '{{USER_NAME}}' => $userName
    ]);
}

/**
 * Builds HTML Order Confirmation Email for the Customer
 */
function buildOrderConfirmationEmail($order, $items = [])
{
    $appName = 'Oh My Gudness';
    $websiteUrl = 'https://ohmygudness.in';
    $supportEmail = 'info@ohmygudness.in';
    $supportPhone = '+91 8147736396';
    $orderId = htmlspecialchars($order['id'] ?? 'N/A');
    $customerName = htmlspecialchars($order['customer_name'] ?? 'Valued Client');
    $totalAmount = number_format((float)($order['total_amount'] ?? 0), 2);
    $deliveryAddress = htmlspecialchars($order['delivery_address'] ?? 'Bangalore Delivery');
    $deliveryDate = !empty($order['delivery_date']) ? date('D, d M Y', strtotime($order['delivery_date'])) : 'Express Same-Day';
    $deliveryTime = htmlspecialchars($order['delivery_time'] ?? 'Standard Slot');
    $paymentMethod = strtoupper(htmlspecialchars($order['payment_method'] ?? 'Online Payment'));

    $itemsRows = '';
    foreach ($items as $item) {
        $pName = htmlspecialchars($item['name'] ?? ($item['product_name'] ?? 'Luxury Arrangement'));
        $qty = (int)($item['quantity'] ?? 1);
        $itemTotal = number_format((float)($item['price'] ?? 0) * $qty, 2);

        $itemsRows .= '
        <tr style="border-bottom: 1px solid #1E293B;">
            <td style="padding: 12px 6px; text-align: left; font-size: 13px; color: #F8FAFC;">' . $pName . '</td>
            <td style="padding: 12px 6px; text-align: center; font-size: 13px; color: #CBD5E1;">' . $qty . '</td>
            <td style="padding: 12px 6px; text-align: right; font-size: 13px; color: #D4AF37; font-weight: 600;">₹' . $itemTotal . '</td>
        </tr>';
    }

    $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Order Confirmed #' . $orderId . '</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #090D16; font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #090D16;">
            <tr>
                <td align="center" style="padding: 30px 10px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #0F172A; border: 1px solid #334155; border-radius: 20px; overflow: hidden; box-shadow: 0 16px 36px rgba(0,0,0,0.5);">
                        <tr>
                            <td align="center" style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); padding: 28px; border-bottom: 1px solid #334155;">
                                <div style="font-family: \'Georgia\', serif; font-size: 22px; font-weight: bold; color: #D4AF37; letter-spacing: 3px; text-transform: uppercase;">OH MY GUDNESS</div>
                                <div style="font-size: 11px; color: #94A3B8; letter-spacing: 1px; margin-top: 4px; text-transform: uppercase;">Luxury Floristry &amp; Surprise House &bull; Bangalore</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 32px 28px; background-color: #0F172A;">
                                <div style="display: inline-block; background-color: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); border-radius: 999px; padding: 6px 14px; font-size: 12px; color: #4ADE80; font-weight: 600; margin-bottom: 16px;">
                                    ✓ Order Successfully Placed &amp; Confirmed
                                </div>
                                <h1 style="font-family: \'Georgia\', serif; font-size: 24px; color: #F8FAFC; margin: 0 0 10px 0;">Thank You for Your Order!</h1>
                                <p style="font-size: 14px; color: #CBD5E1; line-height: 1.6; margin: 0 0 20px 0;">
                                    Dear <strong style="color: #F8FAFC;">' . $customerName . '</strong>,<br>
                                    Your order has been received and forwarded to our floral atelier. Our master florists are curating your fresh stems with utmost precision.
                                </p>

                                <div style="background-color: #1E293B; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; border: 1px solid #334155;">
                                    <div style="font-size: 12px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Order Identification</div>
                                    <div style="font-size: 16px; font-weight: bold; color: #D4AF37; font-family: monospace;">#' . $orderId . '</div>
                                </div>

                                <h2 style="font-family: \'Georgia\', serif; font-size: 16px; color: #F8FAFC; margin: 0 0 12px 0; border-bottom: 1px solid #334155; padding-bottom: 6px;">Order Summary</h2>
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #334155;">
                                            <th style="padding: 8px 6px; text-align: left; font-size: 11px; color: #94A3B8; text-transform: uppercase;">Item</th>
                                            <th style="padding: 8px 6px; text-align: center; font-size: 11px; color: #94A3B8; text-transform: uppercase;">Qty</th>
                                            <th style="padding: 8px 6px; text-align: right; font-size: 11px; color: #94A3B8; text-transform: uppercase;">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ' . $itemsRows . '
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2" style="padding: 14px 6px; text-align: right; font-size: 14px; font-weight: bold; color: #F8FAFC;">Total Amount Paid:</td>
                                            <td style="padding: 14px 6px; text-align: right; font-size: 18px; font-weight: bold; color: #D4AF37;">₹' . $totalAmount . '</td>
                                        </tr>
                                    </tfoot>
                                </table>

                                <h2 style="font-family: \'Georgia\', serif; font-size: 16px; color: #F8FAFC; margin: 24px 0 12px 0; border-bottom: 1px solid #334155; padding-bottom: 6px;">Delivery Details</h2>
                                <div style="background-color: #1E293B; border-radius: 12px; padding: 16px 20px; font-size: 13px; line-height: 1.6; color: #CBD5E1;">
                                    <div style="margin-bottom: 8px;">📍 <strong>Delivery Address:</strong> ' . $deliveryAddress . '</div>
                                    <div style="margin-bottom: 8px;">📅 <strong>Delivery Date:</strong> ' . $deliveryDate . '</div>
                                    <div style="margin-bottom: 8px;">⏰ <strong>Time Slot:</strong> ' . $deliveryTime . '</div>
                                    <div>💳 <strong>Payment Method:</strong> ' . $paymentMethod . ' (Paid)</div>
                                </div>

                                <div style="text-align: center; margin: 32px 0 12px 0;">
                                    <a href="' . $websiteUrl . '/#/orders" style="display: inline-block; background: linear-gradient(135deg, #F5C518 0%, #D4AF37 100%); color: #0F172A; text-decoration: none; padding: 14px 28px; border-radius: 999px; font-weight: bold; font-size: 14px; box-shadow: 0 4px 16px rgba(212, 175, 55, 0.4);">
                                        View Live Order Status &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="background-color: #090D16; padding: 20px; border-top: 1px solid #1E293B; font-size: 11px; color: #64748B;">
                                <p style="margin: 0 0 6px 0;">Need concierge assistance? Call/WhatsApp: <a href="tel:' . $supportPhone . '" style="color: #D4AF37; text-decoration: none;">' . $supportPhone . '</a> or email <a href="mailto:' . $supportEmail . '" style="color: #D4AF37; text-decoration: none;">' . $supportEmail . '</a></p>
                                <p style="margin: 0;">&copy; ' . date('Y') . ' Oh My Gudness. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    return $html;
}

/**
 * Builds HTML Order Notification Email for the Store Admin
 */
function buildAdminOrderNotificationEmail($order, $items = [])
{
    $orderId = htmlspecialchars($order['id'] ?? 'N/A');
    $customerName = htmlspecialchars($order['customer_name'] ?? 'N/A');
    $customerEmail = htmlspecialchars($order['customer_email'] ?? 'N/A');
    $customerPhone = htmlspecialchars($order['customer_phone'] ?? 'N/A');
    $totalAmount = number_format((float)($order['total_amount'] ?? 0), 2);
    $deliveryAddress = htmlspecialchars($order['delivery_address'] ?? 'N/A');
    $deliveryDate = !empty($order['delivery_date']) ? date('D, d M Y', strtotime($order['delivery_date'])) : 'N/A';
    $deliveryTime = htmlspecialchars($order['delivery_time'] ?? 'N/A');
    $orderTime = !empty($order['created_at']) ? $order['created_at'] : date('Y-m-d H:i:s');

    $itemsList = '';
    foreach ($items as $item) {
        $pName = htmlspecialchars($item['name'] ?? ($item['product_name'] ?? 'Item'));
        $qty = (int)($item['quantity'] ?? 1);
        $price = number_format((float)($item['price'] ?? 0), 2);
        $itemsList .= '<li style="margin-bottom: 6px;"><strong>' . $pName . '</strong> &times; ' . $qty . ' (₹' . $price . ' each)</li>';
    }

    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px;">
        <div style="background: #1e293b; color: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="margin: 0 0 6px 0; color: #f59e0b; font-size: 20px;">🚨 [NEW PAID ORDER RECEIVED]</h2>
            <div style="font-size: 14px;">Order #' . $orderId . ' &bull; <strong>₹' . $totalAmount . '</strong></div>
        </div>

        <h3 style="color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px;">Customer Information</h3>
        <p style="font-size: 14px; line-height: 1.6; color: #334155;">
            <strong>Customer:</strong> ' . $customerName . '<br>
            <strong>Phone:</strong> <a href="tel:' . $customerPhone . '">' . $customerPhone . '</a> &bull; <a href="https://wa.me/91' . preg_replace('/[^0-9]/', '', $customerPhone) . '">Chat on WhatsApp</a><br>
            <strong>Email:</strong> <a href="mailto:' . $customerEmail . '">' . $customerEmail . '</a><br>
            <strong>Order Placed At:</strong> ' . $orderTime . '
        </p>

        <h3 style="color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px;">Delivery Details</h3>
        <p style="font-size: 14px; line-height: 1.6; color: #334155;">
            <strong>Delivery Date:</strong> <span style="color: #b45309; font-weight: bold;">' . $deliveryDate . '</span><br>
            <strong>Time Slot:</strong> <span style="color: #b45309; font-weight: bold;">' . $deliveryTime . '</span><br>
            <strong>Address:</strong> ' . $deliveryAddress . '
        </p>

        <h3 style="color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px;">Items to Prepare</h3>
        <ul style="font-size: 14px; color: #334155; line-height: 1.6;">
            ' . $itemsList . '
        </ul>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; text-align: center;">
            <a href="https://ohmygudness.in/backend/admin_orders.php" style="background: #0f172a; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: bold;">
                Open in Admin Orders &rarr;
            </a>
        </div>
    </div>';

    return $html;
}
