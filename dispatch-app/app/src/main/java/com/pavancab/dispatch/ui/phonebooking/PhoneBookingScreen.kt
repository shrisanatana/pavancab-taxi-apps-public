package com.pavancab.dispatch.ui.phonebooking

import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

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
fun PhoneBookingScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }

    var customerName by remember { mutableStateOf("") }
    var customerPhone by remember { mutableStateOf("") }
    var customerCountryCode by remember { mutableStateOf("+91") }
    var pickupLocation by remember { mutableStateOf("") }
    var dropLocation by remember { mutableStateOf("") }
    var pickupDate by remember { mutableStateOf("") }
    var pickupTime by remember { mutableStateOf("") }
    var pickupDateInternal by remember { mutableStateOf("") }
    var pickupTimeInternal by remember { mutableStateOf("") }
    var cabType by remember { mutableStateOf("Sedan") }
    var totalFare by remember { mutableStateOf("") }
    var specialNotes by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var success by remember { mutableStateOf(false) }
    var bookingRef by remember { mutableStateOf("") }
    var dropError by remember { mutableStateOf("") }
    var dateError by remember { mutableStateOf("") }
    var timeError by remember { mutableStateOf("") }

    var tripType by remember { mutableStateOf("One Way") }
    var tripTypeExpanded by remember { mutableStateOf(false) }
    val tripTypes = listOf("One Way", "Round Trip", "Hourly", "Tour")

    var cabTypeExpanded by remember { mutableStateOf(false) }
    val cabTypes = listOf("Sedan", "SUV", "Hatchback", "Tempo Traveller", "Innova", "Luxury")

    var showDatePicker by remember { mutableStateOf(false) }
    var showTimePicker by remember { mutableStateOf(false) }

    val indianDateFormat = remember { SimpleDateFormat("dd-MM-yyyy", Locale.getDefault()) }
    val internalDateFormat = remember { SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()) }
    val indianTimeFormat = remember { SimpleDateFormat("hh:mm a", Locale.getDefault()) }
    val internalTimeFormat = remember { SimpleDateFormat("HH:mm", Locale.getDefault()) }

    if (showDatePicker) {
        val datePickerState = rememberDatePickerState()
        DatePickerDialog(
            onDismissRequest = { showDatePicker = false },
            confirmButton = {
                TextButton(onClick = {
                    datePickerState.selectedDateMillis?.let { millis ->
                        val cal = Calendar.getInstance().apply { timeInMillis = millis }
                        pickupDateInternal = internalDateFormat.format(cal.time)
                        pickupDate = indianDateFormat.format(cal.time)
                        dateError = ""
                    }
                    showDatePicker = false
                }) { Text("OK", color = Gold) }
            },
            dismissButton = {
                TextButton(onClick = { showDatePicker = false }) { Text("Cancel", color = Gray400) }
            }
        ) {
            DatePicker(state = datePickerState, colors = DatePickerDefaults.colors(
                containerColor = DarkBgLighter,
                selectedDayContainerColor = Gold,
                selectedDayContentColor = DarkBg
            ))
        }
    }

    if (showTimePicker) {
        val timePickerState = rememberTimePickerState(initialHour = Calendar.getInstance().get(Calendar.HOUR_OF_DAY), initialMinute = Calendar.getInstance().get(Calendar.MINUTE), is24Hour = false)
        AlertDialog(
            onDismissRequest = { showTimePicker = false },
            containerColor = DarkBgLighter,
            title = { Text("Select Time", color = White, fontWeight = FontWeight.Bold) },
            text = {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    TimePicker(state = timePickerState, colors = TimePickerDefaults.colors(
                        selectorColor = Gold,
                        containerColor = DarkBgLighter
                    ))
                    Spacer(Modifier.height(8.dp))
                    val h = timePickerState.hour
                    val m = timePickerState.minute
                    val cal = Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, h); set(Calendar.MINUTE, m) }
                    Text(indianTimeFormat.format(cal.time), color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    val h = timePickerState.hour
                    val m = timePickerState.minute
                    val cal = Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, h); set(Calendar.MINUTE, m) }
                    pickupTimeInternal = internalTimeFormat.format(cal.time)
                    pickupTime = indianTimeFormat.format(cal.time)
                    timeError = ""
                    showTimePicker = false
                }) { Text("OK", color = Gold) }
            },
            dismissButton = {
                TextButton(onClick = { showTimePicker = false }) { Text("Cancel", color = Gray400) }
            }
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Phone Booking", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        if (success) {
            Box(modifier = Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(64.dp))
                    Spacer(Modifier.height(16.dp))
                    Text("Booking Created!", color = Emerald, fontSize = 20.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(8.dp))
                    Text(bookingRef, color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    Text("Booking created!", color = Gray400, fontSize = 13.sp)
                    Spacer(Modifier.height(24.dp))
                    GradientButton(onClick = {
                        success = false; bookingRef = ""
                        customerName = ""; customerPhone = ""
                        pickupLocation = ""; dropLocation = ""
                        pickupDate = ""; pickupTime = ""
                        pickupDateInternal = ""; pickupTimeInternal = ""
                        tripType = "One Way"
                        cabType = "Sedan"; totalFare = ""; specialNotes = ""
                    }, text = "Create Another Booking", icon = Icons.Default.Add)
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(onClick = onBack, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                        Text("Go Back", color = Gray400)
                    }
                }
            }
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
                contentPadding = PaddingValues(vertical = 12.dp)
            ) {
                item {
                    PavanCard {
                        SectionHeader("Customer Details")
                        Spacer(Modifier.height(8.dp))
                        PavanTextField(value = customerName, onValueChange = { customerName = it }, label = "Customer Name *", leadingIcon = Icons.Default.Person)
                        Spacer(Modifier.height(8.dp))
                        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            CountryCodePicker(selectedCode = customerCountryCode, onCodeSelected = { customerCountryCode = it }, modifier = Modifier.width(100.dp))
                            PavanTextField(
                                value = customerPhone,
                                onValueChange = { if (it.length <= 10 && it.all { c -> c.isDigit() }) customerPhone = it },
                                label = "WhatsApp Number *",
                                leadingIcon = Icons.Default.Phone,
                                modifier = Modifier.weight(1f)
                            )
                        }
                        if (customerPhone.length >= 7) {
                            Spacer(Modifier.height(4.dp))
                            Text("WhatsApp: $customerCountryCode $customerPhone", color = Emerald, fontSize = 11.sp, modifier = Modifier.padding(start = 4.dp))
                        }
                    }
                }

                item {
                    PavanCard {
                        SectionHeader("Trip Details")
                        Spacer(Modifier.height(8.dp))
                        ExposedDropdownMenuBox(expanded = tripTypeExpanded, onExpandedChange = { tripTypeExpanded = !tripTypeExpanded }) {
                            PavanTextField(value = tripType, onValueChange = {}, label = "Trip Type", leadingIcon = Icons.Default.TripOrigin, readOnly = true, modifier = Modifier.fillMaxWidth().menuAnchor())
                            ExposedDropdownMenu(expanded = tripTypeExpanded, onDismissRequest = { tripTypeExpanded = false }) {
                                tripTypes.forEach { t ->
                                    DropdownMenuItem(text = { Text(t, color = White) }, onClick = { tripType = t; tripTypeExpanded = false })
                                }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                        PavanTextField(value = pickupLocation, onValueChange = { pickupLocation = it }, label = "Pickup Location *", leadingIcon = Icons.Default.MyLocation)
                        Spacer(Modifier.height(8.dp))
                        PavanTextField(value = dropLocation, onValueChange = { dropLocation = it; if (dropError.isNotBlank()) dropError = "" }, label = "Drop Location *", leadingIcon = Icons.Default.LocationOn)
                        if (dropError.isNotBlank()) { Spacer(Modifier.height(4.dp)); Text(dropError, color = Red, fontSize = 11.sp, modifier = Modifier.padding(start = 4.dp)) }
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Surface(
                                modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable { showDatePicker = true },
                                shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, if (pickupDate.isNotBlank()) Gold else Gray700)
                            ) {
                                Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.CalendarToday, null, tint = if (pickupDate.isNotBlank()) Gold else Gray500, modifier = Modifier.size(18.dp))
                                    Spacer(Modifier.width(8.dp))
                                    Text(if (pickupDate.isNotBlank()) pickupDate else "Select Date", color = if (pickupDate.isNotBlank()) White else Gray500, fontSize = 13.sp)
                                }
                            }
                            Surface(
                                modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable { showTimePicker = true },
                                shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, if (pickupTime.isNotBlank()) Gold else Gray700)
                            ) {
                                Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Schedule, null, tint = if (pickupTime.isNotBlank()) Gold else Gray500, modifier = Modifier.size(18.dp))
                                    Spacer(Modifier.width(8.dp))
                                Text(if (pickupTime.isNotBlank()) pickupTime else "Select Time", color = if (pickupTime.isNotBlank()) White else Gray500, fontSize = 13.sp)
                            }
                        }
                        if (dateError.isNotBlank() || timeError.isNotBlank()) {
                            Spacer(Modifier.height(4.dp))
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Box(modifier = Modifier.weight(1f)) { if (dateError.isNotBlank()) Text(dateError, color = Red, fontSize = 11.sp) }
                                Box(modifier = Modifier.weight(1f)) { if (timeError.isNotBlank()) Text(timeError, color = Red, fontSize = 11.sp) }
                            }
                        }
                    }
                    }
                }

                item {
                    PavanCard {
                        SectionHeader("Cab & Fare")
                        Spacer(Modifier.height(8.dp))
                        ExposedDropdownMenuBox(expanded = cabTypeExpanded, onExpandedChange = { cabTypeExpanded = !cabTypeExpanded }) {
                            PavanTextField(value = cabType, onValueChange = {}, label = "Cab Type", leadingIcon = Icons.Default.DirectionsCar, readOnly = true, modifier = Modifier.fillMaxWidth().menuAnchor())
                            ExposedDropdownMenu(expanded = cabTypeExpanded, onDismissRequest = { cabTypeExpanded = false }) {
                                cabTypes.forEach { type ->
                                    DropdownMenuItem(text = { Text(type, color = White) }, onClick = { cabType = type; cabTypeExpanded = false })
                                }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                        PavanTextField(value = totalFare, onValueChange = { totalFare = it }, label = "Total Fare (\u20B9) *", leadingIcon = Icons.Default.AttachMoney)
                        Spacer(Modifier.height(8.dp))
                        PavanTextField(value = specialNotes, onValueChange = { specialNotes = it }, label = "Notes (optional)", leadingIcon = Icons.Default.Note)
                    }
                }

                item {
                    Spacer(Modifier.height(4.dp))
                    GradientButton(
                        onClick = {
                            val errors = mutableListOf<String>()
                            if (customerName.isBlank()) errors.add("Customer name")
                            if (customerPhone.length < 7) errors.add("Valid phone number")
                            if (pickupLocation.isBlank()) errors.add("Pickup location")
                            if (dropLocation.isBlank()) errors.add("Drop location")
                            if (pickupDateInternal.isBlank()) errors.add("Pickup date")
                            if (pickupTimeInternal.isBlank()) errors.add("Pickup time")
                            if (totalFare.isBlank()) errors.add("Fare amount")
                            dropError = if (dropLocation.isBlank()) "Drop location is required" else ""
                            dateError = if (pickupDateInternal.isBlank()) "Select pickup date" else ""
                            timeError = if (pickupTimeInternal.isBlank()) "Select pickup time" else ""
                            if (errors.isNotEmpty()) {
                                Toast.makeText(context, "Please fill: ${errors.joinToString(", ")}", Toast.LENGTH_SHORT).show()
                                return@GradientButton
                            }
                            val fare = totalFare.toDoubleOrNull()
                            if (fare == null || fare <= 0) {
                                Toast.makeText(context, "Enter a valid fare amount", Toast.LENGTH_SHORT).show()
                                return@GradientButton
                            }
                            loading = true
                            scope.launch {
                                val tripTypeApi = when (tripType) { "Round Trip" -> "round_trip"; "Hourly" -> "hourly"; "Tour" -> "tour"; else -> "one_way" }
                                val result = repo.createPhoneBooking(
                                    name = customerName, phone = "$customerCountryCode$customerPhone",
                                    tripType = tripTypeApi, pickup = pickupLocation, drop = dropLocation,
                                    date = pickupDateInternal,
                                    time = pickupTimeInternal,
                                    cabType = cabType, fare = fare, notes = specialNotes.ifBlank { "Manual Phone Booking by Dispatch" }
                                )
                                loading = false
                                if (result.safeBool("success") == true) {
                                    bookingRef = result.getAsJsonObject("booking").safeString("booking_ref") ?: ""
                                    success = true
                                    Toast.makeText(context, "Booking created!", Toast.LENGTH_SHORT).show()
                                } else {
                                    Toast.makeText(context, result.safeString("error") ?: "Failed to create booking", Toast.LENGTH_SHORT).show()
                                }
                            }
                        },
                        text = if (loading) "Creating Booking..." else "Create Phone Booking",
                        icon = if (loading) null else Icons.Default.Phone,
                        enabled = !loading
                    )
                }
            }
        }
        if (loading) LoadingOverlay("Creating booking...")
    }
}
