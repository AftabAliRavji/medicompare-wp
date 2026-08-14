-- Insert Gmail OAuth Client Credentials for MediCompare
-- These options are required for Gmail API access token generation

INSERT INTO wp_options (option_name, option_value, autoload)
VALUES 
('medicompare_gmail_client_id', '81769999742-4u4rm19ufj7o36fcl3cnmoaa99ipatbh.apps.googleusercontent.com', 'yes'),
('medicompare_gmail_client_secret', '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', 'yes');
