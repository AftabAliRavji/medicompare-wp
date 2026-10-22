<?php
/* -------------------------------
     HERO SECTION WHICH CAN THEN BE RE-USED ON THE PHARMACY PAGES
     AND ANY OTHER
     -------------------------------*/
    function mc_render_hero(array $args = [])
    {
        $defaults = [
            'kicker'      => '',
            'title'       => '',
            'description' => '',
            'content'     => '',
        ];

        $args = wp_parse_args($args, $defaults);
    ?>

   <div class="wp-block-group alignfull mc-hero-wrapper">
    <section class="mc-hero-section">

        <div class="mc-hero-content">

            <div class="mc-hero-left">

                <?php if (!empty($args['kicker'])) : ?>
                    <div class="mc-hero-kicker">
                        <?php echo esc_html($args['kicker']); ?>
                    </div>
                <?php endif; ?>

                <h1 class="mc-hero-title">
                    <?php echo esc_html($args['title']); ?>
                </h1>

                <p class="mc-hero-description">
                    <?php echo esc_html($args['description']); ?>
                </p>

                <?php echo $args['content']; ?>

            </div>

        </div>

    </section>
  </div>

    <?php
    }