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
