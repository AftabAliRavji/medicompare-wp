-- Add subscription meta fields for all existing pharmacies
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_subscription_status', 'trial'
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_subscription_status'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_subscription_period_start', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_subscription_period_start'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_subscription_period_end', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_subscription_period_end'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_next_billing_date', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_next_billing_date'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_last_payment_date', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_last_payment_date'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_last_payment_amount', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_last_payment_amount'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_last_payment_reference', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_last_payment_reference'
  );

-- Stripe fields
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_stripe_customer_id', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_stripe_customer_id'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_stripe_subscription_id', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_stripe_subscription_id'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT ID, '_mc_stripe_price_id', ''
FROM wp_posts
WHERE post_type = 'mc_pharmacy'
  AND ID NOT IN (
      SELECT post_id FROM wp_postmeta WHERE meta_key = '_mc_stripe_price_id'
  );
