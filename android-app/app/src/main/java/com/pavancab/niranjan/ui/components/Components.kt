package com.pavancab.niranjan.ui.components

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.pavancab.niranjan.ui.theme.*

@Composable
fun GradientButton(onClick: () -> Unit, text: String, modifier: Modifier = Modifier, enabled: Boolean = true, icon: ImageVector? = null, colors: List<Color> = listOf(Gold, GoldDark)) {
    Button(onClick = onClick, enabled = enabled, modifier = modifier.fillMaxWidth().height(52.dp), shape = RoundedCornerShape(14.dp), colors = ButtonDefaults.buttonColors(containerColor = Color.Transparent), contentPadding = PaddingValues()) {
        Box(modifier = Modifier.fillMaxSize().background(brush = Brush.horizontalGradient(colors), shape = RoundedCornerShape(14.dp)), contentAlignment = Alignment.Center) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (icon != null) { Icon(icon, null, tint = DarkBg, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)) }
                Text(text, color = DarkBg, fontWeight = FontWeight.Black, fontSize = 14.sp, letterSpacing = 1.sp)
            }
        }
    }
}

@Composable
fun GradientButtonSmall(onClick: () -> Unit, text: String, modifier: Modifier = Modifier, enabled: Boolean = true, colors: List<Color> = listOf(Gold, GoldDark)) {
    Button(onClick = onClick, enabled = enabled, modifier = modifier.height(40.dp), shape = RoundedCornerShape(10.dp), colors = ButtonDefaults.buttonColors(containerColor = Color.Transparent), contentPadding = PaddingValues(horizontal = 16.dp)) {
        Box(modifier = Modifier.background(brush = Brush.horizontalGradient(colors), shape = RoundedCornerShape(10.dp)), contentAlignment = Alignment.Center) {
            Text(text, color = DarkBg, fontWeight = FontWeight.Black, fontSize = 12.sp)
        }
    }
}

@Composable
fun OutlinedGoldButton(onClick: () -> Unit, text: String, modifier: Modifier = Modifier, icon: ImageVector? = null) {
    OutlinedButton(onClick = onClick, modifier = modifier.height(44.dp), shape = RoundedCornerShape(12.dp), border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gold)) {
        if (icon != null) { Icon(icon, null, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(6.dp)) }
        Text(text, fontWeight = FontWeight.Bold, fontSize = 12.sp)
    }
}

@Composable
fun PavanCard(modifier: Modifier = Modifier, content: @Composable ColumnScope.() -> Unit) {
    Card(modifier = modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = CardBg), border = BorderStroke(1.dp, CardBorder)) {
        Column(modifier = Modifier.padding(16.dp), content = content)
    }
}

@Composable
fun PavanCardClickable(onClick: () -> Unit, modifier: Modifier = Modifier, selected: Boolean = false, borderColor: Color = if (selected) Gold else CardBorder, content: @Composable ColumnScope.() -> Unit) {
    Card(onClick = onClick, modifier = modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = if (selected) GoldSurface else CardBg), border = BorderStroke(1.dp, borderColor)) {
        Column(modifier = Modifier.padding(16.dp), content = content)
    }
}

@Composable
fun SectionHeader(title: String, subtitle: String? = null, action: String? = null, onActionClick: (() -> Unit)? = null) {
    Row(modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Column { Text(title, color = White, fontWeight = FontWeight.Black, fontSize = 16.sp); if (subtitle != null) Text(subtitle, color = Gray400, fontSize = 12.sp) }
        if (action != null && onActionClick != null) { TextButton(onClick = onActionClick) { Text(action, color = Gold, fontWeight = FontWeight.Bold, fontSize = 13.sp) } }
    }
}

