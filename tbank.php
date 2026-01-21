<?php
// 1768985142695DEMO - Terminal ID
// 2$XMgboHTiyRtE*d - Password
// Merchant_name - Dustore
// Merchant_id - 200000001715398
// Terminal_id - 25857237
// Sbp_merchant_id - MA0005888661
?>
<html>

<head>

</head>

<body>
    <script src="https://integrationjs.tbank.ru/integration.js" onload="onPaymentIntegrationLoad()" async></script>
    <script>
        const initConfig = {
            terminalKey: 'myTerminalKey', // Значение TerminalKey из личного кабинета
            product: 'eacq',
            features: {
                payment: {}, // Добавьте, если нужны кнопки оплаты
                iframe: {}, // Добавьте, если нужно встроить платежную форму в iframe
                addcardIframe: {} // Добавьте, если нужно встроить приложение привязки карты в iframe
            }
        }

        function onPaymentIntegrationLoad() {
            PaymentIntegration.init(initConfig).then().catch();
        }
    </script>
</body>

</html>