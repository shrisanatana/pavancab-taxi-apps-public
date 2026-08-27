package com.pavancab.niranjan.ui

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.SystemUpdate
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import com.pavancab.niranjan.data.UpdateInfo

@Composable
fun UpdateDialog(
    info: UpdateInfo,
    onRemindLater: () -> Unit,
    onDismiss: () -> Unit
) {
    val context = LocalContext.current
    val Gold = androidx.compose.ui.graphics.Color(0xFFF59E0B)
    val White = androidx.compose.ui.graphics.Color.White
    AlertDialog(
        onDismissRequest = {
            if (!info.forceUpdate) onDismiss()
        },
        containerColor = androidx.compose.ui.graphics.Color(0xFF141C2B),
        icon = {
            Icon(Icons.Default.SystemUpdate, null, tint = Gold, modifier = Modifier.size(44.dp))
        },
        title = {
            Text(
                if (info.forceUpdate) "Update Required" else "New Update Available",
                color = White,
                fontWeight = FontWeight.Black,
                fontSize = 20.sp
            )
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(
                    if (info.latestVersionName.isNotBlank()) "PavanCab v${info.latestVersionName}" else "PavanCab",
                    color = Gold,
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 13.sp
                )
                Text(
                    info.message,
                    color = White.copy(alpha = 0.85f),
                    fontSize = 14.sp,
                    lineHeight = 20.sp
                )
                if (info.forceUpdate) {
                    Text(
                        "This version is required to continue using the app.",
                        color = androidx.compose.ui.graphics.Color(0xFFFF8A80),
                        fontSize = 13.sp,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth().padding(top = 4.dp)
                    )
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    try {
                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(info.playStoreUrl)))
                    } catch (_: Exception) {}
                },
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = androidx.compose.ui.graphics.Color(0xFF070A12))
            ) {
                Text("Update Now", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            if (!info.forceUpdate) {
                TextButton(onClick = onRemindLater) {
                    Text("Later", color = White.copy(alpha = 0.7f))
                }
            }
        }
    )
}
