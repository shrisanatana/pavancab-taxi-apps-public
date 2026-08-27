package com.pavancab.dispatch.ui.users

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.*
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.model.Booking
import com.pavancab.dispatch.model.FcmToken
import com.pavancab.dispatch.model.UserDetail
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

private val userGson: Gson = GsonBuilder()
    .registerTypeAdapter(String::class.java, object : TypeAdapter<String>() {
        override fun read(`in`: JsonReader): String {
            return if (`in`.peek() == JsonToken.NULL) { `in`.nextNull(); "" } else `in`.nextString()
        }
        override fun write(out: JsonWriter, value: String?) { out.value(value ?: "") }
    })
    .create()

@Composable
private fun TagBadge(text: String, color: Color) {
    Surface(shape = RoundedCornerShape(6.dp), color = color.copy(alpha = 0.15f)) {
        Text(text, color = color, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
    }
}

@Composable
private fun ActionButton(label: String, icon: ImageVector, color: Color, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Surface(
        modifier = modifier.clip(RoundedCornerShape(10.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(10.dp),
        color = color.copy(alpha = 0.1f),
        border = BorderStroke(1.dp, color.copy(alpha = 0.35f))
    ) {
        Row(modifier = Modifier.padding(vertical = 10.dp), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = color, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(label, color = color, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun StatCell(label: String, value: String, color: Color, modifier: Modifier = Modifier) {
    Column(modifier = modifier, horizontalAlignment = Alignment.CenterHorizontally) {
        Text(value, color = color, fontSize = 14.sp, fontWeight = FontWeight.Black, maxLines = 1, overflow = TextOverflow.Ellipsis)
        Spacer(Modifier.height(2.dp))
        Text(label, color = Gray400, fontSize = 10.sp)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UserDetailScreen(
    phone: String,
    email: String,
    userId: Int,
    onBack: () -> Unit,
    onBookingClick: (Int) -> Unit = {},
    onDriverClick: (Int) -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var user by remember { mutableStateOf<UserDetail?>(null) }
    var loading by remember { mutableStateOf(true) }
    var banning by remember { mutableStateOf(false) }

    var showPushDialog by remember { mutableStateOf(false) }
    var pushTitle by remember { mutableStateOf("") }
    var pushBody by remember { mutableStateOf("") }

    suspend fun load() {
        loading = true
        val obj = repo.getUserDetail(phone = phone, email = email, userId = userId)
        user = try {
            val userObj = if (obj.has("user") && obj.get("user").isJsonObject) obj.getAsJsonObject("user") else obj
            val detail = userGson.fromJson(userObj, UserDetail::class.java)
            val bkList = if (obj.has("bookings") && obj.get("bookings").isJsonArray) {
                obj.getAsJsonArray("bookings").map { userGson.fromJson(it, Booking::class.java) }
            } else emptyList()
            val tokList = if (obj.has("fcm_tokens") && obj.get("fcm_tokens").isJsonArray) {
                obj.getAsJsonArray("fcm_tokens").map { userGson.fromJson(it, FcmToken::class.java) }
            } else emptyList()
            detail.copy(bookings = bkList, fcmTokens = tokList)
        } catch (_: Exception) { null }
        loading = false
    }

    LaunchedEffect(userId, phone, email) { load() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("User Details", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Box(modifier = Modifier.fillMaxSize().padding(padding)) {
            when {
                loading -> Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
                user == null -> Column(
                    modifier = Modifier.fillMaxSize().padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.Center
                ) {
                    Icon(Icons.Default.PersonOff, null, tint = Gray600, modifier = Modifier.size(56.dp))
                    Spacer(Modifier.height(12.dp))
                    Text("User Not Found", color = Gray400, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                }
                else -> {
                    val u = user!!
                    LazyColumn(
                        modifier = Modifier.fillMaxSize(),
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp)
                    ) {
                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                ActionButton("Call", Icons.Default.Phone, Emerald, Modifier.weight(1f)) {
                                    context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${u.mobile}")))
                                }
                                ActionButton("WhatsApp", Icons.Default.Chat, Color(0xFF25D366), Modifier.weight(1f)) {
                                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${u.mobile.replace("+", "").replace(" ", "")}")))
                                }
                                ActionButton("Send Push", Icons.Default.Notifications, Gold, Modifier.weight(1f)) {
                                    pushTitle = ""; pushBody = ""; showPushDialog = true
                                }
                            }
                        }
                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                Button(
                                    onClick = {
                                        scope.launch {
                                            banning = true
                                            val r = repo.banUser(u.userId, u.isBanned != 1)
                                            banning = false
                                            Toast.makeText(context, if (r.safeBool("success") == true) (if (u.isBanned == 1) "User unbanned" else "User banned") else r.safeString("error") ?: "Failed", Toast.LENGTH_SHORT).show()
                                            load()
                                        }
                                    },
                                    enabled = !banning,
                                    modifier = Modifier.weight(1f).height(44.dp),
                                    shape = RoundedCornerShape(10.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = if (u.isBanned == 1) Emerald else Red.copy(alpha = 0.15f),
                                        contentColor = if (u.isBanned == 1) DarkBg else Red
                                    )
                                ) {
                                    Icon(if (u.isBanned == 1) Icons.Default.CheckCircle else Icons.Default.Block, null, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(6.dp))
                                    Text(if (u.isBanned == 1) "UNBAN USER" else "BAN USER", fontSize = 12.sp, fontWeight = FontWeight.Black)
                                }
                            }
                        }

                        item {
                            PavanCard {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Box(modifier = Modifier.size(10.dp).clip(CircleShape).background(if (u.isOnline == 1) Emerald else Gray600))
                                    Spacer(Modifier.width(8.dp))
                                    Text(u.name.ifBlank { "Unknown User" }, color = White, fontSize = 17.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    if (u.isBanned == 1) TagBadge("BANNED", Red) else TagBadge(if (u.isOnline == 1) "ONLINE" else "OFFLINE", if (u.isOnline == 1) Emerald else Gray500)
                                }
                                Spacer(Modifier.height(10.dp))
                                InfoRow("Mobile", u.mobile.ifBlank { "\u2014" }, Icons.Default.Phone)
                                InfoRow("Email", u.email.ifBlank { "\u2014" }, Icons.Default.Email)
                                if (u.lastActiveAt.isNotBlank()) InfoRow("Last Active", DateUtils.formatDateTime(u.lastActiveAt), Icons.Default.Schedule)
                                if (u.createdAt.isNotBlank()) InfoRow("Member Since", DateUtils.formatDate(u.createdAt), Icons.Default.CalendarToday)
                            }
                        }

                        item {
                            PavanCard {
                                SectionHeader("Statistics")
                                Spacer(Modifier.height(10.dp))
                                Row(modifier = Modifier.fillMaxWidth()) {
                                    StatCell("Total Bookings", "${u.totalBookings}", White, Modifier.weight(1f))
                                    StatCell("Completed", "${u.completedBookings}", Emerald, Modifier.weight(1f))
                                    StatCell("Cancelled", "${u.cancelledBookings}", Red, Modifier.weight(1f))
                                    StatCell("Total Spent", fmt(u.totalSpent), Gold, Modifier.weight(1f))
                                }
                            }
                        }

                        item {
                            PavanCard {
                                SectionHeader("Devices (${u.fcmTokens.size})", "Registered FCM tokens")
                                Spacer(Modifier.height(10.dp))
                                if (u.fcmTokens.isEmpty()) {
                                    Text("No devices registered", color = Gray500, fontSize = 12.sp)
                                } else {
                                    u.fcmTokens.forEachIndexed { index, token ->
                                        TokenItem(token)
                                        if (index < u.fcmTokens.lastIndex) Spacer(Modifier.height(6.dp))
                                    }
                                }
                            }
                        }

                        item {
                            SectionHeader("Booking History (${u.bookings.size}) \u2022 tap a ride for details")
                        }

                        if (u.bookings.isEmpty()) {
                            item {
                                PavanCard {
                                    Text("No bookings yet", color = Gray500, fontSize = 12.sp)
                                }
                            }
                        } else {
                            items(u.bookings, key = { it.id }) { bk ->
                                Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { onBookingClick(bk.id) }, shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                                    Column(modifier = Modifier.padding(14.dp)) {
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                            StatusBadge(bk.status)
                                        }
                                        Spacer(Modifier.height(6.dp))
                                        Text("${bk.pickupLocation} \u2192 ${bk.dropLocation}", color = Gray400, fontSize = 12.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
                                        Spacer(Modifier.height(2.dp))
                                        Text("${bk.pickupDate} ${bk.pickupTime}", color = Gray500, fontSize = 11.sp)
                                        Spacer(Modifier.height(4.dp))
                                        Row(verticalAlignment = Alignment.CenterVertically) {
                                            Text(bk.cabType, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                                            Text(fmt(bk.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                        }
                                        // Ratings received by THIS user from the driver
                                        if (bk.passengerRating > 0) {
                                            Spacer(Modifier.height(6.dp))
                                            Surface(shape = RoundedCornerShape(6.dp), color = Gold.copy(alpha = 0.08f)) {
                                                Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                                                    Icon(Icons.Default.ThumbUp, null, tint = Gold, modifier = Modifier.size(11.dp))
                                                    Spacer(Modifier.width(4.dp))
                                                    Text("Rated by driver: ", color = Gray400, fontSize = 10.sp)
                                                    Text("\u2605".repeat(bk.passengerRating), color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black)
                                                }
                                            }
                                            if (bk.passengerReview.isNotBlank()) {
                                                Spacer(Modifier.height(3.dp))
                                                Text("\u201C${bk.passengerReview}\u201D", color = Gray500, fontSize = 10.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
                                            }
                                        }
                                        if (bk.driverName.isNotBlank()) {
                                            Spacer(Modifier.height(6.dp))
                                            Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable { if (bk.driverId > 0) onDriverClick(bk.driverId) }, color = Gray900) {
                                                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
                                                    Icon(Icons.Default.Person, null, tint = Emerald, modifier = Modifier.size(13.dp))
                                                    Spacer(Modifier.width(6.dp))
                                                    Text("Driver: ${bk.driverName}", color = White, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                                                    Text("VIEW \u203A", color = Emerald, fontSize = 9.sp, fontWeight = FontWeight.Black)
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
        }

        if (showPushDialog) {
            val target = user
            AlertDialog(
                onDismissRequest = { showPushDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("Send Push to ${target?.name?.ifBlank { null } ?: target?.mobile ?: "User"}", color = Gold, fontWeight = FontWeight.Bold, fontSize = 14.sp) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Send a personal notification to this user's devices.", color = Gray400, fontSize = 12.sp)
                        OutlinedTextField(
                            value = pushTitle, onValueChange = { pushTitle = it },
                            label = { Text("Title *") },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp), singleLine = true,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                        OutlinedTextField(
                            value = pushBody, onValueChange = { pushBody = it },
                            label = { Text("Message *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 100.dp),
                            shape = RoundedCornerShape(12.dp), minLines = 4, maxLines = 8,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        if (pushTitle.isBlank() || pushBody.isBlank()) {
                            Toast.makeText(context, "Title and message required", Toast.LENGTH_SHORT).show()
                            return@TextButton
                        }
                        val t = target ?: return@TextButton
                        showPushDialog = false
                        scope.launch {
                            val r = repo.sendPersonalPush(t.mobile, t.email, pushTitle, pushBody)
                            val msg = if (r.safeBool("success") == true) "Push sent!" else r.safeString("error") ?: "Failed to send"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        }
                    }) { Text("Send", color = Gold) }
                },
                dismissButton = { TextButton(onClick = { showPushDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }
    }
}

@Composable
private fun TokenItem(token: FcmToken) {
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) {
        Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(modifier = Modifier.size(8.dp).clip(CircleShape).background(if (token.isOnline == 1) Emerald else Gray600))
            Spacer(Modifier.width(8.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(token.deviceInfo.ifBlank { "Unknown Device" }, color = White, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(token.fcmToken, color = Gray500, fontSize = 9.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text("Last active: ${if (token.lastActiveAt.isNotBlank()) DateUtils.formatDateTime(token.lastActiveAt) else "unknown"}", color = Gray500, fontSize = 9.sp)
            }
            if (token.isOnline == 1) TagBadge("ONLINE", Emerald)
        }
    }
}

@Composable
private fun DetailBookingCard(bk: Booking) {
    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                StatusBadge(bk.status)
            }
            Spacer(Modifier.height(6.dp))
            Text("${bk.pickupLocation} \u2192 ${bk.dropLocation}", color = Gray400, fontSize = 12.sp, maxLines = 2, overflow = TextOverflow.Ellipsis)
            Spacer(Modifier.height(2.dp))
            Text("${bk.pickupDate} ${bk.pickupTime}", color = Gray500, fontSize = 11.sp)
            Spacer(Modifier.height(4.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(bk.cabType, color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                Text(fmt(bk.totalFare), color = Gold, fontSize = 15.sp, fontWeight = FontWeight.Bold)
            }
            if (bk.driverName.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text("Driver: ${bk.driverName}", color = Emerald, fontSize = 11.sp)
            }
        }
    }
}
