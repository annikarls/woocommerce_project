<!-- Yasmine och Elin -->
<?php get_header(); ?>
<?php get_template_part('partials/navbar'); ?>

<section class="faq-info" data-aos="fade-up"> <!-- data-aos som gör att innehållet glider upp på sidan-->
  <!-- 3 kolumner med vardera faq-category där också frågorna hämtas och skrivs ut en ny accordion för varje fråga-->
  <?php if( have_rows('layout') ):
  while ( have_rows('layout') ) : the_row();
  if( get_row_layout() == 'faq' ): ?>
  <div class="columns is-centered">
    <div class="column">
      <h2 class="faq-category">
        <?php the_sub_field('rubrik_1'); ?>
      </h2>
      <section class="accordions">
        <?php
        if( have_rows('col_1') ):
        while ( have_rows('col_1') ) : the_row(); ?>
        <article class="accordion">
          <div class="accordion-header toggle">
            <h2>
              <?php the_sub_field('fragefalt'); ?>
            </h2>
            <button class="toggle" aria-label="toggle"></button>
          </div>
          <div class="accordion-body">
            <div class="accordion-content">
              <p>
                <?php the_sub_field('svar'); ?>
              </p>
            </div>
          </div>
        </article>

        <?php
      endwhile;
      endif;
      ?>
      </section>
    </div>
    <div class="column">
      <h2 class="faq-category">
        <?php the_sub_field('rubrik_2'); ?>
      </h2>
      <section class="accordions">
        <?php
        if( have_rows('col_2') ):
        while ( have_rows('col_2') ) : the_row(); ?>
        <article class="accordion">
          <div class="accordion-header toggle">
            <h2>
              <?php the_sub_field('fragefalt'); ?>
            </h2>
            <button class="toggle" aria-label="toggle"></button>
          </div>
          <div class="accordion-body">
            <div class="accordion-content">
              <p>
                <?php the_sub_field('svar'); ?>
              </p>
            </div>
          </div>
        </article>
        <?php
      endwhile;
      endif;
      ?>
      </section>
    </div>
    <div class="column">
      <h2 class="faq-category">
        <?php the_sub_field('rubrik_3'); ?>
      </h2>
      <section class="accordions">
        <?php
        if( have_rows('col_3') ):
        while ( have_rows('col_3') ) : the_row(); ?>
        <article class="accordion">
          <div class="accordion-header toggle">
            <h2>
              <?php the_sub_field('fragefalt'); ?>
            </h2>
            <button class="toggle" aria-label="toggle"></button>
          </div>
          <div class="accordion-body">
            <div class="accordion-content">
              <p>
                <?php the_sub_field('svar'); ?>
              </p>
            </div>
          </div>
        </article>
        <?php
      endwhile;
      endif;
      ?>
      </section>
    </div>
  </div>
</section>
<?php
endif;
endwhile;
endif;
?>

<!-- Yasmine -->
<section> <!-- hämtar och visar karta och formulär i två kolumner jämte varandra -->
  <div class="container">
    <div class="kontakt">
      <div class="columns is-centered">
        <div class="column map">
          <?php if( have_rows('layout') ):
        while ( have_rows('layout') ) : the_row();
        if( get_row_layout() == '2_column' ): ?>
          <?php the_sub_field('karta');?>
          <?php
              endif;
              endwhile;
              endif;
              ?>
        </div>
        <div class="column formular">
          <?php if( have_rows('layout') ):
        while ( have_rows('layout') ) : the_row();
        if( get_row_layout() == '2_column' ): ?>
          <?php
              if( have_rows('formular') ):
              while ( have_rows('formular') ) : the_row(); ?>
          <div class="kontaktTitle">
            <?php the_sub_field('title');?>
          </div>
          <p>
            <?php the_sub_field('formular');?>
          </p>
        <?php endwhile;
      endif;
      ?>
          <?php
    endif;
    endwhile;
    endif;
    ?>
        </div>
      </div>
    </div>
  </div>
</section>
<script> /* för att hämta accordion som faq-info använder*/
  jQuery(document).ready(function() {
    var accordions = bulmaAccordion.attach();
  });
</script>

<?php get_footer(); ?>
