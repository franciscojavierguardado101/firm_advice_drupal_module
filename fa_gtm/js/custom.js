/**
 * @file
 * FA GTM JS.
 */
(function ($, Drupal) {

    /**
     * Attaches FA GTM behavior.
     * 
     * @type {Drupal~behavior}
     * 
     * @prop {Drupal~behaviorAttach} attach
     */
    Drupal.behaviors.fa_gtm = {
        attach: function() {
            var enquiryForm = $('.webform-button--submit');
            if (enquiryForm) {
                $(once('enquiry-submit-ajax', '.webform-button--submit')).click(function (e) {
                    var rootParentId = $(this).closest('.webform-submission-request-for-quote-add-form').attr('id');

                    if(rootParentId) {
                        var fullName = $('#' + rootParentId + ' [name="full_name"]').val();
                        var email =  $('#' + rootParentId + ' [name="email"]').val();
                        var phoneNumber =  $('#' + rootParentId + ' [name="phone_number"]').val();
                        var enquiryType =  $('#' + rootParentId + ' [name="enquiry_for"]').val();
                        var enquiry =  $('#' + rootParentId + ' [name="your_enquiry"]').val();
                        if (fullName.length > 0 && email.length > 0 && phoneNumber.length > 0 && enquiryType.length > 0 && enquiry.length > 0) {
                            // Push data to the data layer
                            window.dataLayer = window.dataLayer || [];
                            window.dataLayer.push({
                                'event': 'enquiryFormSubmit', // Custom event name
                                'formValues': {
                                    'name': fullName,
                                    'email': email,
                                    'mobileNumber': phoneNumber,
                                    'enquiryType': enquiryType,
                                    'enquiry': enquiry
                                }
                            });
                        }
                    }
                });
            }
        }
    };
}(jQuery, Drupal));
