package com.pavancab.driver.ui.earnings

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.pavancab.driver.data.Repository
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import com.google.gson.JsonObject
import com.google.gson.JsonNull
import kotlinx.coroutines.delay

private fun JsonObject?.safeInt(key: String, fallback: Int = 0): Int {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asInt
}

private fun JsonObject?.safeDouble(key: String, fallback: Double = 0.0): Double {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asDouble
}

@Composable
fun EarningsScreen(repo: Repository, refreshTrigger: Int = 0) {
    var earnings by remember { mutableStateOf<JsonObject?>(null) }
    var loading by remember { mutableStateOf(true) }

    suspend fun load() {
        loading = true
        try { earnings = repo.getEarnings() } catch (_: Exception) {}
        loading = false
    }
    LaunchedEffect(Unit) {
        load()
        while (true) { delay(10000); load() }
    }
    LaunchedEffect(refreshTrigger) { if (refreshTrigger > 0) load() }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Surface(modifier = Modifier.fillMaxWidth(), color = DarkBgLighter, shape = RoundedCornerShape(bottomStart = 20.dp, bottomEnd = 20.dp)) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text("EARNINGS", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black)
                }
            }
        }

        // Today
        item {
            EarningsCard(
                label = "TODAY",
                rides = earnings.safeInt("today_rides"),
                earnings = earnings.safeDouble("today_earnings"),
                commission = earnings.safeInt("commission_per_ride", 300),
                color = Gold
            )
        }
        // This Week
        item {
            EarningsCard(
                label = "THIS WEEK",
                rides = earnings.safeInt("week_rides"),
                earnings = earnings.safeDouble("week_earnings"),
                commission = earnings.safeInt("commission_per_ride", 300),
                color = Blue
            )
        }
        // This Month
        item {
            EarningsCard(
                label = "THIS MONTH",
                rides = earnings.safeInt("month_rides"),
                earnings = earnings.safeDouble("month_earnings"),
                commission = earnings.safeInt("commission_per_ride", 300),
                color = Emerald
            )
        }
    }

    if (loading) LoadingOverlay("Loading earnings...")
}

@Composable
private fun EarningsCard(label: String, rides: Int, earnings: Double, commission: Int, color: Color) {
    val net = (rides * commission).toDouble()
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Surface(modifier = Modifier.size(36.dp), shape = RoundedCornerShape(10.dp), color = color.copy(alpha = 0.12f)) {
                    Box(contentAlignment = Alignment.Center) { Icon(Icons.Default.Wallet, null, tint = color, modifier = Modifier.size(18.dp)) }
                }
                Spacer(Modifier.width(12.dp))
                Text(label, color = color, fontSize = 13.sp, fontWeight = FontWeight.Black)
            }
            Spacer(Modifier.height(16.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Column { Text("Total Earn.", color = Gray400, fontSize = 10.sp); Text(fmt(earnings), color = White, fontSize = 18.sp, fontWeight = FontWeight.Black) }
                Column(horizontalAlignment = Alignment.End) { Text("Rides", color = Gray400, fontSize = 10.sp); Text("$rides", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black) }
                Column(horizontalAlignment = Alignment.End) { Text("Commission", color = Gray400, fontSize = 10.sp); Text(fmt(net), color = Red, fontSize = 14.sp, fontWeight = FontWeight.Bold) }
            }
        }
    }
}
