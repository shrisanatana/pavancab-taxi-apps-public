package com.pavancab.dispatch.ui.bookings

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

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private fun toDateInternal(value: String): String {
    val out = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
    listOf("yyyy-MM-dd", "dd-MM-yyyy", "yyyy-MM-dd HH:mm:ss", "yyyy-MM-dd'T'HH:mm:ss").forEach { f ->
        try {
            val inFmt = SimpleDateFormat(f, Locale.getDefault())
            inFmt.isLenient = false
            inFmt.parse(value)?.let { return out.format(it) }
        } catch (_: Exception) {}
    }
    return value
}

private fun toTimeInternal(value: String): String {
    val out = SimpleDateFormat("HH:mm", Locale.getDefault())
    listOf("hh:mm a", "HH:mm", "HH:mm:ss").forEach { f ->
        try {
            val inFmt = SimpleDateFormat(f, Locale.getDefault())
            inFmt.isLenient = false
            inFmt.parse(value)?.let { return out.format(it) }
        } catch (_: Exception) {}
    }
    return value
}

private fun displayTripType(raw: String): String {
    val key = raw.trim().lowercase().replace('_', ' ')
    return when (key) {
        "one way", "oneway" -> "One Way"
        "round trip", "roundtrip" -> "Round Trip"
        "hourly" -> "Hourly"
        "tour" -> "Tour"
        "" -> "One Way"
        else -> raw
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EditBookingScreen(bookingId: Int, onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }

    var pageLoading by remember { mutableStateOf(true) }
    var notFound by remember { mutableStateOf(false) }
    var saving by remember { mutableStateOf(false) }

    var customerName by remember { mutableStateOf("") }
    var countryCode by remember { mutableStateOf("+91") }
    var customerPhone by remember { mutableStateOf("") }
    var pickupLocation by remember { mutableStateOf("") }
    var dropLocation by remember { mutableStateOf("") }
    var pickupDate by remember { mutableStateOf("") }
    var pickupTime by remember { mutableStateOf("") }
    var pickupDateInternal by remember { mutableStateOf("") }
    var pickupTimeInternal by remember { mutableStateOf("") }
    var tripType by remember { mutableStateOf("One Way") }
    var cabType by remember { mutableStateOf("Sedan") }
    var totalFare by remember { mutableStateOf("") }
    var specialNotes by remember { mutableStateOf("") }

    var cabTypeExpanded by remember { mutableStateOf(false) }
    var tripTypeExpanded by remember { mutableStateOf(false) }
    val cabTypes = listOf("Sedan", "SUV", "Hatchback", "Tempo Traveller", "Innova", "Luxury")
    val tripTypes = listOf("One Way", "Round Trip", "Hourly", "Tour")

    var showDatePicker by remember { mutableStateOf(false) }
    var showTimePicker by remember { mutableStateOf(false) }

    val indianDateFormat = remember { SimpleDateFormat("dd-MM-yyyy", Locale.getDefault()) }
    val internalDateFormat = remember { SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()) }
    val indianTimeFormat = remember { SimpleDateFormat("hh:mm a", Locale.getDefault()) }
    val internalTimeFormat = remember { SimpleDateFormat("HH:mm", Locale.getDefault()) }

    LaunchedEffect(bookingId) {
        val b = repo.getBookingDetail(bookingId)
        if (b == null) {
            notFound = true
        } else {
            customerName = b.customerName
            val digits = b.customerPhone.filter { it.isDigit() }
            if (digits.length > 10) {
                countryCode = "+" + digits.dropLast(10)
                customerPhone = digits.takeLast(10)
            } else {
                countryCode = "+91"
                customerPhone = digits
            }
            pickupLocation = b.pickupLocation
            dropLocation = b.dropLocation
            if (b.pickupDate.isNotBlank()) {
                pickupDateInternal = toDateInternal(b.pickupDate)
                try { pickupDate = indianDateFormat.format(internalDateFormat.parse(pickupDateInternal)!!) } catch (_: Exception) { pickupDate = b.pickupDate }
            }
            if (b.pickupTime.isNotBlank()) {
                pickupTimeInternal = toTimeInternal(b.pickupTime)
                try { pickupTime = indianTimeFormat.format(internalTimeFormat.parse(pickupTimeInternal)!!) } catch (_: Exception) { pickupTime = b.pickupTime }
            }
            tripType = displayTripType(b.tripType)
            cabType = b.cabType.ifBlank { "Sedan" }
            if (b.totalFare > 0) {
                totalFare = if (b.totalFare == b.totalFare.toLong().toDouble()) b.totalFare.toLong().toString() else b.totalFare.toString()
            }
            specialNotes = b.specialNotes
        }
        pageLoading = false
    }

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
                title = { Text("Edit Booking #${bookingId}", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when {
                pageLoading -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
                notFound -> Column(
                    modifier = Modifier.fillMaxSize().padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Icon(Icons.Default.Info, null, tint = Gray600, modifier = Modifier.size(56.dp))
                    Spacer(Modifier.height(12.dp))
                    Text("Booking Not Found", color = Gray400, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = onBack, text = "Go Back")
                }
                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
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
                                CountryCodePicker(selectedCode = countryCode, onCodeSelected = { countryCode = it }, modifier = Modifier.width(100.dp))
                                PavanTextField(
                                    value = customerPhone,
                                    onValueChange = { if (it.length <= 10 && it.all { c -> c.isDigit() }) customerPhone = it },
                                    label = "WhatsApp Number *",
                                    leadingIcon = Icons.Default.Phone,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                        }
                    }

                    item {
                        PavanCard {
                            SectionHeader("Trip Details")
                            Spacer(Modifier.height(8.dp))
                            PavanTextField(value = pickupLocation, onValueChange = { pickupLocation = it }, label = "Pickup Location *", leadingIcon = Icons.Default.MyLocation)
                            Spacer(Modifier.height(8.dp))
                            PavanTextField(value = dropLocation, onValueChange = { dropLocation = it }, label = "Drop Location *", leadingIcon = Icons.Default.LocationOn)
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
                            }
                        }
                    }

                    item {
                        PavanCard {
                            SectionHeader("Trip Type, Cab & Fare")
                            Spacer(Modifier.height(8.dp))
                            ExposedDropdownMenuBox(expanded = tripTypeExpanded, onExpandedChange = { tripTypeExpanded = !tripTypeExpanded }) {
                                PavanTextField(value = tripType, onValueChange = {}, label = "Trip Type", leadingIcon = Icons.Default.Route, readOnly = true, modifier = Modifier.fillMaxWidth().menuAnchor())
                                ExposedDropdownMenu(expanded = tripTypeExpanded, onDismissRequest = { tripTypeExpanded = false }) {
                                    tripTypes.forEach { type ->
                                        DropdownMenuItem(text = { Text(type, color = White) }, onClick = { tripType = type; tripTypeExpanded = false })
                                    }
                                }
                            }
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
                                if (customerName.isBlank() || customerPhone.length < 7 || pickupLocation.isBlank()) {
                                    Toast.makeText(context, "Please fill name, valid phone and pickup location", Toast.LENGTH_SHORT).show()
                                    return@GradientButton
                                }
                                val fare = totalFare.toDoubleOrNull()
                                if (fare == null || fare <= 0) {
                                    Toast.makeText(context, "Enter a valid fare amount", Toast.LENGTH_SHORT).show()
                                    return@GradientButton
                                }
                                saving = true
                                scope.launch {
                                    val result = repo.editBooking(
                                        bookingId = bookingId,
                                        name = customerName,
                                        phone = "$countryCode$customerPhone",
                                        pickup = pickupLocation,
                                        drop = dropLocation,
                                        date = pickupDateInternal,
                                        time = pickupTimeInternal,
                                        cabType = cabType,
                                        fare = fare,
                                        notes = specialNotes
                                    )
                                    saving = false
                                    if (result.safeBool("success") == true) {
                                        Toast.makeText(context, "Booking updated successfully!", Toast.LENGTH_SHORT).show()
                                        onBack()
                                    } else {
                                        Toast.makeText(context, result.safeString("error") ?: "Failed to update booking", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            },
                            text = if (saving) "Saving Changes..." else "Save Changes",
                            icon = if (saving) null else Icons.Default.Save,
                            enabled = !saving
                        )
                    }
                }
            }
            if (pageLoading) LoadingOverlay("Loading booking...")
            if (saving) LoadingOverlay("Saving changes...")
        }
    }
}
