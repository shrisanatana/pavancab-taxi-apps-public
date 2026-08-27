package com.pavancab.dispatch.ui.dashboard

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.net.Uri
import androidx.core.content.ContextCompat
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.model.Booking
import com.pavancab.dispatch.network.ApiClient
import com.pavancab.dispatch.network.ApiException
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private fun JsonObject?.safeInt(key: String): Int? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asInt
}

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

// Parse pickupDate (yyyy-MM-dd) + pickupTime (HH:mm) into epoch millis for sorting/urgency
private fun pickupMillis(bk: Booking): Long = try {
    val cal = java.util.Calendar.getInstance()
    val d = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(bk.pickupDate)
    if (d != null) cal.time = d
    val tp = bk.pickupTime.split(":")
    cal.set(java.util.Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(java.util.Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    cal.set(java.util.Calendar.SECOND, 0)
    cal.timeInMillis
} catch (_: Exception) { Long.MAX_VALUE }

// Indian-format label with part of day, e.g. "Wed, 26 Aug • 2:44 PM (Afternoon)"
private fun formatPickupLabel(bk: Booking): String = try {
    val cal = java.util.Calendar.getInstance()
    val d = java.text.SimpleDateFormat("yyyy-MM-dd", java.util.Locale.US).parse(bk.pickupDate)
    if (d != null) cal.time = d
    val tp = bk.pickupTime.split(":")
    cal.set(java.util.Calendar.HOUR_OF_DAY, tp.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0)
    cal.set(java.util.Calendar.MINUTE, tp.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0)
    val pod = when (cal.get(java.util.Calendar.HOUR_OF_DAY)) {
        in 5..11 -> "Morning"
        in 12..15 -> "Afternoon"
        in 16..19 -> "Evening"
        else -> "Night"
    }
    java.text.SimpleDateFormat("EEE, dd MMM \u2022 hh:mm a", java.util.Locale.getDefault()).format(cal.time) + " ($pod)"
} catch (_: Exception) { "${bk.pickupDate} ${bk.pickupTime}" }

private fun isOverdue(bk: Booking): Boolean = pickupMillis(bk) != Long.MAX_VALUE && pickupMillis(bk) < System.currentTimeMillis()

private fun JsonObject?.safeDouble(key: String): Double? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asDouble
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(onBookingClick: (Int) -> Unit, onPhoneBooking: () -> Unit, onWhatsAppConfig: () -> Unit, onCommission: () -> Unit, onLogout: () -> Unit, onReports: () -> Unit = {}, onUsers: () -> Unit = {}, onDriverConfig: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var userName by remember { mutableStateOf("Admin") }
    var role by remember { mutableStateOf("") }
    var pendingBookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var allBookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var todayCount by remember { mutableIntStateOf(0) }
    var activeCount by remember { mutableIntStateOf(0) }
    var pendingCount by remember { mutableIntStateOf(0) }
    var totalRevenue by remember { mutableDoubleStateOf(0.0) }
    var assignedCount by remember { mutableIntStateOf(0) }
    var inTransitCount by remember { mutableIntStateOf(0) }
    var completedCount by remember { mutableIntStateOf(0) }
    var cancelledCount by remember { mutableIntStateOf(0) }
    var availableDrivers by remember { mutableIntStateOf(0) }
    var totalDrivers by remember { mutableIntStateOf(0) }
    var loading by remember { mutableStateOf(true) }
    var errorMsg by remember { mutableStateOf("") }
    var sessionExpired by remember { mutableStateOf(false) }
    var selectedTab by remember { mutableIntStateOf(0) }
    var lastBookingCount by remember { mutableIntStateOf(0) }

    suspend fun refresh() {
        try {
            errorMsg = ""
            val stats = repo.getStats()
            if (stats.has("error") && !stats.get("error").isJsonNull) {
                errorMsg = stats.get("error")?.asString ?: "Server error"
                loading = false
                return
            }
            pendingCount = stats.safeInt("pending") ?: 0
            assignedCount = stats.safeInt("assigned") ?: 0
            inTransitCount = stats.safeInt("inTransit") ?: 0
            activeCount = stats.safeInt("active") ?: 0
            completedCount = stats.safeInt("completed") ?: 0
            cancelledCount = stats.safeInt("cancelledTotal") ?: 0
            todayCount = stats.safeInt("total") ?: 0
            totalRevenue = stats.safeDouble("totalRevenue") ?: 0.0
            availableDrivers = stats.safeInt("availableDrivers") ?: 0
            totalDrivers = stats.safeInt("totalDrivers") ?: 0
            pendingBookings = repo.getAllBookings(status = "PENDING")
            allBookings = repo.getAllBookings().take(20)

            if (lastBookingCount > 0 && pendingBookings.size > lastBookingCount) {
                playNewBookingTone(context)
            }
            lastBookingCount = pendingBookings.size
        } catch (e: com.pavancab.dispatch.network.ApiException) {
            sessionExpired = true
            errorMsg = "Session expired. Please re-login."
        } catch (_: Exception) {
            errorMsg = "Failed to load data. Pull down to retry."
        }
        loading = false
    }

    val refreshReceiver = remember {
        object : BroadcastReceiver() {
            override fun onReceive(ctx: Context, intent: Intent?) {
                scope.launch { delay(500); refresh() }
            }
        }
    }

    LaunchedEffect(Unit) {
        userName = UserPrefs.getName(context).ifBlank { "Admin" }
        role = UserPrefs.getRole(context)
        refresh()
        while (true) { delay(10000); refresh() }
    }

    DisposableEffect(Unit) {
        val filter = IntentFilter("com.pavancab.dispatch.REFRESH_DATA")
        ContextCompat.registerReceiver(context, refreshReceiver, filter, ContextCompat.RECEIVER_NOT_EXPORTED)
        onDispose { try { context.unregisterReceiver(refreshReceiver) } catch (_: Exception) {} }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("DISPATCH", color = Gold, fontWeight = FontWeight.Black, letterSpacing = 2.sp, fontSize = 20.sp) },
                actions = {
                    IconButton(onClick = { scope.launch { refresh() } }) { Icon(Icons.Default.Refresh, "Refresh", tint = White) }
                    IconButton(onClick = {
                        scope.launch { repo.logout(); UserPrefs.clear(context); ApiClient.cookieJar.clear() }
                        onLogout()
                    }) { Icon(Icons.Default.Logout, "Logout", tint = White) }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        LazyColumn(
            modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
            contentPadding = PaddingValues(vertical = 12.dp)
        ) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Hi, $userName!", color = White, fontSize = 20.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                    Surface(shape = RoundedCornerShape(6.dp), color = if (role == "admin") Gold.copy(alpha = 0.15f) else Blue.copy(alpha = 0.15f)) {
                        Text(role.uppercase(), color = if (role == "admin") Gold else Blue, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp))
                    }
                }
            }
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        StatCard("Pending", "$pendingCount", Icons.Default.Schedule, Modifier.weight(1f), Gold, compact = true)
                        StatCard("Assigned", "$assignedCount", Icons.Default.Assignment, Modifier.weight(1f), Blue, compact = true)
                        StatCard("Active", "$activeCount", Icons.Default.LocalTaxi, Modifier.weight(1f), Emerald, compact = true)
                    }
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        StatCard("Completed", "$completedCount", Icons.Default.CheckCircle, Modifier.weight(1f), Emerald, compact = true)
                        StatCard("Cancelled", "$cancelledCount", Icons.Default.Cancel, Modifier.weight(1f), Red, compact = true)
                        StatCard("Total", "$todayCount", Icons.Default.BarChart, Modifier.weight(1f), Gray400, compact = true)
                    }
                }
            }
            item {
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                    Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.DirectionsCar, null, tint = Emerald, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Drivers: ", color = Gray400, fontSize = 12.sp)
                        Text("$availableDrivers/$totalDrivers Available", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                    }
                }
            }
            item {
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                    Row(modifier = Modifier.padding(14.dp), horizontalArrangement = Arrangement.SpaceBetween) {
                        Column { Text("Total Bookings", color = Gray400, fontSize = 11.sp); Text("$todayCount", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold) }
                    }
                }
            }
            item {
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable {
                    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/918180951176"))) }
                }, shape = RoundedCornerShape(12.dp), color = Color(0xFF25D366).copy(alpha = 0.12f), border = BorderStroke(1.dp, Color(0xFF25D366).copy(alpha = 0.4f))) {
                    Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Chat, null, tint = Color(0xFF25D366), modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("WhatsApp Support", color = White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
            item {
                if (role == "admin") {
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onWhatsAppConfig() }, shape = RoundedCornerShape(12.dp), color = Color(0xFF25D366).copy(alpha = 0.08f), border = BorderStroke(1.dp, Color(0xFF25D366).copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Settings, null, tint = Color(0xFF25D366), modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("WhatsApp API Config", color = White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
            item {
                if (role == "admin") {
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onDriverConfig() }, shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.10f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Payment, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Driver Payment Config", color = White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.weight(1f))
                            Icon(Icons.Default.ChevronRight, null, tint = Gold, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
            item {
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onPhoneBooking() }, shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.10f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                    Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Phone, null, tint = Gold, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("Phone Booking", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.weight(1f))
                    }
                }
            }
            if (role == "admin") {
                item {
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onCommission() }, shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.10f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.AccountBalance, null, tint = Emerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Commission Report", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.weight(1f))
                            Icon(Icons.Default.ChevronRight, null, tint = Emerald, modifier = Modifier.size(18.dp))
                        }
                    }
                }
                item {
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onReports() }, shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.10f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.BugReport, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Ride Reports", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.weight(1f))
                            Icon(Icons.Default.ChevronRight, null, tint = Gold, modifier = Modifier.size(18.dp))
                        }
                    }
                }
            }
            if (errorMsg.isNotEmpty()) {
                item {
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = if (sessionExpired) Red.copy(alpha = 0.12f) else Gold.copy(alpha = 0.12f), border = BorderStroke(1.dp, if (sessionExpired) Red.copy(alpha = 0.3f) else Gold.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(if (sessionExpired) Icons.Default.Warning else Icons.Default.ErrorOutline, null, tint = if (sessionExpired) Red else Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(errorMsg, color = White, fontSize = 12.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                            if (sessionExpired) {
                                Text("LOGIN", color = Red, fontSize = 11.sp, fontWeight = FontWeight.Black, modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable { onLogout() }.padding(4.dp))
                            }
                        }
                    }
                }
            }
            item {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf("Pending" to pendingBookings, "All" to allBookings).forEachIndexed { index, (label, _) ->
                        val sel = selectedTab == index
                        Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedTab = index }, shape = RoundedCornerShape(8.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                            val count = if (index == 0) pendingBookings.size else allBookings.size
                            Text("$label ($count)", color = if (sel) DarkBg else Gray400, fontSize = 12.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp))
                        }
                    }
                }
            }
            // Nearest pickup first — overdue rides naturally rise to the top
            val sortedPending = pendingBookings.sortedBy { pickupMillis(it) }
            val sortedAll = allBookings.sortedBy { pickupMillis(it) }
            val displayedBookings = if (selectedTab == 0) sortedPending else sortedAll
            if (displayedBookings.isEmpty() && !loading) {
                item { EmptyState(Icons.Default.Inbox, if (selectedTab == 0) "No pending rides" else "No bookings yet", if (selectedTab == 0) "All new bookings appear here" else "") }
            }
            items(displayedBookings) { bk ->
                BookingCard(
                    bk = bk,
                    onClick = { onBookingClick(bk.id) },
                    onToggleFreeze = {
                        scope.launch {
                            val r = if (bk.isFrozen == 1) repo.unfreezeRide(bk.id) else repo.freezeRide(bk.id)
                            val msg = if (r.safeBool("success") == true)
                                (if (bk.isFrozen == 1) "Ride unfrozen - released to drivers." else "Ride frozen - hidden from drivers.")
                            else r.safeString("error") ?: "Failed"
                            android.widget.Toast.makeText(context, msg, android.widget.Toast.LENGTH_SHORT).show()
                            refresh()
                        }
                    }
                )
            }
        }
        if (loading) LoadingOverlay("Loading dashboard...")
    }
}

