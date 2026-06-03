<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 *
 * @since vantage 1.0
 * @license GPL 2.0
 */

get_header(); ?>

<div id="primary" class="content-area">
  <main id="main" class="site-main" role="main">

    <?php
    while ( have_posts() ) :
      the_post();
      ?>

      <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
          <h1 class="entry-title"><?php the_title(); ?></h1> <!-- ページタイトル -->
        </header>

        <div class="entry-content">
          <?php the_content(); ?> <!-- ★ここで本文（ショートコード含む）を出力 -->
        </div>
      </article>

      <?php
    endwhile;
    ?>

  </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
