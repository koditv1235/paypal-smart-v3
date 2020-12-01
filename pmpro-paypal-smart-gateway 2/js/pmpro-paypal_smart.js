paypal.Buttons({
    env: pmproPayPalSmart.gateway_environment,
    onClick: async (data, actions) => {
      jQuery('#pmpro_message_bottom').removeClass('pmpro_error').hide()
      var check;
      if(pmproPayPalSmart.isLogin){
          check = true;    
      }else{
          check = await check_pmpro_user();
      }
      if(check){
          return actions.resolve()
      }else{
          jQuery('#pmpro_message_bottom').addClass('pmpro_error').text('Email or Userid already exists').show()
          return actions.reject()
      }
    },
    createOrder: function(data, actions) {
      // This function sets up the details of the transaction, including the amount and line item details.
      return actions.order.create({
        purchase_units: [{
          amount: {
            value: pmproPayPalSmart.amount
          }
        }],
        application_context: { shipping_preference: 'NO_SHIPPING' }
      });
    },
    onApprove: function(data, actions) {
      // This function captures the funds from the transaction.
      return actions.order.capture().then(function(details) {
        if(details.status == 'COMPLETED'){
          console.log(details);
          jQuery('#pmpro_user_fields').addClass('loader');
          jQuery("form#pmpro_form").find('input#order-id').val(details.id);
          jQuery("form#pmpro_form").submit();
          // This function shows a transaction success message to your buyer.
          // alert('Transaction completed by ' + details.payer.name.given_name);
        }
      });
    }
  }).render('#paypal-button-container');


async function check_pmpro_user(){
    var isError = true;
    var data = {
        'username': jQuery('#username').val(),
        'bemail': jQuery('#bemail').val()
    };
    console.log(data);
    await jQuery.ajax({
        url: pmproPayPalSmart.ajax_url,
        type: 'POST',
        data: data,
        beforeSend : function(){
            jQuery("#pmpro_btn-submit").prop('disabled', true);
        },
        success : function(res){
            return (res === 'TRUE') ? true : false;
        },error(err){
            jQuery("#pmpro_btn-submit").prop('disabled', false);
        }
    })
    return false;
    
}