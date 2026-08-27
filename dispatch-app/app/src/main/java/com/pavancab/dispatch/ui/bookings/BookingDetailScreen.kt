package com.pavancab.dispatch.ui.bookings

import android.content.Intent
import android.net.Uri
import android.widget.Toast
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
import com.pavancab.dispatch.model.Driver
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import com.pavancab.dispatch.utils.DateUtils
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingDetailScreen(
    bookingId: Int,
    onBack: () -> Unit,
    onEditBooking: (Int) -> Unit = {},
    onDriverClick: (Int) -> Unit = {},
    onUserClick: (String, String, Int) -> Unit = { _, _, _ -> }
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var booking by remember { mutableStateOf<Booking?>(null) }
    var loading by remember { mutableStateOf(true) }
    var actionLoading by remember { mutableStateOf(false) }
    var showDriverSheet by remember { mutableStateOf(false) }
    var drivers by remember { mutableStateOf<List<Driver>>(emptyList()) }
    var showProposeFareDialog by remember { mutableStateOf(false) }
    var showFareDialog by remember { mutableStateOf(false) }
    var showCancelDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf(false) }
    var role by remember { mutableStateOf("") }
    var showStatusDialog by remember { mutableStateOf(false) }
    var proposeFareAmount by remember { mutableStateOf("") }
    var proposeReason by remember { mutableStateOf("Driver asking minimum fare") }
    var newFare by remember { mutableStateOf("") }
    var driverSearchQuery by remember { mutableStateOf("") }
    var showAddDriverDialog by remember { mutableStateOf(false) }
    var addDriverName by remember { mutableStateOf("") }
    var addDriverPhone by remember { mutableStateOf("") }
    var addDriverModel by remember { mutableStateOf("Goa Cab") }
    var addDriverPlate by remember { mutableStateOf("GA-03-T-1234") }

    suspend fun refresh(scheduleAlarm: Boolean = false) {
        val newBooking = repo.getBookingDetail(bookingId)
        if (newBooking != null) {
            booking = newBooking
            if (scheduleAlarm) com.pavancab.dispatch.worker.AlarmScheduler.scheduleForRide(context, newBooking)
        }
        loading = false
    }
    LaunchedEffect(Unit) {
        role = UserPrefs.getRole(context)
        refresh(scheduleAlarm = true)
        while (true) { delay(5000); refresh() }
    }

    fun action(msg: String) { Toast.makeText(context, msg, Toast.LENGTH_SHORT).show() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(booking?.bookingRef ?: "Booking", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = { IconButton(onClick = { loading = true; scope.launch { refresh() } }) { Icon(Icons.Default.Refresh, null, tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        if (loading) {
            Box(modifier = Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
        } else if (booking == null) {
            val err = com.pavancab.dispatch.data.Repository.lastError
            EmptyState(Icons.Default.ErrorOutline, "Booking not found", (err ?: "Tap refresh to retry").take(90))
        } else {
            val bk = booking!!
            val hasProposal = bk.proposedFare > 0 && bk.fareProposalStatus == "PENDING"

            LazyColumn(modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(vertical = 12.dp)) {
                        item { PavanCard { SectionHeader("Customer Details"); Spacer(Modifier.height(4.dp));
                            // Tappable customer row -> user profile
                            Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable { onUserClick(bk.customerPhone, bk.userEmail, 0) }, color = Gray900) {
                                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Person, null, tint = Blue, modifier = Modifier.size(15.dp))
                                    Spacer(Modifier.width(8.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text(bk.customerName.ifBlank { "Passenger" }, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                        Text(bk.customerPhone, color = Gray400, fontSize = 11.sp)
                                    }
                                    Text("VIEW PROFILE \u203A", color = Blue, fontSize = 9.sp, fontWeight = FontWeight.Black)
                                }
                            }
                            InfoRow("Phone", bk.customerPhone, Icons.Default.Phone); Row(modifier = Modifier.padding(start = 122.dp, top = 4.dp)) { SmallActionBtn("Call", Icons.Default.Phone) { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${bk.customerPhone}"))) }; Spacer(Modifier.width(8.dp)); SmallActionBtn("WhatsApp", Icons.Default.Chat) { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${bk.customerPhone.replace("+", "").replace(" ", "")}"))) } }; if (bk.userEmail.isNotBlank()) InfoRow("Email", bk.userEmail, Icons.Default.Email) } }

                item {
                    PavanCard {
                        SectionHeader("Trip Details"); Spacer(Modifier.height(4.dp))
                        InfoRow("Trip Type", bk.tripType.replace("_", " ").uppercase(), Icons.Default.TripOrigin)
                        InfoRow("Pickup", bk.pickupLocation, Icons.Default.MyLocation)
                        InfoRow("Drop", bk.dropLocation, Icons.Default.LocationOn)
                        InfoRow("Date", DateUtils.formatDate(bk.pickupDate), Icons.Default.CalendarToday)
                        InfoRow("Time", DateUtils.formatTime(bk.pickupTime), Icons.Default.Schedule)
                        InfoRow("Cab Type", displayCabType(bk.cabType), Icons.Default.DirectionsCar)
                        HorizontalDivider(modifier = Modifier.padding(vertical = 6.dp), color = DividerColor)
                        // ── FARE BREAKDOWN (matches passenger app view) ──
                        Text("FARE DETAILS", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
                        Spacer(Modifier.height(6.dp))
                        // Route price (what the system calculated)
                        if (bk.baseFare > 0) {
                            Row(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
                                Text("Route price (system)", color = Gray400, fontSize = 12.sp, modifier = Modifier.weight(1f))
                                Text(fmt(bk.baseFare), color = White, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                        // Night surcharge (visible in special_notes)
                        if (bk.specialNotes.contains("[NIGHT]")) {
                            Row(modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
                                Text("Night allowance (10 PM \u2013 6 AM)", color = Color(0xFFF59E0B), fontSize = 12.sp, modifier = Modifier.weight(1f))
                                Text("+\u20B9500", color = Color(0xFFF59E0B), fontSize = 12.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                        // User offered fare (what passenger chose to pay)
                        if (bk.userOfferedFare > 0) {
                            Spacer(Modifier.height(4.dp))
                            Surface(shape = RoundedCornerShape(8.dp), color = BlueAccent.copy(alpha = 0.12f), border = BorderStroke(1.dp, BlueAccent.copy(alpha = 0.3f))) {
                                Column(modifier = Modifier.padding(8.dp)) {
                                    Text("Passenger chose to pay:", color = BlueAccent.copy(alpha = 0.8f), fontSize = 10.sp)
                                    Text("\u20B9${bk.userOfferedFare.toInt()}", color = BlueAccent, fontSize = 18.sp, fontWeight = FontWeight.Black)
                                    if (bk.baseFare > 0 && bk.userOfferedFare > bk.baseFare) {
                                        val diff = bk.userOfferedFare - bk.baseFare
                                        Text("+\u20B9${diff.toInt()} above route price", color = BlueAccent.copy(alpha = 0.7f), fontSize = 10.sp)
                                    } else if (bk.baseFare > 0 && bk.userOfferedFare < bk.baseFare) {
                                        val diff = bk.baseFare - bk.userOfferedFare
                                        Text("\u2093\u20B9${diff.toInt()} below route price", color = Orange.copy(alpha = 0.8f), fontSize = 10.sp)
                                    }
                                }
                            }
                        }
                        // Driver fare proposal (if pending)
                        if (hasProposal) {
                            Spacer(Modifier.height(4.dp))
                            Surface(shape = RoundedCornerShape(8.dp), color = Orange.copy(alpha = 0.12f), border = BorderStroke(1.dp, Orange.copy(alpha = 0.3f))) {
                                Column(modifier = Modifier.padding(8.dp)) {
                                    Text("Driver proposed fare:", color = Orange.copy(alpha = 0.8f), fontSize = 10.sp)
                                    Text("\u20B9${bk.proposedFare.toInt()}", color = Orange, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                                    Text("Waiting for passenger to accept/decline", color = Orange.copy(alpha = 0.6f), fontSize = 10.sp)
                                }
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        HorizontalDivider(color = DividerColor)
                        Spacer(Modifier.height(4.dp))
                        // Final fare (what passenger pays)
                        Row(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text("FINAL FARE", color = if (bk.userOfferedFare > 0) BlueAccent else Gold, fontSize = 12.sp, fontWeight = FontWeight.Black)
                                if (bk.userOfferedFare > 0) {
                                    Text("(passenger's offer)", color = Gray500, fontSize = 9.sp)
                                }
                            }
                            Text(fmt(bk.totalFare), color = if (bk.userOfferedFare > 0) BlueAccent else Gold, fontSize = 26.sp, fontWeight = FontWeight.Black)
                        }
                        if (hasProposal) {
                            Spacer(Modifier.height(4.dp))
                            Surface(shape = RoundedCornerShape(8.dp), color = Orange.copy(alpha = 0.12f), border = BorderStroke(1.dp, Orange.copy(alpha = 0.3f))) {
                                Text("Proposed fare: ${fmt(bk.proposedFare)} - Waiting for customer", color = Orange, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(8.dp))
                            }
                        }
                        if (bk.specialNotes.isNotBlank()) {
                            Spacer(Modifier.height(4.dp))
                            InfoRow("Notes", bk.specialNotes, Icons.Default.Note)
                        }
                        HorizontalDivider(modifier = Modifier.padding(vertical = 6.dp), color = DividerColor)
                        val decisionColor = when (bk.driverDecision.uppercase()) { "ACCEPTED" -> Emerald; "REJECTED" -> Red; else -> Gray500 }
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 4.dp)) {
                            Icon(Icons.Default.FactCheck, contentDescription = null, tint = Gray400, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Driver Decision:", color = Gray400, fontSize = 13.sp, modifier = Modifier.width(110.dp))
                            Surface(shape = RoundedCornerShape(4.dp), color = decisionColor.copy(alpha = 0.15f)) {
                                Text(bk.driverDecision.ifBlank { "NONE" }.uppercase(), color = decisionColor, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                            }
                        }
                            InfoRow("Rating", if (bk.userRating > 0) "\u2605".repeat(bk.userRating) + "\u2606".repeat((5 - bk.userRating).coerceAtLeast(0)) else "Not rated", Icons.Default.Star, valueColor = if (bk.userRating > 0) Gold else White)
                            if (bk.userReview.isNotBlank()) InfoRow("Review", bk.userReview, Icons.Default.RateReview)
                            InfoRow("Driver rated Passenger", if (bk.passengerRating > 0) "\u2605".repeat(bk.passengerRating) + "\u2606".repeat((5 - bk.passengerRating).coerceAtLeast(0)) else "Not rated", Icons.Default.ThumbUp, valueColor = if (bk.passengerRating > 0) Gold else White)
                            if (bk.passengerReview.isNotBlank()) InfoRow("Passenger Review", bk.passengerReview, Icons.Default.RateReview)
                        InfoRow("Reminder", if (bk.reminderSent > 0) "Sent ${bk.reminderSent} time(s)${if (bk.reminderType.isNotBlank()) " (${bk.reminderType})" else ""}" else "Not sent", Icons.Default.NotificationsActive)
                        Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 4.dp)) {
                            Icon(Icons.Default.Source, contentDescription = null, tint = Gray400, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Source:", color = Gray400, fontSize = 13.sp, modifier = Modifier.width(110.dp))
                            Surface(shape = RoundedCornerShape(4.dp), color = Blue.copy(alpha = 0.15f)) {
                                Text((if (bk.bookingSource.isNotBlank()) bk.bookingSource else "app").uppercase(), color = Blue, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                            }
                        }
                        if (bk.createdAt.isNotBlank()) InfoRow("Created", bk.createdAt.take(16), Icons.Default.History)
                    }
                }

                if (bk.driverName.isNotBlank()) item { PavanCard { SectionHeader("Assigned Driver"); Spacer(Modifier.height(4.dp));
                    // Tappable driver row -> driver detail page
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable { if (bk.driverId > 0) onDriverClick(bk.driverId) }, color = Gray900) {
                        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Person, null, tint = Gold, modifier = Modifier.size(15.dp))
                            Spacer(Modifier.width(8.dp))
                            Column(Modifier.weight(1f)) {
                                Text(bk.driverName, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                Text(bk.vehicleNumber, color = Gray400, fontSize = 11.sp)
                            }
                            if (bk.driverId > 0) Text("VIEW PROFILE \u203A", color = Gold, fontSize = 9.sp, fontWeight = FontWeight.Black)
                        }
                    }
                    InfoRow("Phone", bk.driverPhone, Icons.Default.Phone); InfoRow("Vehicle", bk.vehicleNumber, Icons.Default.DirectionsCar); Row(modifier = Modifier.padding(start = 122.dp, top = 4.dp)) { SmallActionBtn("Call", Icons.Default.Phone) { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${bk.driverPhone}"))) }; Spacer(Modifier.width(8.dp)); SmallActionBtn("WhatsApp", Icons.Default.Chat) { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${bk.driverPhone.replace("+", "").replace(" ", "")}"))) } } } }

                item {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                        StatusBadge(bk.status)
                        if (bk.isFrozen == 1) {
                            Spacer(Modifier.width(8.dp))
                            Surface(shape = RoundedCornerShape(999.dp), color = Purple.copy(alpha = 0.2f), border = BorderStroke(1.dp, Purple.copy(alpha = 0.6f))) {
                                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Lock, null, tint = Purple, modifier = Modifier.size(12.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text("FROZEN", color = Purple, fontSize = 10.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }
                    }
                }

                if (role == "admin") item {
                    OutlinedButton(onClick = { onEditBooking(bookingId) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Blue), border = BorderStroke(1.dp, Blue.copy(alpha = 0.5f))) {
                        Icon(Icons.Default.EditNote, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("Edit Booking", fontWeight = FontWeight.Bold)
                    }
                }

                if (bk.status in listOf("PENDING", "CONFIRMED")) item {
                    GradientButton(onClick = { scope.launch { drivers = repo.getDrivers(); showDriverSheet = true } }, text = "ASSIGN DRIVER", icon = Icons.Default.Assignment, enabled = !actionLoading)
                }
                if (bk.status in listOf("ASSIGNED", "ACCEPTED")) item {
                    GradientButton(onClick = { actionLoading = true; scope.launch { val r = repo.updateBookingStatus(bookingId, "IN_TRANSIT"); actionLoading = false; action(if (r.safeBool("success") == true) "Ride started!" else r.safeString("error") ?: "Failed"); refresh() } }, text = "START RIDE", icon = Icons.Default.PlayArrow, enabled = !actionLoading)
                }
                if (bk.status in listOf("IN_TRANSIT", "ON_TRIP")) item {
                    GradientButton(onClick = { actionLoading = true; scope.launch { val r = repo.updateBookingStatus(bookingId, "COMPLETED"); actionLoading = false; action(if (r.safeBool("success") == true) "Ride completed!" else r.safeString("error") ?: "Failed"); refresh() } }, text = "COMPLETE RIDE", icon = Icons.Default.CheckCircle, enabled = !actionLoading)
                }
                if (bk.status !in listOf("COMPLETED", "CANCELLED", "CANCELLED_BY_USER", "CANCELLED_BY_ADMIN")) item {
                    OutlinedButton(onClick = { showCancelDialog = true }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Red), border = BorderStroke(1.dp, Red.copy(alpha = 0.5f))) {
                        Icon(Icons.Default.Cancel, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("Cancel Ride", fontWeight = FontWeight.Bold)
                    }
                }
                if (bk.status !in listOf("COMPLETED", "CANCELLED", "CANCELLED_BY_USER", "CANCELLED_BY_ADMIN")) item {
                    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        OutlinedButton(onClick = { proposeFareAmount = ""; proposeReason = "Driver asking minimum fare"; showProposeFareDialog = true }, modifier = Modifier.weight(1f), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) { Icon(Icons.Default.TrendingUp, null, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(6.dp)); Text("Propose Fare", fontSize = 12.sp, fontWeight = FontWeight.Bold) }
                        OutlinedButton(onClick = { newFare = bk.totalFare.toInt().toString(); showFareDialog = true }, modifier = Modifier.weight(1f), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Blue), border = BorderStroke(1.dp, Blue.copy(alpha = 0.3f))) { Icon(Icons.Default.Edit, null, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(6.dp)); Text("Edit Fare", fontSize = 12.sp, fontWeight = FontWeight.Bold) }
                    }
                }
                item {
                    OutlinedButton(onClick = { showStatusDialog = true }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Orange), border = BorderStroke(1.dp, Orange.copy(alpha = 0.5f))) {
                        Icon(Icons.Default.SwapHoriz, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("Change Status", fontWeight = FontWeight.Bold)
                    }
                }
                if (bk.status !in listOf("COMPLETED", "CANCELLED", "CANCELLED_BY_USER", "CANCELLED_BY_ADMIN")) item {
                    OutlinedButton(onClick = {
                        actionLoading = true
                        scope.launch {
                            val r = if (bk.isFrozen == 1) repo.unfreezeRide(bookingId) else repo.freezeRide(bookingId)
                            actionLoading = false
                            action(if (r.safeBool("success") == true) (if (bk.isFrozen == 1) "Ride unfrozen — released to drivers." else "Ride frozen — hidden from drivers.") else r.safeString("error") ?: "Failed")
                            refresh()
                        }
                    }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Purple), border = BorderStroke(1.dp, Purple.copy(alpha = 0.5f)), enabled = !actionLoading) {
                        Icon(if (bk.isFrozen == 1) Icons.Default.LockOpen else Icons.Default.Lock, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text(if (bk.isFrozen == 1) "Unfreeze Ride (release to drivers)" else "Freeze Ride (hide from drivers)", fontWeight = FontWeight.Bold)
                    }
                }
                if (role == "admin") item {
                    OutlinedButton(onClick = { showDeleteDialog = true }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Red), border = BorderStroke(1.dp, Red.copy(alpha = 0.5f))) {
                        Icon(Icons.Default.DeleteForever, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("Delete Permanently", fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
        // Assign Driver Sheet with Quick Add
        if (showDriverSheet) {
            val filteredDrivers = if (driverSearchQuery.isBlank()) drivers else drivers.filter {
                it.name.contains(driverSearchQuery, ignoreCase = true) ||
                it.phone.contains(driverSearchQuery) ||
                it.plateNumber.contains(driverSearchQuery, ignoreCase = true) ||
                it.carModel.contains(driverSearchQuery, ignoreCase = true)
            }
            ModalBottomSheet(onDismissRequest = { showDriverSheet = false; driverSearchQuery = "" }, containerColor = DarkBgLighter, shape = RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)) {
                Column(modifier = Modifier.padding(horizontal = 16.dp)) {
                    Text("Select Driver", color = White, fontSize = 16.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(bottom = 8.dp))
                    OutlinedTextField(
                        value = driverSearchQuery, onValueChange = { driverSearchQuery = it },
                        placeholder = { Text("Search by name, phone, plate...", color = Gray500, fontSize = 13.sp) },
                        leadingIcon = { Icon(Icons.Default.Search, null, tint = Gray500) },
                        trailingIcon = { if (driverSearchQuery.isNotBlank()) IconButton(onClick = { driverSearchQuery = "" }) { Icon(Icons.Default.Clear, null, tint = Gray500) } },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp), singleLine = true,
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold)
                    )
                    Spacer(Modifier.height(8.dp))
                    // Quick Add New Driver button
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { showAddDriverDialog = true }, shape = RoundedCornerShape(10.dp), color = Gold.copy(alpha = 0.10f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Add, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Add New Driver", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Text("${filteredDrivers.size} drivers found", color = Gray500, fontSize = 11.sp)
                    Spacer(Modifier.height(8.dp))
                    if (filteredDrivers.isEmpty()) { Text("No drivers found. Tap 'Add New Driver' above.", color = Gray400, fontSize = 13.sp, modifier = Modifier.padding(16.dp)) }
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        items(filteredDrivers) { d ->
                            val statusColor = when (d.status) { "available" -> Emerald; "on_trip" -> Orange; else -> Gray500 }
                            Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable {
                                showDriverSheet = false; driverSearchQuery = ""; actionLoading = true
                                scope.launch { val r = repo.assignDriver(bookingId, d.id); actionLoading = false; action(if (r.safeBool("success") == true) "Driver ${d.name} assigned!" else r.safeString("error") ?: "Failed"); refresh() }
                            }, shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                                Column(modifier = Modifier.padding(12.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Text(d.name, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                        Surface(shape = RoundedCornerShape(4.dp), color = statusColor.copy(alpha = 0.15f)) {
                                            Text(d.status.uppercase(), color = statusColor, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                                        }
                                    }
                                    Text("${d.carModel} \u2022 ${d.plateNumber}", color = Gray400, fontSize = 12.sp)
                                    Row { Text("\u2605 ${String.format("%.1f", d.rating)}", color = Gold, fontSize = 12.sp); Spacer(Modifier.width(12.dp)); Text(d.phone, color = Gray300, fontSize = 12.sp) }
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }
            }
        }
        // Quick Add Driver Dialog
        if (showAddDriverDialog) {
            AlertDialog(
                onDismissRequest = { showAddDriverDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("Add New Driver", color = Gold, fontWeight = FontWeight.Bold) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        PavanTextField(value = addDriverName, onValueChange = { addDriverName = it }, label = "Driver Name *")
                        PavanTextField(value = addDriverPhone, onValueChange = { addDriverPhone = it }, label = "Phone Number *")
                        PavanTextField(value = addDriverModel, onValueChange = { addDriverModel = it }, label = "Car Model")
                        PavanTextField(value = addDriverPlate, onValueChange = { addDriverPlate = it }, label = "Plate Number")
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        if (addDriverName.isBlank() || addDriverPhone.isBlank()) {
                            Toast.makeText(context, "Name and phone required", Toast.LENGTH_SHORT).show()
                            return@TextButton
                        }
                        showAddDriverDialog = false
                        actionLoading = true
                        scope.launch {
                            val r = repo.addDriver(addDriverName, addDriverPhone, addDriverModel, addDriverPlate)
                            actionLoading = false
                            if (r.safeBool("success") == true) {
                                action("Driver ${addDriverName} added!")
                                drivers = repo.getDrivers()
                                addDriverName = ""; addDriverPhone = ""; addDriverModel = "Goa Cab"; addDriverPlate = "GA-03-T-1234"
                            } else {
                                action(r.safeString("error") ?: "Failed to add driver")
                            }
                        }
                    }) { Text("Add & Assign", color = Gold) }
                },
                dismissButton = { TextButton(onClick = { showAddDriverDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }
        // Status Dialog
        if (showStatusDialog) {
            val bk = booking
            if (bk != null) {
            val allStatuses = listOf(
                "PENDING" to "Pending",
                "CONFIRMED" to "Confirmed",
                "ASSIGNED" to "Assigned",
                "ACCEPTED" to "Accepted",
                "IN_TRANSIT" to "In Transit",
                "ON_TRIP" to "On Trip",
                "COMPLETED" to "Completed",
                "CANCELLED" to "Cancelled",
                "CANCELLED_BY_ADMIN" to "Cancelled by Admin"
            )
            AlertDialog(
                onDismissRequest = { showStatusDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("Change Ride Status", color = White, fontWeight = FontWeight.Bold) },
                text = {
                    LazyColumn(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        items(allStatuses) { (status, label) ->
                            val isCurrent = bk.status == status
                            val stColor = when (status) {
                                "PENDING" -> Gold
                                "CONFIRMED", "ASSIGNED", "ACCEPTED" -> Blue
                                "IN_TRANSIT", "ON_TRIP" -> Orange
                                "COMPLETED" -> Emerald
                                else -> Red
                            }
                            Surface(
                                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable {
                                    showStatusDialog = false
                                    actionLoading = true
                                    scope.launch {
                                        val r = repo.updateBookingStatus(bookingId, status)
                                        actionLoading = false
                                        action(if (r.safeBool("success") == true) "Status changed to $label!" else r.safeString("error") ?: "Failed")
                                        refresh()
                                    }
                                },
                                shape = RoundedCornerShape(10.dp),
                                color = if (isCurrent) stColor.copy(alpha = 0.15f) else DarkBgLighter,
                                border = BorderStroke(1.dp, if (isCurrent) stColor else CardBorder)
                            ) {
                                Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Surface(modifier = Modifier.size(8.dp), shape = RoundedCornerShape(4.dp), color = stColor) {}
                                    Spacer(Modifier.width(10.dp))
                                    Text(label, color = if (isCurrent) stColor else White, fontSize = 14.sp, fontWeight = if (isCurrent) FontWeight.Bold else FontWeight.Medium, modifier = Modifier.weight(1f))
                                    if (isCurrent) {
                                        Surface(shape = RoundedCornerShape(4.dp), color = stColor.copy(alpha = 0.2f)) {
                                            Text("CURRENT", color = stColor, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                confirmButton = {},
                dismissButton = { TextButton(onClick = { showStatusDialog = false }) { Text("Close", color = Gray400) } }
            )
            }
        }
        // Propose Fare Dialog
        if (showProposeFareDialog) AlertDialog(onDismissRequest = { showProposeFareDialog = false }, containerColor = DarkBgLighter, title = { Text("Propose Fare to Customer", color = Gold, fontWeight = FontWeight.Bold) }, text = { Column { Text("Customer will be asked to accept or decline this fare.", color = Gray400, fontSize = 12.sp); Spacer(Modifier.height(8.dp)); PavanTextField(value = proposeFareAmount, onValueChange = { proposeFareAmount = it }, label = "Proposed Fare (\u20B9) *"); Spacer(Modifier.height(8.dp)); PavanTextField(value = proposeReason, onValueChange = { proposeReason = it }, label = "Reason") } }, confirmButton = { TextButton(onClick = { showProposeFareDialog = false; val amt = proposeFareAmount.toDoubleOrNull() ?: return@TextButton; actionLoading = true; scope.launch { val r = repo.proposeFare(bookingId, amt, proposeReason); actionLoading = false; action(if (r.safeBool("success") == true) "Fare proposal sent to customer!" else r.safeString("error") ?: "Failed"); refresh() } }) { Text("Send Proposal", color = Gold) } }, dismissButton = { TextButton(onClick = { showProposeFareDialog = false }) { Text("Cancel", color = Gray400) } })
        // Edit Fare Dialog (direct admin override)
        if (showFareDialog) AlertDialog(onDismissRequest = { showFareDialog = false }, containerColor = DarkBgLighter, title = { Text("Edit Fare (Direct)", color = White, fontWeight = FontWeight.Bold) }, text = { PavanTextField(value = newFare, onValueChange = { newFare = it }, label = "New Fare (\u20B9)") }, confirmButton = { TextButton(onClick = { showFareDialog = false; val amt = newFare.toDoubleOrNull() ?: return@TextButton; actionLoading = true; scope.launch { val r = repo.adjustFare(bookingId, amt); actionLoading = false; action(if (r.safeBool("success") == true) "Fare updated!" else "Failed"); refresh() } }) { Text("Update", color = Gold) } }, dismissButton = { TextButton(onClick = { showFareDialog = false }) { Text("Cancel", color = Gray400) } })
        if (showCancelDialog) AlertDialog(onDismissRequest = { showCancelDialog = false }, containerColor = DarkBgLighter, title = { Text("Cancel Ride?", color = Red, fontWeight = FontWeight.Bold) }, text = { Text("This will cancel booking ${booking?.bookingRef ?: ""}. This action cannot be undone.", color = Gray300, fontSize = 13.sp) }, confirmButton = { TextButton(onClick = { showCancelDialog = false; actionLoading = true; scope.launch { val r = repo.cancelBooking(bookingId); actionLoading = false; action(if (r.safeBool("success") == true) "Ride cancelled" else "Failed"); refresh() } }) { Text("Cancel Ride", color = Red) } }, dismissButton = { TextButton(onClick = { showCancelDialog = false }) { Text("Go Back", color = Gray400) } })
        if (showDeleteDialog) AlertDialog(onDismissRequest = { showDeleteDialog = false }, containerColor = DarkBgLighter, title = { Text("Delete Permanently?", color = Red, fontWeight = FontWeight.Bold) }, text = { Text("This will permanently delete booking ${booking?.bookingRef ?: ""} from the database. This CANNOT be undone!", color = Gray300, fontSize = 13.sp) }, confirmButton = { TextButton(onClick = { showDeleteDialog = false; actionLoading = true; scope.launch { val r = repo.deleteBooking(bookingId); actionLoading = false; if (r.safeBool("success") == true) { action("Booking deleted"); onBack() } else action(r.safeString("error") ?: "Failed") } }) { Text("DELETE", color = Red) } }, dismissButton = { TextButton(onClick = { showDeleteDialog = false }) { Text("Cancel", color = Gray400) } })
        if (actionLoading) LoadingOverlay("Processing...")
    }
}

@Composable
private fun SmallActionBtn(label: String, icon: androidx.compose.ui.graphics.vector.ImageVector, onClick: () -> Unit) {
    Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onClick), shape = RoundedCornerShape(8.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = Gold, modifier = Modifier.size(14.dp))
            Spacer(Modifier.width(4.dp))
            Text(label, color = White, fontSize = 11.sp, fontWeight = FontWeight.Medium)
        }
    }
}
