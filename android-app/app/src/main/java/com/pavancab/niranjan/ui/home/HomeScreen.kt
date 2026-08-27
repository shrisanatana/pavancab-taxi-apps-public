package com.pavancab.niranjan.ui.home

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pavancab.niranjan.CrashLogger
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.model.*
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.util.Locale

private fun fmt(v: Double) = "\u20B9" + String.format(Locale.US, "%,.0f", v)

data class CabOption(val name: String, val seats: String, val desc: String, val color: Color, val fare: Double)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onNavigateToBooking: (String, String, String, String, String, Double) -> Unit,
    onNotifications: () -> Unit = {},
    onOpenRides: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var userName by remember { mutableStateOf("Guest") }
    var selectedTab by remember { mutableIntStateOf(0) }

    var pickups by remember { mutableStateOf<List<PickupPlace>>(emptyList()) }
    var selectedPickup by remember { mutableStateOf<PickupPlace?>(null) }
    var showPickupSheet by remember { mutableStateOf(false) }

    var drops by remember { mutableStateOf<List<DropFare>>(emptyList()) }
    var selectedDrop by remember { mutableStateOf<DropFare?>(null) }
    var showDropSheet by remember { mutableStateOf(false) }

    var hourlyMap by remember { mutableStateOf<Map<String, Map<Int, Double>>>(emptyMap()) }
    var hourlyExtra by remember { mutableStateOf(HourlyExtra()) }
    var selectedHourlyDuration by remember { mutableIntStateOf(8) }
    var tours by remember { mutableStateOf<List<Tour>>(emptyList()) }

    var loading by remember { mutableStateOf(false) }
    var loadingMsg by remember { mutableStateOf("") }
    var errorText by remember { mutableStateOf("") }
    var retryKey by remember { mutableIntStateOf(0) }

    val loadingMsgs = listOf("Finding best pickup locations...", "Scanning nearby cab availability...", "Connecting to driver network...", "Optimizing routes for you...")

    LaunchedEffect(Unit) { userName = UserPrefs.getName(context).ifBlank { "Guest" } }

    LaunchedEffect(selectedTab, retryKey) {
        selectedPickup = null; selectedDrop = null; tours = emptyList(); hourlyMap = emptyMap()
        errorText = ""; loading = true; loadingMsg = loadingMsgs[0]
        launch { var i = 0; while (loading) { delay(1500); i = (i + 1) % loadingMsgs.size; loadingMsg = loadingMsgs[i] } }
        try {
            val type = when (selectedTab) { 0 -> "oneway"; 1 -> "hourly"; else -> "tour" }
            pickups = repo.getPickups(type)
            if (pickups.isEmpty()) errorText = "No pickup locations available."
        } catch (e: Exception) {
            CrashLogger.log("ERROR", "Load pickups: ${e.message}", "Home", e)
            errorText = "Could not load locations. Check your connection."
        }
        loading = false
    }

    LaunchedEffect(selectedPickup?.id, selectedTab, retryKey) {
        val p = selectedPickup ?: return@LaunchedEffect
        selectedDrop = null; drops = emptyList(); hourlyMap = emptyMap(); tours = emptyList()
        loading = true; loadingMsg = "Loading options for ${p.name}..."
        try {
            when (selectedTab) {
                0 -> { loadingMsg = "Finding destinations from ${p.name}..."; drops = repo.getDrops(p.id) }
                1 -> { loadingMsg = "Loading hourly packages..."; val (m, e) = repo.getHourlyFaresMap(p.id); hourlyMap = m; hourlyExtra = e }
                else -> { loadingMsg = "Loading sightseeing tours..."; tours = repo.getTours(p.id) }
            }
            if (when (selectedTab) { 0 -> drops.isEmpty(); 1 -> hourlyMap.isEmpty(); else -> tours.isEmpty() }) errorText = "No options found for this location."
        } catch (e: Exception) {
            CrashLogger.log("ERROR", "Load data: ${e.message}", "Home", e)
            errorText = "Could not load options. Retry."
        }
        loading = false
    }

    if (showPickupSheet) {
        LocationPickerSheet(title = "Select Pickup Location", accent = Gold, items = pickups.map { it.name }, onDismiss = { showPickupSheet = false }) { idx ->
            selectedPickup = pickups[idx]; showPickupSheet = false
        }
    }
    if (showDropSheet && selectedTab == 0) {
        LocationPickerSheet(title = "Select Destination", accent = Blue, items = drops.map { "${it.destination}  \u2022  ${it.distance} km" }, onDismiss = { showDropSheet = false }) { idx ->
            selectedDrop = drops[idx]; showDropSheet = false
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(modifier = Modifier.fillMaxSize()) {
            // HERO HEADER — Goa gradient card
            Surface(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 8.dp),
                shape = RoundedCornerShape(22.dp),
                border = BorderStroke(1.dp, Gold.copy(alpha = 0.25f))
            ) {
                Box(modifier = Modifier.background(Brush.horizontalGradient(listOf(Gold.copy(alpha = 0.14f), Emerald.copy(alpha = 0.08f), Blue.copy(alpha = 0.08f))))) {
                    Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text("\uD83C\uDF34", fontSize = 16.sp)
                                Spacer(Modifier.width(5.dp))
                                Text("PAVANCAB", color = Gold, fontSize = 21.sp, fontWeight = FontWeight.Black, letterSpacing = 2.sp)
                            }
                            Spacer(Modifier.height(2.dp))
                            Text("Hi $userName! Where to in GOA today?", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                        }
                        // GOA location pill
                        Surface(shape = RoundedCornerShape(14.dp), color = White.copy(alpha = 0.06f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                            Row(modifier = Modifier.padding(horizontal = 9.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.LocationOn, null, tint = Gold, modifier = Modifier.size(11.dp))
                                Text("GOA", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                            }
                        }
                        Spacer(Modifier.width(6.dp))
                        IconButton(onClick = onNotifications) {
                            Icon(Icons.Default.Notifications, null, tint = Gold)
                        }
                    }
                }
            }

            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("ONE WAY", "HOURLY", "TOUR").forEachIndexed { i, label ->
                    val sel = selectedTab == i
                    Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable { selectedTab = i }, shape = RoundedCornerShape(12.dp), color = if (sel) Gold else Gray800, border = BorderStroke(1.dp, if (sel) GoldLight else CardBorder)) {
                        Text(label, textAlign = TextAlign.Center, fontWeight = FontWeight.Black, fontSize = 12.sp, color = if (sel) DarkBg else Gray400, modifier = Modifier.padding(vertical = 12.dp))
                    }
                }
            }

            Spacer(Modifier.height(8.dp))

            Column(modifier = Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(horizontal = 20.dp, vertical = 8.dp)) {
                StepLabel("STEP 1 \u2014 PICKUP LOCATION")
                Spacer(Modifier.height(8.dp))

                DropdownSelector(
                    label = "Select Pickup Location",
                    selectedText = selectedPickup?.name,
                    accent = Gold,
                    icon = Icons.Default.LocationOn
                ) { showPickupSheet = true }

                if (selectedPickup != null) {
                    Spacer(Modifier.height(16.dp))
                    when (selectedTab) {
                        0 -> {
                            StepLabel("STEP 2 \u2014 DESTINATION")
                            Spacer(Modifier.height(8.dp))
                            DropdownSelector(
                                label = "Select Destination",
                                selectedText = selectedDrop?.let { "${it.destination}  (${it.distance} km)" },
                                accent = Blue,
                                icon = Icons.Default.Flag
                            ) { showDropSheet = true }

                            if (selectedDrop != null) {
                                Spacer(Modifier.height(16.dp))
                                StepLabel("STEP 3 \u2014 CHOOSE YOUR CAB")
                                Spacer(Modifier.height(8.dp))
                                val d = selectedDrop!!
                                val ertigaFare = d.suvFare * 0.95
                                val crystaFare = d.suvFare * 1.25
val cabs = listOf(
                                    CabOption("Sedan", "4", "Standard sedan", SedanColor, d.sedanFare),
                                    CabOption("Ertiga", "6", "Maruti Ertiga", ErtigaColor, ertigaFare),
                                    CabOption("SUV", "6+", "Innova Crysta", SUVColor, d.suvFare),
                                    CabOption("Crysta", "6+", "Premium Crysta", CrystaColor, crystaFare)
                                )
                                cabs.forEach { cab ->
                                    CabCard(cab, if (cab.name == "Sedan") "MOST POPULAR" else if (cab.name == "Crysta") "PREMIUM" else "") { onNavigateToBooking("One Way", selectedPickup!!.name, d.destination, d.distance, cab.name, cab.fare) }
                                    Spacer(Modifier.height(8.dp))
                                }
                                Spacer(Modifier.height(12.dp))
                                BoostNote()
                            }
                        }
                        1 -> HourlyContent(hourlyMap, hourlyExtra, selectedHourlyDuration, { selectedHourlyDuration = it }, { cab -> onNavigateToBooking("Hourly", selectedPickup!!.name, "${selectedPickup!!.name}, ${selectedHourlyDuration} Hours", "${selectedHourlyDuration} Hours", cab.name, cab.fare) })
                        else -> TourContent(tours, onBook = { tour, vehicleName, vehicleFare ->
                            val dur = tour.duration.ifBlank { "Full Day" }
                            onNavigateToBooking("Tour", selectedPickup!!.name, "Tour: ${tour.title.ifBlank { selectedPickup!!.name }}", dur, vehicleName, vehicleFare)
                        })
                    }
                }

                // CUSTOM ONE-WAY BOOKING — name your own pickup, drop & price
                if (selectedTab == 0) {
                    Spacer(Modifier.height(16.dp))
                    CustomBookingCard(
                        repo = repo,
                        context = context,
                        scope = scope,
                        onOpenRides = onOpenRides
                    )
                }

                if (errorText.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Red.copy(alpha = 0.08f), border = BorderStroke(1.dp, Red.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CloudOff, null, tint = Red, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(errorText, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                            TextButton(onClick = { errorText = ""; if (selectedPickup == null) retryKey++ else retryKey++ }) { Text("RETRY", color = Gold, fontWeight = FontWeight.Black, fontSize = 12.sp) }
                        }
                    }
                }
                Spacer(Modifier.height(12.dp))
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable {
                    runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/918180951176"))) }
                }, shape = RoundedCornerShape(12.dp), color = Color(0xFF25D366).copy(alpha = 0.12f), border = BorderStroke(1.dp, Color(0xFF25D366).copy(alpha = 0.4f))) {
                    Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Chat, null, tint = Color(0xFF25D366), modifier = Modifier.size(20.dp))
                        Spacer(Modifier.width(10.dp))
                        Column(Modifier.weight(1f)) {
                            Text("Need help? Chat with us", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                            Text("WhatsApp Support \u2022 +91 81809 51176", color = Color(0xFF25D366), fontSize = 11.sp)
                        }
                    }
                }
                Spacer(Modifier.height(10.dp))
                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)), shape = RoundedCornerShape(14.dp), color = DarkBgLighter, border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Info, null, tint = Gold, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Can't find your location?", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        }
                        Spacer(Modifier.height(6.dp))
                        Text("We cover all major pickup locations in Goa. If your pickup or drop location isn't listed, contact our team directly and we'll make a custom booking for you.", color = Gray400, fontSize = 12.sp, lineHeight = 17.sp)
                        Spacer(Modifier.height(8.dp))
                        Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable {
                            runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/918180951176?text=Hi%2C%20I%20need%20a%20custom%20booking.%20My%20pickup%2Fdrop%20is%20not%20listed%20in%20the%20app."))) }
                        }, shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.15f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                            Row(modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.Phone, null, tint = Gold, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(6.dp))
                                Text("Contact Team for Custom Booking", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
                Spacer(Modifier.height(24.dp))
            }
        }
        if (loading) {
            Box(modifier = Modifier.fillMaxSize().background(DarkBg.copy(alpha = 0.85f)), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = Gold, modifier = Modifier.size(48.dp), strokeWidth = 3.dp)
                    Spacer(Modifier.height(16.dp))
                    Text(loadingMsg, color = Gray300, fontSize = 13.sp, fontWeight = FontWeight.Medium, textAlign = TextAlign.Center, modifier = Modifier.padding(horizontal = 32.dp))
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun LocationPickerSheet(title: String, accent: Color, items: List<String>, onDismiss: () -> Unit, onSelected: (Int) -> Unit) {
    var search by remember { mutableStateOf("") }
    val filteredIndices = remember(items, search) {
        if (search.isBlank()) items.indices.toList()
        else items.indices.filter { items[it].contains(search, ignoreCase = true) }
    }
    ModalBottomSheet(onDismissRequest = onDismiss, containerColor = DarkBgLighter, shape = RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)) {
        Column(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp)) {
            Text(title, color = White, fontSize = 16.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(bottom = 10.dp))
            OutlinedTextField(
                value = search,
                onValueChange = { search = it },
                placeholder = { Text("Search locations...", color = Gray600, fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, null, tint = accent, modifier = Modifier.size(18.dp)) },
                trailingIcon = {
                    if (search.isNotEmpty()) IconButton(onClick = { search = "" }, modifier = Modifier.size(30.dp)) {
                        Icon(Icons.Default.Close, null, tint = Gray500, modifier = Modifier.size(15.dp))
                    }
                },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier.fillMaxWidth(),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedTextColor = White, unfocusedTextColor = White,
                    focusedBorderColor = accent.copy(alpha = 0.5f), unfocusedBorderColor = CardBorder,
                    focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold
                )
            )
            Spacer(Modifier.height(12.dp))
            if (filteredIndices.isEmpty()) {
                Box(modifier = Modifier.fillMaxWidth().padding(vertical = 32.dp), contentAlignment = Alignment.Center) {
                    Text("No matching location found", color = Gray500, fontSize = 13.sp)
                }
            } else {
                LazyColumn(modifier = Modifier.fillMaxWidth().heightIn(max = 400.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    itemsIndexed(filteredIndices) { _, originalIndex ->
                        val name = items[originalIndex]
                        Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable {
                            search = ""
                            onSelected(originalIndex)
                        }, shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Box(modifier = Modifier.size(8.dp).clip(CircleShape).background(accent))
                                Spacer(Modifier.width(14.dp))
                                Text(name, color = White, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                                Icon(Icons.Default.ChevronRight, null, tint = Gray500, modifier = Modifier.size(18.dp))
                            }
                        }
                    }
                }
            }
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun DropdownSelector(label: String, selectedText: String?, accent: Color, icon: androidx.compose.ui.graphics.vector.ImageVector, onClick: () -> Unit) {
    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onClick), shape = RoundedCornerShape(14.dp), color = if (selectedText != null) accent.copy(alpha = 0.08f) else CardBg, border = BorderStroke(1.5.dp, if (selectedText != null) accent.copy(alpha = 0.4f) else accent.copy(alpha = 0.3f))) {
        Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = accent, modifier = Modifier.size(22.dp))
            Spacer(Modifier.width(12.dp))
            if (selectedText != null) {
                Column(Modifier.weight(1f)) {
                    Text(label.removePrefix("Select ").uppercase(), color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                    Text(selectedText, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                }
                Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onClick), shape = RoundedCornerShape(8.dp), color = Gray800) {
                    Text("CHANGE", color = accent, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                }
            } else {
                Text(label, color = Gray400, fontSize = 14.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
                Icon(Icons.Default.ArrowDropDown, null, tint = accent, modifier = Modifier.size(24.dp))
            }
        }
    }
}

