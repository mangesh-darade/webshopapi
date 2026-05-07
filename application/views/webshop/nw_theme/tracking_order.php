<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order</title>
</head>
<body>
    <?php include_once('header.php'); ?>
    <div class="container-order">
        <div id="order-heading-div">
            <p class="order-number-div">Order <span id="order-number"></span></p>
            <p class="date" id="order-date"><span id="order_time"></span></p>
        </div>
        <div id="order-tracking-div">
            <p id="order-status">Order <span id="status"></span></p>
            <div id="delivery-details-div">
                <strong>Deliver to:</strong> <span id="deliver_to">Loading...</span>
            </div>
        </div>
        <div id="order-items-div">
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="orderItems-body"></tbody>
            </table>
        </div>
    </div>
    <?php include_once('footer.php'); ?>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        const orderNumber = document.getElementById("order-number");
        const orderStatus = document.getElementById("status");
        const deliverToElement = document.getElementById("deliver_to");
        const orderBody = document.getElementById("orderItems-body");
        const orderId = <?= json_encode($this->data['order_id']); ?>;

        function fetchTrackingData(id) {
            return $.ajax({
                url: '<?= base_url("webshop/getTrackingData") ?>',
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: id,
                    '<?= $this->security->get_csrf_token_name(); ?>': '<?= $this->security->get_csrf_hash(); ?>'
                }
            });
        }

        fetchTrackingData(orderId).then(function (res) {
            if (!res || res.status !== 'success' || !res.tracking || !res.tracking.order) {
                return;
            }
            const data = res.tracking;
            orderNumber.textContent = '#' + data.order.id;
            orderStatus.textContent = data.order.sale_status || '';
            deliverToElement.textContent = data.order.deliver_to || '';

            if (Array.isArray(data.order_items)) {
                orderBody.innerHTML = data.order_items.map(function (item) {
                    var qty = Number(item.quantity || 0);
                    var price = Number(item.unit_price || 0);
                    return '<tr><td>' + (item.product_name || '') + '</td><td>' + qty.toFixed(2) + '</td><td>' + price.toFixed(2) + '</td><td>' + (qty * price).toFixed(2) + '</td></tr>';
                }).join('');
            }
        });
    </script>
</body>
</html>
