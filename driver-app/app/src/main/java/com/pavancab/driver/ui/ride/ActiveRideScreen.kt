package com.pavancab.driver.ui.ride

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.pavancab.driver.data.Repository
import com.pavancab.driver.model.Booking
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun ActiveRideScreen(
    booking: Booking,
    repo: Repository,
    onBack: () -> Unit,
    onStatusChanged: () -> Unit,
    onSubscription: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var loading by remember { mutableStateOf(false) }
    var errorText by remember { mutableStateOf("") }
    var showCompleteDialog by remember { mutableStateOf(false) }
    var statusText by remember { mutableStateOf(booking.status) }
    // Rate passenger flow
    var inlineRating by remember { mutableIntStateOf(0) }
    var inlineReview by remember { mutableStateOf("") }
    var submittingRating by remember { mutableStateOf(false) }
    var ratingSubmitted by remember { mutableStateOf(false) }
    val teamPhone = "+918180951176"

    // Live auto-refresh — reflect dispatch freeze/release or status edits without reopening the app
    LaunchedEffect(booking.id) {
        while (true) {
            delay(10000)
            try {
                val live = repo.getBookingDetail(booking.id)
                if (live != null && live.status != statusText) {
                    statusText = live.status
                    if (live.status == "COMPLETED") onStatusChanged()
                }
            } catch (_: Exception) {}
        }
    }

    val tripStatuses = listOf("ASSIGNED", "ACCEPTED", "IN_TRANSIT", "COMPLETED")
    val currentIndex = tripStatuses.indexOf(statusText).coerceAtLeast(0)
    val nextAction = when (statusText) {
        "ASSIGNED" -> "START TRIP"
        "ACCEPTED" -> "START TRIP"
        "IN_TRANSIT" -> "COMPLETE RIDE"
        else -> null
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        // Top bar
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) }
            Text("ACTIVE RIDE", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f))
            StatusBadge(statusText)
        }
        Spacer(Modifier.height(16.dp))

        // Status progress
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            tripStatuses.forEachIndexed { i, s ->
                Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.weight(1f)) {
                    Box(modifier = Modifier.size(28.dp).clip(RoundedCornerShape(14.dp)).then(
                        if (i <= currentIndex) Modifier else Modifier
                    ), contentAlignment = Alignment.Center) {
                        Surface(modifier = Modifier.size(28.dp), shape = RoundedCornerShape(14.dp), color = if (i <= currentIndex) Gold else Gray700) {
                            Box(contentAlignment = Alignment.Center) {
                                if (i < currentIndex) {
                                    Icon(Icons.Default.Check, null, tint = DarkBg, modifier = Modifier.size(16.dp))
                                } else {
                                    Text("${i+1}", color = if (i <= currentIndex) DarkBg else Gray400, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(4.dp))
                    Text(s.replace('_', ' '), color = if (i <= currentIndex) Gold else Gray500, fontSize = 8.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                }
                if (i < tripStatuses.size - 1) {
                    Box(modifier = Modifier.padding(top = 10.dp).height(2.dp).weight(0.5f).clip(RoundedCornerShape(1.dp)).background(if (i < currentIndex) Gold else Gray700))
                }
            }
        }

        Spacer(Modifier.height(20.dp))

        // Booking ref
        Text(booking.bookingRef, color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Black)
        Spacer(Modifier.height(12.dp))

        // Customer info card
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text("CUSTOMER", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text(booking.customerName, color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                Text(booking.customerPhone, color = Gray300, fontSize = 13.sp)
            }
        }
        Spacer(Modifier.height(12.dp))

        // Pickup → Drop card
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(12.dp).clip(RoundedCornerShape(6.dp)).background(Emerald))
                    Spacer(Modifier.width(10.dp))
                    Text("PICKUP", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                }
                Text(booking.pickupLocation, color = White, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.padding(start = 22.dp))
                Spacer(Modifier.height(12.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(12.dp).clip(RoundedCornerShape(6.dp)).background(Red))
                    Spacer(Modifier.width(10.dp))
                    Text("DROP", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                }
                Text(booking.dropLocation, color = White, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.padding(start = 22.dp))
            }
        }
        Spacer(Modifier.height(12.dp))

        // Fare + Cab
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("CAB", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    Text(displayCabType(booking.cabType), color = White, fontSize = 14.sp, fontWeight = FontWeight.Medium)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("FARE", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    Text(fmt(booking.totalFare), color = Gold, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    if (booking.userOfferedFare > 0) {
                        Text("Passenger offered: \u20B9${booking.userOfferedFare.toInt()}", color = Cyan, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                    if (booking.baseFare > 0 && booking.baseFare != booking.userOfferedFare && booking.baseFare != booking.totalFare) {
                        Text("Route price: \u20B9${booking.baseFare.toInt()}", color = Gray500, fontSize = 10.sp)
                    }
                }
            }
        }

        // Special notes
        if (booking.specialNotes.isNotBlank()) {
            Spacer(Modifier.height(12.dp))
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Gold.copy(alpha = 0.08f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("SPECIAL NOTES", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text(booking.specialNotes, color = Gray300, fontSize = 12.sp)
                }
            }
        }

        // User rating card
        if (booking.userRating > 0) {
            Spacer(Modifier.height(12.dp))
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = Emerald.copy(alpha = 0.08f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.2f))) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text("CUSTOMER RATING", color = Emerald, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(6.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("${booking.userRating}/5", color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    }
                    if (booking.userReview.isNotBlank()) {
                        Spacer(Modifier.height(4.dp))
                        Text("\"${booking.userReview}\"", color = Gray300, fontSize = 12.sp, fontStyle = FontStyle.Italic)
                    }
                }
            }
        }

        if (errorText.isNotEmpty()) {
            Spacer(Modifier.height(8.dp))
            if (errorText.contains("payment_required") || errorText.contains("commission")) {
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { onSubscription() }, color = Gold.copy(alpha = 0.1f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                    Column(modifier = Modifier.padding(12.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Payment, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("PAYMENT REQUIRED", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text(errorText.removePrefix("error:").trim(), color = Gray300, fontSize = 12.sp)
                        Spacer(Modifier.height(6.dp))
                        Text("Tap here to pay \u2192", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            } else {
                Text(errorText, color = Red, fontSize = 12.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
            }
        }

        // Rate passenger — after ride completed, once only
        if (statusText == "COMPLETED" && booking.passengerRating == 0 && !ratingSubmitted) {
            Spacer(Modifier.height(16.dp))
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f))) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text("RATE PASSENGER", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                    Spacer(Modifier.height(4.dp))
                    Text("How was ${booking.customerName}?", color = Gray400, fontSize = 12.sp)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                        repeat(5) { i ->
                            IconButton(onClick = { inlineRating = i + 1 }, enabled = !submittingRating, modifier = Modifier.size(38.dp)) {
                                Icon(if (i < inlineRating) Icons.Default.Star else Icons.Default.StarBorder, null, tint = if (i < inlineRating) Gold else Gray600, modifier = Modifier.size(28.dp))
                            }
                        }
                    }
                    OutlinedTextField(
                        value = inlineReview,
                        onValueChange = { if (it.length <= 300) inlineReview = it },
                        placeholder = { Text("Review (optional)", color = Gray600, fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(8.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter, cursorColor = Gold)
                    )
                    Spacer(Modifier.height(10.dp))
                    Button(
                        onClick = {
                            if (inlineRating > 0) {
                                submittingRating = true
                                scope.launch {
                                    try {
                                        val r = repo.ratePassenger(booking.id, inlineRating, inlineReview)
                                        if (r.get("success")?.asBoolean == true) ratingSubmitted = true
                                        else errorText = r.get("error")?.asString ?: "Failed to submit"
                                    } catch (e: Exception) {
                                        errorText = "Failed: ${e.message}"
                                    }
                                    submittingRating = false
                                }
                            }
                        },
                        enabled = inlineRating > 0 && !submittingRating,
                        modifier = Modifier.fillMaxWidth().height(42.dp),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                    ) {
                        if (submittingRating) {
                            CircularProgressIndicator(modifier = Modifier.size(16.dp), color = DarkBg, strokeWidth = 2.dp)
                        } else {
                            Icon(Icons.Default.Star, null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("SUBMIT RATING", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
        if (statusText == "COMPLETED" && (booking.passengerRating > 0 || ratingSubmitted)) {
            Spacer(Modifier.height(12.dp))
            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.08f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.25f))) {
                Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.ThumbUp, null, tint = Emerald, modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Thanks for completing this ride!", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(Modifier.height(20.dp))

        // Action buttons row
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            // Call customer
            Surface(
                modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable {
                    val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:${booking.customerPhone}"))
                    context.startActivity(intent)
                },
                shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.12f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))
            ) {
                Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.Default.Phone, null, tint = Emerald, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("CALL", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
            // WhatsApp customer
            Surface(
                modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable {
                    try {
                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${booking.customerPhone.replace("+", "").replace(" ", "")}"))
                        context.startActivity(intent)
                    } catch (_: Exception) {}
                },
                shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.12f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))
            ) {
                Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.Default.Chat, null, tint = Emerald, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("WHATSAPP", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(Modifier.height(8.dp))

        // Call team
        Surface(
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable {
                val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$teamPhone"))
                context.startActivity(intent)
            },
            shape = RoundedCornerShape(12.dp), color = Blue.copy(alpha = 0.12f), border = BorderStroke(1.dp, Blue.copy(alpha = 0.3f))
        ) {
            Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                Icon(Icons.Default.Headset, null, tint = Blue, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
                Text("CALL DISPATCH", color = Blue, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }

        // Primary action button
        if (nextAction != null) {
            Spacer(Modifier.height(16.dp))
            val actionColor = when (nextAction) {
                "START TRIP" -> Blue
                "COMPLETE RIDE" -> Emerald
                else -> Gold
            }
            GradientButton(
                onClick = {
                    if (nextAction == "COMPLETE RIDE") {
                        showCompleteDialog = true
                    } else {
                        loading = true
                        errorText = ""
                        scope.launch {
                            val newStatus = if (statusText == "ASSIGNED" || statusText == "ACCEPTED") "IN_TRANSIT" else "COMPLETED"
                            val result = repo.updateTripStatus(booking.id, newStatus)
                            loading = false
                            if (result.get("success")?.asBoolean == true) {
                                statusText = newStatus
                                onStatusChanged()
                            } else {
                                errorText = result.get("error")?.asString ?: "Failed to update"
                            }
                        }
                    }
                },
                text = nextAction,
                icon = if (nextAction == "START TRIP") Icons.Default.PlayArrow else Icons.Default.CheckCircle,
                color = actionColor
            )

            // Secondary: complete trip directly without starting (e.g. short/cancelled-on-spot rides)
            if (statusText == "ASSIGNED" || statusText == "ACCEPTED") {
                Spacer(Modifier.height(8.dp))
                OutlinedButton(
                    onClick = { showCompleteDialog = true },
                    enabled = !loading,
                    modifier = Modifier.fillMaxWidth().height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, Emerald.copy(alpha = 0.55f)),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Emerald)
                ) {
                    Icon(Icons.Default.CheckCircle, null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(6.dp))
                    Text("COMPLETE TRIP DIRECTLY", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }

    if (loading) LoadingOverlay("Updating...")

    // Complete ride confirmation dialog
    if (showCompleteDialog) {
        AlertDialog(
            onDismissRequest = { showCompleteDialog = false },
            containerColor = DarkBgLighter,
            title = { Text("Complete Ride?", color = White, fontWeight = FontWeight.Bold) },
            text = { Text("Confirm that you have dropped off ${booking.customerName} at ${booking.dropLocation}.", color = Gray300, fontSize = 14.sp) },
            confirmButton = {
                Button(onClick = {
                    showCompleteDialog = false
                    loading = true; errorText = ""
                    scope.launch {
                        val result = repo.updateTripStatus(booking.id, "COMPLETED")
                        loading = false
                        if (result.get("success")?.asBoolean == true) {
                            statusText = "COMPLETED"
                            onStatusChanged()
                        } else {
                            errorText = result.get("error")?.asString ?: "Failed"
                        }
                    }
                }, colors = ButtonDefaults.buttonColors(containerColor = Emerald), shape = RoundedCornerShape(10.dp)) {
                    Text("CONFIRM", color = DarkBg, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showCompleteDialog = false }) { Text("CANCEL", color = Gray400) }
            }
        )
    }
}