@Composable
fun StatusBadge(status: String, modifier: Modifier = Modifier) {
    val (color, text) = when (status.uppercase()) { "PENDING" -> StatusPending to "Pending"; "CONFIRMED" -> StatusConfirmed to "Confirmed"; "ASSIGNED" -> StatusAssigned to "Driver Assigned"; "IN_TRANSIT", "ON_TRIP" -> StatusInTransit to "On Trip"; "ARRIVED" -> Blue to "Arrived"; "COMPLETED" -> StatusCompleted to "Completed"; "CANCELLED", "CANCELLED_BY_USER" -> StatusCancelled to "Cancelled"; else -> Gray400 to status }
    Surface(modifier = modifier, shape = RoundedCornerShape(8.dp), color = color.copy(alpha = 0.15f)) {
        Text(text, modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp), color = color, fontWeight = FontWeight.Bold, fontSize = 11.sp)
    }
}

@Composable
fun DriverInfoCard(driverName: String, driverPhone: String, vehicleNumber: String, vehicleModel: String? = null, driverRating: Double? = null, onCall: () -> Unit, onWhatsApp: () -> Unit) {
    PavanCard {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Surface(shape = CircleShape, color = GoldSurface, modifier = Modifier.size(48.dp)) {
                Box(contentAlignment = Alignment.Center) { Icon(Icons.Default.Person, "Driver", tint = Gold, modifier = Modifier.size(24.dp)) }
            }
            Spacer(Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(driverName, color = White, fontWeight = FontWeight.Bold, fontSize = 15.sp)
                    if (driverRating != null && driverRating > 0) { Spacer(Modifier.width(6.dp)); Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(14.dp)); Text(String.format("%.1f", driverRating), color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold) }
                }
                Text(vehicleNumber, color = Gray400, fontSize = 12.sp)
                if (vehicleModel != null) Text(vehicleModel, color = Gray500, fontSize = 11.sp)
            }
            Column {
                IconButton(onClick = onCall, modifier = Modifier.size(40.dp).background(EmeraldSurface, CircleShape)) { Icon(Icons.Default.Phone, "Call", tint = Emerald, modifier = Modifier.size(18.dp)) }
                Spacer(Modifier.height(4.dp))
                IconButton(onClick = onWhatsApp, modifier = Modifier.size(40.dp).background(EmeraldSurface, CircleShape)) { Icon(Icons.Default.Chat, "WhatsApp", tint = Emerald, modifier = Modifier.size(18.dp)) }
            }
        }
    }
}

