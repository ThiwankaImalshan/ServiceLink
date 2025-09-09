<?php
/**
 * Email Configuration for Service Delivery Web Application
 * Uses PHPMailer for email sending
 */

// Check if vendor autoload exists before requiring
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    throw new Exception('PHPMailer not installed. Please run: composer install');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email Configuration Constants
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP host
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'helawiskam2019@gmail.com'); // Change to your email
define('SMTP_PASSWORD', 'hawc rfod mbxk zdyq'); // Change to your app password
define('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
define('FROM_EMAIL', 'helawiskam2019@gmail.com');
define('FROM_NAME', 'ServiceLink');

/**
 * Create and configure PHPMailer instance
 */
function createMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Default settings
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    } catch (Exception $e) {
        error_log("Email configuration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP verification email
 */
function sendOTPEmail($email, $firstName, $otp) {
    $mail = createMailer();
    if (!$mail) {
        return ['success' => false, 'message' => 'Email system configuration error'];
    }
    
    try {
        $mail->addAddress($email, $firstName);
        $mail->Subject = 'Email Verification - ServiceLink';
        
        $mail->Body = getOTPEmailTemplate($firstName, $otp);
        $mail->AltBody = "Hello $firstName,\n\nYour email verification code is: $otp\n\nThis code will expire in 10 minutes.\n\nBest regards,\nServiceLink Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'OTP sent successfully'];
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send verification email'];
    }
}

/**
 * Generate OTP email template
 */
function getOTPEmailTemplate($firstName, $otp) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Email Verification</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .otp-code { background: #007bff; color: white; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; border-radius: 5px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>ServiceLink</h1>
                <h2>Email Verification Required</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>$firstName</strong>,</p>
                <p>Thank you for registering with ServiceLink! To complete your registration, please verify your email address using the code below:</p>
                <div class='otp-code'>$otp</div>
                <p><strong>Important:</strong></p>
                <ul>
                    <li>This code will expire in 10 minutes</li>
                    <li>Do not share this code with anyone</li>
                    <li>If you didn't create an account, please ignore this email</li>
                </ul>
                <p>If you have any questions, please contact our support team.</p>
                <p>Best regards,<br>The ServiceLink Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send welcome email after successful verification
 */
function sendWelcomeEmail($email, $firstName, $role) {
    $mail = createMailer();
    if (!$mail) {
        return ['success' => false, 'message' => 'Email system configuration error'];
    }
    
    try {
        $mail->addAddress($email, $firstName);
        $mail->Subject = 'Welcome to ServiceLink!';
        
        $roleText = $role === 'provider' ? 'Service Provider' : 'Customer';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Welcome to ServiceLink</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to ServiceLink!</h1>
                </div>
                <div class='content'>
                    <p>Hello <strong>$firstName</strong>,</p>
                    <p>Congratulations! Your email has been verified and your account is now active.</p>
                    <p>You have registered as a <strong>$roleText</strong>. You can now:</p>
                    " . ($role === 'provider' ? "
                    <ul>
                        <li>Complete your service profile</li>
                        <li>Add your qualifications and certifications</li>
                        <li>Start receiving service requests</li>
                        <li>Manage your availability and pricing</li>
                    </ul>
                    <p><a href='" . BASE_URL . "/my-service.php' class='button'>Complete Your Profile</a></p>
                    " : "
                    <ul>
                        <li>Browse available services</li>
                        <li>Post service requirements</li>
                        <li>Contact service providers</li>
                        <li>Manage your profile</li>
                    </ul>
                    <p><a href='" . BASE_URL . "/services.php' class='button'>Browse Services</a></p>
                    ") . "
                    <p>If you have any questions, our support team is here to help.</p>
                    <p>Best regards,<br>The ServiceLink Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Welcome to ServiceLink, $firstName! Your account as a $roleText is now active.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Welcome email sent'];
    } catch (Exception $e) {
        error_log("Welcome email error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send welcome email'];
    }
}

/**
 * Send password reset OTP email
 */
function sendPasswordResetEmail($email, $firstName, $otp) {
    $mail = createMailer();
    if (!$mail) {
        return ['success' => false, 'message' => 'Email system configuration error'];
    }
    
    try {
        $mail->addAddress($email, $firstName);
        $mail->Subject = 'Password Reset Code - ServiceLink';
        
        $mail->Body = getPasswordResetEmailTemplate($firstName, $otp);
        $mail->AltBody = "Hello $firstName,\n\nYour password reset verification code is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this reset, please ignore this email.\n\nBest regards,\nServiceLink Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Password reset email sent successfully'];
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send password reset email'];
    }
}

/**
 * Generate password reset email template
 */
function getPasswordResetEmailTemplate($firstName, $otp) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .otp-code { background: #dc3545; color: white; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; margin: 20px 0; border-radius: 5px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>ServiceLink</h1>
                <h2>Password Reset Request</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>$firstName</strong>,</p>
                <p>We received a request to reset your ServiceLink account password. Use the verification code below to proceed with your password reset:</p>
                <div class='otp-code'>$otp</div>
                <div class='warning'>
                    <strong>Important Security Information:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                        <li>Your password will remain unchanged unless you complete the reset process</li>
                    </ul>
                </div>
                <p><strong>What happens next?</strong></p>
                <ol>
                    <li>Enter the verification code on the reset page</li>
                    <li>Create a new strong password</li>
                    <li>Your account will be secured with the new password</li>
                </ol>
                <p>If you continue to have issues accessing your account, please contact our support team.</p>
                <p>Best regards,<br>The ServiceLink Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated security email. Please do not reply to this message.</p>
                <p>If you didn't request this password reset, please ignore this email or contact support if you have concerns.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Send password changed notification email
 */
function sendPasswordChangedEmail($email, $firstName) {
    $mail = createMailer();
    if (!$mail) {
        return ['success' => false, 'message' => 'Email system configuration error'];
    }
    
    try {
        $mail->addAddress($email, $firstName);
        $mail->Subject = 'Password Changed - ServiceLink';
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Changed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .alert { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>ServiceLink</h1>
                    <h2>Password Changed Successfully</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>$firstName</strong>,</p>
                    <p>Your ServiceLink account password has been successfully changed.</p>
                    <div class='alert'>
                        <strong>Security Notice:</strong> If you did not make this change, please contact our support team immediately and consider securing your email account.
                    </div>
                    <p><strong>Account Security Tips:</strong></p>
                    <ul>
                        <li>Use a strong, unique password</li>
                        <li>Enable two-factor authentication if available</li>
                        <li>Never share your login credentials</li>
                        <li>Log out from shared or public computers</li>
                    </ul>
                    <p>If you have any questions about your account security, please contact our support team.</p>
                    <p>Best regards,<br>The ServiceLink Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $firstName,\n\nYour ServiceLink account password has been successfully changed.\n\nIf you did not make this change, please contact our support team immediately.\n\nBest regards,\nServiceLink Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Password changed notification sent'];
    } catch (Exception $e) {
        error_log("Password changed email error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send notification email'];
    }
}

/**
 * Send contact message email to provider
 */
function sendContactEmail($providerEmail, $providerName, $senderName, $senderEmail, $senderPhone, $subject, $message, $contactMethod) {
    $mail = createMailer();
    if (!$mail) {
        return ['success' => false, 'message' => 'Email system configuration error'];
    }
    
    try {
        $mail->addAddress($providerEmail, $providerName);
        $mail->Subject = 'New Contact Request - ' . $subject;
        
        $mail->Body = getContactEmailTemplate($providerName, $senderName, $senderEmail, $senderPhone, $subject, $message, $contactMethod);
        
        $altMessage = "Hello $providerName,\n\n";
        $altMessage .= "You have received a new contact request through ServiceLink.\n\n";
        $altMessage .= "From: $senderName\n";
        $altMessage .= "Email: $senderEmail\n";
        if ($senderPhone) {
            $altMessage .= "Phone: $senderPhone\n";
        }
        $altMessage .= "Preferred Contact: " . ucfirst($contactMethod) . "\n";
        $altMessage .= "Subject: $subject\n\n";
        $altMessage .= "Message:\n$message\n\n";
        $altMessage .= "Please respond to this inquiry promptly to maintain your professional reputation.\n\n";
        $altMessage .= "Best regards,\nServiceLink Team";
        
        $mail->AltBody = $altMessage;
        
        $mail->send();
        return ['success' => true, 'message' => 'Contact email sent successfully'];
    } catch (Exception $e) {
        error_log("Contact email error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to send contact email'];
    }
}

/**
 * Generate contact email template
 */
function getContactEmailTemplate($providerName, $senderName, $senderEmail, $senderPhone, $subject, $message, $contactMethod) {
    $preferredContact = '';
    $contactMethodText = ucfirst($contactMethod);
    
    if ($contactMethod === 'phone' && $senderPhone) {
        $preferredContact = "<p><strong>Phone:</strong> <a href=\"tel:$senderPhone\" style=\"color: #007bff; text-decoration: none;\">$senderPhone</a></p>";
    } elseif ($contactMethod === 'both' && $senderPhone) {
        $preferredContact = "<p><strong>Phone:</strong> <a href=\"tel:$senderPhone\" style=\"color: #007bff; text-decoration: none;\">$senderPhone</a></p>";
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>New Contact Request</title>
        <style>
            body { 
                font-family: 'Inter', Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f8fafc;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }
            .header { 
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: 700;
            }
            .header p {
                margin: 0;
                opacity: 0.9;
                font-size: 16px;
            }
            .content { 
                padding: 30px 20px; 
                background: white; 
            }
            .contact-info {
                background: #f1f5f9;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                border-left: 4px solid #3b82f6;
            }
            .contact-info h3 {
                margin: 0 0 15px 0;
                color: #1e40af;
                font-size: 18px;
            }
            .contact-info p {
                margin: 8px 0;
                font-size: 14px;
            }
            .message-box {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
            }
            .message-box h4 {
                margin: 0 0 15px 0;
                color: #334155;
                font-size: 16px;
            }
            .message-text {
                white-space: pre-wrap;
                font-size: 14px;
                line-height: 1.6;
                color: #475569;
            }
            .cta-button {
                display: inline-block;
                background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
                color: white;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                margin: 15px 0;
                font-weight: 600;
                text-align: center;
            }
            .footer { 
                text-align: center; 
                padding: 20px; 
                background: #f8fafc;
                color: #64748b; 
                font-size: 12px; 
                border-top: 1px solid #e2e8f0;
            }
            .footer p {
                margin: 5px 0;
            }
            .badge {
                display: inline-block;
                background: #dbeafe;
                color: #1e40af;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                margin: 0 5px;
            }
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                    border-radius: 8px;
                }
                .header, .content {
                    padding: 20px 15px;
                }
                .contact-info, .message-box {
                    padding: 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔗 ServiceLink</h1>
                <p>New Contact Request</p>
            </div>
            <div class='content'>
                <p>Hello <strong>$providerName</strong>,</p>
                <p>You have received a new contact request through ServiceLink! A potential client is interested in your services.</p>
                
                <div class='contact-info'>
                    <h3>👤 Contact Information</h3>
                    <p><strong>Name:</strong> $senderName</p>
                    <p><strong>Email:</strong> <a href=\"mailto:$senderEmail\" style=\"color: #3b82f6; text-decoration: none;\">$senderEmail</a></p>
                    $preferredContact
                    <p><strong>Preferred Contact Method:</strong> <span class='badge'>$contactMethodText</span></p>
                </div>
                
                <div class='message-box'>
                    <h4>📋 Subject: $subject</h4>
                    <div class='message-text'>$message</div>
                </div>
                
                <p><strong>💡 Quick Response Tips:</strong></p>
                <ul style='color: #475569; font-size: 14px; line-height: 1.6;'>
                    <li>Respond within 24 hours to maintain your professional reputation</li>
                    <li>Ask specific questions about their project requirements</li>
                    <li>Provide a clear timeline and pricing estimate</li>
                    <li>Share relevant examples of your previous work</li>
                </ul>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href=\"mailto:$senderEmail?subject=Re: $subject\" class='cta-button' style='color: white; text-decoration: none;'>
                        📧 Reply to $senderName
                    </a>
                </div>
                
                <p style='background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 15px; border-radius: 6px; font-size: 14px;'>
                    <strong>⚡ Pro Tip:</strong> Quick responses lead to more bookings! Studies show that providers who respond within the first hour are 7x more likely to get hired.
                </p>
                
                <p>Thank you for being part of the ServiceLink community!</p>
                <p>Best regards,<br><strong>The ServiceLink Team</strong></p>
            </div>
            <div class='footer'>
                <p>This message was sent through ServiceLink's secure contact system.</p>
                <p>📧 Do not reply to this email directly - please respond to the sender's email address above.</p>
                <p>&copy; " . date('Y') . " ServiceLink. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}
