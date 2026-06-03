<?php
/*
Template Name: チーム作成申請完了ページ
*/

$mypage_url = home_url('/mypage/');

get_header();
?>

<div class="registration-complete-page team-registration-complete-page">
  <div class="registration-complete-bg" aria-hidden="true">
    <div class="registration-complete-bg-gradient"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--1"></div>
    <div class="registration-complete-bg-wave registration-complete-bg-wave--2"></div>
  </div>

  <div class="registration-complete-container">
    <?php
    if (function_exists('aidunite_render_team_registration_complete_content')) {
        aidunite_render_team_registration_complete_content($mypage_url);
    } else {
        $part = get_stylesheet_directory() . '/template-parts/team/registration-complete-content.php';
        if (is_readable($part)) {
            load_template($part, false, ['mypage_url' => $mypage_url]);
        }
    }
    ?>
  </div>
</div>

<?php get_footer(); ?>
