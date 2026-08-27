package com.pavancab.niranjan.ui.booking

import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.pavancab.niranjan.CrashLogger
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.network.ApiClient
import com.pavancab.niranjan.ui.components.*
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingConfirmScreen(
    tripType: String, pickup: String, drop: String,
    duration: String, cabType: String, fare: Double,
    onBookingDone: () -> Unit, onBack: () -> Unit
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }

    var userName by rememberSaveable { mutableStateOf("") }
    var userPhone by rememberSaveable { mutableStateOf("") }
    var userEmail by rememberSaveable { mutableStateOf("") }
    var notes by rememberSaveable { mutableStateOf("") }
    var isLoading by remember { mutableStateOf(false) }

    // Duplicate-submission guard — one in-flight booking per screen instance
    var submitted by rememberSaveable { mutableStateOf(false) }

    // Success state — full confirmation screen instead of a transient toast
    var successRef by rememberSaveable { mutableStateOf("") }
    var successDate by rememberSaveable { mutableStateOf("") }
    var successTime by rememberSaveable { mutableStateOf("") }
    var successModeNow by rememberSaveable { mutableStateOf(true) }

    val indianDateFormat = remember { SimpleDateFormat("dd-MM-yyyy", Locale.getDefault()) }
    val internalDateFormat = remember { SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()) }
    val indianTimeFormat = remember { SimpleDateFormat("hh:mm a", Locale.getDefault()) }
    val internalTimeFormat = remember { SimpleDateFormat("HH:mm", Locale.getDefault()) }

    var rideMode by rememberSaveable { mutableStateOf("now") }
    var selectedDateDisplay by rememberSaveable { mutableStateOf("") }
    var selectedTimeDisplay by rememberSaveable { mutableStateOf("") }
    var selectedDateInternal by remember { mutableStateOf("") }
    var selectedTimeInternal by remember { mutableStateOf("") }
    var showDatePickerDialog by remember { mutableStateOf(false) }
    var showTimePickerDialog by remember { mutableStateOf(false) }

    // Night allowance: rides starting 10 PM – 6 AM carry a one-time driver night charge
    val NIGHT_RATE = 500
    fun isNightPickup(): Boolean {
        if (selectedDateInternal.isBlank() || selectedTimeInternal.isBlank()) return false
        return try {
            val dt = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).parse("$selectedDateInternal $selectedTimeInternal")
            val cal = Calendar.getInstance().apply { if (dt != null) time = dt }
            val h = cal.get(Calendar.HOUR_OF_DAY)
            h >= 22 || h < 6
        } catch (_: Exception) { false }
    }
    val nightApplicable = isNightPickup()
    val totalFare: Double = fare + (if (nightApplicable) NIGHT_RATE.toDouble() else 0.0)
    val baseFare = fare
    var showOfferInput by rememberSaveable { mutableStateOf(false) }
    var offerAmountText by rememberSaveable { mutableStateOf("") }
    val offerAmount = offerAmountText.toDoubleOrNull() ?: 0.0
    val isUserOffer = tripType == "One Way" && showOfferInput && offerAmount >= 100
    val effectiveFare = if (isUserOffer) offerAmount else totalFare

    // Book for someone else
    var bookingForOther by rememberSaveable { mutableStateOf(false) }
    var otherName by rememberSaveable { mutableStateOf("") }
    var otherPhone by rememberSaveable { mutableStateOf("") }

    LaunchedEffect(Unit) {
        userName = UserPrefs.getName(context)
        userPhone = UserPrefs.getPhone(context)
        userEmail = UserPrefs.getEmail(context)
        otherName = userName
        otherPhone = userPhone
        val now = Calendar.getInstance()
        selectedDateInternal = internalDateFormat.format(now.time)
        selectedTimeInternal = internalTimeFormat.format(now.time)
        selectedDateDisplay = indianDateFormat.format(now.time)
        selectedTimeDisplay = indianTimeFormat.format(now.time)
    }

    if (showDatePickerDialog) {
        val datePickerState = rememberDatePickerState()
        DatePickerDialog(
            onDismissRequest = { showDatePickerDialog = false },
            confirmButton = {
                TextButton(onClick = {
                    datePickerState.selectedDateMillis?.let { millis ->
                        val cal = Calendar.getInstance().apply { timeInMillis = millis }
                        selectedDateInternal = internalDateFormat.format(cal.time)
                        selectedDateDisplay = indianDateFormat.format(cal.time)
                        if (rideMode == "now") {
                            val now = Calendar.getInstance()
                            selectedTimeInternal = internalTimeFormat.format(now.time)
                            selectedTimeDisplay = indianTimeFormat.format(now.time)
                        }
                    }
                    showDatePickerDialog = false
                }) { Text("OK", color = Gold) }
            },
            dismissButton = {
                TextButton(onClick = { showDatePickerDialog = false }) { Text("Cancel", color = Gray400) }
            }
        ) {
            DatePicker(state = datePickerState, colors = DatePickerDefaults.colors(
                containerColor = DarkBg,
                selectedDayContainerColor = Gold,
                selectedDayContentColor = DarkBg
            ))
        }
    }

    if (showTimePickerDialog) {
        val cal = Calendar.getInstance()
        val timePickerState = rememberTimePickerState(
            initialHour = cal.get(Calendar.HOUR_OF_DAY),
            initialMinute = cal.get(Calendar.MINUTE),
            is24Hour = false
        )
        AlertDialog(
            onDismissRequest = { showTimePickerDialog = false },
            containerColor = DarkBg,
            title = { Text("Select Time", color = White, fontWeight = FontWeight.Bold) },
            text = {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    TimePicker(state = timePickerState, colors = TimePickerDefaults.colors(
                        selectorColor = Gold,
                        containerColor = DarkBg
                    ))
                    Spacer(Modifier.height(8.dp))
                    val h = timePickerState.hour
                    val m = timePickerState.minute
                    val preview = Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, h); set(Calendar.MINUTE, m) }
                    Text(indianTimeFormat.format(preview.time), color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    val h = timePickerState.hour
                    val m = timePickerState.minute
                    val preview = Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, h); set(Calendar.MINUTE, m) }
                    selectedTimeInternal = internalTimeFormat.format(preview.time)
                    selectedTimeDisplay = indianTimeFormat.format(preview.time)
                    showTimePickerDialog = false
                }) { Text("OK", color = Gold) }
            },
            dismissButton = {
                TextButton(onClick = { showTimePickerDialog = false }) { Text("Cancel", color = Gray400) }
            }
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Confirm Booking", fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg, titleContentColor = White)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize()) {
            if (successRef.isNotEmpty()) {
                // Auto-redirect to My Rides after 5 seconds if user doesn't navigate manually
                LaunchedEffect(successRef) {
                    kotlinx.coroutines.delay(5000)
                    onBookingDone()
                }
                // ============ RIDE PLACED — full success screen ============
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding)
                        .verticalScroll(rememberScrollState())
                        .padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Spacer(Modifier.height(24.dp))
                    Box(
                        modifier = Modifier.size(96.dp).clip(RoundedCornerShape(28.dp)).background(Emerald.copy(alpha = 0.12f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(56.dp))
                    }
                    Spacer(Modifier.height(16.dp))
                    Text("Ride Placed!", color = Emerald, fontSize = 22.sp, fontWeight = FontWeight.Black)
                    Text("Your ride has been placed successfully", color = Gray400, fontSize = 13.sp)
                    Spacer(Modifier.height(18.dp))

                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f))) {
                        Column(modifier = Modifier.padding(16.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("BOOKING REFERENCE", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                            Spacer(Modifier.height(6.dp))
                            Text(successRef, color = Gold, fontSize = 24.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                            Spacer(Modifier.height(8.dp))
                            Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable {
                                runCatching {
                                    val cm = context.getSystemService(Context.CLIPBOARD_SERVICE) as ClipboardManager
                                    cm.setPrimaryClip(ClipData.newPlainText("PavanCab Booking Ref", successRef))
                                    Toast.makeText(context, "Reference copied", Toast.LENGTH_SHORT).show()
                                }
                            }, shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.15f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                                Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.ContentCopy, null, tint = Gold, modifier = Modifier.size(13.dp))
                                    Spacer(Modifier.width(5.dp))
                                    Text("COPY REFERENCE", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }
                    }

                    Spacer(Modifier.height(14.dp))
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            InfoRow(label = "Trip Type", value = formatTripType(tripType), icon = Icons.Default.TripOrigin)
                            InfoRow(label = "Pickup", value = pickup, icon = Icons.Default.MyLocation)
                            InfoRow(label = "Drop", value = drop.ifEmpty { duration }, icon = Icons.Default.LocationOn)
                            InfoRow(label = "Cab", value = cabType, icon = Icons.Default.DirectionsCar)
                            InfoRow(
                                label = "When",
                                value = if (successModeNow) "Today at $successTime" else "$successDate at $successTime",
                                icon = Icons.Default.Schedule
                            )
                            InfoRow(label = if (isUserOffer) "Your Offered Fare" else "Fare", value = "\u20B9${effectiveFare.toInt()}", icon = Icons.Default.AttachMoney, valueColor = if (isUserOffer) Color(0xFF22D3EE) else Gold)
                        }
                    }

                    Spacer(Modifier.height(14.dp))
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = GoldSurface) {
                        Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.NotificationsActive, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(10.dp))
                            Text(
                                "You'll get a notification as soon as your driver is assigned.",
                                color = Gold, fontSize = 12.sp, lineHeight = 17.sp
                            )
                        }
                    }

                    Spacer(Modifier.height(22.dp))
                    GradientButton(onClick = onBookingDone, text = "VIEW MY RIDES", icon = Icons.Default.DirectionsCar)
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(
                        onClick = {
                            // Reset for a fresh booking
                            successRef = ""; notes = ""
                            rideMode = "now"; submitted = false
                            onBack()
                        },
                        modifier = Modifier.fillMaxWidth().height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)
                    ) {
                        Icon(Icons.Default.Add, null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(6.dp))
                        Text("BOOK ANOTHER RIDE", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    }
                    Spacer(Modifier.height(14.dp))
                    Text("Redirecting to My Rides in a few seconds\u2026", color = Gray600, fontSize = 10.sp)
                    Spacer(Modifier.height(24.dp))
                }
            } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .verticalScroll(rememberScrollState())
                    .padding(16.dp)
            ) {
                PavanCard {
                    SectionHeader(title = "Booking Summary", subtitle = "Review your ride details")
                    Spacer(Modifier.height(8.dp))
                    InfoRow(label = "Trip Type", value = formatTripType(tripType), icon = Icons.Default.TripOrigin)
                    InfoRow(label = "Pickup", value = pickup, icon = Icons.Default.MyLocation)
                    InfoRow(label = "Drop", value = drop.ifEmpty { duration }, icon = Icons.Default.LocationOn)
                    if (duration.isNotEmpty() && tripType != "One Way") InfoRow(label = "Duration", value = duration, icon = Icons.Default.Schedule)
                    if (duration.isNotEmpty() && tripType == "One Way") InfoRow(label = "Approx Distance", value = duration, icon = Icons.Default.Straighten)
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp), color = DividerColor)
                    InfoRow(label = "Cab Type", value = cabType, icon = Icons.Default.DirectionsCar)
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp), color = DividerColor)
                    // Itemized fare breakdown
                    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
                        Text("Base fare", color = Gray400, fontSize = 12.sp, modifier = Modifier.weight(1f))
                        Text("\u20B9${fare.toInt()}", color = White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    }
                    if (nightApplicable) {
                        Row(modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
                            Text("Night allowance (10 PM \u2013 6 AM)", color = Color(0xFFF59E0B), fontSize = 12.sp, modifier = Modifier.weight(1f))
                            Text("+\u20B9$NIGHT_RATE", color = Color(0xFFF59E0B), fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                    if (tripType == "One Way") {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp)
                                .clip(RoundedCornerShape(8.dp)).clickable { showOfferInput = !showOfferInput }
                                .padding(horizontal = 4.dp, vertical = 2.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                if (showOfferInput) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(Modifier.width(4.dp))
                            Text(
                                if (showOfferInput) "Hide fare offer" else "Want to offer your own fare?",
                                color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Medium
                            )
                        }
                        if (showOfferInput) {
                            Spacer(Modifier.height(6.dp))
                            OutlinedTextField(
                                value = offerAmountText,
                                onValueChange = { v -> offerAmountText = v.filter { it.isDigit() }.take(6) },
                                label = { Text("Your Offered Fare (\u20B9)", color = Gray400, fontSize = 12.sp) },
                                placeholder = { Text("Min \u20B9100", color = Gray600, fontSize = 12.sp) },
                                leadingIcon = { Icon(Icons.Default.AttachMoney, null, tint = Gold, modifier = Modifier.size(18.dp)) },
                                singleLine = true,
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = CardBorder, focusedTextColor = White, unfocusedTextColor = White),
                                modifier = Modifier.fillMaxWidth().height(52.dp)
                            )
                            if (offerAmount in 1.0..99.0) {
                                Text("Minimum offer is \u20B9100", color = Red, fontSize = 10.sp, modifier = Modifier.padding(top = 2.dp))
                            }
                        }
                    }
                    HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp), color = DividerColor)
                    if (isUserOffer) {
                        Row(modifier = Modifier.fillMaxWidth().padding(top = 5.dp)) {
                            Text("Your Offered Fare", color = Color(0xFF22D3EE), fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                            Text("\u20B9${effectiveFare.toInt()}", color = Color(0xFF22D3EE), fontSize = 18.sp, fontWeight = FontWeight.Black)
                        }
                        Row(modifier = Modifier.fillMaxWidth().padding(top = 2.dp)) {
                            Text("Base fare \u20B9${fare.toInt()}", color = Gray500, fontSize = 10.sp)
                        }
                    } else {
                        Row(modifier = Modifier.fillMaxWidth().padding(top = 5.dp)) {
                            Text("Total Estimated Fare", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                            Text("\u20B9${totalFare.toInt()}", color = Gold, fontSize = 18.sp, fontWeight = FontWeight.Black)
                        }
                    }
                    if (nightApplicable) {
                        Spacer(Modifier.height(4.dp))
                        Text("\u2139 Night allowance covers driver's late-night return — already included above, nothing extra to pay the driver.", color = Gray500, fontSize = 10.sp, lineHeight = 13.sp)
                    } else if (tripType == "Hourly") {
                        Spacer(Modifier.height(4.dp))
                        Text("\u2139 Pickup after 10 PM adds a one-time \u20B9$NIGHT_RATE night allowance to this fare.", color = Gray600, fontSize = 10.sp)
                    }
                }

                Spacer(Modifier.height(16.dp))

                PavanCard {
                    SectionHeader(title = "When do you need the ride?")
                    Spacer(Modifier.height(8.dp))

                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Surface(
                            modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable { rideMode = "now" },
                            shape = RoundedCornerShape(12.dp),
                            color = if (rideMode == "now") Gold else CardBg,
                            border = BorderStroke(1.dp, if (rideMode == "now") Gold else CardBorder)
                        ) {
                            Row(
                                modifier = Modifier.padding(vertical = 14.dp, horizontal = 12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.Center
                            ) {
                                Icon(Icons.Default.Bolt, null, tint = if (rideMode == "now") DarkBg else Gray400, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Ride Now", fontWeight = FontWeight.Black, fontSize = 13.sp, color = if (rideMode == "now") DarkBg else Gray400)
                            }
                        }

                        Surface(
                            modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable { rideMode = "later" },
                            shape = RoundedCornerShape(12.dp),
                            color = if (rideMode == "later") Gold else CardBg,
                            border = BorderStroke(1.dp, if (rideMode == "later") Gold else CardBorder)
                        ) {
                            Row(
                                modifier = Modifier.padding(vertical = 14.dp, horizontal = 12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.Center
                            ) {
                                Icon(Icons.Default.Schedule, null, tint = if (rideMode == "later") DarkBg else Gray400, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Schedule", fontWeight = FontWeight.Black, fontSize = 13.sp, color = if (rideMode == "later") DarkBg else Gray400)
                            }
                        }
                    }

                    if (rideMode == "now") {
                        Spacer(Modifier.height(12.dp))
                        Surface(shape = RoundedCornerShape(10.dp), color = GoldSurface) {
                            Row(
                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.Info, null, tint = Gold, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Ride Now — driver will be assigned immediately", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                            }
                        }
                        Spacer(Modifier.height(10.dp))
                        InfoRow(label = "Ride Time", value = selectedTimeDisplay, icon = Icons.Default.Schedule)
                    } else {
                        Spacer(Modifier.height(12.dp))

                        Surface(
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { showDatePickerDialog = true },
                            shape = RoundedCornerShape(12.dp),
                            color = CardBg,
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 14.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.CalendarToday, null, tint = Gold, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(12.dp))
                                Column(modifier = Modifier.weight(1f)) {
                                    Text("Ride Date", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                                    Spacer(Modifier.height(2.dp))
                                    Text(
                                        if (selectedDateDisplay.isNotEmpty()) selectedDateDisplay else "Select Date",
                                        color = White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold
                                    )
                                }
                                Icon(Icons.Default.ChevronRight, null, tint = Gray500, modifier = Modifier.size(20.dp))
                            }
                        }

                        Spacer(Modifier.height(10.dp))

                        Surface(
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { showTimePickerDialog = true },
                            shape = RoundedCornerShape(12.dp),
                            color = CardBg,
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 14.dp, vertical = 14.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(Icons.Default.AccessTime, null, tint = Gold, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(12.dp))
                                Column(modifier = Modifier.weight(1f)) {
                                    Text("Pickup Time", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                                    Spacer(Modifier.height(2.dp))
                                    Text(
                                        if (selectedTimeDisplay.isNotEmpty()) selectedTimeDisplay else "Select Time",
                                        color = White, fontSize = 14.sp, fontWeight = FontWeight.SemiBold
                                    )
                                }
                                Icon(Icons.Default.ChevronRight, null, tint = Gray500, modifier = Modifier.size(20.dp))
                            }
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))

                PavanCard {
                    SectionHeader(title = "Passenger Details")
                    Spacer(Modifier.height(8.dp))
                    PavanTextField(value = userName, onValueChange = { userName = it }, label = "Full Name", leadingIcon = Icons.Default.Person)
                    Spacer(Modifier.height(12.dp))
                    PavanTextField(value = userPhone, onValueChange = {}, label = "Phone Number", leadingIcon = Icons.Default.Phone, readOnly = true)
                    Spacer(Modifier.height(12.dp))
                    PavanTextField(value = userEmail, onValueChange = { userEmail = it }, label = "Email (Optional)", leadingIcon = Icons.Default.Email)
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(
                        value = notes, onValueChange = { notes = it },
                        label = { Text("Special Notes / Flight Number", fontSize = 13.sp) },
                        modifier = Modifier.fillMaxWidth(), minLines = 2, maxLines = 3,
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold, unfocusedBorderColor = Gray700,
                            focusedContainerColor = CardBg, unfocusedContainerColor = CardBg,
                            focusedTextColor = White, unfocusedTextColor = White,
                            focusedLabelColor = Gold, unfocusedLabelColor = Gray400
                        )
                    )
                }

                Spacer(Modifier.height(16.dp))

                // Book for someone else
                PavanCard {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.PersonOutline, null, tint = Blue, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Column(Modifier.weight(1f)) {
                            Text("Booking for someone else?", color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            Text("Add the actual passenger's details", color = Gray500, fontSize = 10.sp)
                        }
                        Switch(checked = bookingForOther, onCheckedChange = { bookingForOther = it }, colors = SwitchDefaults.colors(checkedTrackColor = Gold, checkedThumbColor = DarkBg))
                    }
                    if (bookingForOther) {
                        Spacer(Modifier.height(8.dp))
                        OutlinedTextField(value = otherName, onValueChange = { otherName = it }, label = { Text("Passenger Name *") }, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(10.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                        Spacer(Modifier.height(6.dp))
                        OutlinedTextField(
                            value = otherPhone, onValueChange = { otherPhone = it.filter { c -> c.isDigit() }.take(12) },
                            label = { Text("Passenger WhatsApp Number *") }, modifier = Modifier.fillMaxWidth(), singleLine = true,
                            leadingIcon = { Text("+91", color = Gray400) },
                            shape = RoundedCornerShape(10.dp),
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold)
                        )
                    }
                }

                Spacer(Modifier.height(16.dp))

                GradientButton(
                    onClick = {
                        if (submitted || isLoading) return@GradientButton
                        if (userName.isBlank()) { Toast.makeText(context, "Please enter your name", Toast.LENGTH_SHORT).show(); return@GradientButton }
                        if (fare <= 0) { Toast.makeText(context, "Invalid fare amount", Toast.LENGTH_SHORT).show(); return@GradientButton }
                        if (bookingForOther && (otherName.isBlank() || otherPhone.length < 10)) { Toast.makeText(context, "Enter passenger name and valid phone number", Toast.LENGTH_SHORT).show(); return@GradientButton }
                        if (rideMode == "later" && (selectedDateInternal.isEmpty() || selectedTimeInternal.isEmpty())) {
                            Toast.makeText(context, "Please select ride date and time", Toast.LENGTH_SHORT).show(); return@GradientButton
                        }
                        if (rideMode == "later") {
                            try {
                                val pickupMillis = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).parse("$selectedDateInternal $selectedTimeInternal")?.time
                                if (pickupMillis != null && pickupMillis < System.currentTimeMillis()) {
                                    Toast.makeText(context, "Pickup date and time cannot be in the past", Toast.LENGTH_LONG).show(); return@GradientButton
                                }
                            } catch (_: Exception) {}
                        }

                        isLoading = true
                        scope.launch {
                            try {
                                val date = if (rideMode == "later") selectedDateInternal else {
                                    val cal = Calendar.getInstance()
                                    String.format("%04d-%02d-%02d", cal.get(Calendar.YEAR), cal.get(Calendar.MONTH) + 1, cal.get(Calendar.DAY_OF_MONTH))
                                }
                                val time = if (rideMode == "later") selectedTimeInternal else {
                                    val cal = Calendar.getInstance()
                                    String.format("%02d:%02d", cal.get(Calendar.HOUR_OF_DAY), cal.get(Calendar.MINUTE))
                                }
                                val modeNow = rideMode == "now"
                                val displayDate = if (modeNow) indianDateFormat.format(Calendar.getInstance().time) else selectedDateDisplay
                                val displayTime = if (modeNow) indianTimeFormat.format(Calendar.getInstance().time) else selectedTimeDisplay

                                var finalNotes = notes
                                if (nightApplicable) finalNotes += "\n[NIGHT] Fare includes \u20B9$NIGHT_RATE night allowance (10PM-6AM pickup)"
                                if (bookingForOther) finalNotes += "\n[Booked for: $otherName ($otherPhone) by account holder $userName]"
                                val paxName = if (bookingForOther) otherName else userName
                                val paxPhone = if (bookingForOther) otherPhone else userPhone

                                val result = repo.createBooking(
                                    name = paxName, phone = paxPhone, email = userEmail,
                                    tripType = tripType, pickup = pickup, drop = drop,
                                    date = date, time = time,
                                    cabType = cabType, fare = effectiveFare, notes = finalNotes,
                                    baseFare = baseFare, fareOffered = if (isUserOffer) effectiveFare else 0.0
                                )

                                isLoading = false
                                if (result.has("success") && result.get("success").asBoolean) {
                                    val ref = result.get("booking_ref")?.let { if (!it.isJsonNull) it.asString else "" } ?: ""
                                    CrashLogger.log("BOOKING", "Created: $ref mode=$rideMode", "BookingConfirm")
                                    submitted = true
                                    successModeNow = modeNow
                                    successDate = displayDate
                                    successTime = displayTime
                                    successRef = ref.ifBlank { "PLACED" }
                                } else {
                                    submitted = false
                                    val err = result.get("error")?.let { if (!it.isJsonNull) it.asString else null }
                                        ?: result.get("message")?.let { if (!it.isJsonNull) it.asString else null }
                                        ?: "Booking failed — please try again"
                                    Toast.makeText(context, err, Toast.LENGTH_LONG).show()
                                }
                            } catch (e: Exception) {
                                isLoading = false
                                submitted = false
                                Toast.makeText(context, "Error: ${ApiClient.humanError(e)}", Toast.LENGTH_LONG).show()
                                CrashLogger.log("ERROR", "Booking failed: ${e.message}", "BookingConfirm", e)
                            }
                        }
                    },
                    text = if (isLoading) "BOOKING..." else "CONFIRM & BOOK",
                    icon = Icons.Default.CheckCircle,
                    enabled = !isLoading && !submitted
                )

                Spacer(Modifier.height(24.dp))
            }
            }

            if (isLoading) LoadingOverlay("Booking your ride...")
        }
    }
}

private fun formatTripType(type: String): String = when (type) {
    "Hourly" -> "Hourly Rental"
    "Tour", "Sightseeing" -> "Sightseeing Tour"
    else -> "One-Way Drop"
}
