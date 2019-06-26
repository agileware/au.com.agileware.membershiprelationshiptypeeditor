CRM.$(function($) {
    $('body').off('click','input.crm-form-submit[name="_qf_MembershipType_next"]');
    $('body').on('click','input.crm-form-submit[name="_qf_MembershipType_next"]',function(e){

        if (formAction == 2) {
            e.preventDefault();

            var form = $(this).parents('form');

            var updatedRealtionshipTypes = $('#relationship_type_id').val();
            if (updatedRealtionshipTypes == null) {
                updatedRealtionshipTypes = [];
            }

            var itemNotFound = false;
            for(var i=0; i<updatedRealtionshipTypes.length; i++) {
                if(currentRelationshipTypes.indexOf(updatedRealtionshipTypes[i]) < 0) {
                    itemNotFound = true;
                }
            }

            if (itemNotFound || updatedRealtionshipTypes.length != currentRelationshipTypes.length) {
                CRM.confirm({
                    width: 400,
                    title: 'Confirm Update',
                    options: {
                        no: ts('No'),
                        yes: ts('Yes')
                    },
                    message:'The Relationship Types for this membership type has changed.<br><br> This will trigger all memberships to be recalculated and may take some time to complete. <br><br>Do you want to continue? If this change was made in error then please click Cancel and do not save these change.',
                }).on('crmConfirm:yes', function() {
                    form.submit();
                }).on('crmConfirm:no', function() {
                    window.location.href='';
                });
            }
            else {
                form.submit();
            }
        }

    });
});