@Composable
private fun StepLabel(text: String) {
    // Numbered gold circle + label, e.g. "STEP 1 — PICKUP LOCATION"
    val num = Regex("STEP (\\d)").find(text)?.groupValues?.get(1)?.toIntOrNull() ?: 0
    val label = text.replace(Regex("^STEP \\d+\\s*[—-]\\s*"), "")
    Row(verticalAlignment = Alignment.CenterVertically) {
        Surface(modifier = Modifier.size(20.dp), shape = CircleShape, color = Gold.copy(alpha = 0.15f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f))) {
            Box(contentAlignment = Alignment.Center) {
                Text("$num", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black)
            }
        }
        Spacer(Modifier.width(7.dp))
        Text(label.uppercase(), color = Gray500, fontSize = 11.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
    }
}

@Composable
private fun HourlyContent(hourlyMap: Map<String, Map<Int, Double>>, extra: HourlyExtra, selectedDuration: Int, onDurationChange: (Int) -> Unit, onBook: (CabOption) -> Unit) {
    StepLabel("STEP 2 \u2014 CHOOSE DURATION & CAB")
    Spacer(Modifier.height(8.dp))
    // Durations come straight from server pricing data (never miss new packages)
    val availableDurations = remember(hourlyMap) {
        val fromData = hourlyMap.values.flatMap { it.keys }.distinct().sorted()
        if (fromData.isEmpty()) listOf(4, 8, 12) else fromData
    }
    // Snap selection to a valid option when data changes
    LaunchedEffect(availableDurations) {
        if (!availableDurations.contains(selectedDuration)) onDurationChange(availableDurations.first())
    }
    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
        availableDurations.forEach { dur ->
            val sel = selectedDuration == dur
            Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { onDurationChange(dur) }, shape = RoundedCornerShape(10.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                Text("${dur}Hr", textAlign = TextAlign.Center, fontWeight = if (sel) FontWeight.Black else FontWeight.Bold, fontSize = 13.sp, color = if (sel) DarkBg else Gray400, modifier = Modifier.padding(vertical = 12.dp))
            }
        }
    }
    Spacer(Modifier.height(12.dp))
    val cabTypes = listOf("Sedan" to SedanColor, "Ertiga" to ErtigaColor, "SUV" to SUVColor, "Crysta" to CrystaColor)
    cabTypes.forEach { (cabName, cabColor) ->
        val fare = hourlyMap[cabName]?.get(selectedDuration) ?: 0.0
        if (fare > 0) {
            val seats = when (cabName) { "Sedan" -> "4"; "Ertiga" -> "6"; else -> "6+" }
            CabCard(CabOption(cabName, seats, "${selectedDuration} Hour Package", cabColor, fare)) {
                onBook(CabOption(cabName, seats, "${selectedDuration} Hour Package", cabColor, fare))
            }
            Spacer(Modifier.height(8.dp))
        }
    }

    if (extra.kmRate > 0 || extra.hrRate > 0) {
        Spacer(Modifier.height(12.dp))
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Info, null, tint = Gold, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("HOURLY PACKAGE DETAILS", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                }
                Spacer(Modifier.height(10.dp))
                InfoRow("Distance", "Km counted from pickup to return (same location)")
                InfoRow("Extra Sedan Km", "\u20B920/km (Sedan only)")
                InfoRow("Extra Other Km", "\u20B925/km (Ertiga, SUV, Crysta)")
                InfoRow("Extra Hour", "\u20B9${extra.hrRate.toInt()}/hr (all vehicles)")
                InfoRow("Night Allowance", "\u20B9${extra.nightRate.toInt()} driver night charges (after 10 PM, one-time)")
                Spacer(Modifier.height(10.dp))
                Text("* Night charge applies as one-time driver allowance for rides starting/ending after 10 PM", color = Gray500, fontSize = 10.sp)
            }
        }
    }

    Spacer(Modifier.height(12.dp))
    BoostNote()
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
        Text("\u2022  $label", color = Gray400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.width(120.dp))
        Text(value, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun BoostNote() {
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.08f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.25f))) {
        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.TrendingUp, null, tint = Gold, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text("Need your cab faster?", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                Text("Boost your fare after booking to attract drivers quicker!", color = Gray400, fontSize = 11.sp)
            }
        }
    }
}

