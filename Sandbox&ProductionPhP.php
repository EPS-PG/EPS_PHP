<?php
session_start(); // ✅ enable session

// === EPS Configuration ===
define('EPS_USERNAME', "Epsdemo@gmail.com");                     //Your Username
define('EPS_PASSWORD', "Epsdemo258@");                           //Your Password
define('EPS_HASH_KEY', "FHZxyzeps56789gfhg678ygu876o=");         //Your Hash_Key
define('EPS_MERCHANT_ID', "29e86e70-0ac6-45eb-ba04-9fcb0aaed12a"); //Your MerchantId
define('EPS_STORE_ID', "d44e705f-9e3a-41de-98b1-1674631637da");    //Your StoreId

// === Helper Functions ===
function generateHash($data, $secretKey) {
    return base64_encode(hash_hmac('sha512', utf8_encode($data), $secretKey, true));
}

function getEpsToken() {
    $url = "https://sandboxpgapi.eps.com.bd/v1/Auth/GetToken";    //Sandbox Url
    // $url = "https://pgapi.eps.com.bd/v1/Auth/GetToken";    //Live Url
    $xHash = generateHash(EPS_USERNAME, EPS_HASH_KEY);
    echo "<script>alert(" . json_encode($xHash) . ");</script>";
    $headers = [
        "Content-Type: application/json",
        "x-hash: $xHash"
    ];
    $payload = json_encode([
        "userName" => EPS_USERNAME,
        "password" => EPS_PASSWORD
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function initializePayment($token, $transactionId) {
    $url = "https://sandboxpgapi.eps.com.bd/v1/EPSEngine/InitializeEPS"; //Sandbox Url
    // $url = "https://pgapi.eps.com.bd/v1/EPSEngine/InitializeEPS"; //Live Url
    $xHash = generateHash($transactionId, EPS_HASH_KEY);
    $headers = [
        "Content-Type: application/json",
        "x-hash: $xHash",
        "Authorization: Bearer $token"
    ];
    $paymentData = [
        "merchantId" => EPS_MERCHANT_ID,
        "storeId" => EPS_STORE_ID,
        "CustomerOrderId" => "Order" . rand(1000, 9999),
        "merchantTransactionId" => $transactionId,
        "transactionTypeId" => 1,
        "totalAmount" => 100.50,
        "successUrl" => fullUrl() . "?action=status",
        "failUrl"    => fullUrl() . "?action=status",
        "cancelUrl"  => fullUrl() . "?action=status",
        "customerName" => "John Doe",
        "customerEmail" => "john@example.com",
        "customerAddress" => "Uttara, Dhaka",
        "customerCity" => "Dhaka",
        "customerState" => "Dhaka",
        "customerPostcode" => "1230",
        "customerCountry" => "BD",
        "customerPhone" => "01700000000",
        "productName" => "Test Product",
        "productProfile" => "general",
        "productCategory" => "Demo"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function verifyTransaction($token, $transactionId) {
    $url = "https://sandboxpgapi.eps.com.bd/v1/EPSEngine/CheckMerchantTransactionStatus?merchantTransactionId=$transactionId"; //Sandbox Url
    //$url = "https://pgapi.eps.com.bd/v1/EPSEngine/CheckMerchantTransactionStatus?merchantTransactionId=$transactionId"; //Live Ur
    $xHash = generateHash($transactionId, EPS_HASH_KEY);
    $headers = [
        "x-hash: $xHash",
        "Authorization: Bearer $token"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function fullUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    return $protocol . "://" . $_SERVER['HTTP_HOST'] . strtok($_SERVER["REQUEST_URI"], '?');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>EPS Payment</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { background: #f8f9fa; }
    .card { max-width: 600px; margin: 5rem auto; padding: 2rem; border-radius: 1rem; }
    .icon { font-size: 3rem; }
  </style>
</head>
<body>
<?php
$action = $_GET['action'] ?? '';
if ($action === 'pay') {
    $tokenRes = getEpsToken();
    if (!empty($tokenRes['token'])) {
        $transactionId = "TXN" . time();
        $_SESSION['transactionId'] = $transactionId; // ✅ Save to session
        $initRes = initializePayment($tokenRes['token'], $transactionId);
        if (!empty($initRes['RedirectURL'])) {
            echo "<script>window.location.href='" . htmlspecialchars($initRes['RedirectURL'], ENT_QUOTES) . "';</script>";
            exit;
        } else {
            echo "<div class='card border-danger text-danger text-center'><h4>Payment Failed</h4><p>Could not initialize payment.</p></div>";
        }
    } else {
        echo "<div class='card border-danger text-danger text-center'><h4>Token Failed</h4><p>Could not retrieve EPS token.</p></div>";
    }
} elseif ($action === 'status') {
    $transactionId = $_SESSION['transactionId'] ?? '';
    if (!$transactionId && !empty($_GET['MerchantTransactionId'])) {
        $transactionId = $_GET['MerchantTransactionId'];
    }

    if ($transactionId) {
        $status = '';
        if (!empty($_GET['Status'])) {
            $status = strtoupper($_GET['Status']);
        } else {
            $tokenRes = getEpsToken();
            if (!empty($tokenRes['token'])) {
                $verify = verifyTransaction($tokenRes['token'], $transactionId);

                if (isset($verify['transactionStatus'])) {
                    $status = strtoupper($verify['transactionStatus']);
                } elseif (isset($verify['status'])) {
                    $status = strtoupper($verify['status']);
                } elseif (isset($verify['data']['transactionStatus'])) {
                    $status = strtoupper($verify['data']['transactionStatus']);
                } elseif (isset($verify['data']['status'])) {
                    $status = strtoupper($verify['data']['status']);
                } else {
                    $status = 'UNKNOWN';
                }
            } else {
                echo "<div class='card text-danger border-danger text-center'><h4>Token Failed</h4><p>Could not retrieve EPS token.</p></div>";
                exit;
            }
        }

        $color = "secondary";
        $icon = "ℹ";
        $msg = "Payment Status Unknown<br>We could not determine the payment status. Please contact support.";

        if ($status === 'SUCCESS' || $status === 'COMPLETED')  {
            $color = "success";
            $icon = "✔";
            $msg = "Payment successful";
        } 
        
        elseif ($status === 'FAILED' || $status === 'FAILURE') {
            $color = "danger";
            $icon = "✖";
            $msg = "Payment failed";
        } elseif ($status === 'CANCEL' || $status === 'CANCELED') {
            $color = "warning";
            $icon = "⚠";
            $msg = "Payment cancelled";
        }

        echo "<div class='card border-$color text-center'>
                <div class='icon text-$color mb-3'>$icon</div>
                <h3 class='text-$color'>$msg</h3>
                <p class='text-muted'>Transaction ID: <code>$transactionId</code></p>
                <a href='?action=pay' class='btn btn-outline-$color mt-3'>Try Again</a>
              </div>";

        unset($_SESSION['transactionId']);
    } else {
        echo "<div class='card text-danger border-danger text-center'><h4>Error</h4><p>No transaction ID received from session or query parameters.</p></div>";
    }
} else {
    echo '<div class="card text-center border-primary">
            <h2 class="mb-4 text-primary">EPS Payment</h2>
            <p class="mb-3">Click below to start a test payment.</p>
            <a href="?action=pay" class="btn btn-primary btn-lg">Pay Now</a>
          </div>';
}
?>

<!-- Developer credit line -->
<div class="text-center mt-5 mb-3 text-muted" style="font-size: 1rem; line-height: 1.4;">
    Developed By <strong>Emon Hossain</strong><br />
    Software Engineer<br />
    Eps - Easy Payment System
</div>

</body>
</html>
