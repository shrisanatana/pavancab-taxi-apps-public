package com.pavancab.niranjan.ui.rides

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListState
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.model.Booking
import com.pavancab.niranjan.model.DriverOffer
import com.pavancab.niranjan.network.ApiClient
import com.pavancab.niranjan.ui.theme.*
import com.pavancab.niranjan.utils.DateUtils
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

// Indian-style part-of-day label: Morning / Afternoon / Evening / Night
private fun partOfDay(h: Int): String = when (h) {
    in 5..11 -> "Morning"
    in 12..15 -> "Afternoon"
    in 16..19 -> "Evening"
    else -> "Night"
}

// Human-friendly ride status for passengers
private fun humanStatus(status: String): String = when (status.uppercase()) {
    "PENDING", "AWAITING" -> "\uD83D\uDFE1 Finding your driver"
    "CONFIRMED" -> "\uD83D\uDD35 Driver being arranged"
    "ASSIGNED", "DRIVER_ASSIGNED" -> "\uD83D\uDFE2 Driver assigned"
    "ACCEPTED" -> "\uD83D\uDFE2 Driver confirmed"
    "IN_TRANSIT", "ON_TRIP" -> "\uD83D\uDE95 On trip"
    "COMPLETED" -> "\u2705 Completed"
    else -> status.replace("_", " ")
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProgressIndicator(currentStep: Int) {
    val steps = listOf("Booked", "Dispatched", "On Trip", "Completed")
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        steps.forEachIndexed { i, label ->
            val active = i < currentStep
            Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.weight(1f)) {
                Box(modifier = Modifier.size(14.dp).clip(CircleShape).background(if (active) Gold else Gray700), contentAlignment = Alignment.Center) {
                    if (active) { Icon(Icons.Default.Check, null, tint = DarkBg, modifier = Modifier.size(9.dp)) }
                }
                Spacer(Modifier.height(3.dp))
                Text(label, color = if (active) Gold else Gray600, fontSize = 10.sp, fontWeight = if (active) FontWeight.Bold else FontWeight.Normal, maxLines = 1)
            }
            if (i < steps.size - 1) {
                Box(modifier = Modifier.padding(top = 6.dp).weight(0.5f).height(1.5.dp).background(if (i < currentStep - 1) Gold else Gray700))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DriverCard(booking: Booking, onCall: (String) -> Unit, onWhatsApp: (String) -> Unit) {
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = CardBgLight, border = BorderStroke(1.dp, CardBorder)) {
        Column(Modifier.padding(12.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("YOUR DRIVER", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                Spacer(Modifier.weight(1f))
                if ((booking.driverRating ?: 0.0) > 0) {
                    Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(3.dp))
                    Text("${booking.driverRating}", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
            Spacer(Modifier.height(6.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Surface(modifier = Modifier.size(42.dp).clip(CircleShape), color = StatusAssigned.copy(alpha = 0.2f)) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(Icons.Default.Person, null, tint = StatusAssigned, modifier = Modifier.size(22.dp))
                    }
                }
                Spacer(Modifier.width(10.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(booking.driverName ?: "Driver", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    booking.vehicleModel?.takeIf { it.isNotBlank() }?.let {
                        Text(it, color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    }
                    booking.vehicleNumber?.takeIf { it.isNotBlank() }?.let {
                        Text(it, color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                    booking.driverPhone?.takeIf { it.isNotBlank() }?.let { Text(it, color = Gray400, fontSize = 11.sp) }
                }
            }
            if (!booking.driverPhone.isNullOrBlank()) {
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(onClick = { onCall(booking.driverPhone!!) }, modifier = Modifier.weight(1f).height(38.dp), shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.5f)), colors = ButtonDefaults.outlinedButtonColors(contentColor = Emerald)) {
                        Icon(Icons.Default.Phone, null, modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("CALL", fontWeight = FontWeight.Black, fontSize = 11.sp)
                    }
                    OutlinedButton(onClick = { onWhatsApp(booking.driverPhone!!) }, modifier = Modifier.weight(1f).height(38.dp), shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, Color(0xFF25D366).copy(alpha = 0.5f)), colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFF25D366))) {
                        Icon(Icons.Default.Chat, null, modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("WHATSAPP", fontWeight = FontWeight.Black, fontSize = 11.sp)
                    }
                }
            }
        }
    }
}
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RideCard(
    booking: Booking,
    highlighted: Boolean,
    relativeDay: String,
    explanation: String,
    completing: Boolean = false,
    offers: List<DriverOffer> = emptyList(),
    statusColor: (String) -> Color,
    progressStep: (String) -> Int,
    onCallDriver: (String) -> Unit,
    onWhatsAppDriver: (String) -> Unit,
    onBoost: (Double) -> Unit,
    onCancel: () -> Unit,
    onComplete: () -> Unit,
    onRate: (Int, String, onDone: () -> Unit, onError: () -> Unit) -> Unit,
    onFareResponded: () -> Unit = {}
) {
    val context = LocalContext.current
    val sc = statusColor(booking.status)
    val step = progressStep(booking.status)
    val statusUpper = booking.status.uppercase()
    val isActive = statusUpper in listOf("PENDING", "CONFIRMED", "ASSIGNED", "AWAITING", "IN_TRANSIT", "ON_TRIP")
    var acceptingOfferId by remember { mutableIntStateOf(0) }
    val offerScope = rememberCoroutineScope()
    // Scheduled ride logic: combine pickupDate (yyyy-MM-dd) + pickupTime (HH:mm) into a timestamp.
    // Critical: never show "searching from now" for rides more than 48 hours away — even 1 week later.
    val pickupMillis: Long = try {
        val timeParts = booking.pickupTime.split(":")
        val hh = timeParts.getOrNull(0)?.filter { it.isDigit() }?.toIntOrNull() ?: 0
        val mm = timeParts.getOrNull(1)?.filter { it.isDigit() }?.take(2)?.toIntOrNull() ?: 0
        val cal = Calendar.getInstance()
        val dateCal = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(booking.pickupDate)
        if (dateCal != null) cal.time = dateCal
        cal.set(Calendar.HOUR_OF_DAY, hh)
        cal.set(Calendar.MINUTE, mm)
        cal.set(Calendar.SECOND, 0)
        cal.timeInMillis
    } catch (_: Exception) { 0L }
    val now = System.currentTimeMillis()
    val isFutureRide = pickupMillis > now && pickupMillis > 0
    val hoursUntilPickup = if (pickupMillis > 0) ((pickupMillis - now) / 3600000L).toInt() else -1
    
    // Night ride detection: pickup between 10PM and 8AM
    val nightCal = Calendar.getInstance().apply { timeInMillis = pickupMillis }
    val pickupHour = nightCal.get(Calendar.HOUR_OF_DAY)
    val isNightPickup = pickupHour >= 22 || pickupHour < 8
    
    // When does driver searching begin?
    // - Day rides (8AM-10PM): 2 hours before pickup
    // - Night rides (10PM-8AM): at 10PM the evening the ride's night begins,
    //   so users see the searching state before they sleep
    val searchStartMillis: Long = if (pickupMillis > 0) {
        val cal = Calendar.getInstance().apply { timeInMillis = pickupMillis }
        if (pickupHour < 8 || pickupHour >= 22) {
            if (pickupHour < 8) cal.add(Calendar.DAY_OF_YEAR, -1)
            cal.set(Calendar.HOUR_OF_DAY, 22)
            cal.set(Calendar.MINUTE, 0)
            cal.set(Calendar.SECOND, 0)
        } else {
            cal.timeInMillis = pickupMillis - 2L * 3600000L
        }
        cal.timeInMillis
    } else Long.MAX_VALUE
    
    // App appears to be finding the driver itself from searchStart until pickup (+1h grace)
    val needsSearching = isActive && booking.driverName.isNullOrBlank() && now >= searchStartMillis && now <= pickupMillis + 3600000L
    
    // Exact date & time when the app will start assigning the driver
    // Indian format: full weekday + date + time with AM/PM + part of day
    // e.g. "Wednesday, 26 Aug • 2:44 PM (Afternoon)"
    val assignTimeTxt: String = if (searchStartMillis != Long.MAX_VALUE) {
        try {
            val calA = Calendar.getInstance().apply { timeInMillis = searchStartMillis }
            val podA = partOfDay(calA.get(Calendar.HOUR_OF_DAY))
            SimpleDateFormat("EEEE, dd MMM \u2022 hh:mm a", Locale.getDefault()).format(java.util.Date(searchStartMillis)) + " ($podA)"
        } catch (_: Exception) { "" }
    } else ""
    
    // Clarification shown BEFORE searching begins — shows exactly WHEN driver will be assigned.
    // Never reveals manual handling — the app itself appears to assign the driver at that moment.
    // Hidden once a driver is already assigned (no duplicate/awkward info).
    val hasDriver = !booking.driverName.isNullOrBlank()
    val scheduledRideText = when {
        !isFutureRide -> ""
        hasDriver -> ""
        needsSearching -> ""
        isNightPickup -> "Driver will be assigned on $assignTimeTxt"
        hoursUntilPickup > 48 -> "Scheduled ride \u2014 driver will be assigned on $assignTimeTxt"
        else -> "Driver will be assigned on $assignTimeTxt"
    }
    val isCompleted = statusUpper == "COMPLETED"
    val canComplete = isActive && hasDriver
    val needsRating = isCompleted && (booking.userRating == null || booking.userRating == 0)

    var inlineRating by remember { mutableIntStateOf(0) }
    var inlineReview by remember { mutableStateOf("") }
    var showRatingUI by remember { mutableStateOf(false) }
    var ratingDismissed by rememberSaveable { mutableStateOf(false) }
    var submittingRating by remember { mutableStateOf(false) }
    var responding by remember { mutableStateOf(false) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(needsRating, ratingDismissed) {
        if (needsRating && !ratingDismissed) showRatingUI = true
    }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        color = if (highlighted) Gold.copy(alpha = 0.07f) else CardBg,
        border = BorderStroke(if (highlighted) 2.dp else 1.dp, if (highlighted) Gold.copy(alpha = 0.7f) else if (needsSearching) Gold.copy(alpha = 0.3f) else CardBorder)
    ) {
        Column {
            // Status color strip — instant visual scan
            Box(Modifier.fillMaxWidth().height(3.dp).background(sc.copy(alpha = 0.85f)))
            Column(Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(booking.bookingRef, color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                Surface(shape = RoundedCornerShape(6.dp), color = sc.copy(alpha = 0.15f)) {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)) {
                        if (statusUpper == "CANCELLED_BY_ADMIN") {
                            Icon(Icons.Default.SupportAgent, null, tint = sc, modifier = Modifier.size(10.dp))
                            Spacer(Modifier.width(3.dp))
                        }
                        Text(
                            when {
                                statusUpper == "CANCELLED_BY_ADMIN" -> "CANCELLED BY PAVANCAB"
                                statusUpper == "CANCELLED_BY_USER" || statusUpper == "CANCELLED" -> "CANCELLED BY YOU"
                                else -> humanStatus(booking.status)
                            },
                            color = sc, fontSize = 10.sp, fontWeight = FontWeight.Black
                        )
                    }
                }
            }
            // Route as hero line
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.Top) {
                Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(top = 5.dp)) {
                    Box(Modifier.size(9.dp).clip(CircleShape).background(Emerald))
                    if (booking.dropLocation.isNotEmpty() && booking.dropLocation != "N/A") {
                        Box(Modifier.width(2.dp).height(14.dp).background(Gray700))
                        Box(Modifier.size(9.dp).clip(CircleShape).background(Red))
                    }
                }
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Text(booking.pickupLocation, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    if (booking.dropLocation.isNotEmpty() && booking.dropLocation != "N/A") {
                        Spacer(Modifier.height(7.dp))
                        Text(booking.dropLocation, color = Gray300, fontSize = 13.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
            // When — relative day first ("Today at 09:30 PM (Night)")
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Schedule, null, tint = Gray500, modifier = Modifier.size(12.dp))
                Spacer(Modifier.width(5.dp))
                val timeTxt = DateUtils.formatTime(booking.pickupTime)
                val hh24 = booking.pickupTime.split(":").firstOrNull()?.filter { it.isDigit() }?.toIntOrNull() ?: -1
                val podTxt = if (hh24 >= 0) " (${partOfDay(hh24)})" else ""
                Text(
                    if (relativeDay.isNotBlank()) "$relativeDay at $timeTxt$podTxt" else "${DateUtils.formatDate(booking.pickupDate)} at $timeTxt$podTxt",
                    color = Gray400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold
                )
            }
            // Plain-language status explanation for anything non-obvious
            if (explanation.isNotEmpty()) {
                Spacer(Modifier.height(6.dp))
                Text(explanation, color = Gray500, fontSize = 11.sp, lineHeight = 15.sp, fontStyle = androidx.compose.ui.text.font.FontStyle.Italic)
            }
            // Scheduled ride clarification (before driver search begins)
            if (scheduledRideText.isNotEmpty()) {
                Spacer(Modifier.height(6.dp))
                Text(scheduledRideText, color = Gold.copy(alpha = 0.85f), fontSize = 11.sp, lineHeight = 15.sp, fontWeight = FontWeight.SemiBold)
            }

            // Fare + chips row
            Spacer(Modifier.height(10.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Surface(shape = RoundedCornerShape(4.dp), color = when (booking.cabType.lowercase()) { "sedan" -> SedanColor.copy(alpha = 0.15f); "ertiga" -> ErtigaColor.copy(alpha = 0.15f); "suv" -> SUVColor.copy(alpha = 0.15f); else -> CrystaColor.copy(alpha = 0.15f) }) {
                    Text(booking.cabType, color = when (booking.cabType.lowercase()) { "sedan" -> SedanColor; "ertiga" -> ErtigaColor; "suv" -> SUVColor; else -> CrystaColor }, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                }
                if (booking.tripType.isNotEmpty()) {
                    Spacer(Modifier.width(6.dp))
                    Surface(shape = RoundedCornerShape(4.dp), color = Gray800) {
                        Text(booking.tripType.uppercase(), color = Gray500, fontSize = 9.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                    }
                }
                Spacer(Modifier.weight(1f))
                if (booking.userOfferedFare > 0) {
                    Column(horizontalAlignment = Alignment.End) {
                        Text("\u20B9${booking.totalFare.toInt()}", color = Color(0xFF22D3EE), fontSize = 17.sp, fontWeight = FontWeight.Black)
                        Text("Your offered fare", color = Gray500, fontSize = 9.sp)
                        if (booking.baseFare > 0 && booking.baseFare != booking.totalFare) {
                            Text("Base \u20B9${booking.baseFare.toInt()}", color = Gray600, fontSize = 9.sp)
                        }
                    }
                } else {
                    Column(horizontalAlignment = Alignment.End) {
                        Text("\u20B9${booking.totalFare.toInt()}", color = Gold, fontSize = 17.sp, fontWeight = FontWeight.Black)
                        if (booking.baseFare > 0 && booking.baseFare != booking.totalFare) {
                            Text("Base \u20B9${booking.baseFare.toInt()}", color = Gray600, fontSize = 9.sp)
                        }
                    }
                }
            }

            // Passenger's own notes echoed back
            if (!booking.specialNotes.isNullOrBlank()) {
                Spacer(Modifier.height(8.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Gray900) {
                    Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp), verticalAlignment = Alignment.Top) {
                        Icon(Icons.Default.Notes, null, tint = Gray500, modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(7.dp))
                        Text(booking.specialNotes ?: "", color = Gray400, fontSize = 11.sp, lineHeight = 15.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
                    }
                }
            }

            if (isActive && booking.proposedFare > 0 && booking.fareProposalStatus == "PENDING") {
                Spacer(Modifier.height(10.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Color(0xFFF59E0B).copy(alpha = 0.08f), border = BorderStroke(1.dp, Color(0xFFF59E0B).copy(alpha = 0.3f))) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.AttachMoney, null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("DRIVER'S FARE PROPOSAL", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                        }
                        Spacer(Modifier.height(6.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text("\u20B9${booking.proposedFare.toInt()}", color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.width(8.dp))
                            Text("(current: \u20B9${booking.totalFare.toInt()})", color = Gray500, fontSize = 12.sp)
                        }
                        if (!booking.fareProposalReason.isNullOrBlank()) {
                            Spacer(Modifier.height(4.dp))
                            Surface(shape = RoundedCornerShape(6.dp), color = Color(0xFFF59E0B).copy(alpha = 0.06f)) {
                                Text(booking.fareProposalReason ?: "", color = Gray400, fontSize = 11.sp, modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp))
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                        if (responding) {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                                CircularProgressIndicator(color = Gold, modifier = Modifier.size(20.dp), strokeWidth = 2.dp)
                                Spacer(Modifier.width(8.dp))
                                Text("Responding...", color = Gray400, fontSize = 11.sp)
                            }
                        } else {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Button(
                                    onClick = {
                                        responding = true
                                        scope.launch {
                                            try {
                                                withContext(kotlinx.coroutines.Dispatchers.IO) {
                                                    Repository(context).respondFareProposal(booking.id, "ACCEPTED")
                                                }
                                            } catch (_: Exception) {}
                                            onFareResponded()
                                            responding = false
                                        }
                                    },
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF10B981))
                                ) {
                                    Icon(Icons.Default.Check, null, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text("ACCEPT \u20B9${booking.proposedFare.toInt()}", fontWeight = FontWeight.Black, fontSize = 10.sp)
                                }
                                Button(
                                    onClick = {
                                        responding = true
                                        scope.launch {
                                            try {
                                                withContext(kotlinx.coroutines.Dispatchers.IO) {
                                                    Repository(context).respondFareProposal(booking.id, "DECLINED")
                                                }
                                            } catch (_: Exception) {}
                                            onFareResponded()
                                            responding = false
                                        }
                                    },
                                    modifier = Modifier.weight(1f).height(38.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFEF4444))
                                ) {
                                    Icon(Icons.Default.Close, null, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text("DECLINE", fontWeight = FontWeight.Black, fontSize = 10.sp)
                                }
                            }
                        }
                    }
                }
            }

            // Driver offers (up to 5) — passenger picks one or waits for a driver at current price
            if (isActive && !hasDriver && offers.isNotEmpty()) {
                Spacer(Modifier.height(10.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Emerald.copy(alpha = 0.05f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.35f))) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.HowToReg, null, tint = Emerald, modifier = Modifier.size(15.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("DRIVER OFFERS (${offers.size})", color = Emerald, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text("Accept one to lock this driver & price \u2014 or wait, a driver may still take it at the current price.", color = Gray500, fontSize = 10.sp, lineHeight = 13.sp)
                        Spacer(Modifier.height(8.dp))
                        offers.forEach { offer ->
                            Surface(
                                modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp),
                                shape = RoundedCornerShape(8.dp),
                                color = CardBgLight,
                                border = BorderStroke(1.dp, CardBorder)
                            ) {
                                Column(modifier = Modifier.padding(10.dp)) {
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        Surface(modifier = Modifier.size(32.dp).clip(CircleShape), color = StatusAssigned.copy(alpha = 0.2f)) {
                                            Box(contentAlignment = Alignment.Center) {
                                                Icon(Icons.Default.Person, null, tint = StatusAssigned, modifier = Modifier.size(17.dp))
                                            }
                                        }
                                        Spacer(Modifier.width(8.dp))
                                        Column(Modifier.weight(1f)) {
                                            Row(verticalAlignment = Alignment.CenterVertically) {
                                                Text(offer.driverName.ifBlank { "Driver" }, color = White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                                if ((booking.driverRating ?: 0.0) > 0 && offer.driverId == 0) {
                                                    Spacer(Modifier.width(4.dp))
                                                    Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(11.dp))
                                                    Text("${booking.driverRating}", color = Gold, fontSize = 10.sp)
                                                }
                                            }
                                            if (offer.vehicleNumber.isNotBlank()) {
                                                Text(offer.vehicleNumber, color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                            }
                                        }
                                        Column(horizontalAlignment = Alignment.End) {
                                            Text("\u20B9${offer.offerAmount.toInt()}", color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Black)
                                            if (offer.offerAmount != booking.totalFare) {
                                                Text("current: \u20B9${booking.totalFare.toInt()}", color = Gray500, fontSize = 9.sp)
                                            }
                                        }
                                    }
                                    if (offer.offerNote.isNotBlank()) {
                                        Spacer(Modifier.height(4.dp))
                                        Text("\u201C${offer.offerNote}\u201D", color = Gray400, fontSize = 10.sp, lineHeight = 13.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
                                    }
                                    Spacer(Modifier.height(8.dp))
                                    Button(
                                        onClick = {
                                            if (acceptingOfferId == 0) {
                                                acceptingOfferId = offer.id
                                                offerScope.launch {
                                                    try {
                                                        withContext(kotlinx.coroutines.Dispatchers.IO) { Repository(context).acceptRideOffer(offer.id) }
                                                    } catch (_: Exception) {}
                                                    onFareResponded()
                                                    acceptingOfferId = 0
                                                }
                                            }
                                        },
                                        enabled = acceptingOfferId == 0,
                                        modifier = Modifier.fillMaxWidth().height(36.dp),
                                        shape = RoundedCornerShape(8.dp),
                                        colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)
                                    ) {
                                        if (acceptingOfferId == offer.id) {
                                            CircularProgressIndicator(modifier = Modifier.size(14.dp), color = DarkBg, strokeWidth = 2.dp)
                                            Spacer(Modifier.width(6.dp))
                                            Text("ACCEPTING...", fontWeight = FontWeight.Black, fontSize = 10.sp)
                                        } else {
                                            Icon(Icons.Default.Check, null, modifier = Modifier.size(14.dp))
                                            Spacer(Modifier.width(4.dp))
                                            Text("ACCEPT \u20B9${offer.offerAmount.toInt()}", fontWeight = FontWeight.Black, fontSize = 10.sp)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (isActive && step > 0) { Spacer(Modifier.height(10.dp)); ProgressIndicator(step) }
            if (needsSearching) { Spacer(Modifier.height(10.dp)); SearchAnimation(booking.status) }
            if (hasDriver) { Spacer(Modifier.height(10.dp)); DriverCard(booking, onCallDriver, onWhatsAppDriver) }

            if (canComplete) {
                Spacer(Modifier.height(10.dp))
                OutlinedButton(
                    onClick = onComplete,
                    modifier = Modifier.fillMaxWidth().height(40.dp),
                    shape = RoundedCornerShape(10.dp),
                    border = BorderStroke(1.dp, Emerald.copy(alpha = 0.55f)),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Emerald)
                ) {
                    Icon(Icons.Default.CheckCircle, null, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("TRIP FINISHED? END RIDE", fontWeight = FontWeight.Black, fontSize = 11.sp, letterSpacing = 0.5.sp)
                }
            }

            if (isActive && !hasDriver && statusUpper in listOf("PENDING", "CONFIRMED", "AWAITING")) {
                Spacer(Modifier.height(10.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.TrendingUp, null, tint = Gold, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("BOOST FARE", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                }
                Spacer(Modifier.height(6.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    listOf(100.0, 200.0, 500.0).forEach { amount ->
                        OutlinedButton(onClick = { onBoost(amount) }, modifier = Modifier.weight(1f).height(38.dp), shape = RoundedCornerShape(8.dp), border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold), contentPadding = PaddingValues(horizontal = 4.dp)) {
                            Icon(Icons.Default.TrendingUp, null, modifier = Modifier.size(12.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("+\u20B9${amount.toInt()}", fontWeight = FontWeight.Black, fontSize = 12.sp)
                        }
                    }
                }
                var showCustomBoost by remember { mutableStateOf(false) }
                var customBoost by remember { mutableStateOf("") }
                if (showCustomBoost) {
                    Spacer(Modifier.height(6.dp))
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        OutlinedTextField(
                            value = customBoost,
                            onValueChange = { customBoost = it.filter { c -> c.isDigit() }.take(5) },
                            placeholder = { Text("Enter \u20B9", color = Gray600, fontSize = 12.sp) },
                            modifier = Modifier.weight(1f).heightIn(min = 48.dp),
                            singleLine = true,
                            shape = RoundedCornerShape(8.dp),
                            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.Number),
                            colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold)
                        )
                        Button(
                            onClick = {
                                val amt = customBoost.toDoubleOrNull() ?: 0.0
                                if (amt >= 50) { showCustomBoost = false; customBoost = ""; onBoost(amt) }
                            },
                            enabled = (customBoost.toDoubleOrNull() ?: 0.0) >= 50,
                            modifier = Modifier.height(48.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                        ) { Text("APPLY", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                    }
                } else {
                    TextButton(onClick = { showCustomBoost = true }) {
                        Text("+ Enter custom amount", color = Gray400, fontSize = 10.sp)
                    }
                }
            }

            if (isActive && !hasDriver) {
                Spacer(Modifier.height(8.dp))
                Button(onClick = onCancel, modifier = Modifier.fillMaxWidth().height(40.dp), shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = Red.copy(alpha = 0.15f), contentColor = Red)) {
                    Icon(Icons.Default.Cancel, null, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("CANCEL RIDE", fontWeight = FontWeight.Black, fontSize = 11.sp)
                }
            }

            if (needsRating && showRatingUI) {
                Spacer(Modifier.height(10.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = CardBgLight, border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Text("RATE YOUR RIDE", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            repeat(5) { i ->
                                IconButton(onClick = { inlineRating = i + 1 }, modifier = Modifier.size(36.dp)) {
                                    Icon(if (i < inlineRating) Icons.Default.Star else Icons.Default.StarBorder, null, tint = if (i < inlineRating) Gold else Gray600, modifier = Modifier.size(28.dp))
                                }
                            }
                        }
                        if (inlineRating > 0) {
                            Spacer(Modifier.height(4.dp))
                            Text(when (inlineRating) { 1 -> "Poor"; 2 -> "Below Average"; 3 -> "Good"; 4 -> "Very Good"; 5 -> "Excellent"; else -> "" }, color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(6.dp))
                        OutlinedTextField(value = inlineReview, onValueChange = { if (it.length <= 300) inlineReview = it }, placeholder = { Text("Write your review (optional)", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold))
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { showRatingUI = false; ratingDismissed = true; inlineRating = 0; inlineReview = "" }, modifier = Modifier.weight(1f).height(38.dp), shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("LATER", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = { if (inlineRating > 0) { submittingRating = true; onRate(inlineRating, inlineReview, { showRatingUI = false; ratingDismissed = true; inlineRating = 0; inlineReview = ""; submittingRating = false }, { submittingRating = false }) } }, enabled = inlineRating > 0 && !submittingRating, modifier = Modifier.weight(1f).height(38.dp), shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                Text(if (submittingRating) "SENDING..." else "SUBMIT", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                        }
                    }
                }
            } else if ((booking.userRating ?: 0) > 0) {
                Spacer(Modifier.height(8.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), color = Gray900) {
                    Column(modifier = Modifier.padding(horizontal = 10.dp, vertical = 8.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            repeat(5) { i -> Icon(if (i < (booking.userRating ?: 0)) Icons.Default.Star else Icons.Default.StarBorder, null, tint = if (i < (booking.userRating ?: 0)) Gold else Gray700, modifier = Modifier.size(14.dp)) }
                            Spacer(Modifier.width(8.dp))
                            Text("Your rating: ${booking.userRating}/5", color = Gray500, fontSize = 11.sp)
                        }
                        if (!booking.userReview.isNullOrBlank()) {
                            Spacer(Modifier.height(3.dp))
                            Text(booking.userReview ?: "", color = Gray500, fontSize = 11.sp, lineHeight = 15.sp)
                        }
                    }
                }
            }
        }
    }
}
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyRidesScreen(highlightBookingId: Int? = null, onBookNow: () -> Unit = {}, refreshTrigger: Int = 0) {    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    val listState = remember { LazyListState() }
    var bookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var driverOffers by remember { mutableStateOf<List<DriverOffer>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var loadError by remember { mutableStateOf("") }
    var isOfflineData by remember { mutableStateOf(false) }
    var showCancelDialog by remember { mutableStateOf<Booking?>(null) }
    var showCompleteDialog by remember { mutableStateOf<Booking?>(null) }
    var actionMessage by remember { mutableStateOf("") }
    var actionIsError by remember { mutableStateOf(false) }
    var activeTab by rememberSaveable { mutableIntStateOf(0) }
    var completing by remember { mutableIntStateOf(0) }
    var cancelling by remember { mutableIntStateOf(0) }
    // Past-tab controls (survive rotation)
    var pastSearch by rememberSaveable { mutableStateOf("") }
    var newestFirst by rememberSaveable { mutableStateOf(true) }

    fun statusColor(status: String): Color = when (status.uppercase()) {
        "PENDING", "AWAITING" -> StatusPending
        "CONFIRMED" -> StatusConfirmed
        "ASSIGNED" -> StatusAssigned
        "IN_TRANSIT", "ON_TRIP" -> StatusInTransit
        "COMPLETED" -> StatusCompleted
        else -> StatusCancelled
    }

    fun progressStep(status: String): Int = when (status.uppercase()) {
        "PENDING" -> 1
        "CONFIRMED" -> 2
        "ASSIGNED", "IN_TRANSIT", "ON_TRIP" -> 3
        "COMPLETED" -> 4
        else -> 0
    }

    fun statusExplanation(status: String): String = when (status.uppercase()) {
        "PENDING", "AWAITING" -> "Your booking is received. Driver details will appear here before your ride."
        "CONFIRMED" -> "Your booking is confirmed. Driver details will appear here soon."
        "ASSIGNED" -> "Driver assigned! Please be ready at the pickup point on time."
        "IN_TRANSIT", "ON_TRIP" -> "Enjoy your ride!"
        "CANCELLED_BY_ADMIN" -> "This ride was cancelled by PavanCab support. Chat with us if this looks wrong."
        "REJECTED" -> "This booking could not be served. Please contact support or book again."
        else -> ""
    }

    fun relativeDayLabel(dateStr: String): String {
        if (dateStr.isBlank()) return ""
        return try {
            val fmt = SimpleDateFormat("yyyy-MM-dd", Locale.US)
            val d = fmt.parse(dateStr.trim()) ?: return DateUtils.formatDate(dateStr)
            val today = Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, 0); set(Calendar.MINUTE, 0); set(Calendar.SECOND, 0); set(Calendar.MILLISECOND, 0) }
            val target = Calendar.getInstance().apply { time = d; set(Calendar.HOUR_OF_DAY, 0); set(Calendar.MINUTE, 0); set(Calendar.SECOND, 0); set(Calendar.MILLISECOND, 0) }
            val diffDays = ((target.timeInMillis - today.timeInMillis) / 86400000L).toInt()
            when (diffDays) {
                0 -> "Today"
                1 -> "Tomorrow"
                -1 -> "Yesterday"
                in 2..6 -> SimpleDateFormat("EEEE", Locale.US).format(d)
                else -> DateUtils.formatDate(dateStr)
            }
        } catch (_: Exception) { DateUtils.formatDate(dateStr) }
    }

    fun fetch(showSpinner: Boolean) {
        scope.launch {
            if (showSpinner) loading = true
            try {
                val p = UserPrefs.getPhone(context)
                val e = UserPrefs.getEmail(context)
                bookings = withContext(kotlinx.coroutines.Dispatchers.IO) { repo.getBookings(p, e) }
                isOfflineData = Repository.lastError == "OFFLINE" && bookings.isNotEmpty()
                loadError = if (bookings.isEmpty() && Repository.lastError != null && Repository.lastError != "OFFLINE" && !ApiClient.sessionExpired) "Couldn't load your rides. Please check your connection." else ""
            } catch (_: Exception) {
                isOfflineData = bookings.isNotEmpty()
                if (bookings.isEmpty() && !ApiClient.sessionExpired) loadError = "Couldn't load your rides. Please check your connection."
            }
            // Smart polling — only ask for offers when a ride is actually waiting for a driver
            val needOffers = !isOfflineData && bookings.any { it.status.uppercase() == "PENDING" && it.driverName.isNullOrBlank() }
            driverOffers = if (needOffers) {
                try { withContext(kotlinx.coroutines.Dispatchers.IO) { repo.getRideOffers() } } catch (_: Exception) { emptyList() }
            } else emptyList()
            loading = false
        }
    }

    LaunchedEffect(Unit) { fetch(true) }

    // Refresh instantly when an FCM push arrives (driver accept / cancel / fare proposal)
    LaunchedEffect(refreshTrigger) {
        if (refreshTrigger > 0) fetch(false)
    }

    // Auto-poll only while app is visible (battery friendly)
    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(Unit) {
        while (true) {
            delay(10000)
            if (lifecycleOwner.lifecycle.currentState.isAtLeast(Lifecycle.State.STARTED)) fetch(false)
        }
    }

    // Scroll to highlighted ride coming from a notification tap
    LaunchedEffect(highlightBookingId, bookings.size) {
        val id = highlightBookingId ?: return@LaunchedEffect
        if (bookings.isEmpty() || id <= 0) return@LaunchedEffect
        val target = bookings.firstOrNull { it.id == id } ?: return@LaunchedEffect
        val isActive = target.status.uppercase() in listOf("PENDING", "CONFIRMED", "ASSIGNED", "AWAITING", "IN_TRANSIT", "ON_TRIP") ||
            (target.status.uppercase() == "COMPLETED" && (target.userRating == null || target.userRating == 0))
        activeTab = if (isActive) 0 else 1
        kotlinx.coroutines.delay(300)
        runCatching { listState.animateScrollToItem(0) }
    }

    val activeStatuses = listOf("PENDING", "CONFIRMED", "ASSIGNED", "AWAITING", "IN_TRANSIT", "ON_TRIP")
    val activeBookings = remember(bookings) {
        bookings.filter { it.status.uppercase() in activeStatuses || (it.status.uppercase() == "COMPLETED" && (it.userRating == null || it.userRating == 0)) }
            .sortedBy { b ->
                // Soonest pickup first so the ride that matters most is on top
                runCatching { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).parse("${b.pickupDate} ${b.pickupTime}")?.time }.getOrNull() ?: Long.MAX_VALUE
            }
    }
    val filteredPast = remember(bookings, pastSearch, newestFirst) {
        val base = bookings.filter { it.status.uppercase() !in activeStatuses && !(it.status.uppercase() == "COMPLETED" && (it.userRating == null || it.userRating == 0)) }
        val searched = if (pastSearch.isBlank()) base else base.filter { b ->
            b.bookingRef.contains(pastSearch, true) ||
                b.pickupLocation.contains(pastSearch, true) ||
                b.dropLocation.contains(pastSearch, true) ||
                b.cabType.contains(pastSearch, true)
        }
        searched.sortedBy { b ->
            val t = runCatching { SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).parse("${b.pickupDate} ${b.pickupTime}")?.time }.getOrNull() ?: 0L
            if (newestFirst) -t else t
        }
    }
    val displayBookings = if (activeTab == 0) activeBookings else filteredPast

    Scaffold(
        containerColor = DarkBg,
        topBar = {
            TopAppBar(
                title = { Text("MY RIDES", fontWeight = FontWeight.Black, fontSize = 15.sp, letterSpacing = 1.sp, color = White) },
                actions = {
                    IconButton(onClick = { fetch(false) }) { Icon(Icons.Default.Refresh, "Refresh", tint = Gold) }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        }
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("Active (${activeBookings.size})", "Past (${filteredPast.size})").forEachIndexed { i, label ->
                    val sel = activeTab == i
                    Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { activeTab = i }, shape = RoundedCornerShape(10.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                        Text(label, textAlign = TextAlign.Center, fontWeight = FontWeight.Black, fontSize = 12.sp, color = if (sel) DarkBg else Gray400, modifier = Modifier.padding(vertical = 10.dp))
                    }
                }
            }

            // Past tab toolbar: search + sort toggle
            if (activeTab == 1 && (filteredPast.isNotEmpty() || pastSearch.isNotBlank())) {
                Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(
                        value = pastSearch,
                        onValueChange = { pastSearch = it },
                        placeholder = { Text("Search ref, place or cab", color = Gray600, fontSize = 12.sp) },
                        leadingIcon = { Icon(Icons.Default.Search, null, tint = Gray500, modifier = Modifier.size(16.dp)) },
                        trailingIcon = {
                            if (pastSearch.isNotEmpty()) IconButton(onClick = { pastSearch = "" }, modifier = Modifier.size(28.dp)) {
                                Icon(Icons.Default.Close, null, tint = Gray500, modifier = Modifier.size(14.dp))
                            }
                        },
                        singleLine = true,
                        shape = RoundedCornerShape(10.dp),
                        modifier = Modifier.weight(1f).heightIn(min = 44.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold.copy(alpha = 0.5f), unfocusedBorderColor = CardBorder, focusedContainerColor = CardBgLight, unfocusedContainerColor = CardBgLight, cursorColor = Gold)
                    )
                    Surface(modifier = Modifier.clip(RoundedCornerShape(10.dp)).clickable { newestFirst = !newestFirst }, shape = RoundedCornerShape(10.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                        Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 11.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.SwapVert, null, tint = Gold, modifier = Modifier.size(14.dp))
                            Spacer(Modifier.width(4.dp))
                            Text(if (newestFirst) "Newest" else "Oldest", color = Gray300, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            when {
                loading && bookings.isEmpty() -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            CircularProgressIndicator(color = Gold, modifier = Modifier.size(48.dp), strokeWidth = 3.dp)
                            Spacer(Modifier.height(16.dp))
                            Text("Loading your rides...", color = Gray400, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                        }
                    }
                }
                displayBookings.isEmpty() && loadError.isNotEmpty() -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
                            Icon(Icons.Default.CloudOff, null, tint = Gray600, modifier = Modifier.size(56.dp))
                            Spacer(Modifier.height(14.dp))
                            Text(loadError, color = Gray400, fontSize = 14.sp, fontWeight = FontWeight.Medium, textAlign = TextAlign.Center)
                            Spacer(Modifier.height(14.dp))
                            Button(onClick = { fetch(true) }, shape = RoundedCornerShape(10.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)) {
                                Icon(Icons.Default.Refresh, null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("TRY AGAIN", fontWeight = FontWeight.Black, fontSize = 12.sp)
                            }
                        }
                    }
                }
                displayBookings.isEmpty() -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.padding(32.dp)) {
                            Icon(Icons.Default.DirectionsCar, null, tint = Gray600, modifier = Modifier.size(64.dp))
                            Spacer(Modifier.height(16.dp))
                            Text(if (activeTab == 0) "No active rides" else if (pastSearch.isNotBlank()) "No matching rides" else "No past rides", color = Gray400, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.height(6.dp))
                            Text(
                                if (activeTab == 0) "Ready to explore Goa?" else if (pastSearch.isNotBlank()) "Try a different search" else "Completed and cancelled rides appear here",
                                color = Gray500, fontSize = 12.sp, textAlign = TextAlign.Center
                            )
                            if (activeTab == 0 && pastSearch.isBlank() && loadError.isEmpty()) {
                                Spacer(Modifier.height(14.dp))
                                Button(onClick = onBookNow, shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)) {
                                    Icon(Icons.Default.LocalTaxi, null, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text("BOOK A RIDE NOW", fontWeight = FontWeight.Black, fontSize = 12.sp)
                                }
                            }
                        }
                    }
                }
                else -> {
                    LazyColumn(state = listState, modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp), contentPadding = PaddingValues(vertical = 8.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        item {
                            if (isOfflineData) {
                                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = StatusPending.copy(alpha = 0.1f), border = BorderStroke(1.dp, StatusPending.copy(alpha = 0.4f))) {
                                    Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                        Icon(Icons.Default.CloudOff, null, tint = StatusPending, modifier = Modifier.size(15.dp))
                                        Spacer(Modifier.width(8.dp))
                                        Text("Offline — showing your saved rides. Will auto-sync when connected.", color = Gray300, fontSize = 11.sp, modifier = Modifier.weight(1f))
                                    }
                                }
                            }
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                                TextButton(onClick = { fetch(true) }, enabled = !loading) {
                                    Icon(Icons.Default.Refresh, null, tint = Gold, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text("REFRESH", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }
                        items(displayBookings, key = { it.id }) { booking ->
                            RideCard(
                                booking = booking,
                                highlighted = highlightBookingId == booking.id,
                                relativeDay = relativeDayLabel(booking.pickupDate),
                                explanation = statusExplanation(booking.status),
                                completing = completing == booking.id,
                                offers = driverOffers.filter { it.bookingId == booking.id },
                                statusColor = { statusColor(it) },
                                progressStep = { progressStep(it) },
                                onCallDriver = { phone -> context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))) },
                                onWhatsAppDriver = { phone -> context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/91${phone.takeLast(10)}"))) },
                                onBoost = { amount ->
                                    scope.launch {
                                        try {
                                            repo.boostFare(booking.id, amount)
                                            actionMessage = "+\u20B9${amount.toInt()} boost applied — drivers notified!"; actionIsError = false
                                            fetch(false)
                                        } catch (e: Exception) { actionMessage = "Boost failed: ${e.message}"; actionIsError = true }
                                    }
                                },
                                onCancel = { showCancelDialog = booking },
                                onComplete = { showCompleteDialog = booking },
                                onRate = { rating, review, onDone, onError ->
                                    scope.launch {
                                        try {
                                            val result = repo.rateRide(booking.id, rating, review)
                                            val success = try { result.get("success").asBoolean } catch (_: Exception) { false }
                                            if (success) {
                                                actionMessage = "Thanks for rating!"; actionIsError = false
                                                fetch(false)
                                                onDone()
                                            } else {
                                                val errMsg = try { result.get("error")?.asString ?: "" } catch (_: Exception) { "" }
                                                actionMessage = errMsg.ifBlank { "Failed to submit rating. Try again." }; actionIsError = true
                                                onError()
                                            }
                                        } catch (e: Exception) { actionMessage = "Failed to submit rating. Check connection."; actionIsError = true; onError() }
                                    }
                                },
                                onFareResponded = { fetch(false) }
                            )
                        }
                        item { Spacer(Modifier.height(8.dp)) }
                    }
                }
            }
        }

        if (actionMessage.isNotEmpty()) {
            LaunchedEffect(actionMessage) { delay(3000); actionMessage = "" }
            Box(modifier = Modifier.fillMaxSize().padding(16.dp), contentAlignment = Alignment.BottomCenter) {
                Surface(shape = RoundedCornerShape(10.dp), color = (if (actionIsError) Red else Emerald).copy(alpha = 0.95f)) {
                    Text(actionMessage, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp))
                }
            }
        }
    }

    // Cancel confirmation with reason capture
    showCancelDialog?.let { booking ->
        val reasons = listOf("Price too high", "Plans changed", "Booked elsewhere", "Driver not suitable", "Other")
        var selReason by remember(showCancelDialog) { mutableStateOf("") }
        AlertDialog(
            onDismissRequest = { showCancelDialog = null },
            icon = { Icon(Icons.Default.Cancel, null, tint = Red, modifier = Modifier.size(32.dp)) },
            title = { Text("Cancel this ride?", fontWeight = FontWeight.Black, color = White) },
            text = {
                Column {
                    Text("Ride ${booking.bookingRef}", color = Gray400, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(10.dp))
                    Text("Why are you cancelling? (helps us improve)", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(6.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        reasons.take(3).forEach { r ->
                            Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selReason = if (selReason == r) "" else r }, shape = RoundedCornerShape(8.dp), color = if (selReason == r) Gold.copy(alpha = 0.25f) else CardBgLight, border = BorderStroke(1.dp, if (selReason == r) Gold else CardBorder)) {
                                Text(r, color = if (selReason == r) Gold else Gray300, fontSize = 10.sp, modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp))
                            }
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        reasons.drop(3).forEach { r ->
                            Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selReason = if (selReason == r) "" else r }, shape = RoundedCornerShape(8.dp), color = if (selReason == r) Gold.copy(alpha = 0.25f) else CardBgLight, border = BorderStroke(1.dp, if (selReason == r) Gold else CardBorder)) {
                                Text(r, color = if (selReason == r) Gold else Gray300, fontSize = 10.sp, modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp))
                            }
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                    Text("\u2022 The assigned driver (if any) will be released", color = Gray300, fontSize = 13.sp, lineHeight = 18.sp)
                    Spacer(Modifier.height(4.dp))
                    Text("\u2022 This cannot be undone", color = Gray300, fontSize = 13.sp, lineHeight = 18.sp)
                    if (cancelling == booking.id) {
                        Spacer(Modifier.height(10.dp))
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            CircularProgressIndicator(color = Red, modifier = Modifier.size(16.dp), strokeWidth = 2.dp)
                            Spacer(Modifier.width(8.dp))
                            Text("Cancelling...", color = Gray400, fontSize = 11.sp)
                        }
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (cancelling != booking.id) {
                            scope.launch {
                                cancelling = booking.id
                                try {
                                    val result = repo.cancelBooking(booking.id, selReason)
                                    val success = try { result.get("success").asBoolean } catch (_: Exception) { false }
                                    if (success) {
                                        actionMessage = "Ride cancelled"; actionIsError = false
                                    } else {
                                        val errMsg = try { result.get("error")?.asString ?: "" } catch (_: Exception) { "" }
                                        actionMessage = errMsg.ifBlank { "Couldn't cancel. Try again." }; actionIsError = true
                                    }
                                } catch (e: Exception) {
                                    actionMessage = "Couldn't cancel. Try again."; actionIsError = true
                                }
                                cancelling = 0
                                showCancelDialog = null
                                fetch(false)
                            }
                        }
                    },
                    enabled = cancelling != booking.id,
                    colors = ButtonDefaults.buttonColors(containerColor = Red, disabledContainerColor = Red.copy(alpha = 0.5f))
                ) { Text("YES, CANCEL", fontWeight = FontWeight.Black) }
            },
            dismissButton = { TextButton(onClick = { showCancelDialog = null }) { Text("KEEP RIDE", color = Gray400) } },
            containerColor = CardBg, shape = RoundedCornerShape(16.dp)
        )
    }

    // Complete ride requires deliberate confirmation — prevents accidental taps & premature completion
    showCompleteDialog?.let { booking ->
        AlertDialog(
            onDismissRequest = { showCompleteDialog = null },
            icon = { Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(32.dp)) },
            title = { Text("Trip finished?", fontWeight = FontWeight.Black, color = White) },
            text = {
                Text(
                    "Please confirm only after your ride with ${booking.driverName ?: "the driver"} is actually completed.\n\nYou'll be asked to rate your experience next.",
                    color = Gray300, fontSize = 13.sp, lineHeight = 19.sp
                )
            },
            confirmButton = {
                Button(
                    onClick = {
                        scope.launch {
                            completing = booking.id
                            try {
                                val result = repo.completeRide(booking.id)
                                val success = try { result.get("success").asBoolean } catch (_: Exception) { false }
                                val errMsg = try { result.get("error")?.let { if (!it.isJsonNull) it.asString else "" } ?: "" } catch (_: Exception) { "" }
                                actionMessage = if (success) "Ride completed! Rate it below." else if (errMsg.isNotEmpty()) "Error: $errMsg" else "Couldn't complete ride. Try again."
                                actionIsError = !success
                            } catch (e: Exception) {
                                actionMessage = "Couldn't complete. Check connection."; actionIsError = true
                            }
                            completing = 0
                            showCompleteDialog = null
                            fetch(false)
                        }
                    },
                    enabled = completing != booking.id,
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)
                ) { Text(if (completing == booking.id) "ENDING..." else "YES, END TRIP", fontWeight = FontWeight.Black) }
            },
            dismissButton = { TextButton(onClick = { showCompleteDialog = null }) { Text("NOT YET", color = Gray400) } },
            containerColor = CardBg, shape = RoundedCornerShape(16.dp)
        )
    }
}

@Composable
private fun SearchAnimation(status: String) {
    val infiniteTransition = rememberInfiniteTransition(label = "search")
    val pulseScale by infiniteTransition.animateFloat(initialValue = 0.3f, targetValue = 1.0f, animationSpec = infiniteRepeatable(tween(1200, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "pulse1")
    val pulseAlpha by infiniteTransition.animateFloat(initialValue = 0.7f, targetValue = 0.0f, animationSpec = infiniteRepeatable(tween(1200, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "alpha1")
    val pulseScale2 by infiniteTransition.animateFloat(initialValue = 0.3f, targetValue = 1.0f, animationSpec = infiniteRepeatable(tween(1200, delayMillis = 400, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "pulse2")
    val pulseAlpha2 by infiniteTransition.animateFloat(initialValue = 0.7f, targetValue = 0.0f, animationSpec = infiniteRepeatable(tween(1200, delayMillis = 400, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "alpha2")
    val pulseScale3 by infiniteTransition.animateFloat(initialValue = 0.3f, targetValue = 1.0f, animationSpec = infiniteRepeatable(tween(1200, delayMillis = 800, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "pulse3")
    val pulseAlpha3 by infiniteTransition.animateFloat(initialValue = 0.7f, targetValue = 0.0f, animationSpec = infiniteRepeatable(tween(1200, delayMillis = 800, easing = FastOutSlowInEasing), RepeatMode.Restart), label = "alpha3")
    val rotateAngle by infiniteTransition.animateFloat(initialValue = 0f, targetValue = 360f, animationSpec = infiniteRepeatable(tween(3000, easing = LinearEasing), RepeatMode.Restart), label = "rotate")
    val dotCountFloat by infiniteTransition.animateFloat(initialValue = 1f, targetValue = 4f, animationSpec = infiniteRepeatable(tween(600), RepeatMode.Restart), label = "dots")
    val dotCount = dotCountFloat.toInt()

    val isSearching = status.uppercase() in listOf("PENDING", "CONFIRMED", "AWAITING")
    val isDispatched = status.uppercase() == "ASSIGNED"
    val sc = if (isSearching) Gold else StatusAssigned

    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = sc.copy(alpha = 0.06f), border = BorderStroke(1.dp, sc.copy(alpha = 0.2f))) {
        Column(modifier = Modifier.padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(80.dp)) {
                Box(modifier = Modifier.size(80.dp).scale(pulseScale).clip(CircleShape).background(sc.copy(alpha = pulseAlpha * 0.3f)))
                Box(modifier = Modifier.size(60.dp).scale(pulseScale2).clip(CircleShape).background(sc.copy(alpha = pulseAlpha2 * 0.25f)))
                Box(modifier = Modifier.size(44.dp).scale(pulseScale3).clip(CircleShape).background(sc.copy(alpha = pulseAlpha3 * 0.2f)))
                Box(modifier = Modifier.size(44.dp).clip(CircleShape).background(sc.copy(alpha = 0.15f)), contentAlignment = Alignment.Center) {
                    Icon(Icons.Default.LocalTaxi, null, tint = sc, modifier = Modifier.size(22.dp).rotate(if (isDispatched) 0f else rotateAngle * 0.1f))
                }
                Box(modifier = Modifier.align(Alignment.TopEnd).size(14.dp).clip(CircleShape).background(Emerald), contentAlignment = Alignment.Center) {
                    Icon(Icons.Default.Check, null, tint = DarkBg, modifier = Modifier.size(10.dp))
                }
            }
            Spacer(Modifier.height(10.dp))
            val mainText = when {
                isSearching -> "Searching for nearby drivers${".".repeat(dotCount)}"
                isDispatched -> "Driver assigned! Getting ready${".".repeat(dotCount)}"
                else -> "Processing${".".repeat(dotCount)}"
            }
            Text(mainText, color = sc, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
            Spacer(Modifier.height(4.dp))
            val subText = when {
                isSearching -> "Scanning the area for the nearest available driver"
                isDispatched -> "Your driver is preparing for pickup"
                else -> "Please wait a moment"
            }
            Text(subText, color = Gray500, fontSize = 11.sp, textAlign = TextAlign.Center)
            if (isSearching) {
                Spacer(Modifier.height(10.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                    repeat(3) { idx ->
                        Box(modifier = Modifier.padding(horizontal = 3.dp).size(6.dp).clip(CircleShape).background(if (idx <= (dotCount - 1) % 3) sc else Gray700))
                    }
                }
            }
        }
    }
}

