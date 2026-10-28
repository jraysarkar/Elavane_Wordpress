Elvane Payment-Based Delivery Charge

What it does
------------
* Cash on Delivery: delivery charge is Rs. 100.
* Razorpay (prepaid): delivery charge is free.
* Any other payment method: leaves the existing shipping settings unchanged.

Installation
------------
1. In WordPress Admin, go to Plugins > Add New > Upload Plugin.
2. Upload the file elvane-payment-based-delivery.zip.
3. Activate “Elvane Payment-Based Delivery Charge”.
4. Test checkout by changing from Razorpay to Cash on Delivery and back. The order total should refresh automatically.

Notes
-----
* The plugin changes all available shipping rates after a customer chooses COD or Razorpay.
* It recognizes the standard WooCommerce COD gateway ID (cod) and any Razorpay gateway ID containing "razorpay".
* It is designed for the classic WooCommerce checkout. If the site uses the Checkout Block and does not refresh after changing payment method, use the classic checkout shortcode [woocommerce_checkout].