private fun playNewBookingTone(context: Context) {
    try {
        val sp = context.getSharedPreferences("dispatch_session", Context.MODE_PRIVATE)
        val savedUri = sp.getString("ringtone_uri", "") ?: ""
        val repeatCount = sp.getInt("ringtone_repeat", 3)
        val uri = if (savedUri.isNotBlank() && savedUri != Uri.EMPTY.toString()) {
            Uri.parse(savedUri)
        } else {
            RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        }

        val handler = android.os.Handler(android.os.Looper.getMainLooper())
        var played = 0

        fun playOnce() {
            try {
                val mp = MediaPlayer()
                mp.setDataSource(context, uri)
                mp.setAudioAttributes(AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build())
                mp.setOnCompletionListener { mp2 ->
                    mp2.release()
                    played++
                    if (played < repeatCount) handler.postDelayed({ playOnce() }, 300)
                }
                mp.setOnErrorListener { mp2, _, _ ->
                    mp2.release()
                    played++
                    if (played < repeatCount) handler.postDelayed({ playOnce() }, 300)
                    true
                }
                mp.prepare()
                mp.start()
            } catch (_: Exception) {}
        }

        playOnce()
    } catch (_: Exception) {}
}

@Composable
private fun StatCard(label: String, value: String, icon: androidx.compose.ui.graphics.vector.ImageVector, modifier: Modifier, accentColor: Color = Gold, compact: Boolean = false) {
    Surface(modifier = modifier, shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        if (compact) {
            Column(modifier = Modifier.padding(horizontal = 6.dp, vertical = 12.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Icon(icon, null, tint = accentColor, modifier = Modifier.size(20.dp))
                Spacer(Modifier.height(6.dp))
                Text(value, color = White, fontSize = 17.sp, fontWeight = FontWeight.Black, maxLines = 1)
                Text(label, color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Medium)
            }
        } else {
            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                Surface(modifier = Modifier.size(40.dp), shape = RoundedCornerShape(10.dp), color = accentColor.copy(alpha = 0.12f)) {
                    Box(contentAlignment = Alignment.Center) { Icon(icon, null, tint = accentColor, modifier = Modifier.size(20.dp)) }
                }
                Spacer(Modifier.width(12.dp))
                Column {
                    Text(value, color = White, fontSize = 22.sp, fontWeight = FontWeight.Black)
                    Text(label, color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                }
            }
        }
    }
}

@Composable
private fun BookingCard(bk: Booking, onClick: () -> Unit, onToggleFreeze: (() -> Unit)? = null) {
    val isPending = bk.status == "PENDING"
    val urgentColor = if (isPending) Gold.copy(alpha = 0.08f) else CardBg
    val borderColor = if (isPending) Gold.copy(alpha = 0.3f) else CardBorder
    val overdue = isOverdue(bk)
    val timeAccent = if (overdue) Red else Gold

    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onClick), shape = RoundedCornerShape(14.dp), color = urgentColor, border = BorderStroke(1.dp, borderColor)) {
        Column(modifier = Modifier.padding(14.dp)) {
            // Prominent pickup date/time banner — nearest needed rides visible at a glance
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = timeAccent.copy(alpha = 0.12f)) {
                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Schedule, null, tint = timeAccent, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(6.dp))
                    Text(
                        if (overdue) "OVERDUE \u2022 ${formatPickupLabel(bk)}" else formatPickupLabel(bk),
                        color = timeAccent, fontSize = 11.sp, fontWeight = FontWeight.Black
                    )
                }
            }
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                if (bk.isFrozen == 1) {
                    Surface(shape = RoundedCornerShape(6.dp), color = Purple.copy(alpha = 0.15f), border = BorderStroke(1.dp, Purple.copy(alpha = 0.4f))) {
                        Text("FROZEN", color = Purple, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                    }
                    Spacer(Modifier.width(6.dp))
                }
                StatusBadge(bk.status)
                if (onToggleFreeze != null && isPending) {
                    Spacer(Modifier.width(6.dp))
                    Surface(
                        shape = RoundedCornerShape(6.dp),
                        color = if (bk.isFrozen == 1) Purple.copy(alpha = 0.18f) else CardBg,
                        border = BorderStroke(1.dp, if (bk.isFrozen == 1) Purple.copy(alpha = 0.4f) else CardBorder),
                        modifier = Modifier.clickable(onClick = onToggleFreeze)
                    ) {
                        Icon(if (bk.isFrozen == 1) Icons.Default.Lock else Icons.Default.LockOpen, null, tint = if (bk.isFrozen == 1) Purple else Gray400, modifier = Modifier.padding(6.dp).size(16.dp))
                    }
                }
            }
            Spacer(Modifier.height(6.dp))
            Text("${bk.customerName} \u2022 ${bk.customerPhone}", color = White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            Spacer(Modifier.height(2.dp))
            Text("${bk.pickupLocation} \u2192 ${bk.dropLocation}", color = Gray400, fontSize = 12.sp)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.cabType, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Text(fmt(bk.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
            if (bk.driverName.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text("Driver: ${bk.driverName}", color = Emerald, fontSize = 11.sp)
            }
        }
    }
}