@Composable
private fun TourContent(tours: List<Tour>, onBook: (Tour, String, Double) -> Unit) {
    StepLabel("STEP 2 \u2014 SELECT TOUR PACKAGE")
    Spacer(Modifier.height(8.dp))
    // Per-tour selected vehicle (defaults to Sedan)
    val selectedVehicle = remember { mutableStateMapOf<Int, String>() }
    tours.forEach { tour ->
        val isDudhsagar = tour.tourName.contains("Dudhsagar", ignoreCase = true)
        // Vehicles with real prices from the server data
        val vehicles = listOf(
            Triple("Sedan", tour.Sedan, SedanColor),
            Triple("Ertiga", tour.Ertiga, ErtigaColor),
            Triple("SUV", tour.SUV, SUVColor),
            Triple("Crysta", tour.Crysta, CrystaColor)
        ).filter { it.second > 0 }
        val chosen = selectedVehicle[tour.id] ?: "Sedan"
        val chosenFare = vehicles.firstOrNull { it.first.equals(chosen, true) }?.second
            ?: vehicles.firstOrNull()?.second ?: tour.Sedan

        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Surface(shape = RoundedCornerShape(6.dp), color = Emerald.copy(alpha = 0.15f)) {
                        Text("TOUR", color = Emerald, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                    }
                    Spacer(Modifier.width(8.dp))
                    Text(tour.tourName, color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                }
                if (tour.title.isNotBlank()) {
                    Spacer(Modifier.height(4.dp))
                    Text(tour.title, color = Gray400, fontSize = 12.sp)
                }
                Spacer(Modifier.height(8.dp))

                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = DarkBgLighter) {
                    Row(modifier = Modifier.padding(10.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Column(Modifier.weight(1f)) {
                            Text("PICKUP", color = Gray500, fontSize = 9.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                            Text(if (isDudhsagar) "7:00 AM" else "10:00 AM", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                        Column(Modifier.weight(1f)) {
                            Text("DROP", color = Gray500, fontSize = 9.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                            Text("Same Location", color = BlueAccent, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                        Column(Modifier.weight(1f)) {
                            Text("DURATION", color = Gray500, fontSize = 9.sp, fontWeight = FontWeight.Black, letterSpacing = 0.5.sp)
                            Text(tour.duration.ifBlank { "Full Day" }, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }

                if (isDudhsagar) {
                    Spacer(Modifier.height(6.dp))
                    Text("* Dudhsagar tour: Early 7 AM pickup. Full day trip with Jeep Safari & Spice Farm.", color = Gray500, fontSize = 10.sp)
                } else {
                    Spacer(Modifier.height(6.dp))
                    Text("* Standard sightseeing: 10 AM pickup, 5 PM return to same location.", color = Gray500, fontSize = 10.sp)
                }

                if (tour.desc.isNotBlank()) {
                    Spacer(Modifier.height(6.dp))
                    Text(tour.desc, color = Gray400, fontSize = 12.sp, maxLines = 3)
                }
                if (tour.inclusions.isNotEmpty()) {
                    Spacer(Modifier.height(6.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                        tour.inclusions.take(3).forEach { inc ->
                            Surface(shape = RoundedCornerShape(4.dp), color = Emerald.copy(alpha = 0.1f)) {
                                Text(inc, color = Emerald, fontSize = 9.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 6.dp, vertical = 3.dp))
                            }
                        }
                    }
                }
                Spacer(Modifier.height(10.dp))
                // Vehicle selector — every price the server provides for this tour
                if (vehicles.size > 1) {
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                        vehicles.forEach { (vName, vFare, vColor) ->
                            val sel = vName.equals(chosen, true)
                            Surface(
                                modifier = Modifier.weight(1f).clip(RoundedCornerShape(8.dp)).clickable { selectedVehicle[tour.id] = vName },
                                shape = RoundedCornerShape(8.dp),
                                color = if (sel) vColor.copy(alpha = 0.18f) else CardBgLight,
                                border = BorderStroke(if (sel) 2.dp else 1.dp, if (sel) vColor else CardBorder)
                            ) {
                                Column(modifier = Modifier.padding(vertical = 7.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                                    Text(vName, color = if (sel) White else Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    Text("\u20B9${vFare.toInt()}", color = if (sel) vColor else Gray500, fontSize = 12.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(if (vehicles.size > 1) "$chosen price" else "Starting from", color = Gray500, fontSize = 10.sp)
                        Text(fmt(chosenFare), color = Gold, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    }
                    Surface(modifier = Modifier.clip(RoundedCornerShape(10.dp)).clickable {
                        selectedVehicle[tour.id] = chosen
                        onBook(tour, chosen, chosenFare)
                    }, shape = RoundedCornerShape(10.dp), color = Emerald) {
                        Text("BOOK $chosen".uppercase(), color = DarkBg, fontSize = 12.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 16.dp, vertical = 10.dp))
                    }
                }
            }
        }
        Spacer(Modifier.height(8.dp))
    }
    Spacer(Modifier.height(12.dp))
    BoostNote()
}

@Composable
private fun CabCard(cab: CabOption, tag: String = "", onBook: () -> Unit) {
    Box(modifier = Modifier.fillMaxWidth()) {
        Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onBook), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.5.dp, cab.color.copy(alpha = 0.3f))) {
            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                Surface(modifier = Modifier.size(46.dp), shape = RoundedCornerShape(13.dp), color = cab.color.copy(alpha = 0.14f)) {
                    Box(contentAlignment = Alignment.Center) {
                        Icon(Icons.Default.LocalTaxi, null, tint = cab.color, modifier = Modifier.size(23.dp))
                    }
                }
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(cab.name, color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                        if (tag.isNotEmpty()) {
                            Spacer(Modifier.width(6.dp))
                            Surface(shape = RoundedCornerShape(5.dp), color = if (tag == "PREMIUM") StatusAssigned.copy(alpha = 0.18f) else Gold.copy(alpha = 0.16f)) {
                                Text(tag, color = if (tag == "PREMIUM") StatusAssigned else Gold, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp), letterSpacing = 0.5.sp)
                            }
                        }
                    }
                    Spacer(Modifier.height(1.dp))
                    Text("${cab.seats} seats \u2022 ${cab.desc}", color = Gray400, fontSize = 11.sp)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(fmt(cab.fare), color = Gold, fontSize = 17.sp, fontWeight = FontWeight.Black)
                    Text("per trip", color = Gray500, fontSize = 10.sp)
                }
                Spacer(Modifier.width(10.dp))
                Box(modifier = Modifier.size(28.dp).clip(CircleShape).background(Gold.copy(alpha = 0.12f)), contentAlignment = Alignment.Center) {
                    Icon(Icons.Default.ChevronRight, null, tint = Gold, modifier = Modifier.size(18.dp))
                }
            }
        }
    }
}

// ==================== CUSTOM ONE-WAY BOOKING ====================
// Passenger defines their OWN pickup, drop, schedule and offers their own fare.
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CustomBookingCard(
    repo: Repository,
    context: android.content.Context,
    scope: kotlinx.coroutines.CoroutineScope,
    onOpenRides: () -> Unit
) {
    var expanded by remember { mutableStateOf(false) }
    var cpickup by remember { mutableStateOf("") }
    var cdrop by remember { mutableStateOf("") }
    var mode by remember { mutableStateOf("now") } // now | later
    val indianDateFormat = remember { java.text.SimpleDateFormat("dd-MM-yyyy", Locale.getDefault()) }
    val internalDateFormat = remember { java.text.SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()) }
    val indianTimeFormat = remember { java.text.SimpleDateFormat("hh:mm a", Locale.getDefault()) }
    val internalTimeFormat = remember { java.text.SimpleDateFormat("HH:mm", Locale.getDefault()) }
    var cdateD by remember { mutableStateOf("") }
    var cdateI by remember { mutableStateOf("") }
    var ctimeD by remember { mutableStateOf("") }
    var ctimeI by remember { mutableStateOf("") }
    var showDate by remember { mutableStateOf(false) }
    var showTime by remember { mutableStateOf(false) }
    var cabSel by remember { mutableStateOf("Sedan") }
    var fareTxt by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    var doneRef by remember { mutableStateOf("") }
    var submitError by remember { mutableStateOf("") }

    LaunchedEffect(mode) {
        if (mode == "now") {
            val n = java.util.Calendar.getInstance()
            cdateI = internalDateFormat.format(n.time); cdateD = indianDateFormat.format(n.time)
            ctimeI = internalTimeFormat.format(n.time); ctimeD = indianTimeFormat.format(n.time)
        }
    }

    fun isNight(h: Int) = h >= 22 || h < 6
    val nightHint = if (mode == "later" && ctimeI.isNotBlank()) {
        try {
            val hh = ctimeI.split(":").firstOrNull()?.toIntOrNull() ?: 0
            isNight(hh)
        } catch (_: Exception) { false }
    } else {
        val h = java.util.Calendar.getInstance().get(java.util.Calendar.HOUR_OF_DAY)
        isNight(h)
    }

    val fareVal = fareTxt.toDoubleOrNull() ?: 0.0
    val fieldsOk = cpickup.trim().length >= 3 && cdrop.trim().length >= 3 && fareVal >= 300 &&
        (mode == "now" || (cdateI.isNotBlank() && ctimeI.isNotBlank()))

    fun submit() {
        sending = true
        submitError = ""
        scope.launch {
            try {
                var notes = "[CUSTOM BOOKING] Passenger offered their own fare"
                if (nightHint) notes += "\n[NIGHT] Pickup between 10 PM - 6 AM; driver night allowance may apply"
                val res = repo.createBooking(
                    name = UserPrefs.getName(context).ifBlank { "Guest" },
                    phone = UserPrefs.getPhone(context),
                    email = UserPrefs.getEmail(context),
                    tripType = "One Way",
                    pickup = cpickup.trim(),
                    drop = cdrop.trim(),
                    date = cdateI,
                    time = ctimeI,
                    cabType = cabSel,
                    fare = fareVal,
                    baseFare = fareVal,
                    fareOffered = fareVal,
                    notes = notes
                )
                val ok = try { res.get("success").asBoolean } catch (_: Exception) { false }
                if (ok) {
                    doneRef = try { res.get("booking_ref")?.asString ?: "PLACED" } catch (_: Exception) { "PLACED" }
                } else {
                    val errMsg = try { res.get("error")?.asString ?: "" } catch (_: Exception) { "" }
                    submitError = errMsg.ifBlank { "Booking failed. Check your details and try again." }
                }
            } catch (_: Exception) { submitError = "Connection error. Check your internet and try again." }
            sending = false
        }
    }

    // Success dialog
    if (doneRef.isNotBlank()) {
        AlertDialog(
            onDismissRequest = { },
            containerColor = DarkBgLighter,
            icon = { Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(40.dp)) },
            title = { Text("Custom Booking Placed!", color = White, fontWeight = FontWeight.Black) },
            text = {
                Column {
                    Text("Reference", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    Text(doneRef, color = Gold, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    Spacer(Modifier.height(8.dp))
                    Text("Our team will confirm your \u20B9${fareVal.toInt()} offer shortly. Track it in My Rides.", color = Gray400, fontSize = 12.sp, lineHeight = 16.sp)
                }
            },
            confirmButton = {
                Button(onClick = { doneRef = ""; expanded = false; cpickup = ""; cdrop = ""; fareTxt = ""; onOpenRides() }, colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)) {
                    Text("VIEW MY RIDES", fontWeight = FontWeight.Black)
                }
            },
            dismissButton = { TextButton(onClick = { doneRef = "" }) { Text("Book another", color = Gray500) } }
        )
    }

    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        color = CardBg,
        border = BorderStroke(1.5.dp, if (expanded) Gold.copy(alpha = 0.6f) else Blue.copy(alpha = 0.35f))
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            // Header — always visible
            Row(modifier = Modifier.fillMaxWidth().clickable { expanded = !expanded }, verticalAlignment = Alignment.CenterVertically) {
                Surface(modifier = Modifier.size(38.dp), shape = RoundedCornerShape(11.dp), color = Blue.copy(alpha = 0.14f)) {
                    Box(contentAlignment = Alignment.Center) { Icon(Icons.Default.EditRoad, null, tint = Blue, modifier = Modifier.size(19.dp)) }
                }
                Spacer(Modifier.width(10.dp))
                Column(Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text("CUSTOM BOOKING", color = White, fontSize = 13.sp, fontWeight = FontWeight.Black)
                        Spacer(Modifier.width(6.dp))
                        Surface(shape = RoundedCornerShape(6.dp), color = Emerald.copy(alpha = 0.15f)) {
                            Text("ANY LOCATION", color = Emerald, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp))
                        }
                    }
                    Text("Own pickup & drop \u2022 name your own price", color = Gray500, fontSize = 10.sp)
                }
                Icon(if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore, null, tint = Gold)
            }

            androidx.compose.animation.AnimatedVisibility(visible = expanded) {
                Column(modifier = Modifier.padding(top = 12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedTextField(value = cpickup, onValueChange = { cpickup = it.take(120) }, label = { Text("Pickup location *") }, placeholder = { Text("e.g. Villa 12, Assagao", color = Gray600, fontSize = 12.sp) }, leadingIcon = { Icon(Icons.Default.MyLocation, null, tint = Gold, modifier = Modifier.size(17.dp)) }, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(11.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                    OutlinedTextField(value = cdrop, onValueChange = { cdrop = it.take(120) }, label = { Text("Drop location *") }, placeholder = { Text("e.g. Goa Airport, Dabolim", color = Gray600, fontSize = 12.sp) }, leadingIcon = { Icon(Icons.Default.Flag, null, tint = Red, modifier = Modifier.size(17.dp)) }, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(11.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))

                    // Now / Schedule toggle
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf("now" to "RIDE NOW", "later" to "SCHEDULE").forEach { (v, lbl) ->
                            val sel = mode == v
                            Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { mode = v }, shape = RoundedCornerShape(10.dp), color = if (sel) Gold else Gray800, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                                Row(modifier = Modifier.padding(vertical = 9.dp), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
                                    Icon(if (sel) Icons.Default.Bolt else Icons.Default.Schedule, null, tint = if (sel) DarkBg else Gray400, modifier = Modifier.size(13.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text(lbl, color = if (sel) DarkBg else Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }
                    }
                    if (mode == "later") {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { showDate = true }, shape = RoundedCornerShape(10.dp), color = Gray900, border = BorderStroke(1.dp, Gray700)) {
                                Row(modifier = Modifier.padding(11.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.CalendarToday, null, tint = Gold, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text(cdateD.ifBlank { "Pick date" }, color = if (cdateD.isBlank()) Gray600 else White, fontSize = 11.sp)
                                }
                            }
                            Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { showTime = true }, shape = RoundedCornerShape(10.dp), color = Gray900, border = BorderStroke(1.dp, Gray700)) {
                                Row(modifier = Modifier.padding(11.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.AccessTime, null, tint = Gold, modifier = Modifier.size(14.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text(ctimeD.ifBlank { "Pick time" }, color = if (ctimeD.isBlank()) Gray600 else White, fontSize = 11.sp)
                                }
                            }
                        }
                    }

                    // Cab selection chips
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        listOf("Sedan", "Ertiga", "SUV", "Crysta").forEach { cb ->
                            val sel = cabSel == cb
                            Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(9.dp)).clickable { cabSel = cb }, shape = RoundedCornerShape(9.dp), color = if (sel) Gold.copy(alpha = 0.18f) else Gray800, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                                Text(cb, textAlign = TextAlign.Center, color = if (sel) Gold else Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp))
                            }
                        }
                    }

                    // Fare offer
                    OutlinedTextField(
                        value = fareTxt,
                        onValueChange = { fareTxt = it.filter { c -> c.isDigit() }.take(6) },
                        label = { Text("Offer your fare (\u20B9) *") },
                        placeholder = { Text("Min \u20B9300 \u2014 e.g. 1500", color = Gray600, fontSize = 12.sp) },
                        leadingIcon = { Icon(Icons.Default.CurrencyRupee, null, tint = Gold, modifier = Modifier.size(17.dp)) },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        shape = RoundedCornerShape(11.dp),
                        keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = androidx.compose.ui.text.input.KeyboardType.Number),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, focusedTextColor = Gold, unfocusedTextColor = Gold, cursorColor = Gold)
                    )
                    if (nightHint) {
                        Text("\uD83C\uDF19 Night pickup selected — driver night allowance may be requested separately.", color = StatusInTransit, fontSize = 10.sp)
                    }

                    Button(
                        onClick = { submit() },
                        enabled = !sending && fieldsOk,
                        modifier = Modifier.fillMaxWidth().height(48.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)
                    ) {
                        if (sending) {
                            CircularProgressIndicator(modifier = Modifier.size(18.dp), color = DarkBg, strokeWidth = 2.dp)
                            Spacer(Modifier.width(8.dp))
                            Text("PLACING BOOKING...", fontWeight = FontWeight.Black, fontSize = 12.sp)
                        } else {
                            Icon(Icons.Default.LocalTaxi, null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("SEND OFFER \u2022 \u20B9${fareVal.toInt()}", fontWeight = FontWeight.Black, fontSize = 13.sp)
                        }
                    }
                    if (submitError.isNotEmpty()) {
                        Spacer(Modifier.height(6.dp))
                        Text(submitError, color = Red, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                    Text("Drivers see your offer instantly and can accept it \u2014 or our team will confirm the best price for you.", color = Gray600, fontSize = 9.sp, lineHeight = 12.sp)
                }
            }
        }
    }

    // Date picker
    if (showDate) {
        val st = rememberDatePickerState()
        DatePickerDialog(onDismissRequest = { showDate = false }, confirmButton = {
            TextButton(onClick = {
                st.selectedDateMillis?.let { ms ->
                    val cal = java.util.Calendar.getInstance().apply { timeInMillis = ms }
                    cdateI = internalDateFormat.format(cal.time); cdateD = indianDateFormat.format(cal.time)
                }
                showDate = false
            }) { Text("OK", color = Gold) }
        }, dismissButton = { TextButton(onClick = { showDate = false }) { Text("Cancel", color = Gray400) } }) {
            DatePicker(state = st, colors = DatePickerDefaults.colors(containerColor = DarkBg, selectedDayContainerColor = Gold, selectedDayContentColor = DarkBg))
        }
    }
    // Time picker
    if (showTime) {
        val cal = java.util.Calendar.getInstance()
        val tps = rememberTimePickerState(initialHour = cal.get(java.util.Calendar.HOUR_OF_DAY), initialMinute = cal.get(java.util.Calendar.MINUTE), is24Hour = false)
        AlertDialog(onDismissRequest = { showTime = false }, containerColor = DarkBgLighter, title = { Text("Select pickup time", color = White) }, text = { TimePicker(state = tps) },
            confirmButton = {
                TextButton(onClick = {
                    ctimeI = String.format("%02d:%02d", tps.hour, tps.minute)
                    ctimeD = indianTimeFormat.format(java.util.Calendar.getInstance().apply { set(java.util.Calendar.HOUR_OF_DAY, tps.hour); set(java.util.Calendar.MINUTE, tps.minute) }.time)
                    showTime = false
                }) { Text("OK", color = Gold) }
            }, dismissButton = { TextButton(onClick = { showTime = false }) { Text("Cancel", color = Gray400) } })
    }
}
