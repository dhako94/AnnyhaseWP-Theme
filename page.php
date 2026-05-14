<?php defined('ABSPATH') || exit; get_header(); ?>

<div style="padding:4rem 0 2rem;background:var(--color-cream)">
    <div class="container" style="max-width:860px">
        <h1><?php the_title(); ?></h1>
    </div>
</div>

<section class="section" style="padding-top:3rem">
    <div class="container" style="max-width:860px">
        <?php while (have_posts()): the_post(); ?>
        <div class="entry-content" style="line-height:1.85">
            <?php the_content(); ?>
        </div>
        <?php endwhile; ?>
    </div>
</section>

<?php get_footer(); ?>
