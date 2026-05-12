document.addEventListener('DOMContentLoaded', function () {
    var billingCountry = document.getElementById('billing_country');
    var shippingCountry = document.getElementById('shipping_country');
    if (billingCountry && shippingCountry) {
        shippingCountry.value = billingCountry.value;
        billingCountry.addEventListener('change', function () {
            shippingCountry.value = billingCountry.value;
        });
    }
});
