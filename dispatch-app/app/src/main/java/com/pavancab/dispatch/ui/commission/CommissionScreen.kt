package com.pavancab.dispatch.ui.commission

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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.model.CommissionDay
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CommissionScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val repo = remember { Repository(context) }
    var report by remember { mutableStateOf<com.pavancab.dispatch.model.CommissionReport?>(null) }
    var loading by remember { mutableStateOf(true) }
    var selectedDays by remember { mutableIntStateOf(30) }

    LaunchedEffect(selectedDays) {
        loading = true
        report = repo.getCommissionReport(selectedDays)
        loading = false
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Commission Report", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                listOf(7 to "7 DAYS", 15 to "15 DAYS", 30 to "30 DAYS").forEach { (days, label) ->
                    val sel = selectedDays == days
                    Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedDays = days }, shape = RoundedCornerShape(8.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                        Text(label, color = if (sel) DarkBg else Gray400, fontSize = 11.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 14.dp, vertical = 8.dp))
                    }
                }
            }

            if (loading) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
            } else if (report == null || report!!.daily.isEmpty()) {
                EmptyState(Icons.Default.Inbox, "No completed rides in this period")
            } else {
                val rpt = report!!
                LazyColumn(modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(vertical = 12.dp)) {
                    item {
                        PavanCard {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Column {
                                    Text("Total Commission", color = Gray400, fontSize = 12.sp)
                                    Text(fmt(rpt.totalCommission), color = Gold, fontSize = 28.sp, fontWeight = FontWeight.Black)
                                }
                                Column(horizontalAlignment = Alignment.End) {
                                    Text("Total Rides", color = Gray400, fontSize = 12.sp)
                                    Text("${rpt.totalRides}", color = White, fontSize = 28.sp, fontWeight = FontWeight.Black)
                                }
                            }
                            Spacer(Modifier.height(6.dp))
                            Text("\u20B9${rpt.commissionPerRide} per completed ride", color = Gray500, fontSize = 11.sp)
                        }
                    }
                    items(rpt.daily) { day ->
                        PavanCard {
                            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(day.rideDate, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    Text("${day.rideCount} ride${if (day.rideCount != 1) "s" else ""} \u2022 ${fmt(day.totalFare)} fare", color = Gray400, fontSize = 12.sp)
                                }
                                Surface(shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.12f)) {
                                    Text(fmt(day.commission.toDouble()), color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp))
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
