/**
 * @file
 * Hero Section JS.
 */
(function ($, Drupal) {
    // Hide the "legal-services" section on page load.
    $("#legal-services").hide();

    /**
     * Attaches Hero section behavior.
     * 
     * @type {Drupal~behavior}
     * 
     * @prop {Drupal~behaviorAttach} attach
     */
    Drupal.behaviors.hero_section = {
        attach: function() {
            $(".hero-button--li").on("click", function() {
                $(".hero-button--li").removeClass("hero-button--active");
                $(this).addClass("hero-button--active");
                var targetId = $(this).data("id");
                if (targetId == "legal-services") {
                    $("#" + targetId).show().siblings(".open").hide();
                    $("#corporate-services").hide();
                }
                if (targetId == "corporate-services") {
                    $("#" + targetId).show().siblings(".open").hide();
                    $("#legal-services").hide();
                }
            });
        }
    };
}(jQuery, Drupal));
