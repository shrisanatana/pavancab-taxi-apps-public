package com.pavancab.driver.ui.ride

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
import kotlinx.coroutines.delay
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.pavancab.driver.data.Repository
import com.pavancab.driver.model.Booking
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

@Composable
fun MyRidesScreen(
    repo: Repository,
    onBookingClick: (Booking) -> Unit,
    refreshTrigger: Int = 0
) {
    var bookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var selectedTab by remember { mutableIntStateOf(0) }
    val tabs = listOf("Active Rides", "Completed", "Cancelled")

    suspend fun load() {
        loading = true
        try {
            bookings = repo.getMyBookings().sortedBy { pickupMillis(it) }
        } catch (_: Exception) {}
        loading = false
    }

    LaunchedEffect(Unit) {
        load()
        while (true) { delay(10000); load() }
    }
    LaunchedEffect(refreshTrigger) { if (refreshTrigger > 0) load() }

    val isActive = { s: String -> !s.contains("COMPLETED") && !s.contains("CANCELLED") }
    val filteredBookings = when (selectedTab) {
        0 -> bookings.filter { isActive(it.status) }.sortedBy { pickupMillis(it) }
        1 -> bookings.filter { it.status == "COMPLETED" }.sortedByDescending { pickupMillis(it) }
        2 -> bookings.filter { it.status.contains("CANCELLED") }.sortedByDescending { pickupMillis(it) }
        else -> bookings
    }

    Column(modifier = Modifier.fillMaxSize()) {
        Surface(modifier = Modifier.fillMaxWidth(), color = DarkBgLighter, shape = RoundedCornerShape(bottomStart = 20.dp, bottomEnd = 20.dp)) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text("MY RIDES", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black)
                Spacer(Modifier.height(12.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    tabs.forEachIndexed { i, label ->
                        val sel = selectedTab == i
                        Surface(
                            modifier = Modifier.weight(1f).clip(RoundedCornerShape(8.dp)).clickable { selectedTab = i },
                            shape = RoundedCornerShape(8.dp),
                            color = if (sel) Gold else CardBg,
                            border = BorderStroke(1.dp, if (sel) Gold else CardBorder)
                        ) {
                            Text(
                                "$label (${when(i) { 0 -> bookings.count { !it.status.contains("COMPLETED") && !it.status.contains("CANCELLED") }; 1 -> bookings.count { it.status == "COMPLETED" }; else -> bookings.count { it.status.contains("CANCELLED") } }})",
                                color = if (sel) DarkBg else Gray400, fontSize = 11.sp, fontWeight = FontWeight.Black,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 8.dp)
                            )
                        }
                    }
                }
            }
        }

        if (filteredBookings.isEmpty() && !loading) {
            EmptyState(Icons.Default.LocalTaxi, "No ${tabs[selectedTab].lowercase()} rides")
        } else {
            LazyColumn(
                modifier = Modifier.padding(horizontal = 16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
                contentPadding = PaddingValues(vertical = 12.dp)
            ) {
                items(filteredBookings) { bk ->
                    com.pavancab.driver.ui.home.DriverBookingCard(bk) { onBookingClick(bk) }
                }
            }
        }
        if (loading) LoadingOverlay("Loading...")
    }
}
