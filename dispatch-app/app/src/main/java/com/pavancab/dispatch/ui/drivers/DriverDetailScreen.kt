package com.pavancab.dispatch.ui.drivers

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.*
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.model.Booking
import com.pavancab.dispatch.model.Driver
import com.pavancab.dispatch.model.DriverDetail
import com.pavancab.dispatch.model.DriverStats
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Locale

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private val driverGson: Gson = GsonBuilder()
    .registerTypeAdapter(String::class.java, object : TypeAdapter<String>() {
        override fun read(`in`: JsonReader): String {
            return if (`in`.peek() == JsonToken.NULL) { `in`.nextNull(); "" } else `in`.nextString()
        }
        override fun write(out: JsonWriter, value: String?) { out.value(value ?: "") }
    })
    .create()

private fun driverStatusColor(status: String): Color = when (status.lowercase()) {
    "available" -> Emerald
    "on_trip" -> Orange
    else -> Gray500
}

@Composable
private fun TagBadge(text: String, color: Color) {
    Surface(shape = RoundedCornerShape(6.dp), color = color.copy(alpha = 0.15f)) {
        Text(text, color = color, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
    }
}

@Composable
private fun ActionButton(label: String, icon: ImageVector, color: Color, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Surface(
        modifier = modifier.clip(RoundedCornerShape(10.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(10.dp),
        color = color.copy(alpha = 0.1f),
        border = BorderStroke(1.dp, color.copy(alpha = 0.35f))
    ) {
        Row(modifier = Modifier.padding(vertical = 10.dp), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = color, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(label, color = color, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun StatCell(label: String, value: String, color: Color, modifier: Modifier = Modifier) {
    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = color, fontSize = 14.sp, fontWeight = FontWeight.Black, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Gray400, fontSize = 10.sp)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DriverDetailScreen(
    driverId: Int,
    onBack: () -> Unit,
    onBookingClick: (Int) -> Unit = {},
    onUserClick: (String, String, Int) -> Unit = { _, _, _ -> },
    onSubscription: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var detail by remember { mutableStateOf<DriverDetail?>(null) }
    var subscription by remember { mutableStateOf<JsonObject?>(null) }
    var hasActiveSub by remember { mutableStateOf(false) }
    var pendingPayCount by remember { mutableIntStateOf(0) }
    var loading by remember { mutableStateOf(true) }
    var loadError by remember { mutableStateOf("") }
    var saving by remember { mutableStateOf(false) }
    var markingPaid by remember { mutableIntStateOf(0) }
    var confirmPaidFor by remember { mutableStateOf<Booking?>(null) }

    var showEditDialog by remember { mutableStateOf(false) }
    var eName by remember { mutableStateOf("") }
    var ePhone by remember { mutableStateOf("") }
    var eCar by remember { mutableStateOf("") }
    var ePlate by remember { mutableStateOf("") }

    suspend fun load() {
        loading = true
        loadError = ""
        val obj = repo.getDriverDetail(driverId)
        detail = try {
            if (!obj.entrySet().iterator().hasNext()) null
            else DriverDetail(
                driver = if (obj.has("driver") && obj.get("driver").isJsonObject) driverGson.fromJson(obj.getAsJsonObject("driver"), Driver::class.java) else Driver(),
                // Backend now returns 'bookings' (full history w/ commission badges); legacy 'recent_rides' kept as fallback
                bookings = when {
                    obj.has("bookings") && obj.get("bookings").isJsonArray -> obj.getAsJsonArray("bookings").map { driverGson.fromJson(it, Booking::class.java) }
                    obj.has("recent_rides") && obj.get("recent_rides").isJsonArray -> obj.getAsJsonArray("recent_rides").map { driverGson.fromJson(it, Booking::class.java) }
                    else -> emptyList()
                },
                stats = if (obj.has("stats") && obj.get("stats").isJsonObject) driverGson.fromJson(obj.getAsJsonObject("stats"), DriverStats::class.java) else DriverStats()
            )
        } catch (_: Exception) { null }
        subscription = if (obj.has("subscription") && obj.get("subscription").isJsonObject) obj.getAsJsonObject("subscription") else null
        hasActiveSub = try { obj.get("has_active_subscription")?.asBoolean == true } catch (_: Exception) { false }
        pendingPayCount = try { obj.get("pending_payments_count")?.asInt ?: 0 } catch (_: Exception) { 0 }
        if (detail == null) loadError = Repository.lastError ?: ""
        loading = false
    }

    LaunchedEffect(driverId) { load() }

    fun toast(msg: String) = Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Driver Details", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when {
                loading -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
                detail == null -> Column(
                    modifier = Modifier.fillMaxSize().padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Icon(Icons.Default.PersonOff, null, tint = Gray600, modifier = Modifier.size(56.dp))
                    Spacer(Modifier.height(12.dp))
                    Text(loadError.ifBlank { "Couldn't load this driver." }, color = Gray400, fontSize = 13.sp, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
                    Spacer(Modifier.height(12.dp))
                    Button(onClick = { scope.launch { load() } }, colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)) {
                        Text("RETRY", fontWeight = FontWeight.Black)
                    }
                }
                else -> {
                    val d = detail!!.driver
                    val stColor = driverStatusColor(d.status)
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp)
                    ) {
                        item {
                            PavanCard {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Box(
                                        modifier = Modifier.size(44.dp).clip(RoundedCornerShape(12.dp)).background(Gold.copy(alpha = 0.12f)),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(Icons.Default.Person, null, tint = Gold, modifier = Modifier.size(26.dp))
                                    }
                                    Spacer(Modifier.width(10.dp))
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(d.name.ifBlank { "Unknown Driver" }, color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                        Text("${d.carModel} \u2022 ${d.plateNumber}", color = Gray400, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                        Spacer(Modifier.height(2.dp))
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(13.dp))
                                            Spacer(Modifier.width(2.dp))
                                            Text(String.format(Locale.US, "%.1f", d.rating), color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                            Text(" (${d.totalRatings})", color = Gray500, fontSize = 10.sp)
                                        }
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        TagBadge(d.status.uppercase().replace('_', ' '), stColor)
                                        if (d.isOnline == 1) {
                                            Spacer(Modifier.height(4.dp))
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                Box(modifier = Modifier.size(7.dp).clip(CircleShape).background(Emerald))
                                                Spacer(Modifier.width(4.dp))
                                                Text("ONLINE", color = Emerald, fontSize = 9.sp, fontWeight = FontWeight.Black)
                                            }
                                        }
                                    }
                                }
                                Spacer(Modifier.height(10.dp))
                                InfoRow("Phone", d.phone.ifBlank { "\u2014" }, Icons.Default.Phone)
                                if (d.isApproved == 0) {
                                    Spacer(Modifier.height(6.dp))
                                    Surface(shape = RoundedCornerShape(8.dp), color = Red.copy(alpha = 0.1f), border = BorderStroke(1.dp, Red.copy(alpha = 0.35f))) {
                                        Text("NOT APPROVED YET", color = Red, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp))
                                    }
                                }
                            }
                        }

                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                ActionButton("Call", Icons.Default.Phone, Emerald, Modifier.weight(1f)) {
                                    context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${d.phone}")))
                                }
                                ActionButton("WhatsApp", Icons.Default.Chat, Color(0xFF25D366), Modifier.weight(1f)) {
                                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${d.phone.replace("+", "").replace(" ", "")}")))
                                }
                                ActionButton("Edit", Icons.Default.Edit, Gold, Modifier.weight(1f)) {
                                    eName = d.name; ePhone = d.phone; eCar = d.carModel; ePlate = d.plateNumber
                                    showEditDialog = true
                                }
                            }
                        }

                        // Approve / reject driver
                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                if (d.isApproved == 0) {
                                    Button(
                                        onClick = {
                                            scope.launch {
                                                saving = true
                                                val r = repo.approveDriver(driverId, true)
                                                saving = false
                                                toast(if (r.safeBool("success") == true) "Driver approved!" else r.safeString("error") ?: "Failed")
                                                load()
                                            }
                                        },
                                        enabled = !saving,
                                        modifier = Modifier.weight(1f).height(44.dp),
                                        shape = RoundedCornerShape(10.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)
                                    ) {
                                        Icon(Icons.Default.Verified, null, modifier = Modifier.size(16.dp))
                                        Spacer(Modifier.width(6.dp))
                                        Text("APPROVE DRIVER", fontSize = 12.sp, fontWeight = FontWeight.Black)
                                    }
                                } else {
                                    ActionButton("REVOKE APPROVAL", Icons.Default.Block, Red, Modifier.weight(1f)) {
                                        scope.launch {
                                            saving = true
                                            val r = repo.approveDriver(driverId, false)
                                            saving = false
                                            toast(if (r.safeBool("success") == true) "Approval revoked" else r.safeString("error") ?: "Failed")
                                            load()
                                        }
                                    }
                                }
                            }
                        }

                        // Subscription card
                        item {
                            PavanCard {
                                SectionHeader("Subscription & Commission")
                                Spacer(Modifier.height(10.dp))
                                if (hasActiveSub && subscription != null) {
                                    val end = try { subscription?.get("end_date")?.asString ?: "" } catch (_: Exception) { "" }
                                    Surface(shape = RoundedCornerShape(8.dp), color = Emerald.copy(alpha = 0.1f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                                        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.CardMembership, null, tint = Emerald, modifier = Modifier.size(16.dp))
                                            Spacer(Modifier.width(8.dp))
                                            Text("ACTIVE SUBSCRIBER \u2014 no commission until $end", color = Emerald, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                        }
                                    }
                                } else {
                                    Surface(shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.08f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                                        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                            Icon(Icons.Default.Info, null, tint = Gold, modifier = Modifier.size(16.dp))
                                            Spacer(Modifier.width(8.dp))
                                            Text("No active subscription \u2014 pays \u20B9200 per ride", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                        }
                                    }
                                }
                                if (pendingPayCount > 0) {
                                    Spacer(Modifier.height(6.dp))
                                    Text("$pendingPayCount pending commission payment(s)", color = Red, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }

                        item {
                            PavanCard {
                                SectionHeader("Statistics")
                                Spacer(Modifier.height(10.dp))
                                Row(modifier = Modifier.fillMaxWidth()) {
                                    StatCell("Total Rides", "${detail!!.stats.totalRides}", White, Modifier.weight(1f))
                                    StatCell("Completed", "${detail!!.stats.completed}", Emerald, Modifier.weight(1f))
                                    StatCell("Cancelled", "${detail!!.stats.cancelled}", Red, Modifier.weight(1f))
                                    StatCell("Total Earnings", fmt(detail!!.stats.totalEarnings), Gold, Modifier.weight(1f))
                                }
                                if (detail!!.stats.commissionDueCount > 0 || detail!!.stats.commissionPaidCount > 0) {
                                    Spacer(Modifier.height(8.dp))
                                    Row(modifier = Modifier.fillMaxWidth()) {
                                        StatCell("Comm. Paid", "${detail!!.stats.commissionPaidCount}", Emerald, Modifier.weight(1f))
                                        StatCell("Comm. Due", "${detail!!.stats.commissionDueCount}", Red, Modifier.weight(1f))
                                        Spacer(Modifier.weight(2f))
                                    }
                                }
                            }
                        }

                        item {
                            SectionHeader("Ride History (${detail!!.bookings.size}) \u2022 tap a ride for details")
                        }

                        if (detail!!.bookings.isEmpty()) {
                            item {
                                PavanCard {
                                    Text("No rides assigned yet", color = Gray500, fontSize = 12.sp)
                                }
                            }
                        } else {
                            items(detail!!.bookings, key = { it.id }) { bk ->
                                HistoryCard(
                                    bk = bk,
                                    busy = markingPaid == bk.id,
                                    onMarkPaid = { confirmPaidFor = bk },
                                    onClick = { onBookingClick(bk.id) },
                                    onCustomerClick = { onUserClick(bk.customerPhone, bk.userEmail, 0) }
                                )
                            }
                        }
                    }
                }
            }
            if (saving || markingPaid > 0) LoadingOverlay(if (saving) "Saving changes..." else "Marking paid...")
        }
    }

    // Mark-paid confirmation
    confirmPaidFor?.let { bk ->
        AlertDialog(
            onDismissRequest = { confirmPaidFor = null },
            containerColor = DarkBgLighter,
            title = { Text("Mark commission paid?", color = White, fontWeight = FontWeight.Bold) },
            text = { Text("Confirm you collected \u20B9200 cash from ${bk.driverName} for ride ${bk.bookingRef}. The driver will be notified.", color = Gray300, fontSize = 13.sp) },
            confirmButton = {
                Button(onClick = {
                    val target = bk
                    confirmPaidFor = null
                    scope.launch {
                        markingPaid = target.id
                        val r = repo.markCommissionPaid(target.id)
                        markingPaid = 0
                        toast(if (r.safeBool("success") == true) "Commission marked paid!" else r.safeString("error") ?: "Failed")
                        load()
                    }
                }, colors = ButtonDefaults.buttonColors(containerColor = Emerald), shape = RoundedCornerShape(10.dp)) {
                    Text("YES, COLLECTED", color = DarkBg, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = { TextButton(onClick = { confirmPaidFor = null }) { Text("CANCEL", color = Gray400) } }
        )
    }

    if (showEditDialog && detail != null) {
        AlertDialog(
            onDismissRequest = { showEditDialog = false },
            containerColor = DarkBgLighter,
            title = { Text("Edit ${detail!!.driver.name}", color = Gold, fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    PavanTextField(eName, { eName = it }, "Name")
                    PavanTextField(ePhone, { ePhone = it }, "Phone")
                    PavanTextField(eCar, { eCar = it }, "Car Model")
                    PavanTextField(ePlate, { ePlate = it }, "Plate Number")
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    val id = detail!!.driver.id
                    showEditDialog = false
                    scope.launch {
                        saving = true
                        val r = repo.editDriver(id, eName, ePhone, eCar, ePlate)
                        saving = false
                        toast(if (r.safeBool("success") == true) "Driver updated!" else r.safeString("error") ?: "Failed to update")
                        load()
                    }
                }) { Text("Save", color = Gold) }
            },
            dismissButton = { TextButton(onClick = { showEditDialog = false }) { Text("Cancel", color = Gray400) } }
        )
    }
}

@Composable
private fun HistoryCard(
    bk: Booking,
    busy: Boolean,
    onMarkPaid: () -> Unit,
    onClick: () -> Unit,
    onCustomerClick: () -> Unit
) {
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                StatusBadge(bk.status)
            }
            Spacer(Modifier.height(6.dp))
            Text(
                "${bk.pickupLocation} \u2192 ${bk.dropLocation}",
                color = Gray400, fontSize = 12.sp, maxLines = 2, overflow = TextOverflow.Ellipsis,
                modifier = Modifier.clickable(onClick = onClick)
            )
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("${bk.pickupDate} ${bk.pickupTime}", color = Gray500, fontSize = 11.sp, modifier = Modifier.weight(1f))
                // Passenger rating given by this driver
                if (bk.passengerRating > 0) {
                    Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(11.dp))
                    Text("${bk.passengerRating} ", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    Text("(to passenger)", color = Gray600, fontSize = 9.sp)
                }
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.cabType, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Text(fmt(bk.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
            // Customer row — tappable to open profile
            if (bk.customerName.isNotBlank()) {
                Spacer(Modifier.height(6.dp))
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable(onClick = onCustomerClick), color = Gray900) {
                    Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Person, null, tint = Blue, modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(6.dp))
                        Text(bk.customerName, color = White, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text("VIEW PROFILE \u203A", color = Blue, fontSize = 9.sp, fontWeight = FontWeight.Black)
                    }
                }
            }
            // Commission badge / action for completed rides
            when (bk.commissionBadge) {
                "FREE" -> {
                    Spacer(Modifier.height(6.dp))
                    Surface(shape = RoundedCornerShape(6.dp), color = Blue.copy(alpha = 0.12f)) {
                        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.VerifiedUser, null, tint = Blue, modifier = Modifier.size(11.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("ADMIN-ASSIGNED \u2014 NO COMMISSION", color = Blue, fontSize = 9.sp, fontWeight = FontWeight.Black)
                        }
                    }
                }
                "SUBSCRIBED" -> {
                    Spacer(Modifier.height(6.dp))
                    Surface(shape = RoundedCornerShape(6.dp), color = Emerald.copy(alpha = 0.12f)) {
                        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CardMembership, null, tint = Emerald, modifier = Modifier.size(11.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("SUBSCRIBED \u2014 NO COMMISSION", color = Emerald, fontSize = 9.sp, fontWeight = FontWeight.Black)
                        }
                    }
                }
                "PAID" -> {
                    Spacer(Modifier.height(6.dp))
                    Surface(shape = RoundedCornerShape(6.dp), color = Blue.copy(alpha = 0.12f)) {
                        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CheckCircle, null, tint = Blue, modifier = Modifier.size(11.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("COMMISSION PAID \u20B9200", color = Blue, fontSize = 9.sp, fontWeight = FontWeight.Black)
                        }
                    }
                }
                "DUE" -> {
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = onMarkPaid,
                        enabled = !busy,
                        modifier = Modifier.fillMaxWidth().height(36.dp),
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Red.copy(alpha = 0.15f), contentColor = Red)
                    ) {
                        Icon(Icons.Default.CurrencyRupee, null, modifier = Modifier.size(14.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("COMMISSION DUE \u2014 MARK PAID \u20B9200", fontSize = 10.sp, fontWeight = FontWeight.Black)
                    }
                }
            }
        }
    }
}
