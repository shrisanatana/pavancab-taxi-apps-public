package com.pavancab.driver.ui.wallet

import android.app.Activity
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.google.gson.JsonObject
import com.razorpay.Checkout
import com.razorpay.PaymentResultListener
import com.pavancab.driver.data.Repository
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import kotlinx.coroutines.launch
import org.json.JSONObject

private data class Txn(
    val id: Int,
    val type: String,
    val amount: Double,
    val balanceAfter: Double,
    val note: String,
    val createdAt: String
)

private var currentOrderId: String = ""

@Composable
fun WalletScreen(repo: Repository, onBack: () -> Unit, activity: com.pavancab.driver.MainActivity?) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var balance by remember { mutableDoubleStateOf(0.0) }
    var minRequired by remember { mutableDoubleStateOf(200.0) }
    var minDeposit by remember { mutableDoubleStateOf(500.0) }
    var isSubscribed by remember { mutableStateOf(false) }
    var txns by remember { mutableStateOf<List<Txn>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var msg by remember { mutableStateOf("") }
    var msgIsError by remember { mutableStateOf(false) }
    var showAddDialog by remember { mutableStateOf(false) }
    var customAmount by remember { mutableStateOf("") }
    var paying by remember { mutableStateOf(false) }

    fun load() {
        scope.launch {
            loading = true
            try {
                val w = repo.getWallet()
                balance = w.get("balance")?.asDouble ?: 0.0
                minRequired = w.get("min_required")?.asDouble ?: 200.0
                minDeposit = w.get("min_deposit")?.asDouble ?: 500.0
                isSubscribed = w.get("is_subscribed")?.asBoolean == true
                val arr = w.getAsJsonArray("transactions")
                txns = (0 until (arr?.size() ?: 0)).map { i ->
                    val o = arr!![i].asJsonObject
                    Txn(
                        id = o.get("id")?.asInt ?: 0,
                        type = try { o.get("type")?.asString ?: "" } catch (_: Exception) { "" },
                        amount = try { o.get("amount")?.asDouble ?: 0.0 } catch (_: Exception) { 0.0 },
                        balanceAfter = try { o.get("balance_after")?.asDouble ?: 0.0 } catch (_: Exception) { 0.0 },
                        note = try { o.get("note")?.asString ?: "" } catch (_: Exception) { "" },
                        createdAt = try { o.get("created_at")?.asString ?: "" } catch (_: Exception) { "" }
                    )
                }
            } catch (_: Exception) {}
            loading = false
        }
    }

    LaunchedEffect(Unit) { load() }

    // Auto-refresh wallet balance + transactions while visible
    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(15000)
            if (lifecycleOwner.lifecycle.currentState.isAtLeast(androidx.lifecycle.Lifecycle.State.STARTED)) load()
        }
    }

    // Razorpay result listener via MainActivity's callback
    val mainAct = activity
    DisposableEffect(Unit) {
        onDispose { mainAct?.setPaymentCallback(null) }
    }

    fun startDeposit(amount: Double) {
        if (amount < minDeposit) { msg = "Minimum deposit is \u20B9${minDeposit.toInt()}"; msgIsError = true; return }
        paying = true
        scope.launch {
            try {
                val order = repo.createWalletOrder(amount)
                val ok = order.get("success")?.asBoolean == true
                if (!ok) {
                    msg = order.get("error")?.asString ?: "Could not start payment"; msgIsError = true; paying = false
                    return@launch
                }
                val keyId = order.get("key_id")?.asString ?: ""
                val orderId = order.get("order_id")?.asString ?: ""
                currentOrderId = orderId
                Checkout.preload(context)
                val checkout = Checkout()
                checkout.setKeyID(keyId)
                val options = JSONObject().apply {
                    put("name", "PAVANCAB Driver Wallet")
                    put("description", "Add \u20B9${amount.toInt()} to wallet")
                    put("order_id", orderId)
                    put("currency", "INR")
                    put("amount", (amount * 100).toInt())
                    put("theme", JSONObject().put("color", "#D4AF37"))
                }
                mainAct?.setPaymentCallback(object : PaymentResultListener {
                    override fun onPaymentSuccess(razorpayPaymentId: String?) {
                        val pId = razorpayPaymentId ?: ""
                        if (pId.isNotBlank() && currentOrderId.isNotBlank()) {
                            scope.launch {
                                try {
                                    val r = repo.verifyWalletPayment(currentOrderId, pId)
                                    val ok2 = r.get("success")?.asBoolean == true
                                    msg = if (ok2) r.get("message")?.asString ?: "Wallet credited!" else r.get("error")?.asString ?: "Verification failed"
                                    msgIsError = !ok2
                                } catch (e: Exception) { msg = "Verify failed: ${e.message}"; msgIsError = true }
                                paying = false
                                load()
                            }
                        } else { paying = false; load() }
                    }
                    override fun onPaymentError(code: Int, response: String?) {
                        paying = false
                        msg = "Payment cancelled or failed"
                        msgIsError = true
                    }
                })
                checkout.open(activity, options)
            } catch (e: Exception) {
                msg = "Payment failed: ${e.message}"; msgIsError = true; paying = false
            }
        }
    }

    Scaffold(
        containerColor = DarkBg,
        topBar = {
            Surface(color = DarkBgLighter) {
                Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) }
                    Text("MY WALLET", color = White, fontSize = 17.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f))
                    Spacer(Modifier.width(12.dp))
                }
            }
        }
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            Column(Modifier.fillMaxSize().padding(16.dp)) {
                // Balance card — premium gradient
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    border = BorderStroke(2.dp, Gold.copy(alpha = 0.6f))
                ) {
                    Box(
                        modifier = Modifier.background(
                            androidx.compose.ui.graphics.Brush.verticalGradient(
                                listOf(Gold.copy(alpha = 0.22f), Gold.copy(alpha = 0.05f), DarkBgLighter)
                            )
                        )
                    ) {
                        Column(modifier = Modifier.fillMaxWidth().padding(vertical = 22.dp, horizontal = 18.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.AccountBalanceWallet, null, tint = Gold, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("TOTAL BALANCE", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 2.sp)
                            }
                            Spacer(Modifier.height(6.dp))
                            Text("\u20B9${balance.toInt()}", color = Gold, fontSize = 44.sp, fontWeight = FontWeight.Black)
                            Spacer(Modifier.height(10.dp))
                            if (!isSubscribed) {
                                val canAcceptNow = balance >= minRequired
                                Surface(shape = RoundedCornerShape(20.dp), color = if (canAcceptNow) Emerald.copy(alpha = 0.15f) else Red.copy(alpha = 0.15f)) {
                                    Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Icon(if (canAcceptNow) Icons.Default.CheckCircle else Icons.Default.Warning, null, tint = if (canAcceptNow) Emerald else Red, modifier = Modifier.size(14.dp))
                                        Spacer(Modifier.width(6.dp))
                                        Text(
                                            if (canAcceptNow) "Ready \u2014 you can accept rides"
                                            else "Add \u20B9${(minRequired - balance).toInt().coerceAtLeast(1)} more to accept rides",
                                            color = if (canAcceptNow) Emerald else Red, fontSize = 11.sp, fontWeight = FontWeight.Bold
                                        )
                                    }
                                }
                            } else {
                                Surface(shape = RoundedCornerShape(20.dp), color = Emerald.copy(alpha = 0.15f)) {
                                    Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Default.WorkspacePremium, null, tint = Emerald, modifier = Modifier.size(14.dp))
                                        Spacer(Modifier.width(6.dp))
                                        Text("PREMIUM ACTIVE \u2014 zero commission on all rides", color = Emerald, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                            Spacer(Modifier.height(16.dp))
                            Button(
                                onClick = { showAddDialog = true },
                                modifier = Modifier.fillMaxWidth().height(48.dp),
                                shape = RoundedCornerShape(14.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                            ) {
                                Icon(Icons.Default.AddCard, null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("ADD MONEY TO WALLET", fontSize = 13.sp, fontWeight = FontWeight.Black)
                            }
                            Spacer(Modifier.height(8.dp))
                            Text("\u24D2 Deposits are non-refundable", color = Gray600, fontSize = 9.sp)
                        }
                    }
                }

                if (msg.isNotEmpty()) {
                    Spacer(Modifier.height(8.dp))
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = (if (msgIsError) Red else Emerald).copy(alpha = 0.1f), border = BorderStroke(1.dp, (if (msgIsError) Red else Emerald).copy(alpha = 0.4f))) {
                        Text(msg, color = if (msgIsError) Red else Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(12.dp))
                    }
                }

                Spacer(Modifier.height(14.dp))
                Text("TRANSACTION HISTORY", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)

                LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(vertical = 8.dp)) {
                    if (!loading && txns.isEmpty()) {
                        item {
                            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = CardBg) {
                                Text("No transactions yet. Add money to get started.", color = Gray500, fontSize = 12.sp, modifier = Modifier.padding(16.dp))
                            }
                        }
                    }
                    items(txns, key = { it.id }) { t ->
                        val isCredit = t.amount >= 0
                        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                            Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                Surface(modifier = Modifier.size(36.dp).clip(RoundedCornerShape(10.dp)), color = (if (isCredit) Emerald else Orange).copy(alpha = 0.15f)) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(
                                            when (t.type) {
                                                "deposit" -> Icons.Default.AddCard
                                                "commission" -> Icons.Default.CurrencyRupee
                                                "subscription" -> Icons.Default.WorkspacePremium
                                                else -> Icons.Default.SwapHoriz
                                            },
                                            null, tint = if (isCredit) Emerald else Orange, modifier = Modifier.size(18.dp)
                                        )
                                    }
                                }
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(
                                        when (t.type) {
                                            "deposit" -> "Money Added"
                                            "commission" -> "Ride Commission"
                                            "subscription" -> "Premium Subscription"
                                            else -> t.type.replaceFirstChar { it.uppercase() }
                                        },
                                        color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold
                                    )
                                    if (t.note.isNotBlank()) Text(t.note, color = Gray500, fontSize = 10.sp, maxLines = 1)
                                    Text(t.createdAt.take(16).replace('T', ' '), color = Gray600, fontSize = 9.sp)
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(
                                        "${if (isCredit) "+" else "-"}\u20B9${kotlin.math.abs(t.amount).toInt()}",
                                        color = if (isCredit) Emerald else Orange, fontSize = 15.sp, fontWeight = FontWeight.Black
                                    )
                                    Text("Bal \u20B9${t.balanceAfter.toInt()}", color = Gray500, fontSize = 9.sp)
                                }
                            }
                        }
                    }
                }
            }
            if (loading || paying) LoadingOverlay(if (paying) "Processing payment..." else "Loading...")
        }
    }

    // Add money dialog
    if (showAddDialog) {
        AlertDialog(
            onDismissRequest = { if (!paying) showAddDialog = false },
            containerColor = DarkBgLighter,
            title = { Text("Add Money to Wallet", color = White, fontWeight = FontWeight.Bold) },
            text = {
                Column {
                    Text("Minimum \u20B9${minDeposit.toInt()} \u2022 Payments are non-refundable", color = Gray400, fontSize = 11.sp)
                    Spacer(Modifier.height(10.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(500.0, 1000.0, 2000.0).forEach { amt ->
                            OutlinedButton(
                                onClick = { customAmount = amt.toInt().toString() },
                                modifier = Modifier.weight(1f),
                                shape = RoundedCornerShape(8.dp),
                                colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold),
                                border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)),
                                contentPadding = PaddingValues(horizontal = 2.dp)
                            ) { Text("\u20B9${amt.toInt()}", fontWeight = FontWeight.Black, fontSize = 12.sp) }
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(
                        value = customAmount,
                        onValueChange = { customAmount = it.filter { c -> c.isDigit() }.take(6) },
                        placeholder = { Text("Or enter amount (\u20B9)", color = Gray600) },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(10.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold)
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val amt = customAmount.toDoubleOrNull() ?: 0.0
                        if (amt >= minDeposit) { showAddDialog = false; startDeposit(amt) }
                    },
                    enabled = (customAmount.toDoubleOrNull() ?: 0.0) >= minDeposit && !paying,
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                ) { Text("PAY NOW", fontWeight = FontWeight.Black) }
            },
            dismissButton = { TextButton(onClick = { if (!paying) showAddDialog = false }) { Text("Cancel", color = Gray400) } }
        )
    }
}
