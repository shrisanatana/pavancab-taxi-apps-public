package com.pavancab.dispatch.ui.reports

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.model.RideReport
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import com.pavancab.dispatch.utils.DateUtils
import kotlinx.coroutines.launch

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private fun severityColor(severity: String): Color = when (severity.uppercase()) {
    "HIGH" -> Red
    "MEDIUM" -> Orange
    "LOW" -> Emerald
    else -> Gray500
}

private fun reportStatusColor(status: String): Color = when (status.uppercase()) {
    "PENDING" -> StatusPending
    "INVESTIGATING" -> Blue
    "RESOLVED" -> Emerald
    else -> Gray500
}

@Composable
private fun TagBadge(text: String, color: Color) {
    Surface(shape = RoundedCornerShape(6.dp), color = color.copy(alpha = 0.15f)) {
        Text(text, color = color, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ReportsScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var reports by remember { mutableStateOf<List<RideReport>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var expandedId by remember { mutableStateOf<Int?>(null) }

    var updateTarget by remember { mutableStateOf<RideReport?>(null) }
    var newStatus by remember { mutableStateOf("PENDING") }
    var statusExpanded by remember { mutableStateOf(false) }
    var adminResponse by remember { mutableStateOf("") }

    fun refresh() {
        loading = true
        scope.launch {
            reports = repo.getRideReports()
            loading = false
        }
    }

    LaunchedEffect(Unit) { refresh() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Ride Reports", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = { IconButton(onClick = { refresh() }) { Icon(Icons.Default.Refresh, "Refresh", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when {
                loading -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
                reports.isEmpty() -> EmptyState(Icons.Default.Flag, "No Reports", "Ride issues reported by users and drivers will appear here")
                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                    contentPadding = PaddingValues(vertical = 12.dp)
                ) {
                    items(reports, key = { it.id }) { report ->
                        ReportCard(
                            report = report,
                            expanded = expandedId == report.id,
                            onToggle = { expandedId = if (expandedId == report.id) null else report.id },
                            onUpdate = {
                                newStatus = report.status.uppercase().ifBlank { "PENDING" }
                                adminResponse = ""
                                updateTarget = report
                            }
                        )
                    }
                }
            }
        }
    }

    updateTarget?.let { target ->
        AlertDialog(
            onDismissRequest = { updateTarget = null },
            containerColor = DarkBgLighter,
            title = { Text("Update Report #${target.id}", color = Gold, fontWeight = FontWeight.Bold, fontSize = 15.sp) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        "Booking ${target.bookingRef.ifBlank { "#${target.bookingId}" }} \u2022 Current: ${target.status}",
                        color = Gray400, fontSize = 12.sp
                    )
                    ExposedDropdownMenuBox(expanded = statusExpanded, onExpandedChange = { statusExpanded = !statusExpanded }) {
                        PavanTextField(value = newStatus, onValueChange = {}, label = "New Status", leadingIcon = Icons.Default.Flag, readOnly = true, modifier = Modifier.menuAnchor())
                        ExposedDropdownMenu(expanded = statusExpanded, onDismissRequest = { statusExpanded = false }) {
                            listOf("PENDING", "INVESTIGATING", "RESOLVED").forEach { s ->
                                DropdownMenuItem(
                                    text = { Text(s, color = reportStatusColor(s), fontWeight = FontWeight.Bold) },
                                    onClick = { newStatus = s; statusExpanded = false }
                                )
                            }
                        }
                    }
                    OutlinedTextField(
                        value = adminResponse, onValueChange = { adminResponse = it },
                        label = { Text("Admin Response (optional)") },
                        modifier = Modifier.fillMaxWidth().heightIn(min = 90.dp),
                        shape = RoundedCornerShape(12.dp), minLines = 3, maxLines = 6,
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                    )
                }
            },
            confirmButton = {
                TextButton(onClick = {
                    val t = target
                    val status = newStatus
                    val response = adminResponse
                    updateTarget = null
                    scope.launch {
                        val r = repo.updateReportStatus(t.id, status, response)
                        val msg = if (r.safeBool("success") == true) "Report updated!" else r.safeString("error") ?: "Failed to update report"
                        Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        refresh()
                    }
                }) { Text("Update", color = Gold) }
            },
            dismissButton = { TextButton(onClick = { updateTarget = null }) { Text("Cancel", color = Gray400) } }
        )
    }
}