@Composable
fun ProgressStepIndicator(currentStep: Int, modifier: Modifier = Modifier) {
    val steps = listOf("Booked", "Dispatched", "On Trip", "Completed")
    Row(modifier = modifier.fillMaxWidth().padding(horizontal = 8.dp), horizontalArrangement = Arrangement.SpaceBetween) {
        steps.forEachIndexed { index, label ->
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                val isActive = index < currentStep
                Surface(shape = CircleShape, color = if (isActive) Gold else Gray700, modifier = Modifier.size(28.dp)) {
                    Box(contentAlignment = Alignment.Center) {
                        if (isActive) Icon(Icons.Default.Check, null, tint = DarkBg, modifier = Modifier.size(16.dp))
                        else Text("${index + 1}", color = Gray400, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
                Spacer(Modifier.height(4.dp))
                Text(label, color = if (isActive) Gold else Gray500, fontSize = 10.sp, fontWeight = if (isActive) FontWeight.Bold else FontWeight.Normal, maxLines = 1)
            }
            if (index < steps.lastIndex) Box(modifier = Modifier.weight(1f).height(2.dp).padding(horizontal = 4.dp).align(Alignment.CenterVertically).background(if (index < currentStep - 1) Gold else Gray700))
        }
    }
}

@Composable
fun BoostFareRow(onBoost: (Double) -> Unit) {
    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf(100.0, 200.0, 500.0).forEach { amount ->
            OutlinedGoldButton(onClick = { onBoost(amount) }, text = "+₹${amount.toInt()}", modifier = Modifier.weight(1f))
        }
    }
}

@Composable
fun RatingBar(rating: Int, onRatingChanged: (Int) -> Unit, modifier: Modifier = Modifier, interactive: Boolean = true) {
    Row(modifier = modifier) {
        for (i in 1..5) {
            IconButton(onClick = { if (interactive) onRatingChanged(i) }, modifier = Modifier.size(36.dp), enabled = interactive) {
                Icon(if (i <= rating) Icons.Default.Star else Icons.Default.StarBorder, contentDescription = "$i stars", tint = if (i <= rating) Gold else Gray600, modifier = Modifier.size(28.dp))
            }
        }
    }
}

@Composable
fun EmptyState(icon: ImageVector, title: String, subtitle: String, modifier: Modifier = Modifier) {
    Column(modifier = modifier.fillMaxWidth().padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Surface(shape = CircleShape, color = CardBg, modifier = Modifier.size(72.dp)) {
            Box(contentAlignment = Alignment.Center) { Icon(icon, null, tint = Gray600, modifier = Modifier.size(32.dp)) }
        }
        Spacer(Modifier.height(16.dp))
        Text(title, color = Gray300, fontWeight = FontWeight.Bold, fontSize = 16.sp)
        Spacer(Modifier.height(4.dp))
        Text(subtitle, color = Gray500, fontSize = 13.sp, textAlign = TextAlign.Center)
    }
}

@Composable
fun LoadingOverlay(message: String = "Loading...") {
    Box(modifier = Modifier.fillMaxSize().background(Color.Black.copy(alpha = 0.5f)), contentAlignment = Alignment.Center) {
        Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = CardBg)) {
            Column(modifier = Modifier.padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                CircularProgressIndicator(color = Gold, modifier = Modifier.size(36.dp))
                Spacer(Modifier.height(12.dp))
                Text(message, color = Gray300, fontSize = 13.sp)
            }
        }
    }
}

@Composable
fun TabSelector(tabs: List<String>, selectedIndex: Int, onTabSelected: (Int) -> Unit, modifier: Modifier = Modifier) {
    Row(modifier = modifier.fillMaxWidth().background(CardBg, RoundedCornerShape(12.dp)).padding(4.dp), horizontalArrangement = Arrangement.spacedBy(4.dp)) {
        tabs.forEachIndexed { index, label ->
            val isSelected = index == selectedIndex
            Box(modifier = Modifier.weight(1f).height(40.dp).clip(RoundedCornerShape(10.dp)).background(if (isSelected) Gold else Color.Transparent).clickable(enabled = !isSelected) { onTabSelected(index) }, contentAlignment = Alignment.Center) {
                Text(label, color = if (isSelected) DarkBg else Gray400, fontWeight = if (isSelected) FontWeight.Black else FontWeight.Medium, fontSize = 12.sp)
            }
        }
    }
}

@Composable
fun PavanTextField(value: String, onValueChange: (String) -> Unit, label: String, modifier: Modifier = Modifier, leadingIcon: ImageVector? = null, readOnly: Boolean = false, singleLine: Boolean = true) {
    OutlinedTextField(value = value, onValueChange = onValueChange, label = { Text(label, fontSize = 13.sp) }, leadingIcon = if (leadingIcon != null) {{ Icon(leadingIcon, null, tint = Gray400, modifier = Modifier.size(18.dp)) }} else null, modifier = modifier.fillMaxWidth(), readOnly = readOnly, singleLine = singleLine, shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, focusedTextColor = White, unfocusedTextColor = White, focusedLabelColor = Gold, unfocusedLabelColor = Gray400))
}

@Composable
fun InfoRow(label: String, value: String, icon: ImageVector? = null, valueColor: Color = White) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
        if (icon != null) { Icon(icon, null, tint = Gray400, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(8.dp)) }
        Text(label, color = Gray400, fontSize = 13.sp, modifier = Modifier.weight(1f))
        Text(value, color = valueColor, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
    }
}
