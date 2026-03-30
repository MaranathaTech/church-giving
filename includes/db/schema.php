<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function maranatha_giving_get_schema() {
    global $wpdb;
    $prefix  = $wpdb->prefix . 'maranatha_giving_';
    $charset = $wpdb->get_charset_collate();

    $sql = "
CREATE TABLE {$prefix}donors (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    email varchar(191) NOT NULL,
    first_name varchar(100) NOT NULL DEFAULT '',
    last_name varchar(100) NOT NULL DEFAULT '',
    phone varchar(30) NOT NULL DEFAULT '',
    address_line1 varchar(200) NOT NULL DEFAULT '',
    address_line2 varchar(200) NOT NULL DEFAULT '',
    city varchar(100) NOT NULL DEFAULT '',
    state varchar(100) NOT NULL DEFAULT '',
    zip varchar(20) NOT NULL DEFAULT '',
    address_country varchar(2) NOT NULL DEFAULT 'US',
    stripe_customer_id varchar(100) NOT NULL DEFAULT '',
    total_donated decimal(13,2) NOT NULL DEFAULT 0.00,
    donation_count int(11) NOT NULL DEFAULT 0,
    magic_link_token varchar(64) NOT NULL DEFAULT '',
    magic_link_expires datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (id),
    UNIQUE KEY email (email),
    KEY stripe_customer_id (stripe_customer_id)
) {$charset};

CREATE TABLE {$prefix}funds (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(191) NOT NULL,
    description text NOT NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    sort_order int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (id)
) {$charset};

CREATE TABLE {$prefix}donations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    donor_id bigint(20) unsigned NOT NULL DEFAULT 0,
    subscription_id bigint(20) unsigned DEFAULT NULL,
    fund_id bigint(20) unsigned DEFAULT NULL,
    amount decimal(13,2) NOT NULL DEFAULT 0.00,
    currency varchar(3) NOT NULL DEFAULT 'USD',
    donation_type varchar(20) NOT NULL DEFAULT 'one-time',
    status varchar(30) NOT NULL DEFAULT 'pending',
    gateway varchar(30) NOT NULL DEFAULT '',
    gateway_transaction_id varchar(191) NOT NULL DEFAULT '',
    gateway_customer_id varchar(100) NOT NULL DEFAULT '',
    donor_email varchar(191) NOT NULL DEFAULT '',
    donor_name varchar(200) NOT NULL DEFAULT '',
    form_id varchar(100) NOT NULL DEFAULT 'default',
    receipt_sent tinyint(1) NOT NULL DEFAULT 0,
    notes text NOT NULL,
    created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    completed_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY donor_id (donor_id),
    KEY subscription_id (subscription_id),
    KEY fund_id (fund_id),
    KEY gateway_transaction_id (gateway_transaction_id),
    KEY created_at (created_at)
) {$charset};

CREATE TABLE {$prefix}subscriptions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    donor_id bigint(20) unsigned NOT NULL DEFAULT 0,
    fund_id bigint(20) unsigned DEFAULT NULL,
    amount decimal(13,2) NOT NULL DEFAULT 0.00,
    currency varchar(3) NOT NULL DEFAULT 'USD',
    frequency varchar(20) NOT NULL DEFAULT 'monthly',
    gateway varchar(30) NOT NULL DEFAULT '',
    gateway_subscription_id varchar(191) NOT NULL DEFAULT '',
    gateway_customer_id varchar(100) NOT NULL DEFAULT '',
    status varchar(30) NOT NULL DEFAULT 'active',
    form_id varchar(100) NOT NULL DEFAULT 'default',
    next_payment_date datetime DEFAULT NULL,
    last_payment_date datetime DEFAULT NULL,
    times_billed int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    cancelled_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY donor_id (donor_id),
    KEY gateway_subscription_id (gateway_subscription_id)
) {$charset};
";

    return $sql;
}
