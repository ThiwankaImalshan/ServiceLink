-- Update currency setting from USD to LKR (Sri Lankan Rupee)
UPDATE settings SET setting_value = 'LKR' WHERE setting_key = 'default_currency';
