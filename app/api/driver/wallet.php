<?php
// Driver Wallet — included by index.php
// Balance = SUM of ledger amounts. Deposits are NON-REFUNDABLE.

if (in_array($action, ['wallet', 'wallet-create-order', 'wallet-verify', 'subscribe-from-wallet', 'upload-avatar'])) {
    if (empty($_SESSION['driver']['isLoggedIn']) || empty($_SESSION['driver']['id'])) {
        jsonResponse(['error' => 'Driver auth required'], 401);
    }
}

$driverIdW = intval($_SESSION['driver']['id'] ?? 0);

// WALLET OVERVIEW + HISTORY
if ($action === 'wallet') {
    $balance = driverWalletBalance($driverIdW);
    $commission = driverCommissionPerRide();
    $subAmount = driverSubscriptionAmount();
    $txns = dbRows("SELECT id, type, amount, balance_after, reference, note, booking_id, created_at FROM app_driver_wallet_txns WHERE driver_id = ? ORDER BY id DESC LIMIT 100", 'i', [$driverIdW]);
    foreach ($txns as &$t) { $t['amount'] = floatval($t['amount']); $t['balance_after'] = floatval($t['balance_after']); }
    unset($t);
    jsonResponse([
        'success' => true,
        'balance' => $balance,
        'min_required' => $commission,
        'can_accept' => (driverHasActiveSubscription($driverIdW) || $balance >= $commission),
        'is_subscribed' => driverHasActiveSubscription($driverIdW),
        'commission_per_ride' => $commission,
        'subscription_amount' => $subAmount,
        'min_deposit' => 500,
        'refundable' => false,
        'transactions' => $txns
    ]);
}

// CREATE RAZORPAY ORDER FOR DEPOSIT (min 500)
if ($action === 'wallet-create-order' && $method === 'POST') {
    $amount = floatval($b['amount'] ?? 0);
    if ($amount < 500) jsonResponse(['success' => false, 'error' => 'Minimum deposit is ₹500']);
    
    list($razorpayKey, $razorpaySecret) = razorpayKeys();
    $receipt = 'wallet_' . $driverIdW . '_' . time();
    
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => "$razorpayKey:$razorpaySecret",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'amount' => intval($amount * 100),
            'currency' => 'INR',
            'receipt' => $receipt,
            'notes' => ['driver_id' => strval($driverIdW), 'type' => 'wallet_deposit']
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $order = json_decode($response, true);
    
    if (empty($order['id'])) jsonResponse(['success' => false, 'error' => 'Failed to create payment order', 'details' => $order]);
    
    jsonResponse([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $amount,
        'key_id' => $razorpayKey,
        'razorpay_key' => $razorpayKey,
        'currency' => 'INR'
    ]);
}

// VERIFY DEPOSIT + CREDIT WALLET
if ($action === 'wallet-verify' && $method === 'POST') {
    $orderId = trim($b['razorpay_order_id'] ?? '');
    $paymentId = trim($b['razorpay_payment_id'] ?? '');
    if (!$orderId || !$paymentId) jsonResponse(['success' => false, 'error' => 'Missing payment details']);
    
    // Idempotency: same payment credited only once
    $dupe = dbRows("SELECT id FROM app_driver_wallet_txns WHERE reference = ? AND type = 'deposit' LIMIT 1", 's', [$paymentId]);
    if (!empty($dupe)) {
        jsonResponse(['success' => true, 'message' => 'Wallet already credited', 'balance' => driverWalletBalance($driverIdW), 'duplicate' => true]);
    }
    
    list($razorpayKey, $razorpaySecret) = razorpayKeys();
    $ch = curl_init("https://api.razorpay.com/v1/payments/$paymentId");
    curl_setopt_array($ch, [
        CURLOPT_USERPWD => "$razorpayKey:$razorpaySecret",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) jsonResponse(['success' => false, 'error' => 'Could not verify payment']);
    $pay = json_decode($response, true);
    $status = $pay['status'] ?? '';
    if ($status !== 'captured' && $status !== 'authorized') jsonResponse(['success' => false, 'error' => 'Payment not completed. Status: ' . $status]);
    
    // Trust the order amount from Razorpay notes/amount
    $credited = isset($pay['amount']) ? round(floatval($pay['amount']) / 100, 2) : 0;
    if ($credited <= 0) jsonResponse(['success' => false, 'error' => 'Invalid payment amount']);
    
    $balAfter = driverWalletTxn($driverIdW, 'deposit', $credited, 'Wallet deposit (non-refundable)', $paymentId, null);
    
    try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "💰 *Wallet Updated!*\n\n₹" . intval($credited) . " added to your PAVANCAB driver wallet.\n\nNew Balance: *₹" . intval($balAfter) . "*\n\nNote: Wallet deposits are non-refundable."); } catch (Exception $e) {}
    
    jsonResponse(['success' => true, 'message' => "₹" . intval($credited) . " added to wallet!", 'balance' => $balAfter]);
}

