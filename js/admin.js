/**
 * @file
 * FA Admin JS.
 */
(function ($, Drupal) {
    /**
     * Attaches Hero section behavior.
     * 
     * @type {Drupal~behavior}
     * 
     * @prop {Drupal~behaviorAttach} attach
     */
    Drupal.behaviors.hero_section = {
        attach: function() {
            $(".footer-aggregation #view-field-grand-total-table-column").prepend('<span>Grand Total: </span>');
            $(".footer-aggregation #view-field-profit-table-column").prepend('<span>Gross Profit (G.P.): </span>');
            // Invoice Table Year and Month Filters.
            // Get the current URL path
            var currentPath = window.location.pathname;

            // Select all links within the dynamic-links div
            var links = document.querySelectorAll('#dynamic-links a');

            // Loop through each link and compare its href attribute with the current URL path
            links.forEach(function(link) {
                var href = link.getAttribute('href');
                var monthYear = link.getAttribute('data-month-year');

                // Check if the link's href attribute matches the current URL path
                if (href === currentPath) {
                    // Add the "active" class to the link
                    link.classList.add('active');
                }

                // Check if the link's data-month-year attribute matches the current URL path
                if (monthYear === currentPath.split('/').pop()) {
                    // Add the "active" class to the link
                    link.classList.add('active');
                }
            });
        }
    };
}(jQuery, Drupal));
