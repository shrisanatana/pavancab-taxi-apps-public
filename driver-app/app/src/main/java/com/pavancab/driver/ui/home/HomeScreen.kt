package com.pavancab.driver.ui.home

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.*
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import com.pavancab.driver.data.Repository
import com.pavancab.driver.model.Booking
import com.pavancab.driver.model.QuickRide
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import java.text.SimpleDateFormat
import java.util.*

private fun pickupMillis(bk: Booking): Long = try {
    val cal = Calendar.getInstance()
    val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(bk.pickupDate)
    if (d != null) cal.time = d
    val tp = bk.pickupTime.split(":")
    cal.set(Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    cal.set(Calendar.SECOND, 0)
    cal.timeInMillis
} catch (_: Exception) { Long.MAX_VALUE }

private fun quickRideMillis(qr: QuickRide): Long = try {
    val cal = Calendar.getInstance()
    val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(qr.pickupDate)
    if (d != null) cal.time = d
    val tp = qr.pickupTime.split(":")
    cal.set(Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    cal.set(Calendar.SECOND, 0)
    cal.timeInMillis
} catch (_: Exception) { 0L }

private fun formatPickupLabel(bk: Booking): String = try {
    val cal = Calendar.getInstance()
    val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(bk.pickupDate)
    if (d != null) cal.time = d
    val tp = bk.pickupTime.split(":")
    cal.set(Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    val pod = when (cal.get(Calendar.HOUR_OF_DAY)) {
        in 5..11 -> "Morning"; in 12..15 -> "Afternoon"; in 16..19 -> "Evening"; else -> "Night"
    }
    SimpleDateFormat("EEE, dd MMM \u2022 hh:mm a", Locale.getDefault()).format(cal.time) + " ($pod)"
} catch (_: Exception) { "${bk.pickupDate} ${bk.pickupTime}" }

private fun isOverdue(bk: Booking): Boolean {
    val ms = pickupMillis(bk)
    return ms != Long.MAX_VALUE && ms < System.currentTimeMillis()
}

@Composable
fun HomeScreen(
    repo: Repository,
    onBookingClick: (Booking) -> Unit,
    onNavigate: (String) -> Unit,
    onSubscription: () -> Unit = {},
    refreshTrigger: Int = 0,
    onWallet: () -> Unit = {}
) {
    var bookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var quickRides by remember { mutableStateOf<List<QuickRide>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var todayRides by remember { mutableIntStateOf(0) }
    var todayEarnings by remember { mutableDoubleStateOf(0.0) }
    var errorText by remember { mutableStateOf("") }
    var isSubscribed by remember { mutableStateOf(true) }
    var pendingPayments by remember { mutableIntStateOf(0) }
    var respondingTo by remember { mutableIntStateOf(0) }
    var decliningTo by remember { mutableIntStateOf(0) }
    var selfAssigningTo by remember { mutableIntStateOf(0) }
    var offeringTo by remember { mutableIntStateOf(0) }
    var commissionPerRide by remember { mutableDoubleStateOf(200.0) }
    var walletBalance by remember { mutableDoubleStateOf(0.0) }
    var canAcceptRides by remember { mutableStateOf(true) }
    var showWalletBlock by remember { mutableStateOf(false) }
    var scope = rememberCoroutineScope()

    val activeStatuses = listOf("ASSIGNED", "CONFIRMED", "ACCEPTED", "IN_TRANSIT", "ON_TRIP")

    suspend fun loadData() {
        try {
            val all = repo.getMyBookings()
            val now = Calendar.getInstance()
            val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(now.time)
            val todayList = all.filter { it.pickupDate == todayStr && !it.status.contains("CANCELLED") }
            todayRides = todayList.count { it.status == "COMPLETED" }
            todayEarnings = todayList.filter { it.status == "COMPLETED" }.sumOf { it.totalFare }

            // Only rides the driver hasn't responded to yet need ACCEPT/REJECT.
            // Self-accepted rides (quick ride grab) keep status ASSIGNED but decision=ACCEPTED -> they belong in ACTIVE TRIPS.
            // Dispatch-assigned rides come as CONFIRMED + decision ACCEPTED -> also ACTIVE TRIPS.
            val needsAction = all.filter { it.status == "ASSIGNED" && !it.driverDecision.equals("ACCEPTED", true) }.sortedBy { pickupMillis(it) }
            val inProgress = all.filter {
                it.status in listOf("CONFIRMED", "ACCEPTED", "IN_TRANSIT", "ON_TRIP") ||
                (it.status == "ASSIGNED" && it.driverDecision.equals("ACCEPTED", true))
            }.sortedBy { pickupMillis(it) }
            bookings = needsAction + inProgress
            errorText = ""
        } catch (e: Exception) {
            errorText = "Failed to load: ${e.message}"
        }

        try {
            val subRes = repo.getSubscriptionStatus()
            isSubscribed = subRes.get("is_subscribed")?.asBoolean == true || subRes.get("has_active_subscription")?.asBoolean == true
            pendingPayments = try { subRes.get("pending_payments_count")?.asInt ?: subRes.get("pending_payments")?.asInt ?: 0 } catch (_: Exception) { 0 }
            commissionPerRide = try { subRes.get("commission_per_ride")?.asDouble ?: 200.0 } catch (_: Exception) { 200.0 }
        } catch (_: Exception) {}

        try {
            quickRides = repo.getQuickRideList().sortedBy { quickRideMillis(it) }
            walletBalance = repo.lastWalletBalance
            canAcceptRides = repo.lastCanAcceptRides || repo.lastIsPremium
            isSubscribed = isSubscribed || repo.lastIsPremium
        } catch (_: Exception) {}

        // Direct wallet read — keeps balance fresh after deposits
        try {
            val w = repo.getWallet()
            val bal = w.get("balance")?.asDouble
            if (bal != null && bal >= 0) walletBalance = bal
            w.get("is_subscribed")?.asBoolean?.let { if (it) isSubscribed = true }
            w.get("min_required")?.asDouble?.let { if (it > 0) commissionPerRide = it }
        } catch (_: Exception) {}
    }

    LaunchedEffect(Unit) {
        loading = true
        loadData()
        loading = false
    }

    LaunchedEffect(refreshTrigger) {
        if (refreshTrigger > 0) loadData()
    }

    LaunchedEffect(Unit) {
        while (true) {
            delay(10000)
            loadData()
        }
    }

    Column(modifier = Modifier.fillMaxSize()) {
        Surface(modifier = Modifier.fillMaxWidth(), color = DarkBgLighter, shape = RoundedCornerShape(bottomStart = 20.dp, bottomEnd = 20.dp)) {
            Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 12.dp)) {
                Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.LocalTaxi, null, tint = Gold, modifier = Modifier.size(24.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("MY RIDES", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black)
                    if (isSubscribed) {
                        Spacer(Modifier.width(6.dp))
                        Surface(shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.2f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f))) {
                            Row(modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.WorkspacePremium, null, tint = Gold, modifier = Modifier.size(12.dp))
                                Text("PREMIUM", color = Gold, fontSize = 8.sp, fontWeight = FontWeight.Black)
                            }
                        }
                    }
                    Spacer(Modifier.weight(1f))
                }
                Spacer(Modifier.height(12.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    StatBox("Today Rides", "$todayRides", Icons.Default.CheckCircle, Emerald, Modifier.weight(1f))
                    StatBox("Today Earn.", fmt(todayEarnings), Icons.Default.Wallet, Gold, Modifier.weight(1f))
                    val activeCount = bookings.count { it.status in activeStatuses }
                    StatBox("Active", "$activeCount", Icons.Default.Speed, Orange, Modifier.weight(1f))
                }

                // WALLET STRIP — always visible, tap to open wallet
                Spacer(Modifier.height(8.dp))
                val walletOk = canAcceptRides || isSubscribed
                Surface(
                    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onWallet() },
                    shape = RoundedCornerShape(12.dp),
                    color = if (walletOk) Emerald.copy(alpha = 0.08f) else Red.copy(alpha = 0.1f),
                    border = BorderStroke(1.5.dp, if (walletOk) Emerald.copy(alpha = 0.45f) else Red.copy(alpha = 0.55f))
                ) {
                    Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                        Surface(modifier = Modifier.size(34.dp).clip(RoundedCornerShape(17.dp)), color = if (walletOk) Emerald.copy(alpha = 0.15f) else Red.copy(alpha = 0.15f)) {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(Icons.Default.AccountBalanceWallet, null, tint = if (walletOk) Emerald else Red, modifier = Modifier.size(18.dp))
                            }
                        }
                        Spacer(Modifier.width(10.dp))
                        Column(Modifier.weight(1f)) {
                            Text("WALLET BALANCE", color = Gray500, fontSize = 8.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                            Text(
                                if (isSubscribed)
                                    "\u20B9${walletBalance.toInt()}  \u2022  PREMIUM ACTIVE"
                                else if (walletOk)
                                    "\u20B9${walletBalance.toInt()}  \u2022  Ready for rides"
                                else
                                    "\u20B9${walletBalance.toInt()}  \u2022  Add \u20B9${(commissionPerRide - walletBalance).toInt().coerceAtLeast(1)} to accept rides",
                                color = if (walletOk || isSubscribed) White else Red,
                                fontSize = 13.sp, fontWeight = FontWeight.Black
                            )
                        }
                        Surface(shape = RoundedCornerShape(8.dp), color = if (walletOk) Emerald else Gold) {
                            Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.Add, null, tint = DarkBg, modifier = Modifier.size(13.dp))
                                Text("ADD", color = DarkBg, fontSize = 11.sp, fontWeight = FontWeight.Black)
                            }
                        }
                    }
                }

                if (!isSubscribed) {
                    Spacer(Modifier.height(8.dp))
                    Surface(
                        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { onSubscription() },
                        shape = RoundedCornerShape(10.dp), color = Gold.copy(alpha = 0.08f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f))
                    ) {
                        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.WorkspacePremium, null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Column(Modifier.weight(1f)) {
                                Text("Go PREMIUM \u2014 \u20B9${commissionPerRide.toInt()} x 0 commission", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black)
                                Text("Get rides BEFORE other drivers \u2022 keep 100% fare", color = Gray400, fontSize = 9.sp)
                            }
                            Text("SUBSCRIBE \u203A", color = Emerald, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }

                if (!isSubscribed && pendingPayments > 0) {
                    Spacer(Modifier.height(6.dp))
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { onSubscription() }, shape = RoundedCornerShape(10.dp), color = Red.copy(alpha = 0.12f), border = BorderStroke(1.dp, Red.copy(alpha = 0.4f))) {
                        Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Warning, null, tint = Red, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Pay commission or subscribe to continue", color = White, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                            Icon(Icons.Default.ChevronRight, null, tint = Gold, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
        }

        if (errorText.isNotEmpty()) {
            Text(errorText, color = Red, fontSize = 12.sp, modifier = Modifier.padding(16.dp))
        }

        if (bookings.isEmpty() && quickRides.isEmpty() && !loading) {
            EmptyState(Icons.Default.LocalTaxi, "No active rides", "Quick rides and assigned rides will appear here")
        } else {
            LazyColumn(modifier = Modifier.padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(10.dp), contentPadding = PaddingValues(vertical = 12.dp)) {
                val needsAction = bookings.filter { it.status == "ASSIGNED" && !it.driverDecision.equals("ACCEPTED", true) }
                val inProgress = bookings.filter {
                    it.status in listOf("CONFIRMED", "ACCEPTED", "IN_TRANSIT", "ON_TRIP") ||
                    (it.status == "ASSIGNED" && it.driverDecision.equals("ACCEPTED", true))
                }

                if (quickRides.isNotEmpty()) {
                    item {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.FlashOn, null, tint = Emerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("QUICK RIDES \u2014 AVAILABLE NOW", color = Emerald, fontSize = 13.sp, fontWeight = FontWeight.Black)
                            Spacer(Modifier.width(8.dp))
                            Surface(shape = RoundedCornerShape(10.dp), color = Emerald.copy(alpha = 0.2f)) {
                                Text("${quickRides.size}", color = Emerald, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                            }
                        }
                    }
                    items(quickRides, key = { it.id }) { ride ->
                        QuickRideCard(
                            ride = ride,
                            isSubscribed = isSubscribed,
                            commissionPerRide = commissionPerRide,
                            onAccept = {
                                // Always hit the API — backend decides; on wallet shortage show top-up dialog
                                selfAssigningTo = ride.id
                                scope.launch {
                                    try {
                                        val res = repo.selfAssignRide(ride.id)
                                        val ok = try { res.get("success")?.asBoolean == true } catch (_: Exception) { false }
                                        if (!ok) {
                                            var err = "Could not accept this ride"
                                            var needWallet = false
                                            try {
                                                err = res.get("error")?.let { if (!it.isJsonNull) it.asString else err } ?: err
                                                needWallet = res.get("requires_wallet_topup")?.asBoolean == true || err.contains("wallet", true)
                                            } catch (_: Exception) {}
                                            errorText = err
                                            if (needWallet) showWalletBlock = true
                                        }
                                        loadData()
                                    } catch (e: Exception) {
                                        errorText = "Failed: ${e.message}"
                                    }
                                    selfAssigningTo = 0
                                }
                            },
                            onDecline = {
                                decliningTo = ride.id
                                scope.launch {
                                    try { repo.declineRide(ride.id) } catch (_: Exception) {}
                                    quickRides = quickRides.filter { it.id != ride.id }
                                    decliningTo = 0
                                }
                            },
                            onOffer = { amount, note ->
                                if (!isSubscribed && walletBalance < 200.0) {
                                    showWalletBlock = true
                                } else {
                                    offeringTo = ride.id
                                    scope.launch {
                                        try {
                                            val res = repo.submitOffer(ride.id, amount, note)
                                            val err = try { res.get("error")?.let { if (!it.isJsonNull) it.asString else "" } ?: "" } catch (_: Exception) { "" }
                                            errorText = if (err.isNotEmpty()) err else "Offer of \u20B9${amount.toInt()} sent to passenger"
                                            loadData()
                                        } catch (e: Exception) {
                                            errorText = "Offer failed: ${e.message}"
                                        }
                                        offeringTo = 0
                                    }
                                }
                            },
                            busy = selfAssigningTo == ride.id || decliningTo == ride.id || offeringTo == ride.id
                        )
                    }
                }

                if (needsAction.isNotEmpty()) {
                    item {
                        Spacer(Modifier.height(4.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.NotificationsActive, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("ASSIGNED TO YOU", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Black)
                            Spacer(Modifier.width(8.dp))
                            Surface(shape = RoundedCornerShape(10.dp), color = Gold.copy(alpha = 0.2f)) {
                                Text("${needsAction.size}", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                            }
                        }
                    }
                    items(needsAction) { bk ->
                        RideRequestCard(
                            booking = bk,
                            isSubscribed = isSubscribed,
                            commissionPerRide = commissionPerRide,
                            onAccept = {
                                respondingTo = bk.id
                                scope.launch {
                                    try {
                                        repo.respondBooking(bk.id, "ACCEPT")
                                        loadData()
                                    } catch (e: Exception) {
                                        errorText = "Accept failed: ${e.message}"
                                    }
                                    respondingTo = 0
                                }
                            },
                            onReject = {
                                respondingTo = bk.id
                                scope.launch {
                                    try {
                                        repo.respondBooking(bk.id, "REJECT")
                                        loadData()
                                    } catch (e: Exception) {
                                        errorText = "Reject failed: ${e.message}"
                                    }
                                    respondingTo = 0
                                }
                            },
                            onClick = { onBookingClick(bk) },
                            responding = respondingTo == bk.id
                        )
                    }
                }

                if (inProgress.isNotEmpty()) {
                    item {
                        Spacer(Modifier.height(4.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.PlayArrow, null, tint = Orange, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("ACTIVE TRIPS", color = Orange, fontSize = 13.sp, fontWeight = FontWeight.Black)
                        }
                    }
                    items(inProgress) { bk ->
                        DriverBookingCard(bk, isSubscribed, commissionPerRide) { onBookingClick(bk) }
                    }
                }
            }
        }

        if (loading) LoadingOverlay("Loading rides...")
    }

    // Wallet low-balance block
    if (showWalletBlock) {
        AlertDialog(
            onDismissRequest = { showWalletBlock = false },
            containerColor = DarkBgLighter,
            icon = { Icon(Icons.Default.AccountBalanceWallet, null, tint = Red, modifier = Modifier.size(32.dp)) },
            title = { Text("Wallet Balance Low", color = White, fontWeight = FontWeight.Bold) },
            text = {
                Column {
                    Text(
                        "You don't have \u20B9${commissionPerRide.toInt()} minimum in your wallet to get this ride.",
                        color = Gray300, fontSize = 13.sp, lineHeight = 18.sp
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "Current balance: \u20B9${walletBalance.toInt()}  \u2022  Required: \u20B9${commissionPerRide.toInt()}",
                        color = Gray500, fontSize = 11.sp
                    )
                    Spacer(Modifier.height(10.dp))
                    Surface(shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.08f)) {
                        Text(
                            "Tip: Premium members never pay commission \u2014 or add money to your wallet to keep accepting rides.",
                            color = Gold, fontSize = 11.sp, lineHeight = 15.sp, modifier = Modifier.padding(8.dp)
                        )
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = { showWalletBlock = false; onWallet() },
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)
                ) { Text("ADD MONEY TO WALLET", fontWeight = FontWeight.Black) }
            },
            dismissButton = {
                TextButton(onClick = { showWalletBlock = false; onSubscription() }) { Text("GO PREMIUM", color = Gold) }
            }
        )
    }
}

@Composable
private fun QuickRideCard(
    ride: QuickRide,
    isSubscribed: Boolean,
    commissionPerRide: Double,
    onAccept: () -> Unit,
    onDecline: () -> Unit,
    onOffer: (Double, String) -> Unit,
    busy: Boolean
) {
    val fare = ride.totalFare
    var showOfferInput by remember { mutableStateOf(false) }
    var offerAmount by remember { mutableStateOf("") }
    var offerNote by remember { mutableStateOf("") }

    // Countdown window (ride locked for drivers until it elapses): server sends the true remaining
    // seconds (window_seconds). We lock an absolute target once per ride so switching screens or
    // refreshing never resets the countdown to a full window, and never shows a fake lock when the
    // ride is already open (window_seconds == 0).
    val serverRemainingMs = ride.windowSeconds.takeIf { it > 0 }?.let { it * 1000L } ?: 0L
    var targetMs by remember(ride.id) { mutableStateOf(0L) }
    var countdownLive by remember(ride.id) { mutableStateOf(0L) }
    LaunchedEffect(ride.id) {
        if (serverRemainingMs > 0 && targetMs <= 0L) {
            targetMs = System.currentTimeMillis() + serverRemainingMs
            countdownLive = serverRemainingMs / 1000L
        }
    }
    LaunchedEffect(ride.id, countdownLive) {
        while (targetMs > 0L && countdownLive > 0) {
            delay(1000L)
            countdownLive = maxOf(0L, (targetMs - System.currentTimeMillis()) / 1000L)
        }
    }
    val windowLocked = countdownLive > 0
    val mm = "%02d".format(countdownLive / 60)
    val ss = "%02d".format(countdownLive % 60)

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = Emerald.copy(alpha = 0.06f),
        border = BorderStroke(2.dp, Emerald.copy(alpha = 0.5f))
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Emerald.copy(alpha = 0.15f)) {
                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.FlashOn, null, tint = Emerald, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(formatQuickTimeLabel(ride), color = Emerald, fontSize = 11.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f))
                    if (ride.offerCount > 0) {
                        Surface(shape = RoundedCornerShape(10.dp), color = Gold.copy(alpha = 0.2f)) {
                            Text("${ride.offerCount} offer(s)", color = Gold, fontSize = 9.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                        }
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(ride.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                if (windowLocked) {
                    Surface(shape = RoundedCornerShape(6.dp), color = Amber.copy(alpha = 0.15f)) {
                        Text("LOCKED $mm:$ss", color = Amber, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                    }
                } else {
                    Surface(shape = RoundedCornerShape(6.dp), color = Emerald.copy(alpha = 0.2f)) {
                        Text("OPEN", color = Emerald, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                    }
                }
            }
            if (windowLocked) {
                Spacer(Modifier.height(6.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(6.dp), color = Amber.copy(alpha = 0.1f)) {
                    Text(
                        "This ride opens for all drivers in $mm:$ss" + if (isSubscribed) " (subscriber priority)" else " \u2014 subscribe to see it 1 min sooner",
                        color = Amber, fontSize = 10.sp, fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(8.dp), lineHeight = 14.sp
                    )
                }
                Spacer(Modifier.height(6.dp))
            }
            Spacer(Modifier.height(6.dp))
            Text(ride.customerName.ifEmpty { "Passenger" }, color = White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text("${ride.pickupLocation} \u2192 ${ride.dropLocation}", color = Gray400, fontSize = 12.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(displayCabType(ride.cabType), color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.End) {
                    if (ride.userOfferedFare > 0) {
                        Text("Passenger offered: \u20B9${ride.userOfferedFare.toInt()}", color = Cyan, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        if (ride.baseFare > 0 && ride.baseFare != ride.userOfferedFare) Text("Route price: \u20B9${ride.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    } else if (ride.baseFare > 0 && ride.baseFare != ride.totalFare) {
                        Text("Route price: \u20B9${ride.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    }
                    Text(fmt(fare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    if (!isSubscribed) {
                        Text("You get \u20B9${(fare - commissionPerRide).toInt()} after \u20B9${commissionPerRide.toInt()} commission", color = Gold.copy(alpha = 0.6f), fontSize = 9.sp)
                    } else {
                        Text("Full fare \u2014 no commission", color = Emerald, fontSize = 9.sp)
                    }
                }
            }
            if (ride.specialNotes.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(ride.specialNotes, color = Gray500, fontSize = 11.sp, maxLines = 2)
            }
            if (ride.myOfferAmount > 0) {
                Spacer(Modifier.height(4.dp))
                Text("Your offer: \u20B9${ride.myOfferAmount.toInt()} (waiting for passenger)", color = Emerald, fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.height(10.dp))

            // Offering window — available on every open PENDING ride (counter-offer).
            // Hidden once the max (5) offers are reached, or when this driver already offered.
            if (ride.offerClosed == 1 && ride.myOfferAmount <= 0) {
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.12f)) {
                    Text(
                        "${ride.offerCount} of ${ride.maxOffers} offers reached \u2014 offering window closed. You can still ACCEPT at the listed fare.",
                        color = Gold, fontSize = 11.sp, fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(10.dp), lineHeight = 15.sp
                    )
                }
                Spacer(Modifier.height(8.dp))
            } else if (ride.canOffer == 1 && ride.myOfferAmount <= 0) {
                if (!showOfferInput) {
                    OutlinedButton(
                        onClick = { showOfferInput = true },
                        enabled = !busy && !windowLocked,
                        modifier = Modifier.fillMaxWidth().height(38.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f))
                    ) {
                        Icon(Icons.Default.TrendingUp, null, modifier = Modifier.size(15.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("COUNTER OFFER FARE", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                    Spacer(Modifier.height(6.dp))
                } else {
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = CardBg, border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f))) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Text("COUNTER OFFER \u2014 SEND YOUR OWN FARE", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                            if (!isSubscribed) {
                                Spacer(Modifier.height(4.dp))
                                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(6.dp), color = Red.copy(alpha = 0.12f)) {
                                    Text(
                                        "\u20B9${commissionPerRide.toInt()} commission will be cut from your earnings when you get this ride (unless you're subscribed).",
                                        color = Red, fontSize = 10.sp, fontWeight = FontWeight.SemiBold,
                                        modifier = Modifier.padding(8.dp), lineHeight = 14.sp
                                    )
                                }
                            }
                            Spacer(Modifier.height(6.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                listOf(fare - 100.0, fare, fare + 200.0).forEach { amt ->
                                    val lbl = if (amt == fare) "LISTED" else if (amt < fare) "\u2212\u20B9100" else "+\u20B9200"
                                    OutlinedButton(
                                        onClick = { offerAmount = amt.toInt().coerceAtLeast(1).toString() },
                                        enabled = !windowLocked,
                                        modifier = Modifier.weight(1f).height(34.dp),
                                        shape = RoundedCornerShape(8.dp),
                                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)),
                                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold),
                                        contentPadding = PaddingValues(horizontal = 2.dp)
                                    ) { Text("$lbl \u20B9${amt.toInt()}", fontWeight = FontWeight.Black, fontSize = 9.sp) }
                                }
                            }
                            Spacer(Modifier.height(6.dp))
                            OutlinedTextField(
                                value = offerAmount,
                                onValueChange = { offerAmount = it.filter { c -> c.isDigit() }.take(5) },
                                placeholder = { Text("Or enter amount (\u20B9)", color = Gray600, fontSize = 12.sp) },
                                modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                                singleLine = true,
                                shape = RoundedCornerShape(8.dp),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold)
                            )
                            OutlinedTextField(
                                value = offerNote,
                                onValueChange = { if (it.length <= 200) offerNote = it },
                                placeholder = { Text("Reason (optional) e.g. long distance return", color = Gray600, fontSize = 12.sp) },
                                modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp),
                                singleLine = true,
                                shape = RoundedCornerShape(8.dp),
                                colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold)
                            )
                            Spacer(Modifier.height(8.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                TextButton(onClick = { showOfferInput = false; offerAmount = ""; offerNote = "" }, enabled = !busy) {
                                    Text("CANCEL", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                                Button(
                                    onClick = {
                                        val amt = offerAmount.toDoubleOrNull() ?: 0.0
                                        if (amt > 0) onOffer(amt, offerNote)
                                    },
                                    enabled = !busy && !windowLocked && (offerAmount.toDoubleOrNull() ?: 0.0) > 0,
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                                ) {
                                    if (busy) {
                                        CircularProgressIndicator(modifier = Modifier.size(16.dp), color = DarkBg, strokeWidth = 2.dp)
                                    } else {
                                        Text("SEND OFFER", fontSize = 12.sp, fontWeight = FontWeight.Black)
                                    }
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
            }

            // ACCEPT / DECLINE row
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button(
                    onClick = onDecline,
                    enabled = !busy,
                    modifier = Modifier.weight(1f).height(42.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Red.copy(alpha = 0.12f), contentColor = Red)
                ) {
                    Icon(Icons.Default.Close, null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("DECLINE", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
                Button(
                    onClick = onAccept,
                    enabled = !busy && !windowLocked,
                    modifier = Modifier.weight(1f).height(42.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = White)
                ) {
                    Icon(Icons.Default.Check, null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("ACCEPT", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
            }
            Spacer(Modifier.height(4.dp))
            Text("Decline hides this ride from you only \u2014 other drivers can still accept, or admin can assign it.", color = Gray600, fontSize = 9.sp, lineHeight = 12.sp)
        }
    }
}

private fun formatQuickTimeLabel(ride: QuickRide): String = try {
    val cal = Calendar.getInstance()
    val d = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(ride.pickupDate)
    if (d != null) cal.time = d
    val tp = ride.pickupTime.split(":")
    cal.set(Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    val pod = when (cal.get(Calendar.HOUR_OF_DAY)) {
        in 5..11 -> "Morning"; in 12..15 -> "Afternoon"; in 16..19 -> "Evening"; else -> "Night"
    }
    SimpleDateFormat("EEE, dd MMM \u2022 hh:mm a", Locale.getDefault()).format(cal.time) + " ($pod)"
} catch (_: Exception) { "${ride.pickupDate} ${ride.pickupTime}" }

@Composable
private fun RideRequestCard(
    booking: Booking,
    isSubscribed: Boolean,
    commissionPerRide: Double,
    onAccept: () -> Unit,
    onReject: () -> Unit,
    onClick: () -> Unit,
    responding: Boolean
) {
    val overdue = isOverdue(booking)
    val borderColor = if (overdue) Red.copy(alpha = 0.5f) else Gold.copy(alpha = 0.5f)
    val bgColor = if (overdue) Red.copy(alpha = 0.06f) else Gold.copy(alpha = 0.06f)

    Surface(
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(14.dp),
        color = bgColor,
        border = BorderStroke(2.dp, borderColor)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.15f)) {
                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.NewReleases, null, tint = Gold, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        if (overdue) "OVERDUE \u2014 ${formatPickupLabel(booking)}" else formatPickupLabel(booking),
                        color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(booking.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                Surface(shape = RoundedCornerShape(6.dp), color = Gold.copy(alpha = 0.2f)) {
                    Text("NEW", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp))
                }
            }
            Spacer(Modifier.height(6.dp))
            Text("${booking.customerName} \u2022 ${booking.customerPhone}", color = White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text("${booking.pickupLocation} \u2192 ${booking.dropLocation}", color = Gray400, fontSize = 12.sp)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(displayCabType(booking.cabType), color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.End) {
                    if (booking.userOfferedFare > 0) {
                        Text("Passenger offered: \u20B9${booking.userOfferedFare.toInt()}", color = Cyan, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        if (booking.baseFare > 0 && booking.baseFare != booking.userOfferedFare) Text("Route price: \u20B9${booking.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    } else if (booking.baseFare > 0 && booking.baseFare != booking.totalFare) {
                        Text("Route price: \u20B9${booking.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    }
                    Text(fmt(booking.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    if (!isSubscribed) {
                        Text("You get \u20B9${(booking.totalFare - commissionPerRide).toInt()} after \u20B9${commissionPerRide.toInt()} commission", color = Gold.copy(alpha = 0.6f), fontSize = 9.sp)
                    }
                }
            }
            if (booking.specialNotes.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(booking.specialNotes, color = Gray500, fontSize = 11.sp, maxLines = 2)
            }
            Spacer(Modifier.height(10.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button(
                    onClick = onAccept,
                    enabled = !responding,
                    modifier = Modifier.weight(1f).height(40.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = White)
                ) {
                    if (responding) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), color = White, strokeWidth = 2.dp)
                    } else {
                        Icon(Icons.Default.Check, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("ACCEPT", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
                OutlinedButton(
                    onClick = onReject,
                    enabled = !responding,
                    modifier = Modifier.weight(1f).height(40.dp),
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Red),
                    border = BorderStroke(1.dp, Red.copy(alpha = 0.5f))
                ) {
                    Icon(Icons.Default.Close, null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("REJECT", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun StatBox(label: String, value: String, icon: ImageVector, color: Color, modifier: Modifier) {
    Surface(modifier = modifier, shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        Column(modifier = Modifier.padding(10.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(icon, null, tint = color, modifier = Modifier.size(18.dp))
            Spacer(Modifier.height(4.dp))
            Text(value, color = White, fontSize = 15.sp, fontWeight = FontWeight.Black, maxLines = 1)
            Text(label, color = Gray400, fontSize = 9.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
fun DriverBookingCard(bk: Booking, isSubscribed: Boolean = true, commissionPerRide: Double = 200.0, onClick: () -> Unit) {
    val overdue = isOverdue(bk)
    val borderColor = when {
        bk.status == "ASSIGNED" -> Gold.copy(alpha = 0.4f)
        bk.status.contains("IN_TRANSIT") || bk.status.contains("ON_TRIP") -> Orange.copy(alpha = 0.4f)
        overdue -> Red.copy(alpha = 0.4f)
        else -> CardBorder
    }
    val bgColor = when {
        bk.status == "ASSIGNED" -> Gold.copy(alpha = 0.08f)
        bk.status.contains("IN_TRANSIT") || bk.status.contains("ON_TRIP") -> Orange.copy(alpha = 0.08f)
        else -> CardBg
    }

    Surface(
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(14.dp),
        color = bgColor,
        border = BorderStroke(1.dp, borderColor)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            val timeColor = when {
                bk.status == "ASSIGNED" -> Gold
                bk.status.contains("IN_TRANSIT") || bk.status.contains("ON_TRIP") -> Orange
                overdue -> Red
                else -> Gray400
            }
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = timeColor.copy(alpha = 0.12f)) {
                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Schedule, null, tint = timeColor, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        if (overdue) "OVERDUE \u2022 ${formatPickupLabel(bk)}" else formatPickupLabel(bk),
                        color = timeColor, fontSize = 11.sp, fontWeight = FontWeight.Black
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                StatusBadge(bk.status)
            }
            Spacer(Modifier.height(6.dp))
            Text("${bk.customerName} \u2022 ${bk.customerPhone}", color = White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text("${bk.pickupLocation} \u2192 ${bk.dropLocation}", color = Gray400, fontSize = 12.sp)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(displayCabType(bk.cabType), color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Column(horizontalAlignment = Alignment.End) {
                    if (bk.userOfferedFare > 0) {
                        Text("Passenger offered: \u20B9${bk.userOfferedFare.toInt()}", color = Cyan, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                        if (bk.baseFare > 0 && bk.baseFare != bk.userOfferedFare) Text("Route price: \u20B9${bk.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    } else if (bk.baseFare > 0 && bk.baseFare != bk.totalFare) {
                        Text("Route price: \u20B9${bk.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                    }
                    Text(fmt(bk.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    if (!isSubscribed && bk.status == "ASSIGNED") {
                        Text("You get \u20B9${(bk.totalFare - commissionPerRide).toInt()} after \u20B9${commissionPerRide.toInt()} commission", color = Gold.copy(alpha = 0.6f), fontSize = 9.sp)
                    }
                }
            }
            if (bk.specialNotes.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(bk.specialNotes, color = Gray500, fontSize = 11.sp, maxLines = 2)
            }
            if (bk.userRating > 0) {
                Spacer(Modifier.height(6.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("${bk.userRating}/5", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    if (bk.userReview.isNotBlank()) {
                        Spacer(Modifier.width(8.dp))
                        Text("\"${bk.userReview}\"", color = Gray400, fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
        }
    }
}