// SUBSCRIBE FROM WALLET BALANCE
if ($action === 'subscribe-from-wallet' && $method === 'POST') {
    $needed = driverSubscriptionAmount();
    $balance = driverWalletBalance($driverIdW);
    if (driverHasActiveSubscription($driverIdW)) jsonResponse(['success' => false, 'error' => 'You already have an active subscription']);
    if ($balance < $needed) {
        jsonResponse(['success' => false, 'error' => "You need ₹" . intval($needed) . " in your wallet to subscribe. Add ₹" . intval(ceil($needed - $balance)) . " more.", 'shortfall' => round($needed - $balance, 2)]);
    }
    
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+1 month'));
    dbExec("INSERT INTO driver_subscriptions (driver_id, start_date, end_date, amount, status) VALUES (?, ?, ?, ?, 'active')", 'issd', [$driverIdW, $startDate, $endDate, $needed]);
    dbExec("UPDATE app_drivers SET has_active_subscription = 1 WHERE id = ?", 'i', [$driverIdW]);
    $balAfter = driverWalletTxn($driverIdW, 'subscription', -$needed, 'Monthly subscription (30 days)', null, null);
    
    try { sendMetaWhatsApp($_SESSION['driver']['phone'] ?? '', "👑 *PREMIUM ACTIVATED!*\n\nYour PAVANCAB subscription is active until *$endDate*.\n\n✅ Zero commission on rides\n⚡ See new rides before other drivers\n🏆 Premium badge on your profile\n\nPaid from wallet: ₹" . intval($needed) . "\nRemaining balance: ₹" . intval($balAfter)); } catch (Exception $e) {}
    try { sendFCMPushToAdmins("👑 New Subscriber", "Driver subscribed (₹" . intval($needed) . ") via wallet until $endDate.", ['type' => 'NEW_SUBSCRIBER']); } catch (Exception $e) {}
    
    jsonResponse(['success' => true, 'message' => "Premium activated until $endDate!", 'end_date' => $endDate, 'balance' => $balAfter]);
}

// UPLOAD PROFILE PHOTO (base64 JPEG)
if ($action === 'upload-avatar' && $method === 'POST') {
    $dataUrl = trim($b['image'] ?? '');
    if (!$dataUrl) jsonResponse(['success' => false, 'error' => 'Image data required']);
    $b64 = preg_replace('#^data:image/\w+;base64,#i', '', $dataUrl);
    $bin = base64_decode($b64);
    if ($bin === false || strlen($bin) > 3 * 1024 * 1024) jsonResponse(['success' => false, 'error' => 'Invalid or oversized image (max 3MB)']);
    
    // Basic image sanity check
    if (@getimagesizefromstring($bin) === false) jsonResponse(['success' => false, 'error' => 'Not a valid image']);
    
    $dirRel = 'uploads/drivers';
    $dirAbs = __DIR__ . '/../../' . $dirRel;
    if (!is_dir($dirAbs)) @mkdir($dirAbs, 0755, true);
    $fname = 'drv_' . $driverIdW . '_' . time() . '.jpg';
    $ok = @file_put_contents($dirAbs . '/' . $fname, $bin);
    if (!$ok) jsonResponse(['success' => false, 'error' => 'Could not save image on server']);
    
    $url = 'https://pavancab.com/app/' . $dirRel . '/' . $fname;
    dbExec("UPDATE app_drivers SET profile_image = ? WHERE id = ?", 'si', [$url, $driverIdW]);
    jsonResponse(['success' => true, 'message' => 'Profile photo updated', 'photo_url' => $url]);
}
