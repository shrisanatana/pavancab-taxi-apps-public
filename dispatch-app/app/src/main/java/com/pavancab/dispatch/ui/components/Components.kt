package com.pavancab.dispatch.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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
import com.pavancab.dispatch.ui.theme.*
import java.util.Locale

data class CountryCode(val code: String, val name: String)

val countryCodes = listOf(
    CountryCode("+91", "India"), CountryCode("+44", "United Kingdom"), CountryCode("+1", "USA / Canada"),
    CountryCode("+971", "UAE"), CountryCode("+7", "Russia"), CountryCode("+49", "Germany"),
    CountryCode("+61", "Australia"), CountryCode("+33", "France"), CountryCode("+65", "Singapore"),
    CountryCode("+966", "Saudi Arabia"), CountryCode("+974", "Qatar"), CountryCode("+968", "Oman"),
    CountryCode("+965", "Kuwait"), CountryCode("+973", "Bahrain"), CountryCode("+66", "Thailand"),
    CountryCode("+60", "Malaysia"), CountryCode("+39", "Italy"), CountryCode("+34", "Spain"),
    CountryCode("+31", "Netherlands"), CountryCode("+41", "Switzerland"), CountryCode("+972", "Israel"),
    CountryCode("+81", "Japan"), CountryCode("+86", "China"), CountryCode("+880", "Bangladesh"),
    CountryCode("+977", "Nepal"), CountryCode("+94", "Sri Lanka")
)

@Composable
fun CountryCodePicker(
    selectedCode: String,
    onCodeSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    var expanded by remember { mutableStateOf(false) }
    Surface(modifier = modifier.clip(RoundedCornerShape(12.dp)).clickable { expanded = !expanded }, shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, if (expanded) Gold else Gray700)) {
        Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(selectedCode, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.width(4.dp))
            Icon(Icons.Default.ArrowDropDown, contentDescription = null, tint = Gray400, modifier = Modifier.size(18.dp))
        }
    }
    AnimatedVisibility(visible = expanded) {
        Surface(modifier = modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) {
            Column(modifier = Modifier.heightIn(max = 240.dp).verticalScroll(rememberScrollState()).padding(vertical = 4.dp)) {
                countryCodes.forEach { cc ->
                    Row(modifier = Modifier.fillMaxWidth().clickable { onCodeSelected(cc.code); expanded = false }.padding(horizontal = 16.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(cc.code, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.width(12.dp))
                        Text(cc.name, color = Gray400, fontSize = 13.sp)
                        Spacer(Modifier.weight(1f))
                        if (cc.code == selectedCode) { Icon(Icons.Default.Check, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp)) }
                    }
                }
            }
        }
    }
}

@Composable
fun GradientButton(
    onClick: () -> Unit,
    text: String,
    icon: ImageVector? = null,
    enabled: Boolean = true
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        modifier = Modifier.fillMaxWidth().height(52.dp),
        shape = RoundedCornerShape(14.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor = Gold,
            contentColor = DarkBg,
            disabledContainerColor = Gold.copy(alpha = 0.4f),
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
fun PavanCard(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit
) {
    Surface(
        modifier = modifier,
        color = CardBg,
        shape = RoundedCornerShape(16.dp),
        border = BorderStroke(1.dp, CardBorder)
    ) {
        Column(modifier = Modifier.padding(16.dp), content = content)
    }
}

@Composable
fun SectionHeader(title: String, subtitle: String? = null) {
    Column {
        Text(title, color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
        if (subtitle != null && subtitle.isNotEmpty()) {
            Text(subtitle, color = Gray400, fontSize = 12.sp, modifier = Modifier.padding(top = 2.dp))
        }
    }
}

@Composable
fun InfoRow(
    label: String,
    value: String,
    icon: ImageVector? = null,
    valueColor: Color = White
) {
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(vertical = 4.dp)) {
        if (icon != null) {
            Icon(icon, contentDescription = null, tint = Gray400, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
        }
        Text(label, color = Gray400, fontSize = 13.sp, modifier = Modifier.width(110.dp))
        Text(value, color = valueColor, fontSize = 13.sp, fontWeight = FontWeight.Medium, modifier = Modifier.weight(1f))
    }
}

@Composable
fun StatusBadge(status: String) {
    val key = status.uppercase()
    val color = when {
        key.startsWith("CANCELLED") -> StatusCancelled
        key.contains("IN_TRANSIT") || key.contains("ON_TRIP") -> StatusInTransit
        else -> when (key) {
            "PENDING" -> StatusPending
            "CONFIRMED" -> StatusConfirmed
            "ASSIGNED" -> StatusAssigned
            "ACCEPTED" -> StatusAccepted
            "COMPLETED" -> StatusCompleted
            else -> Gray600
        }
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
