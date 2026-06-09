<?php

if (!defined('ABSPATH')) exit;

class MediCompare_Admin_Dashboard_Widget {

    public static function render_inline_widget() {

        echo '<h2>Pending Pharmacy Verifications</h2>';

        $pending = new WP_Query([
            'post_type'      => 'mc_pharmacy',
            'posts_per_page' => 20,
            'meta_query'     => [
                [
                    'key'   => '_mc_status',
                    'value' => 'pending_verification'
                ]
            ]
        ]);

        if (!$pending->have_posts()) {
            echo '<p>No pharmacies awaiting verification.</p>';
            return;
        }

        echo '<table class="widefat striped" style="margin-top:15px;">';
        echo '<thead>
                <tr>
                    <th>Pharmacy</th>
                    <th>Email</th>
                    <th>GPhC</th>
                    <th>City</th>
                    <th>Action</th>
                </tr>
              </thead>';
        echo '<tbody>';

        while ($pending->have_posts()) {
            $pending->the_post();
            $id = get_the_ID();

            $email = get_post_meta($id, '_mc_email', true);
            $gphc  = get_post_meta($id, '_mc_gphc_number', true);
            $city  = get_post_meta($id, '_mc_city', true);

            echo '<tr>';
            echo '<td>' . esc_html(get_the_title()) . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($gphc) . '</td>';
            echo '<td>' . esc_html($city) . '</td>';
            echo '<td><a class="button button-primary" href="' . admin_url("post.php?post={$id}&action=edit") . '">Verify Now</a></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        wp_reset_postdata();
    }
}
