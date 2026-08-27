package com.pavancab.driver.ui.subscription

import android.app.Activity
import android.content.Context
import android.content.Intent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.razorpay.Checkout
import com.pavancab.driver.data.Repository
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import kotlinx.coroutines.launch
import org.json.JSONObject

@Composable
fun SubscriptionScreen(
    repo: Repository,
    onBack: () -> Unit = {},
    activity: com.pavancab.driver.MainActivity? = null
) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    var loading by remember { mutableStateOf(true) }
    var isSubscribed by remember { mutableStateOf(false) }
    var endDate by remember { mutableStateOf("") }
    var daysLeft by remember { mutableIntStateOf(0) }
    var subAmount by remember { mutableDoubleStateOf(1000.0) }
    var rideCommission by remember { mutableDoubleStateOf(200.0) }
    var pendingCount by remember { mutableIntStateOf(0) }
    var pendingTotal by remember { mutableDoubleStateOf(0.0) }
    var canAccept by remember { mutableStateOf(true) }
    var payProcessing by remember { mutableStateOf(false) }
    var paySuccess by remember { mutableStateOf("") }
    var payError by remember { mutableStateOf("") }
    var showCancelConfirm by remember { mutableStateOf(false) }
    var cancelling by remember { mutableStateOf(false) }

    var paymentHistory by remember { mutableStateOf<List<Map<String, Any>>>(emptyList()) }
    var subscriptionHistory by remember { mutableStateOf<List<Map<String, Any>>>(emptyList()) }
    var showHistory by remember { mutableStateOf(false) }
    var walletBalance by remember { mutableDoubleStateOf(0.0) }
    var subscribingFromWallet by remember { mutableStateOf(false) }

    fun loadStatus() {
        scope.launch {
            loading = true
            try {
                val res = repo.getSubscriptionStatus()
                isSubscribed = res.get("is_subscribed")?.asBoolean == true || res.get("has_active_subscription")?.asBoolean == true
                endDate = res.get("end_date")?.asString ?: ""
                daysLeft = res.get("days_left")?.asInt ?: 0
                subAmount = res.get("subscription_amount")?.asDouble ?: 1000.0
                rideCommission = res.get("commission_per_ride")?.asDouble ?: 200.0
                pendingCount = res.get("pending_payments_count")?.asInt ?: res.get("pending_payments")?.asInt ?: 0
                pendingTotal = res.get("pending_payments_total")?.asDouble ?: res.get("pending_amount")?.asDouble ?: 0.0
                canAccept = res.get("can_accept")?.asBoolean == true
            } catch (_: Exception) {}
            try {
                val w = repo.getWallet()
                walletBalance = w.get("balance")?.asDouble ?: 0.0
            } catch (_: Exception) {}
            loading = false
        }
    }

    fun subscribeNow() {
        payError = ""; paySuccess = ""
        subscribingFromWallet = true
        scope.launch {
            try {
                val r = repo.subscribeFromWallet()
                if (r.get("success")?.asBoolean == true) {
                    paySuccess = r.get("message")?.asString ?: "Premium activated!"
                    loadStatus()
                } else {
                    payError = (r.get("error")?.asString ?: "Could not subscribe") + "  \u2014 Add money to your wallet first."
                }
            } catch (e: Exception) { payError = e.message ?: "Failed" }
            subscribingFromWallet = false
        }
    }

    fun loadHistory() {
        scope.launch {
            try {
                val res = repo.getPaymentHistory()
                val arr = res.getAsJsonArray("payments")
                paymentHistory = (0 until (arr?.size() ?: 0)).map { arr!![it].asJsonObject }.map { m ->
                    mapOf(
                        "type" to (m.get("type")?.asString ?: ""),
                        "amount" to (m.get("amount")?.asDouble ?: 0.0),
                        "status" to (m.get("status")?.asString ?: ""),
                        "booking_id" to (m.get("booking_id")?.asInt ?: 0),
                        "created_at" to (m.get("created_at")?.asString ?: ""),
                        "paid_at" to (m.get("paid_at")?.asString ?: "")
                    )
                }
                val subArr = res.getAsJsonArray("subscriptions")
                subscriptionHistory = (0 until (subArr?.size() ?: 0)).map { subArr!![it].asJsonObject }.map { m ->
                    mapOf(
                        "amount" to (m.get("amount")?.asDouble ?: 0.0),
                        "start_date" to (m.get("start_date")?.asString ?: ""),
                        "end_date" to (m.get("end_date")?.asString ?: ""),
                        "status" to (m.get("status")?.asString ?: ""),
                        "created_at" to (m.get("created_at")?.asString ?: "")
                    )
                }
            } catch (_: Exception) {}
        }
    }

    LaunchedEffect(Unit) { loadStatus() }

    // Auto-refresh subscription status, days-left & wallet balance while visible
    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(30000)
            if (lifecycleOwner.lifecycle.currentState.isAtLeast(androidx.lifecycle.Lifecycle.State.STARTED)) loadStatus()
        }
    }

    DisposableEffect(activity) {
        val listener = object : com.razorpay.PaymentResultListener {
            override fun onPaymentSuccess(razorpayPaymentId: String?) {
                payProcessing = false
                val prefs = context.getSharedPreferences("rzp", Context.MODE_PRIVATE)
                val orderId = prefs.getString("order_id", "") ?: ""
                val type = prefs.getString("type", "") ?: ""
                val bId = prefs.getInt("booking_id", 0)
                scope.launch {
                    try {
                        val res = repo.verifyPayment(orderId, razorpayPaymentId ?: "", type = type, bookingId = bId)
                        if (res.get("success")?.asBoolean == true) {
                            paySuccess = if (type == "subscription") "Subscription activated!" else "Commission paid!"
                            loadStatus()
                        } else {
                            payError = res.get("error")?.asString ?: "Verification failed"
                        }
                    } catch (e: Exception) {
                        payError = e.message ?: "Verification failed"
                    }
                }
            }
            override fun onPaymentError(code: Int, description: String?) {
                payProcessing = false
                payError = "Payment failed: $description"
            }
        }
        activity?.setPaymentCallback(listener)
        onDispose { activity?.setPaymentCallback(null) }
    }

    LaunchedEffect(Unit) {
        Checkout.preload(context)
    }

    fun startRazorpayPayment(type: String, amount: Double, bookingId: Int = 0) {
        payError = ""
        paySuccess = ""
        payProcessing = true
        scope.launch {
            try {
                val orderRes = repo.createOrder(type, bookingId)
                if (orderRes.get("success")?.asBoolean != true) {
                    payProcessing = false
                    payError = orderRes.get("error")?.asString ?: "Failed to create order"
                    return@launch
                }
                val orderId = orderRes.get("order_id")?.asString ?: ""
                val amountPaise = (orderRes.get("amount")?.asDouble ?: 0.0).toInt() * 100
                val keyId = orderRes.get("razorpay_key")?.asString ?: orderRes.get("key_id")?.asString ?: ""

                context.getSharedPreferences("rzp", Context.MODE_PRIVATE).edit()
                    .putString("order_id", orderId)
                    .putString("type", type)
                    .putInt("booking_id", bookingId)
                    .apply()

                val checkout = Checkout()
                checkout.setKeyID(keyId)
                val options = JSONObject().apply {
                    put("amount", amountPaise)
                    put("currency", "INR")
                    put("name", "PAVANCAB Goa")
                    put("description", if (type == "subscription") "Monthly Subscription" else "Ride Commission")
                    put("order_id", orderId)
                    put("theme", JSONObject().put("color", "#D4AF37"))
                }
                val act = findActivity(context)
                if (act != null) {
                    checkout.open(act, options)
                } else {
                    payProcessing = false
                    payError = "Cannot start payment"
                }
            } catch (e: Exception) {
                payProcessing = false
                payError = e.message ?: "Payment error"
            }
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(modifier = Modifier.fillMaxSize().padding(16.dp).verticalScroll(rememberScrollState())) {
            Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                IconButton(onClick = onBack) {
                    Icon(Icons.Default.ArrowBack, "Back", tint = White, modifier = Modifier.size(24.dp))
                }
                Text("Subscription & Payments", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.height(16.dp))

            if (loading) {
                LoadingOverlay("Loading...")
                return
            }

            // Status card
            if (isSubscribed) {
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Emerald.copy(alpha = 0.1f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(24.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("ACTIVE SUBSCRIPTION", color = Emerald, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(8.dp))
                        Text("Valid until $endDate", color = White, fontSize = 14.sp)
                        Text("$daysLeft days remaining", color = Gray400, fontSize = 12.sp)
                        Spacer(Modifier.height(4.dp))
                        Text("No commission on rides", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        Spacer(Modifier.height(8.dp))
                        OutlinedButton(
                            onClick = { showCancelConfirm = true },
                            modifier = Modifier.fillMaxWidth().height(36.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.outlinedButtonColors(contentColor = Red),
                            border = BorderStroke(1.dp, Red.copy(alpha = 0.5f))
                        ) {
                            Icon(Icons.Default.Cancel, null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Cancel Subscription", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            } else {
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Red.copy(alpha = 0.1f), border = BorderStroke(1.dp, Red.copy(alpha = 0.3f))) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Warning, null, tint = Red, modifier = Modifier.size(24.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("NO ACTIVE SUBSCRIPTION", color = Red, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text("You pay ₹${rideCommission.toInt()} commission per ride", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        if (pendingCount > 0) {
                            Spacer(Modifier.height(8.dp))
                            Text("You have $pendingCount unpaid ride commission(s)", color = White, fontSize = 13.sp)
                            Text("Total pending: ₹${pendingTotal.toInt()}", color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            // Pay options
            Text("CHOOSE PAYMENT OPTION", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(8.dp))

            // GO PREMIUM — paid FROM WALLET
            Surface(
                shape = RoundedCornerShape(14.dp),
                color = Gold.copy(alpha = 0.08f),
                border = BorderStroke(1.dp, Gold.copy(alpha = if (isSubscribed) 0.15f else 0.45f))
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.WorkspacePremium, null, tint = Gold, modifier = Modifier.size(22.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("GO PREMIUM", color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Black)
                        Spacer(Modifier.weight(1f))
                        Text("\u20B9${subAmount.toInt()}/mo", color = Gold, fontSize = 17.sp, fontWeight = FontWeight.Black)
                    }
                    Spacer(Modifier.height(10.dp))
                    PremiumBenefit("Keep 100% of every fare \u2014 zero commission")
                    PremiumBenefit("See new rides up to 1 minute BEFORE other drivers")
                    PremiumBenefit("Premium crown badge on your driver profile")
                    PremiumBenefit("Accept unlimited rides \u2014 no per-ride charges")
                    if (isSubscribed) {
                        Spacer(Modifier.height(10.dp))
                        Text("\u2713 Currently active \u2014 valid until $endDate", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    } else {
                        Spacer(Modifier.height(12.dp))
                        Button(
                            onClick = { subscribeNow() },
                            enabled = !subscribingFromWallet && !payProcessing,
                            modifier = Modifier.fillMaxWidth().height(46.dp),
                            shape = RoundedCornerShape(12.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Gold,
                                contentColor = DarkBg,
                                disabledContainerColor = Gold.copy(alpha = 0.4f)
                            )
                        ) {
                            if (subscribingFromWallet) {
                                CircularProgressIndicator(modifier = Modifier.size(16.dp), color = DarkBg, strokeWidth = 2.dp)
                                Spacer(Modifier.width(8.dp))
                                Text("ACTIVATING...", fontSize = 12.sp, fontWeight = FontWeight.Black)
                            } else {
                                Icon(Icons.Default.AccountBalanceWallet, null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("SUBSCRIBE FROM WALLET (\u20B9${walletBalance.toInt()} bal.)", fontSize = 12.sp, fontWeight = FontWeight.Black)
                            }
                        }
                        if (walletBalance < subAmount) {
                            Spacer(Modifier.height(6.dp))
                            Text(
                                "You need \u20B9${subAmount.toInt()} in your wallet to subscribe. Add \u20B9${(subAmount - walletBalance).toInt()} more from the Wallet screen.",
                                color = Orange, fontSize = 10.sp
                            )
                        }
                        Spacer(Modifier.height(6.dp))
                        Text("Paid instantly from your wallet balance \u2022 Cancel anytime", color = Gray600, fontSize = 9.sp)
                    }
                }
            }

            Spacer(Modifier.height(12.dp))

            // How commission works without premium
            Surface(
                shape = RoundedCornerShape(14.dp),
                color = CardBg,
                border = BorderStroke(1.dp, CardBorder)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.CurrencyRupee, null, tint = Blue, modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Without Premium", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "Self-accepted rides: \u20B9${rideCommission.toInt()} commission auto-deducted from your wallet after each completed ride.\n\nRides assigned by our admin team: ALWAYS commission-free.",
                        color = Gray400, fontSize = 12.sp, lineHeight = 17.sp
                    )
                    Spacer(Modifier.height(8.dp))
                    Text("\u24D0 Wallet payments are non-refundable.", color = Gray600, fontSize = 10.sp)
                }
            }

            // Processing / success / error
            if (payProcessing) {
                Spacer(Modifier.height(16.dp))
                LoadingOverlay("Processing payment...")
            }
            if (paySuccess.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Emerald.copy(alpha = 0.1f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                    Text(paySuccess, color = Emerald, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.padding(12.dp))
                }
            }
            if (payError.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Red.copy(alpha = 0.1f), border = BorderStroke(1.dp, Red.copy(alpha = 0.3f))) {
                    Text(payError, color = Red, fontSize = 12.sp, modifier = Modifier.padding(12.dp))
                }
            }

            Spacer(Modifier.height(20.dp))

            // Payment history toggle
            Surface(
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable {
                    showHistory = !showHistory
                    if (showHistory) loadHistory()
                },
                shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)
            ) {
                Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.History, null, tint = Gray400, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Payment History", color = White, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                    Icon(if (showHistory) Icons.Default.ExpandLess else Icons.Default.ExpandMore, null, tint = Gray400)
                }
            }

            if (showHistory) {
                Spacer(Modifier.height(8.dp))
                if (subscriptionHistory.isNotEmpty()) {
                    Text("SUBSCRIPTIONS", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 4.dp))
                    Spacer(Modifier.height(4.dp))
                    subscriptionHistory.forEach { sub ->
                        Surface(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp), shape = RoundedCornerShape(10.dp), color = CardBg) {
                            Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text("₹${(sub["amount"] as? Double ?: 0.0).toInt()} — ${sub["start_date"]} to ${sub["end_date"]}", color = White, fontSize = 12.sp)
                                    Text(sub["status"].toString().uppercase(), color = if (sub["status"] == "active") Emerald else Gray500, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
                if (paymentHistory.isNotEmpty()) {
                    Text("RIDE COMMISSIONS", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 4.dp))
                    Spacer(Modifier.height(4.dp))
                    paymentHistory.forEach { pay ->
                        Surface(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp), shape = RoundedCornerShape(10.dp), color = CardBg) {
                            Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                                Column(modifier = Modifier.weight(1f)) {
                                    val bkId = pay["booking_id"] as? Int ?: 0
                                    Text("₹${(pay["amount"] as? Double ?: 0.0).toInt()} — Ride #$bkId", color = White, fontSize = 12.sp)
                                    Text(pay["status"].toString().uppercase(), color = if (pay["status"] == "paid") Emerald else Gold, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                }
                                Text(pay["paid_at"]?.toString()?.take(10) ?: pay["created_at"]?.toString()?.take(10) ?: "", color = Gray500, fontSize = 10.sp)
                            }
                        }
                    }
                } else if (subscriptionHistory.isEmpty()) {
                    Text("No payment history yet", color = Gray500, fontSize = 12.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth().padding(12.dp))
                }
            }

            Spacer(Modifier.height(40.dp))
        }

        // Cancel subscription confirmation dialog
        if (showCancelConfirm) {
            Surface(modifier = Modifier.fillMaxSize().background(Color.Black.copy(alpha = 0.7f)).clickable { showCancelConfirm = false }, color = Color.Transparent) {
                Box(contentAlignment = Alignment.Center) {
                    Surface(modifier = Modifier.padding(32.dp), shape = RoundedCornerShape(16.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) {
                        Column(modifier = Modifier.padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Default.Warning, null, tint = Red, modifier = Modifier.size(40.dp))
                            Spacer(Modifier.height(12.dp))
                            Text("Cancel Subscription?", color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.height(8.dp))
                            Text("You can still use your subscription until $endDate. No refund will be given.", color = Gray400, fontSize = 13.sp, textAlign = TextAlign.Center)
                            Spacer(Modifier.height(16.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                                OutlinedButton(onClick = { showCancelConfirm = false }, shape = RoundedCornerShape(10.dp), border = BorderStroke(1.dp, Gray600)) {
                                    Text("Keep", color = White, fontSize = 13.sp)
                                }
                                Button(
                                    onClick = {
                                        cancelling = true
                                        scope.launch {
                                            try {
                                                repo.cancelSubscription()
                                                showCancelConfirm = false
                                                loadStatus()
                                            } catch (e: Exception) {
                                                payError = e.message ?: "Cancel failed"
                                            }
                                            cancelling = false
                                        }
                                    },
                                    enabled = !cancelling,
                                    shape = RoundedCornerShape(10.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Red)
                                ) {
                                    if (cancelling) CircularProgressIndicator(modifier = Modifier.size(16.dp), color = White, strokeWidth = 2.dp)
                                    else Text("Cancel Subscription", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

private fun findActivity(context: Context): Activity? {
    if (context is Activity) return context
    if (context is android.content.ContextWrapper) {
        var ctx = context.baseContext
        while (ctx is android.content.ContextWrapper) {
            if (ctx is Activity) return ctx
            ctx = ctx.baseContext
        }
    }
    return null
}

@Composable
private fun PremiumBenefit(text: String) {
    Row(modifier = Modifier.padding(vertical = 3.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(14.dp))
        Spacer(Modifier.width(8.dp))
        Text(text, color = Gray300, fontSize = 12.sp)
    }
}
