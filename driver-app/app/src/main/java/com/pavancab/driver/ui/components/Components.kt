package com.pavancab.driver.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.pavancab.driver.ui.theme.*
import java.util.Locale

@Composable
fun GradientButton(
    onClick: () -> Unit,
    text: String,
    icon: ImageVector? = null,
    enabled: Boolean = true,
    color: Color = Gold
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        modifier = Modifier.fillMaxWidth().height(52.dp),
        shape = RoundedCornerShape(14.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor = color,
            contentColor = DarkBg,
            disabledContainerColor = color.copy(alpha = 0.4f),
            disabledContentColor = DarkBg.copy(alpha = 0.5f)
        )
    ) {
        if (icon != null) {
            Icon(icon, contentDescription = null, modifier = Modifier.size(20.dp))
            Spacer(Modifier.width(8.dp))
        }
        Text(text, fontSize = 16.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
fun StatusBadge(status: String) {
    val key = status.uppercase()
    val color = when {
        key == "PENDING" -> Gold
        key == "ASSIGNED" -> Cyan
        key == "ACCEPTED" -> Blue
        key.contains("IN_TRANSIT") || key.contains("ON_TRIP") -> Orange
        key == "COMPLETED" -> Emerald
        key.contains("CANCELLED") -> Red
        else -> Gray600
    }
    Box(
        modifier = Modifier
            .background(color.copy(alpha = 0.15f), RoundedCornerShape(6.dp))
            .padding(horizontal = 10.dp, vertical = 4.dp)
    ) {
        Text(
            status.replace('_', ' '),
            color = Color.Black,
            fontSize = 10.sp,
            fontWeight = FontWeight.Bold
        )
    }
}

@Composable
fun LoadingOverlay(message: String = "Loading...") {
    Box(
        modifier = Modifier.fillMaxSize().background(Color.Black.copy(alpha = 0.5f)),
        contentAlignment = Alignment.Center
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CircularProgressIndicator(color = Gold)
            Spacer(Modifier.height(12.dp))
            Text(message, color = White, fontSize = 14.sp)
        }
    }
}

@Composable
fun EmptyState(icon: ImageVector, title: String, subtitle: String = "") {
    Box(modifier = Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(icon, contentDescription = null, tint = Gray600, modifier = Modifier.size(56.dp))
            Spacer(Modifier.height(12.dp))
            Text(title, color = Gray400, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
            if (subtitle.isNotEmpty()) {
                Spacer(Modifier.height(4.dp))
                Text(subtitle, color = Gray500, fontSize = 12.sp)
            }
        }
    }
}

@Composable
fun PavanTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    leadingIcon: ImageVector? = null,
    readOnly: Boolean = false,
    modifier: Modifier = Modifier
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        leadingIcon = leadingIcon?.let { { Icon(it, contentDescription = null, tint = Gold) } },
        readOnly = readOnly,
        singleLine = true,
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = Gold,
            unfocusedBorderColor = Gray700,
            disabledBorderColor = Gray700,
            focusedContainerColor = CardBg,
            unfocusedContainerColor = CardBg,
            disabledContainerColor = CardBg,
            cursorColor = Gold,
            focusedLabelColor = Gold,
            unfocusedLabelColor = Gray400
        )
    )
}

fun fmt(v: Double): String = "\u20B9" + String.format(Locale.US, "%,.0f", v)

fun displayCabType(raw: String): String {
    val known = listOf("sedan", "suv", "ertiga", "crysta", "mini", "auto", "bike")
    val lower = raw.trim().lowercase()
    if (lower.isEmpty()) return "Sedan"
    if (known.contains(lower)) return raw.trim().replaceFirstChar { it.uppercase() }
    if (lower.toDoubleOrNull() != null) return "Sedan"
    return raw.trim().uppercase()
}
