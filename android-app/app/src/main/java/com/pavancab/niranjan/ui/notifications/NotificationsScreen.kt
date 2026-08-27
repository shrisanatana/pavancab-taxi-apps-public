package com.pavancab.niranjan.ui.notifications

import androidx.compose.foundation.BorderStroke
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.gson.JsonObject
import com.google.gson.JsonNull
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.launch

private fun jStr(o: JsonObject, vararg keys: String): String {
    for (k in keys) {
        val el = o.get(k)
        if (el != null && el !is JsonNull) {
            val s = try { el.asString } catch (_: Exception) { "" }
            if (s.isNotBlank()) return s
        }
    }
    return ""
}

@Composable
fun NotificationsScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var items by remember { mutableStateOf<List<JsonObject>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }

    LaunchedEffect(Unit) {
        scope.launch {
            items = Repository(context).getNotificationHistory()
            loading = false
        }
    }

    Scaffold(
        containerColor = DarkBg,
        topBar = {
            Surface(color = DarkBgLighter) {
                Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 4.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) }
                    Text("NOTIFICATIONS", color = White, fontSize = 17.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f))
                }
            }
        }
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            when {
                loading -> Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
                items.isEmpty() -> Column(
                    modifier = Modifier.fillMaxSize().padding(32.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Icon(Icons.Default.NotificationsNone, null, tint = Gray600, modifier = Modifier.size(64.dp))
                    Spacer(Modifier.height(14.dp))
                    Text("No notifications yet", color = Gray400, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                    Text("Ride updates and offers will appear here", color = Gray500, fontSize = 12.sp)
                }
                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(items.size) { i ->
                        val n = items[items.size - 1 - i] // newest first
                        val title = jStr(n, "title", "heading")
                        val body = jStr(n, "message", "body", "text", "description")
                        val time = jStr(n, "created_at", "time", "timestamp")
                        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                            Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.Top) {
                                Surface(modifier = Modifier.size(34.dp), shape = RoundedCornerShape(17.dp), color = Gold.copy(alpha = 0.1f)) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(Icons.Default.Notifications, null, tint = Gold, modifier = Modifier.size(16.dp))
                                    }
                                }
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) {
                                    Text(title.ifBlank { "PAVANCAB Update" }, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                                    if (body.isNotBlank()) {
                                        Spacer(Modifier.height(2.dp))
                                        Text(body, color = Gray400, fontSize = 12.sp, lineHeight = 16.sp)
                                    }
                                    if (time.isNotBlank()) {
                                        Spacer(Modifier.height(4.dp))
                                        Text(time.take(16).replace('T', ' '), color = Gray600, fontSize = 9.sp)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