@Composable
private fun ReportCard(report: RideReport, expanded: Boolean, onToggle: () -> Unit, onUpdate: () -> Unit) {
    val sevColor = severityColor(report.severity)
    val stColor = reportStatusColor(report.status)
    Surface(
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onToggle),
        shape = RoundedCornerShape(14.dp),
        color = CardBg,
        border = BorderStroke(1.dp, if (expanded) sevColor.copy(alpha = 0.4f) else CardBorder)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("#${report.id}", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.width(8.dp))
                Text(report.bookingRef.ifBlank { "No booking ref" }, color = Gray300, fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis, modifier = Modifier.weight(1f))
                Spacer(Modifier.width(6.dp))
                TagBadge(report.severity.uppercase(), sevColor)
            }
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                TagBadge(report.issueCategory.uppercase(), Purple)
                TagBadge(report.status.uppercase(), stColor)
                Spacer(Modifier.weight(1f))
                if (report.createdAt.isNotBlank()) Text(DateUtils.formatDateTime(report.createdAt), color = Gray500, fontSize = 10.sp)
            }
            Spacer(Modifier.height(8.dp))
            Text(
                report.description,
                color = White,
                fontSize = 12.sp,
                lineHeight = 16.sp,
                maxLines = if (expanded) Int.MAX_VALUE else 2,
                overflow = TextOverflow.Ellipsis
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Person, null, tint = Gray500, modifier = Modifier.size(13.dp))
                Spacer(Modifier.width(4.dp))
                Text("${report.reporterName.ifBlank { "Unknown" }} \u2022 ${report.reporterPhone}", color = Gray400, fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            if (report.driverName.isNotBlank()) {
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.DirectionsCar, null, tint = Gray500, modifier = Modifier.size(13.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Driver: ${report.driverName}", color = Gray400, fontSize = 11.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                }
            }
            AnimatedVisibility(visible = expanded) {
                Column {
                    Spacer(Modifier.height(10.dp))
                    Box(modifier = Modifier.fillMaxWidth().height(1.dp).background(DividerColor))
                    Spacer(Modifier.height(8.dp))
                    InfoRow("Pickup", report.pickupLocation.ifBlank { "\u2014" })
                    InfoRow("Drop", report.dropLocation.ifBlank { "\u2014" })
                    if (report.vehicleNumber.isNotBlank()) InfoRow("Vehicle", report.vehicleNumber)
                    if (report.rideStatusAtReport.isNotBlank()) InfoRow("Ride Was", report.rideStatusAtReport.replace('_', ' '))
                    Spacer(Modifier.height(8.dp))
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = DarkBgLighter) {
                        Column(modifier = Modifier.padding(10.dp)) {
                            Text("Admin Response", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                            Spacer(Modifier.height(2.dp))
                            Text(
                                if (report.adminResponse.isBlank()) "No response yet" else report.adminResponse,
                                color = if (report.adminResponse.isBlank()) Gray500 else Emerald,
                                fontSize = 12.sp
                            )
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Surface(
                        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable(onClick = onUpdate),
                        shape = RoundedCornerShape(10.dp),
                        color = Gold.copy(alpha = 0.08f),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.25f))
                    ) {
                        Row(
                            modifier = Modifier.padding(vertical = 10.dp),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(Icons.Default.Update, null, tint = Gold, modifier = Modifier.size(14.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("Update Status", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
