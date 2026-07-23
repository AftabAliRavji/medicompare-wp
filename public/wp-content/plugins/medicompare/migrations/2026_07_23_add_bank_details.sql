INSERT INTO wp_options (option_name, option_value, autoload)
VALUES 
    ('mc_bank_account_name', 'mediCompare', 'yes'),
    ('mc_bank_name', 'HSBC', 'yes'),
    ('mc_bank_account_number', 'xxxxxxx', 'yes'),
    ('mc_bank_sort_code', 'xx-xx-xx', 'yes')
ON DUPLICATE KEY UPDATE option_value = VALUES(option_value);
