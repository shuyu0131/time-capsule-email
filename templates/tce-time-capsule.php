<?php
/**
 * Template Name: 时光邮局页面
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="primary" class="tce-page">
    <?php
    while (have_posts()) :
        the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            </header>
            <div class="entry-content">
                <?php
                the_content();

                // 如果正文里没有短代码，自动渲染表单
                if (!has_shortcode(get_post_field('post_content', get_the_ID()), 'time_capsule_email') &&
                    !has_shortcode(get_post_field('post_content', get_the_ID()), 'time_capsule_public')) {
                    echo do_shortcode('[time_capsule_email]');
                }
                ?>
            </div>
        </article>
        <?php
    endwhile;
    ?>
</main>
<?php
get_footer();
